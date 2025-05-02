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

// Process forgot password form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitizeInput($_POST['email']);
    
    if (empty($email)) {
        $error = 'Please enter your email address';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        $conn = getDBConnection();
        
        // Check if email exists
        $stmt = $conn->prepare("SELECT user_id, full_name FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            // Show error if email doesn't exist (not recommended for security)
            $error = "No account found with this email address. Please check your email or register for an account.";
        } else {
            $user = $result->fetch_assoc();
            
            // Generate reset token
            $resetToken = bin2hex(random_bytes(32));
            $tokenExpiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store reset token in database
            $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE user_id = ?");
            $stmt->bind_param("ssi", $resetToken, $tokenExpiry, $user['user_id']);
            
            if ($stmt->execute()) {
                // Send password reset email
                $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/utb/advocate-management-system/auth/reset-password.php?token=" . $resetToken;
                $to = $email;
                $subject = "Password Reset - Advocate Management System";
                $message = "Dear " . $user['full_name'] . ",\n\n";
                $message .= "We received a request to reset your password. Please click the link below to reset your password:\n\n";
                $message .= $resetLink . "\n\n";
                $message .= "This link will expire in 1 hour.\n\n";
                $message .= "If you did not request a password reset, please ignore this email.\n\n";
                $message .= "Best regards,\nThe Advocate Management Team";
                $headers = "From: noreply@advocatemanagement.com";
                
                // Uncomment to enable email sending in production
                // mail($to, $subject, $message, $headers);
                
                // For development, display the reset link
                if ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1') {
                    $_SESSION['dev_reset_link'] = $resetLink;
                }
                
                $success = true;
            } else {
                $error = "An error occurred. Please try again.";
            }
        }
        
        $stmt->close();
        $conn->close();
    }
}

// Set page title for header
$pageTitle = "Forgot Password - Advocate Management System";

// Include header (without navigation for login page)
include_once '../includes/header-minimal.php';
?>

<div class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-blue-800">Advocate Management System</h1>
            <p class="text-gray-600">Reset Your Password</p>
        </div>
        
        <div class="bg-white rounded-lg shadow-lg p-8">
            <?php if ($success): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                    <p class="font-medium">Password Reset Email Sent</p>
                    <!-- <p>If an account exists with the email address you provided, we've sent instructions to reset your password. Please check your inbox and follow the instructions.</p> -->
                    
                    <?php if (isset($_SESSION['dev_reset_link'])): ?>
                        <div class="mt-4 p-3 bg-gray-100 rounded text-sm">
                            <p class="font-medium">Development Mode: Reset Link</p>
                            <a href="<?php echo $_SESSION['dev_reset_link']; ?>" target="_blank" class="text-blue-600 break-all"><?php echo $_SESSION['dev_reset_link']; ?></a>
                        </div>
                        <?php unset($_SESSION['dev_reset_link']); ?>
                    <?php endif; ?>
                    
                    <p class="mt-4"><a href="login.php" class="text-green-700 font-semibold hover:underline">Return to Login</a></p>
                </div>
            <?php else: ?>
                <?php if (!empty($error)): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                        <p><?php echo $error; ?></p>
                    </div>
                <?php endif; ?>
                
                <p class="text-gray-600 mb-6">Enter your email address below and we'll send you instructions to reset your password.</p>
                
                <form method="POST" action="" data-validate class="space-y-6">
                    <div>
                        <label for="email" class="block text-gray-700 font-medium mb-2">Email Address</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-envelope text-gray-400"></i>
                            </div>
                            <input type="email" id="email" name="email" 
                                   class="form-input pl-10 w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-150" 
                                   required 
                                   placeholder="Enter your email address"
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                    </div>
                    
                    <button type="submit" class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-300 flex items-center justify-center">
                        <i class="fas fa-paper-plane mr-2"></i>
                        Send Reset Instructions
                    </button>
                </form>
                
                <div class="mt-6 text-center text-sm text-gray-600">
                    <a href="/auth/login.php" class="text-blue-600 hover:text-blue-800 hover:underline">
                        <i class="fas fa-arrow-left mr-1"></i> Back to Login
                    </a>
                </div>
            <?php endif; ?>
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