@php
    $title = 'Edit Template';
    $subtitle = $template->name;
@endphp
@extends('layouts.app')

@section('content')
    <div class="content-card">
        <form method="POST" action="{{ route('templates.update', $template) }}">
            @include('templates._form')
            <div class="mt-3 d-flex gap-2">
                <button class="btn btn-primary">Update Template</button>
                <a href="{{ route('templates.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
@endsection
