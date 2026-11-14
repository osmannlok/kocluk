<?php // app/Core/Database.php
declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;
use Stringable;
use Throwable;

/**
 * PDO tekil bağlantı sarmalayıcısı. Yalnızca prepared statement; hata ayrıntısı kullanıcıya sızmaz.
 */
final class Database
{
    private static ?self $instance = null;
    private ?PDO $pdo = null;
    private int $depth = 0;

    private function __construct()
    {
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    /** Tembel bağlantı: ilk kullanımda açılır. */
    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = $this->connect();
        }
        return $this->pdo;
    }

    /** @return list<array<string, mixed>> */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function fetchValue(string $sql, array $params = []): mixed
    {
        $value = $this->run($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    /** Etkilenen satır sayısı. */
    public function execute(string $sql, array $params = []): int
    {
        return $this->run($sql, $params)->rowCount();
    }

    /** Eklenen kaydın AUTO_INCREMENT kimliği. */
    public function insert(string $sql, array $params = []): int
    {
        $this->run($sql, $params);
        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * Transaction: iç içe çağrı mevcut transaction'a katılır; hata = rollback + yeniden fırlat.
     */
    public function transaction(callable $fn): mixed
    {
        if ($this->depth > 0) {
            $this->depth++;
            try {
                return $fn();
            } finally {
                $this->depth--;
            }
        }

        $pdo = $this->pdo();
        $pdo->beginTransaction();
        $this->depth = 1;
        try {
            $result = $fn();
            if ($pdo->inTransaction()) {
                $pdo->commit();
            }
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        } finally {
            $this->depth = 0;
        }
    }

    private function connect(): PDO
    {
        $cfg = Config::get('database');
        if (!is_array($cfg)) {
            throw new RuntimeException('Veritabanı yapılandırması bulunamadı.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            (string) $cfg['host'],
            (int) $cfg['port'],
            (string) $cfg['database'],
            (string) $cfg['charset']
        );

        try {
            return new PDO($dsn, (string) $cfg['username'], (string) $cfg['password'], (array) $cfg['options']);
        } catch (PDOException $e) {
            Logger::error('Veritabanı bağlantı hatası', ['error' => $e->getMessage()]);
            // Önceki istisna zincire EKLENMEZ: stack trace argümanlarında parola bulunabilir
            throw new RuntimeException('Veritabanı bağlantısı kurulamadı.');
        }
    }

    private function run(string $sql, array $params): PDOStatement
    {
        try {
            $stmt = $this->pdo()->prepare($sql);
            foreach ($params as $key => $value) {
                $name = is_int($key) ? $key + 1 : ':' . ltrim((string) $key, ':');
                [$bound, $type] = self::normalize($value);
                $stmt->bindValue($name, $bound, $type);
            }
            $stmt->execute();
            return $stmt;
        } catch (PDOException $e) {
            // SQL ve hata yalnızca loga yazılır; parametre DEĞERLERİ yazılmaz (kişisel veri/parola olabilir)
            Logger::error('SQL hatası', [
                'sql' => $sql,
                'sqlstate' => (string) $e->getCode(),
                'error' => $e->getMessage(),
                'param_count' => count($params),
            ]);
            throw $e; // kullanıcıya genel mesajı ErrorHandler gösterir
        }
    }

    /**
     * Gerçek prepared statement'ta LIMIT ? gibi yerler tamsayı ister; bu yüzden tipe göre bağlanır.
     *
     * @return array{0: mixed, 1: int}
     */
    private static function normalize(mixed $value): array
    {
        return match (true) {
            $value === null => [null, PDO::PARAM_NULL],
            is_bool($value) => [(int) $value, PDO::PARAM_INT],
            is_int($value) => [$value, PDO::PARAM_INT],
            is_float($value) => [(string) $value, PDO::PARAM_STR],
            is_string($value) => [$value, PDO::PARAM_STR],
            $value instanceof Stringable => [(string) $value, PDO::PARAM_STR],
            default => throw new InvalidArgumentException('Geçersiz sorgu parametresi.'),
        };
    }
}