@extends('layouts.app')

@section('title', 'Counselor Dashboard - AcadAlert')

@section('page_title', 'Caseload Management')
@section('page_actions')
    <div>
        <span class="badge bg-secondary text-white p-2 me-2">
            <i class="fas fa-user me-1"></i> {{ auth()->user()->name }}
        </span>
        <button class="btn btn-sm btn-outline-primary" onclick="window.location.reload()">
            <i class="fas fa-sync me-1"></i> Refresh
        </button>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#filterModal">
            <i class="fas fa-filter me-1"></i> Filters
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
                    <div class="stat-label">Open Cases</div>
                    <div class="stat-number">{{ $openCases ?? 0 }}</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-briefcase"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card danger">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Critical Priority</div>
                    <div class="stat-number">{{ $criticalCases ?? 0 }}</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card warning">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Awaiting Parent</div>
                    <div class="stat-number">{{ $awaitingParent ?? 0 }}</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-phone"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="stat-card success">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Avg Response Time</div>
                    <div class="stat-number">{{ $avgResponseTime ?? '2.3' }} days</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row">
    <div class="col-xl-6 col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-chart-pie text-primary"></i>
                Case Priority Distribution
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="priorityChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-6 col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-chart-bar text-primary"></i>
                Case Status Distribution
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Caseload Trend -->
<div class="row">
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-chart-line text-primary"></i>
                Caseload Trend
            </div>
            <div class="card-body">
                <div class="chart-container" style="height: 200px;">
                    <canvas id="caseloadTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cases List -->
<div class="row">
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list text-primary"></i>
                My Cases
                <span class="badge bg-secondary ms-2">{{ count($cases ?? []) }}</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Priority</th>
                                <th>Student</th>
                                <th>Program</th>
                                <th>Risk Level</th>
                                <th>Status</th>
                                <th>Last Session</th>
                                <th>Due</th>
                                <th>Effectiveness</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($cases as $case)
                            <tr class="{{ $case->priority === 'Critical' ? 'table-danger' : '' }}">
                                <td>
                                    @php
                                        $priorityColors = [
                                            'Critical' => 'danger',
                                            'High' => 'warning',
                                            'Medium' => 'info',
                                            'Low' => 'secondary'
                                        ];
                                    @endphp
                                    <span class="badge bg-{{ $priorityColors[$case->priority] ?? 'secondary' }}">
                                        {{ $case->priority }}
                                    </span>
                                </td>
                                <td>
                                    <strong>{{ $case->student_name ?? 'N/A' }}</strong>
                                </td>
                                <td>{{ $case->program ?? 'N/A' }}</td>
                                <td>
                                    <span class="badge {{ $case->risk_level === 'High' ? 'badge-risk-high' : ($case->risk_level === 'Moderate' ? 'badge-risk-moderate' : 'badge-risk-low') }}">
                                        {{ $case->risk_level ?? 'N/A' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary">
                                        {{ str_replace('_', ' ', $case->status) }}
                                    </span>
                                </td>
                                <td>{{ $case->last_session_date ?? 'No sessions' }}</td>
                                <td class="{{ $case->follow_up_date && \Carbon\Carbon::parse($case->follow_up_date)->isPast() ? 'text-danger fw-bold' : '' }}">
                                    {{ $case->follow_up_date ?? 'N/A' }}
                                </td>
                                <td>
                                    @if($case->improvement !== null && $case->improvement > 0)
                                        <span class="text-success">
                                            <i class="fas fa-arrow-up me-1"></i> {{ $case->improvement }} pts
                                        </span>
                                    @elseif($case->improvement !== null && $case->improvement < 0)
                                        <span class="text-danger">
                                            <i class="fas fa-arrow-down me-1"></i> {{ abs($case->improvement) }} pts
                                        </span>
                                    @else
                                        <span class="text-muted">Pending</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('counselor.case', $case->id) }}" class="btn btn-sm btn-outline-primary">
                                        <i class=""></i> Open
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-2x d-block mb-2"></i>
                                    No cases assigned to you.
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

<!-- Filter Modal -->
<div class="modal fade" id="filterModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-filter me-2"></i> Filter Cases
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="GET" action="{{ route('counselor.dashboard') }}">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Priority</label>
                        <select class="form-select" name="priority">
                            <option value="all">All Priorities</option>
                            <option value="Critical">Critical</option>
                            <option value="High">High</option>
                            <option value="Medium">Medium</option>
                            <option value="Low">Low</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Status</label>
                        <select class="form-select" name="status">
                            <option value="all">All Statuses</option>
                            <option value="new">New</option>
                            <option value="in_progress">In Progress</option>
                            <option value="awaiting_parent">Awaiting Parent</option>
                            <option value="awaiting_student">Awaiting Student</option>
                            <option value="referred">Referred</option>
                            <option value="resolved">Resolved</option>
                            <option value="closed">Closed</option>
                            <option value="reopened">Reopened</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Search</label>
                        <input type="text" class="form-control" name="search" placeholder="Student name or number...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-1"></i> Apply Filters
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<!-- ======================================== -->
<!-- CHART.JS SCRIPTS - Step 17 & 18           -->
<!-- ======================================== -->
<!-- 
    NOTE: All chart initialization is now handled by counselor-charts.js
    The inline chart code has been removed to prevent duplicate initialization.
-->
<script src="{{ asset('js/charts/chart-config.js') }}"></script>
<script src="{{ asset('js/charts/counselor-charts.js') }}"></script>

<!-- 
    Debugging: Check if charts are loading correctly
    Open browser console to see logs from chart-config.js and counselor-charts.js
-->
<script>
    console.log('[Counselor Dashboard] Chart scripts loaded.');
    console.log('[Counselor Dashboard] Charts will be initialized by counselor-charts.js');
    
</script>
@endpush