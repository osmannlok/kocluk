<?php
// app/Core/DbSessionHandler.php
declare(strict_types=1);

namespace App\Core;

use SessionHandlerInterface;
use SessionUpdateTimestampHandlerInterface;
use Throwable;

final class DbSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    private Database $db;
    private int $lifetime;

    public function __construct(int $lifetimeSeconds)
    {
        $this->db = Database::instance();
        $this->lifetime = max(60, $lifetimeSeconds);
    }

    // Oturum kimliği DB'ye ham değil SHA-256 özeti olarak yazılır: DB sızarsa oturumlar ele geçirilemez.
    private function key(string $id): string
    {
        return hash('sha256', $id);
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        try {
            $payload = $this->db->fetchValue(
                'SELECT payload FROM sessions WHERE id = :id AND last_activity >= :min',
                ['id' => $this->key($id), 'min' => time() - $this->lifetime]
            );
            return is_string($payload) ? $payload : '';
        } catch (Throwable $e) {
            Logger::error('Oturum okunamadı', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function write(string $id, string $data): bool
    {
        try {
            // write() sırasında $_SESSION hâlâ doludur; auth kimliği buradan alınır.
            $raw = $_SESSION['auth_user_id'] ?? null;
            $userId = is_int($raw) ? $raw : ((is_string($raw) && ctype_digit($raw)) ? (int) $raw : null);
            $request = Request::capture();

            $this->db->execute(
                'INSERT INTO sessions (id, user_id, payload, ip_address, user_agent, last_activity)
                 VALUES (:id, :uid, :payload, :ip, :ua, :now)
                 ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), payload = VALUES(payload),
                    ip_address = VALUES(ip_address), user_agent = VALUES(user_agent),
                    last_activity = VALUES(last_activity)',
                [
                    'id' => $this->key($id),
                    'uid' => $userId,
                    'payload' => $data,
                    'ip' => ip_to_bin($request->ip()),
                    'ua' => mb_substr($request->userAgent(), 0, 255),
                    'now' => time(),
                ]
            );
            return true;
        } catch (Throwable $e) {
            Logger::error('Oturum yazılamadı', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        try {
            $this->db->execute('DELETE FROM sessions WHERE id = :id', ['id' => $this->key($id)]);
            return true;
        } catch (Throwable $e) {
            Logger::error('Oturum silinemedi', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function gc(int $max_lifetime): int|false
    {
        try {
            $limit = max($max_lifetime, $this->lifetime);
            return $this->db->execute('DELETE FROM sessions WHERE last_activity < :t', ['t' => time() - $limit]);
        } catch (Throwable $e) {
            Logger::error('Oturum temizliği başarısız', ['error' => $e->getMessage()]);
            return false;
        }
    }

    // Strict mode: istemcinin uydurduğu kimlik DB'de yoksa PHP yeni kimlik üretir (session fixation koruması).
    public function validateId(string $id): bool
    {
        try {
            $v = $this->db->fetchValue(
                'SELECT 1 FROM sessions WHERE id = :id AND last_activity >= :min',
                ['id' => $this->key($id), 'min' => time() - $this->lifetime]
            );
            return $v !== null && $v !== false;
        } catch (Throwable $e) {
            Logger::error('Oturum kimliği doğrulanamadı', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        try {
            $this->db->execute(
                'UPDATE sessions SET last_activity = :now WHERE id = :id',
                ['now' => time(), 'id' => $this->key($id)]
            );
            return true;
        } catch (Throwable $e) {
            Logger::error('Oturum zamanı güncellenemedi', ['error' => $e->getMessage()]);
            return false;
        }
    }
}