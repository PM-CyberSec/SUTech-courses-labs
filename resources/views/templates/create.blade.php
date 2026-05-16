@php
    $title = 'Add Template';
    $subtitle = 'Create deployment template';
@endphp
@extends('layouts.app')

@section('content')
    <div class="content-card">
        <form method="POST" action="{{ route('templates.store') }}">
            @include('templates._form')
            <div class="mt-3 d-flex gap-2">
                <button class="btn btn-primary">Save Template</button>
                <a href="{{ route('templates.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
@endsection
