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

// Initialize filters
$filters = [
    'status' => isset($_GET['status']) ? $_GET['status'] : '',
    'advocate' => isset($_GET['advocate']) ? (int)$_GET['advocate'] : 0,
    'case' => isset($_GET['case']) ? (int)$_GET['case'] : 0,
    'date_from' => isset($_GET['date_from']) ? $_GET['date_from'] : '',
    'date_to' => isset($_GET['date_to']) ? $_GET['date_to'] : '',
];

// Connect to database
$conn = getDBConnection();

// Build query based on filters
$whereConditions = ["a.client_id = ?"];
$params = [$clientId];
$types = "i";

if (!empty($filters['status'])) {
    $whereConditions[] = "a.status = ?";
    $params[] = $filters['status'];
    $types .= "s";
}

if (!empty($filters['advocate'])) {
    $whereConditions[] = "a.advocate_id = ?";
    $params[] = $filters['advocate'];
    $types .= "i";
}

if (!empty($filters['case'])) {
    $whereConditions[] = "a.case_id = ?";
    $params[] = $filters['case'];
    $types .= "i";
}

if (!empty($filters['date_from'])) {
    $whereConditions[] = "a.appointment_date >= ?";
    $params[] = $filters['date_from'];
    $types .= "s";
}

if (!empty($filters['date_to'])) {
    $whereConditions[] = "a.appointment_date <= ?";
    $params[] = $filters['date_to'];
    $types .= "s";
}

$whereClause = implode(" AND ", $whereConditions);

// Get appointments
$query = "
    SELECT 
        a.*,
        u.full_name as advocate_name,
        c.case_number,
        c.title as case_title
    FROM appointments a
    JOIN advocate_profiles ap ON a.advocate_id = ap.advocate_id
    JOIN users u ON ap.user_id = u.user_id
    LEFT JOIN cases c ON a.case_id = c.case_id
    WHERE $whereClause
    ORDER BY 
        CASE 
            WHEN a.status = 'scheduled' AND a.appointment_date >= CURDATE() THEN 1
            WHEN a.status = 'scheduled' AND a.appointment_date < CURDATE() THEN 2
            WHEN a.status = 'completed' THEN 3
            WHEN a.status = 'cancelled' THEN 4
            WHEN a.status = 'rescheduled' THEN 5
            ELSE 6
        END,
        a.appointment_date ASC,
        a.start_time ASC
";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$appointments = [];
$upcomingCount = 0;
$pastCount = 0;
$cancelledCount = 0;

while ($appointment = $result->fetch_assoc()) {
    $appointments[] = $appointment;
    
    // Count by status and date
    $appointmentDate = strtotime($appointment['appointment_date']);
    $today = strtotime(date('Y-m-d'));
    
    if ($appointment['status'] === 'cancelled') {
        $cancelledCount++;
    } else if ($appointment['status'] === 'scheduled' && $appointmentDate >= $today) {
        $upcomingCount++;
    } else if ($appointment['status'] === 'completed' || ($appointment['status'] === 'scheduled' && $appointmentDate < $today)) {
        $pastCount++;
    }
}

// Get advocates for filter dropdown
$advocatesQuery = "
    SELECT DISTINCT ap.advocate_id, u.full_name
    FROM appointments a
    JOIN advocate_profiles ap ON a.advocate_id = ap.advocate_id
    JOIN users u ON ap.user_id = u.user_id
    WHERE a.client_id = ?
    ORDER BY u.full_name
";
$advocatesStmt = $conn->prepare($advocatesQuery);
$advocatesStmt->bind_param("i", $clientId);
$advocatesStmt->execute();
$advocatesResult = $advocatesStmt->get_result();

$advocates = [];
while ($advocate = $advocatesResult->fetch_assoc()) {
    $advocates[] = $advocate;
}

// Get cases for filter dropdown
$casesQuery = "
    SELECT DISTINCT c.case_id, c.case_number, c.title
    FROM appointments a
    JOIN cases c ON a.case_id = c.case_id
    WHERE a.client_id = ?
    ORDER BY c.case_number
";
$casesStmt = $conn->prepare($casesQuery);
$casesStmt->bind_param("i", $clientId);
$casesStmt->execute();
$casesResult = $casesStmt->get_result();

$cases = [];
while ($case = $casesResult->fetch_assoc()) {
    $cases[] = $case;
}

// Close connection
$conn->close();

// Set page title
$pageTitle = "My Appointments";
include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">My Appointments</h1>
            <p class="text-gray-600">View and manage your appointments with advocates</p>
        </div>
        
        <div class="mt-4 md:mt-0">
            <a href="request.php" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-plus mr-2"></i> Request Appointment
            </a>
        </div>
    </div>
    
    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-500 mr-4">
                    <i class="fas fa-calendar-alt text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Upcoming Appointments</p>
                    <p class="text-2xl font-semibold"><?php echo $upcomingCount; ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-500 mr-4">
                    <i class="fas fa-check-circle text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Past Appointments</p>
                    <p class="text-2xl font-semibold"><?php echo $pastCount; ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-red-100 text-red-500 mr-4">
                    <i class="fas fa-times-circle text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Cancelled Appointments</p>
                    <p class="text-2xl font-semibold"><?php echo $cancelledCount; ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Filter Appointments</h2>
        <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select id="status" name="status" class="form-select w-full">
                    <option value="">All Statuses</option>
                    <option value="scheduled" <?php echo $filters['status'] === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                    <option value="completed" <?php echo $filters['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $filters['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    <option value="rescheduled" <?php echo $filters['status'] === 'rescheduled' ? 'selected' : ''; ?>>Rescheduled</option>
                </select>
            </div>
            
            <div>
                <label for="advocate" class="block text-sm font-medium text-gray-700 mb-1">Advocate</label>
                <select id="advocate" name="advocate" class="form-select w-full">
                    <option value="0">All Advocates</option>
                    <?php foreach ($advocates as $advocate): ?>
                        <option value="<?php echo $advocate['advocate_id']; ?>" <?php echo $filters['advocate'] == $advocate['advocate_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($advocate['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label for="case" class="block text-sm font-medium text-gray-700 mb-1">Case</label>
                <select id="case" name="case" class="form-select w-full">
                    <option value="0">All Cases</option>
                    <?php foreach ($cases as $case): ?>
                        <option value="<?php echo $case['case_id']; ?>" <?php echo $filters['case'] == $case['case_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($case['case_number'] . ' - ' . $case['title']); ?>
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
            
            <div class="flex items-end space-x-2">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg">
                    <i class="fas fa-filter mr-2"></i> Apply Filters
                </button>
                
                <a href="index.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg">
                    <i class="fas fa-times mr-2"></i> Clear Filters
                </a>
            </div>
        </form>
    </div>
    
    <!-- Appointments List -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <?php if (empty($appointments)): ?>
            <div class="text-center py-8">
                <div class="text-gray-400 mb-3"><i class="fas fa-calendar-times text-5xl"></i></div>
                <h3 class="text-lg font-medium text-gray-900 mb-1">No appointments found</h3>
                <p class="text-gray-500 mb-6">
                    <?php if (!empty($filters['status']) || !empty($filters['advocate']) || !empty($filters['case']) || !empty($filters['date_from']) || !empty($filters['date_to'])): ?>
                        Try adjusting your filters or
                    <?php endif; ?>
                    request a new appointment with an advocate
                </p>
                <a href="request.php" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                    <i class="fas fa-plus mr-2"></i> Request Appointment
                </a>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Advocate</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Case</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purpose</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($appointments as $appointment): ?>
                            <?php
                                                        // Determine row styling based on status and date
                                                        $rowClass = '';
                                                        $appointmentDate = strtotime($appointment['appointment_date']);
                                                        $today = strtotime(date('Y-m-d'));
                                                        
                                                        if ($appointment['status'] === 'scheduled' && $appointmentDate < $today) {
                                                            $rowClass = 'bg-yellow-50'; // Past due appointments
                                                        } else if ($appointment['status'] === 'cancelled') {
                                                            $rowClass = 'bg-red-50'; // Cancelled appointments
                                                        } else if ($appointment['status'] === 'completed') {
                                                            $rowClass = 'bg-green-50'; // Completed appointments
                                                        }
                                                        ?>
                                                        <tr class="<?php echo $rowClass; ?> hover:bg-gray-50">
                                                            <td class="px-6 py-4 whitespace-nowrap">
                                                                <div class="text-sm font-medium text-gray-900">
                                                                    <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?>
                                                                </div>
                                                                <div class="text-sm text-gray-500">
                                                                    <?php echo date('h:i A', strtotime($appointment['start_time'])); ?> - 
                                                                    <?php echo date('h:i A', strtotime($appointment['end_time'])); ?>
                                                                </div>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap">
                                                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($appointment['advocate_name']); ?></div>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap">
                                                                <?php if ($appointment['case_id']): ?>
                                                                    <div class="text-sm text-gray-900">
                                                                        <a href="../cases/view.php?id=<?php echo $appointment['case_id']; ?>" class="text-blue-600 hover:underline">
                                                                            <?php echo htmlspecialchars($appointment['case_number']); ?>
                                                                        </a>
                                                                    </div>
                                                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($appointment['case_title']); ?></div>
                                                                <?php else: ?>
                                                                    <span class="text-sm text-gray-500">No related case</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="px-6 py-4">
                                                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($appointment['title']); ?></div>
                                                                <?php if (!empty($appointment['description'])): ?>
                                                                    <div class="text-xs text-gray-500 truncate max-w-xs" title="<?php echo htmlspecialchars($appointment['description']); ?>">
                                                                        <?php echo htmlspecialchars(substr($appointment['description'], 0, 50) . (strlen($appointment['description']) > 50 ? '...' : '')); ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap">
                                                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($appointment['location']); ?></div>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap">
                                                                <?php
                                                                $statusClass = 'bg-gray-100 text-gray-800';
                                                                $statusIcon = 'fa-calendar';
                                                                
                                                                switch ($appointment['status']) {
                                                                    case 'scheduled':
                                                                        if ($appointmentDate >= $today) {
                                                                            $statusClass = 'bg-blue-100 text-blue-800';
                                                                            $statusIcon = 'fa-calendar-check';
                                                                        } else {
                                                                            $statusClass = 'bg-yellow-100 text-yellow-800';
                                                                            $statusIcon = 'fa-calendar-times';
                                                                        }
                                                                        break;
                                                                    case 'completed':
                                                                        $statusClass = 'bg-green-100 text-green-800';
                                                                        $statusIcon = 'fa-check-circle';
                                                                        break;
                                                                    case 'cancelled':
                                                                        $statusClass = 'bg-red-100 text-red-800';
                                                                        $statusIcon = 'fa-times-circle';
                                                                        break;
                                                                    case 'rescheduled':
                                                                        $statusClass = 'bg-purple-100 text-purple-800';
                                                                        $statusIcon = 'fa-calendar-plus';
                                                                        break;
                                                                }
                                                                ?>
                                                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                                                    <i class="fas <?php echo $statusIcon; ?> mr-1"></i>
                                                                    <?php 
                                                                    if ($appointment['status'] === 'scheduled' && $appointmentDate < $today) {
                                                                        echo 'Past Due';
                                                                    } else {
                                                                        echo ucfirst($appointment['status']);
                                                                    }
                                                                    ?>
                                                                </span>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                                <a href="view.php?id=<?php echo $appointment['appointment_id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                                                    <i class="fas fa-eye"></i> View
                                                                </a>
                                                                
                                                                <?php if ($appointment['status'] === 'scheduled' && $appointmentDate > $today): ?>
                                                                    <a href="cancel.php?id=<?php echo $appointment['appointment_id']; ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to cancel this appointment?');">
                                                                        <i class="fas fa-times-circle"></i> Cancel
                                                                    </a>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
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
                            