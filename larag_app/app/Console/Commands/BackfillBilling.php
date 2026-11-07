<?php

namespace App\Console\Commands;

use App\Models\Workspace;
use App\Services\Workspaces\WorkspaceProvisioner;
use Illuminate\Console\Command;

/**
 * Ensure every existing workspace has a credit wallet and its owner a Free
 * subscription (backfill after the Phase 3 billing tables land). Idempotent.
 */
class BackfillBilling extends Command
{
    protected $signature = 'hiremium:backfill-billing';

    protected $description = 'Ensure every workspace has a credit wallet + owner subscription';

    public function handle(WorkspaceProvisioner $provisioner): int
    {
        $workspaces = Workspace::with('owner')->get();

        foreach ($workspaces as $workspace) {
            $provisioner->ensureBilling($workspace);
        }

        $this->info("Ensured billing for {$workspaces->count()} workspace(s).");

        return self::SUCCESS;
    }
}
