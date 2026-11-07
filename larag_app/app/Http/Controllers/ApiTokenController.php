<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ApiTokenController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Single API key policy: revoke all existing tokens before issuing a new one.
        $user->tokens()->delete();

        $token = $user->createToken('api-key');

        return redirect()
            ->route('dashboard')
            ->with('plain_text_token', $token->plainTextToken)
            ->with('status', 'api-key-generated');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->tokens()->delete();

        return redirect()
            ->route('dashboard')
            ->with('status', 'api-key-revoked');
    }
}
