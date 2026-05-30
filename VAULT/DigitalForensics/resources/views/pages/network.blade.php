@extends('layouts.master')

@section('content')

<h2>Network Activity</h2>

<div class="card">

<table>
    <thead>
        <tr>
            <th>Source IP</th>
            <th>Destination IP</th>
            <th>Ports</th>
            <th>Bytes</th>
        </tr>
    </thead>

    <tbody>
    @forelse($network as $n)
        <tr>
            <td>{{ $n->src_ip }}</td>
            <td>{{ $n->dst_ip }}</td>
            <td>{{ $n->src_port }} → {{ $n->dst_port }}</td>
            <td>{{ $n->bytes_sent }}</td>
        </tr>
    @empty
        <tr><td colspan="4">No network data</td></tr>
    @endforelse
    </tbody>
</table>

</div>

@endsection