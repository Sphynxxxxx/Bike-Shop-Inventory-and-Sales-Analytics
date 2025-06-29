<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bike Shop Inventory and Sales Analytics - Login</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 900px;
            min-height: 500px;
        }
        
        .login-left {
            background: linear-gradient(135deg, #212529 0%, #495057 100%);
            color: white;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .login-left::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="1" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
            animation: float 20s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }
        
        .login-right {
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .brand-logo {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            position: relative;
            z-index: 1;
        }
        
        .brand-logo i {
            font-size: 2.5rem;
            color: white;
        }
        
        .welcome-text {
            position: relative;
            z-index: 1;
        }
        
        .welcome-text h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .welcome-text p {
            font-size: 1.1rem;
            opacity: 0.9;
            line-height: 1.6;
        }
        
        .login-form h3 {
            font-weight: 700;
            color: #212529;
            margin-bottom: 0.5rem;
        }
        
        .login-form .text-muted {
            margin-bottom: 2rem;
        }
        
        .form-floating {
            margin-bottom: 1.5rem;
        }
        
        .form-floating .form-control {
            border: 2px solid #f8f9fa;
            border-radius: 12px;
            padding: 1rem 0.75rem;
            height: 60px;
            background-color: #f8f9fa;
            transition: all 0.3s ease;
        }
        
        .form-floating .form-control:focus {
            border-color: #212529;
            background-color: white;
            box-shadow: 0 0 0 0.2rem rgba(33, 37, 41, 0.1);
        }
        
        .form-floating label {
            color: #6c757d;
            padding: 1rem 0.75rem;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #212529 0%, #495057 100%);
            border: none;
            border-radius: 12px;
            padding: 0.875rem 2rem;
            font-weight: 600;
            color: white;
            width: 100%;
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(33, 37, 41, 0.3);
            color: white;
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .form-check-input:checked {
            background-color: #212529;
            border-color: #212529;
        }
        
        .forgot-password {
            color: #6c757d;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }
        
        .forgot-password:hover {
            color: #212529;
        }
        
        .demo-credentials {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            padding: 1rem;
            margin-top: 1.5rem;
            border-left: 4px solid #212529;
        }
        
        .demo-credentials h6 {
            color: #212529;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .demo-credentials p {
            color: #6c757d;
            font-size: 0.875rem;
            margin: 0;
        }
        
        .stats-overlay {
            position: absolute;
            bottom: 2rem;
            left: 3rem;
            right: 3rem;
            z-index: 1;
        }
        
        .stats-item {
            text-align: center;
            opacity: 0.8;
        }
        
        .stats-item h4 {
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        
        .stats-item small {
            font-size: 0.8rem;
            opacity: 0.7;
        }
        
        @media (max-width: 768px) {
            .login-container {
                margin: 1rem;
                border-radius: 16px;
            }
            
            .login-left {
                padding: 2rem;
                min-height: 300px;
            }
            
            .login-right {
                padding: 2rem;
            }
            
            .stats-overlay {
                position: static;
                margin-top: 2rem;
            }
        }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
            margin-right: 10px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .btn-login.loading {
            pointer-events: none;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="row g-0 h-100">
                <!-- Left Side - Brand & Welcome -->
                <div class="col-lg-5 login-left">
                    <div class="brand-logo">
                        <i class="fas fa-bicycle"></i>
                    </div>
                    <div class="welcome-text">
                        <h2>Welcome Back!</h2>
                        <p>Access your bike shop analytics dashboard and manage your inventory with ease.</p>
                    </div>
                    
                    <div class="stats-overlay">
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="stats-item">
                                    <h4>150+</h4>
                                    <small>Products</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="stats-item">
                                    <h4>₱2.5M</h4>
                                    <small>Revenue</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="stats-item">
                                    <h4>500+</h4>
                                    <small>Sales</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Side - Login Form -->
                <div class="col-lg-7 login-right">
                    <div class="login-form">
                        <h3>Sign In</h3>
                        <p class="text-muted">Enter your credentials to access the dashboard</p>
                        
                        <form id="loginForm">
                            <div class="form-floating">
                                <input type="email" class="form-control" id="email" placeholder="name@example.com">
                                <label for="email">
                                    <i class="fas fa-envelope me-2"></i>Email Address
                                </label>
                            </div>
                            
                            <div class="form-floating">
                                <input type="password" class="form-control" id="password" placeholder="Password">
                                <label for="password">
                                    <i class="fas fa-lock me-2"></i>Password
                                </label>
                            </div>
                            
                            <div class="remember-forgot">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="remember" checked>
                                    <label class="form-check-label" for="remember">
                                        Remember me
                                    </label>
                                </div>
                                <a href="#" class="forgot-password">Forgot password?</a>
                            </div>
                            
                            <button type="submit" class="btn btn-login" id="loginBtn">
                                <span id="loginText">
                                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                                </span>
                            </button>
                        </form>
                        

                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const loginBtn = document.getElementById('loginBtn');
            const loginText = document.getElementById('loginText');
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            
            // Add loading state
            loginBtn.classList.add('loading');
            loginText.innerHTML = '<span class="loading"></span>Signing In...';
            
            // Simulate authentication delay
            setTimeout(() => {
                // Static credential validation
                if (email === 'admin@bikeshop.com' && password === 'admin123') {
                    // Success
                    loginText.innerHTML = '<i class="fas fa-check me-2"></i>Success! Redirecting...';
                    
                    // Redirect to dashboard after short delay
                    setTimeout(() => {
                        window.location.href = 'tabs/dashboard.php';
                    }, 1000);
                } else {
                    // Error - Invalid credentials
                    loginBtn.classList.remove('loading');
                    loginText.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Invalid Credentials';
                    loginBtn.style.background = 'linear-gradient(135deg, #dc3545 0%, #c82333 100%)';
                    
                    // Shake animation for error feedback
                    loginBtn.style.animation = 'shake 0.5s ease-in-out';
                    
                    // Reset button after delay
                    setTimeout(() => {
                        loginText.innerHTML = '<i class="fas fa-sign-in-alt me-2"></i>Sign In';
                        loginBtn.style.background = 'linear-gradient(135deg, #212529 0%, #495057 100%)';
                        loginBtn.style.animation = '';
                    }, 2000);
                }
            }, 1500);
        });

        // Add input focus effects
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'translateY(-2px)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'translateY(0)';
            });
        });

        // Forgot password handler
        document.querySelector('.forgot-password').addEventListener('click', function(e) {
            e.preventDefault();
            alert('Password reset functionality would be implemented here.');
        });
    </script>
</body>
</html>