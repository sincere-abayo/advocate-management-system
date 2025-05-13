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

// Check if case ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['flash_message'] = "Invalid case ID";
    $_SESSION['flash_type'] = "error";
    header("Location: index.php");
    exit;
}

$caseId = (int)$_GET['id'];

// Connect to database
$conn = getDBConnection();

// Get case details
$query = "
    SELECT 
        c.*,
        GROUP_CONCAT(DISTINCT CONCAT(u.full_name, '|', ap.advocate_id) SEPARATOR ',') as advocates
    FROM cases c
    LEFT JOIN case_assignments ca ON c.case_id = ca.case_id
    LEFT JOIN advocate_profiles ap ON ca.advocate_id = ap.advocate_id
    LEFT JOIN users u ON ap.user_id = u.user_id
    WHERE c.case_id = ? AND c.client_id = ?
    GROUP BY c.case_id
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $caseId, $clientId);
$stmt->execute();
$result = $stmt->get_result();

// Check if case exists and belongs to the client
if ($result->num_rows === 0) {
    $_SESSION['flash_message'] = "Case not found or you don't have permission to view it";
    $_SESSION['flash_type'] = "error";
    header("Location: index.php");
    exit;
}

$case = $result->fetch_assoc();

// Parse advocates
$advocatesList = [];
if (!empty($case['advocates'])) {
    $advocatesArray = explode(',', $case['advocates']);
    foreach ($advocatesArray as $advocateInfo) {
        list($name, $id) = explode('|', $advocateInfo);
        $advocatesList[] = [
            'name' => $name,
            'id' => $id
        ];
    }
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
";

$documentsStmt = $conn->prepare($documentsQuery);
$documentsStmt->bind_param("i", $caseId);
$documentsStmt->execute();
$documentsResult = $documentsStmt->get_result();

$documents = [];
while ($document = $documentsResult->fetch_assoc()) {
    $documents[] = $document;
}

// Get case activities
$activitiesQuery = "
    SELECT 
        ca.*,
        u.full_name
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

// Get upcoming hearings
$hearingsQuery = "
    SELECT 
        ch.*,
        u.full_name as created_by_name
    FROM case_hearings ch
    JOIN users u ON ch.created_by = u.user_id
    WHERE ch.case_id = ? AND ch.hearing_date >= CURDATE()
    ORDER BY ch.hearing_date ASC, ch.hearing_time ASC
";

$hearingsStmt = $conn->prepare($hearingsQuery);
$hearingsStmt->bind_param("i", $caseId);
$hearingsStmt->execute();
$hearingsResult = $hearingsStmt->get_result();

$upcomingHearings = [];
while ($hearing = $hearingsResult->fetch_assoc()) {
    $upcomingHearings[] = $hearing;
}

// Get past hearings
$pastHearingsQuery = "
    SELECT 
        ch.*,
        u.full_name as created_by_name
    FROM case_hearings ch
    JOIN users u ON ch.created_by = u.user_id
    WHERE ch.case_id = ? AND ch.hearing_date < CURDATE()
    ORDER BY ch.hearing_date DESC, ch.hearing_time DESC
";

$pastHearingsStmt = $conn->prepare($pastHearingsQuery);
$pastHearingsStmt->bind_param("i", $caseId);
$pastHearingsStmt->execute();
$pastHearingsResult = $pastHearingsStmt->get_result();

$pastHearings = [];
while ($hearing = $pastHearingsResult->fetch_assoc()) {
    $pastHearings[] = $hearing;
}

// Get case invoices
$invoicesQuery = "
    SELECT 
        b.*,
        u.full_name as advocate_name
    FROM billings b
    JOIN advocate_profiles ap ON b.advocate_id = ap.advocate_id
    JOIN users u ON ap.user_id = u.user_id
    WHERE b.case_id = ? AND b.client_id = ?
    ORDER BY b.billing_date DESC
";

$invoicesStmt = $conn->prepare($invoicesQuery);
$invoicesStmt->bind_param("ii", $caseId, $clientId);
$invoicesStmt->execute();
$invoicesResult = $invoicesStmt->get_result();

$invoices = [];
while ($invoice = $invoicesResult->fetch_assoc()) {
    $invoices[] = $invoice;
}

// Close connection
$conn->close();

// Helper function to get status badge class
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'pending':
            return 'bg-yellow-100 text-yellow-800';
        case 'active':
            return 'bg-blue-100 text-blue-800';
        case 'closed':
            return 'bg-gray-100 text-gray-800';
        case 'won':
            return 'bg-green-100 text-green-800';
        case 'lost':
            return 'bg-red-100 text-red-800';
        case 'settled':
            return 'bg-purple-100 text-purple-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

// Set page title
$pageTitle = $case['title'];
include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Case Header -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 flex items-center">
                    <?php echo htmlspecialchars($case['title']); ?>
                    <span class="ml-3 px-2.5 py-0.5 rounded-full text-sm font-medium <?php echo getStatusBadgeClass($case['status']); ?>">
                        <?php echo ucfirst(htmlspecialchars($case['status'])); ?>
                    </span>
                </h1>
                <p class="text-gray-600 mt-1">Case Number: <span class="font-medium"><?php echo htmlspecialchars($case['case_number']); ?></span></p>
            </div>
            <div class="mt-4 md:mt-0">
                <a href="index.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg inline-flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Cases
                </a>
            </div>
        </div>
    </div>

    <!-- Case Details Tabs -->
    <div x-data="{ activeTab: 'details' }" class="bg-white rounded-lg shadow-md overflow-hidden">
        <!-- Tab Navigation -->
        <div class="bg-gray-50 border-b border-gray-200">
            <nav class="flex flex-wrap">
                <button @click="activeTab = 'details'" :class="{ 'border-blue-500 text-blue-600': activeTab === 'details', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'details' }" class="py-4 px-6 font-medium text-sm border-b-2 focus:outline-none">
                    <i class="fas fa-info-circle mr-2"></i> Details
                </button>
                <button @click="activeTab = 'documents'" :class="{ 'border-blue-500 text-blue-600': activeTab === 'documents', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'documents' }" class="py-4 px-6 font-medium text-sm border-b-2 focus:outline-none">
                    <i class="fas fa-file-alt mr-2"></i> Documents <span class="ml-1 px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-800"><?php echo count($documents); ?></span>
                </button>
                <button @click="activeTab = 'hearings'" :class="{ 'border-blue-500 text-blue-600': activeTab === 'hearings', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'hearings' }" class="py-4 px-6 font-medium text-sm border-b-2 focus:outline-none">
                    <i class="fas fa-gavel mr-2"></i> Hearings <span class="ml-1 px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-800"><?php echo count($upcomingHearings) + count($pastHearings); ?></span>
                </button>
                <button @click="activeTab = 'invoices'" :class="{ 'border-blue-500 text-blue-600': activeTab === 'invoices', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'invoices' }" class="py-4 px-6 font-medium text-sm border-b-2 focus:outline-none">
                    <i class="fas fa-file-invoice-dollar mr-2"></i> Invoices <span class="ml-1 px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-800"><?php echo count($invoices); ?></span>
                </button>
                <button @click="activeTab = 'activity'" :class="{ 'border-blue-500 text-blue-600': activeTab === 'activity', 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'activity' }" class="py-4 px-6 font-medium text-sm border-b-2 focus:outline-none">
                    <i class="fas fa-history mr-2"></i> Activity <span class="ml-1 px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-800"><?php echo count($activities); ?></span>
                </button>
            </nav>
        </div>

        <!-- Tab Content -->
        <div class="p-6">
            <!-- Details Tab -->
            <div x-show="activeTab === 'details'">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Case Information</h3>
                        
                        <div class="space-y-4">
                            <div>
                                <p class="text-sm font-medium text-gray-500">Case Type</p>
                                <p class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($case['case_type']); ?></p>
                            </div>
                            
                            <div>
                                <p class="text-sm font-medium text-gray-500">Court</p>
                                <p class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($case['court'] ?? 'Not specified'); ?></p>
                            </div>
                            
                            <div>
                                <p class="text-sm font-medium text-gray-500">Filing Date</p>
                                <p class="mt-1 text-sm text-gray-900"><?php echo $case['filing_date'] ? date('F j, Y', strtotime($case['filing_date'])) : 'Not specified'; ?></p>
                            </div>
                            
                            <?php if ($case['status'] === 'closed' || $case['status'] === 'won' || $case['status'] === 'lost' || $case['status'] === 'settled'): ?>
                                <div>
                                    <p class="text-sm font-medium text-gray-500">Closing Date</p>
                                    <p class="mt-1 text-sm text-gray-900"><?php echo $case['closing_date'] ? date('F j, Y', strtotime($case['closing_date'])) : 'Not specified'; ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <div>
                                <p class="text-sm font-medium text-gray-500">Priority</p>
                                <p class="mt-1 text-sm">
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
                                        <?php echo ucfirst(htmlspecialchars($case['priority'])); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Assigned Advocates</h3>
                        
                        <?php if (empty($advocatesList)): ?>
                            <p class="text-sm text-gray-500">No advocates assigned to this case yet.</p>
                        <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach ($advocatesList as $advocate): ?>
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-blue-100 text-blue-600">
                                                <i class="fas fa-user-tie"></i>
                                            </span>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($advocate['name']); ?></p>
                                        </div>
                                        <div class="ml-auto">
                                            <a href="../appointments/request.php?advocate_id=<?php echo $advocate['id']; ?>&case_id=<?php echo $caseId; ?>" class="text-sm text-blue-600 hover:text-blue-800">
                                                <i class="fas fa-calendar-plus mr-1"></i> Schedule Meeting
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($case['description'])): ?>
                    <div class="mt-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Case Description</h3>
                        <div class="bg-gray-50 rounded-lg p-4 text-gray-700">
                            <?php echo nl2br(htmlspecialchars($case['description'])); ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="mt-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Next Steps</h3>
                    
                    <?php if (empty($upcomingHearings)): ?>
                        <p class="text-sm text-gray-500">No upcoming hearings scheduled.</p>
                    <?php else: ?>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-calendar-day text-blue-600"></i>
                                </div>
                                <div class="ml-3">
                                    <h4 class="text-sm font-medium text-blue-800">Next Hearing: <?php echo date('F j, Y', strtotime($upcomingHearings[0]['hearing_date'])); ?> at <?php echo date('g:i A', strtotime($upcomingHearings[0]['hearing_time'])); ?></h4>
                                    <p class="mt-1 text-sm text-blue-700">
                                        <?php echo htmlspecialchars($upcomingHearings[0]['hearing_type']); ?> 
                                        <?php if (!empty($upcomingHearings[0]['court_room'])): ?>
                                            at <?php echo htmlspecialchars($upcomingHearings[0]['court_room']); ?>
                                        <?php endif; ?>
                                    </p>
                                    <?php if (!empty($upcomingHearings[0]['description'])): ?>
                                        <p class="mt-1 text-sm text-blue-600"><?php echo htmlspecialchars($upcomingHearings[0]['description']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Documents Tab -->
            <div x-show="activeTab === 'documents'" x-cloak>
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Case Documents</h3>
                </div>
                
                <?php if (empty($documents)): ?>
                    <div class="text-center py-8">
                        <div class="text-gray-400 mb-2">
                            <i class="fas fa-file-alt text-5xl"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-1">No documents yet</h3>
                        <p class="text-gray-500">Documents related to your case will appear here</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Document</th>
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
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center">
                                                    <?php
                                                    $fileExtension = pathinfo($document['file_path'], PATHINFO_EXTENSION);
                                                    $iconClass = 'fa-file';
                                                    $iconColor = 'text-gray-500';
                                                    
                                                    switch (strtolower($fileExtension)) {
                                                        case 'pdf':
                                                            $iconClass = 'fa-file-pdf';
                                                            $iconColor = 'text-red-500';
                                                            break;
                                                        case 'doc':
                                                        case 'docx':
                                                            $iconClass = 'fa-file-word';
                                                            $iconColor = 'text-blue-500';
                                                            break;
                                                        case 'xls':
                                                        case 'xlsx':
                                                            $iconClass = 'fa-file-excel';
                                                            $iconColor = 'text-green-500';
                                                            break;
                                                        case 'jpg':
                                                        case 'jpeg':
                                                        case 'png':
                                                            $iconClass = 'fa-file-image';
                                                            $iconColor = 'text-purple-500';
                                                            break;
                                                    }
                                                    ?>
                                                    <i class="fas <?php echo $iconClass; ?> text-2xl <?php echo $iconColor; ?>"></i>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($document['title']); ?></div>
                                                    <?php if (!empty($document['description'])): ?>
                                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars(substr($document['description'], 0, 50) . (strlen($document['description']) > 50 ? '...' : '')); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                <?php echo htmlspecialchars($document['document_type'] ?? 'General'); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($document['uploaded_by_name']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('M d, Y', strtotime($document['upload_date'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="../../<?php echo $document['file_path']; ?>" target="_blank" class="text-blue-600 hover:text-blue-900">
                                                <i class="fas fa-download mr-1"></i> Download
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Hearings Tab -->
            <div x-show="activeTab === 'hearings'" x-cloak>
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Upcoming Hearings</h3>
                    
                    <?php if (empty($upcomingHearings)): ?>
                        <div class="bg-gray-50 rounded-lg p-4 text-gray-500">
                            <p>No upcoming hearings scheduled.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($upcomingHearings as $hearing): ?>
                                <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-4">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center">
                                            <div class="bg-blue-100 rounded-full p-2 text-blue-600">
                                                <i class="fas fa-gavel"></i>
                                            </div>
                                            <div class="ml-4">
                                                <h4 class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($hearing['hearing_type']); ?></h4>
                                                <p class="text-sm text-gray-500">
                                                    <?php echo date('l, F j, Y', strtotime($hearing['hearing_date'])); ?> at 
                                                    <?php echo date('g:i A', strtotime($hearing['hearing_time'])); ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div>
                                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                <?php echo ucfirst($hearing['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <?php if (!empty($hearing['court_room'])): ?>
                                            <div>
                                                <p class="text-xs text-gray-500">Court Room</p>
                                                <p class="text-sm font-medium"><?php echo htmlspecialchars($hearing['court_room']); ?></p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($hearing['judge'])): ?>
                                            <div>
                                                <p class="text-xs text-gray-500">Judge</p>
                                                <p class="text-sm font-medium"><?php echo htmlspecialchars($hearing['judge']); ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if (!empty($hearing['description'])): ?>
                                        <div class="mt-4 pt-4 border-t border-gray-200">
                                            <p class="text-sm text-gray-700"><?php echo nl2br(htmlspecialchars($hearing['description'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($pastHearings)): ?>
                    <div class="mt-8">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Past Hearings</h3>
                        
                        <div class="space-y-4">
                            <?php foreach ($pastHearings as $hearing): ?>
                                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center">
                                            <div class="bg-gray-200 rounded-full p-2 text-gray-600">
                                                <i class="fas fa-gavel"></i>
                                            </div>
                                            <div class="ml-4">
                                                <h4 class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($hearing['hearing_type']); ?></h4>
                                                <p class="text-sm text-gray-500">
                                                    <?php echo date('l, F j, Y', strtotime($hearing['hearing_date'])); ?> at 
                                                    <?php echo date('g:i A', strtotime($hearing['hearing_time'])); ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div>
                                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                <?php echo ucfirst($hearing['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    </div>
                                    
                                    <?php if (!empty($hearing['outcome'])): ?>
                                        <div class="mt-4 pt-4 border-t border-gray-200">
                                            <p class="text-xs text-gray-500 mb-1">Outcome</p>
                                            <p class="text-sm text-gray-700"><?php echo nl2br(htmlspecialchars($hearing['outcome'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($hearing['next_steps'])): ?>
                                        <div class="mt-4">
                                            <p class="text-xs text-gray-500 mb-1">Next Steps</p>
                                            <p class="text-sm text-gray-700"><?php echo nl2br(htmlspecialchars($hearing['next_steps'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Invoices Tab -->
            <div x-show="activeTab === 'invoices'" x-cloak>
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Invoices</h3>
                </div>
                
                <?php if (empty($invoices)): ?>
                    <div class="text-center py-8">
                        <div class="text-gray-400 mb-2">
                            <i class="fas fa-file-invoice-dollar text-5xl"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-1">No invoices yet</h3>
                        <p class="text-gray-500">Invoices related to your case will appear here</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice #</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($invoices as $invoice): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">INV-<?php echo str_pad($invoice['billing_id'], 5, '0', STR_PAD_LEFT); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('M d, Y', strtotime($invoice['billing_date'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('M d, Y', strtotime($invoice['due_date'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
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
                                            }
                                            ?>
                                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                                <?php echo ucfirst($invoice['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="../invoices/view.php?id=<?php echo $invoice['billing_id']; ?>" class="text-blue-600 hover:text-blue-900">
                                                <i class="fas fa-eye mr-1"></i> View
                                            </a>
                                            <?php if ($invoice['status'] === 'pending'): ?>
                                                <a href="../invoices/pay.php?id=<?php echo $invoice['billing_id']; ?>" class="ml-3 text-green-600 hover:text-green-900">
                                                    <i class="fas fa-credit-card mr-1"></i> Pay
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

            <!-- Activity Tab -->
            <div x-show="activeTab === 'activity'" x-cloak>
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Case Activity</h3>
                </div>
                
                <?php if (empty($activities)): ?>
                    <div class="text-center py-8">
                        <div class="text-gray-400 mb-2">
                            <i class="fas fa-history text-5xl"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-1">No activity yet</h3>
                        <p class="text-gray-500">Case activity will appear here</p>
                    </div>
                <?php else: ?>
                    <div class="flow-root">
                        <ul class="-mb-8">
                            <?php foreach ($activities as $index => $activity): ?>
                                <li>
                                    <div class="relative pb-8">
                                        <?php if ($index !== count($activities) - 1): ?>
                                            <span class="absolute top-5 left-5 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                                        <?php endif; ?>
                                        <div class="relative flex items-start space-x-3">
                                            <div class="relative">
                                                <?php
                                                $iconClass = 'bg-gray-400';
                                                $iconContent = '<i class="fas fa-info-circle text-white"></i>';
                                                
                                                switch ($activity['activity_type']) {
                                                    case 'update':
                                                        $iconClass = 'bg-blue-500';
                                                        $iconContent = '<i class="fas fa-edit text-white"></i>';
                                                        break;
                                                    case 'document':
                                                        $iconClass = 'bg-purple-500';
                                                        $iconContent = '<i class="fas fa-file-alt text-white"></i>';
                                                        break;
                                                    case 'hearing':
                                                        $iconClass = 'bg-green-500';
                                                        $iconContent = '<i class="fas fa-gavel text-white"></i>';
                                                        break;
                                                    case 'note':
                                                        $iconClass = 'bg-yellow-500';
                                                        $iconContent = '<i class="fas fa-sticky-note text-white"></i>';
                                                        break;
                                                    case 'status_change':
                                                        $iconClass = 'bg-red-500';
                                                        $iconContent = '<i class="fas fa-exchange-alt text-white"></i>';
                                                        break;
                                                }
                                                ?>
                                                <span class="h-10 w-10 rounded-full flex items-center justify-center <?php echo $iconClass; ?>">
                                                    <?php echo $iconContent; ?>
                                                </span>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($activity['full_name']); ?>
                                                    </div>
                                                    <p class="mt-0.5 text-sm text-gray-500">
                                                        <?php echo date('M d, Y \a\t h:i A', strtotime($activity['activity_date'])); ?>
                                                    </p>
                                                </div>
                                                <div class="mt-2 text-sm text-gray-700">
                                                    <p><?php echo nl2br(htmlspecialchars($activity['description'])); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    
</div>

<script>
// Check if there's a hash in the URL to set the active tab
document.addEventListener('DOMContentLoaded', function() {
    const hash = window.location.hash.substring(1);
    if (hash && ['details', 'documents', 'hearings', 'invoices', 'activity'].includes(hash)) {
        // Using Alpine.js to set the active tab
        const tabContainer = document.querySelector('[x-data]');
        if (tabContainer && tabContainer.__x) {
            tabContainer.__x.$data.activeTab = hash;
        }
    }
});
</script>

<?php
// Include footer
include '../includes/footer.php';
?>

