@extends('layouts.app')

@section('title', 'Department Dashboard - AcadAlert')

@section('page_title', 'Department Dashboard')
@section('page_actions')
    <div>
        <span class="badge bg-primary text-white p-2 me-2">
            <i class="fas fa-building me-1"></i> {{ $departmentName ?? 'Department' }}
        </span>
        <button class="btn btn-sm btn-outline-primary" onclick="window.location.reload()">
            <i class="fas fa-sync me-1"></i> Refresh
        </button>
    </div>
@endsection

@section('content')
<!-- Quick Stats Cards -->
<div class="row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card primary">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Total Students</div>
                    <div class="stat-number">{{ $totalStudents ?? 0 }}</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card danger">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">High Risk Students</div>
                    <div class="stat-number">{{ $highRiskCount ?? 0 }}</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card warning">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Moderate Risk</div>
                    <div class="stat-number">{{ $moderateRiskCount ?? 0 }}</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card purple">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Need Escalation</div>
                    <div class="stat-number">{{ $needEscalation ?? 0 }}</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-arrow-up"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row">
    <div class="col-xl-8 col-lg-7 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-chart-bar text-primary"></i>
                Risk by Program
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="riskByProgramChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-4 col-lg-5 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-chart-line text-primary"></i>
                Department Risk Trend
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="departmentTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Program Summary -->
<div class="row">
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list text-primary"></i>
                Program Summary
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Program</th>
                                <th>Students</th>
                                <th>Low Risk</th>
                                <th>Moderate Risk</th>
                                <th>High Risk</th>
                                <th>High Risk %</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($programs as $program)
                            <tr>
                                <td><strong>{{ $program->code }}</strong></td>
                                <td>{{ $program->student_count ?? 0 }}</td>
                                <td><span class="badge badge-risk-low">{{ $program->low_risk ?? 0 }}</span></td>
                                <td><span class="badge badge-risk-moderate">{{ $program->moderate_risk ?? 0 }}</span></td>
                                <td><span class="badge badge-risk-high">{{ $program->high_risk ?? 0 }}</span></td>
                                <td>
                                    @if($program->high_risk_percentage > 15)
                                        <span class="text-danger fw-bold">{{ $program->high_risk_percentage }}%</span>
                                    @elseif($program->high_risk_percentage > 8)
                                        <span class="text-warning">{{ $program->high_risk_percentage }}%</span>
                                    @else
                                        <span class="text-success">{{ $program->high_risk_percentage }}%</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('teacher.blocks', ['programId' => $program->id]) }}" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye me-1"></i> View Blocks
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-3">
                                    <i class="fas fa-info-circle me-2"></i> No programs found in this department.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Critical Alerts -->
<div class="row">
    <div class="col-12 mb-4">
        <div class="card border-{{ count($alerts) > 0 ? 'danger' : 'success' }}">
            <div class="card-header bg-{{ count($alerts) > 0 ? 'danger' : 'success' }} text-white">
                <i class="fas fa-bell me-2"></i> 
                {{ count($alerts) > 0 ? 'Critical Alerts' : 'All Clear' }}
            </div>
            <div class="card-body">
                @if(count($alerts) > 0)
                    <ul class="list-unstyled mb-0">
                        @foreach($alerts as $alert)
                        <li class="py-2 border-bottom {{ $loop->last ? 'border-0' : '' }}">
                            <span class="badge {{ $alert['severity'] === 'critical' ? 'bg-danger' : 'bg-warning' }} me-2">
                                {{ $alert['severity'] === 'critical' ? 'CRITICAL' : 'WARNING' }}
                            </span>
                            {{ $alert['message'] }}
                        </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-muted mb-0">
                        <i class="fas fa-check-circle text-success me-2"></i> 
                        No critical alerts at this time. All programs are performing well.
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<!-- ======================================== -->
<!-- CHART.JS SCRIPTS - Step 17 & 18           -->
<!-- ======================================== -->
<!-- 
    NOTE: All chart initialization is now handled by teacher-charts.js
    The inline chart code has been removed to prevent duplicate initialization.
-->
<script src="{{ asset('js/charts/chart-config.js') }}"></script>
<script src="{{ asset('js/charts/teacher-charts.js') }}"></script>

<!-- 
    Debugging: Check if charts are loading correctly
    Open browser console to see logs from chart-config.js and teacher-charts.js
-->
<script>
    console.log('[Master Teacher Dashboard] Chart scripts loaded.');
    console.log('[Master Teacher Dashboard] Charts will be initialized by teacher-charts.js');
    
    // If charts don't load, you can manually trigger loading with:
    // window.loadTeacherCharts();
</script>
@endpush