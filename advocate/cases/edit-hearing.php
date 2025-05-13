<?php
// Set page title
$pageTitle = "Edit Hearing";

// Include header
include_once '../includes/header.php';

// Get advocate ID
$advocateId = $advocateData['advocate_id'];

// Check if hearing ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirectWithMessage($path_url.'advocate/cases/index.php', 'Invalid hearing ID', 'error');
}

$hearingId = (int)$_GET['id'];

// Create database connection
$conn = getDBConnection();

// Get hearing details and verify advocate has access
$stmt = $conn->prepare("
    SELECT h.*, c.case_id, c.case_number, c.title as case_title, c.status as case_status,
           u.full_name as created_by_name
    FROM case_hearings h
    JOIN cases c ON h.case_id = c.case_id
    JOIN case_assignments ca ON c.case_id = ca.case_id
    JOIN users u ON h.created_by = u.user_id
    WHERE h.hearing_id = ? AND ca.advocate_id = ?
");
$stmt->bind_param("ii", $hearingId, $advocateId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    redirectWithMessage($path_url.'advocate/cases/index.php', 'You do not have access to this hearing', 'error');
}

$hearing = $result->fetch_assoc();
$stmt->close();

// Initialize form data with current hearing details
$formData = [
    'hearing_date' => $hearing['hearing_date'],
    'hearing_time' => $hearing['hearing_time'],
    'hearing_type' => $hearing['hearing_type'],
    'court_room' => $hearing['court_room'],
    'judge' => $hearing['judge'],
    'description' => $hearing['description'],
    'outcome' => $hearing['outcome'],
    'next_steps' => $hearing['next_steps'],
    'status' => $hearing['status']
];

// Check if we should prompt for outcome
$promptOutcome = isset($_GET['prompt_outcome']) && $_GET['prompt_outcome'] == 1;

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
    
    // If status is completed, outcome should be provided
    if ($formData['status'] === 'completed' && empty($formData['outcome'])) {
        $errors['outcome'] = 'Outcome is required for completed hearings';
    }
    
    // If no errors, update hearing
    if (empty($errors)) {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Update hearing record
            $updateStmt = $conn->prepare("
                UPDATE case_hearings 
                SET hearing_date = ?, hearing_time = ?, hearing_type = ?, 
                    court_room = ?, judge = ?, description = ?, 
                    outcome = ?, next_steps = ?, status = ?, 
                    updated_at = NOW()
                WHERE hearing_id = ?
            ");
            $updateStmt->bind_param(
                "sssssssssi",
                $formData['hearing_date'],
                $formData['hearing_time'],
                $formData['hearing_type'],
                $formData['court_room'],
                $formData['judge'],
                $formData['description'],
                $formData['outcome'],
                $formData['next_steps'],
                $formData['status'],
                $hearingId
            );
            
            $updateResult = $updateStmt->execute();
            $updateStmt->close();
            
            if (!$updateResult) {
                throw new Exception("Failed to update hearing record");
            }
            
            // Add case activity
            $activityDesc = "Hearing updated: " . $formData['hearing_type'] . " on " . 
                            formatDate($formData['hearing_date']) . " at " . 
                            formatTime($formData['hearing_time']);
            
            $activityStmt = $conn->prepare("
                INSERT INTO case_activities (case_id, user_id, activity_type, description)
                VALUES (?, ?, 'hearing', ?)
            ");
            $activityStmt->bind_param("iis", $hearing['case_id'], $_SESSION['user_id'], $activityDesc);
            $activityResult = $activityStmt->execute();
            $activityStmt->close();
            
            if (!$activityResult) {
                throw new Exception("Failed to log hearing update activity");
            }
            
            // Commit transaction
            $conn->commit();
            
            // Redirect with success message
            redirectWithMessage(
                $path_url.'advocate/cases/hearing-details.php?id=' . $hearingId, 
                'Hearing updated successfully', 
                'success'
            );
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            
            // Set error message
            $errors['general'] = "Failed to update hearing: " . $e->getMessage();
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
            <h1 class="text-2xl font-bold text-gray-800">Edit Hearing</h1>
            <p class="text-gray-600">
                <a href="<?php echo $path_url; ?>advocate/cases/view.php?id=<?php echo $hearing['case_id']; ?>" class="text-blue-600 hover:underline">
                    <?php echo htmlspecialchars($hearing['case_number']); ?> - <?php echo htmlspecialchars($hearing['case_title']); ?>
                </a>
            </p>
        </div>
        
        <div class="mt-4 md:mt-0">
            <a href="<?php echo $path_url; ?>advocate/cases/hearing-details.php?id=<?php echo $hearingId; ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back to Hearing Details
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
        
        <?php if ($promptOutcome): ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-800 p-4 mb-6" role="alert">
                <p class="font-medium">Please add outcome details for this completed hearing.</p>
                <p>Since you've marked this hearing as completed, please provide details about the outcome and any next steps.</p>
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
                <div id="outcome-container" class="<?php echo $formData['status'] !== 'completed' ? 'opacity-50' : ''; ?>">
                    <label for="outcome" class="block text-sm font-medium text-gray-700 mb-1">
                        Outcome <?php echo $formData['status'] === 'completed' ? '*' : ''; ?>
                    </label>
                    <textarea id="outcome" name="outcome" rows="3" class="form-textarea w-full <?php echo isset($errors['outcome']) ? 'border-red-500' : ''; ?>" placeholder="Enter the outcome of the hearing (required for completed hearings)" <?php echo $formData['status'] !== 'completed' ? 'disabled' : ''; ?>><?php echo htmlspecialchars($formData['outcome']); ?></textarea>
                    <?php if (isset($errors['outcome'])): ?>
                        <p class="text-red-500 text-sm mt-1"><?php echo $errors['outcome']; ?></p>
                    <?php endif; ?>
                </div>
                
                <div>
                    <label for="next_steps" class="block text-sm font-medium text-gray-700 mb-1">Next Steps</label>
                    <textarea id="next_steps" name="next_steps" rows="3" class="form-textarea w-full" placeholder="Enter any follow-up actions or next steps"><?php echo htmlspecialchars($formData['next_steps']); ?></textarea>
                </div>
            </div>
            
            <div class="flex justify-end space-x-4">
                <a href="<?php echo $path_url; ?>advocate/cases/hearing-details.php?id=<?php echo $hearingId; ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg">
                    Cancel
                </a>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg">
                    Update Hearing
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

<script>
// Show/hide outcome field based on status
document.addEventListener('DOMContentLoaded', function() {
    const statusSelect = document.getElementById('status');
    const outcomeContainer = document.getElementById('outcome-container');
    const outcomeField = document.getElementById('outcome');
    
    function toggleOutcomeVisibility() {
        if (statusSelect.value === 'completed') {
            outcomeContainer.classList.remove('opacity-50');
            outcomeField.removeAttribute('disabled');
            outcomeField.setAttribute('required', 'required');
        } else {
            outcomeContainer.classList.add('opacity-50');
            outcomeField.setAttribute('disabled', 'disabled');
            outcomeField.removeAttribute('required');
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