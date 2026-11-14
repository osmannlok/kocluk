<?php
// app/Models/UserSession.php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * sessions tablosu: yalnızca kullanıcı bazlı oturum sonlandırma ve sayım.
 */
final class UserSession extends BaseModel
{
    protected string $table = 'sessions';
    protected bool $softDeletes = false;
    protected array $fillable = [];

    /**
     * Kullanıcının oturumlarını siler; $exceptSessionId verilirse o oturum korunur.
     * Neden iki değer hariç: sessions.id ham kimlik de olsa SHA-256 özeti de olsa
     * (05b ile uyumsuzluk riski) mevcut oturum yanlışlıkla silinmez.
     */
    public function deleteByUser(int $userId, ?string $exceptSessionId = null): int
    {
        $db = Database::instance();

        if ($exceptSessionId === null || $exceptSessionId === '') {
            return $db->execute('DELETE FROM sessions WHERE user_id = ?', [$userId]);
        }

        return $db->execute(
            'DELETE FROM sessions WHERE user_id = ? AND id NOT IN (?, ?)',
            [$userId, $exceptSessionId, hash('sha256', $exceptSessionId)]
        );
    }

    public function countByUser(int $userId): int
    {
        return (int) Database::instance()->fetchValue(
            'SELECT COUNT(*) FROM sessions WHERE user_id = ?',
            [$userId]
        );
    }
}