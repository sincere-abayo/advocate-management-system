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

// Initialize variables
$errors = [];
$formData = [
    'advocate_id' => isset($_GET['advocate_id']) ? (int)$_GET['advocate_id'] : '',
    'case_id' => isset($_GET['case_id']) ? (int)$_GET['case_id'] : '',
    'title' => '',
    'description' => '',
    'appointment_date' => '',
    'start_time' => '',
    'end_time' => '',
    'location' => '',
];

// Connect to database
$conn = getDBConnection();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate advocate
    if (empty($_POST['advocate_id'])) {
        $errors['advocate_id'] = 'Please select an advocate';
    } else {
        $formData['advocate_id'] = (int)$_POST['advocate_id'];
    }
    
    // Case is optional
    $formData['case_id'] = !empty($_POST['case_id']) ? (int)$_POST['case_id'] : null;
    
    // Validate title
    if (empty($_POST['title'])) {
        $errors['title'] = 'Please enter a title for the appointment';
    } else {
        $formData['title'] = $_POST['title'];
    }
    
    // Description is optional
    $formData['description'] = $_POST['description'] ?? '';
    
    // Validate date
    if (empty($_POST['appointment_date'])) {
        $errors['appointment_date'] = 'Please select a date';
    } else {
        $formData['appointment_date'] = $_POST['appointment_date'];
        
        // Check if date is in the future
        $appointmentDate = strtotime($formData['appointment_date']);
        $today = strtotime(date('Y-m-d'));
        
        if ($appointmentDate < $today) {
            $errors['appointment_date'] = 'Appointment date must be in the future';
        }
    }
    
    // Validate start time
    if (empty($_POST['start_time'])) {
        $errors['start_time'] = 'Please select a start time';
    } else {
        $formData['start_time'] = $_POST['start_time'];
    }
    
    // Validate end time
    if (empty($_POST['end_time'])) {
        $errors['end_time'] = 'Please select an end time';
    } else {
        $formData['end_time'] = $_POST['end_time'];
        
        // Check if end time is after start time
        if (!empty($formData['start_time']) && $formData['end_time'] <= $formData['start_time']) {
            $errors['end_time'] = 'End time must be after start time';
        }
    }
    
    // Validate location
    if (empty($_POST['location'])) {
        $errors['location'] = 'Please enter a location';
    } else {
        $formData['location'] = $_POST['location'];
    }
    
    // If no errors, check for scheduling conflicts
    if (empty($errors)) {
        // Check if advocate is available at the requested time
        $conflictQuery = "
            SELECT appointment_id 
            FROM appointments 
            WHERE advocate_id = ? 
            AND appointment_date = ? 
            AND status = 'scheduled'
            AND (
                (start_time <= ? AND end_time > ?) OR
                (start_time < ? AND end_time >= ?) OR
                (start_time >= ? AND end_time <= ?)
            )
        ";
        
        $conflictStmt = $conn->prepare($conflictQuery);
        $conflictStmt->bind_param(
            "isssssss",
            $formData['advocate_id'],
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
            $errors['scheduling'] = 'The advocate is not available at the selected time. Please choose a different time.';
        }
    }
    
    // If no errors, create the appointment request
    if (empty($errors)) {
        try {
            // Start transaction
            $conn->begin_transaction();
            
         // Insert appointment with 'scheduled' status instead of 'pending'
$insertQuery = "
INSERT INTO appointments (
    advocate_id, client_id, case_id, title, description, 
    appointment_date, start_time, end_time, location, status
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled')
";

            
            $insertStmt = $conn->prepare($insertQuery);
            $insertStmt->bind_param(
                "iiissssss",
                $formData['advocate_id'],
                $clientId,
                $formData['case_id'],
                $formData['title'],
                $formData['description'],
                $formData['appointment_date'],
                $formData['start_time'],
                $formData['end_time'],
                $formData['location']
            );
            
            $insertStmt->execute();
            $appointmentId = $conn->insert_id;
            
            // Create notification for advocate
            $advocateUserStmt = $conn->prepare("
                SELECT u.user_id, u.full_name 
                FROM users u 
                JOIN advocate_profiles ap ON u.user_id = ap.user_id 
                WHERE ap.advocate_id = ?
            ");
            $advocateUserStmt->bind_param("i", $formData['advocate_id']);
            $advocateUserStmt->execute();
            $advocateUser = $advocateUserStmt->get_result()->fetch_assoc();
            
            if ($advocateUser) {
                $clientName = $_SESSION['full_name'];
                $notificationTitle = "New Appointment Request";
                $notificationMessage = "Client $clientName has requested an appointment on " . 
                    date('F j, Y', strtotime($formData['appointment_date'])) . " at " . 
                    date('g:i A', strtotime($formData['start_time'])) . ".";
                
                $notificationStmt = $conn->prepare("
                    INSERT INTO notifications (
                        user_id, title, message, related_to, related_id
                    ) VALUES (?, ?, ?, 'appointment', ?)
                ");
                
                $notificationStmt->bind_param(
                    "issi",
                    $advocateUser['user_id'],
                    $notificationTitle,
                    $notificationMessage,
                    $appointmentId
                );
                
                $notificationStmt->execute();
            }
            
            // Add case activity if case_id is provided
            if ($formData['case_id']) {
                $activityDesc = "Appointment requested: " . $formData['title'] . " on " . 
                    date('F j, Y', strtotime($formData['appointment_date'])) . " at " . 
                    date('g:i A', strtotime($formData['start_time']));
                
                addCaseActivity($formData['case_id'], $_SESSION['user_id'], 'update', $activityDesc);
            }
            
            // Commit transaction
            $conn->commit();
            
            // Set success message and redirect
            $_SESSION['flash_message'] = "Appointment request submitted successfully. You will be notified when the advocate confirms the appointment.";
            $_SESSION['flash_type'] = "success";
            header("Location: index.php");
            exit;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $errors['general'] = "Error creating appointment request: " . $e->getMessage();
        }
    }
}

// Get all advocates for dropdown
$advocatesQuery = "
    SELECT ap.advocate_id, u.full_name, ap.specialization, ap.experience_years
    FROM advocate_profiles ap
    JOIN users u ON ap.user_id = u.user_id
    WHERE u.status = 'active'
    ORDER BY u.full_name
";
$advocatesResult = $conn->query($advocatesQuery);

$advocates = [];
while ($advocate = $advocatesResult->fetch_assoc()) {
    $advocates[] = $advocate;
}

// Get client's cases for dropdown
$casesQuery = "
    SELECT case_id, case_number, title
    FROM cases
    WHERE client_id = ? AND status != 'closed'
    ORDER BY filing_date DESC
";
$casesStmt = $conn->prepare($casesQuery);
$casesStmt->bind_param("i", $clientId);
$casesStmt->execute();
$casesResult = $casesStmt->get_result();

$cases = [];
while ($case = $casesResult->fetch_assoc()) {
    $cases[] = $case;
}

// Close connection
$conn->close();

// Set page title
$pageTitle = "Request Appointment";
include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Request Appointment</h1>
            <p class="text-gray-600">Schedule a meeting with an advocate</p>
        </div>
        
        <div class="mt-4 md:mt-0">
            <a href="index.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back to Appointments
            </a>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow-md p-6">
        <?php if (isset($errors['general'])): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p><?php echo $errors['general']; ?></p>
            </div>
        <?php endif; ?>
        
        <?php if (isset($errors['scheduling'])): ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6" role="alert">
                <p><?php echo $errors['scheduling']; ?></p>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label for="advocate_id" class="form-label">
                        Select Advocate <span class="text-red-500">*</span>
                    </label>
                    <select id="advocate_id" name="advocate_id" class="form-select w-full <?php echo isset($errors['advocate_id']) ? 'border-red-500 focus:ring-red-500 focus:border-red-500' : 'focus:ring-blue-500 focus:border-blue-500'; ?>" required>
                        <option value="">Select an advocate</option>
                        <?php foreach ($advocates as $advocate): ?>
                            <option value="<?php echo $advocate['advocate_id']; ?>" <?php echo $formData['advocate_id'] == $advocate['advocate_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($advocate['full_name']); ?> 
                                <?php if (!empty($advocate['specialization'])): ?>
                                    (<?php echo htmlspecialchars($advocate['specialization']); ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['advocate_id'])): ?>
                        <p class="text-red-500 text-sm mt-1"><i class="fas fa-exclamation-circle mr-1"></i><?php echo $errors['advocate_id']; ?></p>
                    <?php endif; ?>
                </div>
                
                <div>
                    <label for="case_id" class="form-label">Related Case</label>
                    <select id="case_id" name="case_id" class="form-select w-full focus:ring-blue-500 focus:border-blue-500">
                        <option value="">No related case</option>
                        <?php foreach ($cases as $case): ?>
                            <option value="<?php echo $case['case_id']; ?>" <?php echo $formData['case_id'] == $case['case_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($case['case_number'] . ' - ' . $case['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-gray-500 text-xs mt-1">Optional: Select a case related to this appointment</p>
                </div>
            </div>
            
            <div class="mb-6">
                <label for="title" class="form-label">
                    Appointment Title <span class="text-red-500">*</span>
                </label>
                <input type="text" id="title" name="title" 
                       class="form-input w-full <?php echo isset($errors['title']) ? 'border-red-500 focus:ring-red-500 focus:border-red-500' : 'focus:ring-blue-500 focus:border-blue-500'; ?>" 
                       value="<?php echo htmlspecialchars($formData['title']); ?>" 
                       placeholder="e.g., Initial Consultation, Case Discussion" 
                       required>
                <?php if (isset($errors['title'])): ?>
                    <p class="text-red-500 text-sm mt-1"><i class="fas fa-exclamation-circle mr-1"></i><?php echo $errors['title']; ?></p>
                <?php endif; ?>
            </div>
            
            <div class="mb-6">
                <label for="description" class="form-label">Description</label>
                <textarea id="description" name="description" rows="3" 
                          class="form-textarea w-full focus:ring-blue-500 focus:border-blue-500" 
                          placeholder="Provide details about the purpose of this appointment"><?php echo htmlspecialchars($formData['description']); ?></textarea>
                <p class="text-gray-500 text-xs mt-1">Optional: Provide additional details about the appointment</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div>
                    <label for="appointment_date" class="form-label">
                        Date <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-calendar text-gray-400"></i>
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
            
            <div class="mb-6">
                <label for="location" class="form-label">
                    Location <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-map-marker-alt text-gray-400"></i>
                    </div>
                    <input type="text" id="location" name="location" 
                           class="form-input pl-10 w-full <?php echo isset($errors['location']) ? 'border-red-500 focus:ring-red-500 focus:border-red-500' : 'focus:ring-blue-500 focus:border-blue-500'; ?>" 
                           value="<?php echo htmlspecialchars($formData['location']); ?>" 
                           placeholder="e.g., Office, Video Call, Phone Call" 
                           required>
                </div>
                <?php if (isset($errors['location'])): ?>
                    <p class="text-red-500 text-sm mt-1"><i class="fas fa-exclamation-circle mr-1"></i><?php echo $errors['location']; ?></p>
                <?php endif; ?>
            </div>
            
            <div class="flex justify-end space-x-4 mt-8">
                <a href="index.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg">
                    Cancel
                </a>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg">
                    Submit Request
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Calculate appointment duration
    const startTimeInput = document.getElementById('start_time');
    const endTimeInput = document.getElementById('end_time');
    const durationSpan = document.getElementById('appointment-duration');
    
    function calculateDuration() {
        if (startTimeInput.value && endTimeInput.value) {
            const start = new Date(`2000-01-01T${startTimeInput.value}`);
            const end = new Date(`2000-01-01T${endTimeInput.value}`);
            
            if (end <= start) {
                durationSpan.textContent = 'End time must be after start time';
                durationSpan.classList.add('text-red-500');
                return;
            }
            
            durationSpan.classList.remove('text-red-500');
            
            // Calculate duration in minutes
            let diff = (end - start) / 60000;
            
            // Format duration
            if (diff < 60) {
                durationSpan.textContent = `${diff} minutes`;
            } else {
                const hours = Math.floor(diff / 60);
                const minutes = diff % 60;
                durationSpan.textContent = `${hours} hour${hours !== 1 ? 's' : ''}${minutes > 0 ? ` ${minutes} minute${minutes !== 1 ? 's' : ''}` : ''}`;
            }
        } else {
            durationSpan.textContent = '--';
        }
    }
    
    startTimeInput.addEventListener('change', calculateDuration);
    endTimeInput.addEventListener('change', calculateDuration);
    
    // Set default times if empty
    if (!startTimeInput.value) {
        // Default to next hour, rounded to nearest 30 minutes
        const now = new Date();
        now.setHours(now.getHours() + 1);
        now.setMinutes(Math.ceil(now.getMinutes() / 30) * 30);
        now.setSeconds(0);
        
        startTimeInput.value = now.toTimeString().substring(0, 5);
    }
    
    if (!endTimeInput.value && startTimeInput.value) {
        // Default to 1 hour after start time
        const start = new Date(`2000-01-01T${startTimeInput.value}`);
        start.setHours(start.getHours() + 1);
        
        endTimeInput.value = start.toTimeString().substring(0, 5);
    }
    
    // Calculate initial duration
    calculateDuration();
    
    // Set minimum date to today
    const dateInput = document.getElementById('appointment_date');
    const today = new Date().toISOString().split('T')[0];
    dateInput.setAttribute('min', today);
    
    // If date is empty, set to today
    if (!dateInput.value) {
        dateInput.value = today;
    }
});
</script>

<?php
// Include footer
include '../includes/footer.php';
?>
