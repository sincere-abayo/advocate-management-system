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
    'status' => isset($_GET['status']) ? $_GET['status'] : '',
    'date_from' => isset($_GET['date_from']) ? $_GET['date_from'] : '',
    'date_to' => isset($_GET['date_to']) ? $_GET['date_to'] : '',
    'search' => isset($_GET['search']) ? $_GET['search'] : '',
];

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Build query based on filters
$whereConditions = ["b.client_id = ?"];
$params = [$clientId];
$types = "i";

if (!empty($filters['status'])) {
    $whereConditions[] = "b.status = ?";
    $params[] = $filters['status'];
    $types .= "s";
}

if (!empty($filters['date_from'])) {
    $whereConditions[] = "b.billing_date >= ?";
    $params[] = $filters['date_from'];
    $types .= "s";
}

if (!empty($filters['date_to'])) {
    $whereConditions[] = "b.billing_date <= ?";
    $params[] = $filters['date_to'];
    $types .= "s";
}

if (!empty($filters['search'])) {
    $searchTerm = "%" . $filters['search'] . "%";
    $whereConditions[] = "(b.description LIKE ? OR u.full_name LIKE ? OR c.title LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "sss";
}

$whereClause = implode(" AND ", $whereConditions);

// Get total count for pagination
$countQuery = "
    SELECT COUNT(*) as total
    FROM billings b
    JOIN advocate_profiles ap ON b.advocate_id = ap.advocate_id
    JOIN users u ON ap.user_id = u.user_id
    LEFT JOIN cases c ON b.case_id = c.case_id
    WHERE $whereClause
";

$countStmt = $conn->prepare($countQuery);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalInvoices = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalInvoices / $perPage);

// Ensure current page is within valid range
if ($page < 1) $page = 1;
if ($page > $totalPages && $totalPages > 0) $page = $totalPages;

// Get sorting parameters
$sortColumn = isset($_GET['sort']) ? $_GET['sort'] : 'b.billing_date';
$sortDirection = isset($_GET['dir']) && $_GET['dir'] === 'asc' ? 'ASC' : 'DESC';

// Valid sort columns to prevent SQL injection
$validSortColumns = [
    'b.billing_id', 'u.full_name', 'b.amount', 'b.billing_date', 'b.due_date', 'b.status'
];

if (!in_array($sortColumn, $validSortColumns)) {
    $sortColumn = 'b.billing_date';
}

// Get invoices with pagination and sorting
$query = "
    SELECT 
        b.*, 
        u.full_name as advocate_name, 
        c.title as case_title, 
        c.case_number
    FROM billings b
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

// Get invoice statistics
$statsQuery = "
    SELECT 
        COUNT(*) as total_invoices,
        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_invoices,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_invoices,
        SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue_invoices,
        SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as total_paid,
        SUM(CASE WHEN status = 'pending' OR status = 'overdue' THEN amount ELSE 0 END) as total_due
    FROM billings
    WHERE client_id = ?
";

$statsStmt = $conn->prepare($statsQuery);
$statsStmt->bind_param("i", $clientId);
$statsStmt->execute();
$stats = $statsStmt->get_result()->fetch_assoc();

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
$pageTitle = "Invoices";
include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">My Invoices</h1>
            <p class="text-gray-600">View and manage all your invoices</p>
        </div>
    </div>
    
    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-500 mr-4">
                    <i class="fas fa-file-invoice-dollar text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Total Invoices</p>
                    <p class="text-2xl font-semibold"><?php echo $stats['total_invoices']; ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-500 mr-4">
                    <i class="fas fa-check-circle text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Paid Invoices</p>
                    <p class="text-2xl font-semibold"><?php echo $stats['paid_invoices']; ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-yellow-100 text-yellow-500 mr-4">
                    <i class="fas fa-clock text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Pending Payment</p>
                    <p class="text-2xl font-semibold"><?php echo formatCurrency($stats['total_due'] ?? 0); ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-red-100 text-red-500 mr-4">
                    <i class="fas fa-exclamation-circle text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Overdue Invoices</p>
                    <p class="text-2xl font-semibold"><?php echo $stats['overdue_invoices']; ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form action="" method="GET" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="status" name="status" class="form-select w-full">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php echo $filters['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="paid" <?php echo $filters['status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="overdue" <?php echo $filters['status'] === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                    </select>
                </div>
                
                <div>
                    <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                    <input type="date" id="date_from" name="date_from" class="form-input w-full" value="<?php echo $filters['date_from']; ?>">
                </div>
                
                <div>
                    <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                    <input type="date" id="date_to" name="date_to" class="form-input w-full" value="<?php echo $filters['date_to']; ?>">
                </div>
                
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input type="text" id="search" name="search" class="form-input w-full" placeholder="Search invoices..." value="<?php echo htmlspecialchars($filters['search']); ?>">
                </div>
            </div>
            
            <div class="flex items-center space-x-2">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg">
                    <i class="fas fa-search mr-2"></i> Filter
                </button>
                
                <a href="index.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg">
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
    
    <!-- Invoices Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <?php if ($result->num_rows > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
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
                                <a href="<?php echo getSortUrl('b.amount'); ?>" class="flex items-center">
                                    Amount <?php echo getSortIcon('b.amount'); ?>
                                </a>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <a href="<?php echo getSortUrl('b.billing_date'); ?>" class="flex items-center">
                                    Date <?php echo getSortIcon('b.billing_date'); ?>
                                </a>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <a href="<?php echo getSortUrl('b.due_date'); ?>" class="flex items-center">
                                    Due Date <?php echo getSortIcon('b.due_date'); ?>
                                </a>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <a href="<?php echo getSortUrl('b.status'); ?>" class="flex items-center">
                                    Status <?php echo getSortIcon('b.status'); ?>
                                </a>
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while ($invoice = $result->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <a href="view.php?id=<?php echo $invoice['billing_id']; ?>" class="text-blue-600 hover:underline">
                                        INV-<?php echo str_pad($invoice['billing_id'], 5, '0', STR_PAD_LEFT); ?>
                                    </a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($invoice['advocate_name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php if (!empty($invoice['case_title'])): ?>
                                        <a href="../cases/view.php?id=<?php echo $invoice['case_id']; ?>" class="text-blue-600 hover:underline">
                                            <?php echo htmlspecialchars($invoice['case_number']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-400">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo formatCurrency($invoice['amount']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M d, Y', strtotime($invoice['billing_date'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M d, Y', strtotime($invoice['due_date'])); ?>
                                    <?php if ($invoice['status'] !== 'paid' && strtotime($invoice['due_date']) < time()): ?>
                                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                            Overdue
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
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
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="view.php?id=<?php echo $invoice['billing_id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <?php if ($invoice['status'] === 'pending' || $invoice['status'] === 'overdue'): ?>
                                        <a href="pay.php?id=<?php echo $invoice['billing_id']; ?>" class="text-green-600 hover:text-green-900">
                                            <i class="fas fa-credit-card"></i> Pay
                                        </a>
                                    <?php endif; ?>
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
                            Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $perPage, $totalInvoices); ?> of <?php echo $totalInvoices; ?> invoices
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
                    <i class="fas fa-file-invoice-dollar text-5xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-1">No invoices found</h3>
                <p class="text-gray-500">
                    <?php if (!empty($filters['search']) || !empty($filters['status']) || !empty($filters['date_from']) || !empty($filters['date_to'])): ?>
                        No invoices match your filter criteria. <a href="index.php" class="text-blue-600 hover:underline">Clear filters</a>
                    <?php else: ?>
                        You don't have any invoices yet
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
