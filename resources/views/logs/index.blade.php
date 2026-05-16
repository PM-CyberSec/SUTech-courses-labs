@php
    $title = 'Deployment Logs';
    $subtitle = 'Filter and inspect execution traces';
@endphp
@extends('layouts.app')

@section('content')
    <div class="content-card">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-3">
                <select name="device_id" class="form-select">
                    <option value="">All Devices</option>
                    @foreach($devices as $device)
                        <option value="{{ $device->id }}" @selected((string) request('device_id') === (string) $device->id)>{{ $device->hostname }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="level" class="form-select">
                    <option value="">All Levels</option>
                    @foreach($levels as $level)
                        <option value="{{ $level }}" @selected(request('level') === $level)>{{ $level }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
            </div>
            <div class="col-md-2">
                <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
            </div>
            <div class="col-md-1">
                <button class="btn btn-outline-secondary w-100">Go</button>
            </div>
        </form>

        @if($logs->isEmpty())
            <div class="text-center text-muted py-4">No logs match this filter.</div>
        @else
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Device</th>
                            <th>Deployment</th>
                            <th>Status</th>
                            <th>Stage</th>
                            <th>Level</th>
                            <th>Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($logs as $log)
                            <tr>
                                <td>{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                                <td>{{ $log->deployment?->device?->hostname ?? '-' }}</td>
                                <td>
                                    @if($log->deployment)
                                        <a href="{{ route('deployments.show', $log->deployment) }}">#{{ $log->deployment->id }}</a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ $log->deployment?->status ?? '-' }}</td>
                                <td>{{ $log->stage }}</td>
                                <td>{{ $log->level }}</td>
                                <td>{{ $log->message }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $logs->links() }}
        @endif
    </div>
@endsection
