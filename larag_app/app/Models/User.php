<?php

namespace App\Models;

use App\Support\Impersonation;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'is_admin', 'workspace_id', 'avatar_path', 'notification_prefs', 'email_canonical', 'registration_ip', 'device_hash', 'flagged_at', 'restricted_at', 'trusted_at', 'provider_name', 'provider_id', 'email_verified_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'notification_prefs' => 'array',
            'flagged_at' => 'datetime',
            'restricted_at' => 'datetime',
            'trusted_at' => 'datetime',
        ];
    }

    /** URL to the user's avatar (served via the authorized route) or null. */
    protected function avatarUrl(): Attribute
    {
        return Attribute::get(
            fn () => $this->avatar_path
                ? route('app.avatar', $this).'?v='.($this->updated_at?->timestamp ?? 0)
                : null
        );
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() !== 'admin') {
            return true;
        }

        // While an admin is impersonating someone, the panel stays closed — even
        // if the impersonated account happens to be an admin as well. The only
        // way back in is the "switch back" button.
        return $this->is_admin && ! Impersonation::isActive();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->where('status', 'active')
            // A plan dated into the future isn't the active one yet.
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->latestOfMany();
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(UsageLog::class);
    }

    /** Plan lifecycle log: assignments, renewals, cancellations, expiries. */
    public function planHistories(): HasMany
    {
        return $this->hasMany(UserPlanHistory::class);
    }

    /** The workspace this user belongs to (their own or the one they joined). */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** Workspaces this user owns (normally one). */
    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    public function accountDevices(): HasMany
    {
        return $this->hasMany(AccountDevice::class);
    }

    /** Whether the user is on an active paid subscription (exempt from multi-account actions). */
    public function hasActivePaidPlan(): bool
    {
        return $this->subscriptions()->active()
            ->whereHas('plan', fn ($q) => $q->where('price', '>', 0))
            ->exists();
    }

    public function isTrusted(): bool
    {
        return $this->trusted_at !== null;
    }

    /** AI analysis is blocked for restricted users — unless trusted or on a paid plan. */
    public function isRestricted(): bool
    {
        return $this->restricted_at !== null
            && ! $this->isTrusted()
            && ! $this->hasActivePaidPlan();
    }

    /**
     * Other accounts that share this user's multi-account signals (device, network
     * or canonical email). Used by the admin panel to surface credit-farming.
     */
    public function linkedAccounts(): Builder
    {
        $query = static::query()->whereKeyNot($this->getKey());

        $loginHashes = $this->accountDevices()->pluck('device_hash');

        if (! $this->device_hash && ! $this->registration_ip && ! $this->email_canonical && $loginHashes->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($q) use ($loginHashes) {
            if ($this->device_hash) {
                $q->orWhere('device_hash', $this->device_hash);
            }
            if ($this->registration_ip) {
                $q->orWhere('registration_ip', $this->registration_ip);
            }
            if ($this->email_canonical) {
                $q->orWhere('email_canonical', $this->email_canonical);
            }
            // Accounts logged in from any of this user's devices (post-login signal).
            if ($loginHashes->isNotEmpty()) {
                $q->orWhereIn('device_hash', $loginHashes)
                    ->orWhereIn('id', fn ($sub) => $sub
                        ->select('user_id')
                        ->from('account_devices')
                        ->whereIn('device_hash', $loginHashes));
            }
        });
    }

    public function isOwnerOfCurrentWorkspace(): bool
    {
        return $this->workspace_id !== null
            && $this->workspace?->owner_id === $this->id;
    }
}
