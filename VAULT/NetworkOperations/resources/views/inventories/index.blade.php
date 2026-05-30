@php
    $title = 'Inventories';
    $subtitle = 'Dynamic inventory groups';
@endphp
@extends('layouts.app')

@section('content')
    <div class="content-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Inventory List</h5>
            @if(in_array($currentRole, ['admin', 'engineer']))
                <a href="{{ route('inventories.create') }}" class="btn btn-primary">Add Inventory</a>
            @endif
        </div>

        @if($inventories->isEmpty())
            <div class="text-center text-muted py-4">No inventories created yet.</div>
        @else
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Group</th>
                            <th>Devices</th>
                            <th>Status</th>
                            <th>Related Devices</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($inventories as $inventory)
                            <tr>
                                <td>{{ $inventory->name }}</td>
                                <td>{{ $inventory->group_name ?? '-' }}</td>
                                <td>{{ $inventory->devices_count }}</td>
                                <td>
                                    <span class="badge {{ $inventory->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                        {{ $inventory->is_active ? 'active' : 'inactive' }}
                                    </span>
                                </td>
                                <td>
                                    @if($inventory->devices->isEmpty())
                                        <span class="text-muted">No devices</span>
                                    @else
                                        @foreach($inventory->devices as $device)
                                            <span class="badge text-bg-light border">{{ $device->hostname }}</span>
                                        @endforeach
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if(in_array($currentRole, ['admin', 'engineer']))
                                        <a href="{{ route('inventories.edit', $inventory) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                    @endif
                                    @if($currentRole === 'admin')
                                        <form method="POST" action="{{ route('inventories.destroy', $inventory) }}" class="d-inline js-confirm" data-confirm="Delete this inventory?">
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
            {{ $inventories->links() }}
        @endif
    </div>
@endsection
