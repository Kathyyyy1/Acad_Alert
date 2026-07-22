<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>Login - AcadAlert | UDD</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- Google Fonts - Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* ---------- Global Reset ---------- */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
            
            /* Full-page background image */
            background-image: url('{{ asset("images/backgrounds/loginbackground.jpg") }}');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            position: relative;
        }
        
        /* Blue overlay layer */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(26, 26, 46, 0.70);
            z-index: 0;
        }

        /* Content above overlay */
        body > * {
            position: relative;
            z-index: 1;
        }
        
        .login-container {
            width: 100%;
            max-width: 420px;
            position: relative;
        }

        .login-border-wrapper {
            position: relative;
            border-radius: 24px;
            padding: 8px; /* INCREASED border thickness from 4px → 8px */
            background: conic-gradient(
                from 0deg,
                #e0f7fa,  /* 1. Light cyan */
                #b3e5fc,  /* 2. Light blue */
                #81d4fa,  /* 3. Light sky blue */
                #4fc3f7,  /* 4. Sky blue */
                #29b6f6,  /* 5. Bright blue */
                #0288d1,  /* 6. Deep blue */
                #0277bd,  /* 7. Darker blue */
                #40b2e7,  /* 8. Bright blue */
                #4fc3f7,  /* 9. Sky blue */
                #81d4fa,  /* 10. Light sky blue */
                #b3e5fc,  /* Back to light blue */
                #e0f7fa   /* Back to light cyan */
            );
            background-size: 400% 400%;
            animation: rotateBorder 6s ease-in-out infinite;
            
            /* Enhanced glow shadow with high-intensity blur */
            box-shadow: 
                0 20px 60px rgba(0, 0, 0, 0.5),
                0 0 40px rgba(41, 182, 246, 0.3),
                0 0 80px rgba(2, 136, 209, 0.2),
                0 0 120px rgba(41, 182, 246, 0.15);
            
            transition: filter 0.4s ease, box-shadow 0.4s ease;
        }

        .login-border-wrapper:hover {
            filter: brightness(1.15) saturate(1.3);
            box-shadow: 
                0 20px 60px rgba(0, 0, 0, 0.5),
                0 0 60px rgba(41, 182, 246, 0.4),
                0 0 120px rgba(2, 136, 209, 0.3),
                0 0 180px rgba(41, 182, 246, 0.2);
        }

        /* Card content sits inside the border wrapper */
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 16px; /* Slightly smaller to accommodate thicker border */
            padding: 40px 35px;
            border: none;
            background-clip: padding-box;
            position: relative;
        }

        /* Inner shadow to separate card from border */
        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.03);
            pointer-events: none;
            z-index: 0;
        }

        /* Ensure all card content is above the inner shadow */
        .login-card > * {
            position: relative;
            z-index: 1;
        }

        /* ---------- Enhanced Border Animation ---------- */
        @keyframes rotateBorder {
            0% {
                background-position: 0% 50%;
            }
            25% {
                background-position: 50% 100%;
            }
            50% {
                background-position: 100% 50%;
            }
            75% {
                background-position: 50% 0%;
            }
            100% {
                background-position: 0% 50%;
            }
        }

        /* ---------- Pulse Glow Animation ---------- */
        @keyframes pulseGlow {
            0%, 100% {
                box-shadow: 
                    0 20px 60px rgba(0, 0, 0, 0.5),
                    0 0 40px rgba(41, 182, 246, 0.3),
                    0 0 80px rgba(2, 136, 209, 0.2),
                    0 0 120px rgba(41, 182, 246, 0.15);
            }
            50% {
                box-shadow: 
                    0 20px 60px rgba(0, 0, 0, 0.5),
                    0 0 60px rgba(41, 182, 246, 0.5),
                    0 0 120px rgba(2, 136, 209, 0.35),
                    0 0 200px rgba(41, 182, 246, 0.25);
            }
        }
        .login-border-wrapper {
            animation: rotateBorder 6s ease-in-out infinite, pulseGlow 3s ease-in-out infinite;
        }

        /* ---------- Form Elements ---------- */
        .login-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-logo img {
            max-width: 80px;
            height: auto;
            margin-bottom: 12px;
        }
        
        .login-logo h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #1a1a2e;
            margin-top: 8px;
            margin-bottom: 4px;
        }
        
        .login-logo p {
            color: #6c757d;
            font-size: 0.9rem;
            margin: 0;
        }
        
        .form-control {
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 0.95rem;
            border: 2px solid #e9ecef;
            transition: all 0.2s ease;
        }
        
        .form-control:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 4px rgba(78, 115, 223, 0.15);
        }
        
        .form-control.is-invalid:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 4px rgba(220, 53, 69, 0.15);
        }
        
        .btn-login {
            background: linear-gradient(135deg, #4e73df, #224abe);
            border: none;
            border-radius: 12px;
            padding: 14px;
            font-weight: 600;
            font-size: 1rem;
            color: #fff;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(78, 115, 223, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .form-check-input:checked {
            background-color: #4e73df;
            border-color: #4e73df;
        }
        
        .alert {
            border-radius: 12px;
            border: none;
        }
        
        .alert-danger {
            background: #fef2f2;
            color: #991b1b;
            border-left: 4px solid #dc3545;
        }
        
        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border-left: 4px solid #22c55e;
        }
        
        .footer-text {
            text-align: center;
            margin-top: 20px;
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.8rem;
        }
        
        .footer-text a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
        }
        
        .footer-text a:hover {
            color: #fff;
            text-decoration: underline;
        }
        
        /* Demo Credentials Box */
        .demo-box {
            background: #f8f9fc;
            border-radius: 12px;
            padding: 15px;
            margin-top: 20px;
            border: 1px dashed #d1d3e2;
        }
        
        .demo-box .demo-title {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6c757d;
            font-weight: 600;
        }
        
        .demo-box .demo-item {
            font-size: 0.85rem;
            padding: 3px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .demo-box .demo-item .badge {
            font-size: 0.7rem;
            padding: 3px 8px;
        }
        
        .demo-box .demo-item code {
            background: #e9ecef;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
        }

        /* ---------- Responsive Adjustments ---------- */
        @media (max-width: 576px) {
            .login-card {
                padding: 30px 20px;
                border-radius: 14px;
            }
            
            .login-logo img {
                max-width: 60px;
            }
            
            .login-logo h1 {
                font-size: 1.4rem;
            }

            .login-border-wrapper {
                padding: 6px;
                border-radius: 20px;
            }

            .login-card {
                border-radius: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Enhanced Animated Gradient Border Wrapper -->
        <div class="login-border-wrapper">
            <div class="login-card">
                <div class="login-logo">
                    <!-- UDD Logo Image -->
                    <img src="{{ asset('images/logo/udd-logo.png') }}" alt="Universidad de Dagupan Logo">
                    <h1>AcadAlert</h1>
                    <p>Universidad de Dagupan</p>
                </div>
                
                <!-- Flash Messages -->
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                
                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i> {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                
                <!-- Login Form -->
                <form method="POST" action="{{ route('login') }}">
                    @csrf
                    
                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-2 border-end-0 rounded-start-3">
                                <i class="fas fa-envelope text-muted"></i>
                            </span>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" 
                                   id="email" name="email" value="{{ old('email') }}" 
                                   placeholder="Enter your email" required autofocus>
                        </div>
                        @error('email')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label fw-semibold">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-2 border-end-0 rounded-start-3">
                                <i class="fas fa-lock text-muted"></i>
                            </span>
                            <input type="password" class="form-control" id="password" 
                                   name="password" placeholder="Enter your password" required>
                        </div>
                    </div>
                    
                    <div class="mb-3 d-flex justify-content-between align-items-center">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="remember" id="remember">
                            <label class="form-check-label" for="remember">
                                Remember me
                            </label>
                        </div>
                        <!-- <a href="#" class="text-decoration-none small text-primary">Forgot password?</a> -->
                    </div>
                    
                    <button type="submit" class="btn btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i> Sign In
                    </button>
                </form>
                
        </div>
        
    </div>
    <br>
     <div class="footer-text">
            &copy; {{ date('Y') }} AcadAlert - AI-Powered Academic Risk Detection System
        </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>