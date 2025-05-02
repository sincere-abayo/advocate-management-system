<?php
ob_start();
// Set page title
$pageTitle = "Schedule Appointment";

// Include header
include_once '../includes/header.php';

// Get advocate ID
$advocateId = $advocateData['advocate_id'];

// Initialize variables
$errors = [];
$formData = [
    'title' => '',
    'description' => '',
    'appointment_date' => '',
    'start_time' => '',
    'end_time' => '',
    'location' => '',
    'client_id' => '',
    'case_id' => ''
];

// Check if case_id is provided in URL
$caseId = isset($_GET['case_id']) ? (int)$_GET['case_id'] : 0;
$caseDetails = null;

// Get database connection
$conn = getDBConnection();

// If case_id is provided, verify it exists and advocate has access to it
if ($caseId > 0) {
    $caseStmt = $conn->prepare("
        SELECT c.*, cp.client_id, u.full_name as client_name
        FROM cases c
        JOIN client_profiles cp ON c.client_id = cp.client_id
        JOIN users u ON cp.user_id = u.user_id
        JOIN case_assignments ca ON c.case_id = ca.case_id
        WHERE c.case_id = ? AND ca.advocate_id = ?
    ");
    $caseStmt->bind_param("ii", $caseId, $advocateId);
    $caseStmt->execute();
    $caseResult = $caseStmt->get_result();
    
    if ($caseResult->num_rows > 0) {
        $caseDetails = $caseResult->fetch_assoc();
        $formData['case_id'] = $caseId;
        $formData['client_id'] = $caseDetails['client_id'];
    } else {
        // Case not found or advocate doesn't have access
        redirectWithMessage('/advocate/appointments/index.php', 'Case not found or you do not have access to it', 'error');
        exit;
    }
}

// Get all clients the advocate has worked with
$clientsStmt = $conn->prepare("
    SELECT DISTINCT cp.client_id, u.full_name
    FROM client_profiles cp
    JOIN users u ON cp.user_id = u.user_id
    LEFT JOIN case_assignments ca ON ca.advocate_id = ?
    LEFT JOIN cases c ON ca.case_id = c.case_id AND c.client_id = cp.client_id
    WHERE ca.advocate_id IS NOT NULL
    ORDER BY u.full_name
");
$clientsStmt->bind_param("i", $advocateId);
$clientsStmt->execute();
$clientsResult = $clientsStmt->get_result();
$clients = [];
while ($client = $clientsResult->fetch_assoc()) {
    $clients[] = $client;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate and sanitize input
    $formData['title'] = sanitizeInput($_POST['title']);
    $formData['description'] = sanitizeInput($_POST['description']);
    $formData['appointment_date'] = sanitizeInput($_POST['appointment_date']);
    $formData['start_time'] = sanitizeInput($_POST['start_time']);
    $formData['end_time'] = sanitizeInput($_POST['end_time']);
    $formData['location'] = sanitizeInput($_POST['location']);
    $formData['client_id'] = (int)$_POST['client_id'];
    $formData['case_id'] = !empty($_POST['case_id']) ? (int)$_POST['case_id'] : null;
    
    // Validate required fields
    if (empty($formData['title'])) {
        $errors['title'] = 'Appointment title is required';
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
    
    if (empty($formData['client_id'])) {
        $errors['client_id'] = 'Client is required';
    }
    
    // Check for scheduling conflicts
    if (empty($errors['appointment_date']) && empty($errors['start_time']) && empty($errors['end_time'])) {
        $conflictStmt = $conn->prepare("
            SELECT * FROM appointments 
            WHERE advocate_id = ? 
            AND appointment_date = ? 
            AND status != 'cancelled'
            AND (
                (start_time <= ? AND end_time > ?) OR
                (start_time < ? AND end_time >= ?) OR
                (start_time >= ? AND end_time <= ?)
            )
        ");
        $conflictStmt->bind_param(
            "isssssss", 
            $advocateId, 
            $formData['appointment_date'], 
            $formData['end_time'], 
            $formData['start_time'], 
            $formData['end_time'], 
            $formData['start_time'], 
            $formData['start_time'], 
            $formData['end_time']
        );
        $conflictStmt->execute();
        $conflictResult = $conflictStmt->get_result();
        
        if ($conflictResult->num_rows > 0) {
            $errors['scheduling'] = 'This appointment conflicts with another appointment you have scheduled';
        }
    }
    
    // If no errors, create the appointment
    if (empty($errors)) {
       // Handle case_id (can be NULL if no case is selected)
$caseId = !empty($formData['case_id']) ? $formData['case_id'] : null;

if ($caseId === null) {
    // SQL with NULL for case_id
    $stmt = $conn->prepare("
        INSERT INTO appointments(
            advocate_id, client_id, case_id, title, description, 
            appointment_date, start_time, end_time, location, status
        ) VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, 'scheduled')
    ");
    
    $stmt->bind_param("iissssss",
        $advocateId,
        $formData['client_id'],
        $formData['title'],
        $formData['description'],
        $formData['appointment_date'],
        $formData['start_time'],
        $formData['end_time'],
        $formData['location']
    );
} else {
    // SQL with case_id parameter
    $stmt = $conn->prepare("
        INSERT INTO appointments(
            advocate_id, client_id, case_id, title, description, 
            appointment_date, start_time, end_time, location, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled')
    ");
    
    $stmt->bind_param("iiissssss",
        $advocateId,
        $formData['client_id'],
        $caseId,
        $formData['title'],
        $formData['description'],
        $formData['appointment_date'],
        $formData['start_time'],
        $formData['end_time'],
        $formData['location']
    );
}

        
        if ($stmt->execute()) {
            $appointmentId = $conn->insert_id;
            
            // Add case activity if appointment is related to a case
            if (!empty($formData['case_id'])) {
                $activityDesc = "Appointment scheduled: " . $formData['title'] . " on " . formatDate($formData['appointment_date']) . " at " . formatTime($formData['start_time']);
                $activityStmt = $conn->prepare("
                    INSERT INTO case_activities (case_id, user_id, activity_type, description)
                    VALUES (?, ?, 'update', ?)
                ");
                $activityStmt->bind_param("iis", $formData['case_id'], $_SESSION['user_id'], $activityDesc);
                $activityStmt->execute();
            }
            
            // Create notification for client
            $clientUserStmt = $conn->prepare("
                SELECT u.user_id, u.full_name 
                FROM users u 
                JOIN client_profiles cp ON u.user_id = cp.user_id 
                WHERE cp.client_id = ?
            ");
            $clientUserStmt->bind_param("i", $formData['client_id']);
            $clientUserStmt->execute();
            $clientUserResult = $clientUserStmt->get_result();
            $clientUser = $clientUserResult->fetch_assoc();
            
            if ($clientUser) {
                $notificationTitle = "New Appointment Scheduled";
                $notificationMessage = "An appointment has been scheduled with you on " . formatDate($formData['appointment_date']) . " at " . formatTime($formData['start_time']) . ": " . $formData['title'];
                
                createNotification($clientUser['user_id'], $notificationTitle, $notificationMessage, 'appointment', $appointmentId);
            }
            
            redirectWithMessage('/advocate/appointments/view.php?id=' . $appointmentId, 'Appointment scheduled successfully', 'success');
            exit;
        } else {
            $errors['general'] = 'An error occurred while scheduling the appointment: ' . $conn->error;
        }
    }
}

// Get cases for the selected client (for AJAX)
if (isset($_GET['get_client_cases']) && !empty($_GET['client_id'])) {
    // Prevent any output before our JSON
    ob_clean();
    
    // Get database connection
    $conn = getDBConnection();
    
    // Make sure we have the advocate ID
    $advocateId = isset($advocateData['advocate_id']) ? $advocateData['advocate_id'] : $_SESSION['advocate_id'];
    
    $clientId = (int)$_GET['client_id'];
    
    try {
        $casesStmt = $conn->prepare("
            SELECT c.case_id, c.title, c.case_number
            FROM cases c
            JOIN case_assignments ca ON c.case_id = ca.case_id
            WHERE c.client_id = ? AND ca.advocate_id = ?
            ORDER BY c.created_at DESC
        ");
        
        if (!$casesStmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $casesStmt->bind_param("ii", $clientId, $advocateId);
        
        if (!$casesStmt->execute()) {
            throw new Exception("Execute failed: " . $casesStmt->error);
        }
        
        $casesResult = $casesStmt->get_result();
        
        $cases = [];
        while ($case = $casesResult->fetch_assoc()) {
            $cases[] = $case;
        }
        
        // Set proper JSON header
        header('Content-Type: application/json');
        echo json_encode($cases);
        exit;
    } catch (Exception $e) {
        // Return error as JSON
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}


?>

<div class="mb-6">
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-semibold text-gray-800">Schedule Appointment</h1>
        <a href="<?php echo $path_url; ?>advocate/appointments/index.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg inline-flex items-center">
            <i class="fas fa-arrow-left mr-2"></i> Back to Appointments
        </a>
    </div>
</div>

<div class="bg-white rounded-lg shadow-md p-6">
    <?php if (!empty($errors['general'])): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p class="font-medium">Error</p>
            <p><?php echo $errors['general']; ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($errors['scheduling'])): ?>
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6" role="alert">
            <p class="font-medium">Scheduling Conflict</p>
            <p><?php echo $errors['scheduling']; ?></p>
        </div>
    <?php endif; ?>
    
    <form method="POST" action="" class="space-y-6">
        <!-- Form Section: Basic Information -->
        <div class="border-b border-gray-200 pb-4 mb-4">
            <h2 class="text-lg font-medium text-gray-800 mb-4">Basic Information</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="title" class="form-label">
                        Appointment Title <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-calendar-check text-gray-400"></i>
                        </div>
                        <input type="text" id="title" name="title" 
                               class="form-input pl-10 w-full <?php echo isset($errors['title']) ? 'border-red-500 focus:ring-red-500 focus:border-red-500' : 'focus:ring-blue-500 focus:border-blue-500'; ?>" 
                               value="<?php echo htmlspecialchars($formData['title']); ?>" 
                               placeholder="Enter appointment title"
                               required>
                    </div>
                    <?php if (isset($errors['title'])): ?>
                        <p class="text-red-500 text-sm mt-1"><i class="fas fa-exclamation-circle mr-1"></i><?php echo $errors['title']; ?></p>
                    <?php endif; ?>
                </div>
                
                <div>
                    <label for="client_id" class="form-label">
                        Client <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-user text-gray-400"></i>
                        </div>
                        <select id="client_id" name="client_id" 
                                class="form-select pl-10 w-full <?php echo isset($errors['client_id']) ? 'border-red-500 focus:ring-red-500 focus:border-red-500' : 'focus:ring-blue-500 focus:border-blue-500'; ?>" 
                                required <?php echo $caseId ? 'disabled' : ''; ?>>
                            <option value="">Select Client</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?php echo $client['client_id']; ?>" <?php echo $formData['client_id'] == $client['client_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($client['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <i class="fas fa-chevron-down text-gray-400"></i>
                        </div>
                    </div>
                    <?php if ($caseId): ?>
                        <input type="hidden" name="client_id" value="<?php echo $formData['client_id']; ?>">
                    <?php endif; ?>
                    <?php if (isset($errors['client_id'])): ?>
                        <p class="text-red-500 text-sm mt-1"><i class="fas fa-exclamation-circle mr-1"></i><?php echo $errors['client_id']; ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Form Section: Schedule Details -->
        <div class="border-b border-gray-200 pb-4 mb-4">
            <h2 class="text-lg font-medium text-gray-800 mb-4">Schedule Details</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div>
                    <label for="appointment_date" class="form-label">
                        Date <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-calendar-alt text-gray-400"></i>
                        </div>
                        <input type="date" id="appointment_date" name="appointment_date" 
                               class="form-input pl-10 w-full <?php echo isset($errors['appointment_date']) ? 'border-red-500 focus:ring-red-500 focus:border-red-500' : 'focus:ring-blue-500 focus:border-blue-500'; ?>" 
                               value="<?php echo $formData['appointment_date']; ?>" 
                               min="<?php echo date('Y-m-d'); ?>" 
                               required>
                    </div>
                    <?php if (isset($errors['appointment_date'])): ?>
                        <p class="text-red-500 text-sm mt-1"><i class="fas fa-exclamation-circle mr-1"></i><?php echo $errors['appointment_date']; ?></p>
                    <?php endif; ?>
                </div>
                
                <div>
                    <label for="start_time" class="form-label">
                        Start Time <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-clock text-gray-400"></i>
                        </div>
                        <input type="time" id="start_time" name="start_time" 
                               class="form-input pl-10 w-full <?php echo isset($errors['start_time']) ? 'border-red-500 focus:ring-red-500 focus:border-red-500' : 'focus:ring-blue-500 focus:border-blue-500'; ?>" 
                               value="<?php echo $formData['start_time']; ?>" 
                               required>
                    </div>
                    <?php if (isset($errors['start_time'])): ?>
                        <p class="text-red-500 text-sm mt-1"><i class="fas fa-exclamation-circle mr-1"></i><?php echo $errors['start_time']; ?></p>
                    <?php endif; ?>
                </div>
                
                <div>
                    <label for="end_time" class="form-label">
                        End Time <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-clock text-gray-400"></i>
                        </div>
                        <input type="time" id="end_time" name="end_time" 
                               class="form-input pl-10 w-full <?php echo isset($errors['end_time']) ? 'border-red-500 focus:ring-red-500 focus:border-red-500' : 'focus:ring-blue-500 focus:border-blue-500'; ?>" 
                               value="<?php echo $formData['end_time']; ?>" 
                               required>
                    </div>
                    <?php if (isset($errors['end_time'])): ?>
                        <p class="text-red-500 text-sm mt-1"><i class="fas fa-exclamation-circle mr-1"></i><?php echo $errors['end_time']; ?></p>
                    <?php endif; ?>
                    <p class="text-gray-500 text-xs mt-1">Duration: <span id="appointment-duration">--</span></p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="location" class="form-label">Location</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-map-marker-alt text-gray-400"></i>
                        </div>
                        <input type="text" id="location" name="location" 
                               class="form-input pl-10 w-full focus:ring-blue-500 focus:border-blue-500" 
                               value="<?php echo htmlspecialchars($formData['location']); ?>" 
                               placeholder="Office, Court, Video Call, etc.">
                    </div>
                </div>
                
                <div>
                    <label for="case_id" class="form-label">Related Case</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-briefcase text-gray-400"></i>
                        </div>
                        <select id="case_id" name="case_id" 
                                class="form-select pl-10 w-full focus:ring-blue-500 focus:border-blue-500" 
                                <?php echo $caseId ? 'disabled' : ''; ?>>
                            <option value="">No Related Case</option>
                            <?php if ($caseId && $caseDetails): ?>
                                <option value="<?php echo $caseDetails['case_id']; ?>" selected>
                                    <?php echo htmlspecialchars($caseDetails['case_number'] . ' - ' . $caseDetails['title']); ?>
                                </option>
                            <?php endif; ?>
                        </select>
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <i class="fas fa-chevron-down text-gray-400"></i>
                        </div>
                    </div>
                    <?php if ($caseId): ?>
                        <input type="hidden" name="case_id" value="<?php echo $caseId; ?>">
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Form Section: Additional Details -->
        <div>
            <h2 class="text-lg font-medium text-gray-800 mb-4">Additional Details</h2>
            
            <div class="mb-6">
                <label for="description" class="form-label">Description</label>
                <div class="relative">
                    <textarea id="description" name="description" 
                              rows="4" 
                              class="form-textarea w-full focus:ring-blue-500 focus:border-blue-500" 
                              placeholder="Enter appointment details, agenda, or notes"><?php echo htmlspecialchars($formData['description']); ?></textarea>
                </div>
                <p class="text-gray-500 text-xs mt-1">Include any relevant details about the appointment</p>
            </div>
        </div>
        
        <!-- Form Actions -->
        <div class="flex justify-end space-x-4 pt-4 border-t border-gray-200">
            <a href="<?php echo $caseId ? '/advocate/cases/view.php?id=' . $caseId : '/advocate/appointments/index.php'; ?>" 
               class="btn-secondary">
                <i class="fas fa-times mr-2"></i> Cancel
            </a>
            <button type="submit" class="btn-primary">
                <i class="fas fa-calendar-plus mr-2"></i> Schedule Appointment
            </button>
        </div>
    </form>
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
    
    // Use the current URL path but with our query parameters
    const url = `${window.location.pathname}?get_client_cases=${null}&client_id=${clientId}`;
    
    // Use a relative URL path that works with your base directory structure
    fetch(url)
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
    if (data.error) {
        throw new Error(data.error);
    }
    
    // Clear the dropdown
    caseSelect.innerHTML = '<option value="">No Related Case</option>';
    caseSelect.disabled = false;
    
    
    if (!data || data.length === 0) {
        const option = document.createElement('option');
        option.disabled = true;
        option.textContent = 'No cases found for this client';
        caseSelect.appendChild(option);
        console.log('No cases found');
    } else {
        // Log each case as we process it
        data.forEach((caseItem, index) => {
            console.log(`Processing case ${index}:`, caseItem);
            
            if (!caseItem || !caseItem.case_id) {
                console.error(`Invalid case item at index ${index}:`, caseItem);
                return;
            }
            
            const option = document.createElement('option');
            option.value = caseItem.case_id;
            option.textContent = `${caseItem.case_number} - ${caseItem.title}`;
            caseSelect.appendChild(option);
            
            console.log(`Added option: ${option.value} - ${option.textContent}`);
        });
        
        // Log the final HTML of the select element
        console.log('Final select HTML:', caseSelect.innerHTML);
    }
    
    // If we have a case_id in the form data, try to select it
    if (<?php echo !empty($formData['case_id']) ? 'true' : 'false'; ?>) {
        const caseIdToSelect = '<?php echo $formData['case_id']; ?>';
        console.log('Trying to select case ID:', caseIdToSelect);
        
        // Check if the option exists
        const optionExists = Array.from(caseSelect.options).some(option => option.value === caseIdToSelect);
        console.log('Option exists:', optionExists);
        
        if (optionExists) {
            caseSelect.value = caseIdToSelect;
            console.log('Selected case ID:', caseSelect.value);
        }
    }
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

    
    // Set default times if empty
    const startTimeInput = document.getElementById('start_time');
    const endTimeInput = document.getElementById('end_time');
    
    if (!startTimeInput.value) {
        // Default to next hour, rounded to nearest 30 minutes
        const now = new Date();
        now.setHours(now.getHours() + 1);
        now.setMinutes(Math.ceil(now.getMinutes() / 30) * 30);
        now.setSeconds(0);
        
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        startTimeInput.value = `${hours}:${minutes}`;
        
        // Default end time to 1 hour after start time
        const end = new Date(now);
        end.setHours(end.getHours() + 1);
        
        const endHours = String(end.getHours()).padStart(2, '0');
        const endMinutes = String(end.getMinutes()).padStart(2, '0');
        endTimeInput.value = `${endHours}:${endMinutes}`;
    }
    
    // Set default date if empty
    const dateInput = document.getElementById('appointment_date');
    if (!dateInput.value) {
        const today = new Date();
        const year = today.getFullYear();
        const month = String(today.getMonth() + 1).padStart(2, '0');
        const day = String(today.getDate()).padStart(2, '0');
        dateInput.value = `${year}-${month}-${day}`;
    }
    
    // Calculate and display appointment duration
    function updateDuration() {
        if (startTimeInput.value && endTimeInput.value) {
            const start = new Date(`2000-01-01T${startTimeInput.value}:00`);
            const end = new Date(`2000-01-01T${endTimeInput.value}:00`);
            
            // Handle case where end time is on the next day
            let diff = end - start;
            if (diff < 0) {
                diff += 24 * 60 * 60 * 1000; // Add 24 hours
            }
            
            const hours = Math.floor(diff / (60 * 60 * 1000));
            const minutes = Math.floor((diff % (60 * 60 * 1000)) / (60 * 1000));
            
            let durationText = '';
            if (hours > 0) {
                durationText += `${hours} hour${hours > 1 ? 's' : ''}`;
            }
            if (minutes > 0) {
                durationText += `${hours > 0 ? ' ' : ''}${minutes} minute${minutes > 1 ? 's' : ''}`;
            }
            
            document.getElementById('appointment-duration').textContent = durationText || 'Invalid time range';
        }
    }
    
    startTimeInput.addEventListener('change', updateDuration);
    endTimeInput.addEventListener('change', updateDuration);
    
    // Initialize duration display
    updateDuration();
    
    // Form validation enhancement
    const form = document.querySelector('form');
    form.addEventListener('submit', function(event) {
        let hasErrors = false;
        
        // Validate end time is after start time
        if (startTimeInput.value && endTimeInput.value) {
            const start = new Date(`2000-01-01T${startTimeInput.value}:00`);
            const end = new Date(`2000-01-01T${endTimeInput.value}:00`);
            
            if (end <= start && end.getHours() !== 0) { // Allow midnight as valid end time
                event.preventDefault();
                hasErrors = true;
                
                // Show error message
                const errorElement = document.createElement('p');
                errorElement.className = 'text-red-500 text-sm mt-1';
                errorElement.innerHTML = '<i class="fas fa-exclamation-circle mr-1"></i>End time must be after start time';
                
                // Remove any existing error message
                const existingError = endTimeInput.parentNode.parentNode.querySelector('.text-red-500');
                if (existingError && existingError.textContent.includes('End time must be after start time')) {
                    existingError.remove();
                }
                
                endTimeInput.parentNode.parentNode.appendChild(errorElement);
                endTimeInput.classList.add('border-red-500', 'focus:ring-red-500', 'focus:border-red-500');
                endTimeInput.classList.remove('focus:ring-blue-500', 'focus:border-blue-500');
            }
        }
        
        return !hasErrors;
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
ob_end_flush();
?>
