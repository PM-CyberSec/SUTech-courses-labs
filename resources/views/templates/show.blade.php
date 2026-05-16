@php
    $title = 'Template Preview';
    $subtitle = $template->name;
@endphp
@extends('layouts.app')

@section('content')
    <div class="row g-3">
        <div class="col-lg-5">
            <div class="content-card">
                <h5 class="mb-3">{{ $template->name }}</h5>
                <p class="text-muted">{{ $template->description ?: 'No description.' }}</p>
                <div class="mb-2"><span class="badge text-bg-light border">{{ $template->category }}</span> <span class="badge text-bg-secondary">v{{ $template->version }}</span></div>
                <div class="small text-muted mb-3">Deployments: {{ $template->deployments_count }}</div>

                @if(in_array($currentRole, ['admin', 'engineer']))
                    <form method="POST" action="{{ route('templates.preview', $template) }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label">Device Context (optional)</label>
                            <select name="device_id" class="form-select">
                                <option value="">No device context</option>
                                @foreach($devices as $device)
                                    <option value="{{ $device->id }}">{{ $device->hostname }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Deployment Variables JSON (optional)</label>
                            <textarea name="deployment_vars" class="form-control font-monospace" rows="4" placeholder='{"key":"value"}'>{{ old('deployment_vars') }}</textarea>
                        </div>
                        <button class="btn btn-primary">Render Preview</button>
                    </form>
                @endif
            </div>
        </div>
        <div class="col-lg-7">
            <div class="content-card">
                <h6 class="mb-2">Template Source</h6>
                <pre class="bg-dark text-light p-3 rounded small">{{ $template->template_body }}</pre>
                @if($preview)
                    <h6 class="mt-3 mb-2">Rendered Preview</h6>
                    <pre class="bg-success-subtle p-3 rounded small border">{{ $preview }}</pre>
                @endif
            </div>
        </div>
    </div>
@endsection
