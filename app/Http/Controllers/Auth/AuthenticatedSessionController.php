<?php

namespace App\Http\Controllers\Auth;
use App\Models\User;
use App\Models\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Providers\RouteServiceProvider;
use App\Services\Auth\SanctumTokenPairService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     *
     * @return \Inertia\Response
     */
    public function create()
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     *
     * Answers JSON (axios login) or a redirect (classic Inertia form post) from
     * the same action so throttling, "remember me" and token issuing stay in one place.
     *
     * @param  \App\Http\Requests\Auth\LoginRequest  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(LoginRequest $request)
    {
        $existing = User::where('email', $request->input('email'))->first();

        if ($existing && $existing->is_band) {
            throw ValidationException::withMessages([
                'email' => trans('auth.banned'),
            ]);
        }

        $request->authenticate();
        $request->session()->regenerate();

        $user = $request->user();
        if ($user) {
            $pair = app(SanctumTokenPairService::class)->issue($user);
            $request->session()->put('sanctum_access_token', $pair['access_token']);
            $request->session()->put('sanctum_refresh_token', $pair['refresh_token']);
        }

        if ($this->wantsJsonLogin($request)) {
            return response()->json([
                'ok' => true,
                'redirect' => $request->session()->pull('url.intended', RouteServiceProvider::HOME),
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ] : null,
            ]);
        }

        return redirect()->intended(RouteServiceProvider::HOME);
    }

    /**
     * Inertia visits are XHR too, so they must not be mistaken for API clients.
     */
    protected function wantsJsonLogin(Request $request): bool
    {
        return ! $request->header('X-Inertia') && $request->expectsJson();
    }

    /**
     * Destroy an authenticated session.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request)
    {
        $user = Auth::guard('web')->user();
        if ($user) {
            app(SanctumTokenPairService::class)->revokePair($user);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        if ($this->wantsJsonLogin($request)) {
            return response()->json(['ok' => true, 'redirect' => '/']);
        }

        return redirect('/');
    }
}
