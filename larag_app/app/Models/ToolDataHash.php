<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A day's fingerprint of the data a run was built from (table
 * `aitools_data_hashes`) — how "this CV was already used today, with another
 * email" is detected without keeping the file around.
 */
class ToolDataHash extends Model
{
    protected $table = 'aitools_data_hashes';

    public const UPDATED_AT = null;

    public const TYPE_FILE = 'file';

    public const TYPE_PAYLOAD = 'payload';

    public const TYPE_COMBO = 'combo';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'used_date' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ToolRun::class, 'aitools_run_id');
    }
}
