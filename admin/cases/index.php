<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user is logged in and is an admin
requireLogin();
requireUserType('admin');

// Get database connection
$conn = getDBConnection();

// Initialize filters
$filters = [
    'search' => isset($_GET['search']) ? $_GET['search'] : '',
    'status' => isset($_GET['status']) ? $_GET['status'] : '',
    'case_type' => isset($_GET['case_type']) ? $_GET['case_type'] : '',
    'priority' => isset($_GET['priority']) ? $_GET['priority'] : '',
    'start_date' => isset($_GET['start_date']) ? $_GET['start_date'] : '',
    'end_date' => isset($_GET['end_date']) ? $_GET['end_date'] : '',
    'sort' => isset($_GET['sort']) ? $_GET['sort'] : 'c.created_at',
    'order' => isset($_GET['order']) ? $_GET['order'] : 'desc'
];

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Build query based on filters
$whereConditions = [];
$params = [];
$types = "";

if (!empty($filters['search'])) {
    $whereConditions[] = "(c.case_number LIKE ? OR c.title LIKE ? OR c.description LIKE ?)";
    $searchTerm = "%" . $filters['search'] . "%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "sss";
}

if (!empty($filters['status'])) {
    $whereConditions[] = "c.status = ?";
    $params[] = $filters['status'];
    $types .= "s";
}

if (!empty($filters['case_type'])) {
    $whereConditions[] = "c.case_type = ?";
    $params[] = $filters['case_type'];
    $types .= "s";
}

if (!empty($filters['priority'])) {
    $whereConditions[] = "c.priority = ?";
    $params[] = $filters['priority'];
    $types .= "s";
}

if (!empty($filters['start_date'])) {
    $whereConditions[] = "c.filing_date >= ?";
    $params[] = $filters['start_date'];
    $types .= "s";
}

if (!empty($filters['end_date'])) {
    $whereConditions[] = "c.filing_date <= ?";
    $params[] = $filters['end_date'];
    $types .= "s";
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Valid sort columns to prevent SQL injection
$validSortColumns = ['c.case_id', 'c.case_number', 'c.title', 'c.status', 'c.priority', 'c.filing_date', 'c.hearing_date', 'c.created_at', 'client_name'];
if (!in_array($filters['sort'], $validSortColumns)) {
    $filters['sort'] = 'c.created_at';
}

$sortDirection = $filters['order'] === 'asc' ? 'ASC' : 'DESC';

// Get total count for pagination
$countQuery = "
    SELECT COUNT(*) as total 
    FROM cases c
    LEFT JOIN client_profiles cp ON c.client_id = cp.client_id
    LEFT JOIN users u ON cp.user_id = u.user_id
    $whereClause
";
$countStmt = $conn->prepare($countQuery);

if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}

$countStmt->execute();
$totalCases = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalCases / $perPage);

// Ensure current page is within valid range
if ($page < 1) $page = 1;
if ($page > $totalPages && $totalPages > 0) $page = $totalPages;

// Get cases with pagination and sorting
$query = "
    SELECT 
        c.case_id, 
        c.case_number, 
        c.title, 
        c.description,
        c.case_type,
        c.court,
        c.filing_date,
        c.hearing_date,
        c.status,
        c.priority,
        c.created_at,
        c.updated_at,
        u.full_name as client_name,
        cp.client_id,
        (SELECT COUNT(*) FROM case_activities WHERE case_id = c.case_id) as activity_count,
        (SELECT COUNT(*) FROM documents WHERE case_id = c.case_id) as document_count,
        (SELECT COUNT(*) FROM case_hearings WHERE case_id = c.case_id) as hearing_count,
        (SELECT GROUP_CONCAT(DISTINCT u2.full_name SEPARATOR ', ') 
         FROM case_assignments ca 
         JOIN advocate_profiles ap ON ca.advocate_id = ap.advocate_id
         JOIN users u2 ON ap.user_id = u2.user_id
         WHERE ca.case_id = c.case_id) as advocates
    FROM cases c
    LEFT JOIN client_profiles cp ON c.client_id = cp.client_id
    LEFT JOIN users u ON cp.user_id = u.user_id
    $whereClause
    ORDER BY {$filters['sort']} $sortDirection
    LIMIT ?, ?
";

$stmt = $conn->prepare($query);
$params[] = $offset;
$params[] = $perPage;
$types .= "ii";
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Get case statistics for summary
$caseStatsQuery = "
    SELECT 
        COUNT(*) as total_cases,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_cases,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_cases,
        SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_cases,
        SUM(CASE WHEN status = 'won' THEN 1 ELSE 0 END) as won_cases,
        SUM(CASE WHEN status = 'lost' THEN 1 ELSE 0 END) as lost_cases,
        SUM(CASE WHEN status = 'settled' THEN 1 ELSE 0 END) as settled_cases,
        SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high_priority_cases,
        SUM(CASE WHEN priority = 'medium' THEN 1 ELSE 0 END) as medium_priority_cases,
        SUM(CASE WHEN priority = 'low' THEN 1 ELSE 0 END) as low_priority_cases
    FROM cases
";
$caseStatsResult = $conn->query($caseStatsQuery);
$caseStats = $caseStatsResult->fetch_assoc();

// Get case types for filter dropdown
$caseTypesQuery = "SELECT DISTINCT case_type FROM cases ORDER BY case_type";
$caseTypesResult = $conn->query($caseTypesQuery);
$caseTypes = [];
while ($row = $caseTypesResult->fetch_assoc()) {
    $caseTypes[] = $row['case_type'];
}

// Helper function to generate sort URL
function getSortUrl($column) {
    global $filters;
    $params = $_GET;
    $params['sort'] = $column;
    $params['order'] = ($filters['sort'] === $column && $filters['order'] === 'desc') ? 'asc' : 'desc';
    return '?' . http_build_query($params);
}

// Helper function to generate sort icon
function getSortIcon($column) {
    global $filters;
    if ($filters['sort'] !== $column) {
        return '<i class="fas fa-sort text-gray-400 ml-1"></i>';
    }
    return ($filters['order'] === 'asc') 
        ? '<i class="fas fa-sort-up text-blue-500 ml-1"></i>' 
        : '<i class="fas fa-sort-down text-blue-500 ml-1"></i>';
}

// Set page title
$pageTitle = "Case Management";
include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Case Management</h1>
            <p class="text-gray-600">Manage all cases in the system</p>
        </div>
    </div>
    
    <!-- Case Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <!-- Status Card -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-500 mr-4">
                    <i class="fas fa-briefcase text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Total Cases</p>
                    <p class="text-2xl font-semibold"><?php echo number_format($caseStats['total_cases']); ?></p>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex justify-between text-sm">
                    <span class="text-yellow-500"><?php echo number_format($caseStats['pending_cases']); ?> Pending</span>
                    <span class="text-blue-500"><?php echo number_format($caseStats['active_cases']); ?> Active</span>
                    <span class="text-gray-500"><?php echo number_format($caseStats['closed_cases']); ?> Closed</span>
                </div>
            </div>
        </div>
        
        <!-- Outcome Card -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-500 mr-4">
                    <i class="fas fa-chart-pie text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Case Outcomes</p>
                    <p class="text-2xl font-semibold"><?php echo number_format($caseStats['closed_cases']); ?> Closed</p>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex justify-between text-sm">
                    <span class="text-green-500"><?php echo number_format($caseStats['won_cases']); ?> Won</span>
                    <span class="text-red-500"><?php echo number_format($caseStats['lost_cases']); ?> Lost</span>
                    <span class="text-blue-500"><?php echo number_format($caseStats['settled_cases']); ?> Settled</span>
                </div>
            </div>
        </div>
        
        <!-- Priority Card -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-red-100 text-red-500 mr-4">
                    <i class="fas fa-flag text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Case Priorities</p>
                    <p class="text-2xl font-semibold"><?php echo number_format($caseStats['high_priority_cases']); ?> High Priority</p>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex justify-between text-sm">
                    <span class="text-red-500"><?php echo number_format($caseStats['high_priority_cases']); ?> High</span>
                    <span class="text-yellow-500"><?php echo number_format($caseStats['medium_priority_cases']); ?> Medium</span>
                    <span class="text-green-500"><?php echo number_format($caseStats['low_priority_cases']); ?> Low</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form action="" method="GET" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input type="text" id="search" name="search" class="form-input w-full" placeholder="Search by case number, title, or description" value="<?php echo htmlspecialchars($filters['search']); ?>">
                </div>
                
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="status" name="status" class="form-select w-full">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php echo $filters['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="closed" <?php echo $filters['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                        <option value="won" <?php echo $filters['status'] === 'won' ? 'selected' : ''; ?>>Won</option>
                        <option value="lost" <?php echo $filters['status'] === 'lost' ? 'selected' : ''; ?>>Lost</option>
                        <option value="settled" <?php echo $filters['status'] === 'settled' ? 'selected' : ''; ?>>Settled</option>
                    </select>
                </div>
                
                <div>
                    <label for="case_type" class="block text-sm font-medium text-gray-700 mb-1">Case Type</label>
                    <select id="case_type" name="case_type" class="form-select w-full">
                        <option value="">All Types</option>
                        <?php foreach ($caseTypes as $type): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $filters['case_type'] === $type ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="priority" class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                    <select id="priority" name="priority" class="form-select w-full">
                        <option value="">All Priorities</option>
                        <option value="high" <?php echo $filters['priority'] === 'high' ? 'selected' : ''; ?>>High</option>
                        <option value="medium" <?php echo $filters['priority'] === 'medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="low" <?php echo $filters['priority'] === 'low' ? 'selected' : ''; ?>>Low</option>
                    </select>
                </div>
                
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                    <input type="date" id="start_date" name="start_date" class="form-input w-full" value="<?php echo htmlspecialchars($filters['start_date']); ?>">
                </div>
                
                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                    <input type="date" id="end_date" name="end_date" class="form-input w-full" value="<?php echo htmlspecialchars($filters['end_date']); ?>">
                </div>
            </div>
            
            <div class="flex items-center space-x-2">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg">
                    <i class="fas fa-filter mr-2"></i> Apply Filters
                </button>
                
                <a href="index.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg">
                    <i class="fas fa-times mr-2"></i> Clear Filters
                </a>
            </div>
            
            <!-- Preserve sort parameters -->
            <?php if (isset($_GET['sort'])): ?>
                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($_GET['sort']); ?>">
            <?php endif; ?>
            
            <?php if (isset($_GET['order'])): ?>
                <input type="hidden" name="order" value="<?php echo htmlspecialchars($_GET['order']); ?>">
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Cases Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <?php if ($result->num_rows === 0): ?>
            <div class="text-center py-8">
                <div class="text-gray-400 mb-3"><i class="fas fa-briefcase text-5xl"></i></div>
                <h3 class="text-lg font-medium text-gray-900 mb-1">No cases found</h3>
                <p class="text-gray-500 mb-6">
                    <?php if (!empty($filters['search']) || !empty($filters['status']) || !empty($filters['case_type']) || !empty($filters['priority']) || !empty($filters['start_date']) || !empty($filters['end_date'])): ?>
                        Try adjusting your filters or search criteria
                    <?php else: ?>
                        There are no cases in the system yet
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
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
                                <a href="<?php echo getSortUrl('c.filing_date'); ?>" class="flex items-center">
                                    Filing Date <?php echo getSortIcon('c.filing_date'); ?>
                                </a>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Advocates
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
                                    <a href="view.php?id=<?php echo $case['case_id']; ?>" class="hover:underline">
                                        <?php echo htmlspecialchars($case['case_number']); ?>
                                    </a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($case['title']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php if (!empty($case['client_name'])): ?>
                                        <a href="../clients/view.php?id=<?php echo $case['client_id']; ?>" class="text-blue-600 hover:underline">
                                            <?php echo htmlspecialchars($case['client_name']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-400">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $statusClass = 'bg-gray-100 text-gray-800';
                                    switch ($case['status']) {
                                        case 'pending':
                                            $statusClass = 'bg-yellow-100 text-yellow-800';
                                            break;
                                        case 'active':
                                            $statusClass = 'bg-blue-100 text-blue-800';
                                            break;
                                        case 'closed':
                                            $statusClass = 'bg-gray-100 text-gray-800';
                                            break;
                                        case 'won':
                                            $statusClass = 'bg-green-100 text-green-800';
                                            break;
                                        case 'lost':
                                            $statusClass = 'bg-red-100 text-red-800';
                                            break;
                                        case 'settled':
                                            $statusClass = 'bg-indigo-100 text-indigo-800';
                                            break;
                                    }
                                    ?>
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                        <?php echo ucfirst($case['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $priorityClass = 'bg-gray-100 text-gray-800';
                                    switch ($case['priority']) {
                                        case 'high':
                                            $priorityClass = 'bg-red-100 text-red-800';
                                            break;
                                        case 'medium':
                                            $priorityClass = 'bg-yellow-100 text-yellow-800';
                                            break;
                                        case 'low':
                                            $priorityClass = 'bg-green-100 text-green-800';
                                            break;
                                    }
                                    ?>
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $priorityClass; ?>">
                                        <?php echo ucfirst($case['priority']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo !empty($case['filing_date']) ? formatDate($case['filing_date']) : 'Not set'; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo !empty($case['advocates']) ? htmlspecialchars($case['advocates']) : 'None assigned'; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end space-x-2">
                                        <a href="view.php?id=<?php echo $case['case_id']; ?>" class="text-blue-600 hover:text-blue-900" title="View">
                                            <i class="fas fa-eye"></i>
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
                            Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $perPage, $totalCases); ?> of <?php echo $totalCases; ?> cases
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
        <?php endif; ?>
    </div>
</div>

<?php
// Include footer
include '../includes/footer.php';
?>
