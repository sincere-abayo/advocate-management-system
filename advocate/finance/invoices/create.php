<?php
// Set page title
$pageTitle = "Create Invoice";

// Include header
include_once '../../includes/header.php';

// Get advocate ID
$advocateId = $advocateData['advocate_id'];

// Get database connection
$conn = getDBConnection();

// Initialize variables
$errors = [];
$formData = [
    'client_id' => '',
    'case_id' => '',
    'amount' => '',
    'description' => '',
    'billing_date' => date('Y-m-d'),
    'due_date' => date('Y-m-d', strtotime('+30 days')),
    'status' => 'pending',
    'payment_method' => '',
    'payment_date' => '',
    'items' => [
        ['description' => '', 'quantity' => 1, 'rate' => 0, 'amount' => 0]
    ]
];

// Check if case_id is provided in URL
$caseId = isset($_GET['case_id']) ? (int)$_GET['case_id'] : 0;
$caseDetails = null;

// Check if client_id is provided in URL
$clientId = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
$clientDetails = null;

// If case_id is provided, verify it exists and advocate is assigned to it
if ($caseId > 0) {
    $caseStmt = $conn->prepare("
        SELECT c.case_id, c.title, c.case_number, c.client_id, u.full_name as client_name
        FROM cases c
        JOIN case_assignments ca ON c.case_id = ca.case_id
        JOIN client_profiles cp ON c.client_id = cp.client_id
        JOIN users u ON cp.user_id = u.user_id
        WHERE c.case_id = ? AND ca.advocate_id = ?
        LIMIT 1
    ");
    $caseStmt->bind_param("ii", $caseId, $advocateId);
    $caseStmt->execute();
    $caseResult = $caseStmt->get_result();
    
    if ($caseResult->num_rows > 0) {
        $caseDetails = $caseResult->fetch_assoc();
        $formData['case_id'] = $caseId;
        $formData['client_id'] = $caseDetails['client_id'];
        $clientId = $caseDetails['client_id'];
    }
}
// If only client_id is provided, verify it exists
elseif ($clientId > 0) {
    $clientStmt = $conn->prepare("
        SELECT cp.client_id, u.full_name as client_name
        FROM client_profiles cp
        JOIN users u ON cp.user_id = u.user_id
        WHERE cp.client_id = ?
        LIMIT 1
    ");
    $clientStmt->bind_param("i", $clientId);
    $clientStmt->execute();
    $clientResult = $clientStmt->get_result();
    
    if ($clientResult->num_rows > 0) {
        $clientDetails = $clientResult->fetch_assoc();
        $formData['client_id'] = $clientId;
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate client
    if (empty($_POST['client_id'])) {
        $errors['client_id'] = 'Client is required';
    } else {
        $formData['client_id'] = (int)$_POST['client_id'];
    }
    
    // Case is optional
    $formData['case_id'] = !empty($_POST['case_id']) ? (int)$_POST['case_id'] : null;
    
    // Validate amount
    if (empty($_POST['amount'])) {
        $errors['amount'] = 'Amount is required';
    } elseif (!is_numeric($_POST['amount']) || $_POST['amount'] <= 0) {
        $errors['amount'] = 'Amount must be a positive number';
    } else {
        $formData['amount'] = (float)$_POST['amount'];
    }
    
    // Validate description
    if (empty($_POST['description'])) {
        $errors['description'] = 'Description is required';
    } else {
        $formData['description'] = $_POST['description'];
    }
    
    // Validate billing date
    if (empty($_POST['billing_date'])) {
        $errors['billing_date'] = 'Billing date is required';
    } else {
        $formData['billing_date'] = $_POST['billing_date'];
    }
    
    // Validate due date
    if (empty($_POST['due_date'])) {
        $errors['due_date'] = 'Due date is required';
    } elseif ($_POST['due_date'] < $_POST['billing_date']) {
        $errors['due_date'] = 'Due date cannot be earlier than billing date';
    } else {
        $formData['due_date'] = $_POST['due_date'];
    }
    
    // Status
    $formData['status'] = $_POST['status'];
    
    // Payment method and date (only if status is paid)
    if ($_POST['status'] === 'paid') {
        if (empty($_POST['payment_method'])) {
            $errors['payment_method'] = 'Payment method is required for paid invoices';
        } else {
            $formData['payment_method'] = $_POST['payment_method'];
        }
        
        if (empty($_POST['payment_date'])) {
            $errors['payment_date'] = 'Payment date is required for paid invoices';
        } else {
            $formData['payment_date'] = $_POST['payment_date'];
        }
    } else {
        $formData['payment_method'] = null;
        $formData['payment_date'] = null;
    }
    
    // Invoice items
    if (isset($_POST['item_description']) && is_array($_POST['item_description'])) {
        $formData['items'] = [];
        $totalAmount = 0;
        
        for ($i = 0; $i < count($_POST['item_description']); $i++) {
            if (empty($_POST['item_description'][$i])) continue;
            
            $quantity = !empty($_POST['item_quantity'][$i]) ? (float)$_POST['item_quantity'][$i] : 1;
            $rate = !empty($_POST['item_rate'][$i]) ? (float)$_POST['item_rate'][$i] : 0;
            $amount = $quantity * $rate;
            $totalAmount += $amount;
            
            $formData['items'][] = [
                'description' => $_POST['item_description'][$i],
                'quantity' => $quantity,
                'rate' => $rate,
                'amount' => $amount
            ];
        }
        
        // Update total amount based on items
        $formData['amount'] = $totalAmount;
    }
    
    // If no errors, insert invoice
    if (empty($errors)) {
        try {
            // Start transaction
            $conn->begin_transaction();
            
           // Insert invoice
$stmt = $conn->prepare("
INSERT INTO billings (
    client_id, advocate_id, case_id, amount, description, 
    billing_date, due_date, status, payment_method, payment_date
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
"iiidssssss",  // Fixed: Added an extra 's' for payment_date
$formData['client_id'],
$advocateId,
$formData['case_id'],
$formData['amount'],
$formData['description'],
$formData['billing_date'],
$formData['due_date'],
$formData['status'],
$formData['payment_method'],
$formData['payment_date']
);

            
            $stmt->execute();
            $invoiceId = $conn->insert_id;
            
            // Insert invoice items
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
            
            // If status is paid, record payment
            if ($formData['status'] === 'paid') {
                $paymentStmt = $conn->prepare("
                    INSERT INTO payments (
                        billing_id, amount, payment_date, payment_method, notes
                    ) VALUES (?, ?, ?, ?, 'Payment recorded during invoice creation')
                ");
                
                $paymentStmt->bind_param(
                    "idss",
                    $invoiceId,
                    $formData['amount'],
                    $formData['payment_date'],
                    $formData['payment_method']
                );
                $paymentStmt->execute();
            }
            
            // Create notification for client
            $clientUserStmt = $conn->prepare("
                SELECT u.user_id, u.full_name 
                FROM users u 
                JOIN client_profiles cp ON u.user_id = cp.user_id 
                WHERE cp.client_id = ?
            ");
            $clientUserStmt->bind_param("i", $formData['client_id']);
            $clientUserStmt->execute();
            $clientUser = $clientUserStmt->get_result()->fetch_assoc();
            
            if ($clientUser) {
                $notificationTitle = "New Invoice Created";
                $notificationMessage = "A new invoice (INV-" . str_pad($invoiceId, 5, '0', STR_PAD_LEFT) . ") for " . formatCurrency($formData['amount']) . " has been created.";
                
                createNotification(
                    $clientUser['user_id'],
                    $notificationTitle,
                    $notificationMessage,
                    'invoice',
                    $invoiceId
                );
            }
            
            // Add case activity if case_id is provided
            if ($formData['case_id']) {
                $activityDesc = "Invoice created: " . formatCurrency($formData['amount']);
                addCaseActivity($formData['case_id'], $_SESSION['user_id'], 'update', $activityDesc);
            }
            
            // Commit transaction
            $conn->commit();
            
            // Redirect to invoice view page
            $_SESSION['flash_message'] = "Invoice created successfully.";
            $_SESSION['flash_type'] = "success";
            header("Location: view.php?id=" . $invoiceId);
            exit;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $errors['general'] = "Error creating invoice: " . $e->getMessage();
        }
    }
}

// Get all clients for dropdown
$clientsQuery = "
    SELECT DISTINCT cp.client_id, u.full_name
    FROM client_profiles cp
    JOIN users u ON cp.user_id = u.user_id
    JOIN cases c ON c.client_id = cp.client_id
    JOIN case_assignments ca ON c.case_id = ca.case_id
    WHERE ca.advocate_id = ?
    ORDER BY u.full_name
";
$clientsStmt = $conn->prepare($clientsQuery);
$clientsStmt->bind_param("i", $advocateId);
$clientsStmt->execute();
$clientsResult = $clientsStmt->get_result();
$clients = [];
while ($client = $clientsResult->fetch_assoc()) {
    $clients[] = $client;
}

// Close database connection
$conn->close();
?>

<div class="mb-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Create Invoice</h1>
            <p class="text-gray-600">Create a new invoice for a client</p>
        </div>
    </div>
</div>

<div class="bg-white rounded-lg shadow-md p-6">
    <?php if (isset($errors['general'])): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p><?php echo $errors['general']; ?></p>
        </div>
    <?php endif; ?>
    
    <form method="POST" action="" id="invoiceForm">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label for="client_id" class="block text-sm font-medium text-gray-700 mb-1">Client *</label>
                <select id="client_id" name="client_id" class="form-select w-full <?php echo isset($errors['client_id']) ? 'border-red-500' : ''; ?>" required <?php echo $caseId ? 'disabled' : ''; ?>>
                    <option value="">Select Client</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?php echo $client['client_id']; ?>" <?php echo $formData['client_id'] == $client['client_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($client['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($caseId): ?>
                    <input type="hidden" name="client_id" value="<?php echo $formData['client_id']; ?>">
                <?php endif; ?>
                <?php if (isset($errors['client_id'])): ?>
                    <p class="text-red-500 text-sm mt-1"><?php echo $errors['client_id']; ?></p>
                <?php endif; ?>
                
                <?php if ($caseId && $caseDetails): ?>
                    <div class="mt-1 text-sm text-blue-600">
                        <i class="fas fa-info-circle"></i> Creating invoice for client: <?php echo htmlspecialchars($caseDetails['client_name']); ?>
                    </div>
                <?php elseif ($clientId && $clientDetails): ?>
                    <div class="mt-1 text-sm text-blue-600">
                        <i class="fas fa-info-circle"></i> Creating invoice for: <?php echo htmlspecialchars($clientDetails['client_name']); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div>
                <label for="case_id" class="block text-sm font-medium text-gray-700 mb-1">Related Case</label>
                <select id="case_id" name="case_id" class="form-select w-full" <?php echo $caseId ? 'disabled' : ''; ?>>
                    <option value="">No Related Case</option>
                                       <?php if ($caseId && $caseDetails): ?>
                        <option value="<?php echo $caseDetails['case_id']; ?>" selected>
                            <?php echo htmlspecialchars($caseDetails['case_number'] . ' - ' . $caseDetails['title']); ?>
                        </option>
                    <?php endif; ?>
                </select>
                <?php if ($caseId): ?>
                    <input type="hidden" name="case_id" value="<?php echo $caseId; ?>">
                <?php endif; ?>
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
                                <td class="px-4 py-2">
                                    <input type="text" name="item_description[]" class="form-input w-full" placeholder="Item description" value="<?php echo htmlspecialchars($item['description']); ?>" required>
                                </td>
                                <td class="px-4 py-2">
                                    <input type="number" name="item_quantity[]" class="form-input w-full item-quantity" min="0.01" step="0.01" placeholder="Qty" value="<?php echo $item['quantity']; ?>" required>
                                </td>
                                <td class="px-4 py-2">
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 sm:text-sm">$</span>
                                        </div>
                                        <input type="number" name="item_rate[]" class="form-input pl-7 w-full item-rate" min="0.01" step="0.01" placeholder="0.00" value="<?php echo $item['rate']; ?>" required>
                                    </div>
                                </td>
                                <td class="px-4 py-2">
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 sm:text-sm">$</span>
                                        </div>
                                        <input type="number" name="item_amount[]" class="form-input pl-7 w-full item-amount" readonly value="<?php echo $item['amount']; ?>">
                                    </div>
                                </td>
                                <td class="px-4 py-2">
                                    <button type="button" class="text-red-600 hover:text-red-900 remove-item" <?php echo $index === 0 ? 'disabled' : ''; ?>>
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="flex justify-between items-center">
                <button type="button" id="add-item" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-plus mr-2"></i> Add Item
                </button>
                
                <div class="text-right">
                    <div class="flex justify-end space-x-4 items-center mb-2">
                        <span class="text-sm font-medium text-gray-700">Subtotal:</span>
                        <span class="text-sm text-gray-900" id="subtotal">$0.00</span>
                    </div>
                    <div class="flex justify-end space-x-4 items-center">
                        <span class="text-base font-medium text-gray-700">Total:</span>
                        <span class="text-base font-bold text-gray-900" id="total">$0.00</span>
                        <input type="hidden" name="amount" id="amount-input" value="<?php echo $formData['amount']; ?>">
                    </div>
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div>
                <label for="billing_date" class="block text-sm font-medium text-gray-700 mb-1">Billing Date *</label>
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
                <select id="status" name="status" class="form-select w-full">
                    <option value="pending" <?php echo $formData['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="paid" <?php echo $formData['status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                    <option value="cancelled" <?php echo $formData['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
        </div>
        
        <!-- Payment details (shown only when status is "paid") -->
        <div id="payment-details" class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6" style="display: none;">
            <div>
                <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-1">Payment Method *</label>
                <select id="payment_method" name="payment_method" class="form-select w-full <?php echo isset($errors['payment_method']) ? 'border-red-500' : ''; ?>">
                    <option value="">Select Payment Method</option>
                    <option value="cash" <?php echo $formData['payment_method'] === 'cash' ? 'selected' : ''; ?>>Cash</option>
                    <option value="check" <?php echo $formData['payment_method'] === 'check' ? 'selected' : ''; ?>>Check</option>
                    <option value="credit_card" <?php echo $formData['payment_method'] === 'credit_card' ? 'selected' : ''; ?>>Credit Card</option>
                    <option value="bank_transfer" <?php echo $formData['payment_method'] === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                    <option value="other" <?php echo $formData['payment_method'] === 'other' ? 'selected' : ''; ?>>Other</option>
                </select>
                <?php if (isset($errors['payment_method'])): ?>
                    <p class="text-red-500 text-sm mt-1"><?php echo $errors['payment_method']; ?></p>
                <?php endif; ?>
            </div>
            
            <div>
                <label for="payment_date" class="block text-sm font-medium text-gray-700 mb-1">Payment Date *</label>
                <input type="date" id="payment_date" name="payment_date" class="form-input w-full <?php echo isset($errors['payment_date']) ? 'border-red-500' : ''; ?>" value="<?php echo $formData['payment_date'] ?: date('Y-m-d'); ?>">
                <?php if (isset($errors['payment_date'])): ?>
                    <p class="text-red-500 text-sm mt-1"><?php echo $errors['payment_date']; ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="flex justify-end space-x-4 mt-8">
            <a href="<?php echo $caseId ? '../cases/view.php?id=' . $caseId : 'index.php'; ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg">
                Cancel
            </a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg">
                Create Invoice
            </button>
        </div>
    </form>
</div>

<!-- Invoice Item Template (hidden) -->
<template id="invoice-item-template">
    <tr class="invoice-item">
        <td class="px-4 py-2">
            <input type="text" name="item_description[]" class="form-input w-full" placeholder="Item description" required>
        </td>
        <td class="px-4 py-2">
            <input type="number" name="item_quantity[]" class="form-input w-full item-quantity" min="0.01" step="0.01" placeholder="Qty" value="1" required>
        </td>
        <td class="px-4 py-2">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <span class="text-gray-500 sm:text-sm">$</span>
                </div>
                <input type="number" name="item_rate[]" class="form-input pl-7 w-full item-rate" min="0.01" step="0.01" placeholder="0.00" required>
            </div>
        </td>
        <td class="px-4 py-2">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <span class="text-gray-500 sm:text-sm">$</span>
                </div>
                <input type="number" name="item_amount[]" class="form-input pl-7 w-full item-amount" readonly>
            </div>
        </td>
        <td class="px-4 py-2">
            <button type="button" class="text-red-600 hover:text-red-900 remove-item">
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
    if (clientSelect) {
        clientSelect.addEventListener('change', function() {
            loadClientCases(this.value);
        });
        
        // Load cases for initial client selection
        if (clientSelect.value) {
            loadClientCases(clientSelect.value);
        }
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
    togglePaymentDetails(); // Initial state
    
    // Form validation
    const invoiceForm = document.getElementById('invoiceForm');
    
    invoiceForm.addEventListener('submit', function(e) {
        // Check if at least one item exists
        const items = document.querySelectorAll('.invoice-item');
        let valid = false;
        
        items.forEach(item => {
            const description = item.querySelector('input[name="item_description[]"]').value.trim();
            if (description) {
                valid = true;
            }
        });
        
        if (!valid) {
            e.preventDefault();
            alert('Please add at least one invoice item');
        }
        
        // Check if total amount is greater than zero
        const totalAmount = parseFloat(amountInput.value);
        if (totalAmount <= 0) {
            e.preventDefault();
            alert('Total invoice amount must be greater than zero');
        }
    });
});
</script>

<?php
// Include footer
include_once '../../includes/footer.php';
?>
