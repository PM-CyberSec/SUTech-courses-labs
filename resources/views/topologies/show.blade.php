@php
    $title = 'Topology Details';
    $subtitle = $topology->name;
@endphp
@extends('layouts.app')

@section('content')
    <div class="content-card mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="mb-1">{{ $topology->name }}</h5>
                <div class="text-muted">{{ $topology->description ?: 'No description provided.' }}</div>
                <div class="small mt-1">
                    Default routing:
                    <span class="badge text-bg-light border">{{ $topology->default_routing_protocol ?: 'none' }}</span>
                </div>
            </div>
            @if(in_array($currentRole, ['admin', 'engineer']))
                <form method="POST" action="{{ route('topologies.generate-configs', $topology) }}">
                    @csrf
                    <button class="btn btn-success">Generate Configs for All Devices</button>
                </form>
            @endif
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-7">
            <div class="content-card h-100">
                <h6 class="mb-3">Device List</h6>
                @if($topology->topologyDevices->isEmpty())
                    <div class="text-muted">No devices in this topology.</div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                            <tr>
                                <th>Hostname</th>
                                <th>Type</th>
                                <th>Routing</th>
                                <th>Interfaces</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($topology->topologyDevices as $device)
                                <tr>
                                    <td class="fw-semibold">{{ $device->hostname }}</td>
                                    <td>{{ $device->device_type }}</td>
                                    <td>{{ $device->routing_protocol ?: '-' }}</td>
                                    <td>
                                        @php
                                            $allInterfaces = $device->interfaces->merge($device->deviceInterfaces)->unique(function ($item) {
                                                return $item->name;
                                            });
                                        @endphp
                                        @if($allInterfaces->isEmpty())
                                            <span class="text-muted">No interfaces</span>
                                        @else
                                            <pre class="small bg-light p-2 rounded mb-0">@foreach($allInterfaces as $interface){{ $interface->name }} | {{ $interface->mode }}@if($interface->ip_address) | {{ $interface->ip_address }} {{ $interface->subnet_mask }}@endif
@endforeach</pre>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
        <div class="col-lg-5">
            <div class="content-card h-100">
                <h6 class="mb-3">Links Between Devices</h6>
                @if($topology->topologyLinks->isEmpty())
                    <div class="text-muted">No links defined.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                            <tr>
                                <th>From</th>
                                <th>To</th>
                                <th>Type</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($topology->topologyLinks as $link)
                                <tr>
                                    <td>{{ $link->fromDevice?->hostname }}:{{ $link->from_interface_name ?: '?' }}</td>
                                    <td>{{ $link->toDevice?->hostname }}:{{ $link->to_interface_name ?: '?' }}</td>
                                    <td>
                                        <span class="badge text-bg-light border">{{ $link->link_type }}</span>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="content-card">
        <h6 class="mb-3">Generated Config Output</h6>
        @if($topology->generatedConfigs->isEmpty())
            <div class="text-muted">No generated configs yet. Click "Generate Configs for All Devices".</div>
        @else
            <ul class="nav nav-tabs" id="configTabs" role="tablist">
                @foreach($topology->generatedConfigs as $generatedConfig)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link @if($loop->first) active @endif" id="tab-{{ $generatedConfig->id }}" data-bs-toggle="tab" data-bs-target="#config-{{ $generatedConfig->id }}" type="button" role="tab">
                            {{ $generatedConfig->topologyDevice?->hostname ?? 'device-'.$generatedConfig->topology_device_id }}
                        </button>
                    </li>
                @endforeach
            </ul>
            <div class="tab-content border border-top-0 p-3 rounded-bottom" id="configTabsContent">
                @foreach($topology->generatedConfigs as $generatedConfig)
                    <div class="tab-pane fade @if($loop->first) show active @endif" id="config-{{ $generatedConfig->id }}" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                            <div class="small text-muted">
                                Generated: {{ $generatedConfig->generated_at?->format('Y-m-d H:i:s') }}
                            </div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-outline-secondary js-copy-config" data-target="config-text-{{ $generatedConfig->id }}">Copy</button>
                                <a href="{{ route('topologies.generated-configs.download', [$topology, $generatedConfig]) }}" class="btn btn-sm btn-outline-primary">Download .txt</a>
                            </div>
                        </div>
                        <pre id="config-text-{{ $generatedConfig->id }}" class="bg-dark text-light p-3 rounded small mb-0">{{ $generatedConfig->config_text }}</pre>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('.js-copy-config').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const targetId = btn.getAttribute('data-target');
                const target = document.getElementById(targetId);
                if (!target) return;
                const text = target.innerText || '';
                navigator.clipboard.writeText(text).then(function() {
                    const oldText = btn.innerText;
                    btn.innerText = 'Copied';
                    setTimeout(function() {
                        btn.innerText = oldText;
                    }, 1200);
                });
            });
        });
    </script>
@endsection
