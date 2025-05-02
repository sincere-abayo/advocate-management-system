<?php
ob_start();
// Set page title
$pageTitle = "Edit Case";

// Include header
include_once '../includes/header.php';

// Check if case ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirectWithMessage(  $path_url.'advocate/cases/index.php', 'Case ID is required', 'error');
    exit;
}

$caseId = (int)$_GET['id'];
$errors = [];

// Get database connection
$conn = getDBConnection();

// Get case details
$stmt = $conn->prepare("
    SELECT c.*, cp.client_id, u.full_name as client_name, u.email as client_email, u.phone as client_phone
    FROM cases c
    JOIN client_profiles cp ON c.client_id = cp.client_id
    JOIN users u ON cp.user_id = u.user_id
    WHERE c.case_id = ?
");
$stmt->bind_param("i", $caseId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    redirectWithMessage($path_url.'advocate/cases/index.php', 'Case not found', 'error');
    exit;
}

$case = $result->fetch_assoc();

// Check if the advocate is assigned to this case
$stmt = $conn->prepare("
    SELECT * FROM case_assignments 
    WHERE case_id = ? AND advocate_id = ?
");
$stmt->bind_param("ii", $caseId, $advocateData['advocate_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    redirectWithMessage($path_url.'advocate/cases/index.php', 'You are not authorized to edit this case', 'error');
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate and sanitize input
    $title = sanitizeInput($_POST['title']);
    $description = sanitizeInput($_POST['description']);
    $caseType = sanitizeInput($_POST['case_type']);
    $court = sanitizeInput($_POST['court']);
    $filingDate = !empty($_POST['filing_date']) ? $_POST['filing_date'] : null;
    $hearingDate = !empty($_POST['hearing_date']) ? $_POST['hearing_date'] : null;
    $status = sanitizeInput($_POST['status']);
    $priority = sanitizeInput($_POST['priority']);
    
    // Validate required fields
    if (empty($title)) {
        $errors['title'] = 'Case title is required';
    }
    
    if (empty($caseType)) {
        $errors['case_type'] = 'Case type is required';
    }
    
    // If no errors, update the case
    if (empty($errors)) {
        $stmt = $conn->prepare("
            UPDATE cases 
            SET title = ?, description = ?, case_type = ?, court = ?, 
                filing_date = ?, hearing_date = ?, status = ?, priority = ?
            WHERE case_id = ?
        ");
        
        $stmt->bind_param(
            "ssssssssi",
            $title, $description, $caseType, $court,
            $filingDate, $hearingDate, $status, $priority, $caseId
        );
        
        if ($stmt->execute()) {
            // Add case activity
            $activityDesc = "Case details updated";
            $activityStmt = $conn->prepare("
                INSERT INTO case_activities (case_id, user_id, activity_type, description)
                VALUES (?, ?, 'update', ?)
            ");
            $activityStmt->bind_param("iis", $caseId, $_SESSION['user_id'], $activityDesc);
            $activityStmt->execute();
            
            // Redirect back to case view
            redirectWithMessage(
                
                
                
                
                $path_url."advocate/cases/view.php?id=$caseId", 'Case updated successfully', 'success');
            exit;
        } else {
            $errors['general'] = 'An error occurred while updating the case: ' . $conn->error;
        }
    }
}

// Close connection
$conn->close();
?>

<div class="mb-6">
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-semibold text-gray-800">Edit Case</h1>
        <a href="<?php echo $path_url; ?>advocate/cases/view.php?id=<?php echo $caseId; ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg inline-flex items-center">
            <i class="fas fa-arrow-left mr-2"></i> Back to Case
        </a>
    </div>
</div>

<div class="bg-white rounded-lg shadow-md p-6">
    <?php if (!empty($errors['general'])): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p><?php echo $errors['general']; ?></p>
        </div>
    <?php endif; ?>
    
    <form method="POST" action="">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Case Title *</label>
                <input type="text" id="title" name="title" class="form-input w-full <?php echo isset($errors['title']) ? 'border-red-500' : ''; ?>" value="<?php echo htmlspecialchars($case['title']); ?>" required>
                <?php if (isset($errors['title'])): ?>
                    <p class="text-red-500 text-sm mt-1"><?php echo $errors['title']; ?></p>
                <?php endif; ?>
            </div>
            
            <div>
                <label for="case_type" class="block text-sm font-medium text-gray-700 mb-1">Case Type *</label>
                <select id="case_type" name="case_type" class="form-select w-full <?php echo isset($errors['case_type']) ? 'border-red-500' : ''; ?>" required>
                    <option value="">Select Case Type</option>
                    <option value="Civil" <?php echo ($case['case_type'] == 'Civil') ? 'selected' : ''; ?>>Civil</option>
                    <option value="Criminal" <?php echo ($case['case_type'] == 'Criminal') ? 'selected' : ''; ?>>Criminal</option>
                    <option value="Family" <?php echo ($case['case_type'] == 'Family') ? 'selected' : ''; ?>>Family</option>
                    <option value="Corporate" <?php echo ($case['case_type'] == 'Corporate') ? 'selected' : ''; ?>>Corporate</option>
                    <option value="Property" <?php echo ($case['case_type'] == 'Property') ? 'selected' : ''; ?>>Property</option>
                    <option value="Taxation" <?php echo ($case['case_type'] == 'Taxation') ? 'selected' : ''; ?>>Taxation</option>
                    <option value="Intellectual Property" <?php echo ($case['case_type'] == 'Intellectual Property') ? 'selected' : ''; ?>>Intellectual Property</option>
                    <option value="Labor" <?php echo ($case['case_type'] == 'Labor') ? 'selected' : ''; ?>>Labor</option>
                    <option value="Constitutional" <?php echo ($case['case_type'] == 'Constitutional') ? 'selected' : ''; ?>>Constitutional</option>
                    <option value="Environmental" <?php echo ($case['case_type'] == 'Environmental') ? 'selected' : ''; ?>>Environmental</option>
                    <option value="Immigration" <?php echo ($case['case_type'] == 'Immigration') ? 'selected' : ''; ?>>Immigration</option>
                    <option value="Bankruptcy" <?php echo ($case['case_type'] == 'Bankruptcy') ? 'selected' : ''; ?>>Bankruptcy</option>
                    <option value="Medical Malpractice" <?php echo ($case['case_type'] == 'Medical Malpractice') ? 'selected' : ''; ?>>Medical Malpractice</option>
                    <option value="Personal Injury" <?php echo ($case['case_type'] == 'Personal Injury') ? 'selected' : ''; ?>>Personal Injury</option>
                    <option value="Insurance" <?php echo ($case['case_type'] == 'Insurance') ? 'selected' : ''; ?>>Insurance</option>
                    <option value="Administrative" <?php echo ($case['case_type'] == 'Administrative') ? 'selected' : ''; ?>>Administrative</option>
                    <option value="Arbitration" <?php echo ($case['case_type'] == 'Arbitration') ? 'selected' : ''; ?>>Arbitration</option>
                    <option value="Other" <?php echo ($case['case_type'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                </select>
                <?php if (isset($errors['case_type'])): ?>
                    <p class="text-red-500 text-sm mt-1"><?php echo $errors['case_type']; ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label for="court" class="block text-sm font-medium text-gray-700 mb-1">Court</label>
                <input type="text" id="court" name="court" class="form-input w-full" value="<?php echo htmlspecialchars($case['court'] ?? ''); ?>">
            </div>
            
            <div>
                <label for="case_number" class="block text-sm font-medium text-gray-700 mb-1">Case Number</label>
                <input type="text" id="case_number" name="case_number" class="form-input w-full bg-gray-100" value="<?php echo htmlspecialchars($case['case_number']); ?>" readonly>
                <p class="text-gray-500 text-xs mt-1">Case number cannot be changed</p>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label for="filing_date" class="block text-sm font-medium text-gray-700 mb-1">Filing Date</label>
                <input type="date" id="filing_date" name="filing_date" class="form-input w-full" value="<?php echo $case['filing_date'] ?? ''; ?>">
            </div>
            
            <div>
                <label for="hearing_date" class="block text-sm font-medium text-gray-700 mb-1">Next Hearing Date</label>
                <input type="date" id="hearing_date" name="hearing_date" class="form-input w-full" value="<?php echo $case['hearing_date'] ?? ''; ?>">
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select id="status" name="status" class="form-select w-full">
                    <option value="pending" <?php echo ($case['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                    <option value="active" <?php echo ($case['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                    <option value="closed" <?php echo ($case['status'] == 'closed') ? 'selected' : ''; ?>>Closed</option>
                    <option value="won" <?php echo ($case['status'] == 'won') ? 'selected' : ''; ?>>Won</option>
                    <option value="lost" <?php echo ($case['status'] == 'lost') ? 'selected' : ''; ?>>Lost</option>
                    <option value="settled" <?php echo ($case['status'] == 'settled') ? 'selected' : ''; ?>>Settled</option>
                </select>
            </div>
            
            <div>
                <label for="priority" class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                <select id="priority" name="priority" class="form-select w-full">
                    <option value="low" <?php echo ($case['priority'] == 'low') ? 'selected' : ''; ?>>Low</option>
                    <option value="medium" <?php echo ($case['priority'] == 'medium') ? 'selected' : ''; ?>>Medium</option>
                    <option value="high" <?php echo ($case['priority'] == 'high') ? 'selected' : ''; ?>>High</option>
                </select>
            </div>
        </div>
        
        <div class="mb-6">
            <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Case Description</label>
            <textarea id="description" name="description" rows="6" class="form-textarea w-full"><?php echo htmlspecialchars($case['description'] ?? ''); ?></textarea>
        </div>
        
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">Client Information</label>
            <div class="bg-gray-50 p-4 rounded-lg">
                <div class="flex items-center">
                    <div class="w-10 h-10 rounded-full bg-blue-500 flex items-center justify-center text-white font-bold text-xl mr-3">
                        <?php echo strtoupper(substr($case['client_name'], 0, 1)); ?>
                    </div>
                    <div>
                        <div class="font-medium"><?php echo htmlspecialchars($case['client_name']); ?></div>
                        <div class="text-sm text-gray-600">
                            <?php echo htmlspecialchars($case['client_email']); ?>
                            <?php if (!empty($case['client_phone'])): ?>
                                • <?php echo htmlspecialchars($case['client_phone']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    </div>
                <p class="text-gray-500 text-xs mt-3">Client information cannot be changed here. To update client details, please visit the client profile.</p>
            </div>
        </div>
        
        <div class="flex justify-end space-x-4">
            <a href="<?php echo $path_url; ?>advocate/cases/view.php?id=<?php echo $caseId; ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg">
                Cancel
            </a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg">
                Update Case
            </button>
        </div>
    </form>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
ob_end_flush();
?>
