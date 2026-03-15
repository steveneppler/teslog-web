<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Services\TeslaAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TeslaOAuthController extends Controller
{
    public function __construct(
        protected TeslaAuthService $teslaAuth,
    ) {}

    /**
     * Redirect the user to Tesla's OAuth authorization page.
     */
    public function redirect(Request $request)
    {
        $state = Str::random(40);

        $request->session()->put('tesla_oauth_state', $state);

        $url = $this->teslaAuth->getAuthorizationUrl($state);

        return redirect()->away($url);
    }

    /**
     * Handle the callback from Tesla OAuth.
     */
    public function callback(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'state' => 'required|string',
        ]);

        $sessionState = $request->session()->pull('tesla_oauth_state');

        if (! $sessionState || ! hash_equals($sessionState, $request->input('state'))) {
            return redirect()->route('dashboard')->with('error', 'Invalid OAuth state. Please try again.');
        }

        try {
            $tokens = $this->teslaAuth->exchangeCode(
                $request->input('code'),
                $request->input('state'),
            );

            // Always go through setup wizard — it handles both new and re-auth flows
            // It will show new vehicles to link and update tokens on existing ones
            $request->session()->put('tesla_tokens', $tokens);

            return redirect()->route('setup');
        } catch (\RuntimeException $e) {
            \Illuminate\Support\Facades\Log::error('Tesla OAuth code exchange failed', ['error' => $e->getMessage()]);

            return redirect()->route('dashboard')->with('error', 'Failed to connect Tesla account. Please try again.');
        }
    }
}
