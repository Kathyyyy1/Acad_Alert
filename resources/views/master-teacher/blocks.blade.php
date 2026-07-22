@extends('layouts.app')

@section('title', 'Blocks - AcadAlert')

@section('page_title', 'Blocks')
@section('page_actions')
    <div>
        <span class="badge bg-secondary text-white p-2 me-2">
            <i class="fas fa-book me-1"></i> {{ $program->code ?? 'Program' }}
        </span>
        <a href="{{ route('teacher.department') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Department
        </a>
    </div>
@endsection

@section('content')
<div class="row">
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-layer-group text-primary me-2"></i>
                Blocks - {{ $program->name ?? 'Program' }}
            </div>
            <div class="card-body">
                <div class="row">
                    @forelse($blocks as $block)
                    <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h5 class="card-title mb-1">{{ $block->name }}</h5>
                                        <small class="text-muted">{{ $block->year_level_name }}</small>
                                    </div>
                                    <span class="badge bg-primary">{{ $block->total_students ?? 0 }} students</span>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <span class="badge badge-risk-low">{{ $block->low_risk ?? 0 }}</span>
                                        <small class="text-muted d-block">Low</small>
                                    </div>
                                    <div>
                                        <span class="badge badge-risk-moderate">{{ $block->moderate_risk ?? 0 }}</span>
                                        <small class="text-muted d-block">Moderate</small>
                                    </div>
                                    <div>
                                        <span class="badge badge-risk-high">{{ $block->high_risk ?? 0 }}</span>
                                        <small class="text-muted d-block">High</small>
                                    </div>
                                </div>
                                <a href="{{ route('teacher.block', ['blockId' => $block->id]) }}" 
                                   class="btn btn-sm btn-outline-primary w-100 mt-3">
                                    <i class="fas fa-eye me-1"></i> View Block
                                </a>
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="col-12">
                        <p class="text-muted text-center py-4">
                            <i class="fas fa-info-circle me-2"></i> No blocks found for this program.
                        </p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection