@php
    $title = 'Create Topology';
    $subtitle = 'Define devices, interfaces, links, and generate full Cisco CLI later';
@endphp
@extends('layouts.app')

@section('content')
    <div class="content-card">
        <h5 class="mb-3">New Topology</h5>
        <form method="POST" action="{{ route('topologies.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Topology Name</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Default Routing Protocol</label>
                    <select name="default_routing_protocol" class="form-select">
                        <option value="">none</option>
                        @foreach(['static', 'rip', 'ospf', 'eigrp'] as $proto)
                            <option value="{{ $proto }}" @selected(old('default_routing_protocol') === $proto)>{{ strtoupper($proto) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Devices JSON</label>
                    <textarea name="devices_json" class="form-control font-monospace" rows="20" required>{{ old('devices_json', $sampleDevicesJson) }}</textarea>
                    <div class="form-text">Each device must include `hostname`, `device_type`, and `interfaces`.</div>
                </div>
                <div class="col-12">
                    <label class="form-label">Links JSON</label>
                    <textarea name="links_json" class="form-control font-monospace" rows="8">{{ old('links_json', $sampleLinksJson) }}</textarea>
                    <div class="form-text">Use device hostnames in `from_device` and `to_device`.</div>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary">Create Topology</button>
                    <a href="{{ route('topologies.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </form>
    </div>
@endsection
