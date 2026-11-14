<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    use HasFactory;

    /**
     * Toplu atamaya izin verilen alanlar.
     */
    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'changes',
        'ip_address',
        'user_agent',
    ];

    /**
     * Alan tipi dönüşümleri.
     */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    /* ---------------------------------------------------------------
     | İlişkiler
     |---------------------------------------------------------------*/

    /**
     * İşlemi yapan kullanıcı.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * İşlemin yapıldığı kayıt (model_type / model_id).
     */
    public function subject(): MorphTo
    {
        return $this->morphTo('model');
    }

    /* ---------------------------------------------------------------
     | Scope'lar
     |---------------------------------------------------------------*/

    /**
     * İşlem adına göre filtre.
     */
    public function scopeOfAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }
}