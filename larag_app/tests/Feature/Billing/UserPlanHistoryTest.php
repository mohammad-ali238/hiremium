<?php

namespace Tests\Feature\Billing;

use App\Models\BillingSetting;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserPlanHistory;
use App\Models\Workspace;
use App\Services\Billing\SubscriptionProvisioner;
use App\Services\Billing\UserPlanGuard;
use App\Services\Workspaces\WorkspaceProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * The user's plan lives on their own subscription record (credit total
 * snapshotted there, not read from the plans table) and every transition is
 * written to the plan history.
 */
class UserPlanHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function plan(string $name, int $credits, float $price = 10): Plan
    {
        return Plan::create([
            'name' => $name, 'credit_limit' => $credits, 'price' => $price,
            'duration_days' => 30, 'is_active' => true,
        ]);
    }

    private function freePlan(int $credits = 100): Plan
    {
        return $this->plan('Free', $credits, 0);
    }

    private function owner(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        app(WorkspaceProvisioner::class)->createOwnerWorkspace($user);

        return $user->refresh();
    }

    public function test_total_credits_come_from_the_subscription_not_the_plan(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan('Starter', 100);

        $subscription = app(SubscriptionProvisioner::class)->provision($user, $plan);

        $this->assertSame(100, $subscription->credits_total);

        // An admin halves the plan: the user keeps the total they were given.
        $plan->update(['credit_limit' => 50]);

        $this->assertSame(100, $subscription->refresh()->creditsTotal());
        $this->assertSame(0, $subscription->creditsUsed());
    }

    public function test_assigning_a_plan_logs_history_and_cancels_the_previous_one(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $starter = $this->plan('Starter', 100);
        $pro = $this->plan('Pro', 500);

        $first = app(SubscriptionProvisioner::class)->provision(
            $user, $starter, [], null, null, UserPlanHistory::SOURCE_ADMIN, $admin->id
        );

        $second = app(SubscriptionProvisioner::class)->provision(
            $user, $pro, [], null, null, UserPlanHistory::SOURCE_ADMIN, $admin->id
        );

        $this->assertSame(Subscription::STATUS_SUPERSEDED, $first->refresh()->status);
        $this->assertNotNull($first->cancelled_at);
        $this->assertSame(Subscription::STATUS_ACTIVE, $second->status);

        $events = UserPlanHistory::where('user_id', $user->id)->orderBy('id')->pluck('event')->all();
        $this->assertSame(['assigned', 'superseded', 'assigned'], $events);

        $superseded = UserPlanHistory::where('event', 'superseded')->first();
        $this->assertSame('Starter', $superseded->plan_name);
        $this->assertSame(100, $superseded->credits_total);
        $this->assertSame($admin->id, $superseded->actor_id);
        $this->assertSame(UserPlanHistory::SOURCE_ADMIN, $superseded->source);
    }

    public function test_app_page_auto_assigns_the_free_plan_and_logs_it(): void
    {
        $this->freePlan(120);
        $user = $this->owner();

        // Start from "no plan at all": drop what provisioning gave them.
        Subscription::where('user_id', $user->id)->delete();
        UserPlanHistory::where('user_id', $user->id)->delete();

        $this->actingAs($user)->get('/app')->assertSuccessful();

        $subscription = Subscription::where('user_id', $user->id)->active()->first();
        $this->assertNotNull($subscription, 'the free plan should have been assigned');
        $this->assertSame(120, $subscription->credits_total);
        $this->assertSame(UserPlanHistory::SOURCE_AUTO_FREE, $subscription->assign_source);
        // A real 30-day period, not a "never expires" sentinel.
        $this->assertSame(30, (int) $subscription->starts_at->diffInDays($subscription->ends_at, true));
        $this->assertNull($subscription->credits_reset_at);

        $history = UserPlanHistory::where('user_id', $user->id)->latest('id')->first();
        $this->assertSame(UserPlanHistory::EVENT_ASSIGNED, $history->event);
        $this->assertSame(UserPlanHistory::SOURCE_AUTO_FREE, $history->source);
        $this->assertSame('Free', $history->plan_name);
    }

    public function test_a_lapsed_plan_is_expired_then_free_is_assigned(): void
    {
        $this->freePlan();
        $user = $this->owner();

        $paid = Subscription::where('user_id', $user->id)->first();
        // Pretend the paid period ran out while they were away.
        $paid->update(['ends_at' => now()->subDay(), 'status' => Subscription::STATUS_ACTIVE]);
        UserPlanHistory::where('user_id', $user->id)->delete();

        app(UserPlanGuard::class)->ensure($user);

        $this->assertSame(Subscription::STATUS_EXPIRED, $paid->refresh()->status);
        $this->assertNotNull($paid->expired_at);

        $events = UserPlanHistory::where('user_id', $user->id)->orderBy('id')->pluck('event')->all();
        $this->assertSame([UserPlanHistory::EVENT_EXPIRED, UserPlanHistory::EVENT_ASSIGNED], $events);
    }

    public function test_deleting_a_subscription_keeps_a_history_entry(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan('Starter', 100);
        $subscription = app(SubscriptionProvisioner::class)->provision($user, $plan);

        $subscription->delete();

        $deleted = UserPlanHistory::where('user_id', $user->id)
            ->where('event', UserPlanHistory::EVENT_DELETED)
            ->first();

        $this->assertNotNull($deleted);
        $this->assertSame('Starter', $deleted->plan_name);
        $this->assertSame(100, $deleted->credits_total);
        $this->assertNull($deleted->subscription_id);
        // The earlier "assigned" entry survives the deletion too.
        $this->assertSame(2, UserPlanHistory::where('user_id', $user->id)->count());
    }

    public function test_cancelling_is_logged_once_even_when_reported_twice(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan('Starter', 100);
        app(SubscriptionProvisioner::class)->provision($user, $plan, [
            'lemonsqueezy_subscription_id' => 'ls_cancel',
            'renews_at' => now()->addDays(30),
        ], null, null, UserPlanHistory::SOURCE_PAYMENT);

        // The user cancels in the dashboard, then the webhook echoes it back.
        app(SubscriptionProvisioner::class)->markCancelled('ls_cancel', 'cancelled', now()->addDays(30), UserPlanHistory::SOURCE_SELF, $user->id);
        app(SubscriptionProvisioner::class)->markCancelled('ls_cancel', 'cancelled', now()->addDays(30));

        $this->assertSame(1, UserPlanHistory::where('user_id', $user->id)
            ->where('event', UserPlanHistory::EVENT_CANCELLED)->count());
        $this->assertNotNull(Subscription::where('lemonsqueezy_subscription_id', 'ls_cancel')->first()->cancelled_at);
    }

    public function test_free_period_rolls_over_monthly_with_a_fresh_total_and_history(): void
    {
        $free = $this->freePlan(100);
        $user = User::factory()->create();

        Carbon::setTestNow('2026-10-21 09:00:00');
        $first = app(SubscriptionProvisioner::class)->provision($user, $free, [], null, null, UserPlanHistory::SOURCE_AUTO_FREE);
        $first->update(['credits_remaining' => 0]); // all credits spent on day one

        // A 30-day period, so nothing happens while it runs.
        $this->assertSame('2026-11-20', $first->ends_at->toDateString());
        Carbon::setTestNow('2026-11-19 09:00:00');
        $this->artisan('hiremium:reset-monthly-credits')->assertSuccessful();
        $this->assertSame(1, Subscription::where('user_id', $user->id)->count());
        $this->assertSame(0, $first->refresh()->credits_remaining);

        // The plan is upgraded in the admin: it applies from the next cycle.
        $free->update(['credit_limit' => 250]);

        // The nightly command runs after the period ended.
        Carbon::setTestNow('2026-11-20 10:00:00');
        $this->artisan('hiremium:reset-monthly-credits')->assertSuccessful();

        $this->assertSame(Subscription::STATUS_EXPIRED, $first->refresh()->status);

        $second = Subscription::where('user_id', $user->id)->active()->firstOrFail();
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(250, $second->credits_remaining);
        $this->assertSame(250, $second->credits_total);
        $this->assertSame('2026-12-20', $second->ends_at->toDateString());

        // Every free cycle is in the history: assigned → expired → assigned.
        $events = UserPlanHistory::where('user_id', $user->id)->orderBy('id')->pluck('event')->all();
        $this->assertSame(['assigned', 'expired', 'assigned'], $events);
        $this->assertSame(
            [UserPlanHistory::SOURCE_AUTO_FREE, UserPlanHistory::SOURCE_AUTO_FREE],
            UserPlanHistory::where('user_id', $user->id)->where('event', 'assigned')->pluck('source')->all(),
        );
    }

    public function test_team_members_do_not_get_their_own_plan(): void
    {
        $this->freePlan();
        $owner = $this->owner();
        $workspace = Workspace::where('owner_id', $owner->id)->firstOrFail();

        $member = User::factory()->create(['email_verified_at' => now()]);
        app(WorkspaceProvisioner::class)->attachMember($member, $workspace, 'Recruiter');

        $this->assertNull(app(UserPlanGuard::class)->ensure($member->refresh()));
        $this->assertSame(0, Subscription::where('user_id', $member->id)->count());
    }

    public function test_billing_page_lists_the_plan_history(): void
    {
        $this->freePlan();
        $user = $this->owner();
        $pro = $this->plan('Pro', 500);

        app(SubscriptionProvisioner::class)->provision($user, $pro, [], null, null, UserPlanHistory::SOURCE_ADMIN);

        $this->actingAs($user)->get('/app/billing')
            ->assertSuccessful()
            ->assertSee('Plan history')
            ->assertSee('Pro')
            ->assertSee('Free');
    }

    public function test_exhausted_credits_do_not_trigger_a_new_plan_before_the_expiry_date(): void
    {
        Carbon::setTestNow('2026-10-21 10:00:00');
        $this->freePlan();
        $user = $this->owner();

        $paid = Subscription::where('user_id', $user->id)->firstOrFail();
        // Every credit spent today, but the period still runs until 21 Nov 2026.
        $paid->update([
            'credits_remaining' => 0,
            'ends_at' => Carbon::parse('2026-11-21 00:00:00'),
            'status' => Subscription::STATUS_ACTIVE,
        ]);
        $historyBefore = UserPlanHistory::where('user_id', $user->id)->count();

        $this->actingAs($user)->get('/app')->assertSuccessful();

        // Same subscription, untouched: no expiry, no replacement, no new history.
        $this->assertSame(1, Subscription::where('user_id', $user->id)->count());
        $this->assertSame(Subscription::STATUS_ACTIVE, $paid->refresh()->status);
        $this->assertNull($paid->expired_at);
        $this->assertSame(0, $paid->credits_remaining);
        $this->assertSame($historyBefore, UserPlanHistory::where('user_id', $user->id)->count());
    }

    public function test_free_plan_is_assigned_only_once_the_expiry_date_is_reached(): void
    {
        Carbon::setTestNow('2026-10-21 10:00:00');
        $this->freePlan();
        $user = $this->owner();

        $paid = Subscription::where('user_id', $user->id)->firstOrFail();
        $paid->update([
            'credits_remaining' => 0,
            'ends_at' => Carbon::parse('2026-11-21 00:00:00'),
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        // The day before expiry: nothing happens.
        Carbon::setTestNow('2026-11-20 23:00:00');
        app(UserPlanGuard::class)->ensure($user);
        $this->assertSame(1, Subscription::where('user_id', $user->id)->count());

        // On the expiry date: the old plan expires and Free takes over.
        Carbon::setTestNow('2026-11-21 00:00:00');
        app(UserPlanGuard::class)->ensure($user);

        $this->assertSame(Subscription::STATUS_EXPIRED, $paid->refresh()->status);
        $fresh = Subscription::where('user_id', $user->id)->active()->firstOrFail();
        $this->assertSame('Free', $fresh->plan_name_snapshot);
        $this->assertSame(UserPlanHistory::SOURCE_AUTO_FREE, $fresh->assign_source);
    }

    public function test_a_late_renewal_webhook_wins_over_the_stop_gap_free_plan(): void
    {
        $this->freePlan();
        $user = User::factory()->create();
        $pro = $this->plan('Pro', 500);

        Carbon::setTestNow('2026-10-21 09:00:00');
        $paid = app(SubscriptionProvisioner::class)->provision($user, $pro, [
            'lemonsqueezy_subscription_id' => 'ls_late',
            'renews_at' => Carbon::parse('2026-11-20 09:00:00'),
        ], null, null, UserPlanHistory::SOURCE_PAYMENT);

        // The period lapses and the user opens /app before the renewal webhook
        // arrives: the paid plan expires and Free steps in.
        Carbon::setTestNow('2026-11-20 09:05:00');
        app(UserPlanGuard::class)->ensure($user);
        $this->assertSame(Subscription::STATUS_EXPIRED, $paid->refresh()->status);
        $this->assertTrue(Subscription::where('user_id', $user->id)->active()->firstOrFail()->plan->isFree());

        // The renewal webhook lands: the paid plan must win, and it must be the
        // ONLY active subscription (otherwise a paying user spends Free credits).
        app(SubscriptionProvisioner::class)->syncFromLemonSqueezy(
            'ls_late', $pro, 'monthly', Carbon::parse('2026-12-20 09:00:00'), 'active'
        );

        $active = Subscription::where('user_id', $user->id)->active()->get();
        $this->assertCount(1, $active, 'a user must never hold two active subscriptions');
        $this->assertSame($paid->id, $active->first()->id);
        $this->assertSame(500, $active->first()->credits_remaining);
        $this->assertNull($active->first()->expired_at);

        // And the whole episode is legible in the history.
        $events = UserPlanHistory::where('user_id', $user->id)->orderBy('id')->pluck('event')->all();
        $this->assertSame(['assigned', 'expired', 'assigned', 'renewed', 'superseded'], $events);
    }

    public function test_api_request_rolls_a_lapsed_free_period_over_instead_of_rejecting(): void
    {
        $this->freePlan(100);
        $user = $this->owner();

        $lapsed = Subscription::where('user_id', $user->id)->firstOrFail();
        $lapsed->update(['ends_at' => now()->subHour(), 'status' => Subscription::STATUS_ACTIVE]);

        $token = $user->createToken('test')->plainTextToken;

        // Only the plan guard is under test here; any 4xx/5xx from the AI call
        // itself is fine as long as it isn't "no active subscription".
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/matching-score', []);

        $this->assertNotSame('no_active_subscription', $response->json('error.code'));
        $this->assertSame(Subscription::STATUS_EXPIRED, $lapsed->refresh()->status);
        $this->assertNotNull(Subscription::where('user_id', $user->id)->active()->first());
    }

    public function test_a_team_member_sees_the_workspace_plan_read_only(): void
    {
        $this->freePlan();
        $owner = $this->owner();
        $workspace = Workspace::where('owner_id', $owner->id)->firstOrFail();
        app(SubscriptionProvisioner::class)->provision($owner, $this->plan('Pro', 500), [], null, null, UserPlanHistory::SOURCE_ADMIN);

        $member = User::factory()->create(['email_verified_at' => now()]);
        app(WorkspaceProvisioner::class)->attachMember($member, $workspace, 'Recruiter');

        $this->actingAs($member->refresh())->get('/app/billing')
            ->assertSuccessful()
            // The workspace's plan, not a misleading "No plan".
            ->assertSee('Pro')
            ->assertSee('managed by the account owner', false)
            ->assertDontSee('No plan')
            // Nothing they could act on, and no history of their own.
            ->assertDontSee('Cancel subscription')
            ->assertDontSee('Plan history');

        $this->assertSame(0, Subscription::where('user_id', $member->id)->count());
    }

    public function test_a_future_dated_plan_is_scheduled_not_granted_immediately(): void
    {
        Carbon::setTestNow('2026-10-21 09:00:00');
        $this->freePlan(100);
        $user = $this->owner();
        $pro = $this->plan('Pro', 500);

        $current = Subscription::where('user_id', $user->id)->active()->firstOrFail();

        // Mid-period, so the current plan is still running when Pro starts.
        $scheduled = app(SubscriptionProvisioner::class)->provision(
            $user, $pro, [], Carbon::parse('2026-11-10 00:00:00'), null, UserPlanHistory::SOURCE_ADMIN
        );

        // Today: still on the old plan, the new one grants nothing yet.
        $this->assertTrue($scheduled->isScheduled());
        $this->assertFalse($scheduled->isUsable());
        $this->assertSame(Subscription::STATUS_ACTIVE, $current->refresh()->status, 'the current plan must not be retired early');
        $this->assertSame($current->id, app(UserPlanGuard::class)->ensure($user)->id);
        $this->assertSame($current->id, Subscription::where('user_id', $user->id)->active()->latest('id')->first()->id);

        // The user sees it announced rather than applied.
        $this->actingAs($user)->get('/app/billing')
            ->assertSuccessful()
            ->assertSee('Pro starts on')
            ->assertSee('Nov 10, 2026');

        // On the start date it takes over and the old plan is superseded.
        Carbon::setTestNow('2026-11-10 00:05:00');
        $active = app(UserPlanGuard::class)->ensure($user);

        $this->assertSame($scheduled->id, $active->id);
        $this->assertSame(Subscription::STATUS_SUPERSEDED, $current->refresh()->status);
        $this->assertCount(1, Subscription::where('user_id', $user->id)->active()->get());
        $this->assertSame(500, $active->credits_remaining);

        $superseded = UserPlanHistory::where('user_id', $user->id)->where('event', 'superseded')->latest('id')->first();
        $this->assertSame('Replaced by Pro', $superseded->note);
        $this->assertSame('2026-11-10', $superseded->cancelled_at->toDateString());
    }

    public function test_scheduled_plan_also_comes_due_through_the_nightly_command(): void
    {
        Carbon::setTestNow('2026-10-21 09:00:00');
        $this->freePlan(100);
        $user = $this->owner();
        $pro = $this->plan('Pro', 500);

        $scheduled = app(SubscriptionProvisioner::class)->provision(
            $user, $pro, [], Carbon::parse('2026-11-21 00:00:00'), null, UserPlanHistory::SOURCE_ADMIN
        );

        Carbon::setTestNow('2026-11-21 00:10:00');
        $this->artisan('hiremium:reset-monthly-credits')->assertSuccessful();

        $active = Subscription::where('user_id', $user->id)->active()->get();
        $this->assertCount(1, $active);
        $this->assertSame($scheduled->id, $active->first()->id);
    }

    public function test_admin_cancel_of_a_paid_plan_goes_through_lemonsqueezy(): void
    {
        BillingSetting::current()->update([
            'enabled' => true, 'store_id' => '42', 'api_key' => 'key', 'signing_secret' => 'sec',
        ]);
        Http::fake([
            'api.lemonsqueezy.com/*' => Http::response(['data' => ['attributes' => [
                'status' => 'cancelled', 'ends_at' => '2026-11-20T09:00:00.000000Z',
            ]]], 200),
        ]);

        $user = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $pro = $this->plan('Pro', 500);

        $paid = app(SubscriptionProvisioner::class)->provision($user, $pro, [
            'lemonsqueezy_subscription_id' => 'ls_admin_cancel',
            'renews_at' => now()->addDays(30),
        ], null, null, UserPlanHistory::SOURCE_PAYMENT);

        $message = app(SubscriptionProvisioner::class)->cancelByAdmin($paid, $admin->id);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'ls_admin_cancel'));
        $this->assertStringContainsString('LemonSqueezy', $message);

        // Access is kept until the period end — same as a self-serve cancel.
        $paid->refresh();
        $this->assertSame(Subscription::STATUS_ACTIVE, $paid->status);
        $this->assertSame('cancelled', $paid->lemonsqueezy_status);
        $this->assertNotNull($paid->cancelled_at);

        $entry = UserPlanHistory::where('user_id', $user->id)->where('event', 'cancelled')->firstOrFail();
        $this->assertSame(UserPlanHistory::SOURCE_ADMIN, $entry->source);
        $this->assertSame($admin->id, $entry->actor_id);
    }

    public function test_admin_cancel_changes_nothing_locally_when_lemonsqueezy_fails(): void
    {
        BillingSetting::current()->update([
            'enabled' => true, 'store_id' => '42', 'api_key' => 'key', 'signing_secret' => 'sec',
        ]);
        Http::fake(['api.lemonsqueezy.com/*' => Http::response(['errors' => [['detail' => 'nope']]], 422)]);

        $user = User::factory()->create();
        $paid = app(SubscriptionProvisioner::class)->provision($user, $this->plan('Pro', 500), [
            'lemonsqueezy_subscription_id' => 'ls_fail',
            'renews_at' => now()->addDays(30),
        ], null, null, UserPlanHistory::SOURCE_PAYMENT);

        try {
            app(SubscriptionProvisioner::class)->cancelByAdmin($paid);
            $this->fail('a provider failure must surface, not be swallowed');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('nothing was changed locally', $e->getMessage());
        }

        $this->assertNull($paid->refresh()->cancelled_at);
        $this->assertSame(0, UserPlanHistory::where('user_id', $user->id)->where('event', 'cancelled')->count());
    }

    public function test_admin_cancel_of_a_hand_assigned_plan_ends_it_immediately(): void
    {
        $this->freePlan(100);
        $user = $this->owner();
        $pro = $this->plan('Pro', 500);
        $assigned = app(SubscriptionProvisioner::class)->provision($user, $pro, [], null, null, UserPlanHistory::SOURCE_ADMIN);

        $message = app(SubscriptionProvisioner::class)->cancelByAdmin($assigned);

        $this->assertStringContainsString('immediately', $message);
        $this->assertSame(Subscription::STATUS_CANCELLED, $assigned->refresh()->status);
        $this->assertNotNull($assigned->cancelled_at);

        $entry = UserPlanHistory::where('user_id', $user->id)->where('event', 'cancelled')->firstOrFail();
        $this->assertSame(UserPlanHistory::SOURCE_ADMIN, $entry->source);
        $this->assertSame('Cancelled by admin', $entry->note);

        // Next visit puts them back on Free, recorded as its own assignment.
        $this->actingAs($user)->get('/app')->assertSuccessful();
        $this->assertTrue(Subscription::where('user_id', $user->id)->active()->firstOrFail()->plan->isFree());
    }

    /** @return array{0: User, 1: Subscription} A paid LS-backed plan whose period has just ended. */
    private function lapsedPaidSubscription(string $lsStatus = 'active', string $lsSubId = 'ls_grace'): array
    {
        $this->freePlan(100);
        $user = $this->owner();
        $pro = $this->plan('Pro', 500);

        // Start from "only a paid plan": drop the Free one provisioning gave them.
        Subscription::where('user_id', $user->id)->delete();
        UserPlanHistory::where('user_id', $user->id)->delete();

        $paid = app(SubscriptionProvisioner::class)->provision($user, $pro, [
            'lemonsqueezy_subscription_id' => $lsSubId,
            'lemonsqueezy_status' => $lsStatus,
            'renews_at' => Carbon::parse('2026-11-20 09:00:00'),
        ], null, null, UserPlanHistory::SOURCE_PAYMENT);

        // The period ends and the renewal has not arrived yet.
        Carbon::setTestNow('2026-11-20 09:05:00');

        return [$user, $paid];
    }

    public function test_a_renewing_paid_plan_is_not_downgraded_to_free_when_the_period_ends(): void
    {
        Carbon::setTestNow('2026-10-21 09:00:00');
        [$user, $paid] = $this->lapsedPaidSubscription();

        $active = app(UserPlanGuard::class)->ensure($user);

        // Still on Pro, with Pro's limits — no Free plan anywhere.
        $this->assertSame($paid->id, $active->id);
        $this->assertSame(Subscription::STATUS_ACTIVE, $paid->refresh()->status);
        $this->assertNull($paid->expired_at);
        $this->assertTrue($paid->inRenewalGrace());
        $this->assertSame('2026-11-25', $paid->ends_at->toDateString(), 'the period is extended by the grace window');
        $this->assertSame(1, Subscription::where('user_id', $user->id)->count());

        // And the wait is recorded rather than silent.
        $grace = UserPlanHistory::where('user_id', $user->id)->where('event', UserPlanHistory::EVENT_GRACE)->firstOrFail();
        $this->assertStringContainsString('Waiting for the renewal payment', $grace->note);

        // Grace is granted once, not extended on every page view.
        app(UserPlanGuard::class)->ensure($user);
        $this->assertSame('2026-11-25', $paid->refresh()->ends_at->toDateString());
        $this->assertSame(1, UserPlanHistory::where('user_id', $user->id)->where('event', UserPlanHistory::EVENT_GRACE)->count());
    }

    public function test_a_card_being_retried_also_keeps_the_paid_plan(): void
    {
        Carbon::setTestNow('2026-10-21 09:00:00');
        [$user, $paid] = $this->lapsedPaidSubscription('past_due', 'ls_dunning');

        app(UserPlanGuard::class)->ensure($user);

        $this->assertTrue($paid->refresh()->inRenewalGrace());
        $this->assertSame(0, Subscription::where('user_id', $user->id)->whereKeyNot($paid->id)->count());
    }

    public function test_the_renewal_landing_during_grace_clears_it_and_moves_the_period_on(): void
    {
        Carbon::setTestNow('2026-10-21 09:00:00');
        [$user, $paid] = $this->lapsedPaidSubscription();
        app(UserPlanGuard::class)->ensure($user);

        app(SubscriptionProvisioner::class)->syncFromLemonSqueezy(
            'ls_grace', Plan::where('name', 'Pro')->firstOrFail(), 'monthly', Carbon::parse('2026-12-20 09:00:00'), 'active'
        );

        $paid->refresh();
        $this->assertNull($paid->renewal_grace_at);
        $this->assertFalse($paid->inRenewalGrace());
        $this->assertSame('2026-12-20', $paid->ends_at->toDateString());
        $this->assertSame(500, $paid->credits_remaining);
        $this->assertCount(1, Subscription::where('user_id', $user->id)->active()->get());
    }

    public function test_free_takes_over_once_the_grace_window_also_runs_out(): void
    {
        Carbon::setTestNow('2026-10-21 09:00:00');
        [$user, $paid] = $this->lapsedPaidSubscription();
        app(UserPlanGuard::class)->ensure($user);

        // Nothing ever arrived from the provider.
        Carbon::setTestNow('2026-11-25 10:00:00');
        $active = app(UserPlanGuard::class)->ensure($user);

        $this->assertSame(Subscription::STATUS_EXPIRED, $paid->refresh()->status);
        $this->assertTrue($active->plan->isFree());
        $this->assertSame(UserPlanHistory::SOURCE_AUTO_FREE, $active->assign_source);

        $events = UserPlanHistory::where('user_id', $user->id)->orderBy('id')->pluck('event')->all();
        $this->assertSame(['assigned', 'grace', 'expired', 'assigned'], $events);
    }

    public function test_a_cancelled_subscription_gets_no_grace_and_free_takes_over_at_period_end(): void
    {
        Carbon::setTestNow('2026-10-21 09:00:00');
        [$user, $paid] = $this->lapsedPaidSubscription('cancelled', 'ls_cancelled');

        $active = app(UserPlanGuard::class)->ensure($user);

        // The customer asked for it to end, so it ends on time.
        $this->assertSame(Subscription::STATUS_EXPIRED, $paid->refresh()->status);
        $this->assertNull($paid->renewal_grace_at);
        $this->assertTrue($active->plan->isFree());
    }

    public function test_a_hand_assigned_plan_gets_no_grace(): void
    {
        Carbon::setTestNow('2026-10-21 09:00:00');
        $this->freePlan(100);
        $user = $this->owner();
        $assigned = app(SubscriptionProvisioner::class)->provision(
            $user, $this->plan('Pro', 500), [], null, Carbon::parse('2026-11-20 09:00:00'), UserPlanHistory::SOURCE_ADMIN
        );

        Carbon::setTestNow('2026-11-20 09:05:00');
        $active = app(UserPlanGuard::class)->ensure($user);

        // No provider to wait for.
        $this->assertSame(Subscription::STATUS_EXPIRED, $assigned->refresh()->status);
        $this->assertNull($assigned->renewal_grace_at);
        $this->assertTrue($active->plan->isFree());
    }

    public function test_the_nightly_command_grants_grace_instead_of_expiring_a_renewing_plan(): void
    {
        Carbon::setTestNow('2026-10-21 09:00:00');
        [$user, $paid] = $this->lapsedPaidSubscription();

        $this->artisan('hiremium:reset-monthly-credits')->assertSuccessful();

        $this->assertTrue($paid->refresh()->inRenewalGrace());
        $this->assertSame(Subscription::STATUS_ACTIVE, $paid->status);
        $this->assertSame(1, Subscription::where('user_id', $user->id)->count());
    }
}
