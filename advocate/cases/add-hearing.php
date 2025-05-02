<?php
// Set page title
$pageTitle = "Add Hearing";

// Include header
include_once '../includes/header.php';

// Get advocate ID
$advocateId = $advocateData['advocate_id'];

// Check if case ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirectWithMessage('../cases/index.php', 'Invalid case ID', 'error');
    exit;
}

$caseId = (int)$_GET['id'];

// Get database connection
$conn = getDBConnection();

// Verify advocate has access to this case
$accessStmt = $conn->prepare("
    SELECT c.case_id, c.title, c.case_number, c.status
    FROM cases c
    JOIN case_assignments ca ON c.case_id = ca.case_id
    WHERE c.case_id = ? AND ca.advocate_id = ?
");
$accessStmt->bind_param("ii", $caseId, $advocateId);
$accessStmt->execute();
$accessResult = $accessStmt->get_result();

if ($accessResult->num_rows === 0) {
    $accessStmt->close();
    $conn->close();
    redirectWithMessage('../cases/index.php', 'You do not have access to this case', 'error');
    exit;
}

$caseData = $accessResult->fetch_assoc();
$accessStmt->close();

// Initialize form data and errors
$formData = [
    'hearing_date' => date('Y-m-d'),
    'hearing_time' => '09:00',
    'hearing_type' => '',
    'court_room' => '',
    'judge' => '',
    'description' => '',
    'outcome' => '',
    'next_steps' => '',
    'status' => 'scheduled'
];
$errors = [];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form data
    $formData['hearing_date'] = sanitizeInput($_POST['hearing_date'] ?? '');
    $formData['hearing_time'] = sanitizeInput($_POST['hearing_time'] ?? '');
    $formData['hearing_type'] = sanitizeInput($_POST['hearing_type'] ?? '');
    $formData['court_room'] = sanitizeInput($_POST['court_room'] ?? '');
    $formData['judge'] = sanitizeInput($_POST['judge'] ?? '');
    $formData['description'] = sanitizeInput($_POST['description'] ?? '');
    $formData['outcome'] = sanitizeInput($_POST['outcome'] ?? '');
    $formData['next_steps'] = sanitizeInput($_POST['next_steps'] ?? '');
    $formData['status'] = sanitizeInput($_POST['status'] ?? 'scheduled');
    
    // Validate required fields
    if (empty($formData['hearing_date'])) {
        $errors['hearing_date'] = 'Hearing date is required';
    }
    
    if (empty($formData['hearing_time'])) {
        $errors['hearing_time'] = 'Hearing time is required';
    }
    
    if (empty($formData['hearing_type'])) {
        $errors['hearing_type'] = 'Hearing type is required';
    }
    
    // If no errors, insert hearing
    if (empty($errors)) {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Insert hearing record
            $insertStmt = $conn->prepare("
                INSERT INTO case_hearings (
                    case_id, hearing_date, hearing_time, hearing_type, 
                    court_room, judge, description, outcome, next_steps, status, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insertStmt->bind_param(
                "isssssssssi",
                $caseId,
                $formData['hearing_date'],
                $formData['hearing_time'],
                $formData['hearing_type'],
                $formData['court_room'],
                $formData['judge'],
                $formData['description'],
                $formData['outcome'],
                $formData['next_steps'],
                $formData['status'],
                $_SESSION['user_id']
            );
            
            $insertResult = $insertStmt->execute();
            $hearingId = $insertStmt->insert_id;
            $insertStmt->close();
            
            if (!$insertResult) {
                throw new Exception("Failed to insert hearing record");
            }
            
            // Add case activity
            $activityDesc = "Hearing added: " . $formData['hearing_type'] . " on " . 
                            formatDate($formData['hearing_date']) . " at " . 
                            formatTime($formData['hearing_time']);
            
            $activityStmt = $conn->prepare("
                INSERT INTO case_activities (case_id, user_id, activity_type, description)
                VALUES (?, ?, 'hearing', ?)
            ");
            $activityStmt->bind_param("iis", $caseId, $_SESSION['user_id'], $activityDesc);
            $activityResult = $activityStmt->execute();
            $activityStmt->close();
            
            if (!$activityResult) {
                throw new Exception("Failed to log hearing activity");
            }
            
            // If hearing is in the future, create a notification for the advocate
            if (strtotime($formData['hearing_date']) > time()) {
                $notificationTitle = "Upcoming Hearing: " . $caseData['case_number'];
                $notificationMessage = "You have a " . $formData['hearing_type'] . " hearing scheduled on " . 
                                      formatDate($formData['hearing_date']) . " at " . 
                                      formatTime($formData['hearing_time']) . " for case " . 
                                      $caseData['case_number'] . ".";
                
                $notificationStmt = $conn->prepare("
                    INSERT INTO notifications (user_id, title, message, related_to, related_id)
                    VALUES (?, ?, ?, 'hearing', ?)
                ");
                $notificationStmt->bind_param("issi", $_SESSION['user_id'], $notificationTitle, $notificationMessage, $hearingId);
                $notificationStmt->execute();
                $notificationStmt->close();
            }
            
            // Commit transaction
            $conn->commit();
            
            // Redirect with success message
            redirectWithMessage(
                '../cases/view.php?id=' . $caseId, 
                'Hearing added successfully', 
                'success'
            );
            exit;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            
            // Set error message
            $errors['general'] = "Failed to add hearing: " . $e->getMessage();
        }
    }
}

// Get hearing types for dropdown
$hearingTypes = [
    'Initial Appearance', 'Arraignment', 'Status Conference', 'Pre-Trial Conference',
    'Motion Hearing', 'Evidentiary Hearing', 'Trial', 'Sentencing', 'Appeal Hearing',
    'Mediation', 'Arbitration', 'Settlement Conference', 'Case Management Conference',
    'Disposition Hearing', 'Preliminary Hearing', 'Grand Jury', 'Bail Hearing',
    'Suppression Hearing', 'Probation Violation Hearing', 'Other'
];

// Close database connection
$conn->close();
?>

<div class="mb-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Add Hearing</h1>
            <p class="text-gray-600">
                Add a new hearing for case: 
                <a href="../cases/view.php?id=<?php echo $caseId; ?>" class="text-blue-600 hover:underline">
                    <?php echo htmlspecialchars($caseData['case_number']); ?> - <?php echo htmlspecialchars($caseData['title']); ?>
                </a>
            </p>
        </div>
        
        <div class="mt-4 md:mt-0">
            <a href="../cases/view.php?id=<?php echo $caseId; ?>" class="btn-secondary">
                <i class="fas fa-arrow-left mr-2"></i> Back to Case
            </a>
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
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="hearing_date" class="block text-sm font-medium text-gray-700 mb-1">Hearing Date *</label>
                    <input type="date" id="hearing_date" name="hearing_date" class="form-input w-full <?php echo isset($errors['hearing_date']) ? 'border-red-500' : ''; ?>" value="<?php echo htmlspecialchars($formData['hearing_date']); ?>" required>
                    <?php if (isset($errors['hearing_date'])): ?>
                        <p class="text-red-500 text-sm mt-1"><?php echo $errors['hearing_date']; ?></p>
                    <?php endif; ?>
                </div>
                
                <div>
                    <label for="hearing_time" class="block text-sm font-medium text-gray-700 mb-1">Hearing Time *</label>
                    <input type="time" id="hearing_time" name="hearing_time" class="form-input w-full <?php echo isset($errors['hearing_time']) ? 'border-red-500' : ''; ?>" value="<?php echo htmlspecialchars($formData['hearing_time']); ?>" required>
                    <?php if (isset($errors['hearing_time'])): ?>
                        <p class="text-red-500 text-sm mt-1"><?php echo $errors['hearing_time']; ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="hearing_type" class="block text-sm font-medium text-gray-700 mb-1">Hearing Type *</label>
                    <select id="hearing_type" name="hearing_type" class="form-select w-full <?php echo isset($errors['hearing_type']) ? 'border-red-500' : ''; ?>" required>
                        <option value="">Select Hearing Type</option>
                        <?php foreach ($hearingTypes as $type): ?>
                            <option value="<?php echo $type; ?>" <?php echo $formData['hearing_type'] === $type ? 'selected' : ''; ?>>
                                <?php echo $type; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['hearing_type'])): ?>
                        <p class="text-red-500 text-sm mt-1"><?php echo $errors['hearing_type']; ?></p>
                    <?php endif; ?>
                </div>
                
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="status" name="status" class="form-select w-full">
                        <option value="scheduled" <?php echo $formData['status'] === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                        <option value="completed" <?php echo $formData['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $formData['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        <option value="postponed" <?php echo $formData['status'] === 'postponed' ? 'selected' : ''; ?>>Postponed</option>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="court_room" class="block text-sm font-medium text-gray-700 mb-1">Court Room</label>
                    <input type="text" id="court_room" name="court_room" class="form-input w-full" value="<?php echo htmlspecialchars($formData['court_room']); ?>" placeholder="e.g., Room 302, Courtroom B">
                </div>
                
                <div>
                    <label for="judge" class="block text-sm font-medium text-gray-700 mb-1">Judge</label>
                    <input type="text" id="judge" name="judge" class="form-input w-full" value="<?php echo htmlspecialchars($formData['judge']); ?>" placeholder="e.g., Hon. Judge Smith">
                </div>
            </div>
            
            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea id="description" name="description" rows="3" class="form-textarea w-full" placeholder="Enter details about the hearing purpose, agenda, etc."><?php echo htmlspecialchars($formData['description']); ?></textarea>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="outcome" class="block text-sm font-medium text-gray-700 mb-1">Outcome</label>
                    <textarea id="outcome" name="outcome" rows="3" class="form-textarea w-full" placeholder="Enter the outcome of the hearing (if completed)"><?php echo htmlspecialchars($formData['outcome']); ?></textarea>
                </div>
                
                <div>
                    <label for="next_steps" class="block text-sm font-medium text-gray-700 mb-1">Next Steps</label>
                    <textarea id="next_steps" name="next_steps" rows="3" class="form-textarea w-full" placeholder="Enter any follow-up actions or next steps"><?php echo htmlspecialchars($formData['next_steps']); ?></textarea>
                </div>
            </div>
            
            <div class="flex justify-end space-x-4">
                <a href="../cases/view.php?id=<?php echo $caseId; ?>" class="btn-secondary">
                    Cancel
                </a>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-plus mr-2"></i> Add Hearing
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.btn-primary {
    @apply bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition duration-150 inline-flex items-center;
}

.btn-secondary {
    @apply bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg transition duration-150 inline-flex items-center;
}

.form-input, .form-select, .form-textarea {
    @apply mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500;
}
</style>

<script>
// Show/hide outcome field based on status
document.addEventListener('DOMContentLoaded', function() {
    const statusSelect = document.getElementById('status');
    const outcomeField = document.getElementById('outcome').parentNode;
    
    function toggleOutcomeVisibility() {
        if (statusSelect.value === 'completed') {
            outcomeField.classList.remove('opacity-50');
            outcomeField.querySelector('textarea').removeAttribute('disabled');
        } else {
            outcomeField.classList.add('opacity-50');
            outcomeField.querySelector('textarea').setAttribute('disabled', 'disabled');
        }
    }
    
    // Initial check
    toggleOutcomeVisibility();
    
    // Listen for changes
    statusSelect.addEventListener('change', toggleOutcomeVisibility);
});
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>
