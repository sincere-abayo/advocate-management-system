<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user is logged in and is an admin
requireLogin();
requireUserType('admin');

// Check if case ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirectWithMessage('../cases/index.php', 'Case ID is required', 'error');
    exit;
}

$caseId = (int)$_GET['id'];

// Get database connection
$conn = getDBConnection();

// Get case details
$caseQuery = "
    SELECT 
        c.*,
        u.full_name as client_name,
        u.email as client_email,
        u.phone as client_phone,
        cp.occupation as client_occupation,
        (SELECT COUNT(*) FROM case_activities WHERE case_id = c.case_id) as activity_count,
        (SELECT COUNT(*) FROM documents WHERE case_id = c.case_id) as document_count,
        (SELECT COUNT(*) FROM case_hearings WHERE case_id = c.case_id) as hearing_count,
        (SELECT SUM(amount) FROM billings WHERE case_id = c.case_id) as total_billed,
        (SELECT SUM(amount) FROM billings WHERE case_id = c.case_id AND status = 'paid') as total_paid
    FROM cases c
    JOIN client_profiles cp ON c.client_id = cp.client_id
    JOIN users u ON cp.user_id = u.user_id
    WHERE c.case_id = ?
";

$stmt = $conn->prepare($caseQuery);
$stmt->bind_param("i", $caseId);
$stmt->execute();
$caseResult = $stmt->get_result();

if ($caseResult->num_rows === 0) {
    redirectWithMessage('../cases/index.php', 'Case not found', 'error');
    exit;
}

$case = $caseResult->fetch_assoc();

// Get assigned advocates
$advocatesQuery = "
    SELECT 
        u.user_id,
        u.full_name,
        u.email,
        u.phone,
        ap.license_number,
        ap.specialization,
        ca.role,
        ca.assigned_date
    FROM case_assignments ca
    JOIN advocate_profiles ap ON ca.advocate_id = ap.advocate_id
    JOIN users u ON ap.user_id = u.user_id
    WHERE ca.case_id = ?
    ORDER BY ca.role = 'primary' DESC, ca.assigned_date ASC
";

$advocatesStmt = $conn->prepare($advocatesQuery);
$advocatesStmt->bind_param("i", $caseId);
$advocatesStmt->execute();
$advocatesResult = $advocatesStmt->get_result();
$advocates = [];
while ($advocate = $advocatesResult->fetch_assoc()) {
    $advocates[] = $advocate;
}

// Get case activities
$activitiesQuery = "
    SELECT 
        ca.*,
        u.full_name as user_name,
        u.user_type
    FROM case_activities ca
    JOIN users u ON ca.user_id = u.user_id
    WHERE ca.case_id = ?
    ORDER BY ca.activity_date DESC
    LIMIT 10
";

$activitiesStmt = $conn->prepare($activitiesQuery);
$activitiesStmt->bind_param("i", $caseId);
$activitiesStmt->execute();
$activitiesResult = $activitiesStmt->get_result();
$activities = [];
while ($activity = $activitiesResult->fetch_assoc()) {
    $activities[] = $activity;
}

// Get case documents
$documentsQuery = "
    SELECT 
        d.*,
        u.full_name as uploaded_by_name
    FROM documents d
    JOIN users u ON d.uploaded_by = u.user_id
    WHERE d.case_id = ?
    ORDER BY d.upload_date DESC
    LIMIT 10
";

$documentsStmt = $conn->prepare($documentsQuery);
$documentsStmt->bind_param("i", $caseId);
$documentsStmt->execute();
$documentsResult = $documentsStmt->get_result();
$documents = [];
while ($document = $documentsResult->fetch_assoc()) {
    $documents[] = $document;
}

// Get case hearings
$hearingsQuery = "
    SELECT 
        h.*,
        u.full_name as created_by_name
    FROM case_hearings h
    JOIN users u ON h.created_by = u.user_id
    WHERE h.case_id = ?
    ORDER BY h.hearing_date ASC, h.hearing_time ASC
";

$hearingsStmt = $conn->prepare($hearingsQuery);
$hearingsStmt->bind_param("i", $caseId);
$hearingsStmt->execute();
$hearingsResult = $hearingsStmt->get_result();
$hearings = [];
while ($hearing = $hearingsResult->fetch_assoc()) {
    $hearings[] = $hearing;
}

// Get case billings
$billingsQuery = "
    SELECT 
        b.*,
        u.full_name as advocate_name
    FROM billings b
    JOIN advocate_profiles ap ON b.advocate_id = ap.advocate_id
    JOIN users u ON ap.user_id = u.user_id
    WHERE b.case_id = ?
    ORDER BY b.billing_date DESC
";

$billingsStmt = $conn->prepare($billingsQuery);
$billingsStmt->bind_param("i", $caseId);
$billingsStmt->execute();
$billingsResult = $billingsStmt->get_result();
$billings = [];
while ($billing = $billingsResult->fetch_assoc()) {
    $billings[] = $billing;
}

// Set page title
$pageTitle = "Case Details: " . $case['case_number'];
include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">
                Case: <?php echo htmlspecialchars($case['case_number']); ?>
            </h1>
            <p class="text-gray-600"><?php echo htmlspecialchars($case['title']); ?></p>
        </div>
        
        <div class="mt-4 md:mt-0">
            <a href="../cases/index.php" class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back to Cases
            </a>
        </div>
    </div>
    
    <!-- Case Status and Info -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Case Status</h2>
            
            <div class="space-y-3">
                <div>
                    <span class="text-gray-500 text-sm">Status:</span>
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
                            $statusClass = 'bg-indigo-100 text-indigo-800';
                            break;
                    }
                    ?>
                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                        <?php echo ucfirst($case['status']); ?>
                    </span>
                </div>
                
                <div>
                    <span class="text-gray-500 text-sm">Priority:</span>
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
                </div>
                
                <div>
                    <span class="text-gray-500 text-sm">Case Type:</span>
                    <span class="text-gray-900"><?php echo htmlspecialchars($case['case_type']); ?></span>
                </div>
                
                <div>
                    <span class="text-gray-500 text-sm">Court:</span>
                    <span class="text-gray-900"><?php echo htmlspecialchars($case['court'] ?? 'Not specified'); ?></span>
                </div>
                
                <div>
                    <span class="text-gray-500 text-sm">Filing Date:</span>
                    <span class="text-gray-900"><?php echo !empty($case['filing_date']) ? formatDate($case['filing_date']) : 'Not filed'; ?></span>
                </div>
                
                <div>
                    <span class="text-gray-500 text-sm">Next Hearing:</span>
                    <span class="text-gray-900">
                        <?php
                        $nextHearing = null;
                        foreach ($hearings as $hearing) {
                            if ($hearing['status'] === 'scheduled' && $hearing['hearing_date'] >= date('Y-m-d')) {
                                $nextHearing = $hearing;
                                break;
                            }
                        }
                        
                        if ($nextHearing) {
                            echo formatDate($nextHearing['hearing_date']) . ' at ' . formatTime($nextHearing['hearing_time']);
                        } else {
                            echo 'No upcoming hearings';
                        }
                        ?>
                    </span>
                </div>
                
                <div>
                    <span class="text-gray-500 text-sm">Created:</span>
                    <span class="text-gray-900"><?php echo formatDateTime($case['created_at']); ?></span>
                </div>
                
                <div>
                    <span class="text-gray-500 text-sm">Last Updated:</span>
                    <span class="text-gray-900"><?php echo formatDateTime($case['updated_at']); ?></span>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Client Information</h2>
            
            <div class="space-y-3">
                <div>
                    <span class="text-gray-500 text-sm">Name:</span>
                    <span class="text-gray-900"><?php echo htmlspecialchars($case['client_name']); ?></span>
                </div>
                
                <div>
                    <span class="text-gray-500 text-sm">Email:</span>
                    <a href="mailto:<?php echo htmlspecialchars($case['client_email']); ?>" class="text-blue-600 hover:text-blue-900">
                        <?php echo htmlspecialchars($case['client_email']); ?>
                    </a>
                </div>
                
                <?php if (!empty($case['client_phone'])): ?>
                <div>
                    <span class="text-gray-500 text-sm">Phone:</span>
                    <a href="tel:<?php echo htmlspecialchars($case['client_phone']); ?>" class="text-blue-600 hover:text-blue-900">
                        <?php echo htmlspecialchars($case['client_phone']); ?>
                    </a>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($case['client_occupation'])): ?>
                <div>
                    <span class="text-gray-500 text-sm">Occupation:</span>
                    <span class="text-gray-900"><?php echo htmlspecialchars($case['client_occupation']); ?></span>
                </div>
                <?php endif; ?>
                
                <div class="pt-2">
                    <a href="../clients/view.php?id=<?php echo $case['client_id']; ?>" class="text-blue-600 hover:text-blue-900 text-sm">
                        <i class="fas fa-external-link-alt mr-1"></i> View Client Profile
                    </a>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Financial Summary</h2>
            
            <div class="space-y-3">
                <div>
                    <span class="text-gray-500 text-sm">Total Billed:</span>
                    <span class="text-gray-900 font-semibold"><?php echo formatCurrency($case['total_billed'] ?? 0); ?></span>
                </div>
                
                <div>
                    <span class="text-gray-500 text-sm">Total Paid:</span>
                    <span class="text-green-600 font-semibold"><?php echo formatCurrency($case['total_paid'] ?? 0); ?></span>
                </div>
                
                <div>
                    <span class="text-gray-500 text-sm">Outstanding:</span>
                    <span class="text-red-600 font-semibold">
                        <?php echo formatCurrency(($case['total_billed'] ?? 0) - ($case['total_paid'] ?? 0)); ?>
                    </span>
                </div>
                
                <div class="pt-2">
                    <a href="../finance/invoices.php?case_id=<?php echo $case['case_id']; ?>" class="text-blue-600 hover:text-blue-900 text-sm">
                        <i class="fas fa-file-invoice-dollar mr-1"></i> View All Invoices
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Case Description -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Case Description</h2>
        
        <div class="prose max-w-none">
            <?php if (!empty($case['description'])): ?>
                <p><?php echo nl2br(htmlspecialchars($case['description'])); ?></p>
            <?php else: ?>
                <p class="text-gray-500 italic">No description provided</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Assigned Advocates -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Assigned Advocates</h2>
        
        <?php if (empty($advocates)): ?>
            <p class="text-gray-500 italic">No advocates assigned to this case</p>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($advocates as $advocate): ?>
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-md font-medium text-gray-900"><?php echo htmlspecialchars($advocate['full_name']); ?></h3>
                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($advocate['specialization']); ?></p>
                                
                                <div class="mt-2 text-sm text-gray-500">
                                    <div>
                                        <span class="font-medium">Email:</span> 
                                        <a href="mailto:<?php echo htmlspecialchars($advocate['email']); ?>" class="text-blue-600 hover:text-blue-900">
                                            <?php echo htmlspecialchars($advocate['email']); ?>
                                        </a>
                                    </div>
                                    
                                    <?php if (!empty($advocate['phone'])): ?>
                                    <div>
                                        <span class="font-medium">Phone:</span> 
                                        <a href="tel:<?php echo htmlspecialchars($advocate['phone']); ?>" class="text-blue-600 hover:text-blue-900">
                                            <?php echo htmlspecialchars($advocate['phone']); ?>
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div>
                                        <span class="font-medium">License:</span> <?php echo htmlspecialchars($advocate['license_number']); ?>
                                    </div>
                                    
                                    <div>
                                        <span class="font-medium">Role:</span> 
                                        <span class="<?php echo $advocate['role'] === 'primary' ? 'text-blue-600 font-medium' : ''; ?>">
                                            <?php echo ucfirst($advocate['role']); ?>
                                        </span>
                                    </div>
                                    
                                    <div>
                                        <span class="font-medium">Assigned:</span> <?php echo formatDate($advocate['assigned_date']); ?>
                                    </div>
                                </div>
                                
                                <div class="mt-2">
                                    <a href="../users/view.php?id=<?php echo $advocate['user_id']; ?>" class="text-blue-600 hover:text-blue-900 text-sm">
                                        <i class="fas fa-external-link-alt mr-1"></i> View Profile
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Tabs for Case Details -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="border-b border-gray-200">
            <nav class="flex -mb-px">
                <button class="tab-button active" data-tab="activities">
                    <i class="fas fa-history mr-2"></i> Activities <span class="ml-1 text-gray-500">(<?php echo $case['activity_count']; ?>)</span>
                </button>
                <button class="tab-button" data-tab="hearings">
                    <i class="fas fa-gavel mr-2"></i> Hearings <span class="ml-1 text-gray-500">(<?php echo $case['hearing_count']; ?>)</span>
                </button>
                <button class="tab-button" data-tab="documents">
                    <i class="fas fa-file-alt mr-2"></i> Documents <span class="ml-1 text-gray-500">(<?php echo $case['document_count']; ?>)</span>
                </button>
                <button class="tab-button" data-tab="billings">
                    <i class="fas fa-file-invoice-dollar mr-2"></i> Billings <span class="ml-1 text-gray-500">(<?php echo count($billings); ?>)</span>
                </button>
            </nav>
        </div>
        
        <!-- Activities Tab -->
        <div id="activities-tab" class="tab-content active">
            <?php if (empty($activities)): ?>
                <div class="p-6 text-center">
                    <p class="text-gray-500 italic">No activities recorded for this case</p>
                </div>
            <?php else: ?>
                <div class="divide-y divide-gray-200">
                    <?php foreach ($activities as $activity): ?>
                        <div class="p-6">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <?php
                                    $activityIconClass = 'fas fa-info-circle text-blue-500';
                                    switch ($activity['activity_type']) {
                                        case 'update':
                                            $activityIconClass = 'fas fa-edit text-blue-500';
                                            break;
                                        case 'document':
                                            $activityIconClass = 'fas fa-file-alt text-yellow-500';
                                            break;
                                        case 'hearing':
                                            $activityIconClass = 'fas fa-gavel text-purple-500';
                                            break;
                                        case 'note':
                                            $activityIconClass = 'fas fa-sticky-note text-green-500';
                                            break;
                                        case 'status_change':
                                            $activityIconClass = 'fas fa-exchange-alt text-red-500';
                                            break;
                                    }
                                    ?>
                                    <div class="h-8 w-8 rounded-full bg-gray-100 flex items-center justify-center">
                                        <i class="<?php echo $activityIconClass; ?>"></i>
                                    </div>
                                </div>
                                <div class="ml-4 flex-1">
                                    <div class="text-sm text-gray-900">
                                        <span class="font-medium"><?php echo htmlspecialchars($activity['user_name']); ?></span>
                                        <span class="text-gray-600">
                                            <?php
                                            switch ($activity['activity_type']) {
                                                case 'update':
                                                    echo 'updated the case';
                                                    break;
                                                case 'document':
                                                    echo 'uploaded a document';
                                                    break;
                                                case 'hearing':
                                                    echo 'added a hearing';
                                                    break;
                                                case 'note':
                                                    echo 'added a note';
                                                    break;
                                                case 'status_change':
                                                    echo 'changed the status';
                                                    break;
                                                default:
                                                    echo 'performed an action';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <div class="mt-1 text-sm text-gray-600">
                                        <?php echo nl2br(htmlspecialchars($activity['description'])); ?>
                                    </div>
                                    <div class="mt-1 text-xs text-gray-500">
                                        <?php echo formatDateTimeRelative($activity['activity_date']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($case['activity_count'] > 10): ?>
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 text-center">
                        <a href="../cases/activities.php?id=<?php echo $case['case_id']; ?>" class="text-blue-600 hover:text-blue-900 text-sm font-medium">
                            View All Activities
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <!-- Hearings Tab -->
        <div id="hearings-tab" class="tab-content hidden">
            <?php if (empty($hearings)): ?>
                <div class="p-6 text-center">
                    <p class="text-gray-500 italic">No hearings scheduled for this case</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($hearings as $hearing): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo formatDate($hearing['hearing_date']); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo formatTime($hearing['hearing_time']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($hearing['hearing_type']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($hearing['court_room'] ?? 'Not specified'); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($hearing['judge'] ?? ''); ?></div>
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
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        <?php if (!empty($hearing['description'])): ?>
                                            <div class="max-w-xs truncate"><?php echo htmlspecialchars($hearing['description']); ?></div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($hearing['outcome']) && $hearing['status'] === 'completed'): ?>
                                            <div class="mt-1">
                                                <span class="font-medium text-gray-900">Outcome:</span> <?php echo htmlspecialchars($hearing['outcome']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Documents Tab -->
        <div id="documents-tab" class="tab-content hidden">
            <?php if (empty($documents)): ?>
                <div class="p-6 text-center">
                    <p class="text-gray-500 italic">No documents uploaded for this case</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Uploaded By</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($documents as $document): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($document['title']); ?></div>
                                        <?php if (!empty($document['description'])): ?>
                                            <div class="text-sm text-gray-500 max-w-xs truncate"><?php echo htmlspecialchars($document['description']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($document['document_type'] ?? 'Not specified'); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($document['uploaded_by_name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo formatDate($document['upload_date']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="../../<?php echo htmlspecialchars($document['file_path']); ?>" target="_blank" class="text-blue-600 hover:text-blue-900 mr-3" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="../../<?php echo htmlspecialchars($document['file_path']); ?>" download class="text-green-600 hover:text-green-900" title="Download">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($case['document_count'] > 10): ?>
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 text-center">
                        <a href="../documents/index.php?case_id=<?php echo $case['case_id']; ?>" class="text-blue-600 hover:text-blue-900 text-sm font-medium">
                            View All Documents
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <!-- Billings Tab -->
        <div id="billings-tab" class="tab-content hidden">
            <?php if (empty($billings)): ?>
                <div class="p-6 text-center">
                    <p class="text-gray-500 italic">No billings recorded for this case</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice #</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Advocate</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($billings as $billing): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-blue-600">
                                            <a href="../finance/invoice-details.php?id=<?php echo $billing['billing_id']; ?>" class="hover:underline">
                                                #<?php echo $billing['billing_id']; ?>
                                            </a>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($billing['advocate_name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo formatCurrency($billing['amount']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo formatDate($billing['billing_date']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo formatDate($billing['due_date']); ?></div>
                                        <?php if ($billing['status'] !== 'paid' && $billing['due_date'] < date('Y-m-d')): ?>
                                            <div class="text-xs text-red-600">Overdue</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        $statusClass = 'bg-gray-100 text-gray-800';
                                        switch ($billing['status']) {
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
                                            <?php echo ucfirst($billing['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="../finance/invoice-details.php?id=<?php echo $billing['billing_id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($billing['status'] === 'pending' || $billing['status'] === 'overdue'): ?>
                                            <a href="../finance/mark-paid.php?id=<?php echo $billing['billing_id']; ?>" class="text-green-600 hover:text-green-900" title="Mark as Paid" onclick="return confirm('Are you sure you want to mark this invoice as paid?');">
                                                <i class="fas fa-check-circle"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 text-center">
                    <a href="../finance/invoices.php?case_id=<?php echo $case['case_id']; ?>" class="text-blue-600 hover:text-blue-900 text-sm font-medium">
                        View All Invoices
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Tab functionality
    document.addEventListener('DOMContentLoaded', function() {
        const tabButtons = document.querySelectorAll('.tab-button');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                const tabName = button.getAttribute('data-tab');
                
                // Hide all tab contents
                tabContents.forEach(content => {
                    content.classList.add('hidden');
                    content.classList.remove('active');
                });
                
                // Remove active class from all buttons
                tabButtons.forEach(btn => {
                    btn.classList.remove('active');
                });
                
                // Show the selected tab content
                document.getElementById(`${tabName}-tab`).classList.remove('hidden');
                document.getElementById(`${tabName}-tab`).classList.add('active');
                
                // Add active class to the clicked button
                button.classList.add('active');
            });
        });
    });
</script>

<style>
    .tab-button {
        @apply px-6 py-3 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap;
    }
    
    .tab-button.active {
        @apply border-b-2 border-blue-500 text-blue-600;
    }
    
    .tab-content {
        display: none;
    }
    
    .tab-content.active {
        display: block;
    }
</style>

<?php
// Include footer
include '../includes/footer.php';
?>
