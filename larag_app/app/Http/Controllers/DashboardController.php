<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\UsageLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $subscription = Subscription::query()
            ->with('plan')
            ->where('user_id', $user->getKey())
            ->latest('id')
            ->first();

        $usageLogs = UsageLog::query()
            ->where('user_id', $user->getKey())
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $token = $user->tokens()->latest('id')->first();

        return view('dashboard', [
            'subscription' => $subscription,
            'usageLogs' => $usageLogs,
            'token' => $token,
            'plainTextToken' => session('plain_text_token'),
        ]);
    }
}
