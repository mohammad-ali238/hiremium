<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class OrdersController extends Controller
{
    /**
     * The current user's billing history (payment/subscription events), newest
     * first, paginated 10 per page.
     */
    public function __invoke(Request $request): View
    {
        $orders = PaymentTransaction::query()
            ->with('plan')
            ->where('user_id', $request->user()->getKey())
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('orders', ['orders' => $orders]);
    }
}
