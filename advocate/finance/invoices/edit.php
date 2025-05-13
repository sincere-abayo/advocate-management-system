<?php
// Set page title
$pageTitle = "Edit Invoice";

// Include header
include_once '../../includes/header.php';

// Get advocate ID
$advocateId = $advocateData['advocate_id'];

// Check if invoice ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['flash_message'] = "Invoice ID is required.";
    $_SESSION['flash_type'] = "error";
    header("Location: index.php");
    exit;
}

$invoiceId = (int)$_GET['id'];

// Get database connection
$conn = getDBConnection();

// Get invoice details
$stmt = $conn->prepare("
    SELECT b.*, 
           c.full_name as client_name,
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
    $_SESSION['flash_message'] = "Invoice not found or you don't have permission to edit it.";
    $_SESSION['flash_type'] = "error";
    header("Location: index.php");
    exit;
}

$invoice = $result->fetch_assoc();

// Check if invoice is already paid or cancelled
if ($invoice['status'] === 'paid' || $invoice['status'] === 'cancelled') {
    $_SESSION['flash_message'] = "Cannot edit an invoice that is already paid or cancelled.";
    $_SESSION['flash_type'] = "error";
    header("Location: view.php?id=" . $invoiceId);
    exit;
}

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

// If no items found, create a default empty item
if (empty($invoiceItems)) {
    $invoiceItems[] = [
        'description' => '',
        'quantity' => 1,
        'rate' => 0,
        'amount' => 0
    ];
}

// Get all clients for dropdown
$clientsStmt = $conn->prepare("
    SELECT cp.client_id, u.full_name 
    FROM client_profiles cp
    JOIN users u ON cp.user_id = u.user_id
    ORDER BY u.full_name ASC
");
$clientsStmt->execute();
$clientsResult = $clientsStmt->get_result();

$clients = [];
while ($client = $clientsResult->fetch_assoc()) {
    $clients[] = $client;
}

// Initialize form data with invoice details
$formData = [
    'client_id' => $invoice['client_id'],
    'case_id' => $invoice['case_id'],
    'description' => $invoice['description'],
    'amount' => $invoice['amount'],
    'billing_date' => $invoice['billing_date'],
    'due_date' => $invoice['due_date'],
    'status' => $invoice['status'],
    'payment_method' => $invoice['payment_method'],
    'payment_date' => $invoice['payment_date'],
    'items' => $invoiceItems
];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form data
    $errors = [];
    
    // Required fields
    if (empty($_POST['client_id'])) {
        $errors['client_id'] = 'Client is required';
    }
    
    if (empty($_POST['description'])) {
        $errors['description'] = 'Description is required';
    }
    
    if (empty($_POST['billing_date'])) {
        $errors['billing_date'] = 'Billing date is required';
    }
    
    if (empty($_POST['due_date'])) {
        $errors['due_date'] = 'Due date is required';
    }
    
    // Validate invoice items
    $totalAmount = 0;
    $items = [];
    
    if (!empty($_POST['item_description'])) {
        for ($i = 0; $i < count($_POST['item_description']); $i++) {
            if (!empty($_POST['item_description'][$i])) {
                $quantity = floatval($_POST['item_quantity'][$i]);
                $rate = floatval($_POST['item_rate'][$i]);
                $amount = $quantity * $rate;
                
                $items[] = [
                    'description' => $_POST['item_description'][$i],
                    'quantity' => $quantity,
                    'rate' => $rate,
                    'amount' => $amount
                ];
                
                $totalAmount += $amount;
            }
        }
    }
    
    if (empty($items)) {
        $errors['items'] = 'At least one invoice item is required';
    }
    
    // If status is 'paid', validate payment details
    if ($_POST['status'] === 'paid') {
        if (empty($_POST['payment_method'])) {
            $errors['payment_method'] = 'Payment method is required for paid invoices';
        }
        
        if (empty($_POST['payment_date'])) {
            $errors['payment_date'] = 'Payment date is required for paid invoices';
        }
    }
    
    // Handle payment method and date
    $paymentMethod = null;
    $paymentDate = null;
    
    if ($_POST['status'] === 'paid') {
        $paymentMethod = $_POST['payment_method'];
        $paymentDate = $_POST['payment_date'];
    }
    
    // Update form data with submitted values
    $formData = [
        'client_id' => $_POST['client_id'],
        'case_id' => !empty($_POST['case_id']) ? $_POST['case_id'] : null,
        'description' => $_POST['description'],
        'amount' => $totalAmount,
        'billing_date' => $_POST['billing_date'],
        'due_date' => $_POST['due_date'],
        'status' => $_POST['status'],
        'payment_method' => $paymentMethod,
        'payment_date' => $paymentDate,
        'items' => $items
    ];
    
    // If no errors, update invoice
    if (empty($errors)) {
        try {
            // Start transaction
            $conn->begin_transaction();
            
            // Update invoice
            $updateStmt = $conn->prepare("
                UPDATE billings SET
                    client_id = ?,
                    case_id = ?,
                    amount = ?,
                    description = ?,
                    billing_date = ?,
                    due_date = ?,
                    status = ?,
                    payment_method = ?,
                    payment_date = ?
                WHERE billing_id = ? AND advocate_id = ?
            ");
            
            $updateStmt->bind_param(
                "iidssssssii",
                $formData['client_id'],
                $formData['case_id'],
                $formData['amount'],
                $formData['description'],
                $formData['billing_date'],
                $formData['due_date'],
                $formData['status'],
                $formData['payment_method'],
                $formData['payment_date'],
                $invoiceId,
                $advocateId
            );

            
            $updateStmt->execute();
            
            // Delete existing invoice items
            $deleteItemsStmt = $conn->prepare("DELETE FROM billing_items WHERE billing_id = ?");
            $deleteItemsStmt->bind_param("i", $invoiceId);
            $deleteItemsStmt->execute();
            
            // Insert new invoice items
            if (!empty($formData['items'])) {
                $itemStmt = $conn->prepare("
                    INSERT INTO billing_items (
                        billing_id, description, quantity, rate, amount
                    ) VALUES (?, ?, ?, ?, ?)
                ");
                
                foreach ($formData['items'] as $item) {
                    $itemStmt->bind_param(
                        "isddd",
                        $invoiceId,
                        $item['description'],
                        $item['quantity'],
                        $item['rate'],
                        $item['amount']
                    );
                    $itemStmt->execute();
                }
            }
            
            // If status changed to paid, create payment record
            if ($formData['status'] === 'paid' && $invoice['status'] !== 'paid') {
                $paymentStmt = $conn->prepare("
                    INSERT INTO payments (
                        billing_id, amount, payment_date, payment_method, notes, created_by
                    ) VALUES (?, ?, ?, ?, 'Payment recorded when invoice marked as paid', ?)
                ");
                
                $paymentStmt->bind_param(
                    "idssi",
                    $invoiceId,
                    $formData['amount'],
                    $formData['payment_date'],
                    $formData['payment_method'],
                    $_SESSION['user_id']
                );
                $paymentStmt->execute();
            }
            
            // Commit transaction
            $conn->commit();
            
            // Redirect to invoice view page
            $_SESSION['flash_message'] = "Invoice updated successfully.";
            $_SESSION['flash_type'] = "success";
            header("Location: view.php?id=" . $invoiceId);
            exit;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            
            $_SESSION['flash_message'] = "Error updating invoice: " . $e->getMessage();
            $_SESSION['flash_type'] = "error";
        }
    }
}

// Close database connection
$conn->close();
?>

<div class="mb-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-800">Edit Invoice</h1>
        
        <a href="view.php?id=<?php echo $invoiceId; ?>" class="btn-secondary">
            <i class="fas fa-arrow-left mr-1"></i> Back to Invoice
        </a>
    </div>
</div>

<div class="bg-white rounded-lg shadow-md p-6">
    <?php if (!empty($errors)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p class="font-bold">Please fix the following errors:</p>
            <ul class="mt-2 list-disc list-inside">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <form method="POST" action="" id="invoiceForm">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label for="client_id" class="block text-sm font-medium text-gray-700 mb-1">Client *</label>
                <select id="client_id" name="client_id" class="form-select w-full <?php echo isset($errors['client_id']) ? 'border-red-500' : ''; ?>" required>
                    <option value="">Select Client</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?php echo $client['client_id']; ?>" <?php echo $formData['client_id'] == $client['client_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($client['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['client_id'])): ?>
                    <p class="text-red-500 text-sm mt-1"><?php echo $errors['client_id']; ?></p>
                <?php endif; ?>
            </div>
            
            <div>
                <label for="case_id" class="block text-sm font-medium text-gray-700 mb-1">Related Case</label>
                <select id="case_id" name="case_id" class="form-select w-full">
                    <option value="">No Related Case</option>
                    <?php if ($formData['case_id']): ?>
                        <option value="<?php echo $invoice['case_id']; ?>" selected>
                            <?php echo htmlspecialchars($invoice['case_number'] . ' - ' . $invoice['case_title']); ?>
                        </option>
                    <?php endif; ?>
                </select>
            </div>
        </div>
        
        <div class="mb-6">
            <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Invoice Description *</label>
            <textarea id="description" name="description" rows="3" class="form-textarea w-full <?php echo isset($errors['description']) ? 'border-red-500' : ''; ?>" required><?php echo htmlspecialchars($formData['description']); ?></textarea>
            <?php if (isset($errors['description'])): ?>
                <p class="text-red-500 text-sm mt-1"><?php echo $errors['description']; ?></p>
            <?php endif; ?>
        </div>
        
        <!-- Invoice Items -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Invoice Items</label>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 mb-3" id="invoice-items-table">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Description
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">
                                Quantity
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">
                                Rate
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">
                                Amount
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-16">
                                Action
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200" id="invoice-items-body">
                            <?php foreach ($formData['items'] as $index => $item): ?>
                            <tr class="invoice-item">
                                <td class="px-4 py-3">
                                    <input type="text" name="item_description[]" class="form-input w-full" placeholder="Item description" value="<?php echo htmlspecialchars($item['description']); ?>" required>
                                </td>
                                <td class="px-4 py-3">
                                    <input type="number" name="item_quantity[]" class="form-input w-full item-quantity" placeholder="Qty" min="0.01" step="0.01" value="<?php echo number_format($item['quantity'], 2); ?>" required>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500">$</span>
                                        </div>
                                        <input type="number" name="item_rate[]" class="form-input w-full pl-7 item-rate" placeholder="0.00" min="0.01" step="0.01" value="<?php echo number_format($item['rate'], 2); ?>" required>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500">$</span>
                                        </div>
                                        <input type="number" name="item_amount[]" class="form-input w-full pl-7 item-amount" placeholder="0.00" value="<?php echo number_format($item['amount'], 2); ?>" readonly>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <button type="button" class="text-red-500 hover:text-red-700 remove-item">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <button type="button" id="add-item" class="btn-secondary text-sm">
                    <i class="fas fa-plus mr-1"></i> Add Item
                </button>
                
                <?php if (isset($errors['items'])): ?>
                    <p class="text-red-500 text-sm mt-1"><?php echo $errors['items']; ?></p>
                <?php endif; ?>
            </div>
            
            <!-- Invoice Totals -->
            <div class="mt-4 flex justify-end">
                <div class="w-64">
                    <div class="flex justify-between py-2 border-t border-gray-200">
                        <span class="font-medium">Subtotal:</span>
                        <span id="subtotal">$<?php echo number_format($formData['amount'], 2); ?></span>
                    </div>
                    <div class="flex justify-between py-2 border-t border-gray-200">
                        <span class="font-bold">Total:</span>
                        <span id="total" class="font-bold">$<?php echo number_format($formData['amount'], 2); ?></span>
                    </div>
                    <input type="hidden" id="amount-input" name="amount" value="<?php echo $formData['amount']; ?>">
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div>
                <label for="billing_date" class="block text-sm font-medium text-gray-700 mb-1">Invoice Date *</label>
                <input type="date" id="billing_date" name="billing_date" class="form-input w-full <?php echo isset($errors['billing_date']) ? 'border-red-500' : ''; ?>" value="<?php echo $formData['billing_date']; ?>" required>
                <?php if (isset($errors['billing_date'])): ?>
                    <p class="text-red-500 text-sm mt-1"><?php echo $errors['billing_date']; ?></p>
                <?php endif; ?>
            </div>
            
            <div>
                <label for="due_date" class="block text-sm font-medium text-gray-700 mb-1">Due Date *</label>
                <input type="date" id="due_date" name="due_date" class="form-input w-full <?php echo isset($errors['due_date']) ? 'border-red-500' : ''; ?>" value="<?php echo $formData['due_date']; ?>" required>
                <?php if (isset($errors['due_date'])): ?>
                    <p class="text-red-500 text-sm mt-1"><?php echo $errors['due_date']; ?></p>
                <?php endif; ?>
            </div>
            
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status *</label>
                <select id="status" name="status" class="form-select w-full" required>
                    <option value="pending" <?php echo $formData['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="paid" <?php echo $formData['status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                    <option value="overdue" <?php echo $formData['status'] === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                    <option value="cancelled" <?php echo $formData['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
        </div>
        
        <!-- Payment Details (shown only when status is "paid") -->
        <div id="payment-details" class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6" style="display: none;">
            <div>
                <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                <select id="payment_method" name="payment_method" class="form-select w-full <?php echo isset($errors['payment_method']) ? 'border-red-500' : ''; ?>">
                    <option value="">Select Payment Method</option>
                    <option value="cash" <?php echo $formData['payment_method'] === 'cash' ? 'selected' : ''; ?>>Cash</option>
                    <option value="check" <?php echo $formData['payment_method'] === 'check' ? 'selected' : ''; ?>>Check</option>
                    <option value="credit_card" <?php echo $formData['payment_method'] === 'credit_card' ? 'selected' : ''; ?>>Credit Card</option>
                    <option value="bank_transfer" <?php echo $formData['payment_method'] === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                    <option value="online_payment" <?php echo $formData['payment_method'] === 'online_payment' ? 'selected' : ''; ?>>Online Payment</option>
                </select>
                <?php if (isset($errors['payment_method'])): ?>
                    <p class="text-red-500 text-sm mt-1"><?php echo $errors['payment_method']; ?></p>
                <?php endif; ?>
            </div>
            
            <div>
                <label for="payment_date" class="block text-sm font-medium text-gray-700 mb-1">Payment Date</label>
                <input type="date" id="payment_date" name="payment_date" class="form-input w-full <?php echo isset($errors['payment_date']) ? 'border-red-500' : ''; ?>" value="<?php echo $formData['payment_date']; ?>">
                <?php if (isset($errors['payment_date'])): ?>
                    <p class="text-red-500 text-sm mt-1"><?php echo $errors['payment_date']; ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="flex justify-end space-x-4 mt-6">
            <a href="view.php?id=<?php echo $invoiceId; ?>" class="btn-secondary">
                Cancel
            </a>
            <button type="submit" class="btn-primary">
                Update Invoice
            </button>
        </div>
    </form>
</div>

<!-- Invoice Item Template (hidden) -->
<template id="invoice-item-template">
    <tr class="invoice-item">
        <td class="px-4 py-3">
            <input type="text" name="item_description[]" class="form-input w-full" placeholder="Item description" required>
        </td>
        <td class="px-4 py-3">
            <input type="number" name="item_quantity[]" class="form-input w-full item-quantity" placeholder="Qty" min="0.01" step="0.01" value="1.00" required>
        </td>
        <td class="px-4 py-3">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <span class="text-gray-500">$</span>
                </div>
                <input type="number" name="item_rate[]" class="form-input w-full pl-7 item-rate" placeholder="0.00" min="0.01" step="0.01" value="0.00" required>
            </div>
        </td>
        <td class="px-4 py-3">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <span class="text-gray-500">$</span>
                </div>
                <input type="number" name="item_amount[]" class="form-input w-full pl-7 item-amount" placeholder="0.00" value="0.00" readonly>
            </div>
        </td>
        <td class="px-4 py-3">
            <button type="button" class="text-red-500 hover:text-red-700 remove-item">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    </tr>
</template>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Client and case selection logic
    const clientSelect = document.getElementById('client_id');
    const caseSelect = document.getElementById('case_id');
    
    // Function to load cases for selected client
    function loadClientCases(clientId) {
        if (!clientId) {
            caseSelect.innerHTML = '<option value="">No Related Case</option>';
            return;
        }
        
        // Show loading indicator
        caseSelect.innerHTML = '<option value="">Loading cases...</option>';
        caseSelect.disabled = true;
        
        fetch(`get-client-cases.php?client_id=${clientId}`)
            .then(response => response.json())
            .then(cases => {
                caseSelect.innerHTML = '<option value="">No Related Case</option>';
                caseSelect.disabled = false;
                
                if (cases.length === 0) {
                    const option = document.createElement('option');
                    option.disabled = true;
                    option.textContent = 'No cases found for this client';
                    caseSelect.appendChild(option);
                } else {
                    cases.forEach(caseItem => {
                        const option = document.createElement('option');
                        option.value = caseItem.case_id;
                        option.textContent = `${caseItem.case_number} - ${caseItem.title}`;
                        caseSelect.appendChild(option);
                    });
                }
                
                <?php if ($formData['case_id']): ?>
                caseSelect.value = '<?php echo $formData['case_id']; ?>';
                <?php endif; ?>
            })
            .catch(error => {
                console.error('Error loading cases:', error);
                caseSelect.innerHTML = '<option value="">Error loading cases</option>';
                caseSelect.disabled = false;
            });
    }
    
    // Load cases when client changes
    clientSelect.addEventListener('change', function() {
        loadClientCases(this.value);
    });
    
    // Load cases for initial client selection
    if (clientSelect.value) {
        loadClientCases(clientSelect.value);
    }
    
    // Invoice items calculation
    const invoiceItemsTable = document.getElementById('invoice-items-table');
    const invoiceItemsBody = document.getElementById('invoice-items-body');
    const addItemButton = document.getElementById('add-item');
    const itemTemplate = document.getElementById('invoice-item-template');
    const subtotalElement = document.getElementById('subtotal');
    const totalElement = document.getElementById('total');
    const amountInput = document.getElementById('amount-input');
    
    // Function to calculate item amount
    function calculateItemAmount(row) {
        const quantity = parseFloat(row.querySelector('.item-quantity').value) || 0;
        const rate = parseFloat(row.querySelector('.item-rate').value) || 0;
        const amount = quantity * rate;
        row.querySelector('.item-amount').value = amount.toFixed(2);
        return amount;
    }
    
    // Function to calculate invoice total
    function calculateTotal() {
        const rows = document.querySelectorAll('.invoice-item');
        let total = 0;
        
        rows.forEach(row => {
            total += calculateItemAmount(row);
        });
        
        subtotalElement.textContent = '$' + total.toFixed(2);
        totalElement.textContent = '$' + total.toFixed(2);
        amountInput.value = total.toFixed(2);
    }
    
    // Add new item row
    addItemButton.addEventListener('click', function() {
        const newRow = document.importNode(itemTemplate.content, true).querySelector('tr');
        invoiceItemsBody.appendChild(newRow);
        
        // Add event listeners to new row
        addRowEventListeners(newRow);
        calculateTotal();
    });
    
    // Function to add event listeners to a row
    function addRowEventListeners(row) {
        const quantityInput = row.querySelector('.item-quantity');
        const rateInput = row.querySelector('.item-rate');
        const removeButton = row.querySelector('.remove-item');
        
        quantityInput.addEventListener('input', calculateTotal);
        rateInput.addEventListener('input', calculateTotal);
        
        removeButton.addEventListener('click', function() {
            row.remove();
            calculateTotal();
        });
    }
    
    // Add event listeners to existing rows
    document.querySelectorAll('.invoice-item').forEach(row => {
        addRowEventListeners(row);
    });
      // Calculate initial total
    calculateTotal();
    
    // Toggle payment details based on status
    const statusSelect = document.getElementById('status');
    const paymentDetails = document.getElementById('payment-details');
    
    function togglePaymentDetails() {
        if (statusSelect.value === 'paid') {
            paymentDetails.style.display = 'grid';
            document.getElementById('payment_method').required = true;
            document.getElementById('payment_date').required = true;
        } else {
            paymentDetails.style.display = 'none';
            document.getElementById('payment_method').required = false;
            document.getElementById('payment_date').required = false;
        }
    }
    
    statusSelect.addEventListener('change', togglePaymentDetails);
    
    // Initialize payment details visibility
    togglePaymentDetails();
});
</script>

<?php
// Include footer
include_once '../../includes/footer.php';
?>