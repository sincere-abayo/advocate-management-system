<?php
// Set page title
$pageTitle = "Case Management";

// Include header
include_once '../includes/header.php';

// Get advocate ID
$advocateId = $advocateData['advocate_id'];

// Create database connection
$conn = getDBConnection();

// Handle sorting
$sortField = isset($_GET['sort']) ? $_GET['sort'] : 'c.created_at';
$sortOrder = isset($_GET['order']) && $_GET['order'] == 'asc' ? 'ASC' : 'DESC';

// Validate sort field to prevent SQL injection
$allowedSortFields = ['c.case_number', 'c.title', 'c.case_type', 'c.status', 'c.priority', 'c.filing_date', 'c.hearing_date', 'c.created_at', 'client_name'];
if (!in_array($sortField, $allowedSortFields)) {
    $sortField = 'c.created_at';
}

// Handle filtering
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$priorityFilter = isset($_GET['priority']) ? $_GET['priority'] : '';
$caseTypeFilter = isset($_GET['case_type']) ? $_GET['case_type'] : '';
$searchTerm = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

// Build query conditions
$conditions = ["ca.advocate_id = ?"];
$params = [$advocateId];
$types = "i";

if (!empty($statusFilter)) {
    $conditions[] = "c.status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

if (!empty($priorityFilter)) {
    $conditions[] = "c.priority = ?";
    $params[] = $priorityFilter;
    $types .= "s";
}

if (!empty($caseTypeFilter)) {
    $conditions[] = "c.case_type = ?";
    $params[] = $caseTypeFilter;
    $types .= "s";
}

if (!empty($searchTerm)) {
    $conditions[] = "(c.case_number LIKE ? OR c.title LIKE ? OR u.full_name LIKE ?)";
    $searchParam = "%$searchTerm%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "sss";
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 10;
$offset = ($page - 1) * $recordsPerPage;

// Get total records count
$countQuery = "
    SELECT COUNT(DISTINCT c.case_id) as total
    FROM cases c
    JOIN case_assignments ca ON c.case_id = ca.case_id
    JOIN client_profiles cp ON c.client_id = cp.client_id
    JOIN users u ON cp.user_id = u.user_id
    WHERE " . implode(" AND ", $conditions);

$stmt = $conn->prepare($countQuery);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$totalRecords = $result->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Get case data
$query = "
    SELECT c.*, u.full_name as client_name, 
           (SELECT COUNT(*) FROM case_activities WHERE case_id = c.case_id) as activity_count,
           (SELECT COUNT(*) FROM documents WHERE case_id = c.case_id) as document_count
    FROM cases c
    JOIN case_assignments ca ON c.case_id = ca.case_id
    JOIN client_profiles cp ON c.client_id = cp.client_id
    JOIN users u ON cp.user_id = u.user_id
    WHERE " . implode(" AND ", $conditions) . "
    GROUP BY c.case_id
    ORDER BY $sortField $sortOrder
    LIMIT ?, ?";

$params[] = $offset;
$params[] = $recordsPerPage;
$types .= "ii";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Get case types for filter
$caseTypesQuery = "SELECT DISTINCT case_type FROM cases ORDER BY case_type";
$caseTypesResult = $conn->query($caseTypesQuery);
$caseTypes = [];
while ($row = $caseTypesResult->fetch_assoc()) {
    $caseTypes[] = $row['case_type'];
}

// Close connection
$stmt->close();
$conn->close();

// Helper function to generate sort URL
function getSortUrl($field) {
    $params = $_GET;
    $params['sort'] = $field;
    $params['order'] = (isset($_GET['sort']) && $_GET['sort'] == $field && isset($_GET['order']) && $_GET['order'] == 'asc') ? 'desc' : 'asc';
    return '?' . http_build_query($params);
}

// Helper function to get sort icon
function getSortIcon($field) {
    if (isset($_GET['sort']) && $_GET['sort'] == $field) {
        return isset($_GET['order']) && $_GET['order'] == 'asc' ? '<i class="fas fa-sort-up ml-1"></i>' : '<i class="fas fa-sort-down ml-1"></i>';
    }
    return '<i class="fas fa-sort ml-1 text-gray-400"></i>';
}

// Helper function to get status badge class
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'active':
            return 'bg-blue-100 text-blue-800';
        case 'pending':
            return 'bg-yellow-100 text-yellow-800';
        case 'closed':
            return 'bg-gray-100 text-gray-800';
        case 'won':
            return 'bg-green-100 text-green-800';
        case 'lost':
            return 'bg-red-100 text-red-800';
        case 'settled':
            return 'bg-purple-100 text-purple-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

// Helper function to get priority badge class
function getPriorityBadgeClass($priority) {
    switch ($priority) {
        case 'high':
            return 'bg-red-100 text-red-800';
        case 'medium':
            return 'bg-yellow-100 text-yellow-800';
        case 'low':
            return 'bg-green-100 text-green-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}
?>

<div class="mb-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
        <h1 class="text-2xl font-semibold text-gray-800">Case Management</h1>
        <div class="mt-4 md:mt-0">
            <a href="create.php" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-plus mr-2"></i> New Case
            </a>
        </div>
    </div>
    
    <p class="text-gray-600 mt-2">Manage all your legal cases in one place.</p>
</div>

<!-- Filters and Search -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <form action="" method="GET" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- Search -->
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <div class="relative">
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Case number, title, client..." class="form-input pl-10 w-full">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400"></i>
                    </div>
                </div>
            </div>
            
            <!-- Status Filter -->
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select id="status" name="status" class="form-select w-full">
                    <option value="">All Statuses</option>
                    <option value="active" <?php echo $statusFilter == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="pending" <?php echo $statusFilter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="closed" <?php echo $statusFilter == 'closed' ? 'selected' : ''; ?>>Closed</option>
                    <option value="won" <?php echo $statusFilter == 'won' ? 'selected' : ''; ?>>Won</option>
                    <option value="lost" <?php echo $statusFilter == 'lost' ? 'selected' : ''; ?>>Lost</option>
                    <option value="settled" <?php echo $statusFilter == 'settled' ? 'selected' : ''; ?>>Settled</option>
                </select>
            </div>
            
            <!-- Priority Filter -->
            <div>
                <label for="priority" class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                <select id="priority" name="priority" class="form-select w-full">
                    <option value="">All Priorities</option>
                    <option value="high" <?php echo $priorityFilter == 'high' ? 'selected' : ''; ?>>High</option>
                    <option value="medium" <?php echo $priorityFilter == 'medium' ? 'selected' : ''; ?>>Medium</option>
                    <option value="low" <?php echo $priorityFilter == 'low' ? 'selected' : ''; ?>>Low</option>
                </select>
            </div>
            
            <!-- Case Type Filter -->
            <div>
                <label for="case_type" class="block text-sm font-medium text-gray-700 mb-1">Case Type</label>
                <select id="case_type" name="case_type" class="form-select w-full">
                    <option value="">All Types</option>
                    <?php foreach ($caseTypes as $type): ?>
                        <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $caseTypeFilter == $type ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($type); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="flex justify-between">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-filter mr-2"></i> Apply Filters
            </button>
            
            <a href="index.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-times mr-2"></i> Clear Filters
            </a>
        </div>
    </form>
</div>

<!-- Cases Table -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <?php if ($result->num_rows > 0): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('c.case_number'); ?>" class="flex items-center">
                                Case Number <?php echo getSortIcon('c.case_number'); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('c.title'); ?>" class="flex items-center">
                                Title <?php echo getSortIcon('c.title'); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('client_name'); ?>" class="flex items-center">
                                Client <?php echo getSortIcon('client_name'); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('c.case_type'); ?>" class="flex items-center">
                                Type <?php echo getSortIcon('c.case_type'); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('c.status'); ?>" class="flex items-center">
                                Status <?php echo getSortIcon('c.status'); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('c.priority'); ?>" class="flex items-center">
                                Priority <?php echo getSortIcon('c.priority'); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('c.hearing_date'); ?>" class="flex items-center">
                                Next Hearing <?php echo getSortIcon('c.hearing_date'); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php while ($case = $result->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600">
                                <a href="<?php echo $path_url ?>advocate/cases/view.php?id=<?php echo $case['case_id']; ?>" class="hover:underline">
                                    <?php echo htmlspecialchars($case['case_number']); ?>
                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">
                                <?php echo htmlspecialchars($case['title']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                <?php echo htmlspecialchars($case['client_name']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                <?php echo htmlspecialchars($case['case_type']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo getStatusBadgeClass($case['status']); ?>">
                                    <?php echo ucfirst(htmlspecialchars($case['status'])); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo getPriorityBadgeClass($case['priority']); ?>">
                                    <?php echo ucfirst(htmlspecialchars($case['priority'])); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                <?php echo !empty($case['hearing_date']) ? formatDate($case['hearing_date']) : '<span class="text-gray-400">Not scheduled</span>'; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex justify-end space-x-2">
                                    <a href="<?php echo $path_url ?>advocate/cases/view.php?id=<?php echo $case['case_id']; ?>" class="text-blue-600 hover:text-blue-900" title="View Case">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="<?php echo $path_url ?>advocate/cases/edit.php?id=<?php echo $case['case_id']; ?>" class="text-indigo-600 hover:text-indigo-900" title="Edit Case">
                                        <i class="fas fa-edit"></i>
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
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-500">
                        Showing <?php echo ($page - 1) * $recordsPerPage + 1; ?> to <?php echo min($page * $recordsPerPage, $totalRecords); ?> of <?php echo $totalRecords; ?> cases
                    </div>
                    <div class="flex space-x-1">
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="px-3 py-1 rounded-md bg-white text-gray-600 border border-gray-300 hover:bg-gray-50">
                                Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="px-3 py-1 rounded-md <?php echo $i == $page ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 border border-gray-300 hover:bg-gray-50'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="px-3 py-1 rounded-md bg-white text-gray-600 border border-gray-300 hover:bg-gray-50">
                                Next
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="p-8 text-center">
            <div class="text-gray-400 mb-4">
                <i class="fas fa-folder-open text-5xl"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-1">No cases found</h3>
            <p class="text-gray-500 mb-6">There are no cases matching your search criteria.</p>
            <a href="create.php" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-plus mr-2"></i> Create New Case
            </a>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-submit form when filters change
    const filterForm = document.querySelector('form');
    const filterInputs = filterForm.querySelectorAll('select');
    
    filterInputs.forEach(input => {
        input.addEventListener('change', function() {
            filterForm.submit();
        });
    });
});
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>
