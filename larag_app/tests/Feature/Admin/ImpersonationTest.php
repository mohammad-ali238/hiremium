<?php

namespace Tests\Feature\Admin;

use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserPlanHistory;
use App\Services\Workspaces\WorkspaceProvisioner;
use App\Support\Impersonation;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin "login as": an admin can see the app exactly as a user does, cannot
 * reach the admin panel while doing so, and can hand the session back. Nobody
 * else can start it, see it, or even tell it exists.
 */
class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    /** The panel isn't registered as Filament's default, so name it for component tests. */
    private function inAdminPanel(): void
    {
        Filament::setCurrentPanel('admin');
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);
    }

    private function member(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        app(WorkspaceProvisioner::class)->createOwnerWorkspace($user);

        return $user->refresh();
    }

    /** Start impersonation the way the admin action does: hand-off, then the route. */
    private function impersonate(User $admin, User $target, ?string $returnUrl = null): void
    {
        $this->actingAs($admin);
        Impersonation::handOff($target->getKey(), $returnUrl ?: url('/admin/users'));
        $this->get(route('impersonate.start'))->assertRedirect(route('app.dashboard'));
    }

    public function test_admin_can_log_in_as_a_user_and_lands_in_the_app(): void
    {
        Plan::create(['name' => 'Free', 'credit_limit' => 100, 'price' => 0, 'duration_days' => 30, 'is_active' => true]);
        $admin = $this->admin();
        $user = $this->member();

        $this->impersonate($admin, $user);

        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Impersonation::isActive());
        $this->assertSame($admin->id, Impersonation::impersonatorId());

        // The bar names the user they are seeing the app as.
        $this->get('/app')
            ->assertSuccessful()
            ->assertSee('You are logged in as')
            ->assertSee($user->name)
            ->assertSee($user->email)
            ->assertSee('Login back as admin');
    }

    public function test_the_admin_panel_is_closed_while_impersonating(): void
    {
        $admin = $this->admin();
        $user = $this->member();

        $this->impersonate($admin, $user);

        $this->get('/admin')->assertForbidden();
        $this->get('/admin/users')->assertForbidden();
    }

    public function test_the_admin_panel_is_closed_even_when_impersonating_another_admin(): void
    {
        $admin = $this->admin();
        $other = $this->admin();

        $this->impersonate($admin, $other);

        $this->assertAuthenticatedAs($other);
        $this->get('/admin')->assertForbidden();
    }

    public function test_switching_back_restores_the_admin_on_the_page_it_started_from(): void
    {
        $admin = $this->admin();
        $user = $this->member();

        $this->impersonate($admin, $user, url('/admin/users/'.$user->id.'/edit'));
        $this->post(route('impersonate.stop'))
            ->assertRedirect(url('/admin/users/'.$user->id.'/edit'));

        $this->assertAuthenticatedAs($admin);
        $this->assertFalse(Impersonation::isActive());
        $this->get('/admin/users')->assertSuccessful();
    }

    public function test_logging_out_while_impersonating_only_ends_the_impersonation(): void
    {
        $admin = $this->admin();
        $user = $this->member();

        $this->impersonate($admin, $user);
        $this->post('/logout')->assertRedirect(url('/admin/users'));

        // The admin keeps their own session instead of being signed out too.
        $this->assertAuthenticatedAs($admin);
        $this->assertFalse(Impersonation::isActive());
    }

    public function test_a_non_admin_cannot_start_impersonation(): void
    {
        $user = $this->member();
        $victim = $this->member();

        $this->actingAs($user);
        Impersonation::handOff($victim->getKey(), url('/admin/users'));

        $this->get(route('impersonate.start'))->assertForbidden();
        $this->assertAuthenticatedAs($user);
        $this->assertFalse(Impersonation::isActive());
    }

    public function test_the_start_route_is_useless_without_a_hand_off(): void
    {
        $admin = $this->admin();

        // No hand-off in the session: nothing to replay, even as an admin.
        $this->actingAs($admin)->get(route('impersonate.start'))->assertForbidden();
        $this->assertAuthenticatedAs($admin);
        $this->assertFalse(Impersonation::isActive());
    }

    public function test_a_hand_off_can_only_be_used_once(): void
    {
        $admin = $this->admin();
        $user = $this->member();

        $this->impersonate($admin, $user);
        $this->post(route('impersonate.stop'));

        // Replaying the start URL after switching back does nothing.
        $this->get(route('impersonate.start'))->assertForbidden();
        $this->assertAuthenticatedAs($admin);
    }

    public function test_impersonation_cannot_be_nested(): void
    {
        $admin = $this->admin();
        $user = $this->member();
        $other = $this->member();

        $this->impersonate($admin, $user);

        Impersonation::handOff($other->getKey(), url('/admin/users'));
        $this->get(route('impersonate.start'))->assertForbidden();
        $this->assertAuthenticatedAs($user);
    }

    public function test_an_admin_cannot_impersonate_themselves(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin);
        Impersonation::handOff($admin->getKey(), url('/admin/users'));

        $this->get(route('impersonate.start'))->assertForbidden();
        $this->assertFalse(Impersonation::isActive());
    }

    public function test_stopping_outside_an_impersonated_session_is_rejected(): void
    {
        $user = $this->member();

        $this->actingAs($user)->post(route('impersonate.stop'))->assertForbidden();
        $this->assertAuthenticatedAs($user);
    }

    public function test_an_impersonated_session_cannot_be_redirected_off_site(): void
    {
        $admin = $this->admin();
        $user = $this->member();

        $this->impersonate($admin, $user, 'https://evil.example.com/admin/users');

        // A stored return URL is never trusted — it falls back to the panel.
        $this->post(route('impersonate.stop'))->assertRedirect(url('/admin'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_switching_back_is_refused_when_the_admin_lost_their_rights(): void
    {
        $admin = $this->admin();
        $user = $this->member();

        $this->impersonate($admin, $user);
        $admin->forceFill(['is_admin' => false])->save();

        $this->post(route('impersonate.stop'))->assertForbidden();
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_normal_user_never_sees_the_bar(): void
    {
        Plan::create(['name' => 'Free', 'credit_limit' => 100, 'price' => 0, 'duration_days' => 30, 'is_active' => true]);
        $user = $this->member();

        $this->actingAs($user)->get('/app')
            ->assertSuccessful()
            ->assertDontSee('You are logged in as')
            ->assertDontSee('Login back as admin');
    }

    public function test_the_login_as_button_is_offered_in_the_admin_users_list(): void
    {
        $admin = $this->admin();
        $this->member();

        $this->actingAs($admin)->get('/admin/users')
            ->assertSuccessful()
            ->assertSee('Login as');
    }

    public function test_impersonating_leaves_no_trace_on_the_user_account(): void
    {
        Plan::create(['name' => 'Free', 'credit_limit' => 100, 'price' => 0, 'duration_days' => 30, 'is_active' => true]);
        $admin = $this->admin();
        $user = $this->member();

        // Start from "no plan at all" to prove the guard stays out of the way.
        Subscription::where('user_id', $user->id)->delete();
        UserPlanHistory::where('user_id', $user->id)->delete();

        $this->impersonate($admin, $user);
        $this->get('/app')->assertSuccessful();

        // No plan assigned, no history written, and no device recorded against
        // them (which would feed the multi-account detector).
        $this->assertSame(0, Subscription::where('user_id', $user->id)->count());
        $this->assertSame(0, UserPlanHistory::where('user_id', $user->id)->count());
        $this->assertSame(0, $user->accountDevices()->count());
    }

    public function test_the_login_as_action_signs_the_admin_in_as_that_user(): void
    {
        Plan::create(['name' => 'Free', 'credit_limit' => 100, 'price' => 0, 'duration_days' => 30, 'is_active' => true]);
        $admin = $this->admin();
        $user = $this->member();

        $this->actingAs($admin);
        $this->inAdminPanel();

        // Drive the real admin button, not just the routes behind it.
        Livewire::test(ListUsers::class)
            ->callTableAction('loginAs', $user)
            ->assertRedirect(route('impersonate.start'));

        $this->get(route('impersonate.start'))->assertRedirect(route('app.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Impersonation::isActive());
        $this->assertSame($admin->id, Impersonation::impersonatorId());
    }

    public function test_the_login_as_action_is_not_offered_for_your_own_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin);
        $this->inAdminPanel();

        Livewire::test(ListUsers::class)
            ->assertTableActionHidden('loginAs', $admin);
    }
}
