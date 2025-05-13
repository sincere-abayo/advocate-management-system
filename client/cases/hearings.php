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
$caseId = isset($_GET['case_id']) ? (int)$_GET['case_id'] : 0;
$status = isset($_GET['status']) ? $_GET['status'] : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query based on filters
$whereConditions = ["c.client_id = ?"];
$params = [$clientId];
$types = "i";

if ($caseId > 0) {
    $whereConditions[] = "c.case_id = ?";
    $params[] = $caseId;
    $types .= "i";
}

if (!empty($status)) {
    $whereConditions[] = "ch.status = ?";
    $params[] = $status;
    $types .= "s";
}

if (!empty($dateFrom)) {
    $whereConditions[] = "ch.hearing_date >= ?";
    $params[] = $dateFrom;
    $types .= "s";
}

if (!empty($dateTo)) {
    $whereConditions[] = "ch.hearing_date <= ?";
    $params[] = $dateTo;
    $types .= "s";
}

if (!empty($search)) {
    $whereConditions[] = "(ch.hearing_type LIKE ? OR ch.court_room LIKE ? OR ch.judge LIKE ? OR ch.description LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "ssss";
}

$whereClause = implode(" AND ", $whereConditions);

// Get hearings
$query = "
    SELECT 
        ch.*,
        c.case_number,
        c.title,
        c.case_type,
        c.status as case_status
    FROM case_hearings ch
    JOIN cases c ON ch.case_id = c.case_id
    WHERE $whereClause
    ORDER BY ch.hearing_date ASC, ch.hearing_time ASC
";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Get cases for filter dropdown
$casesQuery = "SELECT case_id, case_number, title FROM cases WHERE client_id = ? ORDER BY case_number";
$casesStmt = $conn->prepare($casesQuery);
$casesStmt->bind_param("i", $clientId);
$casesStmt->execute();
$casesResult = $casesStmt->get_result();

$cases = [];
while ($case = $casesResult->fetch_assoc()) {
    $cases[] = $case;
}

// Get hearing statistics
$statsQuery = "
    SELECT 
        COUNT(*) as total_hearings,
        SUM(CASE WHEN ch.hearing_date >= CURDATE() THEN 1 ELSE 0 END) as upcoming_hearings,
        SUM(CASE WHEN ch.status = 'completed' THEN 1 ELSE 0 END) as completed_hearings,
        SUM(CASE WHEN ch.status = 'postponed' THEN 1 ELSE 0 END) as postponed_hearings,
        MIN(CASE WHEN ch.hearing_date >= CURDATE() THEN ch.hearing_date ELSE NULL END) as next_hearing_date
    FROM case_hearings ch
    JOIN cases c ON ch.case_id = c.case_id
    WHERE c.client_id = ?
";
$statsStmt = $conn->prepare($statsQuery);
$statsStmt->bind_param("i", $clientId);
$statsStmt->execute();
$hearingStats = $statsStmt->get_result()->fetch_assoc();

// Close connection
$conn->close();

// Set page title
$pageTitle = "Upcoming Hearings";
include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Upcoming Hearings</h1>
            <p class="text-gray-600">View all scheduled court hearings for your cases</p>
        </div>
    </div>
    
    <!-- Hearing Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-100 text-purple-500 mr-4">
                    <i class="fas fa-gavel text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Total Hearings</p>
                    <p class="text-2xl font-semibold"><?php echo $hearingStats['total_hearings']; ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-500 mr-4">
                    <i class="fas fa-calendar-alt text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Upcoming Hearings</p>
                    <p class="text-2xl font-semibold"><?php echo $hearingStats['upcoming_hearings']; ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-500 mr-4">
                    <i class="fas fa-check-circle text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Completed Hearings</p>
                    <p class="text-2xl font-semibold"><?php echo $hearingStats['completed_hearings']; ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-yellow-100 text-yellow-500 mr-4">
                    <i class="fas fa-clock text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Next Hearing</p>
                    <p class="text-2xl font-semibold">
                        <?php echo $hearingStats['next_hearing_date'] ? date('M d, Y', strtotime($hearingStats['next_hearing_date'])) : 'None'; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form action="" method="GET" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label for="case_id" class="block text-sm font-medium text-gray-700 mb-1">Case</label>
                    <select id="case_id" name="case_id" class="form-select w-full">
                        <option value="0">All Cases</option>
                        <?php foreach ($cases as $case): ?>
                            <option value="<?php echo $case['case_id']; ?>" <?php echo $caseId == $case['case_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($case['case_number'] . ' - ' . $case['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="status" name="status" class="form-select w-full">
                        <option value="">All Statuses</option>
                        <option value="scheduled" <?php echo $status === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        <option value="postponed" <?php echo $status === 'postponed' ? 'selected' : ''; ?>>Postponed</option>
                    </select>
                </div>
                
                <div>
                    <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                    <input type="date" id="date_from" name="date_from" class="form-input w-full" value="<?php echo $dateFrom; ?>">
                </div>
                
                <div>
                    <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                    <input type="date" id="date_to" name="date_to" class="form-input w-full" value="<?php echo $dateTo; ?>">
                </div>
            </div>
            
            <div class="flex flex-col md:flex-row gap-4">
                <div class="flex-grow">
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input type="text" id="search" name="search" class="form-input w-full" placeholder="Search by hearing type, court room, judge, or description" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg mr-2">
                        <i class="fas fa-search mr-2"></i> Filter
                    </button>
                    
                    <a href="hearings.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg">
                        <i class="fas fa-times mr-2"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Hearings List -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <?php if ($result->num_rows === 0): ?>
            <div class="text-center py-8">
                <div class="text-gray-400 mb-2">
                    <i class="fas fa-gavel text-5xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-1">No hearings found</h3>
                <p class="text-gray-500">
                    <?php if (!empty($search) || !empty($status) || !empty($dateFrom) || !empty($dateTo) || $caseId > 0): ?>
                        Try adjusting your search filters
                    <?php else: ?>
                        You don't have any hearings scheduled yet
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Date & Time
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Case
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Court Room
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Hearing Type
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Judge
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while ($hearing = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo date('M d, Y', strtotime($hearing['hearing_date'])); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?php echo date('h:i A', strtotime($hearing['hearing_time'])); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        <a href="view.php?id=<?php echo $hearing['case_id']; ?>" class="text-blue-600 hover:underline">
                                            <?php echo htmlspecialchars($hearing['case_number']); ?>
                                        </a>
                                    </div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($hearing['title']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($hearing['court_room'] ?? 'Not specified'); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($hearing['hearing_type']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($hearing['judge'] ?? 'Not assigned'); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $statusClass = 'bg-gray-100 text-gray-800';
                                    switch ($hearing['status']) {
                                        case 'scheduled':
                                            $statusClass = 'bg-blue-100 text-blue-800';
                                            break;
                                        case 'completed':
                                            $statusClass = 'bg-green-100 text-green-800';
                                            break;
                                        case 'cancelled':
                                            $statusClass = 'bg-red-100 text-red-800';
                                            break;
                                        case 'postponed':
                                            $statusClass = 'bg-yellow-100 text-yellow-800';
                                            break;
                                    }
                                    ?>
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                        <?php echo ucfirst($hearing['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="hearing-details.php?id=<?php echo $hearing['hearing_id']; ?>" class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Calendar View Toggle Button -->
    <div class="mt-6 text-center">
        <a href="calendar.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
            <i class="fas fa-calendar-alt mr-2"></i> Switch to Calendar View
        </a>
    </div>
</div>

<?php
// Include footer
include '../includes/footer.php';
?>
