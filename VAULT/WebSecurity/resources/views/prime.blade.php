@extends('Sandbox')
@section('title', 'Prime Numbers')

@section('sandbox_content')
<div class="p-3 rounded-4 glass-effect info-container">
    <h3 class="h5 text-cyan mb-4 border-bottom border-white border-opacity-10 pb-2">Prime Numbers Detector (1-100)</h3>
    <div class="d-flex flex-wrap gap-2">
        @php
            function isPrime($number) {
                if($number <= 1) return false;
                for($i = 2; $i <= sqrt($number); $i++) {
                    if($number % $i == 0) return false;
                }
                return true;
            }
        @endphp
        
        @foreach (range(1, 100) as $i)
            @if(isPrime($i))
                <span class="badge bg-info text-dark shadow-sm" style="width: 2.5rem;">{{ $i }}</span>
            @else
                <span class="badge bg-dark text-light opacity-50 shadow-sm" style="width: 2.5rem; border: 1px solid rgba(255,255,255,0.1);">{{ $i }}</span>
            @endif
        @endforeach
    </div>
</div>
@endsection