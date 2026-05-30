<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $rateLimitKey = strtolower((string) $request->input('email')).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);

            Log::warning('Login rate limit lockout', [
                'email' => strtolower((string) $request->input('email')),
                'ip' => $request->ip(),
                'retry_after_seconds' => $seconds,
            ]);

            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Please retry in {$seconds} seconds.",
            ])->status(429);
        }

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            RateLimiter::hit($rateLimitKey, 60);

            Log::warning('Failed login attempt', [
                'email' => strtolower((string) $credentials['email']),
                'ip' => $request->ip(),
            ]);

            return back()
                ->withErrors(['email' => 'The provided credentials are incorrect.'])
                ->onlyInput('email');
        }

        RateLimiter::clear($rateLimitKey);
        $request->session()->regenerate();

        if (! (bool) Auth::user()?->is_active || Auth::user()?->approved_at === null) {
            Log::warning('Blocked login due to inactive/unapproved account', [
                'user_id' => Auth::id(),
                'email' => Auth::user()?->email,
                'ip' => $request->ip(),
            ]);

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'Your account is pending approval or has been deactivated.',
            ])->onlyInput('email');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You are now logged out.');
    }
}
