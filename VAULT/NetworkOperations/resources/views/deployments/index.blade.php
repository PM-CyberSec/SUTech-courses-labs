@php
    $title = 'Deployments';
    $subtitle = 'Execution history and status';
@endphp
@extends('layouts.app')

@section('content')
    <div class="content-card">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <form method="GET" class="d-flex gap-2 flex-wrap">
                <select name="device_id" class="form-select">
                    <option value="">All Devices</option>
                    @foreach($devices as $device)
                        <option value="{{ $device->id }}" @selected((string) request('device_id') === (string) $device->id)>{{ $device->hostname }}</option>
                    @endforeach
                </select>
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
                <button class="btn btn-outline-secondary">Filter</button>
            </form>
            @if(in_array($currentRole, ['admin', 'engineer']))
                <a href="{{ route('deployments.create') }}" class="btn btn-primary">Run Deployment</a>
            @endif
        </div>

        @if($deployments->isEmpty())
            <div class="text-center text-muted py-4">No deployments found.</div>
        @else
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Device</th>
                            <th>Template</th>
                            <th>Playbook</th>
                            <th>Status</th>
                            <th>Pre/Post Check</th>
                            <th>Idempotent</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($deployments as $deployment)
                            <tr>
                                <td>#{{ $deployment->id }}</td>
                                <td>{{ $deployment->device?->hostname ?? '-' }}</td>
                                <td>{{ $deployment->configTemplate?->name ?? '-' }}</td>
                                <td>{{ $deployment->playbook_name }}</td>
                                <td><span class="badge text-bg-secondary">{{ $deployment->status }}</span></td>
                                <td>{{ $deployment->precheck_status }} / {{ $deployment->postcheck_status }}</td>
                                <td>{{ $deployment->is_idempotent === null ? '-' : ($deployment->is_idempotent ? 'yes' : 'no') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('deployments.show', $deployment) }}" class="btn btn-sm btn-outline-secondary">View</a>
                                    @if(in_array($currentRole, ['admin', 'engineer']) && in_array($deployment->status, ['pending', 'failed']))
                                        <form method="POST" action="{{ route('deployments.execute', $deployment) }}" class="d-inline">
                                            @csrf
                                            <button class="btn btn-sm btn-success">Execute</button>
                                        </form>
                                    @endif
                                    @if($currentRole === 'admin')
                                        <form method="POST" action="{{ route('deployments.destroy', $deployment) }}" class="d-inline js-confirm" data-confirm="Delete deployment record?">
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
            {{ $deployments->links() }}
        @endif
    </div>
@endsection
