<?php

namespace App\Filament\Admin\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\HtmlString;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('email')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->maxLength(255),

                Section::make('Linked accounts')
                    ->description('Other accounts sharing this user\'s device, network or email identity — review for free-credit farming.')
                    ->visible(fn (string $operation, ?User $record) => $operation === 'edit' && $record && $record->linkedAccounts()->exists())
                    ->schema([
                        Placeholder::make('linked_accounts')
                            ->hiddenLabel()
                            ->content(fn (?User $record) => new HtmlString(self::linkedAccountsHtml($record))),
                    ]),
            ]);
    }

    private static function linkedAccountsHtml(?User $record): string
    {
        if (! $record) {
            return '';
        }

        // All device hashes this user has been seen on (registration + logins).
        $myDevices = $record->accountDevices()->pluck('device_hash')
            ->when($record->device_hash, fn ($c) => $c->push($record->device_hash))
            ->unique();

        $rows = $record->linkedAccounts()->get()->map(function (User $other) use ($record, $myDevices) {
            $otherDevices = $other->accountDevices()->pluck('device_hash')
                ->when($other->device_hash, fn ($c) => $c->push($other->device_hash))
                ->unique();

            $signals = collect([
                $myDevices->intersect($otherDevices)->isNotEmpty() ? 'shared device' : null,
                $record->registration_ip && $other->registration_ip === $record->registration_ip ? 'same IP' : null,
                $record->email_canonical && $other->email_canonical === $record->email_canonical ? 'email alias' : null,
            ])->filter()->implode(', ');

            return '<li class="py-1">'
                .'<strong>'.e($other->name).'</strong> — '.e($other->email)
                .' <span style="color:#d97706">('.e($signals ?: 'shared login device').')</span>'
                .' <span style="color:#9ca3af">· joined '.e($other->created_at?->format('M j, Y')).'</span>'
                .'</li>';
        })->implode('');

        return '<ul style="list-style:disc;padding-left:1.25rem">'.$rows.'</ul>';
    }
}
