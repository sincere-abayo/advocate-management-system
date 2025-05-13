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
    // First, get the original invoice details
    $invoiceStmt = $conn->prepare("
        SELECT 
            case_id, 
            client_id, 
            advocate_id, 
            amount, 
            description, 
            billing_date, 
            due_date 
        FROM billings 
        WHERE billing_id = ?
    ");
    
    $invoiceStmt->bind_param("i", $invoiceId);
    $invoiceStmt->execute();
    $invoiceResult = $invoiceStmt->get_result();
    
    if ($invoiceResult->num_rows === 0) {
        throw new Exception("Original invoice not found");
    }
    
    $originalInvoice = $invoiceResult->fetch_assoc();
    
    // Set new dates (current date for billing_date, +30 days for due_date)
    $currentDate = date('Y-m-d');
    $dueDate = date('Y-m-d', strtotime('+30 days'));
    
    // Create the duplicate invoice
    $duplicateStmt = $conn->prepare("
        INSERT INTO billings (
            case_id, 
            client_id, 
            advocate_id, 
            amount, 
            description, 
            billing_date, 
            due_date, 
            status
        ) VALUES (?, ?, ?, ?, CONCAT(?, ' (Duplicate)'), ?, ?, 'pending')
    ");
    
    $duplicateStmt->bind_param(
        "iiidsss", 
        $originalInvoice['case_id'], 
        $originalInvoice['client_id'], 
        $originalInvoice['advocate_id'], 
        $originalInvoice['amount'], 
        $originalInvoice['description'], 
        $currentDate, 
        $dueDate
    );
    
    if (!$duplicateStmt->execute()) {
        throw new Exception("Failed to create duplicate invoice: " . $conn->error);
    }
    
    $newInvoiceId = $conn->insert_id;
    
    // Now duplicate all the billing items
    $itemsStmt = $conn->prepare("
        SELECT 
            description, 
            quantity, 
            rate, 
            amount 
        FROM billing_items 
        WHERE billing_id = ?
    ");
    
    $itemsStmt->bind_param("i", $invoiceId);
    $itemsStmt->execute();
    $itemsResult = $itemsStmt->get_result();
    
    // Prepare statement for inserting items
    $insertItemStmt = $conn->prepare("
        INSERT INTO billing_items (
            billing_id, 
            description, 
            quantity, 
            rate, 
            amount
        ) VALUES (?, ?, ?, ?, ?)
    ");
    
    // Insert each item
    while ($item = $itemsResult->fetch_assoc()) {
        $insertItemStmt->bind_param(
            "isddd", 
            $newInvoiceId, 
            $item['description'], 
            $item['quantity'], 
            $item['rate'], 
            $item['amount']
        );
        
        if (!$insertItemStmt->execute()) {
            throw new Exception("Failed to duplicate invoice items: " . $conn->error);
        }
    }
    
    // Create a case activity note about the duplication
    $noteStmt = $conn->prepare("
        INSERT INTO case_activities (
            case_id, 
            user_id, 
            activity_type, 
            description
        ) VALUES (?, ?, 'note', ?)
    ");
    
    $noteText = "Invoice #" . $invoiceId . " has been duplicated as Invoice #" . $newInvoiceId;
    $noteStmt->bind_param("iis", $originalInvoice['case_id'], $_SESSION['user_id'], $noteText);
    $noteStmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    // Set success message
    $_SESSION['flash_message'] = "Invoice has been duplicated successfully.";
    $_SESSION['flash_type'] = "success";
    
    // Redirect to the new invoice
    header("Location: view.php?id=" . $newInvoiceId);
    exit;
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    $_SESSION['flash_message'] = "Error: " . $e->getMessage();
    $_SESSION['flash_type'] = "error";
    
    // Redirect back to original invoice
    header("Location: view.php?id=" . $invoiceId);
    exit;
}

// Close connection
$conn->close();
?>