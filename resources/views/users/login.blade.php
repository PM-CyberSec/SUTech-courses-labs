@extends('layouts.master')
@section('title', 'Login')
@section('content')
<div id="particles-js"></div>

<div class="container d-flex justify-content-center align-items-center" style="min-height: calc(100vh - 70px);">

    <div class="card glass-card p-4" style="width: 380px;">

        <h2 class="text-center mb-4">Login</h2>

        <form action="{{ route('do_login') }}" method="post">
            @csrf

            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif

            @foreach ($errors->all() as $error)
                <div class="alert alert-danger">{{ $error }}</div>
            @endforeach

            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" placeholder="Enter email" value="{{ old('email') }}" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" placeholder="Enter password" required>
            </div>

            <button type="submit" class="btn btn-future w-100">Login</button>
        </form>

    </div>

</div>
@endsection
