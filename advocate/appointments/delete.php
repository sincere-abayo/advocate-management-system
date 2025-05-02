<?php
// Set page title
$pageTitle = "Delete Appointment";

// Include header
include_once '../includes/header.php';

// Check if appointment ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirectWithMessage('index.php', 'No appointment specified', 'error');
    exit;
}

$appointmentId = (int)$_GET['id'];
$advocateId = $advocateData['advocate_id'];

// Get database connection
$conn = getDBConnection();

// Get appointment details for confirmation
$stmt = $conn->prepare("
    SELECT a.*, 
           c.full_name as client_name, 
           cs.case_id, cs.title as case_title
    FROM appointments a
    JOIN client_profiles cp ON a.client_id = cp.client_id
    JOIN users c ON cp.user_id = c.user_id
    LEFT JOIN cases cs ON a.case_id = cs.case_id
    WHERE a.appointment_id = ? AND a.advocate_id = ?
");

$stmt->bind_param("ii", $appointmentId, $advocateId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    redirectWithMessage('index.php', 'Appointment not found or you do not have permission to delete it', 'error');
    exit;
}

$appointment = $result->fetch_assoc();

// Process deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    // Check if we need to add a case activity before deleting
    $caseId = $appointment['case_id'];
    
    // Delete the appointment
    $deleteStmt = $conn->prepare("DELETE FROM appointments WHERE appointment_id = ? AND advocate_id = ?");
    $deleteStmt->bind_param("ii", $appointmentId, $advocateId);
    
    if ($deleteStmt->execute()) {
        // If this was related to a case, add case activity
        if (!empty($caseId)) {
            $activityDesc = "Appointment deleted: " . $appointment['title'];
            addCaseActivity($caseId, $_SESSION['user_id'], 'update', $activityDesc);
        }
        
        // Set success message and redirect
        $_SESSION['flash_message'] = "Appointment deleted successfully.";
        $_SESSION['flash_type'] = "success";
        header("Location: index.php");
        exit;
    } else {
        $error = "Failed to delete appointment. Please try again.";
    }
    
    $deleteStmt->close();
}

// Close database connection
$stmt->close();
$conn->close();
?>

<div class="mb-6">
    <div class="flex items-center space-x-2">
        <a href="index.php" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left"></i> Back to Appointments
        </a>
        <span class="text-gray-400">|</span>
        <a href="view.php?id=<?php echo $appointmentId; ?>" class="text-blue-600 hover:text-blue-800">
            View Appointment
        </a>
    </div>
</div>

<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="bg-red-50 px-6 py-4 border-b border-red-200">
        <h2 class="text-xl font-semibold text-red-700">Delete Appointment</h2>
    </div>
    
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
                        <strong>Warning:</strong> This action cannot be undone. This will permanently delete the appointment.
                    </p>
                </div>
            </div>
        </div>
        
        <div class="mb-6">
            <h3 class="text-lg font-medium text-gray-900 mb-2">Appointment Details</h3>
            
            <div class="bg-gray-50 p-4 rounded-lg">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Title</p>
                        <p class="font-medium"><?php echo htmlspecialchars($appointment['title']); ?></p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-500">Client</p>
                        <p class="font-medium"><?php echo htmlspecialchars($appointment['client_name']); ?></p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-500">Date & Time</p>
                        <p class="font-medium">
                            <?php echo formatDate($appointment['appointment_date']); ?><br>
                            <?php echo formatTime($appointment['start_time']); ?> - <?php echo formatTime($appointment['end_time']); ?>
                        </p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-500">Status</p>
                        <p class="font-medium"><?php echo ucfirst($appointment['status']); ?></p>
                    </div>
                    
                    <?php if (!empty($appointment['case_id'])): ?>
                        <div>
                            <p class="text-sm text-gray-500">Related Case</p>
                            <p class="font-medium"><?php echo htmlspecialchars($appointment['case_title']); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <form method="POST" action="" onsubmit="return confirm('Are you sure you want to delete this appointment? This action cannot be undone.');">
            <div class="flex justify-end space-x-4">
                <a href="view.php?id=<?php echo $appointmentId; ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg">
                    Cancel
                </a>
                <button type="submit" name="confirm_delete" value="1" class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-lg">
                    <i class="fas fa-trash-alt mr-2"></i> Delete Appointment
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* Form styling for consistency across the application */
.form-label {
    @apply block text-sm font-medium text-gray-700 mb-1;
}

.form-input, .form-select, .form-textarea {
    @apply rounded-md shadow-sm border-gray-300 focus:ring-blue-500 focus:border-blue-500 transition duration-150;
}

.btn-primary {
    @apply bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition duration-150 inline-flex items-center;
}

.btn-secondary {
    @apply bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg transition duration-150 inline-flex items-center;
}

.btn-danger {
    @apply bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-lg transition duration-150 inline-flex items-center;
}
</style>

<?php
// Include footer
include_once '../includes/footer.php';
?>