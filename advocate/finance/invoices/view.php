<?php
// Set page title
$pageTitle = "View Invoice";

// Include header
include_once '../../includes/header.php';

// Get advocate ID
$advocateId = $advocateData['advocate_id'];

// Check if invoice ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['flash_message'] = "Invoice ID is required.";
    $_SESSION['flash_type'] = "error";
    header("Location:index.php");
    exit;
}

$invoiceId = (int)$_GET['id'];

// Get database connection
$conn = getDBConnection();

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
    $_SESSION['flash_message'] = "Invoice not found or you don't have permission to view it.";
    $_SESSION['flash_type'] = "error";
    header("Location:index.php");
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
while ($payment = $paymentsResult->fetch_assoc()) {
    $payments[] = $payment;
}

// Calculate total paid amount
$totalPaid = 0;
foreach ($payments as $payment) {
    $totalPaid += $payment['amount'];
}

// Calculate balance due
$balanceDue = $invoice['amount'] - $totalPaid;

// Get advocate details
$advocateStmt = $conn->prepare("
    SELECT u.full_name, u.email, u.phone, ap.* 
    FROM users u
    JOIN advocate_profiles ap ON u.user_id = ap.user_id
    WHERE ap.advocate_id = ?
    LIMIT 1
");
$advocateStmt->bind_param("i", $advocateId);
$advocateStmt->execute();
$advocateResult = $advocateStmt->get_result();
$advocate = $advocateResult->fetch_assoc();

// Close database connection
$conn->close();

// Format invoice number
$invoiceNumber = 'INV-' . str_pad($invoice['billing_id'], 5, '0', STR_PAD_LEFT);

// Determine status class
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
    case 'cancelled':
        $statusClass = 'bg-gray-100 text-gray-800';
        break;
}

// Check if invoice is overdue
$isOverdue = ($invoice['status'] === 'pending' && strtotime($invoice['due_date']) < time());
?>

<div class="mb-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Invoice <?php echo $invoiceNumber; ?></h1>
            <p class="text-gray-600">
                <?php echo formatDate($invoice['billing_date']); ?> | 
                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                    <?php echo ucfirst($invoice['status']); ?>
                    <?php if ($isOverdue): ?>
                        <span class="ml-1">(Overdue)</span>
                    <?php endif; ?>
                </span>
            </p>
        </div>
        
        <div class="mt-4 md:mt-0 flex flex-wrap gap-2">
            <a href="edit.php?id=<?php echo $invoiceId; ?>" class="btn-secondary">
                <i class="fas fa-edit mr-1"></i> Edit
            </a>
            
            <a href="print.php?id=<?php echo $invoiceId; ?>" class="btn-secondary" target="_blank">
                <i class="fas fa-print mr-1"></i> Print
            </a>
            
            <a href="download.php?id=<?php echo $invoiceId; ?>" class="btn-secondary">
                <i class="fas fa-download mr-1"></i> Download PDF
            </a>
            
            <a href="email.php?id=<?php echo $invoiceId; ?>" class="hidden btn-secondary">
                <i class="fas fa-envelope mr-1"></i> Email
            </a>
            
            <?php if ($invoice['status'] === 'pending'): ?>
                <a href="mark-paid.php?id=<?php echo $invoiceId; ?>" class="btn-primary">
                    <i class="fas fa-check-circle mr-1"></i> Mark as Paid
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Invoice Summary -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <!-- Invoice Header -->
            <div class="p-6 border-b border-gray-200">
                <div class="flex flex-col md:flex-row justify-between">
                    <!-- Advocate Info -->
                    <div class="mb-4 md:mb-0">
                        <h3 class="text-lg font-semibold text-gray-800">From</h3>
                        <p class="text-gray-700"><?php echo htmlspecialchars($advocate['full_name']); ?></p>
                        <p class="text-gray-600"><?php echo htmlspecialchars($advocate['email']); ?></p>
                        <p class="text-gray-600"><?php echo htmlspecialchars($advocate['phone'] ?? 'N/A'); ?></p>
                    </div>
                    
                    <!-- Client Info -->
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">To</h3>
                        <p class="text-gray-700"><?php echo htmlspecialchars($invoice['client_name']); ?></p>
                        <p class="text-gray-600"><?php echo htmlspecialchars($invoice['client_email']); ?></p>
                        <p class="text-gray-600"><?php echo htmlspecialchars($invoice['client_phone'] ?? 'N/A'); ?></p>
                        <?php if (!empty($invoice['client_address'])): ?>
                            <p class="text-gray-600"><?php echo nl2br(htmlspecialchars($invoice['client_address'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Invoice Details -->
            <div class="p-6 border-b border-gray-200">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Invoice Number</p>
                        <p class="font-medium"><?php echo $invoiceNumber; ?></p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-500">Invoice Date</p>
                        <p class="font-medium"><?php echo formatDate($invoice['billing_date']); ?></p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-500">Due Date</p>
                        <p class="font-medium"><?php echo formatDate($invoice['due_date']); ?></p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-500">Amount Due</p>
                        <p class="font-medium"><?php echo formatCurrency($balanceDue); ?></p>
                    </div>
                </div>
                
                <?php if (!empty($invoice['case_id'])): ?>
                    <div class="mt-4 p-3 bg-blue-50 rounded-lg">
                        <p class="text-sm text-gray-500">Related Case</p>
                        <p class="font-medium">
                            <a href="../cases/view.php?id=<?php echo $invoice['case_id']; ?>" class="text-blue-600 hover:underline">
                                <?php echo htmlspecialchars($invoice['case_number'] . ' - ' . $invoice['case_title']); ?>
                            </a>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Invoice Items -->
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Invoice Items</h3>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
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
                                    <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">
                                        <div class="flex flex-col items-center justify-center py-4">
                                            <i class="fas fa-receipt text-gray-400 text-3xl mb-2"></i>
                                            <p>No itemized details available for this invoice</p>
                                        </div>
                                        <div class="mt-2">
                                            <p class="font-medium"><?php echo htmlspecialchars($invoice['description']); ?></p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($invoiceItems as $item): ?>
                                    <tr>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            <?php echo htmlspecialchars($item['description']); ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500 text-right">
                                            <?php echo number_format($item['quantity'], 2); ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500 text-right">
                                            <?php echo formatCurrency($item['rate']); ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900 text-right font-medium">
                                            <?php echo formatCurrency($item['amount']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td colspan="3" class="px-6 py-4 text-sm font-medium text-gray-900 text-right">
                                    Total
                                </td>
                                <td class="px-6 py-4 text-sm font-bold text-gray-900 text-right">
                                    <?php echo formatCurrency($invoice['amount']); ?>
                                </td>
                            </tr>
                            <?php if ($totalPaid > 0): ?>
                                <tr>
                                    <td colspan="3" class="px-6 py-4 text-sm font-medium text-gray-900 text-right">
                                        Paid
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium text-green-600 text-right">
                                        <?php echo formatCurrency($totalPaid); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="px-6 py-4 text-sm font-medium text-gray-900 text-right">
                                        Balance Due
                                    </td>
                                    <td class="px-6 py-4 text-sm font-bold text-gray-900 text-right">
                                        <?php echo formatCurrency($balanceDue); ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tfoot>
                    </table>
                </div>
                
                   <?php if (!empty($invoice['description'])): ?>
                    <div class="mt-6">
                        <h4 class="text-sm font-medium text-gray-700 mb-2">Additional Information</h4>
                        <div class="p-4 bg-gray-50 rounded-lg text-sm text-gray-700">
                            <?php echo nl2br(htmlspecialchars($invoice['description'])); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Payment Information -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Payment Summary</h3>
                
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Subtotal:</span>
                        <span class="font-medium"><?php echo formatCurrency($invoice['amount']); ?></span>
                    </div>
                    
                    <?php if ($totalPaid > 0): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Paid:</span>
                            <span class="font-medium text-green-600"><?php echo formatCurrency($totalPaid); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="flex justify-between pt-3 border-t border-gray-200">
                        <span class="text-gray-800 font-medium">Balance Due:</span>
                        <span class="font-bold text-lg <?php echo $balanceDue > 0 ? 'text-red-600' : 'text-green-600'; ?>">
                            <?php echo formatCurrency($balanceDue); ?>
                        </span>
                    </div>
                </div>
                
                <?php if ($invoice['status'] === 'pending' && $balanceDue > 0): ?>
                    <div class="mt-6">
                        <a href="record-payment.php?id=<?php echo $invoiceId; ?>" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg inline-block text-center">
                            <i class="fas fa-plus-circle mr-1"></i> Record Payment
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Payment History -->
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Payment History</h3>
                
                <?php if (empty($payments)): ?>
                    <div class="text-center py-4">
                        <div class="text-gray-400 mb-2"><i class="fas fa-money-bill-wave text-3xl"></i></div>
                        <p class="text-gray-500">No payments recorded yet</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($payments as $payment): ?>
                            <div class="border-l-4 border-green-500 pl-4 py-2">
                                <div class="flex justify-between">
                                    <p class="font-medium"><?php echo formatCurrency($payment['amount']); ?></p>
                                    <span class="text-sm text-gray-500"><?php echo formatDate($payment['payment_date']); ?></span>
                                </div>
                                <p class="text-sm text-gray-600">
                                    Method: <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                                </p>
                                <?php if (!empty($payment['notes'])): ?>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <?php echo htmlspecialchars($payment['notes']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Actions and Notes -->
<div class="grid grid-cols-1 gap-6 mb-6">
    <!-- Invoice Actions -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Invoice Actions</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <?php if ($invoice['status'] === 'pending'): ?>
                <a href="mark-paid.php?id=<?php echo $invoiceId; ?>" class="flex flex-col items-center justify-center p-4 bg-green-50 rounded-lg hover:bg-green-100 transition duration-200">
                    <div class="text-green-500 mb-2"><i class="fas fa-check-circle text-2xl"></i></div>
                    <span class="text-sm font-medium text-gray-700">Mark as Paid</span>
                </a>
                
                <?php if (!$isOverdue): ?>
                    <a href="mark-overdue.php?id=<?php echo $invoiceId; ?>" class="flex flex-col items-center justify-center p-4 bg-yellow-50 rounded-lg hover:bg-yellow-100 transition duration-200">
                        <div class="text-yellow-500 mb-2"><i class="fas fa-exclamation-circle text-2xl"></i></div>
                        <span class="text-sm font-medium text-gray-700">Mark as Overdue</span>
                    </a>
                <?php endif; ?>
                
                <a href="send-reminder.php?id=<?php echo $invoiceId; ?>" class="flex flex-col items-center justify-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition duration-200">
                    <div class="text-blue-500 mb-2"><i class="fas fa-bell text-2xl"></i></div>
                    <span class="text-sm font-medium text-gray-700">Send Reminder</span>
                </a>
            <?php endif; ?>
            
            <?php if ($invoice['status'] !== 'cancelled'): ?>
                <a href="cancel.php?id=<?php echo $invoiceId; ?>" class="flex flex-col items-center justify-center p-4 bg-red-50 rounded-lg hover:bg-red-100 transition duration-200" onclick="return confirm('Are you sure you want to cancel this invoice?');">
                    <div class="text-red-500 mb-2"><i class="fas fa-ban text-2xl"></i></div>
                    <span class="text-sm font-medium text-gray-700">Cancel Invoice</span>
                </a>
            <?php endif; ?>
            
            <a href="duplicate.php?id=<?php echo $invoiceId; ?>" class="flex flex-col items-center justify-center p-4 bg-purple-50 rounded-lg hover:bg-purple-100 transition duration-200">
                <div class="text-purple-500 mb-2"><i class="fas fa-copy text-2xl"></i></div>
                <span class="text-sm font-medium text-gray-700">Duplicate Invoice</span>
            </a>
            
            <a href="print.php?id=<?php echo $invoiceId; ?>" target="_blank" class="flex flex-col items-center justify-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition duration-200">
                <div class="text-gray-500 mb-2"><i class="fas fa-print text-2xl"></i></div>
                <span class="text-sm font-medium text-gray-700">Print Invoice</span>
            </a>
            
            <a href="download.php?id=<?php echo $invoiceId; ?>" class="flex flex-col items-center justify-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition duration-200">
                <div class="text-gray-500 mb-2"><i class="fas fa-download text-2xl"></i></div>
                <span class="text-sm font-medium text-gray-700">Download PDF</span>
            </a>
            
            <a href="email.php?id=<?php echo $invoiceId; ?>" class="hidden flex flex-col items-center justify-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition duration-200">
                <div class="text-gray-500 mb-2"><i class="fas fa-envelope text-2xl"></i></div>
                <span class="text-sm font-medium text-gray-700">Email to Client</span>
            </a>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../../includes/footer.php';
?>