<?php
// Set page title
$pageTitle = "Client Details";

// Include header
include_once '../includes/header.php';

// Get advocate ID
$advocateId = $advocateData['advocate_id'];

// Check if client ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirectWithMessage('index.php', 'Client ID is required', 'error');
    exit;
}

$clientId = (int)$_GET['id'];

// Get database connection
$conn = getDBConnection();

// Get client details
$clientStmt = $conn->prepare("
    SELECT cp.*, u.user_id, u.full_name, u.email, u.phone, u.address, u.status, u.created_at
    FROM client_profiles cp
    JOIN users u ON cp.user_id = u.user_id
    WHERE cp.client_id = ?
");
$clientStmt->bind_param("i", $clientId);
$clientStmt->execute();
$clientResult = $clientStmt->get_result();

if ($clientResult->num_rows === 0) {
    redirectWithMessage('index.php', 'Client not found', 'error');
    exit;
}

$client = $clientResult->fetch_assoc();
$clientStmt->close();

// Verify that the advocate has access to this client
$accessStmt = $conn->prepare("
    SELECT COUNT(*) as has_access
    FROM cases c
    JOIN case_assignments ca ON c.case_id = ca.case_id
    WHERE c.client_id = ? AND ca.advocate_id = ?
");
$accessStmt->bind_param("ii", $clientId, $advocateId);
$accessStmt->execute();
$accessResult = $accessStmt->get_result();
$hasAccess = $accessResult->fetch_assoc()['has_access'] > 0;
$accessStmt->close();

if (!$hasAccess) {
    redirectWithMessage('index.php', 'You do not have access to this client', 'error');
    exit;
}

// Get client cases
$casesStmt = $conn->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM case_activities WHERE case_id = c.case_id) as activity_count,
           (SELECT COUNT(*) FROM documents WHERE case_id = c.case_id) as document_count
    FROM cases c
    JOIN case_assignments ca ON c.case_id = ca.case_id
    WHERE c.client_id = ? AND ca.advocate_id = ?
    ORDER BY c.created_at DESC
");
$casesStmt->bind_param("ii", $clientId, $advocateId);
$casesStmt->execute();
$casesResult = $casesStmt->get_result();
$cases = [];
while ($case = $casesResult->fetch_assoc()) {
    $cases[] = $case;
}
$casesStmt->close();

// Get client appointments
$appointmentsStmt = $conn->prepare("
    SELECT a.*
    FROM appointments a
    WHERE a.client_id = ? AND a.advocate_id = ? AND a.appointment_date >= CURDATE()
    ORDER BY a.appointment_date ASC, a.start_time ASC
    LIMIT 5
");
$appointmentsStmt->bind_param("ii", $clientId, $advocateId);
$appointmentsStmt->execute();
$appointmentsResult = $appointmentsStmt->get_result();
$appointments = [];
while ($appointment = $appointmentsResult->fetch_assoc()) {
    $appointments[] = $appointment;
}
$appointmentsStmt->close();

// Get client activities
$activitiesStmt = $conn->prepare("
    SELECT ca.*, c.title as case_title, u.full_name as user_name
    FROM case_activities ca
    JOIN cases c ON ca.case_id = c.case_id
    JOIN users u ON ca.user_id = u.user_id
    JOIN case_assignments cas ON c.case_id = cas.case_id
    WHERE c.client_id = ? AND cas.advocate_id = ?
    ORDER BY ca.activity_date DESC
    LIMIT 10
");
$activitiesStmt->bind_param("ii", $clientId, $advocateId);
$activitiesStmt->execute();
$activitiesResult = $activitiesStmt->get_result();
$activities = [];
while ($activity = $activitiesResult->fetch_assoc()) {
    $activities[] = $activity;
}
$activitiesStmt->close();

// Get client billing summary
$billingsStmt = $conn->prepare("
    SELECT 
        SUM(amount) as total_billed,
        SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as total_paid,
        SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as total_pending,
        SUM(CASE WHEN status = 'overdue' THEN amount ELSE 0 END) as total_overdue
    FROM billings
    WHERE client_id = ? AND advocate_id = ?
");
$billingsStmt->bind_param("ii", $clientId, $advocateId);
$billingsStmt->execute();
$billing = $billingsStmt->get_result()->fetch_assoc();
$billingsStmt->close();

// Close database connection
$conn->close();
?>

<div class="mb-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($client['full_name']); ?></h1>
            <p class="text-gray-600">Client since <?php echo formatDate($client['created_at'], 'F Y'); ?></p>
        </div>
        
        <div class="mt-4 md:mt-0 flex flex-wrap gap-2">
            <a href="../cases/create.php?client_id=<?php echo $clientId; ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-plus mr-2"></i> New Case
            </a>
            
            <a href="../appointments/create.php?client_id=<?php echo $clientId; ?>" class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-calendar-plus mr-2"></i> Schedule Appointment
            </a>
            
            <a href="../finance/invoices/create.php?client_id=<?php echo $clientId; ?>" class="bg-purple-600 hover:bg-purple-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-file-invoice-dollar mr-2"></i> Create Invoice
            </a>
            
            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg inline-flex items-center">
                    <i class="fas fa-ellipsis-h mr-2"></i> More Actions
                </button>
                
                <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-2 z-10" style="display: none;">
                    <a href="../messages/compose.php?recipient=<?php echo $client['user_id']; ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-envelope mr-2"></i> Send Message
                    </a>
                    <a href="edit.php?id=<?php echo $clientId; ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-edit mr-2"></i> Edit Client
                    </a>
                    <a href="../documents/upload.php?client_id=<?php echo $clientId; ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-file-upload mr-2"></i> Upload Document
                    </a>
                    <div class="border-t my-1"></div>
                    <a href="#" onclick="confirmDeactivate(<?php echo $clientId; ?>)" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                        <i class="fas fa-user-slash mr-2"></i> Deactivate Client
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Client Information -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="bg-blue-600 text-white px-6 py-4">
                <h2 class="text-lg font-semibold">Client Information</h2>
            </div>
            
            <div class="p-6">
                <div class="flex items-center justify-center mb-6">
                    <div class="h-24 w-24 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 text-3xl font-bold">
                        <?php echo strtoupper(substr($client['full_name'], 0, 1)); ?>
                    </div>
                </div>
                
                <div class="space-y-4">
                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Full Name</h3>
                        <p class="text-gray-900"><?php echo htmlspecialchars($client['full_name']); ?></p>
                    </div>
                    
                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Email Address</h3>
                        <p class="text-gray-900">
                            <a href="mailto:<?php echo htmlspecialchars($client['email']); ?>" class="text-blue-600 hover:underline">
                                <?php echo htmlspecialchars($client['email']); ?>
                            </a>
                        </p>
                    </div>
                    
                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Phone Number</h3>
                        <?php if (!empty($client['phone'])): ?>
                            <p class="text-gray-900">
                                <a href="tel:<?php echo htmlspecialchars($client['phone']); ?>" class="text-blue-600 hover:underline">
                                    <?php echo htmlspecialchars($client['phone']); ?>
                                </a>
                            </p>
                        <?php else: ?>
                            <p class="text-gray-500 italic">Not provided</p>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Address</h3>
                        <?php if (!empty($client['address'])): ?>
                            <p class="text-gray-900"><?php echo nl2br(htmlspecialchars($client['address'])); ?></p>
                        <?php else: ?>
                            <p class="text-gray-500 italic">Not provided</p>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Occupation</h3>
                        <?php if (!empty($client['occupation'])): ?>
                            <p class="text-gray-900"><?php echo htmlspecialchars($client['occupation']); ?></p>
                        <?php else: ?>
                            <p class="text-gray-500 italic">Not provided</p>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($client['date_of_birth'])): ?>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500">Date of Birth</h3>
                            <p class="text-gray-900"><?php echo formatDate($client['date_of_birth']); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($client['reference_source'])): ?>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500">Reference Source</h3>
                            <p class="text-gray-900"><?php echo htmlspecialchars($client['reference_source']); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Status</h3>
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
                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                            <?php echo ucfirst($client['status']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Financial Summary -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mt-6">
            <div class="bg-green-600 text-white px-6 py-4">
                <h2 class="text-lg font-semibold">Financial Summary</h2>
            </div>
            
            <div class="p-6">
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h3 class="text-sm font-medium text-gray-500">Total Billed</h3>
                        <p class="text-xl font-semibold text-gray-900"><?php echo formatCurrency($billing['total_billed'] ?? 0); ?></p>
                    </div>
                    
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h3 class="text-sm font-medium text-gray-500">Total Paid</h3>
                        <p class="text-xl font-semibold text-green-600"><?php echo formatCurrency($billing['total_paid'] ?? 0); ?></p>
                    </div>
                    
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h3 class="text-sm font-medium text-gray-500">Pending</h3>
                        <p class="text-xl font-semibold text-blue-600"><?php echo formatCurrency($billing['total_pending'] ?? 0); ?></p>
                    </div>
                    
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h3 class="text-sm font-medium text-gray-500">Overdue</h3>
                        <p class="text-xl font-semibold text-red-600"><?php echo formatCurrency($billing['total_overdue'] ?? 0); ?></p>
                    </div>
                </div>
                
                <div class="mt-4">
                    <a href="../finance/client-billing.php?id=<?php echo $clientId; ?>" class="text-blue-600 hover:underline text-sm flex items-center justify-center">
                        <span>View Billing History</span>
                        <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Cases and Activities -->
    <div class="lg:col-span-2">
        <!-- Client Cases -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="bg-blue-600 text-white px-6 py-4 flex justify-between items-center">
                <h2 class="text-lg font-semibold">Cases</h2>
                <a href="../cases/create.php?client_id=<?php echo $clientId; ?>" class="text-white hover:text-blue-100">
                    <i class="fas fa-plus"></i>
                </a>
            </div>
            
            <div class="overflow-x-auto">
                <?php if (count($cases) > 0): ?>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Case Number
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Title
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Filed
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Activity
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($cases as $case): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($case['case_number']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <a href="../cases/view.php?id=<?php echo $case['case_id']; ?>" class="text-blue-600 hover:underline">
                                            <?php echo htmlspecialchars($case['title']); ?>
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        $statusClass = 'bg-gray-100 text-gray-800';
                                        switch ($case['status']) {
                                            case 'active':
                                                $statusClass = 'bg-blue-100 text-blue-800';
                                                break;
                                            case 'pending':
                                                $statusClass = 'bg-yellow-100 text-yellow-800';
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
                                                $statusClass = 'bg-purple-100 text-purple-800';
                                                break;
                                        }
                                        ?>
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                            <?php echo ucfirst($case['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo !empty($case['filing_date']) ? formatDate($case['filing_date']) : 'N/A'; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <div class="flex items-center space-x-2">
                                            <span class="flex items-center">
                                                <i class="fas fa-comment-alt text-blue-500 mr-1"></i>
                                                <?php echo $case['activity_count']; ?>
                                            </span>
                                            <span class="flex items-center">
                                                <i class="fas fa-file-alt text-green-500 mr-1"></i>
                                                <?php echo $case['document_count']; ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="../cases/view.php?id=<?php echo $case['case_id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="../cases/edit.php?id=<?php echo $case['case_id']; ?>" class="text-indigo-600 hover:text-indigo-900">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="text-center py-8">
                        <div class="text-gray-400 mb-2">
                            <i class="fas fa-folder-open text-5xl"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-1">No cases found</h3>
                        <p class="text-gray-500">This client doesn't have any cases assigned to you yet.</p>
                        <div class="mt-4">
                            <a href="../cases/create.php?client_id=<?php echo $clientId; ?>" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-plus mr-2"></i> Create New Case
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Upcoming Appointments -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mt-6">
            <div class="bg-blue-600 text-white px-6 py-4 flex justify-between items-center">
                <h2 class="text-lg font-semibold">Upcoming Appointments</h2>
                <a href="../appointments/create.php?client_id=<?php echo $clientId; ?>" class="text-white hover:text-blue-100">
                    <i class="fas fa-plus"></i>
                </a>
            </div>
            
            <div class="p-6">
                <?php if (count($appointments) > 0): ?>
                    <div class="space-y-4">
                        <?php foreach ($appointments as $appointment): ?>
                            <div class="border-l-4 border-blue-500 pl-4 py-2">
                                <div class="flex justify-between">
                                    <p class="font-medium"><?php echo htmlspecialchars($appointment['title']); ?></p>
                                    <span class="text-sm text-gray-500"><?php echo formatTime($appointment['start_time']); ?> - <?php echo formatTime($appointment['end_time']); ?></span>
                                </div>
                                <p class="text-sm text-gray-600">
                                    <?php echo formatDate($appointment['appointment_date']); ?>
                                    <?php if (!empty($appointment['location'])): ?>
                                        <span class="mx-2">•</span> <?php echo htmlspecialchars($appointment['location']); ?>
                                    <?php endif; ?>
                                </p>
                                <?php if (!empty($appointment['description'])): ?>
                                    <p class="text-sm text-gray-500 mt-1">
                                        <?php echo htmlspecialchars(substr($appointment['description'], 0, 100)); ?>
                                        <?php echo (strlen($appointment['description']) > 100) ? '...' : ''; ?>
                                    </p>
                                <?php endif; ?>
                                <div class="mt-2 flex space-x-2">
                                    <a href="../appointments/view.php?id=<?php echo $appointment['appointment_id']; ?>" class="text-xs text-blue-600 hover:underline">View Details</a>
                                    <a href="../appointments/edit.php?id=<?php echo $appointment['appointment_id']; ?>" class="text-xs text-blue-600 hover:underline">Edit</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-4 text-center">
                        <a href="../appointments/index.php?client_id=<?php echo $clientId; ?>" class="text-blue-600 hover:underline text-sm">
                            View All Appointments
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center py-6">
                        <div class="text-gray-400 mb-2">
                            <i class="fas fa-calendar-alt text-4xl"></i>
                        </div>
                        <p class="text-gray-500">No upcoming appointments with this client.</p>
                        <div class="mt-4">
                            <a href="../appointments/create.php?client_id=<?php echo $clientId; ?>" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-calendar-plus mr-2"></i> Schedule Appointment
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Activities -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mt-6">
            <div class="bg-blue-600 text-white px-6 py-4">
                <h2 class="text-lg font-semibold">Recent Activities</h2>
            </div>
            
            <div class="p-6">
                <?php if (count($activities) > 0): ?>
                    <div class="space-y-6">
                        <?php foreach ($activities as $activity): ?>
                            <div class="flex">
                                <div class="flex-shrink-0 mr-3">
                                    <?php
                                    $iconClass = 'fas fa-info-circle text-blue-500';
                                    switch ($activity['activity_type']) {
                                        case 'update':
                                            $iconClass = 'fas fa-edit text-blue-500';
                                            break;
                                        case 'document':
                                            $iconClass = 'fas fa-file-alt text-green-500';
                                            break;
                                        case 'hearing':
                                            $iconClass = 'fas fa-gavel text-purple-500';
                                            break;
                                        case 'note':
                                            $iconClass = 'fas fa-sticky-note text-yellow-500';
                                            break;
                                        case 'status_change':
                                            $iconClass = 'fas fa-exchange-alt text-red-500';
                                            break;
                                    }
                                    ?>
                                    <div class="bg-gray-100 rounded-full p-2">
                                        <i class="<?php echo $iconClass; ?>"></i>
                                    </div>
                                </div>
                                <div>
                                    <p class="text-sm font-medium">
                                        <a href="../cases/view.php?id=<?php echo $activity['case_id']; ?>" class="text-blue-600 hover:underline">
                                            <?php echo htmlspecialchars($activity['case_title']); ?>
                                        </a>
                                    </p>
                                    <p class="text-sm text-gray-500">
                                        <?php echo htmlspecialchars($activity['description']); ?>
                                    </p>
                                    <p class="text-xs text-gray-400 mt-1 flex items-center">
                                        <span><?php echo date('M d, Y h:i A', strtotime($activity['activity_date'])); ?></span>
                                        <span class="mx-1">•</span>
                                                                               <span><?php echo date('M d, Y h:i A', strtotime($activity['activity_date'])); ?></span>
                                        <span class="mx-1">•</span>
                                        <span><?php echo htmlspecialchars($activity['user_name']); ?></span>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (count($cases) > 0): ?>
                        <div class="mt-4 text-center">
                            <a href="../cases/activities.php?client_id=<?php echo $clientId; ?>" class="text-blue-600 hover:underline text-sm">
                                View All Activities
                            </a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-center py-6">
                        <div class="text-gray-400 mb-2">
                            <i class="fas fa-history text-4xl"></i>
                        </div>
                        <p class="text-gray-500">No recent activities for this client.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Deactivate Client Modal -->
<div id="deactivateModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <div class="text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                <i class="fas fa-user-slash text-red-600 text-xl"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Deactivate Client</h3>
            <p class="text-sm text-gray-500 mb-6">
                Are you sure you want to deactivate this client? They will no longer be able to log in to the system.
                All associated cases and data will remain in the system.
            </p>
        </div>
        
        <div class="flex justify-end space-x-3">
            <button type="button" onclick="closeDeactivateModal()" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg">
                Cancel
            </button>
            <form id="deactivateForm" method="POST" action="deactivate.php">
                <input type="hidden" name="client_id" id="deactivateClientId" value="">
                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-lg">
                    Deactivate
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    function confirmDeactivate(clientId) {
        document.getElementById('deactivateClientId').value = clientId;
        document.getElementById('deactivateModal').classList.remove('hidden');
    }
    
    function closeDeactivateModal() {
        document.getElementById('deactivateModal').classList.add('hidden');
    }
    
    // Close modal when clicking outside
    document.getElementById('deactivateModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeDeactivateModal();
        }
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>