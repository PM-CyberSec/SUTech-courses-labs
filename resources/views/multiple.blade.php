@extends('Sandbox')
@section('title', 'Multiplication Table')

@section('sandbox_content')
<div class="p-4 rounded-4 glass-effect info-container" style="background: rgba(0, 0, 0, 0.3) !important; border: 1px solid rgba(0, 229, 255, 0.1) !important;">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom border-white border-opacity-10 pb-3">
        <h3 class="h5 text-cyan mb-0">Multiplication Table of <span class="text-glow-cyan">{{ $j }}</span></h3>
        <span class="badge bg-dark text-info border border-info border-opacity-25 py-2 px-3">
            <i class="bi bi-info-circle me-2"></i>URL Path: /multiple/{{ $j }}
        </span>
    </div>

    <div class="alert alert-info bg-dark border-info border-opacity-25 text-light small mb-4">
        <i class="bi bi-lightbulb me-2 text-warning"></i>
        <strong>Tip:</strong> You can calculate any table by typing its number directly in the URL (e.g., <code>/multiple/12</code>).
    </div>
    
    <div class="table-responsive">
        <table class="table table-borderless text-light mb-0">
            <tbody>
                @foreach (range(1, 10) as $i)
                    <tr class="border-bottom border-white border-opacity-10">
                        <td class="py-3 fw-bold text-info" style="width: 100px;">{{ $i }} <span class="text-light opacity-50 mx-2">×</span> {{ $j }}</td>
                        <td class="py-3 text-center" style="width: 50px;"><i class="bi bi-arrow-right text-light opacity-25"></i></td>
                        <td class="py-3 fw-bold text-warning fs-5">= {{ $i * $j }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4 pt-3 border-top border-white border-opacity-10">
        <label class="form-label small text-info text-uppercase fw-bold">Select another number</label>
        <div class="d-flex flex-wrap gap-2 mt-2">
            @foreach(range(1, 10) as $n)
                <a href="{{ route('sandbox.multiple', ['j' => $n]) }}" class="btn btn-sm {{ $j == $n ? 'btn-future' : 'btn-outline-info' }} shadow-sm" style="width: 3rem;">{{ $n }}</a>
            @endforeach
        </div>
    </div>
</div>
@endsection