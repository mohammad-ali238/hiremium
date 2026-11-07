<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A single credit ledger entry. `amount` is negative for a spend, positive for
 * a grant/refund; `source` records which pool moved ('monthly' or 'pack').
 */
class CreditTransaction extends Model
{
    public const UPDATED_AT = null;

    public const SOURCE_MONTHLY = 'monthly';

    public const SOURCE_PACK = 'pack';

    protected $fillable = ['workspace_id', 'user_id', 'source', 'amount', 'reason', 'subject_type', 'subject_id'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
