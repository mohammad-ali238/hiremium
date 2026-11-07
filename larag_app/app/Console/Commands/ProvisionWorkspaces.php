<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Workspaces\WorkspaceProvisioner;
use Illuminate\Console\Command;

/**
 * Backfill: give every user without a workspace their own workspace + Owner
 * role. Safe to re-run — only touches users whose workspace_id is null.
 */
class ProvisionWorkspaces extends Command
{
    protected $signature = 'hiremium:provision-workspaces';

    protected $description = 'Create a workspace + Owner role for any user that has none';

    public function handle(WorkspaceProvisioner $provisioner): int
    {
        $users = User::whereNull('workspace_id')->get();

        if ($users->isEmpty()) {
            $this->info('All users already have a workspace.');

            return self::SUCCESS;
        }

        foreach ($users as $user) {
            $workspace = $provisioner->createOwnerWorkspace($user);
            $this->line("Provisioned workspace #{$workspace->id} for {$user->email}");
        }

        $this->info("Provisioned {$users->count()} workspace(s).");

        return self::SUCCESS;
    }
}
