<?php
// app/Controllers/Admin/AuditLogController.php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\AuditLog;
use App\Models\SecurityEvent;
use App\Models\User;

/**
 * Yönetim: denetim günlüğü ve güvenlik olayları (yalnızca audit.view). SADECE OKUMA.
 */
final class AuditLogController extends AdminBaseController
{
    private const PERMISSION = 'audit.view';
    private const EXPORT_PER_PAGE = 100;
    private const EXPORT_MAX_PAGES = 50;
    private const SEVERITIES = ['info', 'warning', 'critical'];

    public function index(Request $request): Response
    {
        $this->require(self::PERMISSION);

        [$filters, $form] = $this->auditFilters($request);
        $result = (new AuditLog())->search($filters, $this->currentPage($request), $this->perPage());

        $items = [];
        foreach ($result['items'] as $row) {
            $row['has_values'] = array_key_exists('old_values', $row) || array_key_exists('new_values', $row);
            $row['diff'] = $this->buildDiff($row['old_values'] ?? null, $row['new_values'] ?? null);
            // Ham JSON görünüme taşınmaz; yalnızca temizlenmiş fark gider.
            unset($row['old_values'], $row['new_values']);
            $items[] = $row;
        }

        return $this->view('admin.audit.index', [
            'title' => 'Denetim günlüğü',
            'logs' => $items,
            'pager' => $this->pager($result, $request),
            'filters' => $form,
        ]);
    }

    /** Tek kaydın eski/yeni farkı (JSON). */
    public function show(Request $request, int $id): Response
    {
        $this->require(self::PERMISSION);

        $log = (new AuditLog())->find($id);
        if ($log === null) {
            throw new HttpException(404, 'Kayıt bulunamadı.');
        }

        $actor = null;
        if (!empty($log['user_id'])) {
            $user = (new User())->withRole((int) $log['user_id']);
            if ($user !== null) {
                $actor = [
                    'id' => (int) $user['id'],
                    'name' => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
                    'email' => (string) ($user['email'] ?? ''),
                ];
            }
        }

        return $this->ok([
            'id' => (int) $log['id'],
            'created_at' => (string) $log['created_at'],
            'user' => $actor,
            'action' => (string) $log['action'],
            'entity_type' => $log['entity_type'] ?? null,
            'entity_id' => isset($log['entity_id']) ? (int) $log['entity_id'] : null,
            'ip' => bin_to_ip($log['ip_address'] ?? null),
            'user_agent' => $log['user_agent'] ?? null,
            'diff' => $this->buildDiff($log['old_values'] ?? null, $log['new_values'] ?? null),
        ]);
    }

    public function securityEvents(Request $request): Response
    {
        $this->require(self::PERMISSION);

        [$filters, $form] = $this->securityFilters($request);
        $events = new SecurityEvent();
        $result = $events->search($filters, $this->currentPage($request), $this->perPage());

        $since = date('Y-m-d H:i:s', time() - 86400);
        $summary = [
            'critical' => (int) $events->search(['severity' => 'critical', 'date_from' => $since], 1, 1)['total'],
            'warning' => (int) $events->search(['severity' => 'warning', 'date_from' => $since], 1, 1)['total'],
            'total' => (int) $events->search(['date_from' => $since], 1, 1)['total'],
        ];

        return $this->view('admin.audit.security', [
            'title' => 'Güvenlik olayları',
            'events' => $result['items'],
            'pager' => $this->pager($result, $request),
            'filters' => $form,
            'summary' => $summary,
            'severities' => self::SEVERITIES,
        ]);
    }

    public function export(Request $request): Response
    {
        $this->require(self::PERMISSION);

        [$filters, $form] = $this->auditFilters($request);
        $model = new AuditLog();
        $rows = $this->collect(static fn (int $page): array => $model->search($filters, $page, self::EXPORT_PER_PAGE));

        $lines = [];
        foreach ($rows as $row) {
            $lines[] = [
                (string) ($row['id'] ?? ''),
                (string) ($row['created_at'] ?? ''),
                (string) ($row['user_email'] ?? ''),
                (string) ($row['action'] ?? ''),
                (string) ($row['entity_type'] ?? ''),
                (string) ($row['entity_id'] ?? ''),
                bin_to_ip($row['ip_address'] ?? null),
                (string) ($row['old_values'] ?? ''),
                (string) ($row['new_values'] ?? ''),
                (string) ($row['user_agent'] ?? ''),
            ];
        }

        // Dışa aktarma da bir veri çekme eylemidir; iç tehdit izi için kaydedilir.
        $this->audit('audit.export', 'audit_log', null, null, ['filters' => $form, 'rows' => count($lines)]);

        return $this->csvResponse(
            'denetim-' . date('Ymd-His') . '.csv',
            ['id', 'tarih', 'kullanici_eposta', 'islem', 'varlik_turu', 'varlik_id', 'ip', 'eski_degerler', 'yeni_degerler', 'tarayici'],
            $lines
        );
    }

    public function exportSecurity(Request $request): Response
    {
        $this->require(self::PERMISSION);

        [$filters, $form] = $this->securityFilters($request);
        $model = new SecurityEvent();
        $rows = $this->collect(static fn (int $page): array => $model->search($filters, $page, self::EXPORT_PER_PAGE));

        $lines = [];
        foreach ($rows as $row) {
            $lines[] = [
                (string) ($row['id'] ?? ''),
                (string) ($row['created_at'] ?? ''),
                (string) ($row['type'] ?? ''),
                (string) ($row['severity'] ?? ''),
                (string) ($row['user_email'] ?? ''),
                bin_to_ip($row['ip_address'] ?? null),
                (string) ($row['detail'] ?? ''),
            ];
        }

        $this->audit('audit.export_security', 'security_event', null, null, ['filters' => $form, 'rows' => count($lines)]);

        return $this->csvResponse(
            'guvenlik-olaylari-' . date('Ymd-His') . '.csv',
            ['id', 'tarih', 'tur', 'onem', 'kullanici_eposta', 'ip', 'ayrinti'],
            $lines
        );
    }

    /**
     * @param callable(int):array<string,mixed> $fetch
     * @return list<array<string,mixed>>
     */
    private function collect(callable $fetch): array
    {
        $all = [];
        for ($page = 1; $page <= self::EXPORT_MAX_PAGES; $page++) {
            $result = $fetch($page);
            foreach ($result['items'] as $row) {
                $all[] = $row;
            }
            if ($page >= (int) $result['pages']) {
                break;
            }
        }

        return $all;
    }

    /** @param list<string> $header @param list<list<string>> $lines */
    private function csvResponse(string $filename, array $header, array $lines): Response
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new HttpException(500, 'Dışa aktarma başarısız.');
        }
        fwrite($handle, "\xEF\xBB\xBF"); // Excel için UTF-8 BOM
        fputcsv($handle, $header, ',', '"', '\\');
        foreach ($lines as $line) {
            fputcsv($handle, array_map([$this, 'csvSafe'], $line), ',', '"', '\\');
        }
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return Response::text($csv)
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withHeader('Cache-Control', 'no-store');
    }

    /** CSV formül enjeksiyonu: =, +, -, @, TAB, CR ile başlayan hücreler kesme işaretiyle etkisizleştirilir. */
    private function csvSafe(string $value): string
    {
        if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value) === 1) {
            return "'" . $value;
        }

        return $value;
    }

    /** @return array{0:array<string,mixed>,1:array<string,string>} [model filtreleri, form değerleri] */
    private function auditFilters(Request $request): array
    {
        $filters = [];
        $form = ['user_id' => '', 'action' => '', 'entity_type' => '', 'date_from' => '', 'date_to' => ''];

        $userId = (string) $request->query('user_id', '');
        if (ctype_digit($userId) && (int) $userId > 0) {
            $filters['user_id'] = (int) $userId;
            $form['user_id'] = $userId;
        }
        $action = trim((string) $request->query('action', ''));
        if (preg_match('/^[a-z0-9_.]{1,100}$/', $action) === 1) {
            $filters['action'] = $action;
            $form['action'] = $action;
        }
        $entity = trim((string) $request->query('entity_type', ''));
        if (preg_match('/^[a-z0-9_]{1,60}$/', $entity) === 1) {
            $filters['entity_type'] = $entity;
            $form['entity_type'] = $entity;
        }
        $this->applyDates($request, $filters, $form);

        return [$filters, $form];
    }

    /** @return array{0:array<string,mixed>,1:array<string,string>} */
    private function securityFilters(Request $request): array
    {
        $filters = [];
        $form = ['type' => '', 'severity' => '', 'user_id' => '', 'date_from' => '', 'date_to' => ''];

        $type = trim((string) $request->query('type', ''));
        if (preg_match('/^[a-z0-9_.\-]{1,60}$/', $type) === 1) {
            $filters['type'] = $type;
            $form['type'] = $type;
        }
        $severity = (string) $request->query('severity', '');
        if (in_array($severity, self::SEVERITIES, true)) {
            $filters['severity'] = $severity;
            $form['severity'] = $severity;
        }
        $userId = (string) $request->query('user_id', '');
        if (ctype_digit($userId) && (int) $userId > 0) {
            $filters['user_id'] = (int) $userId;
            $form['user_id'] = $userId;
        }
        $this->applyDates($request, $filters, $form);

        return [$filters, $form];
    }

    /** @param array<string,mixed> $filters @param array<string,string> $form */
    private function applyDates(Request $request, array &$filters, array &$form): void
    {
        foreach (['date_from' => ' 00:00:00', 'date_to' => ' 23:59:59'] as $key => $suffix) {
            $raw = trim((string) $request->query($key, ''));
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
            if ($date !== false && $date->format('Y-m-d') === $raw) {
                $filters[$key] = $raw . $suffix;
                $form[$key] = $raw;
            }
        }
    }

    /** @return list<array{field:string,old:string,new:string}> */
    private function buildDiff(mixed $old, mixed $new): array
    {
        $oldArr = $this->decode($old);
        $newArr = $this->decode($new);
        $keys = array_values(array_unique(array_merge(array_keys($oldArr), array_keys($newArr))));

        $diff = [];
        foreach ($keys as $key) {
            $hasOld = array_key_exists($key, $oldArr);
            $hasNew = array_key_exists($key, $newArr);
            if ($hasOld && $hasNew && json_encode($oldArr[$key]) === json_encode($newArr[$key])) {
                continue;
            }
            $diff[] = [
                'field' => (string) $key,
                'old' => $hasOld ? $this->stringify($oldArr[$key]) : '—',
                'new' => $hasNew ? $this->stringify($newArr[$key]) : '—',
            ];
        }

        return $diff;
    }

    /** @return array<string|int,mixed> */
    private function decode(mixed $json): array
    {
        if (!is_string($json) || $json === '') {
            return [];
        }
        $data = json_decode($json, true);

        return is_array($data) ? $data : [];
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_scalar($value)) {
            $text = (string) $value;
        } else {
            $text = (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return mb_strimwidth($text, 0, 500, '…');
    }
}