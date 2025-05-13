<?php
// Set page title
$pageTitle = "Clients";

// Include header
include_once '../includes/header.php';

// Get advocate ID
$advocateId = $advocateData['advocate_id'];

// Get database connection
$conn = getDBConnection();

// Set up pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Set up search and filters
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';

// Build query conditions
$conditions = ["ca.advocate_id = ?"];
$params = [$advocateId];
$types = "i";

if (!empty($search)) {
    $searchTerm = "%$search%";
    $conditions[] = "(u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "sss";
}

if (!empty($statusFilter)) {
    $conditions[] = "u.status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

$whereClause = implode(" AND ", $conditions);

// Get total clients count
$countStmt = $conn->prepare("
    SELECT COUNT(DISTINCT cp.client_id) as total
    FROM client_profiles cp
    JOIN users u ON cp.user_id = u.user_id
    JOIN cases c ON cp.client_id = c.client_id
    JOIN case_assignments ca ON c.case_id = ca.case_id
    WHERE $whereClause
");
$countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalClients = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

// Calculate total pages
$totalPages = ceil($totalClients / $perPage);

// Ensure current page is within valid range
if ($page < 1) $page = 1;
if ($page > $totalPages && $totalPages > 0) $page = $totalPages;

// Get clients with pagination
$clientsStmt = $conn->prepare("
    SELECT DISTINCT cp.client_id, u.user_id, u.full_name, u.email, u.phone, u.status,
           u.created_at, cp.occupation, 
           (SELECT COUNT(*) FROM cases WHERE client_id = cp.client_id) as case_count
    FROM client_profiles cp
    JOIN users u ON cp.user_id = u.user_id
    JOIN cases c ON cp.client_id = c.client_id
    JOIN case_assignments ca ON c.case_id = ca.case_id
    WHERE $whereClause
    ORDER BY u.full_name ASC
    LIMIT ? OFFSET ?
");

$clientsStmt->bind_param($types . "ii", ...[...$params, $perPage, $offset]);
$clientsStmt->execute();
$clientsResult = $clientsStmt->get_result();
$clients = [];
while ($client = $clientsResult->fetch_assoc()) {
    $clients[] = $client;
}
$clientsStmt->close();

// Function to generate sort URL
function getSortUrl($column) {
    $params = $_GET;
    $params['sort'] = $column;
    $params['order'] = (isset($_GET['sort']) && $_GET['sort'] == $column && isset($_GET['order']) && $_GET['order'] == 'asc') ? 'desc' : 'asc';
    return '?' . http_build_query($params);
}

// Function to generate sort icon
function getSortIcon($column) {
    if (isset($_GET['sort']) && $_GET['sort'] == $column) {
        return $_GET['order'] == 'asc' ? '<i class="fas fa-sort-up ml-1"></i>' : '<i class="fas fa-sort-down ml-1"></i>';
    }
    return '<i class="fas fa-sort ml-1 text-gray-400"></i>';
}

// Close database connection
$conn->close();
?>

<div class="mb-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Clients</h1>
            <p class="text-gray-600">Manage your clients and their cases</p>
        </div>
        
        <div class="mt-4 md:mt-0">
            <a href="add.php" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-plus mr-2"></i> Add New Client
            </a>
        </div>
    </div>
</div>

<!-- Filters and Search -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <form action="" method="GET" class="space-y-4 md:space-y-0 md:flex md:items-end md:space-x-4">
        <div class="flex-grow">
            <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <div class="relative">
                <input type="text" id="search" name="search" class="form-input pl-10 w-full" placeholder="Search by name, email, or phone" value="<?php echo htmlspecialchars($search); ?>">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="fas fa-search text-gray-400"></i>
                </div>
            </div>
        </div>
        
        <div class="w-full md:w-48">
            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select id="status" name="status" class="form-select w-full">
                <option value="">All Statuses</option>
                <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
            </select>
        </div>
        
        <div class="flex space-x-2">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg">
                <i class="fas fa-filter mr-2"></i> Filter
            </button>
            
            <a href="index.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-times mr-2"></i> Clear
            </a>
        </div>
    </form>
</div>

<!-- Clients Table -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <?php if (count($clients) > 0): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('u.full_name'); ?>" class="flex items-center">
                                Client Name <?php echo getSortIcon('u.full_name'); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('u.email'); ?>" class="flex items-center">
                                Contact <?php echo getSortIcon('u.email'); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('cp.occupation'); ?>" class="flex items-center">
                                Occupation <?php echo getSortIcon('cp.occupation'); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('case_count'); ?>" class="flex items-center">
                                Cases <?php echo getSortIcon('case_count'); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('u.status'); ?>" class="flex items-center">
                                Status <?php echo getSortIcon('u.status'); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($clients as $client): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10 bg-blue-100 rounded-full flex items-center justify-center">
                                        <span class="text-blue-600 font-medium text-lg">
                                            <?php echo strtoupper(substr($client['full_name'], 0, 1)); ?>
                                        </span>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <a href="view.php?id=<?php echo $client['client_id']; ?>" class="hover:text-blue-600">
                                                <?php echo htmlspecialchars($client['full_name']); ?>
                                            </a>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            Client since <?php echo formatDate($client['created_at'], 'M Y'); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <a href="mailto:<?php echo htmlspecialchars($client['email']); ?>" class="hover:text-blue-600">
                                        <?php echo htmlspecialchars($client['email']); ?>
                                    </a>
                                </div>
                                <?php if (!empty($client['phone'])): ?>
                                    <div class="text-sm text-gray-500">
                                        <a href="tel:<?php echo htmlspecialchars($client['phone']); ?>" class="hover:text-blue-600">
                                            <?php echo htmlspecialchars($client['phone']); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <?php echo !empty($client['occupation']) ? htmlspecialchars($client['occupation']) : 'Not specified'; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <a href="../cases/index.php?client_id=<?php echo $client['client_id']; ?>" class="hover:text-blue-600">
                                        <?php echo $client['case_count']; ?> case<?php echo $client['case_count'] != 1 ? 's' : ''; ?>
                                    </a>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $statusClass = 'bg-gray-100 text-gray-800';
                                switch ($client['status']) {
                                    case 'active':
                                        $statusClass = 'bg-green-100 text-green-800';
                                        break;
                                    case 'inactive':
                                        $statusClass = 'bg-red-100 text-red-800';
                                        break;
                                    case 'pending':
                                        $statusClass = 'bg-yellow-100 text-yellow-800';
                                        break;
                                }
                                ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                    <?php echo ucfirst($client['status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex justify-end space-x-2">
                                    <a href="view.php?id=<?php echo $client['client_id']; ?>" class="text-blue-600 hover:text-blue-900" title="View Client">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="../cases/create.php?client_id=<?php echo $client['client_id']; ?>" class="text-green-600 hover:text-green-900" title="New Case">
                                        <i class="fas fa-plus-circle"></i>
                                    </a>
                                    <a href="../appointments/create.php?client_id=<?php echo $client['client_id']; ?>" class="text-purple-600 hover:text-purple-900" title="Schedule Appointment">
                                        <i class="fas fa-calendar-plus"></i>
                                    </a>
                                    <a href="../messages/compose.php?recipient=<?php echo $client['user_id']; ?>" class="text-indigo-600 hover:text-indigo-900" title="Send Message">
                                        <i class="fas fa-envelope"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                <div class="flex justify-between items-center">
                    <div class="text-sm text-gray-500">
                        Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $perPage, $totalClients); ?> of <?php echo $totalClients; ?> clients
                    </div>
                    <div class="flex space-x-1">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo ($page - 1); ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>" class="px-3 py-1 rounded-md bg-white border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Previous
                            </a>
                        <?php else: ?>
                            <span class="px-3 py-1 rounded-md bg-gray-100 border border-gray-300 text-sm font-medium text-gray-400 cursor-not-allowed">
                                Previous
                            </span>
                        <?php endif; ?>
                        
                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $startPage + 4);
                        if ($endPage - $startPage < 4 && $totalPages > 5) {
                            $startPage = max(1, $endPage - 4);
                        }
                        ?>
                        
                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="px-3 py-1 rounded-md bg-blue-600 border border-blue-600 text-sm font-medium text-white">
                                    <?php echo $i; ?>
                                </span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>" class="px-3 py-1 rounded-md bg-white border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    <?php echo $i; ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo ($page + 1); ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>" class="px-3 py-1 rounded-md bg-white border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Next
                            </a>
                        <?php else: ?>
                            <span class="px-3 py-1 rounded-md bg-gray-100 border border-gray-300 text-sm font-medium text-gray-400 cursor-not-allowed">
                                Next
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="text-center py-8">
            <div class="text-gray-400 mb-2">
                <i class="fas fa-users text-5xl"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-1">No clients found</h3>
            <p class="text-gray-500">
                <?php if (!empty($search) || !empty($statusFilter)): ?>
                    No clients match your search criteria. Try adjusting your filters.
                <?php else: ?>
                    You don't have any clients yet. Add your first client to get started.
                <?php endif; ?>
            </p>
            <div class="mt-4">
                <a href="add.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-plus mr-2"></i> Add New Client
                </a>
                <?php if (!empty($search) || !empty($statusFilter)): ?>
                    <a href="index.php" class="ml-3 inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-times mr-2"></i> Clear Filters
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.form-input, .form-select, .form-textarea {
    @apply mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500;
}
</style>

<?php
// Include footer
include_once '../includes/footer.php';
?>