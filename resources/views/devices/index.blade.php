@php
    $title = 'Devices';
    $subtitle = 'Manage network devices';
@endphp
@extends('layouts.app')

@section('content')
    <div class="content-card">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <form method="GET" class="d-flex gap-2 flex-wrap">
                <input type="text" name="search" class="form-control" placeholder="Search hostname / ip" value="{{ request('search') }}">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <button class="btn btn-outline-secondary">Filter</button>
            </form>
            @if(in_array($currentRole, ['admin', 'engineer']))
                <a href="{{ route('devices.create') }}" class="btn btn-primary">Add Device</a>
            @endif
        </div>

        @if($devices->isEmpty())
            <div class="text-center text-muted py-4">No devices configured yet.</div>
        @else
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Hostname</th>
                            <th>IP Address</th>
                            <th>Platform</th>
                            <th>Status</th>
                            <th>Inventory</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($devices as $device)
                            <tr>
                                <td>{{ $device->hostname }}</td>
                                <td>{{ $device->mgmt_ip }}</td>
                                <td>{{ $device->platform }}</td>
                                <td><span class="badge text-bg-secondary">{{ $device->status }}</span></td>
                                <td>{{ $device->inventory?->name ?? '-' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('devices.auto-config', $device) }}" class="btn btn-sm btn-outline-dark">Auto Config</a>
                                    @if(in_array($currentRole, ['admin', 'engineer']))
                                        <a href="{{ route('devices.edit', $device) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                    @endif
                                    @if($currentRole === 'admin')
                                        <form method="POST" action="{{ route('devices.destroy', $device) }}" class="d-inline js-confirm" data-confirm="Delete this device?">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $devices->links() }}
        @endif
    </div>
@endsection
