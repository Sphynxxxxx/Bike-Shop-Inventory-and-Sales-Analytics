<?php
// Start the session
session_start();

// Check if user was logged in (optional security check)
$was_logged_in = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie if it exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Optional: Set a logout message for the login page
if ($was_logged_in) {
    // Start a new session just for the logout message
    session_start();
    $_SESSION['logout_message'] = 'You have been successfully logged out.';
    $_SESSION['logout_type'] = 'success';
} else {
    // If user wasn't logged in, set a different message
    session_start();
    $_SESSION['logout_message'] = 'Session expired. Please log in again.';
    $_SESSION['logout_type'] = 'warning';
}

// Prevent caching of this page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Redirect to login page
// Adjust the path based on your file structure
header("Location: ../../index.php");
exit();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logging Out - Bike Shop</title>
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
        
        .logout-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 3rem;
            text-align: center;
            max-width: 400px;
            width: 100%;
        }
        
        .logout-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #212529 0%, #495057 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }
        
        .logout-icon i {
            font-size: 2rem;
            color: white;
        }
        
        .spinner-border {
            color: #212529;
        }
        
        .logout-text {
            color: #6c757d;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <!-- Fallback content in case redirect fails -->
    <div class="logout-container">
        <div class="logout-icon">
            <i class="fas fa-sign-out-alt"></i>
        </div>
        <h3 class="mb-3">Logging Out...</h3>
        <div class="spinner-border" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="logout-text">Please wait while we securely log you out.</p>
        <p class="logout-text">
            <small>If you are not redirected automatically, <a href="../login.php" class="text-decoration-none">click here</a>.</small>
        </p>
    </div>

    <script>
        // Fallback redirect in case PHP redirect fails
        setTimeout(function() {
            window.location.href = '../login.php';
        }, 3000);
        
        // Clear any stored data in localStorage/sessionStorage
        if (typeof(Storage) !== "undefined") {
            localStorage.clear();
            sessionStorage.clear();
        }
    </script>
</body>
</html>