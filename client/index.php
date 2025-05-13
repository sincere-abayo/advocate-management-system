<?php
// Include necessary files
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check if user is logged in and is a client
requireLogin();
requireUserType('client');

// Get client ID from session
$userId = $_SESSION['user_id'];
$clientId = $_SESSION['client_id'];

// Connect to database
$conn = getDBConnection();

// Get client data
$clientStmt = $conn->prepare("
    SELECT u.*, cp.* 
    FROM users u
    JOIN client_profiles cp ON u.user_id = cp.user_id
    WHERE u.user_id = ?
");
$clientStmt->bind_param("i", $userId);
$clientStmt->execute();
$clientData = $clientStmt->get_result()->fetch_assoc();

// Get case statistics
$caseStatsStmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_cases,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_cases,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_cases,
        SUM(CASE WHEN status IN ('closed', 'won', 'lost', 'settled') THEN 1 ELSE 0 END) as closed_cases
    FROM cases
    WHERE client_id = ?
");
$caseStatsStmt->bind_param("i", $clientId);
$caseStatsStmt->execute();
$caseStats = $caseStatsStmt->get_result()->fetch_assoc();

// Get upcoming hearings
$hearingsStmt = $conn->prepare("
    SELECT ch.*, c.case_number, c.title, c.case_type
    FROM case_hearings ch
    JOIN cases c ON ch.case_id = c.case_id
    WHERE c.client_id = ? AND ch.hearing_date >= CURDATE()
    ORDER BY ch.hearing_date ASC, ch.hearing_time ASC
    LIMIT 5
");
$hearingsStmt->bind_param("i", $clientId);
$hearingsStmt->execute();
$hearingsResult = $hearingsStmt->get_result();

// Get upcoming appointments
$appointmentsStmt = $conn->prepare("
    SELECT a.*, u.full_name as advocate_name, c.case_number, c.title as case_title
    FROM appointments a
    JOIN advocate_profiles ap ON a.advocate_id = ap.advocate_id
    JOIN users u ON ap.user_id = u.user_id
    LEFT JOIN cases c ON a.case_id = c.case_id
    WHERE a.client_id = ? AND a.appointment_date >= CURDATE() AND a.status != 'cancelled'
    ORDER BY a.appointment_date ASC, a.start_time ASC
    LIMIT 5
");
$appointmentsStmt->bind_param("i", $clientId);
$appointmentsStmt->execute();
$appointmentsResult = $appointmentsStmt->get_result();

// Get recent invoices
$invoicesStmt = $conn->prepare("
    SELECT b.*, u.full_name as advocate_name
    FROM billings b
    JOIN advocate_profiles ap ON b.advocate_id = ap.advocate_id
    JOIN users u ON ap.user_id = u.user_id
    WHERE b.client_id = ?
    ORDER BY b.billing_date DESC
    LIMIT 5
");
$invoicesStmt->bind_param("i", $clientId);
$invoicesStmt->execute();
$invoicesResult = $invoicesStmt->get_result();

// Get unread notifications count
$notificationsStmt = $conn->prepare("
    SELECT COUNT(*) as unread_count
    FROM notifications
    WHERE user_id = ? AND is_read = 0
");
$notificationsStmt->bind_param("i", $userId);
$notificationsStmt->execute();
$notificationsData = $notificationsStmt->get_result()->fetch_assoc();
$unreadNotifications = $notificationsData['unread_count'];

// Close connection
$conn->close();

// Set page title
$pageTitle = "Client Dashboard";
include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Welcome, <?php echo htmlspecialchars($clientData['full_name']); ?></h1>
            <p class="text-gray-600">Here's an overview of your cases and activities</p>
        </div>
        
        <div class="mt-4 md:mt-0">
            <a href="cases/index.php" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-briefcase mr-2"></i> View All Cases
            </a>
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
            <div class="mt-4">
                <div class="flex justify-between text-sm">
                    <span class="text-green-500"><?php echo $caseStats['active_cases']; ?> Active</span>
                    <span class="text-yellow-500"><?php echo $caseStats['pending_cases']; ?> Pending</span>
                    <span class="text-gray-500"><?php echo $caseStats['closed_cases']; ?> Closed</span>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-100 text-purple-500 mr-4">
                    <i class="fas fa-gavel text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Upcoming Hearings</p>
                    <p class="text-2xl font-semibold"><?php echo $hearingsResult->num_rows; ?></p>
                </div>
            </div>
            <?php if ($hearingsResult->num_rows > 0): ?>
                <div class="mt-4">
                    <a href="hearings/index.php" class="text-blue-600 hover:underline text-sm">View all hearings</a>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-500 mr-4">
                    <i class="fas fa-calendar-alt text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Appointments</p>
                    <p class="text-2xl font-semibold"><?php echo $appointmentsResult->num_rows; ?></p>
                </div>
            </div>
            <?php if ($appointmentsResult->num_rows > 0): ?>
                <div class="mt-4">
                    <a href="appointments/index.php" class="text-blue-600 hover:underline text-sm">View all appointments</a>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-red-100 text-red-500 mr-4">
                    <i class="fas fa-file-invoice-dollar text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Pending Invoices</p>
                    <p class="text-2xl font-semibold" id="pending-invoices">-</p>
                </div>
            </div>
            <div class="mt-4">
                <a href="invoices/index.php" class="text-blue-600 hover:underline text-sm">View all invoices</a>
            </div>
        </div>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Upcoming Hearings -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-800">Upcoming Hearings</h2>
                <a href="hearings/index.php" class="text-blue-600 hover:text-blue-800 text-sm">View All</a>
            </div>
            
            <div class="p-6">
                <?php if ($hearingsResult->num_rows === 0): ?>
                    <div class="text-center py-4">
                        <div class="text-gray-400 mb-2">
                            <i class="fas fa-gavel text-4xl"></i>
                        </div>
                        <p class="text-gray-500">No upcoming hearings scheduled</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php while ($hearing = $hearingsResult->fetch_assoc()): ?>
                            <div class="border-b border-gray-200 pb-4 last:border-b-0 last:pb-0">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="font-medium text-gray-900">
                                            <a href="cases/view.php?id=<?php echo $hearing['case_id']; ?>" class="hover:text-blue-600">
                                                <?php echo htmlspecialchars($hearing['case_number']); ?>: <?php echo htmlspecialchars($hearing['title']); ?>
                                            </a>
                                        </h3>
                                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($hearing['hearing_type']); ?></p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-medium text-gray-900">
                                            <?php echo date('M d, Y', strtotime($hearing['hearing_date'])); ?>
                                        </p>
                                        <p class="text-sm text-gray-600">
                                            <?php echo date('h:i A', strtotime($hearing['hearing_time'])); ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="mt-2 flex items-center text-sm">
                                    <i class="fas fa-map-marker-alt text-gray-400 mr-1"></i>
                                    <span class="text-gray-600"><?php echo htmlspecialchars($hearing['court_room']); ?></span>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Upcoming Appointments -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-800">Upcoming Appointments</h2>
                <a href="appointments/index.php" class="text-blue-600 hover:text-blue-800 text-sm">View All</a>
            </div>
            
            <div class="p-6">
                <?php if ($appointmentsResult->num_rows === 0): ?>
                    <div class="text-center py-4">
                        <div class="text-gray-400 mb-2">
                            <i class="fas fa-calendar-alt text-4xl"></i>
                        </div>
                        <p class="text-gray-500">No upcoming appointments scheduled</p>
                        <a href="appointments/create.php" class="mt-2 inline-block text-blue-600 hover:underline">
                            <i class="fas fa-plus mr-1"></i> Schedule an appointment
                        </a>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php while ($appointment = $appointmentsResult->fetch_assoc()): ?>
                            <div class="border-b border-gray-200 pb-4 last:border-b-0 last:pb-0">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="font-medium text-gray-900">
                                            <?php echo htmlspecialchars($appointment['title']); ?>
                                        </h3>
                                        <p class="text-sm text-gray-600">
                                            With: <?php echo htmlspecialchars($appointment['advocate_name']); ?>
                                        </p>
                                        <?php if ($appointment['case_id']): ?>
                                            <p class="text-sm text-gray-600">
                                                Case: <a href="cases/view.php?id=<?php echo $appointment['case_id']; ?>" class="text-blue-600 hover:underline">
                                                    <?php echo htmlspecialchars($appointment['case_number']); ?>
                                                </a>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-medium text-gray-900">
                                            <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?>
                                        </p>
                                        <p class="text-sm text-gray-600">
                                            <?php echo date('h:i A', strtotime($appointment['start_time'])); ?> - 
                                            <?php echo date('h:i A', strtotime($appointment['end_time'])); ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="mt-2 flex items-center text-sm">
                                    <i class="fas fa-map-marker-alt text-gray-400 mr-1"></i>
                                    <span class="text-gray-600"><?php echo htmlspecialchars($appointment['location']); ?></span>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Invoices -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-800">Recent Invoices</h2>
                <a href="invoices/index.php" class="text-blue-600 hover:text-blue-800 text-sm">View All</a>
            </div>
            
            <div class="p-6">
                <?php if ($invoicesResult->num_rows === 0): ?>
                    <div class="text-center py-4">
                        <div class="text-gray-400 mb-2">
                            <i class="fas fa-file-invoice-dollar text-4xl"></i>
                        </div>
                        <p class="text-gray-500">No invoices found</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice #</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php while ($invoice = $invoicesResult->fetch_assoc()): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <a href="invoices/view.php?id=<?php echo $invoice['billing_id']; ?>" class="text-blue-600 hover:underline">
                                                INV-<?php echo str_pad($invoice['billing_id'], 5, '0', STR_PAD_LEFT); ?>
                                            </a>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('M d, Y', strtotime($invoice['billing_date'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo formatCurrency($invoice['amount']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $statusClass = 'bg-gray-100 text-gray-800';
                                            switch ($invoice['status']) {
                                                case 'paid':
                                                    $statusClass = 'bg-green-100 text-green-800';
                                                    break;
                                                case 'pending':
                                                    $statusClass = 'bg-yellow-100 text-yellow-800';
                                                    break;
                                                case 'overdue':
                                                    $statusClass = 'bg-red-100 text-red-800';
                                                    break;
                                                case 'cancelled':
                                                    $statusClass = 'bg-gray-100 text-gray-800';
                                                    break;
                                            }
                                            ?>
                                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                                <?php echo ucfirst($invoice['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Activities -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">Recent Case Activities</h2>
            </div>
            
            <div class="p-6">
                <div id="recent-activities">
                    <div class="text-center py-4">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
                        <p class="text-gray-500 mt-2">Loading recent activities...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Load pending invoices count
    fetch('api/get_pending_invoices.php')
        .then(response => response.json())
        .then(data => {
            document.getElementById('pending-invoices').textContent = data.count;
        })
        .catch(error => {
            console.error('Error fetching pending invoices:', error);
            document.getElementById('pending-invoices').textContent = '0';
        });
    
    // Load recent activities
    fetch('api/get_recent_activities.php')
        .then(response => response.json())
        .then(data => {
            const activitiesContainer = document.getElementById('recent-activities');
            
            if (data.length === 0) {
                activitiesContainer.innerHTML = `
                    <div class="text-center py-4">
                        <div class="text-gray-400 mb-2">
                            <i class="fas fa-history text-4xl"></i>
                        </div>
                        <p class="text-gray-500">No recent activities found</p>
                    </div>
                `;
                return;
            }
            
            let activitiesHTML = '<div class="space-y-4">';
            
            data.forEach(activity => {
                const date = new Date(activity.activity_date);
                const formattedDate = date.toLocaleDateString('en-US', { 
                    month: 'short', 
                    day: 'numeric', 
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
                
                let iconClass = 'fas fa-info-circle text-blue-500';
                switch (activity.activity_type) {
                    case 'update':
                        iconClass = 'fas fa-edit text-blue-500';
                        break;
                    case 'document':
                        iconClass = 'fas fa-file-alt text-yellow-500';
                        break;
                    case 'hearing':
                        iconClass = 'fas fa-gavel text-purple-500';
                        break;
                    case 'note':
                        iconClass = 'fas fa-sticky-note text-green-500';
                        break;
                    case 'status_change':
                        iconClass = 'fas fa-exchange-alt text-red-500';
                        break;
                }
                
                activitiesHTML += `
                    <div class="border-b border-gray-200 pb-4 last:border-b-0 last:pb-0">
                        <div class="flex">
                            <div class="flex-shrink-0 mr-3">
                                <div class="h-8 w-8 rounded-full bg-gray-100 flex items-center justify-center">
                                    <i class="${iconClass}"></i>
                                </div>
                            </div>
                            <div>
                                <p class="text-sm text-gray-900">${activity.description}</p>
                                <p class="text-xs text-gray-500 mt-1">
                                    <a href="cases/view.php?id=${activity.case_id}" class="text-blue-600 hover:underline">
                                        ${activity.case_number}
                                    </a> • ${formattedDate}
                                </p>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            activitiesHTML += '</div>';
            activitiesContainer.innerHTML = activitiesHTML;
        })
        .catch(error => {
            console.error('Error fetching recent activities:', error);
            document.getElementById('recent-activities').innerHTML = `
                <div class="text-center py-4">
                    <div class="text-red-400 mb-2">
                        <i class="fas fa-exclamation-circle text-4xl"></i>
                    </div>
                    <p class="text-gray-500">Failed to load recent activities</p>
                </div>
            `;
        });
});
</script>

<?php
// Include footer
include 'includes/footer.php';
?>

