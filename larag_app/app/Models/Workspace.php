<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A workspace is the tenant/account. Recruiter data (jobs, candidates, batches,
 * activities) is scoped to a workspace; team members are users with this id.
 */
class Workspace extends Model
{
    protected $fillable = ['name', 'owner_id'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function invites(): HasMany
    {
        return $this->hasMany(TeamInvite::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function creditWallet(): HasOne
    {
        return $this->hasOne(CreditWallet::class);
    }

    /** The owner's current active subscription — the source of monthly credits. */
    public function ownerSubscription(): ?Subscription
    {
        return $this->owner
            ? $this->owner->subscriptions()->active()->latest('id')->first()
            : null;
    }
}
