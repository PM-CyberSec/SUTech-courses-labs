@php
    $title = 'Edit Device';
    $subtitle = $device->hostname;
@endphp
@extends('layouts.app')

@section('content')
    <div class="content-card">
        <form method="POST" action="{{ route('devices.update', $device) }}">
            @include('devices._form')
            <div class="mt-3 d-flex gap-2">
                <button class="btn btn-primary">Update Device</button>
                <a href="{{ route('devices.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
@endsection
