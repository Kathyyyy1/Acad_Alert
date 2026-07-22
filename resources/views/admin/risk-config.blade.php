@extends('layouts.app')

@section('title', 'Risk Configuration - AcadAlert')

@section('page_title', 'Risk Configuration')
@section('page_actions')
    <div>
        <button class="btn btn-sm btn-info" onclick="testAIConnection()">
            <i class="fas fa-robot me-1"></i> Test AI Connection
        </button>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
    </div>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-sliders-h me-2"></i> Risk Thresholds
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.risk.save') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-bold">Low Risk (0 to <span id="lowVal">{{ $thresholds->low_threshold ?? 40 }}</span>)</label>
                        <input type="range" class="form-range" name="low_threshold" 
                               id="lowThreshold" min="0" max="100" 
                               value="{{ $thresholds->low_threshold ?? 40 }}"
                               oninput="document.getElementById('lowVal').textContent = this.value">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Moderate Risk (<span id="modLowVal">{{ $thresholds->low_threshold ?? 40 }}</span> to <span id="modHighVal">{{ $thresholds->moderate_threshold ?? 70 }}</span>)</label>
                        <div class="row">
                            <div class="col-6">
                                <input type="number" class="form-control" name="moderate_low" 
                                       value="{{ $thresholds->low_threshold ?? 40 }}" readonly>
                            </div>
                            <div class="col-6">
                                <input type="range" class="form-range" name="moderate_threshold" 
                                       id="moderateThreshold" min="0" max="100" 
                                       value="{{ $thresholds->moderate_threshold ?? 70 }}"
                                       oninput="document.getElementById('modHighVal').textContent = this.value">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">High Risk (<span id="highVal">{{ $thresholds->moderate_threshold ?? 70 }}</span> to 100)</label>
                        <input type="range" class="form-range" name="high_threshold" 
                               id="highThreshold" min="0" max="100" 
                               value="{{ $thresholds->high_threshold ?? 71 }}"
                               oninput="document.getElementById('highVal').textContent = this.value">
                    </div>
                    <hr>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Risk Score Weights</label>
                        <div class="row">
                            <div class="col-6">
                                <label class="form-label small">Grade Weight</label>
                                <input type="number" class="form-control" name="grade_weight" 
                                       value="{{ $thresholds->grade_weight ?? 0.60 }}" step="0.01" min="0" max="1">
                            </div>
                            <div class="col-6">
                                <label class="form-label small">Attendance Weight</label>
                                <input type="number" class="form-control" name="attendance_weight" 
                                       value="{{ $thresholds->attendance_weight ?? 0.40 }}" step="0.01" min="0" max="1">
                            </div>
                        </div>
                        <small class="text-muted">Grade and attendance weights must sum to 1.00 (100%)</small>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-save me-1"></i> Save Configuration
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header bg-info text-white">
                <i class="fas fa-info-circle me-2"></i> Current Settings
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6">
                        <div class="bg-light p-2 rounded mb-2">
                            <div class="text-muted small">Low Risk Range</div>
                            <div class="fw-bold text-success">0 - {{ $thresholds->low_threshold ?? 40 }}</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="bg-light p-2 rounded mb-2">
                            <div class="text-muted small">Moderate Risk Range</div>
                            <div class="fw-bold text-warning">{{ $thresholds->low_threshold ?? 40 }} - {{ $thresholds->moderate_threshold ?? 70 }}</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="bg-light p-2 rounded mb-2">
                            <div class="text-muted small">High Risk Range</div>
                            <div class="fw-bold text-danger">{{ $thresholds->moderate_threshold ?? 70 }} - 100</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="bg-light p-2 rounded mb-2">
                            <div class="text-muted small">Formula Weights</div>
                            <div class="fw-bold">Grade: {{ ($thresholds->grade_weight ?? 0.60) * 100 }}% | Attendance: {{ ($thresholds->attendance_weight ?? 0.40) * 100 }}%</div>
                        </div>
                    </div>
                </div>
                <div class="alert alert-info mt-3">
                    <i class="fas fa-robot me-2"></i>
                    <strong>AI Connection Status:</strong>
                    <span id="aiStatus">Checking...</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        testAIConnection();
    });
    
    function testAIConnection() {
        const statusEl = document.getElementById('aiStatus');
        statusEl.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Checking...';
        
        fetch('{{ route('admin.risk.test-ai') }}')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    statusEl.innerHTML = '<span class="badge bg-success">Connected</span>';
                } else {
                    statusEl.innerHTML = '<span class="badge bg-danger">' + data.message + '</span>';
                }
            })
            .catch(() => {
                statusEl.innerHTML = '<span class="badge bg-danger">Connection failed</span>';
            });
    }
</script>
@endpush