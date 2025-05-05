<?php
// Include necessary files
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/auth.php';

// Check if user is logged in and has appropriate permissions
requireLogin();
requireUserType('advocate');

// Get invoice ID from URL
$invoiceId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($invoiceId <= 0) {
    redirectWithMessage('index.php', 'Invalid invoice ID.', 'error');
    exit;
}

// Connect to database
$conn = getDBConnection();

// Start transaction
$conn->begin_transaction();

try {
    // First, check if the invoice exists and is not already paid or canceled
    $checkStmt = $conn->prepare("SELECT status, client_id, advocate_id FROM billings WHERE billing_id = ?");
    $checkStmt->bind_param("i", $invoiceId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Invoice not found");
    }
    
    $invoice = $result->fetch_assoc();
    
    if ($invoice['status'] === 'paid') {
        throw new Exception("Cannot cancel a paid invoice");
    }
    
    if ($invoice['status'] === 'cancelled') {
        throw new Exception("This invoice is already cancelled");
    }
    
    // Update invoice status to 'cancelled'
    $updateStmt = $conn->prepare("UPDATE billings SET status = 'cancelled' WHERE billing_id = ?");
    $updateStmt->bind_param("i", $invoiceId);
    
    if (!$updateStmt->execute()) {
        throw new Exception("Failed to update invoice status: " . $conn->error);
    }
    
    // Create a note in the system about the cancellation
    $noteStmt = $conn->prepare("
        INSERT INTO case_activities (
            case_id, 
            user_id, 
            activity_type, 
            description
        ) 
        SELECT 
            case_id, 
            ?, 
            'note', 
            CONCAT('Invoice #', ?, ' has been cancelled')
        FROM billings 
        WHERE billing_id = ?
    ");
    
    $noteStmt->bind_param("iii", $_SESSION['user_id'], $invoiceId, $invoiceId);
    $noteStmt->execute();
    
    // Create notification for the client
    $notificationStmt = $conn->prepare("
        INSERT INTO notifications (
            user_id, 
            title, 
            message, 
            related_to, 
            related_id
        ) 
        SELECT 
            u.user_id, 
            'Invoice Cancelled', 
            CONCAT('Invoice #', ?, ' has been cancelled.'), 
            'billing', 
            ?
        FROM client_profiles cp
        JOIN users u ON cp.user_id = u.user_id
        WHERE cp.client_id = ?
    ");
    
    $notificationStmt->bind_param("iii", $invoiceId, $invoiceId, $invoice['client_id']);
    $notificationStmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    // Set success message
    $_SESSION['flash_message'] = "Invoice has been cancelled successfully.";
    $_SESSION['flash_type'] = "success";
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    $_SESSION['flash_message'] = "Error: " . $e->getMessage();
    $_SESSION['flash_type'] = "error";
}

// Close connection
$conn->close();

// Redirect back to invoice view
header("Location: view.php?id=" . $invoiceId);
exit;
?>