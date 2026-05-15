@extends('layouts.master')

@section('content')

<h2>Process Monitoring</h2>

<div class="card">

<table>
    <thead>
        <tr>
            <th>PID</th>
            <th>Process</th>
            <th>File</th>
        </tr>
    </thead>

    <tbody>
    @forelse($processes as $p)
        <tr>
            <td>{{ $p->pid }}</td>
            <td>{{ $p->process_name }}</td>
            <td>{{ $p->file }}</td>
        </tr>
    @empty
        <tr><td colspan="3">No process data</td></tr>
    @endforelse
    </tbody>

</table>

</div>

@endsection