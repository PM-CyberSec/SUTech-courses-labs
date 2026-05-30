@php
    $title = 'Add Inventory';
    $subtitle = 'Create dynamic inventory group';
@endphp
@extends('layouts.app')

@section('content')
    <div class="content-card">
        <form method="POST" action="{{ route('inventories.store') }}">
            @include('inventories._form')
            <div class="mt-3 d-flex gap-2">
                <button class="btn btn-primary">Save Inventory</button>
                <a href="{{ route('inventories.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
@endsection
