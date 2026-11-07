<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Singleton (id = 1) holding the LemonSqueezy billing credentials and toggle.
 * Secrets are encrypted at rest via the `encrypted` cast.
 */
#[Fillable(['api_key', 'signing_secret', 'store_id', 'enabled'])]
class BillingSetting extends Model
{
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'signing_secret' => 'encrypted',
            'enabled' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1], [
            'enabled' => false,
        ]);
    }

    /**
     * Whether checkout/webhook flows can run: enabled + all credentials present.
     */
    public function isConfigured(): bool
    {
        return $this->enabled
            && ! empty($this->api_key)
            && ! empty($this->signing_secret)
            && ! empty($this->store_id);
    }
}
