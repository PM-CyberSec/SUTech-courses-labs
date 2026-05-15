@extends('layouts.auth')

@section('title', 'Sign Up | DLDS SOC')

@section('content')
<h2 class="page-title">Create your account</h2>
<p class="page-description">Set up analyst access for the SOC dashboard.</p>

<form method="POST" action="{{ route('register.store') }}" class="auth-form" novalidate>
    @csrf

    <div class="form-group">
        <label for="name">Full name</label>
        <input id="name" type="text" name="name" value="{{ old('name') }}" required autocomplete="name" autofocus placeholder="SOC Analyst">
        @error('name')
            <p class="auth-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="form-group">
        <label for="email">Email address</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="analyst@company.com">
        @error('email')
            <p class="auth-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="form-group">
        <label for="password">Password</label>
        <input id="password" type="password" name="password" required autocomplete="new-password" placeholder="At least 8 characters">
        @error('password')
            <p class="auth-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="form-group">
        <label for="password_confirmation">Confirm password</label>
        <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="Re-enter your password">
    </div>

    <button type="submit">Create Account</button>
</form>

<p class="auth-switch">
    Already registered?
    <a href="{{ route('login') }}">Log in</a>
</p>
@endsection
