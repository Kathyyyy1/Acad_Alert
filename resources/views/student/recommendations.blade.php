@extends('layouts.app')

@section('title', 'My Recommendations - AcadAlert')

@section('page_title', 'My Intervention Recommendations')
@section('page_actions')
    <div>
        <span class="badge bg-secondary text-white p-2 me-2">
            <i class="fas fa-user me-1"></i> {{ auth()->user()->name }}
        </span>
        <a href="{{ route('student.dashboard') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>
@endsection

@section('content')
<div class="row">
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-lightbulb me-2"></i> 
                Your Intervention Recommendations
                <span class="badge bg-light text-primary ms-2">{{ count($recommendations) }}</span>
            </div>
            <div class="card-body">
                @if(count($recommendations) > 0)
                    @php
                        $completedCount = 0;
                        foreach ($recommendations as $rec) {
                            if ($rec->is_completed) $completedCount++;
                        }
                        $pendingCount = count($recommendations) - $completedCount;
                    @endphp
                    
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h3>{{ count($recommendations) }}</h3>
                                    <small class="text-muted">Total Recommendations</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-warning">
                                <div class="card-body text-center">
                                    <h3>{{ $pendingCount }}</h3>
                                    <small class="text-muted">Pending Completion</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h3>{{ $completedCount }}</h3>
                                    <small>Completed</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="list-group">
                        @foreach($recommendations as $recommendation)
                            @php
                                $actions = json_decode($recommendation->suggested_actions, true);
                                $riskFactors = json_decode($recommendation->risk_factors, true);
                            @endphp
                            <div class="list-group-item {{ $recommendation->is_completed ? 'list-group-item-success' : '' }}">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center mb-2">
                                            <span class="badge bg-{{ $recommendation->is_completed ? 'success' : 'warning' }} me-2">
                                                {{ $recommendation->is_completed ? 'Completed' : 'Pending' }}
                                            </span>
                                            <small class="text-muted">
                                                Generated: {{ \Carbon\Carbon::parse($recommendation->generated_at)->format('M d, Y') }}
                                            </small>
                                        </div>
                                        
                                        @if($riskFactors)
                                            <div class="mb-2">
                                                <strong>Risk Factors:</strong>
                                                @foreach($riskFactors as $factor)
                                                    <span class="badge bg-secondary">{{ $factor }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                        
                                        @if($actions)
                                            <div>
                                                <strong>Recommended Actions:</strong>
                                                <ul class="mb-0 mt-1">
                                                    @foreach($actions as $action)
                                                        <li>
                                                            <span class="badge bg-{{ $action['priority'] === 'high' ? 'danger' : ($action['priority'] === 'medium' ? 'warning' : 'info') }}">
                                                                {{ $action['priority'] }}
                                                            </span>
                                                            <strong>{{ ucfirst(str_replace('_', ' ', $action['action'])) }}</strong>
                                                            @if(isset($action['details']))
                                                                <span class="text-muted">- {{ $action['details'] }}</span>
                                                            @endif
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="ms-3">
                                        @if(!$recommendation->is_completed)
                                            <button class="btn btn-sm btn-success" onclick="markCompleted({{ $recommendation->id }}, this)">
                                                <i class="fas fa-check me-1"></i> Mark as Done
                                            </button>
                                        @else
                                            <span class="badge bg-success">Completed</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-lightbulb fa-3x d-block mb-3"></i>
                        <p>No intervention recommendations available.</p>
                        <small class="text-muted">Your master teacher or counselor will generate recommendations based on your academic performance.</small>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Toast Notification -->
<div id="recommendationToast" style="position: fixed; top: 80px; right: 20px; z-index: 9999; display: none;">
    <div class="toast align-items-center show" role="alert">
        <div class="d-flex">
            <div class="toast-body" id="toastMessage"></div>
            <button type="button" class="btn-close me-2 m-auto" onclick="document.getElementById('recommendationToast').style.display='none'"></button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function markCompleted(recommendationId, button) {
        const btn = button;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';
        
        fetch('/student/recommendation/complete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ recommendation_id: recommendationId }),
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const listItem = btn.closest('.list-group-item');
                listItem.classList.remove('list-group-item-warning');
                listItem.classList.add('list-group-item-success');
                btn.outerHTML = '<span class="badge bg-success">Completed</span>';
                
                // Update badge
                const badge = listItem.querySelector('.badge');
                if (badge) {
                    badge.className = 'badge bg-success me-2';
                    badge.textContent = 'Completed';
                }
                
                // Update counts
                showToast('Recommendation marked as completed!');
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showToast('' + data.message);
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check me-1"></i> Mark as Done';
            }
        })
        .catch(error => {
            showToast('Error: ' + error.message);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check me-1"></i> Mark as Done';
        });
    }
    
    function showToast(message) {
        const toast = document.getElementById('recommendationToast');
        document.getElementById('toastMessage').textContent = message;
        toast.style.display = 'block';
        
        setTimeout(() => {
            toast.style.display = 'none';
        }, 4000);
    }
</script>
@endpush