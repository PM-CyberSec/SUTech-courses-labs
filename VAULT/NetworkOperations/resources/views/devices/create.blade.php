@php
    $title = 'Add Device';
    $subtitle = 'Create a new managed device';
@endphp
@extends('layouts.app')

@section('content')
    <div class="content-card">
        <form method="POST" action="{{ route('devices.store') }}">
            @include('devices._form')
            <div class="mt-3 d-flex gap-2">
                <button class="btn btn-primary">Save Device</button>
                <a href="{{ route('devices.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
@endsection
