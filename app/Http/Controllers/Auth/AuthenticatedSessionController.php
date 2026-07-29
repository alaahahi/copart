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
     * @param  \App\Http\Requests\Auth\LoginRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(LoginRequest $request)
    {
        
        //$admin_id =UserType::where('name', 'admin')->first() ? UserType::where('name', 'admin')->first()->id :"";
        $user_id=User::where('email', $request->email)->first() ? User::where('email', $request->email)->first()->is_band :1;
        if(!$user_id){
            $request->authenticate();
            $request->session()->regenerate();

            $user = $request->user();
            if ($user) {
                $pair = app(SanctumTokenPairService::class)->issue($user);
                $request->session()->put('sanctum_access_token', $pair['access_token']);
                $request->session()->put('sanctum_refresh_token', $pair['refresh_token']);
            }

            return redirect()->intended(RouteServiceProvider::HOME);
        }else

        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);

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

        return redirect('/');
    }
}
