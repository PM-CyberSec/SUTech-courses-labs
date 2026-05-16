@php
    $title = 'Dashboard';
    $subtitle = 'AutoConfigLab Overview';
@endphp

@extends('layouts.app')

@section('content')
    <div class="row g-3 mb-3">
        <div class="col-md-6 col-lg-3">
            <div class="stat-card">
                <div class="text-muted small">Devices</div>
                <div class="h4 mb-0">{{ $stats['devices'] }}</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="stat-card">
                <div class="text-muted small">Inventories</div>
                <div class="h4 mb-0">{{ $stats['inventories'] }}</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="stat-card">
                <div class="text-muted small">Templates</div>
                <div class="h4 mb-0">{{ $stats['templates'] }}</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="stat-card">
                <div class="text-muted small">Deployments</div>
                <div class="h4 mb-0">{{ $stats['total_deployments'] }}</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="stat-card border-success-subtle">
                <div class="text-muted small">Successful Deployments</div>
                <div class="h4 mb-0 text-success">{{ $stats['completed_deployments'] ?? 0 }}</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="stat-card border-danger-subtle">
                <div class="text-muted small">Failed Deployments</div>
                <div class="h4 mb-0 text-danger">{{ $stats['failed_deployments'] ?? 0 }}</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="stat-card">
                <div class="text-muted small">Pending Deployments</div>
                <div class="h4 mb-0">{{ $stats['pending_deployments'] ?? 0 }}</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="stat-card">
                <div class="text-muted small">Success Rate</div>
                <div class="h4 mb-0 text-success">{{ $stats['success_rate'] ?? 0 }}%</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="stat-card">
                <div class="text-muted small">Failure Rate</div>
                <div class="h4 mb-0 text-danger">{{ $stats['failure_rate'] ?? 0 }}%</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="stat-card">
                <div class="text-muted small">Est. Time Saved</div>
                <div class="h4 mb-0">{{ $stats['estimated_time_saved'] ?? 0 }} min</div>
            </div>
        </div>
    </div>

    <div class="content-card mb-3">
        <div class="d-flex flex-wrap gap-2">
            @if(in_array($currentRole, ['admin', 'engineer']))
                <a href="{{ route('devices.create') }}" class="btn btn-primary">Add Device</a>
                <a href="{{ route('templates.create') }}" class="btn btn-outline-primary">Add Template</a>
                <a href="{{ route('deployments.wizard') }}" class="btn btn-success">Open Wizard</a>
                <a href="{{ route('devices.index') }}" class="btn btn-warning">Generate Cisco Config</a>
                <a href="{{ route('topologies.index') }}" class="btn btn-info">Topology Config Lab</a>
            @endif
            <a href="{{ route('logs.index') }}" class="btn btn-dark">View Logs</a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-7">
            <div class="content-card h-100">
                <h5>Deployment Success vs Failed</h5>
                <canvas id="deploymentChart" height="120"></canvas>
                <div class="small text-muted mt-2">
                    Rolled back: {{ $deploymentHistory['rolled_back'] ?? 0 }} | Total: {{ $stats['total_deployments'] }}
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="content-card h-100">
                <h5>Device Status Indicators</h5>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    @forelse($deviceStatus as $status => $count)
                        <span class="badge text-bg-light border">{{ $status }}: {{ $count }}</span>
                    @empty
                        <span class="text-muted">No device status data.</span>
                    @endforelse
                </div>
                <h6 class="mt-3">Live Logs</h6>
                @if($recentLogs->isEmpty())
                    <div class="text-muted">No log entries yet.</div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($recentLogs as $log)
                            <li class="list-group-item px-0">
                                <div class="small">
                                    <strong>[{{ strtoupper($log->level) }}]</strong>
                                    {{ $log->deployment?->device?->hostname ?? 'n/a' }} -
                                    {{ $log->message }}
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    <div class="content-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Latest Deployments</h5>
            <a href="{{ route('deployments.index') }}" class="btn btn-sm btn-outline-secondary">View All</a>
        </div>
        @if($latestDeployments->isEmpty())
            <div class="text-center text-muted py-4">No deployment activity yet.</div>
        @else
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Device</th>
                            <th>Template</th>
                            <th>Status</th>
                            <th>Started</th>
                            <th>Finished</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($latestDeployments as $deployment)
                            <tr>
                                <td><a href="{{ route('deployments.show', $deployment) }}">#{{ $deployment->id }}</a></td>
                                <td>{{ $deployment->device?->hostname ?? 'n/a' }}</td>
                                <td>{{ $deployment->configTemplate?->name ?? 'n/a' }}</td>
                                <td>
                                    <span class="badge {{ $deployment->status === 'success' ? 'text-bg-success' : ($deployment->status === 'failed' ? 'text-bg-danger' : 'text-bg-secondary') }}">
                                        {{ $deployment->status }}
                                    </span>
                                </td>
                                <td>{{ $deployment->started_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                <td>{{ $deployment->finished_at?->format('Y-m-d H:i') ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script>
        (function() {
            const ctx = document.getElementById('deploymentChart');
            if (!ctx) return;
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Success', 'Failed'],
                    datasets: [{
                        label: 'Deployments',
                            data: [{{ $stats['completed_deployments'] ?? 0 }}, {{ $stats['failed_deployments'] ?? 0 }}],
                        backgroundColor: ['#16a34a', '#dc2626']
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {legend: {display: false}}
                }
            });
        })();
    </script>
@endsection
