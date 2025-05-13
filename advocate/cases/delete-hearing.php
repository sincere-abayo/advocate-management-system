<?php
// Include required files
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is an advocate
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'advocate') {
    header("Location: ../../auth/login.php");
    exit;
}

// Check if hearing ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirectWithMessage('index.php', 'Invalid hearing ID', 'error');
    exit;
}

$hearingId = (int)$_GET['id'];

// Get advocate ID
$advocateId = null;
if (isset($_SESSION['advocate_id'])) {
    $advocateId = $_SESSION['advocate_id'];
} else {
    // Get advocate ID from database if not in session
    $conn = getDBConnection();
    $userStmt = $conn->prepare("
        SELECT ap.advocate_id 
        FROM advocate_profiles ap 
        WHERE ap.user_id = ?
    ");
    $userStmt->bind_param("i", $_SESSION['user_id']);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    
    if ($userResult->num_rows > 0) {
        $advocateData = $userResult->fetch_assoc();
        $advocateId = $advocateData['advocate_id'];
    } else {
        redirectWithMessage('index.php', 'Advocate profile not found', 'error');
        exit;
    }
    
    $userStmt->close();
}

// Get database connection
$conn = getDBConnection();

// Verify advocate has access to this hearing
$accessStmt = $conn->prepare("
    SELECT h.*, c.case_id, c.title as case_title, c.case_number
    FROM case_hearings h
    JOIN cases c ON h.case_id = c.case_id
    JOIN case_assignments ca ON c.case_id = ca.case_id
    WHERE h.hearing_id = ? AND ca.advocate_id = ?
");
$accessStmt->bind_param("ii", $hearingId, $advocateId);
$accessStmt->execute();
$accessResult = $accessStmt->get_result();

if ($accessResult->num_rows === 0) {
    $accessStmt->close();
    $conn->close();
    redirectWithMessage('index.php', 'You do not have access to this hearing', 'error');
    exit;
}

$hearing = $accessResult->fetch_assoc();
$accessStmt->close();

// Process deletion if confirmed
$confirmed = isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'yes';

if ($confirmed) {
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Add case activity before deleting the hearing
        $activityDesc = "Hearing deleted: " . $hearing['hearing_type'] . " scheduled for " . 
                        formatDate($hearing['hearing_date']) . " at " . 
                        formatTime($hearing['hearing_time']);
        
        $activityStmt = $conn->prepare("
            INSERT INTO case_activities (case_id, user_id, activity_type, description)
            VALUES (?, ?, 'hearing', ?)
        ");
        $activityStmt->bind_param("iis", $hearing['case_id'], $_SESSION['user_id'], $activityDesc);
        $activityResult = $activityStmt->execute();
        $activityStmt->close();
        
        if (!$activityResult) {
            throw new Exception("Failed to log hearing deletion activity");
        }
        
        // Delete the hearing
        $deleteStmt = $conn->prepare("DELETE FROM case_hearings WHERE hearing_id = ?");
        $deleteStmt->bind_param("i", $hearingId);
        $deleteResult = $deleteStmt->execute();
        $deleteStmt->close();
        
        if (!$deleteResult) {
            throw new Exception("Failed to delete hearing");
        }
        
        // Commit transaction
        $conn->commit();
        
        // Redirect with success message
        redirectWithMessage(
            'view.php?id=' . $hearing['case_id'], 
            'Hearing deleted successfully', 
            'success'
        );
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        // Log the error
        error_log("Hearing deletion error: " . $e->getMessage());
        
        // Set error message for display
        $error = 'Failed to delete hearing: ' . $e->getMessage();
    }
}

// Set page title
$pageTitle = "Delete Hearing";

// Include header
include_once '../includes/header.php';
?>

<div class="mb-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Delete Hearing</h1>
            <p class="text-gray-600">
                <a href="view.php?id=<?php echo $hearing['case_id']; ?>" class="text-blue-600 hover:underline">
                    <?php echo htmlspecialchars($hearing['case_number']); ?> - <?php echo htmlspecialchars($hearing['case_title']); ?>
                </a>
            </p>
        </div>
        
        <div class="mt-4 md:mt-0">
            <a href="hearing-details.php?id=<?php echo $hearingId; ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back to Hearing Details
            </a>
        </div>
    </div>
</div>

<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="p-6">
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p><?php echo $error; ?></p>
            </div>
        <?php endif; ?>
        
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-yellow-700">
                        <strong class="font-medium">Warning:</strong> You are about to delete this hearing. This action cannot be undone.
                    </p>
                </div>
            </div>
        </div>
        
        <div class="mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Hearing Details</h2>
            
            <div class="bg-gray-50 p-4 rounded-lg">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Hearing Type</p>
                        <p class="text-base text-gray-900"><?php echo htmlspecialchars($hearing['hearing_type']); ?></p>
                    </div>
                    
                    <div>
                        <p class="text-sm font-medium text-gray-500">Date & Time</p>
                        <p class="text-base text-gray-900">
                            <?php echo formatDate($hearing['hearing_date'], 'F j, Y'); ?> at 
                            <?php echo formatTime($hearing['hearing_time']); ?>
                        </p>
                    </div>
                    
                    <div>
                        <p class="text-sm font-medium text-gray-500">Status</p>
                        <p class="text-base text-gray-900"><?php echo ucfirst(htmlspecialchars($hearing['status'])); ?></p>
                    </div>
                    
                    <div>
                        <p class="text-sm font-medium text-gray-500">Location</p>
                        <p class="text-base text-gray-900">
                            <?php echo !empty($hearing['court_room']) ? htmlspecialchars($hearing['court_room']) : 'Not specified'; ?>
                        </p>
                    </div>
                </div>
                
                <?php if (!empty($hearing['description'])): ?>
                <div class="mt-4">
                    <p class="text-sm font-medium text-gray-500">Description</p>
                    <p class="text-base text-gray-900"><?php echo htmlspecialchars($hearing['description']); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <form method="POST" action="" class="space-y-6">
            <div class="flex items-center">
                <input type="checkbox" id="confirm_delete" name="confirm_delete" value="yes" class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 rounded">
                <label for="confirm_delete" class="ml-2 block text-sm text-gray-900">
                    I confirm that I want to delete this hearing and understand this action cannot be undone.
                </label>
            </div>
            
            <div class="flex justify-end space-x-4">
                <a href="hearing-details.php?id=<?php echo $hearingId; ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg">
                    Cancel
                </a>
                <button type="submit" id="delete_button" class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-lg opacity-50 cursor-not-allowed" disabled>
                    Delete Hearing
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const confirmCheckbox = document.getElementById('confirm_delete');
    const deleteButton = document.getElementById('delete_button');
    
    confirmCheckbox.addEventListener('change', function() {
        if (this.checked) {
            deleteButton.classList.remove('opacity-50', 'cursor-not-allowed');
            deleteButton.removeAttribute('disabled');
        } else {
            deleteButton.classList.add('opacity-50', 'cursor-not-allowed');
            deleteButton.setAttribute('disabled', 'disabled');
        }
    });
});
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>