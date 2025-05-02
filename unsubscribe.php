<?php
// Include required files
require_once 'config/database.php';
require_once 'includes/functions.php';

// Start session
session_start();

// Check if email and token are provided
if (isset($_GET['email']) && isset($_GET['token'])) {
    $email = sanitizeInput($_GET['email']);
    $token = sanitizeInput($_GET['token']);
    
    // Verify token
    $expected_token = md5($email . 'salt');
    
    if ($token === $expected_token) {
        // Token is valid, process unsubscription
        $conn = getDBConnection();
        
        // Update subscriber status
        $stmt = $conn->prepare("UPDATE newsletter_subscribers SET status = 'unsubscribed', unsubscription_date = NOW() WHERE email = ?");
        $stmt->bind_param("s", $email);
        
        if ($stmt->execute()) {
            $_SESSION['flash_message'] = "You have been successfully unsubscribed from our newsletter.";
            $_SESSION['flash_type'] = "success";
        } else {
            $_SESSION['flash_message'] = "There was an error processing your request. Please try again.";
            $_SESSION['flash_type'] = "error";
        }
        
        $stmt->close();
        $conn->close();
    } else {
        // Invalid token
        $_SESSION['flash_message'] = "Invalid unsubscribe link. Please contact support if you need assistance.";
        $_SESSION['flash_type'] = "error";
    }
} else {
    // Missing parameters
    $_SESSION['flash_message'] = "Invalid unsubscribe link. Please contact support if you need assistance.";
    $_SESSION['flash_type'] = "error";
}

// Include header
$pageTitle = "Unsubscribe";
include 'includes/header.php';
?>

<!-- Unsubscribe Page -->
<section class="py-16 bg-gray-50">
    <div class="container mx-auto px-4">
        <div class="max-w-2xl mx-auto bg-white p-8 rounded-lg shadow-md">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-800 mb-4">Newsletter Unsubscribe</h1>
            </div>
            
            <?php displayFlashMessage(); ?>
            
            <div class="text-center mt-8">
                <p class="text-gray-600 mb-6">If you'd like to resubscribe in the future, you can do so from our website.</p>
                <a href="index.php" class="inline-block bg-blue-600 text-white hover:bg-blue-700 font-semibold py-2 px-6 rounded-lg transition duration-300">
                    Return to Homepage
                </a>
            </div>
        </div>
    </div>
</section>

<?php
// Include footer
include 'includes/footer.php';
?>