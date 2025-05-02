<?php
ob_start();
// Set page title
$pageTitle = "Appointments";

// Include header
include_once '../includes/header.php';

// Get advocate ID
$advocateId = $advocateData['advocate_id'];

// Get filter parameters
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$dateFrom = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : '';
$clientId = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

// Build query conditions
$conditions = ["a.advocate_id = ?"];
$params = [$advocateId];
$types = "i";

if (!empty($status)) {
    $conditions[] = "a.status = ?";
    $params[] = $status;
    $types .= "s";
}

if (!empty($dateFrom)) {
    $conditions[] = "a.appointment_date >= ?";
    $params[] = $dateFrom;
    $types .= "s";
}

if (!empty($dateTo)) {
    $conditions[] = "a.appointment_date <= ?";
    $params[] = $dateTo;
    $types .= "s";
}

if (!empty($clientId)) {
    $conditions[] = "a.client_id = ?";
    $params[] = $clientId;
    $types .= "i";
}

if (!empty($search)) {
    $conditions[] = "(a.title LIKE ? OR c.full_name LIKE ? OR cs.title LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "sss";
}

$whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// Get database connection
$conn = getDBConnection();

// Get total appointments count for pagination
$countSql = "
    SELECT COUNT(*) as total
    FROM appointments a
    JOIN client_profiles cp ON a.client_id = cp.client_id
    JOIN users c ON cp.user_id = c.user_id
    LEFT JOIN cases cs ON a.case_id = cs.case_id
    $whereClause
";

$countStmt = $conn->prepare($countSql);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalResult = $countStmt->get_result();
$totalRow = $totalResult->fetch_assoc();
$totalAppointments = $totalRow['total'];

// Pagination
$perPage = 10;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$totalPages = ceil($totalAppointments / $perPage);
$offset = ($currentPage - 1) * $perPage;

// Get appointments
$sql = "
    SELECT a.*, c.full_name as client_name, cs.title as case_title
    FROM appointments a
    JOIN client_profiles cp ON a.client_id = cp.client_id
    JOIN users c ON cp.user_id = c.user_id
    LEFT JOIN cases cs ON a.case_id = cs.case_id
    $whereClause
    ORDER BY a.appointment_date ASC, a.start_time ASC
    LIMIT ?, ?
";

$stmt = $conn->prepare($sql);
$types .= "ii";
$params[] = $offset;
$params[] = $perPage;
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Get clients for filter dropdown
$clientsStmt = $conn->prepare("
    SELECT cp.client_id, u.full_name
    FROM client_profiles cp
    JOIN users u ON cp.user_id = u.user_id
    JOIN appointments a ON cp.client_id = a.client_id
    WHERE a.advocate_id = ?
    GROUP BY cp.client_id
    ORDER BY u.full_name
");
$clientsStmt->bind_param("i", $advocateId);
$clientsStmt->execute();
$clientsResult = $clientsStmt->get_result();
$clients = [];
while ($client = $clientsResult->fetch_assoc()) {
    $clients[] = $client;
}
?>

<div class="mb-6">
    <div class="flex flex-col md:flex-row md:justify-between md:items-center">
        <h1 class="text-2xl font-semibold text-gray-800 mb-4 md:mb-0">Appointments</h1>
        <a href="<?php echo $path_url ?>advocate/appointments/create.php" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
            <i class="fas fa-plus mr-2"></i> Schedule Appointment
        </a>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <form method="GET" action="" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select id="status" name="status" class="form-select w-full">
                    <option value="">All Statuses</option>
                    <option value="scheduled" <?php echo $status === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                    <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    <option value="rescheduled" <?php echo $status === 'rescheduled' ? 'selected' : ''; ?>>Rescheduled</option>
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
            
            <div>
                <label for="client_id" class="block text-sm font-medium text-gray-700 mb-1">Client</label>
                <select id="client_id" name="client_id" class="form-select w-full">
                    <option value="">All Clients</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?php echo $client['client_id']; ?>" <?php echo $clientId == $client['client_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($client['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="flex flex-col md:flex-row gap-4">
            <div class="flex-grow">
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" id="search" name="search" class="form-input w-full" placeholder="Search by title, client, or case" value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div class="flex items-end">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg mr-2">
                    <i class="fas fa-search mr-2"></i> Filter
                </button>
                <a href="<?php echo $path_url ?>advocate/appointments/index.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg">
                    <i class="fas fa-times mr-2"></i> Clear
                </a>
            </div>
        </div>
    </form>
</div>

<!-- Appointments List -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <?php if ($result->num_rows > 0): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Date & Time
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Title
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Client
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Case
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
                    <?php while ($appointment = $result->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    <?php echo formatDate($appointment['appointment_date']); ?>
                                </div>
                                <div class="text-sm text-gray-500">
                                    <?php echo formatTime($appointment['start_time']); ?> - <?php echo formatTime($appointment['end_time']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($appointment['title']); ?>
                                </div>
                                <?php if (!empty($appointment['location'])): ?>
                                    <div class="text-sm text-gray-500">
                                        <i class="fas fa-map-marker-alt mr-1"></i> <?php echo htmlspecialchars($appointment['location']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <?php echo htmlspecialchars($appointment['client_name']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <?php echo !empty($appointment['case_title']) ? htmlspecialchars($appointment['case_title']) : 'N/A'; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $statusClass = 'bg-gray-100 text-gray-800';
                                switch ($appointment['status']) {
                                    case 'scheduled':
                                        $statusClass = 'bg-blue-100 text-blue-800';
                                        break;
                                    case 'completed':
                                        $statusClass = 'bg-green-100 text-green-800';
                                        break;
                                    case 'cancelled':
                                        $statusClass = 'bg-red-100 text-red-800';
                                        break;
                                    case 'rescheduled':
                                        $statusClass = 'bg-yellow-100 text-yellow-800';
                                        break;
                                }
                                ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                    <?php echo ucfirst(htmlspecialchars($appointment['status'])); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex justify-end space-x-2">
                                    <a href="<?php echo $path_url ?>advocate/appointments/view.php?id=<?php echo $appointment['appointment_id']; ?>" class="text-blue-600 hover:text-blue-900" title="View Appointment">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="<?php echo $path_url ?>advocate/appointments/edit.php?id=<?php echo $appointment['appointment_id']; ?>" class="text-indigo-600 hover:text-indigo-900" title="Edit Appointment">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="<?php echo $path_url ?>advocate/appointments/delete.php?id=<?php echo $appointment['appointment_id']; ?>" class="text-red-600 hover:text-red-900" title="Delete Appointment" onclick="return confirm('Are you sure you want to delete this appointment?');">
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
                        Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $perPage, $totalAppointments); ?> of <?php echo $totalAppointments; ?> appointments
                    </div>
                    <div class="text-sm text-gray-500">
                        Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $perPage, $totalAppointments); ?> of <?php echo $totalAppointments; ?> appointments
                    </div>
                    <div class="flex space-x-1">
                        <?php if ($currentPage > 1): ?>
                            <a href="?page=<?php echo ($currentPage - 1); ?><?php echo !empty($status) ? '&status=' . urlencode($status) : ''; ?><?php echo !empty($dateFrom) ? '&date_from=' . urlencode($dateFrom) : ''; ?><?php echo !empty($dateTo) ? '&date_to=' . urlencode($dateTo) : ''; ?><?php echo !empty($clientId) ? '&client_id=' . urlencode($clientId) : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="px-3 py-1 rounded-md bg-white border border-gray-300 text-gray-700 hover:bg-gray-50">
                                Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?><?php echo !empty($status) ? '&status=' . urlencode($status) : ''; ?><?php echo !empty($dateFrom) ? '&date_from=' . urlencode($dateFrom) : ''; ?><?php echo !empty($dateTo) ? '&date_to=' . urlencode($dateTo) : ''; ?><?php echo !empty($clientId) ? '&client_id=' . urlencode($clientId) : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="px-3 py-1 rounded-md <?php echo $i === $currentPage ? 'bg-blue-600 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($currentPage < $totalPages): ?>
                            <a href="?page=<?php echo ($currentPage + 1); ?><?php echo !empty($status) ? '&status=' . urlencode($status) : ''; ?><?php echo !empty($dateFrom) ? '&date_from=' . urlencode($dateFrom) : ''; ?><?php echo !empty($dateTo) ? '&date_to=' . urlencode($dateTo) : ''; ?><?php echo !empty($clientId) ? '&client_id=' . urlencode($clientId) : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="px-3 py-1 rounded-md bg-white border border-gray-300 text-gray-700 hover:bg-gray-50">
                                Next
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="text-center py-8">
            <div class="text-gray-400 mb-3"><i class="fas fa-calendar-times text-5xl"></i></div>
            <h3 class="text-lg font-medium text-gray-900 mb-1">No appointments found</h3>
            <p class="text-gray-500 mb-6">
                <?php if (!empty($search) || !empty($status) || !empty($dateFrom) || !empty($dateTo) || !empty($clientId)): ?>
                    No appointments match your filter criteria. Try adjusting your filters.
                <?php else: ?>
                    You don't have any appointments scheduled yet.
                <?php endif; ?>
            </p>
            <a href="<?php echo $path_url ?>advocate/appointments/create.php" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-plus mr-2"></i> Schedule Appointment
            </a>
        </div>
    <?php endif; ?>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
ob_end_flush();
?>
