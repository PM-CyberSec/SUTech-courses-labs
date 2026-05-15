@extends('layouts.auth')

@section('title', 'Login | DLDS SOC')

@section('content')
<h2 class="page-title">Welcome back</h2>
<p class="page-description">Sign in to continue monitoring live security events.</p>

<form method="POST" action="{{ route('login.store') }}" class="auth-form" novalidate>
    @csrf

    <div class="form-group">
        <label for="email">Email address</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus placeholder="analyst@company.com">
        @error('email')
            <p class="auth-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="form-group">
        <label for="password">Password</label>
        <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="Enter your password">
        @error('password')
            <p class="auth-error">{{ $message }}</p>
        @enderror
    </div>

    <label class="auth-checkbox" for="remember">
        <input id="remember" type="checkbox" name="remember" value="1" @checked(old('remember'))>
        <span>Keep me signed in on this device</span>
    </label>

    <button type="submit">Log In</button>
</form>

<p class="auth-switch">
    @if(config('app.public_registration') || !app()->environment('production'))
        No account yet?
        <a href="{{ route('register') }}">Create one</a>
    @endif
</p>
@endsection
