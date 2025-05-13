<?php
// error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Set page title
$pageTitle = "Upcoming Hearings";

// Include header
include_once '../includes/header.php';

// Get advocate ID
$advocateId = $advocateData['advocate_id'];

// Get database connection
$conn = getDBConnection();

// Set default filter values
$daysAhead = isset($_GET['days_ahead']) ? (int)$_GET['days_ahead'] : 30;
$caseType = isset($_GET['case_type']) ? sanitizeInput($_GET['case_type']) : '';
$court = isset($_GET['court']) ? sanitizeInput($_GET['court']) : '';
$sortBy = isset($_GET['sort']) ? sanitizeInput($_GET['sort']) : 'hearing_date';
$sortOrder = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'DESC' : 'ASC';

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Build the query
$query = "
    SELECT ch.*, c.case_number, c.title, c.case_type, c.court, c.status,
           cp.client_id, u.full_name as client_name
    FROM case_hearings ch
    JOIN cases c ON ch.case_id = c.case_id
    JOIN case_assignments ca ON c.case_id = ca.case_id
    JOIN client_profiles cp ON c.client_id = cp.client_id
    JOIN users u ON cp.user_id = u.user_id
    WHERE ca.advocate_id = ? 
    AND ch.hearing_date >= CURDATE() 
    AND ch.hearing_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
";

$countQuery = "
    SELECT COUNT(*) as total
    FROM case_hearings ch
    JOIN cases c ON ch.case_id = c.case_id
    JOIN case_assignments ca ON c.case_id = ca.case_id
    WHERE ca.advocate_id = ? 
    AND ch.hearing_date >= CURDATE() 
    AND ch.hearing_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
";

$params = [$advocateId, $daysAhead];
$types = "ii";

// Add filters if provided
if (!empty($caseType)) {
    $query .= " AND c.case_type = ?";
    $countQuery .= " AND c.case_type = ?";
    $params[] = $caseType;
    $types .= "s";
}

if (!empty($court)) {
    $query .= " AND c.court = ?";
    $countQuery .= " AND c.court = ?";
    $params[] = $court;
    $types .= "s";
}

// Add sorting
$query .= " ORDER BY " . $sortBy . " " . $sortOrder;

// Add pagination
$query .= " LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$types .= "ii";

// Get total count for pagination
$countStmt = $conn->prepare($countQuery);

// Create a new array for binding parameters to the count query
$countParams = $params;
array_splice($countParams, -2); // Remove the LIMIT and OFFSET params
$countTypes = substr($types, 0, -2); // Remove the 'ii' for LIMIT and OFFSET

// Bind parameters properly
$bindParams = array($countTypes);
foreach ($countParams as $key => $value) {
    $bindParams[] = &$countParams[$key];
}

call_user_func_array([$countStmt, 'bind_param'], $bindParams);
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalCount = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalCount / $perPage);

// Get hearings
$stmt = $conn->prepare($query);

// Create a new array for binding parameters to the main query
$bindParams = array($types);
foreach ($params as $key => $value) {
    $bindParams[] = &$params[$key];
}

call_user_func_array([$stmt, 'bind_param'], $bindParams);
$stmt->execute();
$result = $stmt->get_result();

// Get case types and courts for filters
$caseTypesStmt = $conn->prepare("
    SELECT DISTINCT c.case_type
    FROM cases c
    JOIN case_assignments ca ON c.case_id = ca.case_id
    WHERE ca.advocate_id = ?
    ORDER BY c.case_type
");
$caseTypesStmt->bind_param("i", $advocateId);
$caseTypesStmt->execute();
$caseTypesResult = $caseTypesStmt->get_result();

$courtsStmt = $conn->prepare("
    SELECT DISTINCT c.court
    FROM cases c
    JOIN case_assignments ca ON c.case_id = ca.case_id
    WHERE ca.advocate_id = ?
    ORDER BY c.court
");
$courtsStmt->bind_param("i", $advocateId);
$courtsStmt->execute();
$courtsResult = $courtsStmt->get_result();

// Helper function to generate sort URL
function getSortUrl($column) {
    global $sortBy, $sortOrder;
    $newOrder = ($sortBy === $column && $sortOrder === 'ASC') ? 'desc' : 'asc';
    $params = $_GET;
    $params['sort'] = $column;
    $params['order'] = $newOrder;
    return '?' . http_build_query($params);
}

// Helper function to generate sort icon
function getSortIcon($column) {
    global $sortBy, $sortOrder;
    if ($sortBy !== $column) {
        return '<i class="fas fa-sort text-gray-400 ml-1"></i>';
    }
    return ($sortOrder === 'ASC') 
        ? '<i class="fas fa-sort-up text-blue-500 ml-1"></i>' 
        : '<i class="fas fa-sort-down text-blue-500 ml-1"></i>';
}

// Close database connections
$stmt->close();
$countStmt->close();
$caseTypesStmt->close();
$courtsStmt->close();
$conn->close();
?>

<div class="mb-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Upcoming Hearings</h1>
            <p class="text-gray-600">View and manage upcoming court hearings for your cases</p>
        </div>
        
        <div class="mt-4 md:mt-0">
            <a href="/advocate/cases/index.php" class="btn-secondary">
                <i class="fas fa-list mr-2"></i> All Cases
            </a>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">Filter Hearings</h2>
    
    <form method="GET" action="" class="space-y-4 md:space-y-0 md:flex md:flex-wrap md:items-end md:gap-4">
        <div class="w-full md:w-auto md:flex-1">
            <label for="days_ahead" class="block text-sm font-medium text-gray-700 mb-1">Time Period</label>
            <select id="days_ahead" name="days_ahead" class="form-select w-full">
                <option value="7" <?php echo $daysAhead == 7 ? 'selected' : ''; ?>>Next 7 days</option>
                <option value="14" <?php echo $daysAhead == 14 ? 'selected' : ''; ?>>Next 14 days</option>
                <option value="30" <?php echo $daysAhead == 30 ? 'selected' : ''; ?>>Next 30 days</option>
                <option value="90" <?php echo $daysAhead == 90 ? 'selected' : ''; ?>>Next 3 months</option>
                <option value="180" <?php echo $daysAhead == 180 ? 'selected' : ''; ?>>Next 6 months</option>
            </select>
        </div>
        
        <div class="w-full md:w-auto md:flex-1">
            <label for="case_type" class="block text-sm font-medium text-gray-700 mb-1">Case Type</label>
            <select id="case_type" name="case_type" class="form-select w-full">
                <option value="">All Case Types</option>
                <?php while ($caseTypeRow = $caseTypesResult->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($caseTypeRow['case_type']); ?>" <?php echo $caseType === $caseTypeRow['case_type'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($caseTypeRow['case_type']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div class="w-full md:w-auto md:flex-1">
            <label for="court" class="block text-sm font-medium text-gray-700 mb-1">Court</label>
            <select id="court" name="court" class="form-select w-full">
                <option value="">All Courts</option>
                <?php while ($courtRow = $courtsResult->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($courtRow['court']); ?>" <?php echo $court === $courtRow['court'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($courtRow['court']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div class="w-full md:w-auto">
            <button type="submit" class="btn-primary w-full md:w-auto">
                <i class="fas fa-filter mr-2"></i> Apply Filters
            </button>
        </div>
        
        <?php if (!empty($caseType) || !empty($court) || $daysAhead != 30): ?>
            <div class="w-full md:w-auto">
                <a href="hearings.php" class="btn-secondary w-full md:w-auto inline-block text-center">
                    <i class="fas fa-times mr-2"></i> Clear Filters
                </a>
            </div>
        <?php endif; ?>
    </form>
</div>

<!-- Hearings Table -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <?php if ($result->num_rows > 0): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('hearing_date'); ?>" class="flex items-center">
                                Hearing Date <?php echo getSortIcon('hearing_date'); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('c.case_number'); ?>" class="flex items-center">
                                Case Number <?php echo getSortIcon('c.case_number'); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('c.title'); ?>" class="flex items-center">
                                Case Title <?php echo getSortIcon('c.title'); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('c.court'); ?>" class="flex items-center">
                                Court <?php echo getSortIcon('c.court'); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('hearing_type'); ?>" class="flex items-center">
                                Hearing Type <?php echo getSortIcon('hearing_type'); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Client
                        </th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php while ($hearing = $result->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center rounded-full 
                                        <?php 
                                        $daysUntil = (strtotime($hearing['hearing_date']) - time()) / (60 * 60 * 24);
                                        if ($daysUntil <= 3) {
                                            echo 'bg-red-100 text-red-800';
                                        } elseif ($daysUntil <= 7) {
                                            echo 'bg-yellow-100 text-yellow-800';
                                        } else {
                                            echo 'bg-blue-100 text-blue-800';
                                        }
                                        ?>">
                                        <i class="fas fa-gavel"></i>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo formatDate($hearing['hearing_date']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?php echo formatTime($hearing['hearing_time']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($hearing['case_number']); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($hearing['title']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($hearing['case_type']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($hearing['court_room'] ?? ''); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($hearing['hearing_type']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($hearing['client_name']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="view.php?id=<?php echo $hearing['case_id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                    <i class="fas fa-eye"></i> View Case
                                </a>
                                <a href="hearing-details.php?id=<?php echo $hearing['hearing_id']; ?>" class="text-indigo-600 hover:text-indigo-900">
                                    <i class="fas fa-gavel"></i> Hearing Details
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
                        Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $perPage, $totalCount); ?> of <?php echo $totalCount; ?> hearings
                    </div>
                    <div class="flex space-x-1">
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="px-3 py-1 rounded-md bg-white text-gray-600 border border-gray-300 hover:bg-gray-50">
                                Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="px-3 py-1 rounded-md <?php echo $i === $page ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 border border-gray-300 hover:bg-gray-50'; ?>">
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
        <div class="text-center py-8">
            <div class="text-gray-400 mb-2"><i class="fas fa-gavel text-5xl"></i></div>
            <h3 class="text-lg font-medium text-gray-900">No upcoming hearings found</h3>
            <p class="text-gray-500 mt-1">
                <?php if (!empty($caseType) || !empty($court)): ?>
                    Try adjusting your filters or
                <?php endif; ?>
                Check back later for scheduled hearings.
            </p>
            <?php if (!empty($caseType) || !empty($court) || $daysAhead != 30): ?>
                <div class="mt-4">
                    <a href="hearings.php" class="text-blue-600 hover:text-blue-800">
                        <i class="fas fa-times mr-1"></i> Clear all filters
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Calendar View Toggle -->
<div class="mt-6 bg-white rounded-lg shadow-md p-6">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-semibold text-gray-800">Calendar View</h2>
        <div>
            <button id="toggleCalendarBtn" class="btn-secondary">
                <i class="fas fa-calendar-alt mr-2"></i> <span id="toggleCalendarText">Show Calendar</span>
            </button>
        </div>
    </div>
    
    <div id="calendarView" class="hidden">
        <div id="hearingsCalendar" class="mt-4 h-96"></div>
    </div>
</div>

<!-- Add CSS for calendar -->
<style>
.btn-primary {
    @apply bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition duration-150 inline-flex items-center;
}

.btn-secondary {
    @apply bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg transition duration-150 inline-flex items-center;
}

.form-select {
    @apply mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md;
}

/* Calendar specific styles */
.fc-event {
    cursor: pointer;
}
.fc-event-title {
    font-weight: 500;
}
.fc-today-button {
    @apply bg-blue-600 hover:bg-blue-700 text-white font-medium py-1 px-3 rounded transition duration-150;
}
.fc-button-primary {
    @apply bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-1 px-3 rounded transition duration-150;
}
.fc-button-primary.fc-button-active {
    @apply bg-blue-600 text-white;
}
</style>

<!-- Add JavaScript for calendar functionality -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.css" rel="stylesheet">

<script>
document.addEventListener('DOMContentLoaded', function() {
    const calendarView = document.getElementById('calendarView');
    const toggleCalendarBtn = document.getElementById('toggleCalendarBtn');
    const toggleCalendarText = document.getElementById('toggleCalendarText');
    let calendar;
    
    toggleCalendarBtn.addEventListener('click', function() {
        if (calendarView.classList.contains('hidden')) {
            calendarView.classList.remove('hidden');
            toggleCalendarText.textContent = 'Hide Calendar';
            
            // Initialize calendar if not already done
            if (!calendar) {
                initializeCalendar();
            }
        } else {
            calendarView.classList.add('hidden');
            toggleCalendarText.textContent = 'Show Calendar';
        }
    });
    
    function initializeCalendar() {
        const calendarEl = document.getElementById('hearingsCalendar');
        calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,listWeek'
            },
            events: [
                <?php 
                // Reset result pointer
                $result->data_seek(0);
                while ($hearing = $result->fetch_assoc()): 
                ?>
                
                    title: '<?php echo addslashes($hearing['hearing_type'] . ': ' . $hearing['title']); ?>',
                    start: '<?php echo $hearing['hearing_date'] . 'T' . $hearing['hearing_time']; ?>',
                    url: '/advocate/cases/view.php?id=<?php echo $hearing['case_id']; ?>',
                    backgroundColor: '<?php echo ($hearing['case_type'] === 'Criminal') ? '#EF4444' : 
                                           (($hearing['case_type'] === 'Civil') ? '#3B82F6' : 
                                           (($hearing['case_type'] === 'Family') ? '#10B981' : '#8B5CF6')); ?>',
                    borderColor: '<?php echo ($hearing['case_type'] === 'Criminal') ? '#EF4444' : 
                                       (($hearing['case_type'] === 'Civil') ? '#3B82F6' : 
                                       (($hearing['case_type'] === 'Family') ? '#10B981' : '#8B5CF6')); ?>'
                
                <?php endwhile; ?>
            ],
            eventClick: function(info) {
                if (info.event.url) {
                    window.location.href = info.event.url;
                    return false;
                }
            }
        });
        calendar.render();
    }
});
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>
