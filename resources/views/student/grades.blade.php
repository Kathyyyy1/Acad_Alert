@extends('layouts.app')

@section('title', 'My Grades - AcadAlert')

@section('page_title', 'My Grades')
@section('page_actions')
    <div>
        <span class="badge bg-secondary text-white p-2 me-2">
            <i class="fas fa-user me-1"></i> {{ auth()->user()->name }}
        </span>
        <div class="d-inline-block">
            <select class="form-select form-select-sm d-inline-block" id="periodSelect" style="width: auto; display: inline-block;">
                @foreach($periods as $period)
                    <option value="{{ $period }}" {{ $currentPeriod == $period ? 'selected' : '' }}>{{ $period }}</option>
                @endforeach
            </select>
        </div>
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
                <i class="fas fa-book me-2"></i> 
                Your Grades - {{ $currentPeriod }} {{ $schoolYear }}
                <span class="badge bg-light text-primary ms-2">{{ $studentInfo->program_code }} - {{ $studentInfo->year_level }}</span>
            </div>
            <div class="card-body">
                @if($subjectGrades->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Subject Code</th>
                                    <th>Subject Name</th>
                                    <th>Score</th>
                                    <th>Grade</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($subjectGrades as $index => $grade)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td><span class="badge bg-secondary">{{ $grade->subject_code }}</span></td>
                                    <td>{{ $grade->subject_name }}</td>
                                    <td>
                                        <span class="{{ $grade->numerical_grade < 75 ? 'text-danger fw-bold' : ($grade->numerical_grade < 80 ? 'text-warning' : '') }}">
                                            {{ $grade->numerical_grade }}%
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $grade->letter_grade === 'F' ? 'bg-danger' : ($grade->letter_grade === 'D' ? 'bg-warning' : 'bg-success') }}">
                                            {{ $grade->letter_grade ?? 'N/A' }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($grade->numerical_grade < 75)
                                            <span class="badge bg-danger"><i class="fas fa-times me-1"></i> Failing</span>
                                        @elseif($grade->numerical_grade < 80)
                                            <span class="badge bg-warning"><i class="fas fa-exclamation-triangle me-1"></i> At Risk</span>
                                        @else
                                            <span class="badge bg-success"><i class="fas fa-check me-1"></i> Passing</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="table-secondary">
                                    <td colspan="3"><strong>Average</strong></td>
                                    <td>
                                        <strong>
                                            @php
                                                $avg = $subjectGrades->avg('numerical_grade');
                                            @endphp
                                            <span class="{{ $avg < 75 ? 'text-danger' : ($avg < 80 ? 'text-warning' : 'text-success') }}">
                                                {{ round($avg, 1) }}%
                                            </span>
                                        </strong>
                                    </td>
                                    <td colspan="2">
                                        @php
                                            $failingCount = $subjectGrades->where('numerical_grade', '<', 75)->count();
                                        @endphp
                                        @if($failingCount > 0)
                                            <span class="badge bg-danger"> {{ $failingCount }} failing subject(s)</span>
                                        @else
                                            <span class="badge bg-success">All subjects passed</span>
                                        @endif
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-book fa-3x d-block mb-3"></i>
                        <p>No grades available for this period.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.getElementById('periodSelect')?.addEventListener('change', function() {
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('period', this.value);
        window.location.href = currentUrl.toString();
    });
</script>
@endpush