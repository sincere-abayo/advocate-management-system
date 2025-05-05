<?php
// error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Set page title
$pageTitle = "Print Invoice";

// Include required files in the correct order
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/auth.php';

// Include minimal header (without navigation)
include_once '../../../includes/header-minimal.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is an advocate
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'advocate') {
    echo "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6' role='alert'>
            <p class='font-bold'>Error</p>
            <p>You must be logged in as an advocate to view this page.</p>
          </div>";
    exit;
}


// Check if invoice ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6' role='alert'>
            <p class='font-bold'>Error</p>
            <p>Invoice ID is required.</p>
          </div>";
    exit;
}

$invoiceId = (int)$_GET['id'];

// Get database connection
$conn = getDBConnection();

// Get advocate ID
$advocateStmt = $conn->prepare("
    SELECT ap.advocate_id, u.full_name as advocate_name, u.email as advocate_email, u.phone as advocate_phone, u.address as advocate_address
    FROM advocate_profiles ap
    JOIN users u ON ap.user_id = u.user_id
    WHERE u.user_id = ?
");
$advocateStmt->bind_param("i", $_SESSION['user_id']);
$advocateStmt->execute();
$advocateResult = $advocateStmt->get_result();

if ($advocateResult->num_rows === 0) {
    echo "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6' role='alert'>
            <p class='font-bold'>Error</p>
            <p>Advocate profile not found.</p>
          </div>";
    exit;
}

$advocateData = $advocateResult->fetch_assoc();
$advocateId = $advocateData['advocate_id'];

// Get invoice details
$stmt = $conn->prepare("
    SELECT b.*, 
           c.full_name as client_name,
           c.email as client_email,
           c.phone as client_phone,
           c.address as client_address,
           cp.client_id,
           cs.case_id, 
           cs.case_number, 
           cs.title as case_title
    FROM billings b
    JOIN client_profiles cp ON b.client_id = cp.client_id
    JOIN users c ON cp.user_id = c.user_id
    LEFT JOIN cases cs ON b.case_id = cs.case_id
    WHERE b.billing_id = ? AND b.advocate_id = ?
    LIMIT 1
");

$stmt->bind_param("ii", $invoiceId, $advocateId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6' role='alert'>
            <p class='font-bold'>Error</p>
            <p>Invoice not found or you don't have permission to view it.</p>
          </div>";
    exit;
}

$invoice = $result->fetch_assoc();

// Get invoice items
$itemsStmt = $conn->prepare("
    SELECT * FROM billing_items 
    WHERE billing_id = ? 
    ORDER BY item_id ASC
");
$itemsStmt->bind_param("i", $invoiceId);
$itemsStmt->execute();
$itemsResult = $itemsStmt->get_result();

$invoiceItems = [];
while ($item = $itemsResult->fetch_assoc()) {
    $invoiceItems[] = $item;
}

// Get payment history
$paymentsStmt = $conn->prepare("
    SELECT * FROM payments 
    WHERE billing_id = ? 
    ORDER BY payment_date DESC
");
$paymentsStmt->bind_param("i", $invoiceId);
$paymentsStmt->execute();
$paymentsResult = $paymentsStmt->get_result();

$payments = [];
$totalPaid = 0;
while ($payment = $paymentsResult->fetch_assoc()) {
    $payments[] = $payment;
    $totalPaid += $payment['amount'];
}

$balanceDue = $invoice['amount'] - $totalPaid;

// Get company settings
$companyName = getSetting('company_name', 'Advocate Management System');
$companyLogo = getSetting('company_logo', '/assets/images/logo.png');
$companyAddress = getSetting('company_address', '');
$companyPhone = getSetting('company_phone', '');
$companyEmail = getSetting('company_email', '');
$companyWebsite = getSetting('company_website', '');



// Format invoice status for display
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'paid':
            return 'bg-green-100 text-green-800';
        case 'pending':
            return 'bg-yellow-100 text-yellow-800';
        case 'overdue':
            return 'bg-red-100 text-red-800';
        case 'cancelled':
            return 'bg-gray-100 text-gray-800';
        default:
            return 'bg-blue-100 text-blue-800';
    }
}

// Get formatted status text
function getStatusText($status) {
    return ucfirst($status);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo $invoice['billing_id']; ?> - <?php echo $companyName; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        @media print {
            body {
                font-size: 12pt;
                color: #000;
                background-color: #fff;
            }
            
            .no-print {
                display: none !important;
            }
            
            .print-container {
                max-width: 100%;
                margin: 0;
                padding: 0;
            }
            
            a {
                text-decoration: none;
                color: #000;
            }
            
            .page-break {
                page-break-before: always;
            }
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Print Controls -->
    <div class="no-print bg-white p-4 shadow-md fixed top-0 left-0 right-0 z-10">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-xl font-bold">Print Invoice #<?php echo $invoice['billing_id']; ?></h1>
            <div class="space-x-2">
                <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg">
                    <i class="fas fa-print mr-1"></i> Print
                </button>
                <a href="view.php?id=<?php echo $invoiceId; ?>" class="bg-gray-500 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded-lg">
                    <i class="fas fa-arrow-left mr-1"></i> Back
                </a>
            </div>
        </div>
    </div>
    
    <!-- Print Content -->
    <div class="print-container container mx-auto my-20 p-8 bg-white shadow-md">
        <!-- Invoice Header -->
        <div class="flex justify-between items-start mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">INVOICE</h1>
                <div class="mt-2">
                    <span class="inline-block px-3 py-1 text-sm font-semibold rounded-full <?php echo getStatusBadgeClass($invoice['status']); ?>">
                        <?php echo getStatusText($invoice['status']); ?>
                    </span>
                </div>
            </div>
            
            <div class="text-right">
                <div class="text-xl font-bold"><?php echo htmlspecialchars($companyName); ?></div>
                <?php if (!empty($companyAddress)): ?>
                    <div class="text-gray-600"><?php echo nl2br(htmlspecialchars($companyAddress)); ?></div>
                <?php endif; ?>
                <?php if (!empty($companyPhone)): ?>
                    <div class="text-gray-600"><?php echo htmlspecialchars($companyPhone); ?></div>
                <?php endif; ?>
                <?php if (!empty($companyEmail)): ?>
                    <div class="text-gray-600"><?php echo htmlspecialchars($companyEmail); ?></div>
                <?php endif; ?>
                <?php if (!empty($companyWebsite)): ?>
                    <div class="text-gray-600"><?php echo htmlspecialchars($companyWebsite); ?></div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Invoice Information -->
        <div class="grid grid-cols-2 gap-8 mb-8">
            <div>
                <h2 class="text-lg font-semibold text-gray-700 mb-2">Bill To:</h2>
                <div class="text-gray-800 font-medium"><?php echo htmlspecialchars($invoice['client_name']); ?></div>
                <?php if (!empty($invoice['client_address'])): ?>
                    <div class="text-gray-600"><?php echo nl2br(htmlspecialchars($invoice['client_address'])); ?></div>
                <?php endif; ?>
                <?php if (!empty($invoice['client_email'])): ?>
                    <div class="text-gray-600"><?php echo htmlspecialchars($invoice['client_email']); ?></div>
                <?php endif; ?>
                <?php if (!empty($invoice['client_phone'])): ?>
                    <div class="text-gray-600"><?php echo htmlspecialchars($invoice['client_phone']); ?></div>
                <?php endif; ?>
            </div>
            
            <div class="text-right">
                <div class="mb-2">
                    <span class="text-gray-600">Invoice Number:</span>
                    <span class="font-medium ml-2"><?php echo $invoice['billing_id']; ?></span>
                </div>
                <div class="mb-2">
                    <span class="text-gray-600">Invoice Date:</span>
                    <span class="font-medium ml-2"><?php echo date('F j, Y', strtotime($invoice['billing_date'])); ?></span>
                </div>
                <div class="mb-2">
                    <span class="text-gray-600">Due Date:</span>
                    <span class="font-medium ml-2"><?php echo date('F j, Y', strtotime($invoice['due_date'])); ?></span>
                </div>
                <?php if (!empty($invoice['case_id'])): ?>
                <div class="mb-2">
                    <span class="text-gray-600">Case Reference:</span>
                    <span class="font-medium ml-2"><?php echo htmlspecialchars($invoice['case_number']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Invoice Items -->
        <div class="mb-8">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr class="bg-gray-50">
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Description
                        </th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Quantity
                        </th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Rate
                        </th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Amount
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($invoiceItems)): ?>
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                No items found for this invoice
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($invoiceItems as $item): ?>
                            <tr>
                                <td class="px-6 py-4 text-sm text-gray-800">
                                    <?php echo htmlspecialchars($item['description']); ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-800 text-right">
                                    <?php echo number_format($item['quantity'], 2); ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-800 text-right">
                                    <?php echo formatCurrency($item['rate']); ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-800 text-right">
                                    <?php echo formatCurrency($item['amount']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="bg-gray-50">
                        <td colspan="3" class="px-6 py-4 text-sm font-medium text-gray-700 text-right">
                            Subtotal:
                        </td>
                        <td class="px-6 py-4 text-sm font-medium text-gray-800 text-right">
                            <?php echo formatCurrency($invoice['amount']); ?>
                        </td>
                    </tr>
                                       <?php if ($totalPaid > 0): ?>
                    <tr>
                        <td colspan="3" class="px-6 py-4 text-sm font-medium text-gray-700 text-right">
                            Total Paid:
                        </td>
                        <td class="px-6 py-4 text-sm font-medium text-green-600 text-right">
                            <?php echo formatCurrency($totalPaid); ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr class="bg-gray-100">
                        <td colspan="3" class="px-6 py-4 text-base font-bold text-gray-700 text-right">
                            Balance Due:
                        </td>
                        <td class="px-6 py-4 text-base font-bold text-gray-800 text-right">
                            <?php echo formatCurrency($balanceDue); ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <!-- Invoice Notes -->
        <?php if (!empty($invoice['description'])): ?>
        <div class="mb-8">
            <h3 class="text-lg font-semibold text-gray-700 mb-2">Notes</h3>
            <div class="p-4 bg-gray-50 rounded-lg text-gray-700">
                <?php echo nl2br(htmlspecialchars($invoice['description'])); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Payment Information -->
        <?php if ($invoice['status'] === 'paid'): ?>
        <div class="mb-8">
            <h3 class="text-lg font-semibold text-gray-700 mb-2">Payment Information</h3>
            <div class="p-4 bg-green-50 rounded-lg">
                <div class="text-green-700">
                    <p><span class="font-medium">Payment Status:</span> Paid in Full</p>
                    <?php if (!empty($invoice['payment_method'])): ?>
                    <p><span class="font-medium">Payment Method:</span> <?php echo htmlspecialchars(ucfirst($invoice['payment_method'])); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($invoice['payment_date'])): ?>
                    <p><span class="font-medium">Payment Date:</span> <?php echo date('F j, Y', strtotime($invoice['payment_date'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php elseif ($invoice['status'] === 'pending' || $invoice['status'] === 'overdue'): ?>
        <div class="mb-8">
            <h3 class="text-lg font-semibold text-gray-700 mb-2">Payment Instructions</h3>
            <div class="p-4 bg-blue-50 rounded-lg text-blue-700">
                <p class="font-medium">Please make payment by the due date: <?php echo date('F j, Y', strtotime($invoice['due_date'])); ?></p>
                <p class="mt-2">Payment can be made by check, bank transfer, or credit card.</p>
                
                <?php
                // Get payment instructions from settings
                $paymentInstructions = getSetting('payment_instructions', '');
                if (!empty($paymentInstructions)):
                ?>
                <div class="mt-2">
                    <?php echo nl2br(htmlspecialchars($paymentInstructions)); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Payment History -->
        <?php if (!empty($payments)): ?>
        <div class="mb-8">
            <h3 class="text-lg font-semibold text-gray-700 mb-2">Payment History</h3>
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr class="bg-gray-50">
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Date
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Method
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Notes
                        </th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Amount
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td class="px-6 py-4 text-sm text-gray-800">
                            <?php echo date('F j, Y', strtotime($payment['payment_date'])); ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-800">
                            <?php echo htmlspecialchars(ucfirst($payment['payment_method'])); ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-800">
                            <?php echo !empty($payment['notes']) ? htmlspecialchars($payment['notes']) : '-'; ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-800 text-right">
                            <?php echo formatCurrency($payment['amount']); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Footer -->
        <div class="mt-12 pt-8 border-t border-gray-200 text-center text-gray-600 text-sm">
            <p>Thank you for your business!</p>
            <p class="mt-2">If you have any questions about this invoice, please contact:</p>
            <p><?php echo htmlspecialchars($advocateData['advocate_name']); ?> | <?php echo htmlspecialchars($advocateData['advocate_email']); ?> | <?php echo htmlspecialchars($advocateData['advocate_phone']); ?></p>
        </div>
    </div>
    
    <?php
    // Close database connection
$conn->close();?>
    <script>
        // Auto-print when the page loads (optional)
        // window.onload = function() {
        //     window.print();
        // };
    </script>
</body>
</html>