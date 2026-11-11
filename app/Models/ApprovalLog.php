<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riwayat aksi pada pengajuan (BR-06). Hanya ditambah, tidak pernah diubah.
 */
#[Fillable(['user_id', 'action', 'step', 'note'])]
class ApprovalLog extends Model
{
    public const UPDATED_AT = null;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
