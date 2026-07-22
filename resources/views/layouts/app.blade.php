<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>@yield('title', 'AcadAlert') - Universidad de Dagupan</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 (Icons) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- Google Fonts - Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    
    @stack('styles')
</head>
<body>
    <div id="app">
        <!-- Top Navigation Bar -->
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
            <div class="container-fluid">
                <!-- Brand / Logo -->
                <a class="navbar-brand" href="{{ route('dashboard') }}">
                    <i class="fas fa-graduation-cap me-2"></i>
                    <span class="fw-bold">AcadAlert</span>
                </a>
                
                <!-- Toggler for mobile -->
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" 
                        aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                
                <!-- Navbar Content -->
                <div class="collapse navbar-collapse" id="navbarMain">
                    <!-- Left Side: Navigation Links -->
                    <ul class="navbar-nav me-auto">
                        @auth
                            @if(auth()->user()->isAdmin())
                                <li class="nav-item">
                                   
                                </li>
                                <li class="nav-item">
                                    
                                </li>
                                <li class="nav-item">
                                    
                                </li>
                            @endif
                            
                            @if(auth()->user()->isMasterTeacher())
                                <li class="nav-item">
                                    
                                </li>
                            @endif
                            
                            @if(auth()->user()->isCounselor())
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('counselor.dashboard') ? 'active' : '' }}" 
                                       href="{{ route('counselor.dashboard') }}">
                                        <i class="fas fa-briefcase me-1"></i> Cases
                                    </a>
                                </li>
                            @endif
                            
                            @if(auth()->user()->isStudent())
                                <li class="nav-item">
                                    
                                </li>
                            @endif
                        @endauth
                    </ul>
                    
                    <!-- Right Side: User Menu -->
                    <ul class="navbar-nav ms-auto">
                        @auth
                            <!-- User Dropdown -->
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" 
                                   id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <div class="avatar-circle bg-light text-primary rounded-circle d-flex align-items-center justify-content-center me-2" 
                                         style="width: 32px; height: 32px; font-weight: 600; font-size: 14px;">
                                        {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                                    </div>
                                    <span class="d-none d-md-inline">{{ auth()->user()->name }}</span>
                                    <small class="d-none d-md-inline text-light opacity-75 ms-1">
                                        ({{ ucfirst(str_replace('_', ' ', auth()->user()->role)) }})
                                    </small>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                    <li>
                                        <a class="dropdown-item" href="{{ route('profile') }}">
                                            <i class="fas fa-user me-2"></i> Profile
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="{{ route('settings') }}">
                                            <i class="fas fa-cog me-2"></i> Settings
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item text-danger" href="{{ route('logout') }}"
                                           onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                                        </a>
                                        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                            @csrf
                                        </form>
                                    </li>
                                </ul>
                            </li>
                        @else
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('login') }}">
                                    <i class="fas fa-sign-in-alt me-1"></i> Login
                                </a>
                            </li>
                        @endauth
                    </ul>
                </div>
            </div>
        </nav>

        <!-- Main Content Area with Sidebar -->
        <div class="d-flex" id="wrapper">
            <!-- Sidebar -->
            <div class="bg-dark text-white" id="sidebar-wrapper" style="min-height: 100vh; width: 250px; flex-shrink: 0;">
                <div class="sidebar-heading text-center py-4 primary-text fs-4 fw-bold text-uppercase border-bottom border-secondary">
                    <i class=""></i>AcadAlert
                </div>
                <div class="list-group list-group-flush my-3">
                    @auth
                        @if(auth()->user()->isAdmin())
                            <a href="{{ route('admin.dashboard') }}" class="list-group-item list-group-item-action bg-transparent text-white border-0 {{ request()->routeIs('admin.dashboard') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-chart-pie me-2"></i> Dashboard
                            </a>
                            <a href="{{ route('admin.users.index') }}" class="list-group-item list-group-item-action bg-transparent text-white border-0 {{ request()->routeIs('admin.users*') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-users me-2"></i> Users
                            </a>
                            <a href="{{ route('admin.academic.index') }}" class="list-group-item list-group-item-action bg-transparent text-white border-0 {{ request()->routeIs('admin.academic*') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-building me-2"></i> Academic Structure
                            </a>
                            <a href="{{ route('admin.risk.config') }}" class="list-group-item list-group-item-action bg-transparent text-white border-0 {{ request()->routeIs('admin.risk*') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-sliders-h me-2"></i> Risk Settings
                            </a>
                            <a href="{{ route('admin.payments.index') }}" class="list-group-item list-group-item-action bg-transparent text-white border-0 {{ request()->routeIs('admin.payments*') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-credit-card me-2"></i> Payments
                            </a>
                            <a href="{{ route('admin.audit.logs') }}" class="list-group-item list-group-item-action bg-transparent text-white border-0 {{ request()->routeIs('admin.audit*') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-history me-2"></i> Audit Logs
                            </a>
                            <a href="{{ route('admin.schoolyear.index') }}" class="list-group-item list-group-item-action bg-transparent text-white border-0 {{ request()->routeIs('admin.schoolyear*') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-calendar-alt me-2"></i> School Year
                            </a>
                            <a href="{{ route('admin.system.health') }}" class="list-group-item list-group-item-action bg-transparent text-white border-0 {{ request()->routeIs('admin.system*') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-server me-2"></i> System Health
                            </a>
                        @endif
                        
                        @if(auth()->user()->isMasterTeacher())
                            <a href="{{ route('teacher.department') }}" class="list-group-item list-group-item-action bg-transparent text-white border-0 {{ request()->routeIs('teacher.department') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-chart-pie me-2"></i> Department Overview
                            </a>
                            <a href="{{ route('teacher.recommendations') }}" class="list-group-item list-group-item-action bg-transparent text-white border-0 {{ request()->routeIs('teacher.recommendations*') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-lightbulb me-2"></i> Recommendations
                            </a>
                            @if(isset($block))
                                <a href="{{ route('teacher.block', ['blockId' => $block->id]) }}" class="list-group-item list-group-item-action bg-transparent text-white border-0 {{ request()->routeIs('teacher.block') ? 'active-sidebar' : '' }}">
                                    <i class="fas fa-users me-2"></i> Current Block
                                </a>
                            @endif
                        @endif
                        
                        @if(auth()->user()->isCounselor())
                            <a href="{{ route('counselor.dashboard') }}" class="list-group-item list-group-item-action bg-transparent text-white border-0 {{ request()->routeIs('counselor.dashboard') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-briefcase me-2"></i> Caseload
                            </a>
                            <a href="{{ route('counselor.cases') }}" class="list-group-item list-group-item-action bg-transparent text-white border-0 {{ request()->routeIs('counselor.cases') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-folder-open me-2"></i> All Cases
                            </a>
                        @endif
                        
                        @if(auth()->user()->isStudent())
                            <a href="{{ route('student.dashboard') }}" class="list-group-item list-group-item-action bg-transparent text-white border-0 {{ request()->routeIs('student.dashboard') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-user-graduate me-2"></i> My Dashboard
                            </a>
                            <a href="{{ route('student.grades') }}" class="list-group-item list-group-item-action bg-transparent text-white border-0 {{ request()->routeIs('student.grades') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-book me-2"></i> My Grades
                            </a>
                            <a href="{{ route('student.attendance') }}" class="list-group-item list-group-item-action bg-transparent text-white border-0 {{ request()->routeIs('student.attendance') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-clipboard-check me-2"></i> Attendance
                            </a>
                            <a href="{{ route('student.recommendations') }}" class="list-group-item list-group-item-action bg-transparent text-white border-0 {{ request()->routeIs('student.recommendations') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-lightbulb me-2"></i> Recommendations
                                @php
                                    $pendingCount = \App\Models\StudentRecommendationTracking::where('student_id', auth()->user()->student->id ?? 0)->where('is_completed', false)->count();
                                @endphp
                                @if($pendingCount > 0)
                                    <span class="badge bg-warning ms-1">{{ $pendingCount }}</span>
                                @endif
                            </a>
                            <a href="{{ route('student.counselor') }}" class="list-group-item list-group-item-action bg-transparent text-white border-0 {{ request()->routeIs('student.counselor') ? 'active-sidebar' : '' }}">
                                <i class="fas fa-headset me-2"></i> Contact Counselor
                            </a>
                        @endif
                    @endauth
                </div>
                
                <!-- Sidebar Footer -->
                <div class="sidebar-footer text-center text-muted small py-3 border-top border-secondary mt-auto" style="position: absolute; bottom: 0; width: 100%;">
                    <i class="fas fa-shield-alt me-1"></i> v1.0.0
                </div>
            </div>
            
            <!-- Page Content -->
            <div id="page-content-wrapper" class="flex-grow-1" style="padding-top: 70px; min-height: 100vh;">
                <div class="container-fluid px-4">
                    <!-- Flash Messages -->
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                            <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif
                    
                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i> {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif
                    
                    @if(session('warning'))
                        <div class="alert alert-warning alert-dismissible fade show mt-3" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i> {{ session('warning') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif
                    
                    <!-- ======================================== -->
                    <!-- CHART ERROR CONTAINER - Added Step 17    -->
                    <!-- ======================================== -->
                    <div id="chartErrorContainer" style="display: none;"></div>
                    
                    <!-- Page Title -->
                    <div class="d-flex justify-content-between align-items-center mt-3 mb-4">
                        <h1 class="h3 mb-0 text-gray-800">@yield('page_title', 'Dashboard')</h1>
                        @yield('page_actions')
                    </div>
                    
                    <!-- Main Content -->
                    @yield('content')
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <footer class="bg-white border-top py-3 mt-auto" style="margin-left: 250px;">
            <div class="container-fluid px-4">
                <div class="row align-items-center">
                    <div class="col-md-6 text-center text-md-start">
                        <span class="text-muted small">
                            &copy; {{ date('Y') }} <strong>AcadAlert</strong> - Universidad de Dagupan
                        </span>
                    </div>
                    <div class="col-md-6 text-center text-md-end">
                        <span class="text-muted small">
                            <i class=""></i> AI-Powered Academic Risk Detection
                        </span>
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <!-- Custom JS -->
    <script src="{{ asset('js/app.js') }}"></script>
    
    <!-- ======================================== -->
    <!-- CHART.JS INITIALIZATION - Added Step 18   -->
    <!-- ======================================== -->
    <script src="{{ asset('js/charts/chart-init.js') }}"></script>
    
    @stack('scripts')
</body>
</html>