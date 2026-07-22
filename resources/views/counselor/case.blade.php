@extends('layouts.app')

@section('title', 'Case View - AcadAlert')

@section('page_title', 'Case Details')
@section('page_actions')
    <div>
        <span class="badge {{ $case->priority === 'Critical' ? 'bg-danger' : ($case->priority === 'High' ? 'bg-warning' : ($case->priority === 'Medium' ? 'bg-info' : 'bg-secondary')) }} p-2 me-2">
            <i class="fas fa-flag me-1"></i> {{ $case->priority }}
        </span>
        <span class="badge bg-secondary p-2 me-2">
            <i class="fas fa-folder-open me-1"></i> {{ str_replace('_', ' ', $case->status) }}
        </span>
        <a href="{{ route('counselor.dashboard') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
    </div>
@endsection

@section('content')
<!-- Student ID Meta Tag for Chart.js -->
<meta name="student-id" content="{{ $case->student_id ?? 0 }}">

<div class="row">
    <!-- Student Information -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-user me-2"></i> Student Information
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <label class="text-muted small fw-bold d-block">Student Name</label>
                    <span class="fw-bold">{{ $student->first_name ?? '' }} {{ $student->last_name ?? '' }}</span>
                </div>
                <div class="mb-2">
                    <label class="text-muted small fw-bold d-block">Student Number</label>
                    <span>{{ $student->student_number ?? 'N/A' }}</span>
                </div>
                <div class="mb-2">
                    <label class="text-muted small fw-bold d-block">Program</label>
                    <span>{{ $student->program_code ?? 'N/A' }}</span>
                </div>
                <div class="mb-2">
                    <label class="text-muted small fw-bold d-block">Year Level</label>
                    <span>{{ $student->year_level ?? 'N/A' }}</span>
                </div>
                <div class="mb-2">
                    <label class="text-muted small fw-bold d-block">Block</label>
                    <span>{{ $student->block_name ?? 'N/A' }}</span>
                </div>
                <hr>
                <div class="mb-2">
                    <label class="text-muted small fw-bold d-block">Primary Parent</label>
                    <span>{{ $student->parent_name ?? 'N/A' }}</span>
                    <br>
                    <small class="text-muted">{{ $student->parent_contact ?? '' }}</small>
                    <br>
                    <small class="text-muted">{{ $student->parent_email ?? '' }}</small>
                </div>
                <hr>
                <div class="mb-2">
                    <label class="text-muted small fw-bold d-block">Escalated By</label>
                    <span>{{ $case->escalated_by_name ?? 'N/A' }}</span>
                </div>
                <div class="mb-2">
                    <label class="text-muted small fw-bold d-block">Escalated On</label>
                    <span>{{ $case->escalated_at ? \Carbon\Carbon::parse($case->escalated_at)->format('M d, Y') : 'N/A' }}</span>
                </div>
                @if($escalationNotes ?? false)
                <hr>
                <div class="mb-2">
                    <label class="text-muted small fw-bold d-block">Escalation Notes</label>
                    <div class="bg-light p-2 rounded small">{{ $escalationNotes }}</div>
                </div>
                @endif
            </div>
        </div>
    </div>
    
    <!-- Intervention Effectiveness -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header bg-success text-white">
                <i class="fas fa-chart-line me-2"></i> Intervention Effectiveness
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="bg-light p-3 rounded mb-2">
                            <div class="text-muted small">Before Intervention</div>
                            <div class="fw-bold text-danger">
                                <i class="fas fa-exclamation-triangle me-1"></i> 
                                {{ $case->risk_score_at_escalation ?? 'N/A' }} - 
                                {{ $case->risk_level_at_escalation ?? 'N/A' }}
                            </div>
                            <small class="text-muted">{{ $case->escalated_at ? \Carbon\Carbon::parse($case->escalated_at)->format('M d, Y') : 'N/A' }}</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="bg-light p-3 rounded mb-2">
                            <div class="text-muted small">After Intervention</div>
                            <div class="fw-bold {{ ($improvement ?? 0) > 0 ? 'text-success' : 'text-warning' }}">
                                @if(isset($improvement) && $improvement > 0)
                                    <i class="fas fa-arrow-up me-1"></i> {{ $currentRisk ?? 'N/A' }}
                                @elseif(isset($improvement) && $improvement < 0)
                                    <i class="fas fa-arrow-down me-1"></i> {{ $currentRisk ?? 'N/A' }}
                                @else
                                    <i class="fas fa-clock me-1"></i> Pending
                                @endif
                            </div>
                            <small class="text-muted">Current Period: Midterm</small>
                        </div>
                    </div>
                </div>
                @if(isset($improvement) && $improvement > 0)
                <div class="alert alert-success mb-0">
                    <i class="fas fa-check-circle me-2"></i> 
                    Improvement: -{{ $improvement }} points Intervention Working!
                </div>
                @elseif(isset($improvement) && $improvement < 0)
                <div class="alert alert-danger mb-0">
                    <i class="fas fa-exclamation-circle me-2"></i> 
                    Worsening: +{{ abs($improvement) }} points Intervention needs review!
                </div>
                @else
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle me-2"></i> 
                    Next grading period: {{ $nextPeriod ?? 'Semifinal' }} - 
                    <button class="btn btn-sm btn-outline-primary" onclick="checkProgress()">
                        Check Progress after {{ $nextPeriod ?? 'Semifinal' }}
                    </button>
                </div>
                @endif
                
                <!-- Risk Trend Chart -->
                <div class="mt-3">
                    <div class="chart-container" style="height: 150px;">
                        <canvas id="studentRiskTrendChart" data-student-id="{{ $case->student_id ?? 0 }}"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Session History -->
<div class="row">
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-history text-primary me-2"></i>
                Session History
                <button class="btn btn-sm btn-primary float-end" data-bs-toggle="modal" data-bs-target="#sessionModal">
                    <i class="fas fa-plus me-1"></i> Log Session
                </button>
            </div>
            <div class="card-body">
                @if($sessions->count() > 0)
                    <div class="list-group">
                        @foreach($sessions as $session)
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-bold">
                                        <span class="badge bg-secondary me-2">{{ $session->session_type }}</span>
                                        {{ \Carbon\Carbon::parse($session->session_date)->format('M d, Y') }}
                                    </div>
                                    <p class="mb-1 mt-1">{{ $session->notes }}</p>
                                    <small class="text-muted">
                                        <i class="fas fa-check-circle me-1 text-success"></i>
                                        Action: {{ $session->action_taken ?? 'None recorded' }}
                                    </small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-secondary">
                                        {{ str_replace('_', ' ', $session->status_after_session ?? 'In Progress') }}
                                    </span>
                                    @if($session->follow_up_date)
                                    <br>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar-alt me-1"></i>
                                        Follow-up: {{ \Carbon\Carbon::parse($session->follow_up_date)->format('M d, Y') }}
                                    </small>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted text-center py-3">
                        <i class="fas fa-comment fa-2x d-block mb-2"></i>
                        No sessions logged yet.
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Actions -->
<div class="row">
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <i class="fas fa-tasks me-2"></i> Actions
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-3">
                        <button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#sessionModal">
                            <i class="fas fa-plus me-1"></i> Log New Session
                        </button>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-info w-100" data-bs-toggle="modal" data-bs-target="#updateStatusModal">
                            <i class="fas fa-exchange-alt me-1"></i> Update Status
                        </button>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-warning w-100" data-bs-toggle="modal" data-bs-target="#updatePriorityModal">
                            <i class="fas fa-flag me-1"></i> Update Priority
                        </button>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#resolveModal">
                            <i class="fas fa-check me-1"></i> Mark Resolved
                        </button>
                    </div>
                </div>
                <div class="row g-2 mt-2">
                    <div class="col-md-12">
                        <button class="btn btn-danger w-100" data-bs-toggle="modal" data-bs-target="#reopenModal" 
                                {{ $case->status !== 'Resolved' && $case->status !== 'Closed' ? 'disabled' : '' }}>
                            <i class="fas fa-redo me-1"></i> Reopen Case
                            @if($case->status !== 'Resolved' && $case->status !== 'Closed')
                                <small class="text-muted">(Case must be resolved first)</small>
                            @endif
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Session Modal -->
<div class="modal fade" id="sessionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-plus me-2"></i> Log New Session
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('counselor.session.store', $case->id) }}">
                @csrf
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Session Date</label>
                            <input type="date" class="form-control" name="session_date" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Session Type</label>
                            <select class="form-select" name="session_type" required>
                                <option value="In-person">In-person</option>
                                <option value="Phone">Phone</option>
                                <option value="Virtual">Virtual</option>
                                <option value="Parent Meeting">Parent Meeting</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Notes</label>
                        <textarea class="form-control" name="notes" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Action Taken</label>
                        <input type="text" class="form-control" name="action_taken" placeholder="e.g., Referred to tutoring">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Follow-up Date</label>
                            <input type="date" class="form-control" name="follow_up_date">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Update Case Status</label>
                            <select class="form-select" name="status_after_session" required>
                                <option value="In Progress">In Progress</option>
                                <option value="Awaiting Parent">Awaiting Parent</option>
                                <option value="Awaiting Student">Awaiting Student</option>
                                <option value="Referred">Referred</option>
                                <option value="Resolved">Resolved</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Save Session
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="fas fa-exchange-alt me-2"></i> Update Case Status
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('counselor.status.update', $case->id) }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Current Status</label>
                        <p class="fw-bold">{{ str_replace('_', ' ', $case->status) }}</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">New Status</label>
                        <select class="form-select" name="status" required>
                            <option value="New">New</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Awaiting Parent">Awaiting Parent</option>
                            <option value="Awaiting Student">Awaiting Student</option>
                            <option value="Referred">Referred</option>
                            <option value="Resolved">Resolved</option>
                            <option value="Closed">Closed</option>
                            <option value="Reopened">Reopened</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Update Priority Modal -->
<div class="modal fade" id="updatePriorityModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">
                    <i class="fas fa-flag me-2"></i> Update Priority
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('counselor.priority.update', $case->id) }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Current Priority</label>
                        <p class="fw-bold">{{ $case->priority }}</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">New Priority</label>
                        <select class="form-select" name="priority" required>
                            <option value="Critical">Critical</option>
                            <option value="High">High</option>
                            <option value="Medium">Medium</option>
                            <option value="Low">Low</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Update Priority
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Resolve Modal -->
<div class="modal fade" id="resolveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="fas fa-check me-2"></i> Mark Case as Resolved
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('counselor.resolve', $case->id) }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Resolution Notes</label>
                        <textarea class="form-control" name="resolved_reason" rows="3" required 
                                  placeholder="Describe how the case was resolved..."></textarea>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        This action will mark the case as <strong>Resolved</strong> and close it.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check me-1"></i> Mark Resolved
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reopen Modal -->
<div class="modal fade" id="reopenModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-redo me-2"></i> Reopen Case
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('counselor.reopen', $case->id) }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Reason for Reopening</label>
                        <textarea class="form-control" name="reopen_reason" rows="3" required 
                                  placeholder="Explain why this case needs to be reopened..."></textarea>
                    </div>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        This will create a new case with status <strong>Reopened</strong> linked to this one.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-redo me-1"></i> Reopen Case
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Student Risk Trend Chart
        // Try to get student ID from meta tag or data attribute
        const metaStudentId = document.querySelector('meta[name="student-id"]')?.content;
        const canvasStudentId = document.getElementById('studentRiskTrendChart')?.dataset?.studentId;
        const studentId = metaStudentId || canvasStudentId || 0;
        
        console.log('[Case View] Student ID for chart:', studentId);
        
        // If student ID is available, use the API endpoint
        if (studentId && studentId !== '0') {
            const url = `/charts/counselor/student-risk/${studentId}`;
            
            fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    const ctx = document.getElementById('studentRiskTrendChart')?.getContext('2d');
                    if (ctx) {
                        new Chart(ctx, {
                            type: 'line',
                            data: data.data,
                            options: {
                                responsive: true,
                                maintainAspectRatio: true,
                                plugins: {
                                    legend: {
                                        display: false
                                    },
                                    tooltip: {
                                        callbacks: {
                                            label: function(context) {
                                                return 'Risk Score: ' + context.parsed.y;
                                            }
                                        }
                                    }
                                },
                                scales: {
                                    y: {
                                        min: 0,
                                        max: 100,
                                        grid: { color: 'rgba(0,0,0,0.05)' },
                                        ticks: {
                                            stepSize: 20
                                        }
                                    },
                                    x: {
                                        grid: { display: false }
                                    }
                                }
                            }
                        });
                        console.log('[Case View] Student Risk Trend chart created successfully via API');
                    }
                } else {
                    // Fallback to inline data if API fails
                    createInlineChart();
                }
            })
            .catch(error => {
                console.error('[Case View] API error, using fallback:', error);
                createInlineChart();
            });
        } else {
            // Use inline data if no student ID
            createInlineChart();
        }
        
        function createInlineChart() {
            const riskHistory = @json($riskHistory);
            const periods = riskHistory.map(r => r.grading_period);
            const scores = riskHistory.map(r => r.risk_score);
            
            const ctx = document.getElementById('studentRiskTrendChart')?.getContext('2d');
            if (ctx) {
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: periods,
                        datasets: [{
                            label: 'Risk Score',
                            data: scores,
                            borderColor: '#4e73df',
                            backgroundColor: 'rgba(78, 115, 223, 0.1)',
                            fill: true,
                            tension: 0.3,
                            pointBackgroundColor: scores.map(s => s >= 71 ? '#dc3545' : (s >= 41 ? '#ffc107' : '#28a745')),
                            pointRadius: 6,
                            pointHoverRadius: 8,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return 'Risk Score: ' + context.parsed.y;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                min: 0,
                                max: 100,
                                grid: { color: 'rgba(0,0,0,0.05)' },
                                ticks: {
                                    stepSize: 20
                                }
                            },
                            x: {
                                grid: { display: false }
                            }
                        }
                    }
                });
                console.log('[Case View] Student Risk Trend chart created successfully (inline)');
            }
        }
    });
    
    function checkProgress() {
        alert('Checking progress after the next grading period. (Placeholder)');
    }
</script>
@endpush