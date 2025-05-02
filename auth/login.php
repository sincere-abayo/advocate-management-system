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
    // Redirect based on user type
    switch ($_SESSION['user_type']) {
        case 'admin':
            header("Location: ../admin/index.php");
            break;
        case 'advocate':
            header("Location: ../advocate/index.php");
            break;
        case 'client':
            header("Location: ../client/index.php");
            break;
        default:
            header("Location: ../index.php");
    }
    exit;
}


$error = '';

// Process login form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        $result = loginUser($username, $password);
        
        if ($result['success']) {
            // Redirect based on user type
            switch ($_SESSION['user_type']) {
                case 'admin':
                    header("Location: ../admin/index.php");
                    break;
                case 'advocate':
                    header("Location: ../advocate/index.php");
                    break;
                case 'client':
                    header("Location: ../client/index.php");
                    break;
                default:
                    header("Location: ../index.php");
            }
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

// Set page title for header
$pageTitle = "Login - Advocate Management System";

// Include header (without navigation for login page)
include_once '../includes/header-minimal.php';
?>

<div class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-blue-800">Advocate Management System</h1>
            <p class="text-gray-600">Sign in to your account</p>
        </div>
        
        <div class="bg-white rounded-lg shadow-lg p-8">
            <?php if (!empty($error)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p><?php echo $error; ?></p>
                </div>
            <?php endif; ?>
               <form method="POST" action="" data-validate class="space-y-6">
                <div class="form-group">
                    <label for="username" class="block text-gray-700 font-medium mb-2">Username or Email</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-user text-gray-400"></i>
                        </div>
                        <input type="text" id="username" name="username" 
                               class="form-input pl-10 w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-150" 
                               required 
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                               placeholder="Enter your username or email">
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="flex items-center justify-between mb-2">
                        <label for="password" class="block text-gray-700 font-medium">Password</label>
                        <a href="forgot-password.php" class="text-sm text-blue-600 hover:text-blue-800 hover:underline">Forgot password?</a>
                    </div>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-lock text-gray-400"></i>
                        </div>
                        <input type="password" id="password" name="password" 
                               class="form-input pl-10 w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-150" 
                               required
                               placeholder="Enter your password">
                        <button type="button" class="toggle-password absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 focus:outline-none" data-target="#password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="flex items-center">
                    <input type="checkbox" id="remember" name="remember" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <label for="remember" class="ml-2 text-sm text-gray-600">Remember me for 30 days</label>
                </div>
                
                <button type="submit" class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-300 flex items-center justify-center">
                    <i class="fas fa-sign-in-alt mr-2"></i>
                    Sign In
                </button>
            </form>
            
           
            
            <div class="mt-6 text-center text-sm text-gray-600">
                Don't have an account? <a href="register.php" class="text-blue-600 hover:underline">Register here</a>
            </div>
        </div>
        
        <div class="mt-8 text-center text-sm text-gray-500">
            © <?php echo date('Y'); ?> Advocate Management System. All rights reserved.
        </div>
    </div>
</div>

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