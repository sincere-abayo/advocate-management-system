<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user is logged in and is a client
requireLogin();
requireUserType('client');

// Get client ID from session
$clientId = $_SESSION['client_id'];

// Check if invoice ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['flash_message'] = "Invalid invoice ID";
    $_SESSION['flash_type'] = "error";
    header("Location: index.php");
    exit;
}

$invoiceId = (int)$_GET['id'];

// Connect to database
$conn = getDBConnection();

// Get invoice details
$query = "
    SELECT 
        b.*,
        u.full_name as advocate_name,
        u.email as advocate_email,
        u.phone as advocate_phone,
        c.title as case_title,
        c.case_number,
        c.case_id
    FROM billings b
    JOIN advocate_profiles ap ON b.advocate_id = ap.advocate_id
    JOIN users u ON ap.user_id = u.user_id
    LEFT JOIN cases c ON b.case_id = c.case_id
    WHERE b.billing_id = ? AND b.client_id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $invoiceId, $clientId);
$stmt->execute();
$result = $stmt->get_result();

// Check if invoice exists and belongs to the client
if ($result->num_rows === 0) {
    $_SESSION['flash_message'] = "Invoice not found or you don't have permission to view it";
    $_SESSION['flash_type'] = "error";
    header("Location: index.php");
    exit;
}

$invoice = $result->fetch_assoc();

// Get invoice items
$itemsQuery = "
    SELECT * FROM billing_items
    WHERE billing_id = ?
    ORDER BY item_id
";

$itemsStmt = $conn->prepare($itemsQuery);
$itemsStmt->bind_param("i", $invoiceId);
$itemsStmt->execute();
$itemsResult = $itemsStmt->get_result();

$items = [];
while ($item = $itemsResult->fetch_assoc()) {
    $items[] = $item;
}

// Get payment history
$paymentsQuery = "
    SELECT 
        p.*,
        u.full_name as created_by_name
    FROM payments p
    JOIN users u ON p.created_by = u.user_id
    WHERE p.billing_id = ?
    ORDER BY p.payment_date DESC
";

$paymentsStmt = $conn->prepare($paymentsQuery);
$paymentsStmt->bind_param("i", $invoiceId);
$paymentsStmt->execute();
$paymentsResult = $paymentsStmt->get_result();

$payments = [];
while ($payment = $paymentsResult->fetch_assoc()) {
    $payments[] = $payment;
}

// Calculate amount paid and balance
$amountPaid = 0;
foreach ($payments as $payment) {
    $amountPaid += $payment['amount'];
}
$balance = $invoice['amount'] - $amountPaid;

// Close connection
$conn->close();

// Set page title
$pageTitle = "Invoice #" . str_pad($invoice['billing_id'], 5, '0', STR_PAD_LEFT);
include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Invoice #<?php echo str_pad($invoice['billing_id'], 5, '0', STR_PAD_LEFT); ?></h1>
            <p class="text-gray-600">
                <?php echo date('F j, Y', strtotime($invoice['billing_date'])); ?>
                <span class="mx-2">•</span>
                <?php
                $statusClass = 'bg-gray-100 text-gray-800';
                switch ($invoice['status']) {
                    case 'paid':
                        $statusClass = 'bg-green-100 text-green-800';
                        break;
                    case 'pending':
                        $statusClass = 'bg-yellow-100 text-yellow-800';
                        break;
                    case 'overdue':
                        $statusClass = 'bg-red-100 text-red-800';
                        break;
                }
                ?>
                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                    <?php echo ucfirst($invoice['status']); ?>
                </span>
            </p>
        </div>
        <div class="mt-4 md:mt-0 flex space-x-2">
            <a href="index.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back to Invoices
            </a>
            <?php if ($invoice['status'] === 'pending' || $invoice['status'] === 'overdue'): ?>
                <a href="pay.php?id=<?php echo $invoiceId; ?>" class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                    <i class="fas fa-credit-card mr-2"></i> Pay Now
                </a>
            <?php endif; ?>
            <a href="print.php?id=<?php echo $invoiceId; ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-print mr-2"></i> Print
            </a>
        </div>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <!-- Invoice Information -->
        <div class="bg-white rounded-lg shadow-md p-6 md:col-span-2">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Invoice From</h3>
                    <p class="text-gray-700 font-medium"><?php echo htmlspecialchars($invoice['advocate_name']); ?></p>
                    <?php if (!empty($invoice['advocate_email'])): ?>
                        <p class="text-gray-600"><?php echo htmlspecialchars($invoice['advocate_email']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($invoice['advocate_phone'])): ?>
                        <p class="text-gray-600"><?php echo htmlspecialchars($invoice['advocate_phone']); ?></p>
                    <?php endif; ?>
                </div>
                
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Invoice Details</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Invoice Number:</span>
                            <span class="text-gray-800 font-medium">INV-<?php echo str_pad($invoice['billing_id'], 5, '0', STR_PAD_LEFT); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Invoice Date:</span>
                            <span class="text-gray-800"><?php echo date('F j, Y', strtotime($invoice['billing_date'])); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Due Date:</span>
                            <span class="text-gray-800"><?php echo date('F j, Y', strtotime($invoice['due_date'])); ?></span>
                        </div>
                        <?php if (!empty($invoice['case_id'])): ?>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Related Case:</span>
                                <a href="../cases/view.php?id=<?php echo $invoice['case_id']; ?>" class="text-blue-600 hover:underline">
                                    <?php echo htmlspecialchars($invoice['case_number']); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($invoice['description'])): ?>
                <div class="mt-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Description</h3>
                    <div class="bg-gray-50 rounded-lg p-4 text-gray-700">
                        <?php echo nl2br(htmlspecialchars($invoice['description'])); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Payment Summary -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Payment Summary</h3>
            <div class="space-y-4">
                <div class="flex justify-between items-center pb-4 border-b border-gray-200">
                    <span class="text-gray-600">Total Amount:</span>
                    <span class="text-xl font-bold text-gray-800"><?php echo formatCurrency($invoice['amount']); ?></span>
                </div>
                
                <div class="flex justify-between items-center">
                    <span class="text-gray-600">Amount Paid:</span>
                    <span class="text-green-600 font-medium"><?php echo formatCurrency($amountPaid); ?></span>
                </div>
                
                <div class="flex justify-between items-center pb-4 border-b border-gray-200">
                    <span class="text-gray-600">Balance Due:</span>
                    <span class="<?php echo $balance > 0 ? 'text-red-600' : 'text-green-600'; ?> font-medium"><?php echo formatCurrency($balance); ?></span>
                </div>
                
                <div class="flex justify-between items-center">
                    <span class="text-gray-600">Status:</span>
                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                        <?php echo ucfirst($invoice['status']); ?>
                    </span>
                </div>
                
                <?php if ($invoice['status'] === 'paid'): ?>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Payment Method:</span>
                        <span class="text-gray-800"><?php echo ucfirst(str_replace('_', ' ', $invoice['payment_method'])); ?></span>
                    </div>
                    
                    <?php if (!empty($invoice['payment_date'])): ?>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Payment Date:</span>
                            <span class="text-gray-800"><?php echo date('F j, Y', strtotime($invoice['payment_date'])); ?></span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <?php if ($invoice['status'] === 'pending' || $invoice['status'] === 'overdue'): ?>
                <div class="mt-6">
                    <a href="pay.php?id=<?php echo $invoiceId; ?>" class="w-full bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center justify-center">
                        <i class="fas fa-credit-card mr-2"></i> Pay Now
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Invoice Items -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">Invoice Items</h3>
        </div>
        
        <?php if (empty($items)): ?>
            <div class="p-6 text-center text-gray-500">
                No detailed items available for this invoice.
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Rate</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($item['description']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    <?php echo number_format($item['quantity'], 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    <?php echo formatCurrency($item['rate']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 text-right">
                                    <?php echo formatCurrency($item['amount']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="bg-gray-50">
                            <td colspan="3" class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 text-right">
                                Total:
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900 text-right">
                                <?php echo formatCurrency($invoice['amount']); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Payment History -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">Payment History</h3>
        </div>
        
        <?php if (empty($payments)): ?>
            <div class="p-6 text-center text-gray-500">
                No payment records found for this invoice.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Date</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo date('M d, Y', strtotime($payment['payment_date'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <?php echo !empty($payment['notes']) ? htmlspecialchars($payment['notes']) : '<span class="text-gray-400">No notes</span>'; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-green-600 text-right">
                                    <?php echo formatCurrency($payment['amount']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Include footer
include '../includes/footer.php';
?>
