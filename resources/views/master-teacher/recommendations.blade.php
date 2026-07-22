@extends('layouts.app')

@section('title', 'Recommendation Management - AcadAlert')

@section('page_title', 'Intervention Recommendation Management')
@section('page_actions')
    <div>
        <a href="{{ route('teacher.department') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Department
        </a>
        <button class="btn btn-sm btn-outline-primary" onclick="window.location.reload()">
            <i class="fas fa-sync me-1"></i> Refresh
        </button>
    </div>
@endsection

@section('content')
<!-- ============================================ -->
<!-- STEP 3: PROGRESS BAR COMPONENT -->
<!-- ============================================ -->
<div id="progressContainer" style="display: none;" class="mb-4">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <i class="fas fa-spinner fa-spin me-2"></i>
            Generating Recommendations
            <span id="progressStatus" class="badge bg-light text-primary ms-2">Processing...</span>
        </div>
        <div class="card-body">
            <div class="progress" style="height: 30px;">
                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                     id="mainProgressBar" style="width: 0%;">
                    0%
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-md-4">
                    <div class="bg-light p-2 rounded text-center">
                        <div class="text-muted small">Processed</div>
                        <div class="fw-bold" id="processedCount">0 / <span id="totalCount">0</span></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="bg-light p-2 rounded text-center">
                        <div class="text-muted small">Successful</div>
                        <div class="fw-bold text-success" id="successCount">0</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="bg-light p-2 rounded text-center">
                        <div class="text-muted small">Failed</div>
                        <div class="fw-bold text-danger" id="failedCount">0</div>
                    </div>
                </div>
            </div>
            <div id="progressDetails" class="mt-2" style="max-height: 200px; overflow-y: auto;">
                <!-- Progress details will be populated here -->
            </div>
        </div>
    </div>
</div>

<!-- Block Selection -->
<div class="row">
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <i class=""></i> Select Block to Manage Recommendations
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('teacher.recommendations') }}" class="row g-3">
                    <div class="col-md-6">
                        <select class="form-select" name="block_id" required>
                            <option value="">-- Select a Block --</option>
                            @foreach($blocks as $block)
                                <option value="{{ $block->id }}" {{ $blockId == $block->id ? 'selected' : '' }}>
                                    {{ $block->program_code }} - Year {{ $block->year_number }} - {{ $block->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-eye me-1"></i> View Block
                        </button>
                    </div>
                    @if($selectedBlock)
                        <div class="col-md-3">
                            <button type="button" class="btn btn-success w-100" onclick="generateAll()">
                                <i class=""></i> Generate AI Recommendations
                            </button>
                        </div>
                    @endif
                </form>
            </div>
        </div>
    </div>
</div>

@if($selectedBlock)
<!-- Block Information -->
<div class="row">
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header bg-success text-white">
                <i class="fas fa-info-circle me-2"></i> Block: {{ $selectedBlock->program_code }} - Year {{ $selectedBlock->year_number }} - {{ $selectedBlock->name }}
                <span class="badge bg-light text-success ms-2">{{ count($students) }} students</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="bg-light p-2 rounded">
                            <div class="text-muted small">Program</div>
                            <div class="fw-bold">{{ $selectedBlock->program_code }} - {{ $selectedBlock->program_name }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="bg-light p-2 rounded">
                            <div class="text-muted small">Year Level</div>
                            <div class="fw-bold">Year {{ $selectedBlock->year_number }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="bg-light p-2 rounded">
                            <div class="text-muted small">Total Students</div>
                            <div class="fw-bold">{{ count($students) }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="bg-light p-2 rounded">
                            <div class="text-muted small">Recommendations</div>
                            <div class="fw-bold">
                                @php
                                    $recCount = 0;
                                    foreach ($students as $student) {
                                        if (isset($recommendations[$student->id]) && $recommendations[$student->id]->count() > 0) {
                                            $recCount++;
                                        }
                                    }
                                @endphp
                                {{ $recCount }} / {{ count($students) }} students have recommendations
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Students & Recommendations Table -->
<div class="row">
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list text-primary me-2"></i> Students & Recommendations
                <span class="badge bg-secondary ms-2">{{ count($students) }}</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student</th>
                                <th>Student Number</th>
                                <th>Risk Level</th>
                                <th>Recommendation</th>
                                <th>Generated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($students as $index => $student)
                            @php
                                $studentRecs = $recommendations[$student->id] ?? collect();
                                $hasRec = $studentRecs->count() > 0;
                                $latestRec = $hasRec ? $studentRecs->first() : null;
                                $riskScore = DB::table('risk_scores')
                                    ->where('student_id', $student->id)
                                    ->where('grading_period', 'Midterm')
                                    ->where('school_year', '2024-2025')
                                    ->first();
                                $riskLevel = $riskScore->risk_level ?? 'N/A';
                                $riskClass = match($riskLevel) {
                                    'High' => 'badge-risk-high',
                                    'Moderate' => 'badge-risk-moderate',
                                    'Low' => 'badge-risk-low',
                                    default => 'bg-secondary'
                                };
                            @endphp
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>
                                    <strong>{{ $student->first_name }} {{ $student->last_name }}</strong>
                                </td>
                                <td>{{ $student->student_number }}</td>
                                <td>
                                    <span class="badge {{ $riskClass }}">
                                        {{ $riskLevel }}
                                    </span>
                                </td>
                                <td>
                                    @if($hasRec)
                                        @php
                                            $actions = json_decode($latestRec->suggested_actions, true);
                                            
                                            // Safety check: ensure $actions is an array
                                            if (!is_array($actions)) {
                                                $actions = is_string($latestRec->suggested_actions) 
                                                    ? json_decode($latestRec->suggested_actions, true) 
                                                    : [];
                                                
                                                if (!is_array($actions) || empty($actions)) {
                                                    $actions = [
                                                        [
                                                            'action' => 'custom',
                                                            'details' => is_string($latestRec->suggested_actions) 
                                                                ? $latestRec->suggested_actions 
                                                                : 'No details available',
                                                            'priority' => 'medium'
                                                        ]
                                                    ];
                                                }
                                            }
                                            
                                            $actions = array_map(function($action) {
                                                return [
                                                    'action' => $action['action'] ?? 'custom',
                                                    'details' => $action['details'] ?? 'No details provided',
                                                    'priority' => $action['priority'] ?? 'medium',
                                                ];
                                            }, $actions);
                                        @endphp
                                        <div class="recommendation-preview">
                                            @if(count($actions) > 0)
                                                <div class="small">
                                                    @foreach($actions as $action)
                                                        <div class="mb-1">
                                                            @php
                                                                $priorityClass = match($action['priority'] ?? 'medium') {
                                                                    'high' => 'danger',
                                                                    'medium' => 'warning',
                                                                    'low' => 'info',
                                                                    default => 'secondary'
                                                                };
                                                            @endphp
                                                            <span class="badge bg-{{ $priorityClass }} me-1">
                                                                {{ ucfirst(str_replace('_', ' ', $action['action'] ?? 'custom')) }}
                                                            </span>
                                                            <span class="text-muted">{{ $action['details'] ?? 'No details' }}</span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @else
                                                <span class="text-muted">No actions defined</span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="badge bg-secondary">Pending</span>
                                    @endif
                                </td>
                                <td>
                                    @if($hasRec)
                                        <small class="text-muted">{{ \Carbon\Carbon::parse($latestRec->generated_at)->format('M d, Y') }}</small>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        @if($hasRec)
                                            <button class="btn btn-sm btn-outline-info" onclick="viewRecommendation({{ $latestRec->id }})" title="View Full AI Content">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <a href="{{ route('teacher.recommendation.edit', $latestRec->id) }}?block_id={{ $blockId }}" 
                                               class="btn btn-sm btn-outline-primary" title="Edit Recommendation">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteRec({{ $latestRec->id }})" title="Delete Recommendation">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        @else
                                            <button class="btn btn-sm btn-outline-success" onclick="generateStudent({{ $student->id }})">
                                                <i class=""></i> Generate
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted">No students found in this block.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

<!-- Generate All Modal -->
<div class="modal fade" id="generateAllModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class=""></i> Generate AI Recommendations
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('teacher.recommendations.generate.block') }}">
                @csrf
                <input type="hidden" name="block_id" value="{{ $blockId }}">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        This will generate AI-powered intervention recommendations for <strong>ALL students</strong> in this block.
                        <br><br>
                        Existing recommendations will be <strong>overwritten</strong> with new content.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Grading Period</label>
                        <select class="form-select" name="period">
                            <option value="Prelim">Prelim</option>
                            <option value="Midterm" selected>Midterm</option>
                            <option value="Semifinal">Semifinal</option>
                            <option value="Finals">Finals</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class=""></i> Generate All
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Recommendation Modal -->
<div class="modal fade" id="viewRecommendationModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="fas fa-eye me-2"></i> AI-Generated Recommendation
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="viewRecommendationContent">
                    <div class="text-center py-3">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="#" id="editRecommendationBtn" class="btn btn-primary">
                    <i class="fas fa-edit me-1"></i> Edit Recommendation
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i> Confirm Delete
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this recommendation?</p>
                <p class="text-muted small">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                    <i class="fas fa-trash me-1"></i> Delete
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let deleteRecId = null;

    // ============================================
    // SAFE DOM ELEMENT GETTER
    // ============================================
    function safeGetElement(id) {
        const el = document.getElementById(id);
        if (!el) {
            console.warn(`Element with ID "${id}" not found.`);
        }
        return el;
    }

    function safeSetText(id, text) {
        const el = safeGetElement(id);
        if (el) {
            el.textContent = text;
            return true;
        }
        return false;
    }

    function safeSetStyle(id, property, value) {
        const el = safeGetElement(id);
        if (el) {
            el.style[property] = value;
            return true;
        }
        return false;
    }

    // ============================================
    // PROGRESS BAR FUNCTIONS
    // ============================================
    function showProgressContainer() {
        const container = safeGetElement('progressContainer');
        if (container) {
            container.style.display = 'block';
        } else {
            // Fallback: create a simple progress indicator
            console.warn('Progress container not found, creating fallback...');
            createFallbackProgress();
        }
    }

    function createFallbackProgress() {
        // Create a simple progress indicator if container doesn't exist
        const container = document.createElement('div');
        container.id = 'progressContainer';
        container.className = 'mb-4';
        container.innerHTML = `
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-spinner fa-spin me-2"></i>
                    Generating Recommendations
                    <span id="progressStatus" class="badge bg-light text-primary ms-2">Processing...</span>
                </div>
                <div class="card-body">
                    <div class="progress" style="height: 30px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" 
                             id="mainProgressBar" style="width: 0%;">0%</div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-4">
                            <div class="bg-light p-2 rounded text-center">
                                <div class="text-muted small">Processed</div>
                                <div class="fw-bold" id="processedCount">0 / <span id="totalCount">0</span></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="bg-light p-2 rounded text-center">
                                <div class="text-muted small">Successful</div>
                                <div class="fw-bold text-success" id="successCount">0</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="bg-light p-2 rounded text-center">
                                <div class="text-muted small">Failed</div>
                                <div class="fw-bold text-danger" id="failedCount">0</div>
                            </div>
                        </div>
                    </div>
                    <div id="progressDetails" class="mt-2" style="max-height: 200px; overflow-y: auto;"></div>
                </div>
            </div>
        `;
        
        // Insert after the block selection card
        const blockSelection = document.querySelector('.row:first-child');
        if (blockSelection && blockSelection.parentNode) {
            blockSelection.parentNode.insertBefore(container, blockSelection.nextSibling);
        } else {
            // Fallback: insert at the beginning of the content area
            const content = document.querySelector('.container-fluid');
            if (content) {
                content.insertBefore(container, content.firstChild);
            }
        }
        console.log('Fallback progress container created.');
    }

    function updateProgress(percentage, total, success, failed) {
        const progressBar = safeGetElement('mainProgressBar');
        if (progressBar) {
            progressBar.style.width = percentage + '%';
            progressBar.textContent = percentage + '%';
            
            if (percentage >= 100) {
                progressBar.className = 'progress-bar bg-success';
            } else if (percentage > 0) {
                progressBar.className = 'progress-bar progress-bar-striped progress-bar-animated bg-primary';
            }
        }
        
        safeSetText('totalCount', total);
        safeSetText('processedCount', (success + failed) + ' / ' + total);
        safeSetText('successCount', success);
        safeSetText('failedCount', failed);
    }

    function showProgressDetails(progressData) {
        const container = safeGetElement('progressDetails');
        if (!container) return;
        
        let html = '<div class="list-group list-group-flush">';
        
        if (progressData && progressData.length > 0) {
            progressData.forEach(item => {
                const statusIcon = item.status === 'success' ? '' : (item.status === 'failed' ? '' : '');
                const statusClass = item.status === 'success' ? 'text-success' : (item.status === 'failed' ? 'text-danger' : 'text-muted');
                const studentName = item.student_name || 'Student ' + item.student_id;
                
                html += `
                    <div class="list-group-item d-flex justify-content-between align-items-center py-1">
                        <span class="${statusClass}">
                            ${statusIcon} ${studentName}
                        </span>
                        <span class="small">
                            ${item.status === 'success' ? 'Done' : (item.status === 'failed' ? 'Failed: ' + (item.error || 'Unknown') : 'Processing...')}
                        </span>
                    </div>
                `;
            });
        } else {
            html += `
                <div class="list-group-item text-center text-muted py-3">
                    <i class="fas fa-info-circle me-2"></i> Waiting for results...
                </div>
            `;
        }
        
        html += '</div>';
        container.innerHTML = html;
    }

    // ============================================
    // GENERATE FUNCTIONS
    // ============================================
    function generateAll() {
        const blockId = document.querySelector('input[name="block_id"]')?.value || '{{ $blockId }}';
        const period = document.querySelector('#generateAllModal select[name="period"]')?.value || 'Midterm';
        
        if (!blockId || blockId === '0') {
            alert('Please select a block first.');
            return;
        }
        
        if (!confirm('Generate AI recommendations for ALL students in this block?\n\nExisting recommendations will be overwritten.')) {
            return;
        }
        
        // Show progress
        showProgressContainer();
        safeSetText('progressStatus', 'Starting...');
        updateProgress(0, 0, 0, 0);
        
        const details = safeGetElement('progressDetails');
        if (details) {
            details.innerHTML = `
                <div class="list-group-item text-center text-muted py-3">
                    <i class="fas fa-spinner fa-spin me-2"></i> Initializing generation...
                </div>
            `;
        }
        
        // Close modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('generateAllModal'));
        if (modal) modal.hide();
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        
        fetch('/teacher/recommendations/generate-block', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                block_id: parseInt(blockId),
                period: period,
            }),
        })
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    throw new Error(`Server returned ${response.status}: ${text.substring(0, 100)}`);
                });
            }
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data);
            
            if (data.success) {
                const total = data.total || 0;
                const success = data.success_count || 0;
                const failed = data.failed_count || 0;
                
                updateProgress(100, total, success, failed);
                safeSetText('progressStatus', 'Complete!');
                
                const statusEl = safeGetElement('progressStatus');
                if (statusEl) statusEl.className = 'badge bg-success ms-2';
                
                if (data.progress && data.progress.length > 0) {
                    showProgressDetails(data.progress);
                }
                
                setTimeout(() => {
                    alert('' + data.message);
                    window.location.reload();
                }, 1500);
            } else {
                safeSetText('progressStatus', '❌ Failed');
                const statusEl = safeGetElement('progressStatus');
                if (statusEl) statusEl.className = 'badge bg-danger ms-2';
                alert('' + (data.message || 'Unknown error occurred.'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            safeSetText('progressStatus', '❌ Error');
            const statusEl = safeGetElement('progressStatus');
            if (statusEl) statusEl.className = 'badge bg-danger ms-2';
            alert('❌ Failed to generate recommendations: ' + error.message);
        });
    }

    function generateStudent(studentId) {
        const blockId = document.querySelector('input[name="block_id"]')?.value || '{{ $blockId }}';
        
        // Get student name from the table row
        let studentName = 'Student';
        const btns = document.querySelectorAll(`button[onclick*="generateStudent(${studentId})"]`);
        if (btns.length > 0) {
            const row = btns[0].closest('tr');
            if (row) {
                const nameCell = row.querySelector('td:nth-child(2)');
                if (nameCell) {
                    studentName = nameCell.textContent.trim();
                }
            }
        }
        
        if (!blockId || blockId === '0') {
            alert('Please select a block first.');
            return;
        }
        
        if (!confirm(`Generate AI recommendation for ${studentName}?`)) {
            return;
        }
        
        // Show progress
        showProgressContainer();
        safeSetText('progressStatus', 'Processing...');
        updateProgress(0, 1, 0, 0);
        
        const details = safeGetElement('progressDetails');
        if (details) {
            details.innerHTML = `
                <div class="list-group-item d-flex justify-content-between align-items-center py-1">
                    <span class="text-muted"> ${studentName}</span>
                    <span class="small">Processing...</span>
                </div>
            `;
        }
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        
        fetch('/teacher/recommendations/generate-selected', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                student_ids: [parseInt(studentId)],
                block_id: parseInt(blockId),
                period: 'Midterm',
            }),
        })
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    throw new Error(`Server returned ${response.status}`);
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                updateProgress(100, 1, 1, 0);
                safeSetText('progressStatus', ' Complete!');
                const statusEl = safeGetElement('progressStatus');
                if (statusEl) statusEl.className = 'badge bg-success ms-2';
                
                if (details) {
                    details.innerHTML = `
                        <div class="list-group-item d-flex justify-content-between align-items-center py-1">
                            <span class="text-success"> ${studentName}</span>
                            <span class="small">Success</span>
                        </div>
                    `;
                }
                
                setTimeout(() => {
                    alert('' + data.message);
                    window.location.reload();
                }, 1500);
            } else {
                updateProgress(100, 1, 0, 1);
                safeSetText('progressStatus', ' Failed');
                const statusEl = safeGetElement('progressStatus');
                if (statusEl) statusEl.className = 'badge bg-danger ms-2';
                
                if (details) {
                    details.innerHTML = `
                        <div class="list-group-item d-flex justify-content-between align-items-center py-1">
                            <span class="text-danger">❌ ${studentName}</span>
                            <span class="small">Failed: ${data.message || 'Unknown'}</span>
                        </div>
                    `;
                }
                
                alert('' + (data.message || 'Unknown error occurred.'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            updateProgress(100, 1, 0, 1);
            safeSetText('progressStatus', '❌ Error');
            const statusEl = safeGetElement('progressStatus');
            if (statusEl) statusEl.className = 'badge bg-danger ms-2';
            
            if (details) {
                details.innerHTML = `
                    <div class="list-group-item d-flex justify-content-between align-items-center py-1">
                        <span class="text-danger">❌ ${studentName}</span>
                        <span class="small">Error: ${error.message}</span>
                    </div>
                `;
            }
            
            alert('❌ Failed to generate recommendation: ' + error.message);
        });
    }

    function deleteRec(recId) {
    deleteRecId = recId;
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}

document.getElementById('confirmDeleteBtn')?.addEventListener('click', function() {
    if (!deleteRecId) return;
    
    const blockId = document.querySelector('input[name="block_id"]')?.value || '{{ $blockId }}';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    
    // FIX: Use the correct URL and method
    const url = `/teacher/recommendations/${deleteRecId}/delete`;
    
    fetch(url, {
        method: 'DELETE',  // Ensure this is DELETE, not POST
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
        },
        body: JSON.stringify({
            block_id: parseInt(blockId),
        }),
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => {
                throw new Error(`Server returned ${response.status}: ${text.substring(0, 100)}`);
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            alert('Recommendation deleted successfully.');
            window.location.reload();
        } else {
            alert('' + (data.message || 'Failed to delete.'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Failed to delete recommendation: ' + error.message);
    })
    .finally(() => {
        const modal = bootstrap.Modal.getInstance(document.getElementById('deleteModal'));
        if (modal) modal.hide();
    });
});
</script>
@endpush