<?php
// Set page title
$pageTitle = "Expense Management";

// Include header
include_once '../../includes/header.php';
;

// Get advocate ID
$advocateId = $_SESSION['advocate_id'];

// Get database connection
$conn = getDBConnection();

// Initialize filters
$filters = [
    'month' => isset($_GET['month']) ? intval($_GET['month']) : date('m'),
    'year' => isset($_GET['year']) ? intval($_GET['year']) : date('Y'),
    'case_id' => isset($_GET['case_id']) ? intval($_GET['case_id']) : 0,
    'category' => isset($_GET['category']) ? $_GET['category'] : '',
    'search' => isset($_GET['search']) ? $_GET['search'] : '',
    'date_from' => isset($_GET['date_from']) ? $_GET['date_from'] : '',
    'date_to' => isset($_GET['date_to']) ? $_GET['date_to'] : '',
];

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Build query based on filters
$whereConditions = ["ce.advocate_id = ?"];
$params = [$advocateId];
$types = "i";

if ($filters['month'] > 0) {
    $whereConditions[] = "MONTH(ce.expense_date) = ?";
    $params[] = $filters['month'];
    $types .= "i";
}

if ($filters['year'] > 0) {
    $whereConditions[] = "YEAR(ce.expense_date) = ?";
    $params[] = $filters['year'];
    $types .= "i";
}

if ($filters['case_id'] > 0) {
    $whereConditions[] = "ce.case_id = ?";
    $params[] = $filters['case_id'];
    $types .= "i";
}

if (!empty($filters['category'])) {
    $whereConditions[] = "ce.expense_category = ?";
    $params[] = $filters['category'];
    $types .= "s";
}

if (!empty($filters['date_from'])) {
    $whereConditions[] = "ce.expense_date >= ?";
    $params[] = $filters['date_from'];
    $types .= "s";
}

if (!empty($filters['date_to'])) {
    $whereConditions[] = "ce.expense_date <= ?";
    $params[] = $filters['date_to'];
    $types .= "s";
}

if (!empty($filters['search'])) {
    $searchTerm = "%" . $filters['search'] . "%";
    $whereConditions[] = "(ce.description LIKE ? OR ce.expense_category LIKE ? OR c.title LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "sss";
}

$whereClause = implode(" AND ", $whereConditions);

// Get total count for pagination
$countQuery = "
    SELECT COUNT(*) as total
    FROM case_expenses ce
    LEFT JOIN cases c ON ce.case_id = c.case_id
    WHERE $whereClause
";

$countStmt = $conn->prepare($countQuery);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalExpenses = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalExpenses / $perPage);

// Ensure current page is within valid range
if ($page < 1) $page = 1;
if ($page > $totalPages && $totalPages > 0) $page = $totalPages;

// Get sorting parameters
$sortColumn = isset($_GET['sort']) ? $_GET['sort'] : 'ce.expense_date';
$sortDirection = isset($_GET['dir']) && $_GET['dir'] === 'asc' ? 'ASC' : 'DESC';

// Valid sort columns to prevent SQL injection
$validSortColumns = [
    'ce.expense_date', 'ce.amount', 'ce.expense_category', 'c.title'
];

if (!in_array($sortColumn, $validSortColumns)) {
    $sortColumn = 'ce.expense_date';
}

// Get expenses with pagination and sorting
$query = "
    SELECT 
        ce.expense_id,
        ce.case_id,
        c.case_number,
        c.title as case_title,
        ce.expense_date,
        ce.amount,
        ce.description,
        ce.expense_category,
        ce.receipt_file
    FROM case_expenses ce
    LEFT JOIN cases c ON ce.case_id = c.case_id
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

// Get all cases for filter dropdown
$casesQuery = "
    SELECT DISTINCT c.case_id, c.case_number, c.title
    FROM cases c
    JOIN case_assignments ca ON c.case_id = ca.case_id
    WHERE ca.advocate_id = ?
    ORDER BY c.filing_date DESC
";
$casesStmt = $conn->prepare($casesQuery);
$casesStmt->bind_param("i", $advocateId);
$casesStmt->execute();
$casesResult = $casesStmt->get_result();
$cases = [];
while ($case = $casesResult->fetch_assoc()) {
    $cases[] = $case;
}

// Get expense categories for filter dropdown
$categoriesQuery = "
    SELECT DISTINCT expense_category 
    FROM case_expenses 
    WHERE advocate_id = ? AND expense_category IS NOT NULL AND expense_category != ''
    ORDER BY expense_category
";
$categoriesStmt = $conn->prepare($categoriesQuery);
$categoriesStmt->bind_param("i", $advocateId);
$categoriesStmt->execute();
$categoriesResult = $categoriesStmt->get_result();
$categories = [];
while ($category = $categoriesResult->fetch_assoc()) {
    $categories[] = $category['expense_category'];
}

// Get summary statistics
// Monthly total
$monthlyQuery = "
    SELECT SUM(amount) as total 
    FROM case_expenses 
    WHERE advocate_id = ? AND MONTH(expense_date) = ? AND YEAR(expense_date) = ?
";
$monthlyStmt = $conn->prepare($monthlyQuery);
$monthlyStmt->bind_param("iii", $advocateId, $filters['month'], $filters['year']);
$monthlyStmt->execute();
$monthlyTotal = $monthlyStmt->get_result()->fetch_assoc()['total'] ?? 0;

// Yearly total
$yearlyQuery = "
    SELECT SUM(amount) as total 
    FROM case_expenses 
    WHERE advocate_id = ? AND YEAR(expense_date) = ?
";
$yearlyStmt = $conn->prepare($yearlyQuery);
$yearlyStmt->bind_param("ii", $advocateId, $filters['year']);
$yearlyStmt->execute();
$yearlyTotal = $yearlyStmt->get_result()->fetch_assoc()['total'] ?? 0;

// Current filter total - use a copy of params without pagination parameters
$filterParams = array_slice($params, 0, count($params) - 2); // Remove the last two parameters (offset and limit)
$filterTypes = substr($types, 0, -2); // Remove the last two types ('ii')

$currentFilterQuery = "
    SELECT SUM(amount) as total 
    FROM case_expenses ce
    LEFT JOIN cases c ON ce.case_id = c.case_id
    WHERE $whereClause
";
$currentFilterStmt = $conn->prepare($currentFilterQuery);
if (!empty($filterParams)) {
    $currentFilterStmt->bind_param($filterTypes, ...$filterParams);
}
$currentFilterStmt->execute();
$currentFilterTotal = $currentFilterStmt->get_result()->fetch_assoc()['total'] ?? 0;


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


?>

<div class="mb-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Expense Management</h1>
            <p class="text-gray-600">Track and manage all your case-related expenses</p>
        </div>
        
        <div class="mt-4 md:mt-0">
            <a href="create.php" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-plus mr-2"></i> Add New Expense
            </a>
        </div>
    </div>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-red-100 text-red-500 mr-4">
                <i class="fas fa-receipt text-xl"></i>
            </div>
            <div>
                <p class="text-gray-500 text-sm">Current Filter Total</p>
                <p class="text-2xl font-semibold"><?php echo formatCurrency($currentFilterTotal); ?></p>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-orange-100 text-orange-500 mr-4">
                <i class="fas fa-calendar-alt text-xl"></i>
            </div>
            <div>
                <p class="text-gray-500 text-sm">Monthly Expenses (<?php echo date('F Y', mktime(0, 0, 0, $filters['month'], 1, $filters['year'])); ?>)</p>
                <p class="text-2xl font-semibold"><?php echo formatCurrency($monthlyTotal); ?></p>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 text-blue-500 mr-4">
                <i class="fas fa-chart-line text-xl"></i>
            </div>
            <div>
                <p class="text-gray-500 text-sm">Yearly Expenses (<?php echo $filters['year']; ?>)</p>
                <p class="text-2xl font-semibold"><?php echo formatCurrency($yearlyTotal); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6 mt-6">
    <form action="" method="GET" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label for="month" class="block text-sm font-medium text-gray-700 mb-1">Month</label>
                <select id="month" name="month" class="form-select w-full">
                    <option value="0">All Months</option>
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo $filters['month'] == $i ? 'selected' : ''; ?>>
                            <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div>
                <label for="year" class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                <select id="year" name="year" class="form-select w-full">
                    <?php for ($i = date('Y'); $i >= date('Y') - 5; $i--): ?>
                        <option value="<?php echo $i; ?>" <?php echo $filters['year'] == $i ? 'selected' : ''; ?>>
                            <?php echo $i; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div>
                <label for="case_id" class="block text-sm font-medium text-gray-700 mb-1">Case</label>
                <select id="case_id" name="case_id" class="form-select w-full">
                    <option value="0">All Cases</option>
                    <?php foreach ($cases as $case): ?>
                        <option value="<?php echo $case['case_id']; ?>" <?php echo $filters['case_id'] == $case['case_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($case['case_number'] . ' - ' . $case['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                <select id="category" name="category" class="form-select w-full">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $filters['category'] == $category ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category); ?>
                        </option>
                    <?php endforeach; ?>
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
            
            <div class="lg:col-span-2">
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" id="search" name="search" class="form-input w-full" placeholder="Search by description, category, or case title" value="<?php echo htmlspecialchars($filters['search']); ?>">
            </div>
        </div>
        
        <div class="flex items-center space-x-2">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg">
                <i class="fas fa-filter mr-2"></i> Apply Filters
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

<!-- Expenses Table -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <?php if ($result->num_rows > 0): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('ce.expense_date'); ?>" class="flex items-center">
                                Date <?php echo getSortIcon('ce.expense_date'); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Case
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('ce.expense_category'); ?>" class="flex items-center">
                                Category <?php echo getSortIcon('ce.expense_category'); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Description
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('ce.amount'); ?>" class="flex items-center">
                                Amount <?php echo getSortIcon('ce.amount'); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Receipt
                        </th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php while ($expense = $result->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo date('M d, Y', strtotime($expense['expense_date'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php if ($expense['case_id']): ?>
                                    <a href="<?php echo $path_url; ?>advocate/cases/view.php?id=<?php echo $expense['case_id']; ?>" class="text-blue-600 hover:text-blue-900">
                                        <?php echo htmlspecialchars($expense['case_number']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-gray-500">No Case</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo htmlspecialchars($expense['expense_category'] ?? 'Uncategorized'); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <?php echo htmlspecialchars($expense['description']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo formatCurrency($expense['amount']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php if (!empty($expense['receipt_file'])): ?>
                                    <a href="<?php echo $path_url; ?>uploads/receipts/<?php echo $expense['receipt_file']; ?>" target="_blank" class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-file-alt mr-1"></i> View
                                    </a>
                                <?php else: ?>
                                    <span class="text-gray-500">No Receipt</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex justify-end space-x-2">
                                    <a href="edit.php?id=<?php echo $expense['expense_id']; ?>" class="text-indigo-600 hover:text-indigo-900" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="delete.php?id=<?php echo $expense['expense_id']; ?>" class="text-red-600 hover:text-red-900" title="Delete" onclick="return confirm('Are you sure you want to delete this expense?');">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </div>
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
                        Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $perPage, $totalExpenses); ?> of <?php echo $totalExpenses; ?> expenses
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
            <h3 class="text-lg font-medium text-gray-900 mb-1">No expenses found</h3>
            <p class="text-gray-500">
                <?php if (!empty($filters['search']) || $filters['month'] > 0 || $filters['case_id'] > 0 || !empty($filters['category']) || !empty($filters['date_from']) || !empty($filters['date_to'])): ?>
                    No expenses match your filter criteria. <a href="index.php" class="text-blue-600 hover:underline">Clear filters</a>
                <?php else: ?>
                    You haven't recorded any expenses yet
                <?php endif; ?>
            </p>
            <div class="mt-4">
                <a href="create.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-plus mr-2"></i> Add New Expense
                </a>
            </div>
        </div>
    <?php endif; ?>
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
// Close database connection
$conn->close();
// Include footer
include_once '../../includes/footer.php';
?>
