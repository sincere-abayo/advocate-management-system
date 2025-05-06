<?php
// Set page title
$pageTitle = "User Management";
include '../includes/header.php';
// Check if user is logged in and is an admin
requireLogin();
requireUserType('admin');

// Get database connection
$conn = getDBConnection();

// Initialize filters
$filters = [
    'search' => isset($_GET['search']) ? $_GET['search'] : '',
    'user_type' => isset($_GET['user_type']) ? $_GET['user_type'] : '',
    'status' => isset($_GET['status']) ? $_GET['status'] : '',
    'sort' => isset($_GET['sort']) ? $_GET['sort'] : 'created_at',
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
    $whereConditions[] = "(username LIKE ? OR email LIKE ? OR full_name LIKE ?)";
    $searchTerm = "%" . $filters['search'] . "%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "sss";
}

if (!empty($filters['user_type'])) {
    $whereConditions[] = "user_type = ?";
    $params[] = $filters['user_type'];
    $types .= "s";
}

if (!empty($filters['status'])) {
    $whereConditions[] = "status = ?";
    $params[] = $filters['status'];
    $types .= "s";
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Valid sort columns to prevent SQL injection
$validSortColumns = ['user_id', 'username', 'email', 'full_name', 'user_type', 'status', 'created_at', 'updated_at'];
if (!in_array($filters['sort'], $validSortColumns)) {
    $filters['sort'] = 'created_at';
}

$sortDirection = $filters['order'] === 'asc' ? 'ASC' : 'DESC';

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total FROM users $whereClause";
$countStmt = $conn->prepare($countQuery);

if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}

$countStmt->execute();
$totalUsers = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalUsers / $perPage);

// Ensure current page is within valid range
if ($page < 1) $page = 1;
if ($page > $totalPages && $totalPages > 0) $page = $totalPages;

// Get users with pagination and sorting
$query = "
    SELECT 
        u.user_id, 
        u.username, 
        u.email, 
        u.full_name, 
        u.phone, 
        u.user_type, 
        u.status, 
        u.created_at, 
        u.updated_at,
        CASE 
            WHEN u.user_type = 'advocate' THEN ap.license_number
            ELSE NULL
        END as license_number,
        CASE 
            WHEN u.user_type = 'advocate' THEN ap.specialization
            ELSE NULL
        END as specialization,
        CASE 
            WHEN u.user_type = 'client' THEN cp.occupation
            ELSE NULL
        END as occupation
    FROM users u
    LEFT JOIN advocate_profiles ap ON u.user_id = ap.user_id AND u.user_type = 'advocate'
    LEFT JOIN client_profiles cp ON u.user_id = cp.user_id AND u.user_type = 'client'
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

// Get user type counts for summary
$userTypesQuery = "
    SELECT 
        user_type, 
        COUNT(*) as count,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended_count,
        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_count
    FROM users 
    GROUP BY user_type
";
$userTypesResult = $conn->query($userTypesQuery);
$userTypeCounts = [];

while ($row = $userTypesResult->fetch_assoc()) {
    $userTypeCounts[$row['user_type']] = $row;
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



?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">User Management</h1>
            <p class="text-gray-600">Manage all users in the system</p>
        </div>
        
        <!-- <div class="mt-4 md:mt-0">
            <a href="create.php" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-user-plus mr-2"></i> Add New User
            </a>
        </div> -->
    </div>
    
    <!-- User Type Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <!-- Advocates Card -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-500 mr-4">
                    <i class="fas fa-user-tie text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Advocates</p>
                    <p class="text-2xl font-semibold"><?php echo isset($userTypeCounts['advocate']) ? number_format($userTypeCounts['advocate']['count']) : 0; ?></p>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex justify-between text-sm">
                    <span class="text-green-500"><?php echo isset($userTypeCounts['advocate']) ? number_format($userTypeCounts['advocate']['active_count']) : 0; ?> Active</span>
                    <span class="text-yellow-500"><?php echo isset($userTypeCounts['advocate']) ? number_format($userTypeCounts['advocate']['pending_count']) : 0; ?> Pending</span>
                    <span class="text-red-500"><?php echo isset($userTypeCounts['advocate']) ? number_format($userTypeCounts['advocate']['suspended_count']) : 0; ?> Suspended</span>
                </div>
            </div>
        </div>
        
        <!-- Clients Card -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-500 mr-4">
                    <i class="fas fa-user-friends text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Clients</p>
                    <p class="text-2xl font-semibold"><?php echo isset($userTypeCounts['client']) ? number_format($userTypeCounts['client']['count']) : 0; ?></p>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex justify-between text-sm">
                    <span class="text-green-500"><?php echo isset($userTypeCounts['client']) ? number_format($userTypeCounts['client']['active_count']) : 0; ?> Active</span>
                    <span class="text-yellow-500"><?php echo isset($userTypeCounts['client']) ? number_format($userTypeCounts['client']['pending_count']) : 0; ?> Pending</span>
                    <span class="text-red-500"><?php echo isset($userTypeCounts['client']) ? number_format($userTypeCounts['client']['suspended_count']) : 0; ?> Suspended</span>
                </div>
            </div>
        </div>
        
        <!-- Admins Card -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-100 text-purple-500 mr-4">
                    <i class="fas fa-user-shield text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Administrators</p>
                    <p class="text-2xl font-semibold"><?php echo isset($userTypeCounts['admin']) ? number_format($userTypeCounts['admin']['count']) : 0; ?></p>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex justify-between text-sm">
                    <span class="text-green-500"><?php echo isset($userTypeCounts['admin']) ? number_format($userTypeCounts['admin']['active_count']) : 0; ?> Active</span>
                    <span class="text-yellow-500"><?php echo isset($userTypeCounts['admin']) ? number_format($userTypeCounts['admin']['pending_count']) : 0; ?> Pending</span>
                    <span class="text-red-500"><?php echo isset($userTypeCounts['admin']) ? number_format($userTypeCounts['admin']['suspended_count']) : 0; ?> Suspended</span>
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
                    <input type="text" id="search" name="search" class="form-input w-full" placeholder="Search by name, email, or username" value="<?php echo htmlspecialchars($filters['search']); ?>">
                </div>
                
                <div>
                    <label for="user_type" class="block text-sm font-medium text-gray-700 mb-1">User Type</label>
                    <select id="user_type" name="user_type" class="form-select w-full">
                        <option value="">All Types</option>
                        <option value="advocate" <?php echo $filters['user_type'] === 'advocate' ? 'selected' : ''; ?>>Advocates</option>
                        <option value="client" <?php echo $filters['user_type'] === 'client' ? 'selected' : ''; ?>>Clients</option>
                        <option value="admin" <?php echo $filters['user_type'] === 'admin' ? 'selected' : ''; ?>>Administrators</option>
                    </select>
                </div>
                
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="status" name="status" class="form-select w-full">
                        <option value="">All Statuses</option>
                        <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="pending" <?php echo $filters['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="suspended" <?php echo $filters['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        <option value="inactive" <?php echo $filters['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
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
    
     <!-- Users Table -->
     <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <?php if ($result->num_rows === 0): ?>
            <div class="text-center py-8">
                <div class="text-gray-400 mb-3"><i class="fas fa-users text-5xl"></i></div>
                <h3 class="text-lg font-medium text-gray-900 mb-1">No users found</h3>
                <p class="text-gray-500 mb-6">
                    <?php if (!empty($filters['search']) || !empty($filters['user_type']) || !empty($filters['status'])): ?>
                        Try adjusting your filters or search criteria
                    <?php else: ?>
                        There are no users in the system yet
                    <?php endif; ?>
                </p>
                <a href="create.php" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                    <i class="fas fa-user-plus mr-2"></i> Add New User
                </a>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <a href="<?php echo getSortUrl('user_id'); ?>" class="flex items-center">
                                    ID <?php echo getSortIcon('user_id'); ?>
                                </a>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <a href="<?php echo getSortUrl('full_name'); ?>" class="flex items-center">
                                    Name <?php echo getSortIcon('full_name'); ?>
                                </a>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <a href="<?php echo getSortUrl('email'); ?>" class="flex items-center">
                                    Email <?php echo getSortIcon('email'); ?>
                                </a>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <a href="<?php echo getSortUrl('user_type'); ?>" class="flex items-center">
                                    Type <?php echo getSortIcon('user_type'); ?>
                                </a>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <a href="<?php echo getSortUrl('status'); ?>" class="flex items-center">
                                    Status <?php echo getSortIcon('status'); ?>
                                </a>
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <a href="<?php echo getSortUrl('created_at'); ?>" class="flex items-center">
                                    Registered <?php echo getSortIcon('created_at'); ?>
                                </a>
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while ($user = $result->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $user['user_id']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center text-gray-600 overflow-hidden">
                                            <?php if (!empty($user['profile_image'])): ?>
                                                <img src="<?php echo $path_url . $user['profile_image']; ?>" alt="Profile" class="h-full w-full object-cover">
                                            <?php else: ?>
                                                <i class="fas fa-user"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($user['full_name']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                @<?php echo htmlspecialchars($user['username']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>" class="text-blue-600 hover:text-blue-900">
                                        <?php echo htmlspecialchars($user['email']); ?>
                                    </a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $typeClass = 'bg-gray-100 text-gray-800';
                                    switch ($user['user_type']) {
                                        case 'advocate':
                                            $typeClass = 'bg-blue-100 text-blue-800';
                                            break;
                                        case 'client':
                                            $typeClass = 'bg-green-100 text-green-800';
                                            break;
                                        case 'admin':
                                            $typeClass = 'bg-purple-100 text-purple-800';
                                            break;
                                    }
                                    ?>
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $typeClass; ?>">
                                        <?php echo ucfirst($user['user_type']); ?>
                                    </span>
                                    <?php if ($user['user_type'] === 'advocate' && !empty($user['specialization'])): ?>
                                        <div class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($user['specialization']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $statusClass = 'bg-gray-100 text-gray-800';
                                    switch ($user['status']) {
                                        case 'active':
                                            $statusClass = 'bg-green-100 text-green-800';
                                            break;
                                        case 'pending':
                                            $statusClass = 'bg-yellow-100 text-yellow-800';
                                            break;
                                        case 'suspended':
                                            $statusClass = 'bg-red-100 text-red-800';
                                            break;
                                        case 'inactive':
                                            $statusClass = 'bg-gray-100 text-gray-800';
                                            break;
                                    }
                                    ?>
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo formatDateTimeRelative($user['created_at']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end space-x-2">
                                        <a href="view.php?id=<?php echo $user['user_id']; ?>" class="text-blue-600 hover:text-blue-900" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($user['status'] === 'active'): ?>
                                            <a href="status.php?id=<?php echo $user['user_id']; ?>&action=suspend" class="text-yellow-600 hover:text-yellow-900" title="Suspend" onclick="return confirm('Are you sure you want to suspend this user?');">
                                                <i class="fas fa-ban"></i>
                                            </a>
                                        <?php elseif ($user['status'] === 'suspended'): ?>
                                            <a href="status.php?id=<?php echo $user['user_id']; ?>&action=activate" class="text-green-600 hover:text-green-900" title="Activate" onclick="return confirm('Are you sure you want to activate this user?');">
                                                <i class="fas fa-check-circle"></i>
                                            </a>
                                        <?php elseif ($user['status'] === 'pending'): ?>
                                            <a href="status.php?id=<?php echo $user['user_id']; ?>&action=approve" class="text-green-600 hover:text-green-900" title="Approve" onclick="return confirm('Are you sure you want to approve this user?');">
                                                <i class="fas fa-check-circle"></i>
                                            </a>
                                        <?php endif; ?>
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
                            Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $perPage, $totalUsers); ?> of <?php echo $totalUsers; ?> users
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
