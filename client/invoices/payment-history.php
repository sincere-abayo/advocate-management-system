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

// Connect to database
$conn = getDBConnection();

// Initialize filters
$filters = [
    'date_from' => isset($_GET['date_from']) ? $_GET['date_from'] : '',
    'date_to' => isset($_GET['date_to']) ? $_GET['date_to'] : '',
    'payment_method' => isset($_GET['payment_method']) ? $_GET['payment_method'] : '',
    'search' => isset($_GET['search']) ? $_GET['search'] : '',
];

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Build query based on filters
$whereConditions = ["b.client_id = ?"];
$params = [$clientId];
$types = "i";

if (!empty($filters['date_from'])) {
    $whereConditions[] = "p.payment_date >= ?";
    $params[] = $filters['date_from'];
    $types .= "s";
}

if (!empty($filters['date_to'])) {
    $whereConditions[] = "p.payment_date <= ?";
    $params[] = $filters['date_to'];
    $types .= "s";
}

if (!empty($filters['payment_method'])) {
    $whereConditions[] = "p.payment_method = ?";
    $params[] = $filters['payment_method'];
    $types .= "s";
}

if (!empty($filters['search'])) {
    $searchTerm = "%" . $filters['search'] . "%";
    $whereConditions[] = "(b.description LIKE ? OR u.full_name LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "ss";
}

$whereClause = implode(" AND ", $whereConditions);

// Get total count for pagination
$countQuery = "
    SELECT COUNT(*) as total
    FROM payments p
    JOIN billings b ON p.billing_id = b.billing_id
    JOIN advocate_profiles ap ON b.advocate_id = ap.advocate_id
    JOIN users u ON ap.user_id = u.user_id
    WHERE $whereClause
";

$countStmt = $conn->prepare($countQuery);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalPayments = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalPayments / $perPage);

// Ensure current page is within valid range
if ($page < 1) $page = 1;
if ($page > $totalPages && $totalPages > 0) $page = $totalPages;

// Get sorting parameters
$sortColumn = isset($_GET['sort']) ? $_GET['sort'] : 'p.payment_date';
$sortDirection = isset($_GET['dir']) && $_GET['dir'] === 'asc' ? 'ASC' : 'DESC';

// Valid sort columns to prevent SQL injection
$validSortColumns = [
    'p.payment_date', 'p.amount', 'p.payment_method', 'b.billing_id', 'u.full_name'
];

if (!in_array($sortColumn, $validSortColumns)) {
    $sortColumn = 'p.payment_date';
}

// Get payments with pagination and sorting
$query = "
    SELECT 
        p.*,
        b.billing_id,
        b.description as invoice_description,
        u.full_name as advocate_name,
        c.case_id,
        c.case_number,
        c.title as case_title
    FROM payments p
    JOIN billings b ON p.billing_id = b.billing_id
    JOIN advocate_profiles ap ON b.advocate_id = ap.advocate_id
    JOIN users u ON ap.user_id = u.user_id
    LEFT JOIN cases c ON b.case_id = c.case_id
    WHERE $whereClause
    ORDER BY $sortColumn $sortDirection
    LIMIT ?, ?
";

$stmt = $conn->prepare($query);
$params[] = $offset;
$params[] = $perPage;
$types .= "ii";
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Get payment statistics
$statsQuery = "
    SELECT 
        COUNT(*) as total_payments,
        SUM(p.amount) as total_amount_paid,
        COUNT(DISTINCT p.billing_id) as invoices_paid,
        MAX(p.payment_date) as last_payment_date
    FROM payments p
    JOIN billings b ON p.billing_id = b.billing_id
    WHERE b.client_id = ?
";

$statsStmt = $conn->prepare($statsQuery);
$statsStmt->bind_param("i", $clientId);
$statsStmt->execute();
$stats = $statsStmt->get_result()->fetch_assoc();

// Get payment methods for filter dropdown
$methodsQuery = "
    SELECT DISTINCT p.payment_method
    FROM payments p
    JOIN billings b ON p.billing_id = b.billing_id
    WHERE b.client_id = ?
    ORDER BY p.payment_method
";

$methodsStmt = $conn->prepare($methodsQuery);
$methodsStmt->bind_param("i", $clientId);
$methodsStmt->execute();
$methodsResult = $methodsStmt->get_result();

$paymentMethods = [];
while ($method = $methodsResult->fetch_assoc()) {
    $paymentMethods[] = $method['payment_method'];
}

// Close connection
$conn->close();

// Helper function to generate sort URL
function getSortUrl($column) {
    global $sortColumn, $sortDirection;
    $params = $_GET;
    $params['sort'] = $column;
    $params['dir'] = ($sortColumn === $column && $sortDirection === 'DESC') ? 'asc' : 'desc';
    return '?' . http_build_query($params);
}

// Helper function to generate sort icon
function getSortIcon($column) {
    global $sortColumn, $sortDirection;
    if ($sortColumn !== $column) {
        return '<i class="fas fa-sort text-gray-400 ml-1"></i>';
    }
    return ($sortDirection === 'ASC') 
        ? '<i class="fas fa-sort-up text-blue-500 ml-1"></i>' 
        : '<i class="fas fa-sort-down text-blue-500 ml-1"></i>';
}

// Set page title
$pageTitle = "Payment History";
include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Payment History</h1>
            <p class="text-gray-600">View all your payment transactions</p>
        </div>
        <div class="mt-4 md:mt-0">
            <a href="index.php" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-file-invoice-dollar mr-2"></i> View Invoices
            </a>
        </div>
    </div>
    
    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-500 mr-4">
                    <i class="fas fa-receipt text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Total Payments</p>
                    <p class="text-2xl font-semibold"><?php echo $stats['total_payments']; ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-500 mr-4">
                    <i class="fas fa-dollar-sign text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Total Amount Paid</p>
                    <p class="text-2xl font-semibold"><?php echo formatCurrency($stats['total_amount_paid']); ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-100 text-purple-500 mr-4">
                    <i class="fas fa-file-invoice text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Invoices Paid</p>
                    <p class="text-2xl font-semibold"><?php echo $stats['invoices_paid']; ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-yellow-100 text-yellow-500 mr-4">
                    <i class="fas fa-calendar-check text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Last Payment</p>
                    <p class="text-2xl font-semibold">
                        <?php echo $stats['last_payment_date'] ? date('M d, Y', strtotime($stats['last_payment_date'])) : 'N/A'; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form action="" method="GET" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                    <input type="date" id="date_from" name="date_from" class="form-input w-full" value="<?php echo $filters['date_from']; ?>">
                </div>
                
                <div>
                    <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                    <input type="date" id="date_to" name="date_to" class="form-input w-full" value="<?php echo $filters['date_to']; ?>">
                </div>
                
                <div>
                    <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                    <select id="payment_method" name="payment_method" class="form-select w-full">
                        <option value="">All Methods</option>
                        <?php foreach ($paymentMethods as $method): ?>
                            <option value="<?php echo $method; ?>" <?php echo $filters['payment_method'] === $method ? 'selected' : ''; ?>>
                                <?php echo ucfirst(str_replace('_', ' ', $method)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input type="text" id="search" name="search" class="form-input w-full" placeholder="Search payments..." value="<?php echo htmlspecialchars($filters['search']); ?>">
                </div>
            </div>
            
            <div class="flex items-center space-x-2">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg">
                    <i class="fas fa-search mr-2"></i> Filter
                </button>
                
                <a href="payment-history.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg">
                    <i class="fas fa-times mr-2"></i> Reset
                </a>
            </div>
            
            <!-- Preserve sort parameters -->
            <?php if (isset($_GET['sort'])): ?>
                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($_GET['sort']); ?>">
            <?php endif; ?>
            
            <?php if (isset($_GET['dir'])): ?>
                <input type="hidden" name="dir" value="<?php echo htmlspecialchars($_GET['dir']); ?>">
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Payments Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <?php if ($result->num_rows > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <a href="<?php echo getSortUrl('p.payment_date'); ?>" class="flex items-center">
                                    Payment Date <?php echo getSortIcon('p.payment_date'); ?>
                                </a>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <a href="<?php echo getSortUrl('b.billing_id'); ?>" class="flex items-center">
                                    Invoice # <?php echo getSortIcon('b.billing_id'); ?>
                                </a>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('u.full_name'); ?>" class="flex items-center">
                                    Advocate <?php echo getSortIcon('u.full_name'); ?>
                                </a>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Case
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <a href="<?php echo getSortUrl('p.payment_method'); ?>" class="flex items-center">
                                    Method <?php echo getSortIcon('p.payment_method'); ?>
                                </a>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Notes
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <a href="<?php echo getSortUrl('p.amount'); ?>" class="flex items-center justify-end">
                                    Amount <?php echo getSortIcon('p.amount'); ?>
                                </a>
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while ($payment = $result->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo date('M d, Y', strtotime($payment['payment_date'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <a href="view.php?id=<?php echo $payment['billing_id']; ?>" class="text-blue-600 hover:underline">
                                        INV-<?php echo str_pad($payment['billing_id'], 5, '0', STR_PAD_LEFT); ?>
                                    </a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($payment['advocate_name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php if (!empty($payment['case_id'])): ?>
                                        <a href="../cases/view.php?id=<?php echo $payment['case_id']; ?>" class="text-blue-600 hover:underline">
                                            <?php echo htmlspecialchars($payment['case_number']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-400">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <?php echo !empty($payment['notes']) ? htmlspecialchars($payment['notes']) : '<span class="text-gray-400">No notes</span>'; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-green-600 text-right">
                                    <?php echo formatCurrency($payment['amount']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="view.php?id=<?php echo $payment['billing_id']; ?>" class="text-blue-600 hover:text-blue-900" title="View Invoice">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="print-receipt.php?payment_id=<?php echo $payment['payment_id']; ?>" class="text-green-600 hover:text-green-900 ml-3" title="Print Receipt">
                                        <i class="fas fa-print"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                    <div class="flex justify-between items-center">
                        <div class="text-sm text-gray-500">
                            Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $perPage, $totalPayments); ?> of <?php echo $totalPayments; ?> payments
                        </div>
                        
                        <div class="flex space-x-1">
                            <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="px-3 py-1 rounded-md bg-white text-gray-600 border border-gray-300 hover:bg-gray-50">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="px-3 py-1 rounded-md <?php echo $i === $page ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 border border-gray-300 hover:bg-gray-50'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="px-3 py-1 rounded-md bg-white text-gray-600 border border-gray-300 hover:bg-gray-50">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center py-8">
                <div class="text-gray-400 mb-2">
                    <i class="fas fa-receipt text-5xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-1">No payment records found</h3>
                <p class="text-gray-500">
                    <?php if (!empty($filters['search']) || !empty($filters['payment_method']) || !empty($filters['date_from']) || !empty($filters['date_to'])): ?>
                        No payments match your filter criteria. <a href="payment-history.php" class="text-blue-600 hover:underline">Clear filters</a>
                    <?php else: ?>
                        You haven't made any payments yet
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Date range validation
    const dateFrom = document.getElementById('date_from');
    const dateTo = document.getElementById('date_to');
    
    dateFrom.addEventListener('change', function() {
        if (dateTo.value && this.value > dateTo.value) {
            dateTo.value = this.value;
        }
    });
    
    dateTo.addEventListener('change', function() {
        if (dateFrom.value && this.value < dateFrom.value) {
            dateFrom.value = this.value;
        }
    });
});
</script>

<?php
// Include footer
include '../includes/footer.php';
?>
