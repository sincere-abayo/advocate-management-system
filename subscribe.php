<?php
// Include required files
require_once 'config/database.php';
require_once 'includes/functions.php';

// Start session
session_start();

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Initialize variables
    $email = '';
    $errors = [];
    
    // Validate email
    if (empty($_POST['email'])) {
        $errors['email'] = 'Email address is required';
    } elseif (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address';
    } else {
        $email = sanitizeInput($_POST['email']);
    }
    
    // If no errors, process subscription
    if (empty($errors)) {
        // Connect to database
        $conn = getDBConnection();
        
        // Check if email already exists
        $stmt = $conn->prepare("SELECT email FROM newsletter_subscribers WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Email already subscribed
            $_SESSION['flash_message'] = "This email is already subscribed to our newsletter.";
            $_SESSION['flash_type'] = "info";
        } else {
            // Add new subscriber
            $stmt = $conn->prepare("INSERT INTO newsletter_subscribers (email, status, subscription_date) VALUES (?, 'active', NOW())");
            $stmt->bind_param("s", $email);
            
            if ($stmt->execute()) {
                // Success
                $_SESSION['flash_message'] = "Thank you for subscribing to our newsletter!";
                $_SESSION['flash_type'] = "success";
                
                // Send confirmation email
                $to = $email;
                $subject = "Welcome to Advocate Management System Newsletter";
                $message = "Dear Subscriber,\n\n";
                $message .= "Thank you for subscribing to our newsletter. You'll now receive updates on new features, legal technology tips, and industry insights.\n\n";
                $message .= "If you didn't subscribe to this newsletter, please click the link below to unsubscribe:\n";
                $message .= "https://advocatemanagement.com/unsubscribe.php?email=" . urlencode($email) . "&token=" . md5($email . 'salt') . "\n\n";
                $message .= "Best regards,\nThe Advocate Management Team";
                $headers = "From: newsletter@advocatemanagement.com";
                
                // Uncomment to enable email sending in production
                // mail($to, $subject, $message, $headers);
            } else {
                // Database error
                $_SESSION['flash_message'] = "Sorry, there was an error processing your subscription. Please try again.";
                $_SESSION['flash_type'] = "error";
            }
        }
        
        $stmt->close();
        $conn->close();
    } else {
        // Set error message
        $_SESSION['flash_message'] = $errors['email'];
        $_SESSION['flash_type'] = "error";
    }
    
    // Redirect back to the page they came from
    $redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
    header("Location: $redirect");
    exit;
}

// If someone navigates directly to this page, redirect to home
header("Location: index.php");
exit;
?>