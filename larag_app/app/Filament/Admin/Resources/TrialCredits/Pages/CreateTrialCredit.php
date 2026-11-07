<?php

namespace App\Filament\Admin\Resources\TrialCredits\Pages;

use App\Filament\Admin\Resources\TrialCredits\TrialCreditResource;
use App\Models\TrialCredit;
use App\Services\Trial\TrialService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateTrialCredit extends CreateRecord
{
    protected static string $resource = TrialCreditResource::class;

    /**
     * Auto-provision a manually added entry: generate uuid + api_key and, when
     * the admin marks it verified, stamp verified_at + expiry.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $service = app(TrialService::class);

        $data['email'] = strtolower(trim($data['email'] ?? ''));
        $data['email_canonical'] = $service->canonicalize($data['email']);
        $data['uuid'] = (string) Str::uuid();
        $data['credits_used'] = $data['credits_used'] ?? 0;

        if (($data['status'] ?? TrialCredit::STATUS_VERIFIED) === TrialCredit::STATUS_VERIFIED) {
            $data['status'] = TrialCredit::STATUS_VERIFIED;
            $data['api_key'] = $service->generateApiKey();
            $data['redirect_token'] = Str::random(48);
            $data['verified_at'] = now();
            $data['expires_at'] = $data['expires_at']
                ?? now()->addDays((int) config('trial.days', TrialCredit::DEFAULT_DAYS));
        }

        return $data;
    }
}
