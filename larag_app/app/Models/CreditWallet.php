<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A workspace's persistent top-up pack balance. Monthly subscription credits
 * are spent first; this balance is drawn down only after they run out.
 */
class CreditWallet extends Model
{
    protected $fillable = ['workspace_id', 'pack_balance'];

    protected function casts(): array
    {
        return ['pack_balance' => 'integer'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
