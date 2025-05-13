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
$status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'filing_date';
$order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Build query based on filters
$whereConditions = ["client_id = ?"];
$params = [$clientId];
$types = "i";

if (!empty($status)) {
    $whereConditions[] = "status = ?";
    $params[] = $status;
    $types .= "s";
}

if (!empty($search)) {
    $whereConditions[] = "(case_number LIKE ? OR title LIKE ? OR description LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "sss";
}

$whereClause = implode(" AND ", $whereConditions);

// Valid sort columns to prevent SQL injection
$validSortColumns = ['case_number', 'title', 'filing_date', 'status', 'priority'];
if (!in_array($sort, $validSortColumns)) {
    $sort = 'filing_date';
}

// Valid order directions
$order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

// Get cases
$query = "
    SELECT c.*, 
           (SELECT COUNT(*) FROM case_hearings ch WHERE ch.case_id = c.case_id) as hearing_count,
           (SELECT COUNT(*) FROM documents d WHERE d.case_id = c.case_id) as document_count
    FROM cases c
    WHERE $whereClause
    ORDER BY $sort $order
";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Get case statuses for filter dropdown
$statusQuery = "SELECT DISTINCT status FROM cases WHERE client_id = ? ORDER BY status";
$statusStmt = $conn->prepare($statusQuery);
$statusStmt->bind_param("i", $clientId);
$statusStmt->execute();
$statusResult = $statusStmt->get_result();

$statuses = [];
while ($row = $statusResult->fetch_assoc()) {
    $statuses[] = $row['status'];
}

// Get case statistics
$statsQuery = "
    SELECT 
        COUNT(*) as total_cases,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_cases,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_cases,
        SUM(CASE WHEN status IN ('closed', 'won', 'lost', 'settled') THEN 1 ELSE 0 END) as closed_cases
    FROM cases
    WHERE client_id = ?
";
$statsStmt = $conn->prepare($statsQuery);
$statsStmt->bind_param("i", $clientId);
$statsStmt->execute();
$caseStats = $statsStmt->get_result()->fetch_assoc();

// Close connection
$conn->close();

// Set page title
$pageTitle = "My Cases";
include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">My Cases</h1>
            <p class="text-gray-600">View and manage all your legal cases</p>
        </div>
    </div>
    
    <!-- Case Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-500 mr-4">
                    <i class="fas fa-briefcase text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Total Cases</p>
                    <p class="text-2xl font-semibold"><?php echo $caseStats['total_cases']; ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-500 mr-4">
                    <i class="fas fa-play-circle text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Active Cases</p>
                    <p class="text-2xl font-semibold"><?php echo $caseStats['active_cases']; ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-yellow-100 text-yellow-500 mr-4">
                    <i class="fas fa-clock text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Pending Cases</p>
                    <p class="text-2xl font-semibold"><?php echo $caseStats['pending_cases']; ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-gray-100 text-gray-500 mr-4">
                    <i class="fas fa-check-circle text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Closed Cases</p>
                    <p class="text-2xl font-semibold"><?php echo $caseStats['closed_cases']; ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form action="" method="GET" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="status" name="status" class="form-select w-full">
                        <option value="">All Statuses</option>
                        <?php foreach ($statuses as $statusOption): ?>
                            <option value="<?php echo $statusOption; ?>" <?php echo $status === $statusOption ? 'selected' : ''; ?>>
                                <?php echo ucfirst($statusOption); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="sort" class="block text-sm font-medium text-gray-700 mb-1">Sort By</label>
                    <select id="sort" name="sort" class="form-select w-full">
                        <option value="filing_date" <?php echo $sort === 'filing_date' ? 'selected' : ''; ?>>Filing Date</option>
                        <option value="case_number" <?php echo $sort === 'case_number' ? 'selected' : ''; ?>>Case Number</option>
                        <option value="title" <?php echo $sort === 'title' ? 'selected' : ''; ?>>Title</option>
                        <option value="status" <?php echo $sort === 'status' ? 'selected' : ''; ?>>Status</option>
                        <option value="priority" <?php echo $sort === 'priority' ? 'selected' : ''; ?>>Priority</option>
                    </select>
                </div>
                
                <div>
                    <label for="order" class="block text-sm font-medium text-gray-700 mb-1">Order</label>
                    <select id="order" name="order" class="form-select w-full">
                        <option value="DESC" <?php echo $order === 'DESC' ? 'selected' : ''; ?>>Descending</option>
                        <option value="ASC" <?php echo $order === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                    </select>
                </div>
            </div>
            
            <div class="flex flex-col md:flex-row gap-4">
                <div class="flex-grow">
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input type="text" id="search" name="search" class="form-input w-full" placeholder="Search by case number, title, or description" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg mr-2">
                        <i class="fas fa-search mr-2"></i> Filter
                    </button>
                    
                    <a href="index.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg">
                        <i class="fas fa-times mr-2"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Cases List -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <?php if ($result->num_rows === 0): ?>
            <div class="text-center py-8">
                <div class="text-gray-400 mb-2">
                    <i class="fas fa-briefcase text-5xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-1">No cases found</h3>
                <p class="text-gray-500">
                    <?php if (!empty($search) || !empty($status)): ?>
                        Try adjusting your search filters
                    <?php else: ?>
                        You don't have any cases yet
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Case Number
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Title & Type
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Filing Date
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Details
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while ($case = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($case['case_number']); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($case['title']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($case['case_type']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($case['filing_date'])); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $statusClass = 'bg-gray-100 text-gray-800';
                                    switch ($case['status']) {
                                        case 'active':
                                            $statusClass = 'bg-green-100 text-green-800';
                                            break;
                                        case 'pending':
                                            $statusClass = 'bg-yellow-100 text-yellow-800';
                                            break;
                                        case 'won':
                                            $statusClass = 'bg-blue-100 text-blue-800';
                                            break;
                                        case 'lost':
                                            $statusClass = 'bg-red-100 text-red-800';
                                            break;
                                        case 'settled':
                                            $statusClass = 'bg-purple-100 text-purple-800';
                                            break;
                                        case 'closed':
                                            $statusClass = 'bg-gray-100 text-gray-800';
                                            break;
                                    }
                                    ?>
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                        <?php echo ucfirst($case['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center space-x-4">
                                    <div class="flex items-center">
                                            <i class="fas fa-gavel text-purple-500 mr-1"></i>
                                            <span class="text-sm text-gray-600"><?php echo $case['hearing_count']; ?> Hearings</span>
                                        </div>
                                        <div class="flex items-center">
                                            <i class="fas fa-file-alt text-blue-500 mr-1"></i>
                                            <span class="text-sm text-gray-600"><?php echo $case['document_count']; ?> Documents</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="view.php?id=<?php echo $case['case_id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="../documents/index.php?case_id=<?php echo $case['case_id']; ?>" class="text-green-600 hover:text-green-900">
                                        <i class="fas fa-file-alt"></i> Documents
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Include footer
include '../includes/footer.php';
?>
