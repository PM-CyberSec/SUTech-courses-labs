@extends('layouts.master')

@section('content')

<h2>Events Stream</h2>

<div class="card">

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Time</th>
            <th>Type</th>
            <th>Severity</th>
            <th>Process</th>
            <th>Network</th>
        </tr>
    </thead>

    <tbody>
    @forelse($events as $event)
        <tr>
            <td>{{ $event->id }}</td>
            <td>{{ $event->event_timestamp }}</td>
            <td>{{ $event->type }}</td>
            <td>{{ $event->severity }}</td>
            <td>
                {{ $event->pid }} {{ $event->process_name }}
            </td>
            <td>
                {{ $event->src_ip }} → {{ $event->dst_ip }}
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="6">No events found</td>
        </tr>
    @endforelse
    </tbody>

</table>

</div>

@endsection