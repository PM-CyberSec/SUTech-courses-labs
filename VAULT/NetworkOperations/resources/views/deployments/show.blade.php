@php
    $title = 'Deployment Details';
    $subtitle = 'Deployment #'.$deployment->id;
@endphp
@extends('layouts.app')

@section('content')
    <div class="row g-3">
        <div class="col-lg-5">
            <div class="content-card">
                <h5 class="mb-3">Execution Summary</h5>
                <div class="mb-1"><strong>Device:</strong> {{ $deployment->device?->hostname ?? '-' }}</div>
                <div class="mb-1"><strong>Template:</strong> {{ $deployment->configTemplate?->name ?? '-' }}</div>
                <div class="mb-1"><strong>Playbook:</strong> {{ $deployment->playbook_name }}</div>
                <div class="mb-1"><strong>Status:</strong> <span class="badge text-bg-secondary">{{ $deployment->status }}</span></div>
                <div class="mb-1"><strong>Simulation:</strong> {{ $deployment->simulation_mode ? 'enabled' : 'disabled' }}</div>
                <div class="mb-1"><strong>Pre-check:</strong> {{ $deployment->precheck_status }}</div>
                <div class="mb-1"><strong>Post-check:</strong> {{ $deployment->postcheck_status }}</div>
                <div class="mb-1"><strong>Idempotent:</strong> {{ $deployment->is_idempotent === null ? '-' : ($deployment->is_idempotent ? 'yes' : 'no') }}</div>
                <div class="mb-1"><strong>Started:</strong> {{ $deployment->started_at?->format('Y-m-d H:i:s') ?? '-' }}</div>
                <div class="mb-3"><strong>Finished:</strong> {{ $deployment->finished_at?->format('Y-m-d H:i:s') ?? '-' }}</div>

                @if(in_array($currentRole, ['admin', 'engineer']) && in_array($deployment->status, ['pending', 'failed']))
                    <form method="POST" action="{{ route('deployments.execute', $deployment) }}" class="mb-2">
                        @csrf
                        <button class="btn btn-success w-100">Run Deployment</button>
                    </form>
                @endif

                @if(in_array($currentRole, ['admin', 'engineer']))
                    <form method="POST" action="{{ route('deployments.rollback', $deployment) }}" class="js-confirm" data-confirm="Execute rollback for this deployment?">
                        @csrf
                        <input type="hidden" name="playbook_name" value="rollback.yml">
                        <button class="btn btn-outline-danger w-100">Rollback</button>
                    </form>
                @endif
            </div>
        </div>
        <div class="col-lg-7">
            <div class="content-card mb-3">
                <h6>Validation Results</h6>
                @if(empty($deployment->validation_results))
                    <div class="text-muted mb-3">No validation details recorded.</div>
                @else
                    @if(!empty($deployment->validation_results['errors']))
                        <div class="alert alert-danger py-2">
                            <div class="fw-semibold">Errors</div>
                            <ul class="mb-0">
                                @foreach($deployment->validation_results['errors'] as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if(!empty($deployment->validation_results['warnings']))
                        <div class="alert alert-warning py-2">
                            <div class="fw-semibold">Warnings</div>
                            <ul class="mb-0">
                                @foreach($deployment->validation_results['warnings'] as $warning)
                                    <li>{{ $warning }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endif

                <h6>Generated Cisco Config</h6>
                @if($deployment->generated_config)
                    <pre class="bg-primary-subtle border p-3 rounded small">{{ $deployment->generated_config }}</pre>
                @else
                    <div class="text-muted mb-3">No generated config stored.</div>
                @endif

                <h6>Diff vs Last Working Config</h6>
                @if(empty($configDiff))
                    <div class="text-muted mb-3">No diff available or no previous working snapshot.</div>
                @else
                    <pre class="small border rounded p-3">@foreach($configDiff as $line){{ $line['type'] === 'added' ? '+ ' : '- ' }}{{ $line['text'] }}
@endforeach</pre>
                @endif
            </div>

            <div class="content-card mb-3">
                <h6>Output</h6>
                @if($deployment->output)
                    <pre class="bg-dark text-light p-3 rounded small">{{ $deployment->output }}</pre>
                @else
                    <div class="text-muted">No output captured yet.</div>
                @endif
                @if($deployment->errors)
                    <h6 class="mt-3 text-danger">Errors</h6>
                    <pre class="bg-danger-subtle border border-danger-subtle p-3 rounded small">{{ $deployment->errors }}</pre>
                @endif
            </div>

            <div class="content-card mb-3">
                <h6>Latest Logs</h6>
                @if($deployment->logs->isEmpty())
                    <div class="text-muted">No logs recorded.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Stage</th>
                                    <th>Level</th>
                                    <th>Message</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($deployment->logs->sortByDesc('id')->take(20) as $log)
                                    <tr>
                                        <td>{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                                        <td>{{ $log->stage }}</td>
                                        <td>{{ $log->level }}</td>
                                        <td>{{ $log->message }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="content-card">
                <h6>Rollback History</h6>
                @if($deployment->rollbacks->isEmpty())
                    <div class="text-muted">No rollback attempts.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Status</th>
                                    <th>Strategy</th>
                                    <th>Started</th>
                                    <th>Finished</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($deployment->rollbacks->sortByDesc('id') as $rollback)
                                    <tr>
                                        <td>#{{ $rollback->id }}</td>
                                        <td>{{ $rollback->status }}</td>
                                        <td>{{ $rollback->strategy }}</td>
                                        <td>{{ $rollback->started_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                                        <td>{{ $rollback->finished_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
