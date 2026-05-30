@php
    $title = 'Edit Inventory';
    $subtitle = $inventory->name;
@endphp
@extends('layouts.app')

@section('content')
    <div class="content-card">
        <form method="POST" action="{{ route('inventories.update', $inventory) }}">
            @include('inventories._form')
            <div class="mt-3 d-flex gap-2">
                <button class="btn btn-primary">Update Inventory</button>
                <a href="{{ route('inventories.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
@endsection
