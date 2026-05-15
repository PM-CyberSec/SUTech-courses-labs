@extends('layouts.master')
@section('title', 'Sandbox')

@section('content')
<div class="container-fluid products-page py-4">
    <div class="row g-4">
        {{-- Left Sidebar --}}
        <div class="col-12 col-md-3">
            <div class="card glass-card shadow-lg sticky-top" style="top: 2rem;">
                <div class="card-body p-4">
                    <h5 class="text-cyan text-glow-cyan mb-4 text-uppercase fw-bold" style="letter-spacing: 0.1rem;">
                        <i class="bi bi-box me-2"></i>Sandbox
                    </h5>
                    <div class="d-grid gap-2">
                        <a href="{{ route('sandbox.even') }}" class="btn btn-future text-start {{ request()->routeIs('sandbox.even') ? 'active' : '' }}">
                            <i class="bi bi-hash me-2"></i>Even Numbers
                        </a>
                        <a href="{{ route('sandbox.prime') }}" class="btn btn-future text-start {{ request()->routeIs('sandbox.prime') ? 'active' : '' }}">
                            <i class="bi bi-heptagon me-2"></i>Prime Numbers
                        </a>
                        <a href="{{ route('sandbox.multiple') }}" class="btn btn-future text-start {{ request()->routeIs('sandbox.multiple') ? 'active' : '' }}">
                            <i class="bi bi-grid-3x3 me-2"></i>Multiplication Table
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right Content Area --}}
        <div class="col-12 col-md-9">
            <div class="card glass-card h-100 min-vh-75 shadow-lg">
                <div class="card-body p-4">
                    @yield('sandbox_content')
                    
                    @if(!View::hasSection('sandbox_content'))
                        <div class="text-center py-5">
                            <h2 class="text-cyan opacity-50">Select a tool from the sidebar to begin.</h2>
                            <p class="text-light opacity-25">Welcome to the development sandbox environment.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection