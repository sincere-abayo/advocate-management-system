<?php
// Set page title
$pageTitle = "Create New Case";

// Include header
include_once '../includes/header.php';

// Get advocate ID
$advocateId = $advocateData['advocate_id'];

// Create database connection
$conn = getDBConnection();

// Get all clients
$stmt = $conn->prepare("
    SELECT cp.client_id, u.full_name, u.email, u.phone
    FROM client_profiles cp
    JOIN users u ON cp.user_id = u.user_id
    WHERE u.status = 'active'
    ORDER BY u.full_name
");
$stmt->execute();
$clientsResult = $stmt->get_result();
$clients = [];
while ($row = $clientsResult->fetch_assoc()) {
    $clients[] = $row;
}

// Get case types (for dropdown)
$caseTypesQuery = "SELECT DISTINCT case_type FROM cases ORDER BY case_type";
$caseTypesResult = $conn->query($caseTypesQuery);
$caseTypes = [];
while ($row = $caseTypesResult->fetch_assoc()) {
    $caseTypes[] = $row['case_type'];
}

// Get courts (for dropdown)
$courtsQuery = "SELECT DISTINCT court FROM cases WHERE court IS NOT NULL AND court != '' ORDER BY court";
$courtsResult = $conn->query($courtsQuery);
$courts = [];
while ($row = $courtsResult->fetch_assoc()) {
    $courts[] = $row['court'];
}

// Process form submission
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate inputs
    $clientId = isset($_POST['client_id']) ? (int)$_POST['client_id'] : 0;
    $title = sanitizeInput($_POST['title'] ?? '');
    $caseType = sanitizeInput($_POST['case_type'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $court = sanitizeInput($_POST['court'] ?? '');
    $filingDate = sanitizeInput($_POST['filing_date'] ?? '');
    $hearingDate = sanitizeInput($_POST['hearing_date'] ?? '');
    $status = sanitizeInput($_POST['status'] ?? 'pending');
    $priority = sanitizeInput($_POST['priority'] ?? 'medium');
    
    // Validate required fields
    if (empty($clientId)) {
        $errors['client_id'] = 'Please select a client';
    }
    
    if (empty($title)) {
        $errors['title'] = 'Case title is required';
    }
    
    if (empty($caseType)) {
        $errors['case_type'] = 'Case type is required';
    }
    
    // If no errors, proceed with case creation
    if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Generate unique case number
            $year = date('Y');
            $month = date('m');
            
            // Get the latest case number for this month/year
            $caseNumberQuery = "SELECT MAX(CAST(SUBSTRING_INDEX(case_number, '-', -1) AS UNSIGNED)) as max_num 
                               FROM cases 
                               WHERE case_number LIKE ?";
            $caseNumberStmt = $conn->prepare($caseNumberQuery);
            $caseNumberPattern = "CASE-$year$month-%";
            $caseNumberStmt->bind_param("s", $caseNumberPattern);
            $caseNumberStmt->execute();
            $caseNumberResult = $caseNumberStmt->get_result();
            $maxNum = $caseNumberResult->fetch_assoc()['max_num'] ?? 0;
            
            // Create new case number
            $newNum = $maxNum + 1;
            $caseNumber = "CASE-$year$month-" . str_pad($newNum, 4, '0', STR_PAD_LEFT);
            
            // Insert case
            $stmt = $conn->prepare("
                INSERT INTO cases (
                    case_number, title, description, case_type, court, 
                    filing_date, hearing_date, status, priority, client_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            if (empty($filingDate)) {
                $filingDate = null;
            }
            
            if (empty($hearingDate)) {
                $hearingDate = null;
            }
            
            $stmt->bind_param(
                "sssssssssi",
                $caseNumber, $title, $description, $caseType, $court,
                $filingDate, $hearingDate, $status, $priority, $clientId
            );
            $stmt->execute();
            $caseId = $conn->insert_id;
            
            // Assign case to advocate
            $assignStmt = $conn->prepare("
                INSERT INTO case_assignments (case_id, advocate_id, role)
                VALUES (?, ?, 'primary')
            ");
            $assignStmt->bind_param("ii", $caseId, $advocateId);
            $assignStmt->execute();
            
            // Add case activity
            $activityDesc = "Case created with status: $status";
                       // Add case activity
                       $activityDesc = "Case created with status: $status";
                       $activityStmt = $conn->prepare("
                           INSERT INTO case_activities (case_id, user_id, activity_type, description)
                           VALUES (?, ?, 'update', ?)
                       ");
                       $activityStmt->bind_param("iis", $caseId, $_SESSION['user_id'], $activityDesc);
                       $activityStmt->execute();
                       
                       // Create notification for client
                       $clientUserId = null;
                       $clientUserQuery = $conn->prepare("SELECT user_id FROM client_profiles WHERE client_id = ?");
                       $clientUserQuery->bind_param("i", $clientId);
                       $clientUserQuery->execute();
                       $clientUserResult = $clientUserQuery->get_result();
                       if ($clientUserRow = $clientUserResult->fetch_assoc()) {
                           $clientUserId = $clientUserRow['user_id'];
                           
                           // Create notification
                           $notificationTitle = "New Case Created";
                           $notificationMessage = "A new case '$title' has been created for you with case number: $caseNumber";
                           $notificationStmt = $conn->prepare("
                               INSERT INTO notifications (user_id, title, message, related_to, related_id)
                               VALUES (?, ?, ?, 'case', ?)
                           ");
                           $notificationStmt->bind_param("issi", $clientUserId, $notificationTitle, $notificationMessage, $caseId);
                           $notificationStmt->execute();
                       }
                       
                       // Commit transaction
                       $conn->commit();
                       
                       // Set success message
                       $_SESSION['flash_message'] = "Case created successfully with case number: $caseNumber";
                       $_SESSION['flash_type'] = "success";
                       
                       // Redirect to case view
                       header("Location: /advocate/cases/view.php?id=$caseId");
                       exit;
                       
                   } catch (Exception $e) {
                       // Rollback transaction on error
                       $conn->rollback();
                       $errors['general'] = "An error occurred: " . $e->getMessage(). " he$hearingDate";
                   }
               }
           }
           
           // Close connection
           $conn->close();
           ?>
           
           <div class="mb-6">
               <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                   <h1 class="text-2xl font-semibold text-gray-800">Create New Case</h1>
                   <div class="mt-4 md:mt-0">
                       <a href="index.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg inline-flex items-center">
                           <i class="fas fa-arrow-left mr-2"></i> Back to Cases
                       </a>
                   </div>
               </div>
               
               <p class="text-gray-600 mt-2">Create a new legal case and assign it to a client.</p>
           </div>
           
           <!-- Case Creation Form -->
           <div class="bg-white rounded-lg shadow-md overflow-hidden">
               <div class="p-6">
                   <?php if (!empty($errors['general'])): ?>
                       <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                           <p><?php echo $errors['general']; ?></p>
                       </div>
                   <?php endif; ?>
                   
                   <form method="POST" action="" class="space-y-6">
                       <!-- Client Selection -->
                       <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                           <div>
                               <label for="client_id" class="block text-sm font-medium text-gray-700 mb-1">Client *</label>
                               <select id="client_id" name="client_id" class="form-select w-full <?php echo isset($errors['client_id']) ? 'border-red-500' : ''; ?>" required>
                                   <option value="">Select Client</option>
                                   <?php foreach ($clients as $client): ?>
                                       <option value="<?php echo $client['client_id']; ?>" <?php echo (isset($_POST['client_id']) && $_POST['client_id'] == $client['client_id']) ? 'selected' : ''; ?>>
                                           <?php echo htmlspecialchars($client['full_name']); ?> (<?php echo htmlspecialchars($client['email']); ?>)
                                       </option>
                                   <?php endforeach; ?>
                               </select>
                               <?php if (isset($errors['client_id'])): ?>
                                   <p class="mt-1 text-sm text-red-500"><?php echo $errors['client_id']; ?></p>
                               <?php endif; ?>
                               
                               <div class="mt-2">
                                   <a href="/advocate/clients/create.php" class="text-blue-600 hover:underline text-sm inline-flex items-center">
                                       <i class="fas fa-plus-circle mr-1"></i> Add New Client
                                   </a>
                               </div>
                           </div>
                           
                           <div>
                               <label for="case_type" class="block text-sm font-medium text-gray-700 mb-1">Case Type *</label>
                               <div class="relative">
                               <select id="case_type" name="case_type" class="form-select w-full <?php echo isset($errors['case_type']) ? 'border-red-500' : ''; ?>" required>
    <option value="">Select Case Type</option>
    <option value="Civil" <?php echo (isset($_POST['case_type']) && $_POST['case_type'] == 'Civil') ? 'selected' : ''; ?>>Civil</option>
    <option value="Criminal" <?php echo (isset($_POST['case_type']) && $_POST['case_type'] == 'Criminal') ? 'selected' : ''; ?>>Criminal</option>
    <option value="Family" <?php echo (isset($_POST['case_type']) && $_POST['case_type'] == 'Family') ? 'selected' : ''; ?>>Family</option>
    <option value="Corporate" <?php echo (isset($_POST['case_type']) && $_POST['case_type'] == 'Corporate') ? 'selected' : ''; ?>>Corporate</option>
    <option value="Property" <?php echo (isset($_POST['case_type']) && $_POST['case_type'] == 'Property') ? 'selected' : ''; ?>>Property</option>
    <option value="Taxation" <?php echo (isset($_POST['case_type']) && $_POST['case_type'] == 'Taxation') ? 'selected' : ''; ?>>Taxation</option>
    <option value="Intellectual Property" <?php echo (isset($_POST['case_type']) && $_POST['case_type'] == 'Intellectual Property') ? 'selected' : ''; ?>>Intellectual Property</option>
    <option value="Labor" <?php echo (isset($_POST['case_type']) && $_POST['case_type'] == 'Labor') ? 'selected' : ''; ?>>Labor</option>
    <option value="Constitutional" <?php echo (isset($_POST['case_type']) && $_POST['case_type'] == 'Constitutional') ? 'selected' : ''; ?>>Constitutional</option>
    <option value="Environmental" <?php echo (isset($_POST['case_type']) && $_POST['case_type'] == 'Environmental') ? 'selected' : ''; ?>>Environmental</option>
    <option value="Immigration" <?php echo (isset($_POST['case_type']) && $_POST['case_type'] == 'Immigration') ? 'selected' : ''; ?>>Immigration</option>
    <option value="Bankruptcy" <?php echo (isset($_POST['case_type']) && $_POST['case_type'] == 'Bankruptcy') ? 'selected' : ''; ?>>Bankruptcy</option>
    <option value="Medical Malpractice" <?php echo (isset($_POST['case_type']) && $_POST['case_type'] == 'Medical Malpractice') ? 'selected' : ''; ?>>Medical Malpractice</option>
    <option value="Personal Injury" <?php echo (isset($_POST['case_type']) && $_POST['case_type'] == 'Personal Injury') ? 'selected' : ''; ?>>Personal Injury</option>
    <option value="Insurance" <?php echo (isset($_POST['case_type']) && $_POST['case_type'] == 'Insurance') ? 'selected' : ''; ?>>Insurance</option>
    <option value="Administrative" <?php echo (isset($_POST['case_type']) && $_POST['case_type'] == 'Administrative') ? 'selected' : ''; ?>>Administrative</option>
    <option value="Arbitration" <?php echo (isset($_POST['case_type']) && $_POST['case_type'] == 'Arbitration') ? 'selected' : ''; ?>>Arbitration</option>
    <option value="Other" <?php echo (isset($_POST['case_type']) && $_POST['case_type'] == 'Other') ? 'selected' : ''; ?>>Other</option>
</select>
                                   <datalist id="case_type_list">
                                       <?php foreach ($caseTypes as $type): ?>
                                           <option value="<?php echo htmlspecialchars($type); ?>">
                                       <?php endforeach; ?>
                                   </datalist>
                               </div>
                               <?php if (isset($errors['case_type'])): ?>
                                   <p class="mt-1 text-sm text-red-500"><?php echo $errors['case_type']; ?></p>
                               <?php endif; ?>
                           </div>
                       </div>
                       
                       <!-- Case Title and Status -->
                       <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                           <div>
                               <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Case Title *</label>
                               <input type="text" id="title" name="title" class="form-input w-full <?php echo isset($errors['title']) ? 'border-red-500' : ''; ?>" value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" required>
                               <?php if (isset($errors['title'])): ?>
                                   <p class="mt-1 text-sm text-red-500"><?php echo $errors['title']; ?></p>
                               <?php endif; ?>
                           </div>
                           
                           <div>
                               <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                               <select id="status" name="status" class="form-select w-full">
                                   <option value="pending" <?php echo (isset($_POST['status']) && $_POST['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                   <option value="active" <?php echo (isset($_POST['status']) && $_POST['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                   <option value="closed" <?php echo (isset($_POST['status']) && $_POST['status'] == 'closed') ? 'selected' : ''; ?>>Closed</option>
                                   <option value="won" <?php echo (isset($_POST['status']) && $_POST['status'] == 'won') ? 'selected' : ''; ?>>Won</option>
                                   <option value="lost" <?php echo (isset($_POST['status']) && $_POST['status'] == 'lost') ? 'selected' : ''; ?>>Lost</option>
                                   <option value="settled" <?php echo (isset($_POST['status']) && $_POST['status'] == 'settled') ? 'selected' : ''; ?>>Settled</option>
                               </select>
                           </div>
                       </div>
                       
                       <!-- Court and Priority -->
                       <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                           <div>
                               <label for="court" class="block text-sm font-medium text-gray-700 mb-1">Court</label>
                               <input type="text" id="court" name="court" list="court_list" class="form-input w-full" value="<?php echo isset($_POST['court']) ? htmlspecialchars($_POST['court']) : ''; ?>">
                               <datalist id="court_list">
                                   <?php foreach ($courts as $court): ?>
                                       <option value="<?php echo htmlspecialchars($court); ?>">
                                   <?php endforeach; ?>
                               </datalist>
                           </div>
                           
                           <div>
                               <label for="priority" class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                               <select id="priority" name="priority" class="form-select w-full">
                                   <option value="low" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'low') ? 'selected' : ''; ?>>Low</option>
                                   <option value="medium" <?php echo (!isset($_POST['priority']) || $_POST['priority'] == 'medium') ? 'selected' : ''; ?>>Medium</option>
                                   <option value="high" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'high') ? 'selected' : ''; ?>>High</option>
                               </select>
                           </div>
                       </div>
                       
                       <!-- Filing Date and Hearing Date -->
                       <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                           <div>
                               <label for="filing_date" class="block text-sm font-medium text-gray-700 mb-1">Filing Date</label>
                               <input type="date" id="filing_date" name="filing_date" class="form-input w-full" value="<?php echo isset($_POST['filing_date']) ? htmlspecialchars($_POST['filing_date']) : ''; ?>">
                           </div>
                           
                           <div>
                               <label for="hearing_date" class="block text-sm font-medium text-gray-700 mb-1">Next Hearing Date</label>
                               <input type="date" id="hearing_date" name="hearing_date" class="form-input w-full" value="<?php echo isset($_POST['hearing_date']) ? htmlspecialchars($_POST['hearing_date']) : null; ?>">
                           </div>
                       </div>
                       
                       <!-- Description -->
                       <div>
                           <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Case Description</label>
                           <textarea id="description" name="description" rows="5" class="form-textarea w-full"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                       </div>
                       
                       <!-- Submit Button -->
                       <div class="flex justify-end">
                           <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-lg inline-flex items-center">
                               <i class="fas fa-save mr-2"></i> Create Case
                           </button>
                       </div>
                   </form>
               </div>
           </div>
           
           <script>
           document.addEventListener('DOMContentLoaded', function() {
               // Client selection enhancement
               const clientSelect = document.getElementById('client_id');
               if (clientSelect) {
                   // Add any client selection enhancements here
               }
               
               // Date validation - filing date cannot be in the future
               const filingDateInput = document.getElementById('filing_date');
               if (filingDateInput) {
                   const today = new Date().toISOString().split('T')[0];
                   filingDateInput.setAttribute('max', today);
               }
               
               // Date validation - hearing date cannot be in the past
               const hearingDateInput = document.getElementById('hearing_date');
               if (hearingDateInput) {
                   const today = new Date().toISOString().split('T')[0];
                   hearingDateInput.setAttribute('min', today);
               }
           });
           </script>
           
           <?php
           // Include footer
           include_once '../includes/footer.php';
           ?>
           