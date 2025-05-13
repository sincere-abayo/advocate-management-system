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
    // Update invoice status to 'paid'
    $updateStmt = $conn->prepare("UPDATE billings SET status = 'paid', payment_date = CURRENT_DATE() WHERE billing_id = ?");
    $updateStmt->bind_param("i", $invoiceId);
    
    if (!$updateStmt->execute()) {
        throw new Exception("Failed to update invoice status: " . $conn->error);
    }
    
    // Create a payment record
    // First, get the invoice details
    $invoiceStmt = $conn->prepare("SELECT amount, client_id, advocate_id FROM billings WHERE billing_id = ?");
    $invoiceStmt->bind_param("i", $invoiceId);
    $invoiceStmt->execute();
    $invoiceResult = $invoiceStmt->get_result();
    
    if ($invoiceResult->num_rows === 0) {
        throw new Exception("Invoice not found");
    }
    
    $invoice = $invoiceResult->fetch_assoc();
    
    // Insert payment record
    $paymentStmt = $conn->prepare("
        INSERT INTO payments (
            billing_id, 
            amount, 
            payment_date, 
            payment_method, 
            notes, 
            created_by
        ) VALUES (?, ?, CURRENT_DATE(), 'Manual', 'Marked as paid by user', ?)
    ");
    
    $paymentStmt->bind_param("idi", $invoiceId, $invoice['amount'], $_SESSION['user_id']);
    
    if (!$paymentStmt->execute()) {
        throw new Exception("Failed to create payment record: " . $conn->error);
    }
    
    // Update advocate's income records
    $incomeStmt = $conn->prepare("
        UPDATE advocate_profiles 
        SET total_income_ytd = total_income_ytd + ?, 
            profit_ytd = profit_ytd + ? 
        WHERE advocate_id = ?
    ");
    
    $incomeStmt->bind_param("ddi", $invoice['amount'], $invoice['amount'], $invoice['advocate_id']);
    
    if (!$incomeStmt->execute()) {
        throw new Exception("Failed to update advocate income: " . $conn->error);
    }
    
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
            'Payment Received', 
            CONCAT('Your payment of $', ?, ' has been received and recorded.'), 
            'billing', 
            ?
        FROM client_profiles cp
        JOIN users u ON cp.user_id = u.user_id
        WHERE cp.client_id = ?
    ");
    
    $notificationStmt->bind_param("dii", $invoice['amount'], $invoiceId, $invoice['client_id']);
    $notificationStmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    // Set success message
    $_SESSION['flash_message'] = "Invoice has been marked as paid successfully.";
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
