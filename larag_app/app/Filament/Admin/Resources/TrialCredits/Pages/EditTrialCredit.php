<?php

namespace App\Filament\Admin\Resources\TrialCredits\Pages;

use App\Filament\Admin\Resources\TrialCredits\TrialCreditResource;
use App\Models\TrialCredit;
use App\Services\Trial\TrialService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditTrialCredit extends EditRecord
{
    protected static string $resource = TrialCreditResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * If an admin flips a pending row to verified via the edit form, make sure
     * it gains an api_key + verified_at just like the Verify action would.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['status'] ?? '') === TrialCredit::STATUS_VERIFIED) {
            $service = app(TrialService::class);

            if (empty($this->record->api_key)) {
                $data['api_key'] = $service->generateApiKey();
            }
            if (empty($this->record->redirect_token)) {
                $data['redirect_token'] = Str::random(48);
            }
            if (empty($this->record->verified_at)) {
                $data['verified_at'] = now();
            }
        }

        return $data;
    }
}
