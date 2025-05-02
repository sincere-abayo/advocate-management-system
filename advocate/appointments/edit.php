<?php
// Set page title
$pageTitle = "Edit Appointment";

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

// Get appointment details
$stmt = $conn->prepare("
    SELECT a.*, 
           c.full_name as client_name, 
           cs.case_id, cs.case_number, cs.title as case_title,
           cp.client_id
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
    redirectWithMessage('index.php', 'Appointment not found or you do not have permission to edit it', 'error');
    exit;
}

$appointment = $result->fetch_assoc();

// Get all clients for dropdown
$clientsStmt = $conn->prepare("
    SELECT DISTINCT cp.client_id, u.full_name
    FROM client_profiles cp
    JOIN users u ON cp.user_id = u.user_id
    JOIN cases c ON cp.client_id = c.client_id
    JOIN case_assignments ca ON c.case_id = ca.case_id
    WHERE ca.advocate_id = ?
    ORDER BY u.full_name
");


$clientsStmt->bind_param("i", $advocateId);
$clientsStmt->execute();
$clientsResult = $clientsStmt->get_result();

$clients = [];
while ($client = $clientsResult->fetch_assoc()) {
    $clients[] = $client;
}

// Initialize form data with current appointment values
$formData = [
    'title' => $appointment['title'],
    'client_id' => $appointment['client_id'],
    'case_id' => $appointment['case_id'] ?? '',
    'appointment_date' => $appointment['appointment_date'],
    'start_time' => $appointment['start_time'],
    'end_time' => $appointment['end_time'],
    'location' => $appointment['location'] ?? '',
    'description' => $appointment['description'] ?? '',
    'status' => $appointment['status']
];

// Initialize errors array
$errors = [];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $formData['title'] = sanitizeInput($_POST['title']);
    $formData['client_id'] = (int)$_POST['client_id'];
    $formData['case_id'] = !empty($_POST['case_id']) ? (int)$_POST['case_id'] : null;
    $formData['appointment_date'] = sanitizeInput($_POST['appointment_date']);
    $formData['start_time'] = sanitizeInput($_POST['start_time']);
    $formData['end_time'] = sanitizeInput($_POST['end_time']);
    $formData['location'] = sanitizeInput($_POST['location']);
    $formData['description'] = sanitizeInput($_POST['description']);
    $formData['status'] = sanitizeInput($_POST['status']);
    
    // Validate required fields
    if (empty($formData['title'])) {
        $errors['title'] = 'Appointment title is required';
    }
    
    if (empty($formData['client_id'])) {
        $errors['client_id'] = 'Client is required';
    }
    
    if (empty($formData['appointment_date'])) {
        $errors['appointment_date'] = 'Appointment date is required';
    } elseif (strtotime($formData['appointment_date']) < strtotime(date('Y-m-d'))) {
        $errors['appointment_date'] = 'Appointment date cannot be in the past';
    }
    
    if (empty($formData['start_time'])) {
        $errors['start_time'] = 'Start time is required';
    }
    
    if (empty($formData['end_time'])) {
        $errors['end_time'] = 'End time is required';
    } elseif ($formData['start_time'] >= $formData['end_time']) {
        $errors['end_time'] = 'End time must be after start time';
    }
    
   // If no errors, update the appointment
if (empty($errors)) {
    if ($formData['case_id'] === null) {
        // SQL with NULL for case_id
        $stmt = $conn->prepare("
            UPDATE appointments 
            SET client_id = ?, case_id = NULL, title = ?, description = ?, 
                appointment_date = ?, start_time = ?, end_time = ?, 
                location = ?, status = ?
            WHERE appointment_id = ? AND advocate_id = ?
        ");
        
        $stmt->bind_param(
            "isssssssii", 
            $formData['client_id'], 
            $formData['title'], 
            $formData['description'], 
            $formData['appointment_date'], 
            $formData['start_time'], 
            $formData['end_time'], 
            $formData['location'], 
            $formData['status'],
            $appointmentId,
            $advocateId
        );
    } else {
        // SQL with case_id parameter
        $stmt = $conn->prepare("
            UPDATE appointments 
            SET client_id = ?, case_id = ?, title = ?, description = ?, 
                appointment_date = ?, start_time = ?, end_time = ?, 
                location = ?, status = ?
            WHERE appointment_id = ? AND advocate_id = ?
        ");
        
        $stmt->bind_param(
            "iisssssssii", 
            $formData['client_id'], 
            $formData['case_id'],
            $formData['title'], 
            $formData['description'], 
            $formData['appointment_date'], 
            $formData['start_time'], 
            $formData['end_time'], 
            $formData['location'], 
            $formData['status'],
            $appointmentId,
            $advocateId
        );
    }
    
    if ($stmt->execute()) {
        // If this is related to a case, add case activity
        if (!empty($formData['case_id'])) {
            $activityDesc = "Appointment updated: " . $formData['title'];
            addCaseActivity($formData['case_id'], $_SESSION['user_id'], 'update', $activityDesc);
        }
        
        // Set success message and redirect
        $_SESSION['flash_message'] = "Appointment updated successfully.";
        $_SESSION['flash_type'] = "success";
        header("Location: view.php?id=$appointmentId");
        exit;
    } else {
        $errors['general'] = "Failed to update appointment. Please try again.";
    }
}

}

// Close database connections
$stmt->close();
$clientsStmt->close();
?>

<div class="mb-6">
    <div class="flex justify-between items-center">
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
</div>

<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
        <h2 class="text-xl font-semibold text-gray-800">Edit Appointment</h2>
    </div>
    
    <div class="p-6">
        <?php if (isset($errors['general'])): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p><?php echo $errors['general']; ?></p>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Appointment Title *</label>
                    <input type="text" id="title" name="title" class="form-input w-full <?php echo isset($errors['title']) ? 'border-red-500' : ''; ?>" value="<?php echo htmlspecialchars($formData['title']); ?>" required>
                    <?php if (isset($errors['title'])): ?>
                        <p class="text-red-500 text-sm mt-1"><?php echo $errors['title']; ?></p>
                    <?php endif; ?>
                </div>
                
                <div>
                    <label for="client_id" class="block text-sm font-medium text-gray-700 mb-1">Client *</label>
                    <select id="client_id" name="client_id" class="form-select w-full <?php echo isset($errors['client_id']) ? 'border-red-500' : ''; ?>" required>
                        <option value="">Select Client</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?php echo $client['client_id']; ?>" <?php echo $formData['client_id'] == $client['client_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($client['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['client_id'])): ?>
                        <p class="text-red-500 text-sm mt-1"><?php echo $errors['client_id']; ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label for="appointment_date" class="block text-sm font-medium text-gray-700 mb-1">Appointment Date *</label>
                    <input type="date" id="appointment_date" name="appointment_date" class="form-input w-full <?php echo isset($errors['appointment_date']) ? 'border-red-500' : ''; ?>" value="<?php echo $formData['appointment_date']; ?>" required>
                    <?php if (isset($errors['appointment_date'])): ?>
                        <p class="text-red-500 text-sm mt-1"><?php echo $errors['appointment_date']; ?></p>
                    <?php endif; ?>
                </div>
                
                <div>
                    <label for="case_id" class="block text-sm font-medium text-gray-700 mb-1">Related Case</label>
                    <select id="case_id" name="case_id" class="form-select w-full">
                        <option value="">No Related Case</option>
                        <?php if (!empty($appointment['case_id'])): ?>
                            <option value="<?php echo $appointment['case_id']; ?>" selected>
                                <?php echo htmlspecialchars($appointment['case_number'] . ' - ' . $appointment['case_title']); ?>
                            </option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label for="start_time" class="block text-sm font-medium text-gray-700 mb-1">Start Time *</label>
                    <input type="time" id="start_time" name="start_time" class="form-input w-full <?php echo isset($errors['start_time']) ? 'border-red-500' : ''; ?>" value="<?php echo $formData['start_time']; ?>" required>
                    <?php if (isset($errors['start_time'])): ?>
                        <p class="text-red-500 text-sm mt-1"><?php echo $errors['start_time']; ?></p>
                    <?php endif; ?>
                </div>
                
                <div>
                    <label for="end_time" class="block text-sm font-medium text-gray-700 mb-1">End Time *</label>
                    <input type="time" id="end_time" name="end_time" class="form-input w-full <?php echo isset($errors['end_time']) ? 'border-red-500' : ''; ?>" value="<?php echo $formData['end_time']; ?>" required>
                    <?php if (isset($errors['end_time'])): ?>
                        <p class="text-red-500 text-sm mt-1"><?php echo $errors['end_time']; ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="mb-6">
                <label for="location" class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                <input type="text" id="location" name="location" class="form-input w-full" value="<?php echo htmlspecialchars($formData['location']); ?>" placeholder="Office, Court, Video Call, etc.">
            </div>
            
            <div class="mb-6">
                <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea id="description" name="description" rows="4" class="form-textarea w-full" placeholder="Enter appointment details, agenda, or notes"><?php echo htmlspecialchars($formData['description']); ?></textarea>
            </div>
            
            <div class="mb-6">
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select id="status" name="status" class="form-select w-full">
                    <option value="scheduled" <?php echo $formData['status'] === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                    <option value="completed" <?php echo $formData['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $formData['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    <option value="rescheduled" <?php echo $formData['status'] === 'rescheduled' ? 'selected' : ''; ?>>Rescheduled</option>
                </select>
                </div>
            
            <div class="flex justify-end space-x-4">
                <a href="view.php?id=<?php echo $appointmentId; ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg">
                    Cancel
                </a>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg">
                    Update Appointment
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Client and case selection logic
    const clientSelect = document.getElementById('client_id');
    const caseSelect = document.getElementById('case_id');
    
    // Function to load cases for selected client
    function loadClientCases(clientId) {
        if (!clientId) {
            caseSelect.innerHTML = '<option value="">No Related Case</option>';
            return;
        }
        
        // Show loading indicator
        caseSelect.innerHTML = '<option value="">Loading cases...</option>';
        caseSelect.disabled = true;
        
        fetch(`get_client_cases.php?client_id=${clientId}`)
            .then(response => response.json())
            .then(cases => {
                caseSelect.innerHTML = '<option value="">No Related Case</option>';
                caseSelect.disabled = false;
                
                if (cases.length === 0) {
                    const option = document.createElement('option');
                    option.disabled = true;
                    option.textContent = 'No cases found for this client';
                    caseSelect.appendChild(option);
                } else {
                    cases.forEach(caseItem => {
                        const option = document.createElement('option');
                        option.value = caseItem.case_id;
                        option.textContent = `${caseItem.case_number} - ${caseItem.title}`;
                        caseSelect.appendChild(option);
                    });
                }
                
                <?php if ($formData['case_id']): ?>
                caseSelect.value = '<?php echo $formData['case_id']; ?>';
                <?php endif; ?>
            })
            .catch(error => {
                console.error('Error loading cases:', error);
                caseSelect.innerHTML = '<option value="">Error loading cases</option>';
                caseSelect.disabled = false;
            });
    }
    
    // Load cases when client changes
    clientSelect.addEventListener('change', function() {
        loadClientCases(this.value);
    });
    
    // Load cases for initial client selection
    if (clientSelect.value) {
        loadClientCases(clientSelect.value);
    }
    
    // Validate end time is after start time
    const startTimeInput = document.getElementById('start_time');
    const endTimeInput = document.getElementById('end_time');
    
    endTimeInput.addEventListener('change', function() {
        if (startTimeInput.value && endTimeInput.value) {
            if (startTimeInput.value >= endTimeInput.value) {
                alert('End time must be after start time');
                endTimeInput.value = '';
            }
        }
    });
    
    startTimeInput.addEventListener('change', function() {
        if (startTimeInput.value && endTimeInput.value) {
            if (startTimeInput.value >= endTimeInput.value) {
                alert('End time must be after start time');
                endTimeInput.value = '';
            }
        }
    });
});
</script>

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
