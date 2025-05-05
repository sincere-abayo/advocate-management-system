<?php
// Set page title
$pageTitle = "Add Case Activity";

// Include header
include_once '../includes/header.php';

// Get advocate ID
$advocateId = $advocateData['advocate_id'];

// Check if case ID is provided
if (!isset($_GET['case_id']) || empty($_GET['case_id'])) {
    redirectWithMessage('index.php', 'Invalid case ID', 'error');
    exit;
}

$caseId = (int)$_GET['case_id'];
$hearingId = isset($_GET['hearing_id']) ? (int)$_GET['hearing_id'] : null;

// Get database connection
$conn = getDBConnection();

// Verify advocate has access to this case
$accessStmt = $conn->prepare("
    SELECT c.*, cp.user_id as client_user_id, u.full_name as client_name
    FROM cases c
    JOIN case_assignments ca ON c.case_id = ca.case_id
    JOIN client_profiles cp ON c.client_id = cp.client_id
    JOIN users u ON cp.user_id = u.user_id
    WHERE c.case_id = ? AND ca.advocate_id = ?
");
$accessStmt->bind_param("ii", $caseId, $advocateId);
$accessStmt->execute();
$accessResult = $accessStmt->get_result();

if ($accessResult->num_rows === 0) {
    $accessStmt->close();
    $conn->close();
    redirectWithMessage('index.php', 'You do not have access to this case', 'error');
    exit;
}

$case = $accessResult->fetch_assoc();
$accessStmt->close();

// Get hearing details if hearing_id is provided
$hearing = null;
if ($hearingId) {
    $hearingStmt = $conn->prepare("
        SELECT * FROM case_hearings WHERE hearing_id = ? AND case_id = ?
    ");
    $hearingStmt->bind_param("ii", $hearingId, $caseId);
    $hearingStmt->execute();
    $hearingResult = $hearingStmt->get_result();
    
    if ($hearingResult->num_rows > 0) {
        $hearing = $hearingResult->fetch_assoc();
    }
    $hearingStmt->close();
}

// Initialize form data
$formData = [
    'activity_type' => $hearingId ? 'hearing' : '',
    'description' => $hearingId ? 'Notes from hearing: ' . ($hearing ? $hearing['hearing_type'] . ' on ' . formatDate($hearing['hearing_date']) : '') : '',
    'notify_client' => false
];

$errors = [];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form data
    $formData['activity_type'] = sanitizeInput($_POST['activity_type'] ?? '');
    $formData['description'] = sanitizeInput($_POST['description'] ?? '');
    $formData['notify_client'] = isset($_POST['notify_client']);
    
    // Validate required fields
    if (empty($formData['activity_type'])) {
        $errors['activity_type'] = 'Activity type is required';
    }
    
    if (empty($formData['description'])) {
        $errors['description'] = 'Description is required';
    }
    
    // If no errors, add activity
    if (empty($errors)) {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Add case activity
            $activityStmt = $conn->prepare("
                INSERT INTO case_activities (case_id, user_id, activity_type, description)
                VALUES (?, ?, ?, ?)
            ");
            $activityStmt->bind_param("iiss", $caseId, $_SESSION['user_id'], $formData['activity_type'], $formData['description']);
            $activityResult = $activityStmt->execute();
            $activityId = $conn->insert_id;
            $activityStmt->close();
            
            if (!$activityResult) {
                throw new Exception("Failed to add case activity");
            }
            
            // If notify client is checked, create notification
            if ($formData['notify_client']) {
                $notificationTitle = "New activity in your case";
                $notificationMessage = "A new " . $formData['activity_type'] . " activity has been added to your case: " . $case['title'];
                
                $notifyStmt = $conn->prepare("
                    INSERT INTO notifications (user_id, title, message, related_to, related_id)
                    VALUES (?, ?, ?, 'case_activity', ?)
                ");
                $notifyStmt->bind_param("issi", $case['client_user_id'], $notificationTitle, $notificationMessage, $activityId);
                $notifyResult = $notifyStmt->execute();
                $notifyStmt->close();
                
                if (!$notifyResult) {
                    throw new Exception("Failed to notify client");
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            // Redirect with success message
            if ($hearingId) {
                redirectWithMessage(
                    'hearing-details.php?id=' . $hearingId, 
                    'Activity added successfully', 
                    'success'
                );
            } else {
                redirectWithMessage(
                    'view.php?id=' . $caseId, 
                    'Activity added successfully', 
                    'success'
                );
            }
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            
            // Set error message
            $errors['general'] = "Failed to add activity: " . $e->getMessage();
        }
    }
}

// Get activity types for dropdown
$activityTypes = [
    'update' => 'Case Update',
    'document' => 'Document Related',
    'hearing' => 'Hearing Related',
    'note' => 'General Note',
    'status_change' => 'Status Change',
    'client_communication' => 'Client Communication',
    'court_filing' => 'Court Filing',
    'research' => 'Legal Research',
    'settlement' => 'Settlement Discussion',
    'billing' => 'Billing Related',
    'other' => 'Other'
];

// Close database connection
$conn->close();
?>

<div class="mb-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Add Case Activity</h1>
            <p class="text-gray-600">
                <a href="view.php?id=<?php echo $caseId; ?>" class="text-blue-600 hover:underline">
                    <?php echo htmlspecialchars($case['case_number']); ?> - <?php echo htmlspecialchars($case['title']); ?>
                </a>
                <?php if ($hearingId && $hearing): ?>
                    <span class="mx-2">•</span>
                    <a href="hearing-details.php?id=<?php echo $hearingId; ?>" class="text-blue-600 hover:underline">
                        <?php echo htmlspecialchars($hearing['hearing_type']); ?> Hearing
                    </a>
                <?php endif; ?>
            </p>
        </div>
        
        <div class="mt-4 md:mt-0">
            <?php if ($hearingId): ?>
                <a href="hearing-details.php?id=<?php echo $hearingId; ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg inline-flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Hearing
                </a>
            <?php else: ?>
                <a href="view.php?id=<?php echo $caseId; ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg inline-flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Case
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="p-6">
        <?php if (isset($errors['general'])): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p><?php echo $errors['general']; ?></p>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" class="space-y-6">
            <div>
                <label for="activity_type" class="block text-sm font-medium text-gray-700 mb-1">Activity Type *</label>
                <select id="activity_type" name="activity_type" class="form-select w-full <?php echo isset($errors['activity_type']) ? 'border-red-500' : ''; ?>" required>
                    <option value="">Select Activity Type</option>
                    <?php foreach ($activityTypes as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo $formData['activity_type'] === $value ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['activity_type'])): ?>
                    <p class="text-red-500 text-sm mt-1"><?php echo $errors['activity_type']; ?></p>
                <?php endif; ?>
            </div>
            
            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description *</label>
                <textarea id="description" name="description" rows="5" class="form-textarea w-full <?php echo isset($errors['description']) ? 'border-red-500' : ''; ?>" placeholder="Enter activity details" required><?php echo htmlspecialchars($formData['description']); ?></textarea>
                <?php if (isset($errors['description'])): ?>
                    <p class="text-red-500 text-sm mt-1"><?php echo $errors['description']; ?></p>
                <?php endif; ?>
                <p class="text-sm text-gray-500 mt-1">
                    Provide detailed information about this activity. This will be visible in the case timeline.
                </p>
            </div>
            
            <div class="flex items-center">
                <input type="checkbox" id="notify_client" name="notify_client" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" <?php echo $formData['notify_client'] ? 'checked' : ''; ?>>
                <label for="notify_client" class="ml-2 block text-sm text-gray-900">
                    Notify client (<?php echo htmlspecialchars($case['client_name']); ?>) about this activity
                </label>
            </div>
            
            <div class="flex justify-end space-x-4">
                <?php if ($hearingId): ?>
                    <a href="hearing-details.php?id=<?php echo $hearingId; ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg">
                        Cancel
                    </a>
                <?php else: ?>
                    <a href="view.php?id=<?php echo $caseId; ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg">
                        Cancel
                    </a>
                <?php endif; ?>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg">
                    Add Activity
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.form-input, .form-select, .form-textarea {
    @apply mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500;
}
</style>

<?php
// Include footer
include_once '../includes/footer.php';
?>