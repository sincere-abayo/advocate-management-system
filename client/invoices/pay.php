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

// Check if invoice is already paid
if ($invoice['status'] === 'paid') {
    $_SESSION['flash_message'] = "This invoice has already been paid";
    $_SESSION['flash_type'] = "info";
    header("Location: view.php?id=$invoiceId");
    exit;
}

// Get payment history
$paymentsQuery = "
    SELECT SUM(amount) as total_paid
    FROM payments
    WHERE billing_id = ?
";

$paymentsStmt = $conn->prepare($paymentsQuery);
$paymentsStmt->bind_param("i", $invoiceId);
$paymentsStmt->execute();
$paymentsResult = $paymentsStmt->get_result();
$paymentData = $paymentsResult->fetch_assoc();

// Calculate balance due
$amountPaid = $paymentData['total_paid'] ?? 0;
$balance = $invoice['amount'] - $amountPaid;

// Initialize variables
$errors = [];
$formData = [
    'payment_method' => '',
    'amount' => $balance,
    'notes' => ''
];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate payment method
    if (empty($_POST['payment_method'])) {
        $errors['payment_method'] = 'Payment method is required';
    } else {
        $formData['payment_method'] = $_POST['payment_method'];
    }
    
    // Validate amount
    if (empty($_POST['amount'])) {
        $errors['amount'] = 'Amount is required';
    } elseif (!is_numeric($_POST['amount']) || $_POST['amount'] <= 0) {
        $errors['amount'] = 'Amount must be a positive number';
    } elseif ($_POST['amount'] > $balance) {
        $errors['amount'] = 'Amount cannot exceed the balance due';
    } else {
        $formData['amount'] = (float)$_POST['amount'];
    }
    
    // Notes are optional
    $formData['notes'] = $_POST['notes'] ?? '';
    
// If no errors, process payment
if (empty($errors)) {
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Insert payment record
        $paymentStmt = $conn->prepare("
            INSERT INTO payments (
                billing_id, amount, payment_date, payment_method, notes, created_by
            ) VALUES (?, ?, CURDATE(), ?, ?, ?)
        ");
        
        $paymentStmt->bind_param(
            "idssi",
            $invoiceId,
            $formData['amount'],
            $formData['payment_method'],
            $formData['notes'],
            $_SESSION['user_id']
        );
        
        $paymentStmt->execute();
        
        // Calculate new total paid amount
        $newTotalPaid = $amountPaid + $formData['amount'];
        
        // Update invoice status if fully paid
        // Only update to 'paid' if the total paid amount equals or exceeds the invoice amount
        if ($newTotalPaid >= $invoice['amount']) {
            $updateStmt = $conn->prepare("
                UPDATE billings
                SET status = 'paid', payment_method = ?, payment_date = CURDATE()
                WHERE billing_id = ?
            ");
            
            $updateStmt->bind_param(
                "si",
                $formData['payment_method'],
                $invoiceId
            );
            
            $updateStmt->execute();
        }
        
        // Create notification for advocate
        $notificationTitle = "Payment Received";
        $notificationMessage = "A payment of " . formatCurrency($formData['amount']) . " has been made for invoice #" . str_pad($invoiceId, 5, '0', STR_PAD_LEFT);
        
        // Get advocate user_id
        $advocateUserStmt = $conn->prepare("
            SELECT u.user_id
            FROM users u
            JOIN advocate_profiles ap ON u.user_id = ap.user_id
            WHERE ap.advocate_id = ?
        ");
        
        $advocateUserStmt->bind_param("i", $invoice['advocate_id']);
        $advocateUserStmt->execute();
        $advocateUser = $advocateUserStmt->get_result()->fetch_assoc();
        
        if ($advocateUser) {
            createNotification(
                $advocateUser['user_id'],
                $notificationTitle,
                $notificationMessage,
                'invoice',
                $invoiceId
            );
        }
        
        // Commit transaction
        $conn->commit();
        
        // Set success message
        $_SESSION['flash_message'] = "Payment processed successfully";
        $_SESSION['flash_type'] = "success";
        
        // Redirect to invoice view
        header("Location: view.php?id=$invoiceId");
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $errors['general'] = "Error processing payment: " . $e->getMessage();
    }
}

}

// Close connection
$conn->close();

// Set page title
$pageTitle = "Pay Invoice #" . str_pad($invoice['billing_id'], 5, '0', STR_PAD_LEFT);
include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Pay Invoice #<?php echo str_pad($invoice['billing_id'], 5, '0', STR_PAD_LEFT); ?></h1>
            <p class="text-gray-600">
                Due: <?php echo date('F j, Y', strtotime($invoice['due_date'])); ?>
                <span class="mx-2">•</span>
                Balance: <?php echo formatCurrency($balance); ?>
            </p>
        </div>
        <div class="mt-4 md:mt-0">
            <a href="view.php?id=<?php echo $invoiceId; ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back to Invoice
            </a>
        </div>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Payment Form -->
        <div class="md:col-span-2">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Payment Information</h2>
                
                <?php if (isset($errors['general'])): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                        <p><?php echo $errors['general']; ?></p>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="paymentForm">
                    <div class="space-y-6">
                        <div>
                            <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-1">Payment Method *</label>
                            <select id="payment_method" name="payment_method" class="form-select w-full <?php echo isset($errors['payment_method']) ? 'border-red-500' : ''; ?>" required>
                                <option value="">Select Payment Method</option>
                                <option value="credit_card" <?php echo $formData['payment_method'] === 'credit_card' ? 'selected' : ''; ?>>Credit Card</option>
                                <option value="bank_transfer" <?php echo $formData['payment_method'] === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                                <option value="paypal" <?php echo $formData['payment_method'] === 'paypal' ? 'selected' : ''; ?>>PayPal</option>
                                <option value="cash" <?php echo $formData['payment_method'] === 'cash' ? 'selected' : ''; ?>>Cash</option>
                                <option value="check" <?php echo $formData['payment_method'] === 'check' ? 'selected' : ''; ?>>Check</option>
                                <option value="other" <?php echo $formData['payment_method'] === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                            <?php if (isset($errors['payment_method'])): ?>
                                <p class="text-red-500 text-sm mt-1"><?php echo $errors['payment_method']; ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <label for="amount" class="block text-sm font-medium text-gray-700 mb-1">Amount *</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 sm:text-sm">$</span>
                                </div>
                                <input type="number" id="amount" name="amount" step="0.01" min="0.01" max="<?php echo $balance; ?>" class="form-input pl-7 w-full <?php echo isset($errors['amount']) ? 'border-red-500' : ''; ?>" value="<?php echo $formData['amount']; ?>" required>
                            </div>
                            <?php if (isset($errors['amount'])): ?>
                                <p class="text-red-500 text-sm mt-1"><?php echo $errors['amount']; ?></p>
                            <?php endif; ?>
                            <p class="text-gray-500 text-sm mt-1">Balance due: <?php echo formatCurrency($balance); ?></p>
                        </div>
                        
                        <div>
                            <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes (Optional)</label>
                            <textarea id="notes" name="notes" rows="3" class="form-textarea w-full" placeholder="Add any notes about this payment"><?php echo htmlspecialchars($formData['notes']); ?></textarea>
                        </div>
                        
                        <div id="credit-card-details" class="space-y-4" style="display: none;">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="card_number" class="block text-sm font-medium text-gray-700 mb-1">Card Number *</label>
                                    <input type="text" id="card_number" class="form-input w-full" placeholder="1234 5678 9012 3456">
                                    <p class="text-gray-500 text-xs mt-1">For demonstration purposes only. No actual payment will be processed.</p>
                                </div>
                                
                                <div>
                                    <label for="card_name" class="block text-sm font-medium text-gray-700 mb-1">Name on Card *</label>
                                    <input type="text" id="card_name" class="form-input w-full" placeholder="John Doe">
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div class="col-span-1">
                                    <label for="exp_month" class="block text-sm font-medium text-gray-700 mb-1">Expiry Month *</label>
                                    <select id="exp_month" class="form-select w-full">
                                        <?php for ($i = 1; $i <= 12; $i++): ?>
                                            <option value="<?php echo sprintf('%02d', $i); ?>"><?php echo sprintf('%02d', $i); ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                
                                <div class="col-span-1">
                                    <label for="exp_year" class="block text-sm font-medium text-gray-700 mb-1">Expiry Year *</label>
                                    <select id="exp_year" class="form-select w-full">
                                        <?php for ($i = date('Y'); $i <= date('Y') + 10; $i++): ?>
                                            <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                
                                <div class="col-span-2">
                                    <label for="cvv" class="block text-sm font-medium text-gray-700 mb-1">CVV *</label>
                                    <input type="text" id="cvv" class="form-input w-full" placeholder="123" maxlength="4">
                                </div>
                            </div>
                        </div>
                        
                        <div id="bank-transfer-details" class="space-y-4" style="display: none;">
                            <div class="bg-blue-50 rounded-lg p-4">
                                <h3 class="font-medium text-blue-800 mb-2">Bank Transfer Instructions</h3>
                                <p class="text-blue-700 text-sm">Please transfer the payment to the following bank account:</p>
                                <div class="mt-2 space-y-1 text-sm">
                                    <p><span class="font-medium">Bank Name:</span> First National Bank</p>
                                    <p><span class="font-medium">Account Name:</span> Advocate Legal Services</p>
                                    <p><span class="font-medium">Account Number:</span> 1234567890</p>
                                    <p><span class="font-medium">Routing Number:</span> 987654321</p>
                                    <p><span class="font-medium">Reference:</span> INV-<?php echo str_pad($invoice['billing_id'], 5, '0', STR_PAD_LEFT); ?></p>
                                </div>
                                <p class="mt-2 text-blue-700 text-sm">Please include the invoice number as reference.</p>
                            </div>
                        </div>
                        
                        <div id="paypal-details" class="space-y-4" style="display: none;">
                            <div class="bg-blue-50 rounded-lg p-4">
                                <h3 class="font-medium text-blue-800 mb-2">PayPal Instructions</h3>
                                <p class="text-blue-700 text-sm">Please send the payment to the following PayPal account:</p>
                                <div class="mt-2 space-y-1 text-sm">
                                    <p><span class="font-medium">PayPal Email:</span> payments@advocatemanagement.com</p>
                                    <p><span class="font-medium">Reference:</span> INV-<?php echo str_pad($invoice['billing_id'], 5, '0', STR_PAD_LEFT); ?></p>
                                </div>
                                <p class="mt-2 text-blue-700 text-sm">Please include the invoice number as reference.</p>
                            </div>
                        </div>
                        
                        <div class="pt-4">
                            <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg">
                                Process Payment
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Invoice Summary -->
        <div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Invoice Summary</h2>
                
                <div class="space-y-4">
                    <div class="flex justify-between items-center pb-4 border-b border-gray-200">
                        <span class="text-gray-600">Invoice Number:</span>
                        <span class="text-gray-800 font-medium">INV-<?php echo str_pad($invoice['billing_id'], 5, '0', STR_PAD_LEFT); ?></span>
                    </div>
                    
                    <?php if (!empty($invoice['case_id'])): ?>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Related Case:</span>
                            <a href="../cases/view.php?id=<?php echo $invoice['case_id']; ?>" class="text-blue-600 hover:underline">
                                <?php echo htmlspecialchars($invoice['case_number']); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Advocate:</span>
                        <span class="text-gray-800"><?php echo htmlspecialchars($invoice['advocate_name']); ?></span>
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Invoice Date:</span>
                        <span class="text-gray-800"><?php echo date('M d, Y', strtotime($invoice['billing_date'])); ?></span>
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Due Date:</span>
                        <span class="text-gray-800"><?php echo date('M d, Y', strtotime($invoice['due_date'])); ?></span>
                    </div>
                    
                    <div class="flex justify-between items-center pb-4 border-b border-gray-200">
                        <span class="text-gray-600">Status:</span>
                        <?php
                        $statusClass = 'bg-gray-100 text-gray-800';
                        switch ($invoice['status']) {
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
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Total Amount:</span>
                        <span class="text-gray-800 font-medium"><?php echo formatCurrency($invoice['amount']); ?></span>
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Amount Paid:</span>
                        <span class="text-green-600"><?php echo formatCurrency($amountPaid); ?></span>
                    </div>
                    
                    <div class="flex justify-between items-center pt-4 border-t border-gray-200">
                        <span class="text-gray-800 font-medium">Balance Due:</span>
                        <span class="text-xl font-bold text-red-600"><?php echo formatCurrency($balance); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const paymentMethodSelect = document.getElementById('payment_method');
    const creditCardDetails = document.getElementById('credit-card-details');
    const bankTransferDetails = document.getElementById('bank-transfer-details');
    const paypalDetails = document.getElementById('paypal-details');
    
    // Show/hide payment method details based on selection
    function togglePaymentDetails() {
        const method = paymentMethodSelect.value;
        
        // Hide all payment details sections
        creditCardDetails.style.display = 'none';
        bankTransferDetails.style.display = 'none';
        paypalDetails.style.display = 'none';
        
        // Show the selected payment method details
        if (method === 'credit_card') {
            creditCardDetails.style.display = 'block';
        } else if (method === 'bank_transfer') {
            bankTransferDetails.style.display = 'block';
        } else if (method === 'paypal') {
            paypalDetails.style.display = 'block';
        }
    }
    
    // Initial toggle based on selected value
    togglePaymentDetails();
    
    // Add event listener for changes
    paymentMethodSelect.addEventListener('change', togglePaymentDetails);
    
    // Credit card input formatting
    const cardNumberInput = document.getElementById('card_number');
    if (cardNumberInput) {
        cardNumberInput.addEventListener('input', function(e) {
            // Remove non-digit characters
            let value = this.value.replace(/\D/g, '');
            
            // Add spaces after every 4 digits
            value = value.replace(/(\d{4})(?=\d)/g, '$1 ');
            
            // Limit to 19 characters (16 digits + 3 spaces)
            value = value.substring(0, 19);
            
            // Update the input value
            this.value = value;
        });
    }
    
    // Form validation
    const paymentForm = document.getElementById('paymentForm');
    paymentForm.addEventListener('submit', function(e) {
        const method = paymentMethodSelect.value;
        
        if (method === 'credit_card') {
            const cardNumber = document.getElementById('card_number').value.replace(/\s/g, '');
            const cardName = document.getElementById('card_name').value.trim();
            const cvv = document.getElementById('cvv').value.trim();
            
            if (!cardNumber || cardNumber.length < 13 || cardNumber.length > 16) {
                e.preventDefault();
                alert('Please enter a valid card number');
                return;
            }
            
            if (!cardName) {
                e.preventDefault();
                alert('Please enter the name on the card');
                return;
            }
            
            if (!cvv || cvv.length < 3 || cvv.length > 4) {
                e.preventDefault();
                alert('Please enter a valid CVV');
                return;
            }
        }
    });
});
</script>

<?php
// Include footer
include '../includes/footer.php'
?>
