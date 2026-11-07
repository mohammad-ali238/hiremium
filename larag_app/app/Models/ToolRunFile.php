<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A file uploaded with a Free Tool run (table `aitools_run_files`). */
class ToolRunFile extends Model
{
    protected $table = 'aitools_run_files';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'extracted_chars' => 'integer',
            'pages' => 'integer',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ToolRun::class, 'aitools_run_id');
    }
}
