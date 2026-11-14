<?php
// app/Models/BaseModel.php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use InvalidArgumentException;
use LogicException;
use Stringable;

/**
 * Tüm modellerin tabanı. SQL yalnızca modellerde yazılır; değerler her zaman
 * parametre ile, kolon/sıralama adları ise beyaz liste ile bağlanır.
 */
abstract class BaseModel
{
    /** where() için izinli operatörler (beyaz liste). */
    protected const OPERATORS = ['=', '!=', '>', '>=', '<', '<=', 'LIKE', 'IN', 'IS NULL', 'IS NOT NULL'];

    protected const MAX_PER_PAGE = 100;
    protected const MAX_PAGE = 1000000;
    protected const MAX_ALL_LIMIT = 10000;

    /** LIKE kaçış karakteri; escapeLike() ile birlikte kullanılır. */
    protected const LIKE_ESCAPE = '#';

    protected string $table = '';
    protected string $primaryKey = 'id';

    /** @var list<string> */
    protected array $fillable = [];

    protected bool $softDeletes = false;

    /** @var list<string> Boşsa bilinen kolonların tamamı sıralanabilir kabul edilir. */
    protected array $sortable = [];

    /** Eklemeli (append-only) tablo: update/softDelete/forceDelete yasak. */
    protected bool $appendOnly = false;

    /** @var list<string> fillable dışında bilinen kolonlar (created_at vb.). */
    protected array $extraColumns = [];

    protected Database $db;

    public function __construct()
    {
        if ($this->table === '' || preg_match('/^[a-z0-9_]+$/', $this->table) !== 1) {
            throw new LogicException(static::class . ': tablo adı tanımlı değil.');
        }
        $this->db = Database::instance();
    }

    // ------------------------------------------------------------------
    // Sözleşme metotları
    // ------------------------------------------------------------------

    public function find(int $id): ?array
    {
        $sql = 'SELECT * FROM `' . $this->table . '` WHERE `' . $this->primaryKey . '` = :id'
            . ($this->softDeletes ? ' AND `deleted_at` IS NULL' : '')
            . ' LIMIT 1';

        return $this->db->fetchOne($sql, [':id' => $id]);
    }

    public function findBy(string $column, mixed $value): ?array
    {
        $col = $this->resolveColumn($column, $this->knownColumns());
        $params = [];

        if ($value === null) {
            $sql = 'SELECT * FROM `' . $this->table . '` WHERE ' . $col . ' IS NULL';
        } else {
            $sql = 'SELECT * FROM `' . $this->table . '` WHERE ' . $col . ' = :v';
            $params[':v'] = $this->bindable($value);
        }
        if ($this->softDeletes) {
            $sql .= ' AND `' . $this->table . '`.`deleted_at` IS NULL';
        }

        return $this->db->fetchOne($sql . ' LIMIT 1', $params);
    }

    public function all(array $where = [], ?string $orderBy = null, ?int $limit = null): array
    {
        [$whereSql, $params] = $this->buildWhere($where);

        $sql = 'SELECT * FROM `' . $this->table . '` WHERE ' . $whereSql;
        $order = $this->buildOrderBy($orderBy);
        if ($order !== '') {
            $sql .= ' ORDER BY ' . $order;
        }
        if ($limit !== null) {
            $sql .= ' LIMIT ' . $this->clamp($limit, 1, self::MAX_ALL_LIMIT);
        }

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * @return array{items: list<array>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginate(int $page, int $perPage, array $where = [], string $orderBy = 'id DESC'): array
    {
        [$whereSql, $params] = $this->buildWhere($where);

        return $this->paginateRaw(
            '`' . $this->table . '`.*',
            '`' . $this->table . '`',
            $whereSql,
            $params,
            $this->buildOrderBy($orderBy),
            $page,
            $perPage
        );
    }

    public function count(array $where = []): int
    {
        [$whereSql, $params] = $this->buildWhere($where);

        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM `' . $this->table . '` WHERE ' . $whereSql,
            $params
        );
    }

    public function create(array $data): int
    {
        // Toplu atama koruması: yalnızca fillable alanlar yazılır.
        $row = $this->onlyFillable($data);
        if ($row === []) {
            throw new InvalidArgumentException('Kaydedilecek alan bulunamadı.');
        }

        $columns = [];
        $holders = [];
        $params = [];
        foreach ($row as $column => $value) {
            $columns[] = '`' . $column . '`';
            $holders[] = ':c_' . $column;
            $params[':c_' . $column] = $this->bindable($value);
        }

        return $this->db->insert(
            'INSERT INTO `' . $this->table . '` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $holders) . ')',
            $params
        );
    }

    public function update(int $id, array $data): bool
    {
        $this->assertMutable('update');

        $row = $this->onlyFillable($data);
        if ($row === []) {
            return false;
        }

        $sets = [];
        $params = [':pk_id' => $id];
        foreach ($row as $column => $value) {
            $sets[] = '`' . $column . '` = :u_' . $column;
            $params[':u_' . $column] = $this->bindable($value);
        }

        $sql = 'UPDATE `' . $this->table . '` SET ' . implode(', ', $sets)
            . ' WHERE `' . $this->primaryKey . '` = :pk_id'
            . ($this->softDeletes ? ' AND `deleted_at` IS NULL' : '');

        $affected = $this->db->execute($sql, $params);

        // Değerler aynıysa etkilenen satır 0 olur; kayıt varsa yine de başarılı say.
        return $affected > 0 || $this->find($id) !== null;
    }

    public function softDelete(int $id): bool
    {
        $this->assertMutable('softDelete');
        if (!$this->softDeletes) {
            throw new LogicException(static::class . ' soft delete desteklemiyor.');
        }

        return $this->db->execute(
            'UPDATE `' . $this->table . '` SET `deleted_at` = NOW() WHERE `' . $this->primaryKey . '` = :id AND `deleted_at` IS NULL',
            [':id' => $id]
        ) > 0;
    }

    public function restore(int $id): bool
    {
        $this->assertMutable('restore');
        if (!$this->softDeletes) {
            throw new LogicException(static::class . ' soft delete desteklemiyor.');
        }

        return $this->db->execute(
            'UPDATE `' . $this->table . '` SET `deleted_at` = NULL WHERE `' . $this->primaryKey . '` = :id AND `deleted_at` IS NOT NULL',
            [':id' => $id]
        ) > 0;
    }

    public function forceDelete(int $id): bool
    {
        $this->assertMutable('forceDelete');

        return $this->db->execute(
            'DELETE FROM `' . $this->table . '` WHERE `' . $this->primaryKey . '` = :id',
            [':id' => $id]
        ) > 0;
    }

    // ------------------------------------------------------------------
    // Alt sınıflar için yardımcılar
    // ------------------------------------------------------------------

    /**
     * Sayfalı sorgu: COUNT + LIMIT/OFFSET. Tüm SQL parçaları çağıran tarafından
     * beyaz listeden üretilmiş olmalıdır; değerler $params ile bağlanır.
     *
     * @return array{items: list<array>, total: int, page: int, per_page: int, pages: int}
     */
    protected function paginateRaw(
        string $columns,
        string $from,
        string $whereSql,
        array $params,
        string $orderBySql,
        int $page,
        int $perPage
    ): array {
        $perPage = $this->clamp($perPage, 1, self::MAX_PER_PAGE);
        $page = $this->clamp($page, 1, self::MAX_PAGE);

        $total = (int) $this->db->fetchValue('SELECT COUNT(*) FROM ' . $from . ' WHERE ' . $whereSql, $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $offset = ($page - 1) * $perPage;

        $sql = 'SELECT ' . $columns . ' FROM ' . $from . ' WHERE ' . $whereSql
            . ($orderBySql !== '' ? ' ORDER BY ' . $orderBySql : '')
            . ' LIMIT ' . $perPage . ' OFFSET ' . $offset;

        return [
            'items' => $this->db->fetchAll($sql, $params),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
        ];
    }

    /**
     * IN (...) ifadesi üretir. Boş dizi güvenlidir: '1=0' (hiçbir satır eşleşmez).
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    protected function inClause(string $columnSql, array $values, string $prefix): array
    {
        if ($values === []) {
            return ['1=0', []];
        }

        $names = [];
        $params = [];
        foreach (array_values($values) as $i => $value) {
            $name = ':' . $prefix . '_' . $i;
            $names[] = $name;
            $params[$name] = $this->bindable($value);
        }

        return [$columnSql . ' IN (' . implode(', ', $names) . ')', $params];
    }

    /** LIKE değerinde %, _ ve kaçış karakterini kaçışlar (desen sızdırmayı önler). */
    protected static function escapeLike(string $value): string
    {
        return str_replace(
            [self::LIKE_ESCAPE, '%', '_'],
            [self::LIKE_ESCAPE . self::LIKE_ESCAPE, self::LIKE_ESCAPE . '%', self::LIKE_ESCAPE . '_'],
            $value
        );
    }

    /** "kolon LIKE :param ESCAPE '#'" üretir; $columnSql güvenilir (kodda sabit) olmalıdır. */
    protected function likeSql(string $columnSql, string $param): string
    {
        return $columnSql . ' LIKE ' . $param . " ESCAPE '" . self::LIKE_ESCAPE . "'";
    }

    protected function clamp(int $value, int $min, int $max): int
    {
        return max($min, min($value, $max));
    }

    protected function truncate(?string $value, int $max): ?string
    {
        return $value === null ? null : mb_substr($value, 0, $max);
    }

    /** @return list<int> Pozitif, tekil tamsayılar. */
    protected function positiveInts(array $values): array
    {
        $result = [];
        foreach ($values as $value) {
            if (is_int($value) || (is_string($value) && ctype_digit($value))) {
                $int = (int) $value;
                if ($int > 0) {
                    $result[$int] = $int;
                }
            }
        }

        return array_values($result);
    }

    protected function assertDateTime(string $value): void
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value) !== 1) {
            throw new InvalidArgumentException('Tarih biçimi Y-m-d H:i:s olmalıdır.');
        }
    }

    /**
     * İkili IP (VARBINARY) değerini doğrular ve hex'e çevirir. SQL tarafında UNHEX(:param)
     * kullanılır; böylece ikili veri sürücü/charset bağımsız güvenle taşınır.
     */
    protected function ipHex(?string $ipBin): ?string
    {
        if ($ipBin === null) {
            return null;
        }
        $length = strlen($ipBin);
        if ($length !== 4 && $length !== 16) {
            throw new InvalidArgumentException('IP ikili biçimde 4 veya 16 bayt olmalıdır.');
        }

        return bin2hex($ipBin);
    }

    /** @return list<string> */
    protected function knownColumns(): array
    {
        $columns = array_merge([$this->primaryKey], $this->fillable, $this->extraColumns);
        if ($this->softDeletes) {
            $columns[] = 'deleted_at';
        }

        return array_values(array_unique($columns));
    }

    /** @return list<string> */
    protected function sortableColumns(): array
    {
        return $this->sortable !== [] ? $this->sortable : $this->knownColumns();
    }

    protected function bindable(mixed $value): int|float|string|null
    {
        if ($value === null || is_int($value) || is_float($value) || is_string($value)) {
            return $value;
        }
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if ($value instanceof Stringable) {
            return (string) $value;
        }

        throw new InvalidArgumentException('Desteklenmeyen parametre tipi.');
    }

    // ------------------------------------------------------------------
    // İç yardımcılar
    // ------------------------------------------------------------------

    private function assertMutable(string $operation): void
    {
        if ($this->appendOnly) {
            throw new LogicException(static::class . '::' . $operation . '() eklemeli (append-only) tabloda kullanılamaz.');
        }
    }

    /** @return array<string, mixed> */
    private function onlyFillable(array $data): array
    {
        $allowed = [];
        foreach ($this->fillable as $column) {
            if (array_key_exists($column, $data)) {
                $allowed[$column] = $data[$column];
            }
        }

        return $allowed;
    }

    /**
     * Kolon adını biçim (^[a-z0-9_.]+$) ve beyaz liste ile doğrular; tablo-nitelikli,
     * backtick'li biçimde döndürür. "tablo.kolon" yalnızca bu modelin tablosu için kabul edilir.
     */
    private function resolveColumn(string $column, array $allowed): string
    {
        if (preg_match('/^[a-z0-9_.]+$/', $column) !== 1) {
            throw new InvalidArgumentException('Geçersiz kolon adı.');
        }

        $bare = $column;
        if (str_contains($column, '.')) {
            $parts = explode('.', $column);
            if (count($parts) !== 2 || $parts[0] !== $this->table) {
                throw new InvalidArgumentException('Geçersiz kolon adı.');
            }
            $bare = $parts[1];
        }

        if (!in_array($bare, $allowed, true)) {
            throw new InvalidArgumentException('Bilinmeyen kolon: ' . $bare);
        }

        return '`' . $this->table . '`.`' . $bare . '`';
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildWhere(array $where): array
    {
        $clauses = [];
        $params = [];
        $n = 0;

        foreach ($where as $column => $condition) {
            $col = $this->resolveColumn((string) $column, $this->knownColumns());
            $n++;

            if (!is_array($condition)) {
                if ($condition === null) {
                    $clauses[] = $col . ' IS NULL';
                    continue;
                }
                $clauses[] = $col . ' = :w' . $n;
                $params[':w' . $n] = $this->bindable($condition);
                continue;
            }

            $rawOperator = $condition[0] ?? null;
            if (!is_string($rawOperator)) {
                throw new InvalidArgumentException('Operatör belirtilmedi.');
            }
            $operator = strtoupper(trim($rawOperator));
            if (!in_array($operator, self::OPERATORS, true)) {
                throw new InvalidArgumentException('Geçersiz operatör.');
            }

            switch ($operator) {
                case 'IS NULL':
                case 'IS NOT NULL':
                    $clauses[] = $col . ' ' . $operator;
                    break;

                case 'IN':
                    $values = $condition[1] ?? [];
                    if (!is_array($values)) {
                        throw new InvalidArgumentException('IN için dizi gerekir.');
                    }
                    [$sql, $inParams] = $this->inClause($col, $values, 'w' . $n);
                    $clauses[] = $sql;
                    $params += $inParams;
                    break;

                case 'LIKE':
                    $pattern = $condition[1] ?? null;
                    if (!is_string($pattern)) {
                        throw new InvalidArgumentException('LIKE için metin gerekir.');
                    }
                    $clauses[] = $this->likeSql($col, ':w' . $n);
                    $params[':w' . $n] = $pattern;
                    break;

                default:
                    if (!array_key_exists(1, $condition)) {
                        throw new InvalidArgumentException('Karşılaştırma değeri eksik.');
                    }
                    $value = $condition[1];
                    if ($value === null) {
                        if ($operator === '=') {
                            $clauses[] = $col . ' IS NULL';
                        } elseif ($operator === '!=') {
                            $clauses[] = $col . ' IS NOT NULL';
                        } else {
                            throw new InvalidArgumentException('NULL ile bu operatör kullanılamaz.');
                        }
                        break;
                    }
                    $clauses[] = $col . ' ' . $operator . ' :w' . $n;
                    $params[':w' . $n] = $this->bindable($value);
            }
        }

        if ($this->softDeletes) {
            $clauses[] = '`' . $this->table . '`.`deleted_at` IS NULL';
        }

        return [$clauses === [] ? '1=1' : implode(' AND ', $clauses), $params];
    }

    /** "kolon [ASC|DESC], ..." ifadesini beyaz listeye göre doğrular. */
    private function buildOrderBy(?string $orderBy): string
    {
        if ($orderBy === null || trim($orderBy) === '') {
            return '';
        }

        $parts = [];
        foreach (explode(',', $orderBy) as $part) {
            $tokens = preg_split('/\s+/', trim($part));
            if ($tokens === false || count($tokens) < 1 || count($tokens) > 2) {
                throw new InvalidArgumentException('Geçersiz sıralama.');
            }

            $direction = strtoupper($tokens[1] ?? 'ASC');
            if ($direction !== 'ASC' && $direction !== 'DESC') {
                throw new InvalidArgumentException('Geçersiz sıralama yönü.');
            }

            $parts[] = $this->resolveColumn($tokens[0], $this->sortableColumns()) . ' ' . $direction;
        }

        return implode(', ', $parts);
    }
}