@extends('layouts.app')

@section('title', 'Student Dashboard - AcadAlert')

@section('page_title', 'My Academic Standing')
@section('page_actions')
    <div>
        <span class="badge bg-secondary text-white p-2 me-2">
            <i class="fas fa-user me-1"></i> {{ auth()->user()->name }}
        </span>
        <div class="d-inline-block">
            <select class="form-select form-select-sm d-inline-block" id="periodSelect" style="width: auto; display: inline-block;">
                <option value="Prelim" {{ $currentPeriod == 'Prelim' ? 'selected' : '' }}>Prelim</option>
                <option value="Midterm" {{ $currentPeriod == 'Midterm' ? 'selected' : '' }}>Midterm</option>
                <option value="Semifinal" {{ $currentPeriod == 'Semifinal' ? 'selected' : '' }}>Semifinal</option>
                <option value="Finals" {{ $currentPeriod == 'Finals' ? 'selected' : '' }}>Finals</option>
            </select>
        </div>
        <button class="btn btn-sm btn-outline-primary" onclick="window.location.reload()">
            <i class="fas fa-sync me-1"></i> Refresh
        </button>
    </div>
@endsection

@section('content')
<!-- Risk Score Card & Risk Trend -->
<div class="row">
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white text-center">
                <i class="fas fa-shield-alt me-2"></i> Your Risk Score
            </div>
            <div class="card-body text-center py-4">
                @if($currentRisk && $currentRisk->risk_score > 0)
                    @php
                        $score = $currentRisk->risk_score ?? 0;
                        $level = $currentRisk->risk_level ?? 'No Data';
                        $levelClass = match($level) {
                            'High' => 'badge-risk-high',
                            'Moderate' => 'badge-risk-moderate',
                            default => 'badge-risk-low'
                        };
                        $icon = match($level) {
                            'High' => 'fa-exclamation-triangle text-danger',
                            'Moderate' => 'fa-clock text-warning',
                            default => 'fa-check-circle text-success'
                        };
                    @endphp
                    <div class="display-1 mb-2">
                        <i class="fas {{ $icon }}"></i>
                    </div>
                    <div class="display-4 fw-bold">{{ $score }}</div>
                    <div class="mt-2">
                        <span class="badge {{ $levelClass }} p-2 fs-6">
                            {{ $level }} RISK
                        </span>
                    </div>
                @else
                    <div class="display-1 mb-2">
                        <i class="fas fa-info-circle text-muted"></i>
                    </div>
                    <div class="display-6 fw-bold text-muted">No Data</div>
                    <div class="mt-2">
                        <span class="badge bg-secondary p-2 fs-6">
                            Pending Assessment
                        </span>
                    </div>
                    <p class="text-muted small mt-3">
                        <i class="fas fa-info-circle me-1"></i>
                        Your risk score will be calculated after grades and attendance are recorded.
                    </p>
                @endif
                <div class="mt-3 text-muted small">
                    <i class="fas fa-info-circle me-1"></i>
                    Score is calculated from grades (60%) and attendance (40%)
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header bg-success text-white">
                <i class="fas fa-chart-line me-2"></i> Your Risk Trend & Progress
            </div>
            <div class="card-body">
                <div class="chart-container" style="height: 200px;">
                    <canvas id="studentRiskTrendChart"></canvas>
                </div>
                <div class="mt-3">
                    @if($improvement !== null && $improvement > 0)
                        <div class="alert alert-success mb-0">
                            <i class="fas fa-arrow-up me-2"></i>
                            You are improving! Your risk dropped from 
                            <strong>{{ $previousRisk->risk_score ?? 'N/A' }}</strong> to 
                            <strong>{{ $currentRisk->risk_score ?? 'N/A' }}</strong>
                            <span class="badge bg-success ms-2">-{{ $improvement }} points</span>
                        </div>
                    @elseif($improvement !== null && $improvement < 0)
                        <div class="alert alert-danger mb-0">
                            <i class="fas fa-arrow-down me-2"></i>
                            Your risk increased from 
                            <strong>{{ $previousRisk->risk_score ?? 'N/A' }}</strong> to 
                            <strong>{{ $currentRisk->risk_score ?? 'N/A' }}</strong>
                            <span class="badge bg-danger ms-2">+{{ abs($improvement) }} points</span>
                        </div>
                    @elseif($improvement === null)
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            Not enough data to show progress trend.
                            <small class="d-block text-muted">Complete at least two grading periods to see improvement.</small>
                        </div>
                    @else
                        <div class="alert alert-secondary mb-0">
                            <i class="fas fa-minus me-2"></i>
                            Your risk score is stable at <strong>{{ $currentRisk->risk_score ?? 'N/A' }}</strong>
                            <span class="badge bg-secondary ms-2">Stable</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Subject Performance -->
<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-book text-primary me-2"></i> Subject Performance
            </div>
            <div class="card-body">
                <div class="chart-container" style="height: 200px;">
                    <canvas id="subjectGradesChart"></canvas>
                </div>
                <div class="mt-3">
                    @php
                        $lowestGrade = $subjectGrades->sortBy('numerical_grade')->first();
                    @endphp
                    @if($lowestGrade && $lowestGrade->numerical_grade < 75)
                        <div class="alert alert-warning mb-0">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Focus on: <strong>{{ $lowestGrade->subject_name }}</strong> ({{ $lowestGrade->numerical_grade }}% - Failing)
                        </div>
                    @else
                        <div class="alert alert-success mb-0">
                            <i class="fas fa-check-circle me-2"></i>
                            You are passing all subjects. Keep up the good work!
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    
    <!-- Attendance Breakdown -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-clipboard-check text-primary me-2"></i> Attendance Breakdown
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Attendance</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($attendanceBreakdown as $att)
                            <tr>
                                <td>{{ $att->subject_name }}</td>
                                <td>
                                    <span class="{{ $att->attendance_rate < 70 ? 'text-danger fw-bold' : ($att->attendance_rate < 80 ? 'text-warning' : '') }}">
                                        {{ round($att->attendance_rate) }}%
                                    </span>
                                </td>
                                <td>
                                    @if($att->attendance_rate < 70)
                                        <span class="badge bg-danger">At risk</span>
                                    @elseif($att->attendance_rate < 80)
                                        <span class="badge bg-warning">Below 80%</span>
                                    @elseif($att->attendance_rate < 90)
                                        <span class="badge bg-info">Good</span>
                                    @else
                                        <span class="badge bg-success">Excellent</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted">No attendance data available.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @php
                    $lowestAttendance = $attendanceBreakdown->sortBy('attendance_rate')->first();
                @endphp
                @if($lowestAttendance && $lowestAttendance->attendance_rate < 75)
                    <div class="alert alert-danger mb-0 mt-2">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        You have <strong>{{ round(100 - $lowestAttendance->attendance_rate) }}% absences</strong> in <strong>{{ $lowestAttendance->subject_name }}</strong>.
                        <br>
                        <small>Please see your instructor to discuss your attendance.</small>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Alerts -->
<div class="row">
    <div class="col-12 mb-4">
        <div class="card border-{{ $unreadAlerts > 0 ? 'danger' : 'success' }}">
            <div class="card-header bg-{{ $unreadAlerts > 0 ? 'danger' : 'success' }} text-white">
                <i class="fas fa-bell me-2"></i> Your Alerts
                @if($unreadAlerts > 0)
                    <span class="badge bg-light text-danger ms-2">{{ $unreadAlerts }} unread</span>
                @endif
                <!-- Debug: Show count -->
                <span class="badge bg-light text-secondary ms-2" style="font-size: 0.6rem;">
                    Total: {{ $alerts->count() }}
                </span>
            </div>
            <div class="card-body">
                @if($alerts->count() > 0)
                    @foreach($alerts as $alert)
                        @php
                            // Determine alert type
                            $alertType = match($alert->severity) {
                                'critical' => 'danger',
                                'high' => 'warning',
                                'medium' => 'info',
                                'low' => 'secondary',
                                'success' => 'success',
                                default => 'info'
                            };
                            // Check if this is a counselor alert
                            $isCounselorAlert = in_array($alert->flag_type, [
                                'counselor_update', 'counselor_action', 'status_update', 
                                'priority_update', 'case_resolved', 'case_reopened'
                            ]);
                        @endphp
                        <div class="alert alert-{{ $alertType }} d-flex justify-content-between align-items-center mb-2" id="alert-{{ $alert->id }}">
                            <div>
                                @if(!$alert->is_acknowledged)
                                    <span class="badge bg-danger me-2">NEW</span>
                                @endif
                                @if($isCounselorAlert)
                                    <i class="fas fa-headset me-2 text-primary"></i>
                                    <span class="fw-bold">[Counselor]</span>
                                @endif
                                {{ $alert->message ?? $alert->flag_type }}
                                @if($alert->consecutive_periods_count > 1)
                                    <span class="badge bg-danger ms-2">x{{ $alert->consecutive_periods_count }} consecutive</span>
                                @endif
                                <br>
                                <small class="text-muted">{{ $alert->grading_period }} {{ $alert->school_year }}</small>
                                @if($alert->created_at)
                                    <small class="text-muted ms-2">| {{ \Carbon\Carbon::parse($alert->created_at)->diffForHumans() }}</small>
                                @endif
                            </div>
                            <div class="alert-actions">
                                @if(!$alert->is_acknowledged)
                                    <button class="btn btn-sm btn-outline-primary acknowledge-btn" 
                                            data-alert-id="{{ $alert->id }}"
                                            onclick="acknowledgeAlert({{ $alert->id }}, this)">
                                        <i class="fas fa-check me-1"></i> Mark as Read
                                    </button>
                                @else
                                    <span class="badge bg-success"><i class="fas fa-check me-1"></i> Read</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                @else
                    <p class="text-muted mb-0">
                        <i class="fas fa-check-circle text-success me-2"></i> 
                        No new alerts at this time.
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Recommendations & Resources -->
<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <i class=""></i> Recommended Resources
            </div>
            <div class="card-body">
                <div class="list-group">
                    @forelse($resources as $resource)
                    <div class="list-group-item">
                        <i class="{{ $resource['icon'] }} me-2 text-primary"></i>
                        <strong>{{ $resource['title'] }}</strong>
                        <br>
                        <small class="text-muted">{{ $resource['description'] }}</small>
                    </div>
                    @empty
                    <div class="list-group-item text-muted">
                        No resources available at this time.
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-headset text-primary me-2"></i> Contact Your Counselor
            </div>
            <div class="card-body">
                @if($counselorInfo)
                <div class="text-center">
                    <div class="display-6 mb-2">
                        <i class="fas fa-user-circle text-primary"></i>
                    </div>
                    <h5>{{ $counselorInfo->name }}</h5>
                    <p class="text-muted small">
                        <i class="fas fa-map-pin me-1"></i> 
                        {{ $counselorInfo->office_location ?? 'Guidance Office, 2nd Floor, Main Building' }}
                    </p>
                    <p class="text-muted small">
                        <i class="fas fa-phone me-1"></i> 
                        {{ $counselorInfo->phone_number ?? '(075) 123-4567 loc. 123' }}
                    </p>
                    <p class="text-muted small">
                        <i class="fas fa-envelope me-1"></i> 
                        {{ $counselorInfo->email }}
                    </p>
                    <p class="text-muted small">
                        <i class="fas fa-clock me-1"></i> 
                        {{ $counselorInfo->office_hours ?? 'Mon-Fri, 9:00 AM - 4:00 PM' }}
                    </p>
                    <button class="btn btn-primary mt-2" onclick="scheduleAppointment()">
                        <i class="fas fa-calendar-plus me-1"></i> Schedule an Appointment
                    </button>
                </div>
                @else
                <div class="text-center text-muted py-3">
                    <i class="fas fa-info-circle fa-2x d-block mb-2"></i>
                    <p>No counselor assigned to your department yet.</p>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Progress Comparison -->
@if($progressComparison)
<div class="row">
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header bg-info text-white">
                <i class="fas fa-chart-simple me-2"></i> Progress Comparison
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="bg-light p-3 rounded mb-2">
                            <div class="text-muted small">Before Intervention</div>
                            <div class="fw-bold text-danger">
                                <i class="fas fa-exclamation-triangle me-1"></i> 
                                {{ $progressComparison->first_score }} - {{ $progressComparison->first_level }}
                                <br>
                                <small class="text-muted">{{ $progressComparison->first_period }}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="bg-light p-3 rounded mb-2">
                            <div class="text-muted small">After Intervention</div>
                            <div class="fw-bold {{ $progressComparison->improvement > 0 ? 'text-success' : 'text-warning' }}">
                                @if($progressComparison->improvement > 0)
                                    <i class="fas fa-arrow-up me-1"></i> 
                                @elseif($progressComparison->improvement < 0)
                                    <i class="fas fa-arrow-down me-1"></i> 
                                @else
                                    <i class="fas fa-minus me-1"></i>
                                @endif
                                {{ $progressComparison->last_score }} - {{ $progressComparison->last_level }}
                                <br>
                                <small class="text-muted">{{ $progressComparison->last_period }}</small>
                            </div>
                        </div>
                    </div>
                </div>
                @if($progressComparison->improvement > 0)
                <div class="alert alert-success mb-0">
                    <i class="fas fa-check-circle me-2"></i> 
                    Improvement: -{{ $progressComparison->improvement }} points Intervention Working!
                </div>
                @elseif($progressComparison->improvement < 0)
                <div class="alert alert-danger mb-0">
                    <i class="fas fa-exclamation-circle me-2"></i> 
                    Worsening: +{{ abs($progressComparison->improvement) }} points Intervention needs review!
                </div>
                @else
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle me-2"></i> 
                    No significant change detected. Continue monitoring.
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<!-- ======================================== -->
<!-- CHART.JS SCRIPTS - Step 17 & 18           -->
<!-- ======================================== -->
<!-- 
    NOTE: All chart initialization is now handled by student-charts.js
    The inline chart code has been removed to prevent duplicate initialization.
-->

<!-- Period Selector Auto-Submit -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Period Selector Auto-Submit
        document.getElementById('periodSelect')?.addEventListener('change', function() {
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('period', this.value);
            window.location.href = currentUrl.toString();
        });
    });
    
    // ========================================
    // Alert Acknowledgment - Fixed
    // ========================================
    function acknowledgeAlert(flagId, button) {
        // Store reference to the button and its parent elements
        const btn = button;
        const alertDiv = btn.closest('.alert');
        const actionsDiv = btn.closest('.alert-actions');
        
        // Disable the button to prevent double-clicks
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';
        
        fetch('/student/acknowledge-alert', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                flag_id: flagId,
            }),
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Replace the button with "Read" badge
                if (actionsDiv) {
                    actionsDiv.innerHTML = '<span class="badge bg-success"><i class="fas fa-check me-1"></i> Read</span>';
                }
                
                // Update the alert style (optional)
                if (alertDiv) {
                    alertDiv.classList.remove('alert-warning', 'alert-info', 'alert-danger');
                    alertDiv.classList.add('alert-success');
                }
                
                // Update unread count
                const countBadge = document.querySelector('.card-header .badge');
                if (countBadge) {
                    let count = parseInt(countBadge.textContent);
                    if (count > 0) {
                        count--;
                        if (count > 0) {
                            countBadge.textContent = count;
                        } else {
                            countBadge.remove();
                        }
                    }
                }
                
                // Show a small toast or notification
                showToast('Alert marked as read!', 'success');
            } else {
                // Show error message
                showToast('❌ ' + data.message, 'error');
                // Re-enable the button
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check me-1"></i> Mark as Read';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('❌ Error: ' + error.message, 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check me-1"></i> Mark as Read';
        });
    }

    // ========================================
    // Toast Notification Helper
    // ========================================
    function showToast(message, type = 'success') {
        const colors = {
            success: 'bg-success text-white',
            error: 'bg-danger text-white',
            warning: 'bg-warning text-dark',
            info: 'bg-info text-white'
        };
        
        // Remove existing toasts
        const existingToasts = document.querySelectorAll('.custom-toast');
        existingToasts.forEach(toast => toast.remove());
        
        const toast = document.createElement('div');
        toast.className = `custom-toast toast align-items-center ${colors[type] || colors.info} border-0 show`;
        toast.role = 'alert';
        toast.ariaLive = 'assertive';
        toast.ariaAtomic = 'true';
        toast.style.position = 'fixed';
        toast.style.top = '80px';
        toast.style.right = '20px';
        toast.style.zIndex = '9999';
        toast.style.minWidth = '300px';
        toast.style.maxWidth = '450px';
        toast.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
        toast.style.borderRadius = '8px';
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        // Auto-dismiss after 4 seconds
        setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 300);
        }, 4000);
    }
    
    function scheduleAppointment() {
        alert('Appointment scheduling will be available soon! (Placeholder)');
    }
</script>

<!-- Chart.js Scripts -->
<script src="{{ asset('js/charts/chart-config.js') }}"></script>
<script src="{{ asset('js/charts/student-charts.js') }}"></script>

<!-- Debugging -->
<script>
    console.log('[Student Dashboard] Chart scripts loaded.');
    console.log('[Student Dashboard] Charts will be initialized by student-charts.js');
</script>
@endpush