<?php
// error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Include required files
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is already logged in
if (isLoggedIn()) {
    header("Location: /index.php");
    exit;
}

$error = '';
$success = false;
$validToken = false;
$userId = null;

// Check if token is provided
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];
    $conn = getDBConnection();
    
    // Verify token
    $stmt = $conn->prepare("SELECT user_id, reset_token_expiry FROM users WHERE reset_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $tokenExpiry = strtotime($user['reset_token_expiry']);
        
        if ($tokenExpiry > time()) {
            $validToken = true;
            $userId = $user['user_id'];
        } else {
            $error = "This password reset link has expired. Please request a new one.";
        }
    } else {
        $error = "Invalid password reset link. Please request a new one.";
    }
    
    $stmt->close();
    // Don't close the connection here
}

// Process password reset form
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $validToken) {
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    
    if (empty($password)) {
        $error = 'Please enter a new password';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } else {
        // Use the existing connection
        
        // Hash new password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Update password and clear reset token
        $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE user_id = ?");
        $stmt->bind_param("si", $hashedPassword, $userId);
        
        if ($stmt->execute()) {
            $success = true;
        } else {
            $error = "An error occurred. Please try again.";
        }
        
        $stmt->close();
    }
}

// Close the connection at the end of the script
if (isset($conn)) {
    $conn->close();
}

// Set page title for header
$pageTitle = "Reset Password - Advocate Management System";

// Include header (without navigation for login page)
include_once '../includes/header-minimal.php';
?>

<div class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-blue-800">Advocate Management System</h1>
            <p class="text-gray-600">Create New Password</p>
        </div>
        
        <div class="bg-white rounded-lg shadow-lg p-8">
            <?php if ($success): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                    <p class="font-medium">Password Reset Successful!</p>
                    <p>Your password has been successfully reset. You can now log in with your new password.</p>
                    <p class="mt-4"><a href="/auth/login.php" class="text-green-700 font-semibold hover:underline">Go to Login</a></p>
                    
                    <!-- Redirect after 3 seconds -->
                    <p class="text-sm mt-2">You will be redirected to the login page in <span id="countdown">3</span> seconds...</p>
                    <meta http-equiv="refresh" content="3;url=login.php">
                    <script>
                        let seconds = 3;
                        const countdownElement = document.getElementById('countdown');
                        
                        const countdownInterval = setInterval(function() {
                            seconds--;
                            countdownElement.textContent = seconds;
                            
                            if (seconds <= 0) {
                                clearInterval(countdownInterval);
                                window.location.href = 'login.php';
                            }
                        }, 1000);
                    </script>
                </div>
            <?php elseif ($validToken): ?>
                <?php if (!empty($error)): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                        <p><?php echo $error; ?></p>
                    </div>
                <?php endif; ?>
                
                <p class="text-gray-600 mb-6">Please enter your new password below.</p>
                
                <form method="POST" action="" data-validate class="space-y-6">
                    <div>
                        <label for="password" class="block text-gray-700 font-medium mb-2">New Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400"></i>
                            </div>
                            <input type="password" id="password" name="password" 
                                   class="form-input pl-10 w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-150" 
                                   required
                                   placeholder="Create a new password">
                            <button type="button" class="toggle-password absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 focus:outline-none" data-target="#password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <p class="text-gray-500 text-sm mt-1">Minimum 8 characters</p>
                    </div>
                    
                    <div>
                    <label for="confirm_password" class="block text-gray-700 font-medium mb-2">Confirm New Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400"></i>
                            </div>
                            <input type="password" id="confirm_password" name="confirm_password" 
                                   class="form-input pl-10 w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-150" 
                                   required
                                   placeholder="Confirm your new password">
                            <button type="button" class="toggle-password absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 focus:outline-none" data-target="#confirm_password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-300 flex items-center justify-center">
                        <i class="fas fa-key mr-2"></i>
                        Reset Password
                    </button>
                </form>
            <?php else: ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p class="font-medium">Invalid or Expired Link</p>
                    <p><?php echo $error; ?></p>
                    <p class="mt-4"><a href="forgot-password.php" class="text-red-700 font-semibold hover:underline">Request a new password reset</a></p>
                </div>
            <?php endif; ?>
            
            <div class="mt-6 text-center text-sm text-gray-600">
                <a href="login.php" class="text-blue-600 hover:text-blue-800 hover:underline">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Login
                </a>
            </div>
        </div>
        
        <div class="mt-8 text-center text-sm text-gray-500">
            © <?php echo date('Y'); ?> Advocate Management System. All rights reserved.
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle password visibility
    const toggleButtons = document.querySelectorAll('.toggle-password');
    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const passwordInput = document.querySelector(targetId);
            
            // Toggle password visibility
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                this.innerHTML = '<i class="fas fa-eye-slash"></i>';
            } else {
                passwordInput.type = 'password';
                this.innerHTML = '<i class="fas fa-eye"></i>';
            }
        });
    });
});
</script>

<?php
// Include footer
include_once '../includes/footer-minimal.php';
?>
 <script src="/assets/js/main.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle password visibility
        const toggleButtons = document.querySelectorAll('.toggle-password');
        toggleButtons.forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const passwordInput = document.querySelector(targetId);
                
                // Toggle password visibility
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    this.innerHTML = '<i class="fas fa-eye-slash"></i>';
                } else {
                    passwordInput.type = 'password';
                    this.innerHTML = '<i class="fas fa-eye"></i>';
                }
            });
        });
    });
    </script>