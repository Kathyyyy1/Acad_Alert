@extends('layouts.app')

@section('title', 'Block Dashboard - AcadAlert')

@section('page_title', 'Block Dashboard')
@section('page_actions')
    <div>
        <span class="badge bg-secondary text-white p-2 me-2">
            <i class="fas fa-users me-1"></i> {{ $blockName ?? 'Block' }}
        </span>
        
        <!-- Grading Period Dropdown -->
        <div class="d-inline-block me-2" style="min-width: 150px;">
            <select class="form-select form-select-sm d-inline-block" id="gradingPeriodSelect" 
                    style="width: auto; display: inline-block; padding: 0.25rem 2rem 0.25rem 0.75rem; border-radius: 0.375rem; background-color: #fff; border: 1px solid #ced4da;">
                <option value="Prelim" {{ $currentPeriod == 'Prelim' ? 'selected' : '' }}>Prelim</option>
                <option value="Midterm" {{ $currentPeriod == 'Midterm' ? 'selected' : '' }}>Midterm</option>
                <option value="Semifinal" {{ $currentPeriod == 'Semifinal' ? 'selected' : '' }}>Semifinal</option>
                <option value="Finals" {{ $currentPeriod == 'Finals' ? 'selected' : '' }}>Finals</option>
            </select>
        </div>
        
        <span class="badge bg-secondary text-white p-2 me-2">
            <i class="fas fa-arrow-left me-1"></i> Previous: {{ $previousPeriod ?? 'Prelim' }}
        </span>
        
        <!-- Run AI Risk Scoring Button -->
        <button class="btn btn-sm btn-success" id="runRiskScoringBtn">
            <i class="fas fa-robot me-1"></i> Run AI Risk Scoring
        </button>
        
        <!-- Refresh Scoring Button (Hidden by default, shown when scores exist) -->
        <button class="btn btn-sm btn-warning" id="refreshScoringBtn" style="display: none;">
            <i class="fas fa-sync me-1"></i> Refresh Scoring
        </button>
        
        <a href="{{ route('teacher.blocks', ['programId' => $block->program_id ?? 0]) }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
    </div>
@endsection

@section('content')
<meta name="block-id" content="{{ $blockId ?? 0 }}">

<!-- Risk Distribution Chart -->
<div class="row">
    <div class="col-xl-6 col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-chart-pie text-primary"></i>
                Risk Distribution - {{ $currentPeriod ?? 'Midterm' }}
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="blockRiskChart"></canvas>
                </div>
                <div class="text-center mt-2">
                    <span class="badge badge-risk-low me-2">Low: {{ $lowRiskCount ?? 0 }}</span>
                    <span class="badge badge-risk-moderate me-2">Moderate: {{ $moderateRiskCount ?? 0 }}</span>
                    <span class="badge badge-risk-high">High: {{ $highRiskCount ?? 0 }}</span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-6 col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-filter text-primary"></i>
                Filters
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Risk Level</label>
                        <select class="form-select form-select-sm" id="filterRisk">
                            <option value="all">All</option>
                            <option value="Low">Low</option>
                            <option value="Moderate">Moderate</option>
                            <option value="High">High</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Grade</label>
                        <select class="form-select form-select-sm" id="filterGrade">
                            <option value="all">All</option>
                            <option value="below75">Below 75</option>
                            <option value="below80">Below 80</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Attendance</label>
                        <select class="form-select form-select-sm" id="filterAttendance">
                            <option value="all">All</option>
                            <option value="below80">Below 80%</option>
                            <option value="below70">Below 70%</option>
                        </select>
                    </div>
                </div>
                <div class="mt-2">
                    <button class="btn btn-sm btn-primary" id="applyFiltersBtn">
                        <i class="fas fa-filter me-1"></i> Apply Filters
                    </button>
                    <button class="btn btn-sm btn-secondary" id="resetFiltersBtn">
                        <i class="fas fa-undo me-1"></i> Reset
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Student List Table -->
<div class="row">
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-users text-primary"></i>
                Students
                <span class="badge bg-secondary ms-2" id="studentCount">{{ $totalStudents ?? 0 }}</span>
                <span class="text-muted small ms-2">
                    Current: {{ $currentPeriod ?? 'Midterm' }} | Previous: {{ $previousPeriod ?? 'Prelim' }}
                </span>
                <div class="float-end">
                    <span class="badge bg-success me-1"><i class="fas fa-arrow-up me-1"></i> Improving</span>
                    <span class="badge bg-warning me-1"><i class="fas fa-minus me-1"></i> Stable</span>
                    <span class="badge bg-danger"><i class="fas fa-arrow-down me-1"></i> Worsening</span>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-mobile-cards" id="studentTable">
                        <thead>
                            <tr>
                                <th style="width: 40px;">
                                    <input type="checkbox" id="selectAll">
                                </th>
                                <th class="sortable" data-column="1">Student <i class="fas fa-sort text-muted"></i></th>
                                <th class="sortable" data-column="2">Grade <i class="fas fa-sort text-muted"></i></th>
                                <th class="sortable" data-column="3">Attendance <i class="fas fa-sort text-muted"></i></th>
                                <th class="sortable" data-column="4">Current Risk <i class="fas fa-sort text-muted"></i></th>
                                <th class="sortable" data-column="5">Previous Risk <i class="fas fa-sort text-muted"></i></th>
                                <th>Trend</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="studentTableBody">
                            @forelse($students as $student)
                            <tr data-risk="{{ $student->current_risk }}" 
                                data-grade="{{ $student->grade }}" 
                                data-attendance="{{ $student->attendance }}"
                                data-student-id="{{ $student->id }}"
                                class="{{ $student->is_critical ? 'table-danger' : '' }}">
                                <td data-label="">
                                    <input type="checkbox" class="student-checkbox" value="{{ $student->id }}">
                                </td>
                                <td data-label="Student">
                                    <strong>{{ $student->last_name }}, {{ $student->first_name }}</strong>
                                    <br>
                                    <small class="text-muted">{{ $student->student_number }}</small>
                                </td>
                                <td data-label="Grade">
                                    <span class="{{ $student->grade_color }}">
                                        {{ $student->grade_formatted ?? 'N/A' }}%
                                    </span>
                                </td>
                                <td data-label="Attendance">
                                    <span class="{{ $student->attendance_color }}">
                                        {{ $student->attendance_formatted ?? 'N/A' }}%
                                    </span>
                                </td>
                                <td data-label="Current Risk">
                                    <span class="badge {{ $student->current_risk_class }}">
                                        {{ $student->current_risk ?? 'Low' }}
                                    </span>
                                    @if($student->risk_score > 0)
                                        <br>
                                        <small class="text-muted">Score: {{ $student->risk_score }}</small>
                                    @endif
                                </td>
                                <td data-label="Previous Risk">
                                    <span class="badge {{ $student->previous_risk_class }}">
                                        {{ $student->previous_risk ?? 'N/A' }}
                                    </span>
                                </td>
                                <td data-label="Trend">
                                    <span class="{{ $student->trend_class }}">
                                        <i class="fas {{ $student->trend_icon }} me-1"></i> {{ $student->trend_display }}
                                    </span>
                                </td>
                                <td data-label="Status" class="status-cell">
                                    @if($student->has_open_case)
                                        <span class="badge bg-info">
                                            <i class="fas fa-folder-open me-1"></i> Open Case
                                        </span>
                                        <br>
                                        <small class="text-muted">Case #{{ $student->case_id ?? 'N/A' }}</small>
                                    @elseif($student->is_critical)
                                        <span class="badge bg-danger">
                                            <i class="fas fa-exclamation-triangle me-1"></i> Critical
                                        </span>
                                    @else
                                        <span class="badge bg-secondary">—</span>
                                    @endif
                                </td>
                                <td data-label="Action">
                                    @if($student->has_open_case)
                                        <button class="btn btn-sm btn-outline-danger escalate-btn" 
                                                data-student="{{ $student->id }}"
                                                data-name="{{ $student->last_name }}, {{ $student->first_name }}"
                                                disabled>
                                            <i class="fas fa-arrow-up me-1"></i> Escalate
                                        </button>
                                        <button class="btn btn-sm btn-outline-warning reset-escalate-btn" 
                                                data-student="{{ $student->id }}"
                                                data-name="{{ $student->last_name }}, {{ $student->first_name }}">
                                            <i class="fas fa-undo me-1"></i> Reset
                                        </button>
                                    @else
                                        <button class="btn btn-sm btn-outline-danger escalate-btn" 
                                                data-student="{{ $student->id }}"
                                                data-name="{{ $student->last_name }}, {{ $student->first_name }}">
                                            <i class="fas fa-arrow-up me-1"></i> Escalate
                                        </button>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    <i class="fas fa-users fa-2x d-block mb-2"></i>
                                    No students found in this block.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                
                <!-- Bulk Actions -->
                <div class="mt-3 d-flex flex-wrap gap-2" id="bulkActions">
                    <button class="btn btn-sm btn-danger" id="bulkEscalateBtn" disabled>
                        <i class="fas fa-arrow-up me-1"></i> Escalate Selected (<span id="selectedCount">0</span>)
                    </button>
                    <button class="btn btn-sm btn-warning" id="bulkEscalateAllBtn">
                        <i class="fas fa-flag me-1"></i> Escalate All Flagged
                    </button>
                    
                    <!-- Bulk Reset Escalation Buttons -->
                    <button class="btn btn-sm btn-warning" id="bulkResetBtn" disabled>
                        <i class="fas fa-undo me-1"></i> Reset Selected (<span id="resetSelectedCount">0</span>)
                    </button>
                    <button class="btn btn-sm btn-secondary" id="bulkResetAllBtn">
                        <i class="fas fa-undo-alt me-1"></i> Reset All Escalations
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Escalation Modal -->
<div class="modal fade" id="escalationModal" tabindex="-1" aria-labelledby="escalationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="escalationModalLabel">
                    <i class="fas fa-arrow-up me-2"></i> Escalate to Guidance Counselor
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-info-circle me-2"></i>
                    <span id="escalationCount">0</span> student(s) will be escalated.
                </div>
                
                <!-- Escalation Warning for already escalated students -->
                <div id="escalationWarning" class="alert alert-info" style="display: none;">
                    <i class="fas fa-info-circle me-2"></i>
                    Some students already have open cases and will be skipped.
                </div>
                
                <div id="escalationStudentList" class="mb-3"></div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Notes (optional)</label>
                    <textarea class="form-control" id="escalationNotes" rows="3" 
                              placeholder="Add any context for the counselor..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmEscalationBtn">
                    <i class="fas fa-check me-1"></i> Escalate to Counselor
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Reset Escalation Modal -->
<div class="modal fade" id="resetEscalationModal" tabindex="-1" aria-labelledby="resetEscalationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="resetEscalationModalLabel">
                    <i class="fas fa-undo me-2"></i> Reset Escalation
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    This will <strong>permanently delete</strong> the escalation record and case for <span id="resetCount">0</span> student(s).
                </div>
                <div id="resetStudentList" class="mb-3"></div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Reason for Reset</label>
                    <textarea class="form-control" id="resetReason" rows="2" 
                              placeholder="Optional: Why is this escalation being reset?"></textarea>
                </div>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Only cases with status <strong>New</strong> or <strong>In Progress</strong> can be reset.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="confirmResetBtn">
                    <i class="fas fa-check me-1"></i> Confirm Reset
                </button>
            </div>
        </div>
    </div>
</div>

<!-- AI Scoring Progress Modal -->
<div class="modal fade" id="scoringModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="scoringModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="scoringModalLabel">
                    <i class="fas fa-robot me-2"></i> AI Risk Scoring in Progress
                </h5>
            </div>
            <div class="modal-body">
                <div class="text-center py-3">
                    <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="fw-bold" id="scoringStatus">Processing student <span id="currentStudent">1</span> of <span id="totalStudents">{{ $totalStudents ?? 20 }}</span>...</p>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" 
                             id="scoringProgress" style="width: 5%;">5%</div>
                    </div>
                    <p class="text-muted small mt-2" id="currentStudentName">Loading...</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // ========================================
    // Block Risk Chart (Doughnut)
    // ========================================
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize chart if canvas exists
        const ctx = document.getElementById('blockRiskChart')?.getContext('2d');
        if (ctx) {
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Low Risk', 'Moderate Risk', 'High Risk'],
                    datasets: [{
                        data: [
                            {{ $lowRiskCount ?? 0 }},
                            {{ $moderateRiskCount ?? 0 }},
                            {{ $highRiskCount ?? 0 }}
                        ],
                        backgroundColor: ['#28a745', '#ffc107', '#dc3545'],
                        borderWidth: 2,
                        borderColor: '#fff',
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                padding: 15,
                            }
                        }
                    },
                    cutout: '65%',
                }
            });
        }

        // ========================================
        // Checkbox Handling
        // ========================================
        document.querySelectorAll('.student-checkbox').forEach(cb => {
            cb.addEventListener('change', updateSelectedCount);
        });

        document.getElementById('selectAll')?.addEventListener('change', function() {
            document.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = this.checked);
            updateSelectedCount();
        });

        // ========================================
        // Individual Escalate Buttons
        // ========================================
        document.querySelectorAll('.escalate-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const studentId = this.dataset.student;
                if (studentId) {
                    openEscalationModal([studentId]);
                }
            });
        });

        // ========================================
        // Individual Reset Escalate Buttons
        // ========================================
        document.querySelectorAll('.reset-escalate-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const studentId = this.dataset.student;
                const studentName = this.dataset.name;
                if (studentId && confirm(`Reset escalation for ${studentName}?`)) {
                    openResetModal([studentId]);
                }
            });
        });

        // ========================================
        // Run AI Risk Scoring Button
        // ========================================
        document.getElementById('runRiskScoringBtn')?.addEventListener('click', function() {
            runRiskScoring();
        });

        // ========================================
        // Refresh Button Event
        // ========================================
        document.getElementById('refreshScoringBtn')?.addEventListener('click', function() {
            refreshRiskScoring();
        });

        // ========================================
        // Check Scoring Status on Page Load
        // ========================================
        checkScoringStatus();

        // ========================================
        // Update Escalation Indicators after page load
        // ========================================
        setTimeout(updateEscalationIndicators, 1500);
        
        // Update indicators when period changes
        document.getElementById('gradingPeriodSelect')?.addEventListener('change', function() {
            setTimeout(updateEscalationIndicators, 500);
        });

        // ========================================
        // Filter Buttons Event Listeners
        // ========================================
        document.getElementById('applyFiltersBtn')?.addEventListener('click', applyFilters);
        document.getElementById('resetFiltersBtn')?.addEventListener('click', resetFilters);
        
        // Bulk Escalate Buttons
        document.getElementById('bulkEscalateBtn')?.addEventListener('click', bulkEscalate);
        document.getElementById('bulkEscalateAllBtn')?.addEventListener('click', bulkEscalateAll);
        
        // Bulk Reset Buttons
        document.getElementById('bulkResetBtn')?.addEventListener('click', bulkResetEscalation);
        document.getElementById('bulkResetAllBtn')?.addEventListener('click', bulkResetAllEscalations);

        // ========================================
        // Confirm Escalation Button
        // ========================================
        document.getElementById('confirmEscalationBtn')?.addEventListener('click', function() {
            confirmEscalation();
        });

        // ========================================
        // Confirm Reset Button
        // ========================================
        document.getElementById('confirmResetBtn')?.addEventListener('click', function() {
            confirmResetEscalation();
        });

        // ========================================
        // Period Dropdown Auto-Submit
        // ========================================
        document.getElementById('gradingPeriodSelect')?.addEventListener('change', function() {
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('period', this.value);
            window.location.href = currentUrl.toString();
        });
    });

    // ========================================
    // Checkbox Functions
    // ========================================
    function updateSelectedCount() {
        const checked = document.querySelectorAll('.student-checkbox:checked').length;
        document.getElementById('selectedCount').textContent = checked;
        document.getElementById('resetSelectedCount').textContent = checked;
        document.getElementById('bulkEscalateBtn').disabled = checked === 0;
        document.getElementById('bulkResetBtn').disabled = checked === 0;
    }

    function toggleAllCheckboxes() {
        const selectAll = document.getElementById('selectAll');
        document.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = selectAll.checked);
        updateSelectedCount();
    }

    // ========================================
    // Sort Table Function
    // ========================================
    let sortDirection = {};

    function sortTable(columnIndex) {
        const tbody = document.getElementById('studentTableBody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        
        if (!sortDirection[columnIndex]) {
            sortDirection[columnIndex] = 'asc';
        } else if (sortDirection[columnIndex] === 'asc') {
            sortDirection[columnIndex] = 'desc';
        } else {
            sortDirection[columnIndex] = 'asc';
        }
        
        const direction = sortDirection[columnIndex];
        
        rows.sort((a, b) => {
            let aVal = a.children[columnIndex]?.textContent?.trim() || '';
            let bVal = b.children[columnIndex]?.textContent?.trim() || '';
            
            const aNum = parseFloat(aVal.replace(/[^0-9.]/g, ''));
            const bNum = parseFloat(bVal.replace(/[^0-9.]/g, ''));
            
            if (!isNaN(aNum) && !isNaN(bNum)) {
                return direction === 'asc' ? aNum - bNum : bNum - aNum;
            }
            
            return direction === 'asc' ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
        });
        
        rows.forEach(row => tbody.appendChild(row));
    }

    // ========================================
    // Filter Functions
    // ========================================
    function applyFilters() {
        const riskFilter = document.getElementById('filterRisk').value;
        const gradeFilter = document.getElementById('filterGrade').value;
        const attendanceFilter = document.getElementById('filterAttendance').value;
        
        const rows = document.querySelectorAll('#studentTableBody tr');
        let visibleCount = 0;
        
        rows.forEach(row => {
            const risk = row.dataset.risk || 'Low';
            const grade = parseFloat(row.dataset.grade) || 0;
            const attendance = parseFloat(row.dataset.attendance) || 0;
            
            let show = true;
            
            if (riskFilter !== 'all' && risk !== riskFilter) show = false;
            if (gradeFilter === 'below75' && grade >= 75) show = false;
            if (gradeFilter === 'below80' && grade >= 80) show = false;
            if (attendanceFilter === 'below80' && attendance >= 80) show = false;
            if (attendanceFilter === 'below70' && attendance >= 70) show = false;
            
            row.style.display = show ? '' : 'none';
            if (show) visibleCount++;
        });
        
        document.getElementById('studentCount').textContent = visibleCount;
    }

    function resetFilters() {
        document.getElementById('filterRisk').value = 'all';
        document.getElementById('filterGrade').value = 'all';
        document.getElementById('filterAttendance').value = 'all';
        applyFilters();
    }

    // ========================================
    // Bulk Escalation Functions
    // ========================================
    
    let escalationStudents = [];

    function openEscalationModal(studentIds) {
        const modal = new bootstrap.Modal(document.getElementById('escalationModal'));
        document.getElementById('escalationCount').textContent = studentIds.length;
        
        const list = document.getElementById('escalationStudentList');
        list.innerHTML = '';
        
        // Show loading state
        list.innerHTML = '<div class="text-center py-2"><div class="spinner-border spinner-border-sm text-primary"></div> Loading student data...</div>';
        
        // Get student details with escalation status
        fetch('/teacher/check-escalation-status', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                student_ids: studentIds,
            }),
        })
        .then(response => response.json())
        .then(data => {
            list.innerHTML = '';
            
            if (data.success) {
                let escalatedCount = 0;
                
                studentIds.forEach(id => {
                    const status = data.statuses[id];
                    const row = document.querySelector(`.student-checkbox[value="${id}"]`);
                    if (row) {
                        const tr = row.closest('tr');
                        const name = tr?.querySelector('td[data-label="Student"] strong')?.textContent || 'Student';
                        const risk = tr?.querySelector('td[data-label="Current Risk"] .badge')?.textContent || 'Unknown';
                        
                        const isEscalated = status?.is_escalated || false;
                        if (isEscalated) escalatedCount++;
                        
                        const statusBadge = isEscalated 
                            ? '<span class="badge bg-info ms-2">Already Escalated</span>'
                            : '';
                        
                        list.innerHTML += `
                            <div class="alert alert-${isEscalated ? 'info' : (risk === 'High' ? 'danger' : 'warning')} py-1 mb-1">
                                <i class="fas fa-user me-2"></i> ${name} - ${risk} Risk
                                ${statusBadge}
                                ${isEscalated ? `<br><small class="text-muted">Case #${status.case_id}: ${status.status}</small>` : ''}
                            </div>
                        `;
                    }
                });
                
                // Count how many are already escalated
                if (escalatedCount > 0) {
                    document.getElementById('escalationWarning').style.display = 'block';
                    document.getElementById('escalationWarning').innerHTML = `
                        <i class="fas fa-info-circle me-2"></i>
                        ${escalatedCount} student(s) already have open cases and will be skipped.
                    `;
                } else {
                    document.getElementById('escalationWarning').style.display = 'none';
                }
            } else {
                // Fallback: show without status check
                studentIds.forEach(id => {
                    const row = document.querySelector(`.student-checkbox[value="${id}"]`);
                    if (row) {
                        const tr = row.closest('tr');
                        const name = tr?.querySelector('td[data-label="Student"] strong')?.textContent || 'Student';
                        const risk = tr?.querySelector('td[data-label="Current Risk"] .badge')?.textContent || 'Unknown';
                        list.innerHTML += `
                            <div class="alert alert-${risk === 'High' ? 'danger' : 'warning'} py-1 mb-1">
                                <i class="fas fa-user me-2"></i> ${name} - ${risk} Risk
                            </div>
                        `;
                    }
                });
                document.getElementById('escalationWarning').style.display = 'none';
            }
        })
        .catch(() => {
            // Fallback: show without status check
            list.innerHTML = '';
            studentIds.forEach(id => {
                const row = document.querySelector(`.student-checkbox[value="${id}"]`);
                if (row) {
                    const tr = row.closest('tr');
                    const name = tr?.querySelector('td[data-label="Student"] strong')?.textContent || 'Student';
                    const risk = tr?.querySelector('td[data-label="Current Risk"] .badge')?.textContent || 'Unknown';
                    list.innerHTML += `
                        <div class="alert alert-${risk === 'High' ? 'danger' : 'warning'} py-1 mb-1">
                            <i class="fas fa-user me-2"></i> ${name} - ${risk} Risk
                        </div>
                    `;
                }
            });
            document.getElementById('escalationWarning').style.display = 'none';
        });
        
        escalationStudents = studentIds;
        modal.show();
    }

    function bulkEscalate() {
        const selected = document.querySelectorAll('.student-checkbox:checked');
        const ids = Array.from(selected).map(cb => cb.value);
        if (ids.length > 0) {
            openEscalationModal(ids);
        }
    }

    function bulkEscalateAll() {
        const flagged = document.querySelectorAll('#studentTableBody tr:not([style*="display: none"]) .student-checkbox');
        const ids = Array.from(flagged).map(cb => cb.value);
        if (ids.length > 0) {
            openEscalationModal(ids);
        } else {
            alert('No students to escalate.');
        }
    }

    // ========================================
    // Confirm Escalation
    // ========================================
    
    function confirmEscalation() {
        const notes = document.getElementById('escalationNotes').value;
        const blockId = {{ $blockId ?? 0 }};
        const period = document.getElementById('gradingPeriodSelect')?.value || 'Midterm';
        
        if (escalationStudents.length === 0) {
            alert('No students selected for escalation.');
            return;
        }
        
        // Disable button during processing
        const confirmBtn = document.getElementById('confirmEscalationBtn');
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';
        
        fetch('/teacher/bulk-escalate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                student_ids: escalationStudents,
                notes: notes,
                block_id: blockId,
                period: period,
            }),
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let message = `${data.escalated} student(s) escalated to Guidance Counselor successfully!`;
                if (data.already_escalated > 0) {
                    message += `\n${data.already_escalated} student(s) already had open cases and were skipped.`;
                }
                alert(message);
            } else {
                alert('Failed to escalate: ' + (data.message || 'Unknown error'));
            }
            
            // Reset and close modal
            document.getElementById('escalationNotes').value = '';
            escalationStudents = [];
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = '<i class="fas fa-check me-1"></i> Escalate to Counselor';
            bootstrap.Modal.getInstance(document.getElementById('escalationModal')).hide();
            window.location.reload();
        })
        .catch(error => {
            alert('❌ Error: ' + error.message);
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = '<i class="fas fa-check me-1"></i> Escalate to Counselor';
        });
    }

    // ========================================
    // Reset Escalation Functions
    // ========================================

    let resetStudents = [];

    function openResetModal(studentIds) {
        resetStudents = studentIds;
        const modal = new bootstrap.Modal(document.getElementById('resetEscalationModal'));
        document.getElementById('resetCount').textContent = studentIds.length;
        
        // Show student list
        const list = document.getElementById('resetStudentList');
        list.innerHTML = '';
        
        studentIds.forEach(id => {
            const row = document.querySelector(`.student-checkbox[value="${id}"]`);
            if (row) {
                const tr = row.closest('tr');
                const name = tr?.querySelector('td[data-label="Student"] strong')?.textContent || 'Student';
                const risk = tr?.querySelector('td[data-label="Current Risk"] .badge')?.textContent || 'Unknown';
                list.innerHTML += `
                    <div class="alert alert-warning py-1 mb-1">
                        <i class="fas fa-user me-2"></i> ${name} - ${risk} Risk
                        <span class="badge bg-info ms-2">Will be reset</span>
                    </div>
                `;
            }
        });
        
        modal.show();
    }

    function confirmResetEscalation() {
        const reason = document.getElementById('resetReason').value || 'Reset due to accidental escalation';
        
        if (resetStudents.length === 0) {
            alert('No students selected for reset.');
            return;
        }
        
        // Disable button during processing
        const confirmBtn = document.getElementById('confirmResetBtn');
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';
        
        fetch('/teacher/bulk-reset-escalation', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                student_ids: resetStudents,
                reason: reason,
            }),
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(`${data.success_count} student(s) escalation reset successfully!\n\n${data.failed_count} failed to reset.`);
            } else {
                alert('Failed to reset: ' + (data.message || 'Unknown error'));
            }
            
            // Reset and close
            document.getElementById('resetReason').value = '';
            resetStudents = [];
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = '<i class="fas fa-check me-1"></i> Confirm Reset';
            bootstrap.Modal.getInstance(document.getElementById('resetEscalationModal')).hide();
            window.location.reload();
        })
        .catch(error => {
            alert('❌ Error: ' + error.message);
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = '<i class="fas fa-check me-1"></i> Confirm Reset';
        });
    }

    function bulkResetEscalation() {
        const checked = document.querySelectorAll('.student-checkbox:checked');
        const ids = Array.from(checked).map(cb => cb.value);
        if (ids.length > 0) {
            // Check which students have open cases
            fetch('/teacher/check-escalation-status', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    student_ids: ids,
                }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Filter only students with open cases
                    const resetableIds = ids.filter(id => data.statuses[id]?.is_escalated === true);
                    if (resetableIds.length === 0) {
                        alert('No selected students have open cases to reset.');
                        return;
                    }
                    openResetModal(resetableIds);
                }
            })
            .catch(() => {
                // Fallback: try resetting all selected
                openResetModal(ids);
            });
        }
    }

    function bulkResetAllEscalations() {
        if (!confirm('This will reset ALL escalations in this block. Are you sure?')) {
            return;
        }
        
        // Get all students with open cases in this block
        const blockId = {{ $blockId ?? 0 }};
        
        fetch('/teacher/block/' + blockId + '/escalated-students', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
            },
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.students && data.students.length > 0) {
                if (confirm(`This will reset ${data.students.length} escalation(s). Continue?`)) {
                    openResetModal(data.students);
                }
            } else {
                alert('No escalations found in this block.');
            }
        })
        .catch(() => {
            alert('Failed to fetch escalated students.');
        });
    }

    // ========================================
    // "Already Escalated" Indicator in Table
    // ========================================

    function updateEscalationIndicators() {
        // Check each student in the table
        const checkboxes = document.querySelectorAll('.student-checkbox');
        if (checkboxes.length === 0) return;
        
        const studentIds = Array.from(checkboxes).map(cb => cb.value);
        
        fetch('/teacher/check-escalation-status', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                student_ids: studentIds,
            }),
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update status cells with escalation info
                checkboxes.forEach(cb => {
                    const studentId = cb.value;
                    const status = data.statuses[studentId];
                    const tr = cb.closest('tr');
                    
                    if (!tr || !status) return;
                    
                    // Get the status cell (column index 7)
                    const statusCell = tr.querySelector('td[data-label="Status"]');
                    if (!statusCell) return;
                    
                    if (status.is_escalated) {
                        // Update with "Already Escalated" badge
                        if (!statusCell.querySelector('.already-escalated-badge')) {
                            // Only update if server-side didn't already show it
                            const currentBadge = statusCell.querySelector('.badge');
                            if (currentBadge && currentBadge.textContent.includes('Open Case')) {
                                return; // Already showing correctly
                            }
                            statusCell.innerHTML = `
                                <span class="badge bg-info already-escalated-badge">
                                    <i class="fas fa-folder-open me-1"></i> Open Case
                                </span>
                                <br>
                                <small class="text-muted">Case #${status.case_id}: ${status.status}</small>
                            `;
                        }
                    }
                });
            }
        })
        .catch(() => {
            // Silent fail - keep server-side rendering
        });
    }

    // ========================================
    // AI Risk Scoring
    // ========================================
    let scoringInterval = null;

    function runRiskScoring() {
        const blockId = {{ $blockId ?? 0 }};
        const period = document.getElementById('gradingPeriodSelect')?.value || 'Midterm';
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('scoringModal'));
        modal.show();
        
        // Reset progress
        let progress = 5;
        const progressBar = document.getElementById('scoringProgress');
        const progressText = document.getElementById('scoringStatus');
        const studentName = document.getElementById('currentStudentName');
        
        progressBar.style.width = '5%';
        progressBar.textContent = '5%';
        progressBar.className = 'progress-bar progress-bar-striped progress-bar-animated';
        progressText.textContent = 'Starting risk scoring...';
        studentName.textContent = 'Initializing connection...';
        
        // Start simulated progress (will be replaced by real progress)
        // This runs only until the AJAX response arrives
        let simProgress = 5;
        if (scoringInterval) clearInterval(scoringInterval);
        
        scoringInterval = setInterval(() => {
            simProgress += 2;
            if (simProgress > 85) {
                // Don't exceed 85% until real response
                return;
            }
            progressBar.style.width = simProgress + '%';
            progressBar.textContent = simProgress + '%';
            
            // Update status messages
            const messages = [
                'Analyzing student data...',
                'Connecting to AI service...',
                'Processing student records...',
                'Calculating risk factors...',
                'Generating risk scores...'
            ];
            const idx = Math.floor(simProgress / 20);
            studentName.textContent = messages[Math.min(idx, messages.length - 1)];
        }, 800);
        
        // Send AJAX request
        fetch(`/teacher/block/${blockId}/run-scoring`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                period: period,
                school_year: '2024-2025',
            }),
        })
        .then(response => {
            // Stop the simulated progress
            if (scoringInterval) {
                clearInterval(scoringInterval);
                scoringInterval = null;
            }
            
            if (!response.ok) {
                return response.json().then(data => {
                    throw new Error(data.message || `HTTP ${response.status}`);
                });
            }
            return response.json();
        })
        .then(data => {
            if (!data.success) {
                if (data.existing) {
                    progressText.textContent = '' + data.message;
                    progressBar.className = 'progress-bar bg-warning';
                    progressBar.style.width = '100%';
                    progressBar.textContent = 'Already Scored';
                    setTimeout(() => modal.hide(), 2000);
                    return;
                }
                throw new Error(data.message || 'Scoring failed');
            }
            
            // Success - update progress to 100%
            progressBar.style.width = '100%';
            progressBar.textContent = '100%';
            progressBar.className = 'progress-bar bg-success';
            progressText.textContent = 'Risk scoring complete!';
            studentName.textContent = `Processed ${data.processed} students (${data.fallback_count || 0} using fallback)`;
            
            setTimeout(() => {
                modal.hide();
                window.location.reload();
            }, 1500);
        })
        .catch(error => {
            // Stop the simulated progress
            if (scoringInterval) {
                clearInterval(scoringInterval);
                scoringInterval = null;
            }
            
            progressText.textContent = '❌ Error: ' + error.message;
            progressBar.className = 'progress-bar bg-danger';
            progressBar.style.width = '100%';
            progressBar.textContent = 'Failed';
            studentName.textContent = 'Please try again or contact support.';
            
            // Show retry button after 3 seconds
            setTimeout(() => {
                const retryBtn = document.createElement('button');
                retryBtn.className = 'btn btn-primary mt-3';
                retryBtn.innerHTML = '<i class="fas fa-redo me-1"></i> Retry';
                retryBtn.onclick = function() {
                    modal.hide();
                    setTimeout(() => runRiskScoring(), 500);
                };
                document.querySelector('#scoringModal .modal-body .text-center').appendChild(retryBtn);
            }, 3000);
        });
    }

    // ========================================
    // Refresh Scoring Function
    // ========================================
    function refreshRiskScoring() {
        if (!confirm('This will DELETE existing risk scores for this period and re-run the AI scoring. Continue?')) {
            return;
        }
        
        const blockId = {{ $blockId ?? 0 }};
        const period = document.getElementById('gradingPeriodSelect')?.value || 'Midterm';
        
        // Disable buttons during refresh
        document.getElementById('refreshScoringBtn').disabled = true;
        document.getElementById('runRiskScoringBtn').disabled = true;
        
        // Show loading state
        const refreshBtn = document.getElementById('refreshScoringBtn');
        const originalHtml = refreshBtn.innerHTML;
        refreshBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Deleting...';
        
        // Send refresh request
        fetch(`/teacher/block/${blockId}/refresh-scoring`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                period: period,
                school_year: '2024-2025',
            }),
        })
        .then(response => response.json())
        .then(data => {
            // Restore button
            refreshBtn.innerHTML = originalHtml;
            refreshBtn.disabled = false;
            document.getElementById('runRiskScoringBtn').disabled = false;
            
            if (data.success) {
                // Hide refresh button, show run button
                document.getElementById('refreshScoringBtn').style.display = 'none';
                document.getElementById('runRiskScoringBtn').style.display = 'inline-block';
                document.getElementById('runRiskScoringBtn').disabled = false;
                
                showToast('' + data.message, 'success');
                
                // Reload page after 2 seconds to show empty data
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                showToast('❌ Failed to refresh: ' + (data.message || 'Unknown error'), 'error');
            }
        })
        .catch(error => {
            refreshBtn.innerHTML = originalHtml;
            refreshBtn.disabled = false;
            document.getElementById('runRiskScoringBtn').disabled = false;
            showToast('❌ Error: ' + error.message, 'error');
        });
    }

    // ========================================
    // Check Scoring Status on Page Load
    // ========================================
    function checkScoringStatus() {
        const blockId = {{ $blockId ?? 0 }};
        const period = document.getElementById('gradingPeriodSelect')?.value || 'Midterm';
        
        fetch(`/teacher/block/${blockId}/scoring-status?period=${period}`)
            .then(response => response.json())
            .then(data => {
                const runBtn = document.getElementById('runRiskScoringBtn');
                const refreshBtn = document.getElementById('refreshScoringBtn');
                
                if (data.processed > 0 && data.total > 0) {
                    // Scores exist - show refresh button
                    refreshBtn.style.display = 'inline-block';
                    runBtn.style.display = 'none';
                    runBtn.disabled = true;
                    
                    // Update tooltip/info
                    const infoSpan = document.getElementById('scoringInfo');
                    if (!infoSpan) {
                        const newInfo = document.createElement('span');
                        newInfo.className = 'badge bg-info ms-2';
                        newInfo.id = 'scoringInfo';
                        newInfo.textContent = `${data.processed}/${data.total} scored`;
                        refreshBtn.parentNode.insertBefore(newInfo, refreshBtn.nextSibling);
                    } else {
                        infoSpan.textContent = `${data.processed}/${data.total} scored`;
                    }
                } else {
                    // No scores - show run button
                    refreshBtn.style.display = 'none';
                    runBtn.style.display = 'inline-block';
                    runBtn.disabled = false;
                    
                    // Remove info if exists
                    const oldInfo = document.getElementById('scoringInfo');
                    if (oldInfo) oldInfo.remove();
                }
            })
            .catch(() => {
                // On error, default to showing run button
                document.getElementById('runRiskScoringBtn').style.display = 'inline-block';
                document.getElementById('refreshScoringBtn').style.display = 'none';
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
        
        const toast = document.createElement('div');
        toast.className = `toast align-items-center ${colors[type] || colors.info} border-0 show`;
        toast.role = 'alert';
        toast.ariaLive = 'assertive';
        toast.ariaAtomic = 'true';
        toast.style.position = 'fixed';
        toast.style.top = '80px';
        toast.style.right = '20px';
        toast.style.zIndex = '9999';
        toast.style.minWidth = '300px';
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(function() {
            toast.classList.remove('show');
            setTimeout(function() {
                toast.remove();
            }, 300);
        }, 5000);
    }
</script>
@endpush