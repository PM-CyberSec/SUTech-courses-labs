@extends('layouts.master')

@section('content')

<h2>Security Overview</h2>

<div class="card">Total Events: {{ $totalEvents }}</div>
<div class="card">High Severity: {{ $highSeverity }}</div>
<div class="card">Medium Severity: {{ $mediumSeverity }}</div>
<div class="card">Low Severity: {{ $lowSeverity }}</div>

@endsection