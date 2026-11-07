<?php

namespace App\Livewire\Team;

use App\Livewire\Concerns\InteractsWithWorkspace;
use App\Models\TeamInvite;
use App\Models\User;
use App\Services\Activity\ActivityLogger;
use App\Services\Billing\PlanLimits;
use App\Support\Rbac;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\PermissionRegistrar;

#[Layout('components.layouts.dashboard')]
#[Title('Team Members')]
class Members extends Component
{
    use InteractsWithWorkspace;

    public string $inviteEmail = '';

    public string $inviteRole = 'Recruiter';

    public function mount(): void
    {
        abort_unless(Auth::user()->can('team.manage'), 403);
    }

    public function invite(): void
    {
        abort_unless(Auth::user()->can('team.manage'), 403);

        $rateKey = 'invite:'.Auth::id();
        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            $this->addError('inviteEmail', 'Too many invites — please wait a minute.');

            return;
        }

        $this->validate([
            'inviteEmail' => ['required', 'email', 'max:255'],
            'inviteRole' => ['required', 'in:'.implode(',', Rbac::assignableRoles())],
        ]);
        RateLimiter::hit($rateKey, 60);

        $workspaceId = Auth::user()->workspace_id;
        $email = strtolower(trim($this->inviteEmail));

        if (! app(PlanLimits::class)->canAddSeat($this->currentWorkspace())) {
            $this->addError('inviteEmail', "You've reached your plan's team-member limit. Upgrade to add more seats.");

            return;
        }

        if (User::where('workspace_id', $workspaceId)->where('email', $email)->exists()) {
            $this->addError('inviteEmail', 'That person is already a member.');

            return;
        }

        if (TeamInvite::where('workspace_id', $workspaceId)->where('email', $email)->whereNull('accepted_at')->exists()) {
            $this->addError('inviteEmail', 'An invite is already pending for that email.');

            return;
        }

        $invite = TeamInvite::create([
            'workspace_id' => $workspaceId,
            'email' => $email,
            'role' => $this->inviteRole,
            'token' => TeamInvite::newToken(),
            'invited_by' => Auth::id(),
            'expires_at' => now()->addDays(7),
        ]);

        ActivityLogger::log('team.invited', $invite, "Invited {$email} as {$this->inviteRole}");

        Notification::route('mail', $email)->notify(
            new \App\Notifications\TeamInvite($this->currentWorkspace()?->name ?? 'the workspace', $invite->role, $invite->token)
        );

        $this->reset('inviteEmail');
        $this->inviteRole = 'Recruiter';
        $this->dispatch('toast', type: 'success', message: 'Invite created. Copy the link to add them.');
    }

    public function revokeInvite(int $id): void
    {
        abort_unless(Auth::user()->can('team.manage'), 403);

        $invite = TeamInvite::where('workspace_id', Auth::user()->workspace_id)->findOrFail($id);
        $email = $invite->email;
        $invite->delete();

        ActivityLogger::log('team.invite_revoked', null, "Revoked invite for {$email}");
        $this->dispatch('toast', type: 'info', message: 'Invite revoked.');
    }

    public function changeRole(int $userId, string $role): void
    {
        abort_unless(Auth::user()->can('team.manage'), 403);

        if (! in_array($role, Rbac::assignableRoles(), true)) {
            return;
        }

        $member = User::where('workspace_id', Auth::user()->workspace_id)->findOrFail($userId);

        if ($member->id === $this->currentWorkspace()?->owner_id) {
            $this->dispatch('toast', type: 'error', message: "The owner's role can't be changed.");

            return;
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($member->workspace_id);
        $member->syncRoles([$role]);

        ActivityLogger::log('team.role_changed', $member, "Changed {$member->name}'s role to {$role}");
        $this->dispatch('toast', type: 'success', message: 'Role updated.');
    }

    public function removeMember(int $userId): void
    {
        abort_unless(Auth::user()->can('team.manage'), 403);

        $member = User::where('workspace_id', Auth::user()->workspace_id)->findOrFail($userId);

        if ($member->id === $this->currentWorkspace()?->owner_id) {
            $this->dispatch('toast', type: 'error', message: "The owner can't be removed.");

            return;
        }
        if ($member->id === Auth::id()) {
            $this->dispatch('toast', type: 'error', message: "You can't remove yourself.");

            return;
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($member->workspace_id);
        $member->syncRoles([]);
        $member->forceFill(['workspace_id' => null])->save();

        ActivityLogger::log('team.member_removed', null, "Removed {$member->name} ({$member->email})");
        $this->dispatch('toast', type: 'info', message: 'Member removed.');
    }

    public function render()
    {
        $workspace = $this->currentWorkspace();
        $workspaceId = Auth::user()->workspace_id;

        $members = User::where('workspace_id', $workspaceId)->orderBy('name')->get()->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->getRoleNames()->first() ?? '—',
            'is_owner' => $u->id === $workspace?->owner_id,
            'is_self' => $u->id === Auth::id(),
        ]);

        $invites = TeamInvite::where('workspace_id', $workspaceId)->whereNull('accepted_at')->latest()->get();

        return view('livewire.team.members', [
            'members' => $members,
            'invites' => $invites,
            'assignableRoles' => Rbac::assignableRoles(),
        ]);
    }
}
