@php
    $title = 'Topologies';
    $subtitle = 'Topology-based Cisco Packet Tracer config generation';
@endphp
@extends('layouts.app')

@section('content')
    <div class="content-card">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="mb-0">Network Topologies</h5>
            @if(in_array($currentRole, ['admin', 'engineer']))
                <a href="{{ route('topologies.create') }}" class="btn btn-primary">Create Topology</a>
            @endif
        </div>

        @if($topologies->isEmpty())
            <div class="text-center text-muted py-4">
                No topologies found yet.
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Default Routing</th>
                            <th>Devices</th>
                            <th>Links</th>
                            <th>Generated Configs</th>
                            <th>Updated</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($topologies as $topology)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $topology->name }}</div>
                                    <div class="small text-muted">{{ $topology->description ?: 'No description' }}</div>
                                </td>
                                <td>
                                    <span class="badge text-bg-light border">{{ $topology->default_routing_protocol ?: 'none' }}</span>
                                </td>
                                <td>{{ $topology->topology_devices_count }}</td>
                                <td>{{ $topology->topology_links_count }}</td>
                                <td>{{ $topology->generated_configs_count }}</td>
                                <td>{{ $topology->updated_at?->format('Y-m-d H:i') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('topologies.show', $topology) }}" class="btn btn-sm btn-outline-primary">Open</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $topologies->links() }}
        @endif
    </div>
@endsection
