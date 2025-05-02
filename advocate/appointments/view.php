<?php
// Set page title
$pageTitle = "View Appointment";

// Include header
include_once '../includes/header.php';

// Check if appointment ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirectWithMessage('index.php', 'No appointment specified', 'error');
    exit;
}

$appointmentId = (int)$_GET['id'];
$advocateId = $advocateData['advocate_id'];

// Get appointment details
$conn = getDBConnection();
$stmt = $conn->prepare("
    SELECT a.*, 
           c.full_name as client_name, 
           cs.case_id, cs.case_number, cs.title as case_title
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
    redirectWithMessage('index.php', 'Appointment not found or you do not have permission to view it', 'error');
    exit;
}

$appointment = $result->fetch_assoc();

// Format appointment status for display
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'scheduled':
            return 'bg-blue-100 text-blue-800';
        case 'completed':
            return 'bg-green-100 text-green-800';
        case 'cancelled':
            return 'bg-red-100 text-red-800';
        case 'rescheduled':
            return 'bg-yellow-100 text-yellow-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

// Get appointment notes/activities
$notesStmt = $conn->prepare("
    SELECT an.*, u.full_name as created_by_name
    FROM appointment_notes an
    JOIN users u ON an.created_by = u.user_id
    WHERE an.appointment_id = ?
    ORDER BY an.created_at DESC
");

$notesStmt->bind_param("i", $appointmentId);
$notesStmt->execute();
$notesResult = $notesStmt->get_result();

$notes = [];
while ($note = $notesResult->fetch_assoc()) {
    $notes[] = $note;
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $newStatus = sanitizeInput($_POST['status']);
    
    if (in_array($newStatus, ['scheduled', 'completed', 'cancelled', 'rescheduled'])) {
        $updateStmt = $conn->prepare("UPDATE appointments SET status = ? WHERE appointment_id = ? AND advocate_id = ?");
        $updateStmt->bind_param("sii", $newStatus, $appointmentId, $advocateId);
        
        if ($updateStmt->execute()) {
            // Update the appointment object with new status
            $appointment['status'] = $newStatus;
            
            // Add activity log
            $activityDesc = "Appointment status updated to: " . ucfirst($newStatus);
            
            // If this is related to a case, add case activity
            if (!empty($appointment['case_id'])) {
                addCaseActivity($appointment['case_id'], $_SESSION['user_id'], 'update', $activityDesc);
            }
            
            // Show success message
            echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                    <p>Appointment status updated successfully.</p>
                  </div>';
        } else {
            echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p>Failed to update appointment status.</p>
                  </div>';
        }
    }
}

// Handle adding notes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_note') {
    $noteContent = sanitizeInput($_POST['note_content']);
    
    if (!empty($noteContent)) {
        $addNoteStmt = $conn->prepare("
            INSERT INTO appointment_notes (appointment_id, note_content, created_by) 
            VALUES (?, ?, ?)
        ");
        $addNoteStmt->bind_param("isi", $appointmentId, $noteContent, $_SESSION['user_id']);
        
        if ($addNoteStmt->execute()) {
            // Set flash message
            $_SESSION['flash_message'] = "Note added successfully.";
            $_SESSION['flash_type'] = "success";
            
            // Redirect to refresh the page and show the new note
            header("Location: view.php?id=$appointmentId");
            exit;
        } else {
            // Set error flash message
            $_SESSION['flash_message'] = "Failed to add note. Please try again.";
            $_SESSION['flash_type'] = "error";
        }
    } else {
        // Set warning flash message for empty note
        $_SESSION['flash_message'] = "Note cannot be empty.";
        $_SESSION['flash_type'] = "warning";
    }
}


// Close database connections
$stmt->close();
$notesStmt->close();
$conn->close();
?>

<div class="border-t border-gray-200 pt-4 mt-4">
    <h3 class="text-sm font-medium text-gray-500 mb-2">Appointment Actions</h3>
    <div class="flex flex-wrap gap-2">
        <?php if ($appointment['status'] === 'scheduled'): ?>
            <form method="POST" action="" class="inline" onsubmit="return confirm('Are you sure you want to cancel this appointment?');">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="status" value="cancelled">
                <button type="submit" class="px-3 py-1 bg-red-100 text-red-700 rounded-md text-sm hover:bg-red-200">
                    <i class="fas fa-times-circle mr-1"></i> Cancel Appointment
                </button>
            </form>
            
            <form method="POST" action="" class="inline" onsubmit="return confirm('Are you sure you want to mark this appointment as completed?');">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="status" value="completed">
                <button type="submit" class="px-3 py-1 bg-green-100 text-green-700 rounded-md text-sm hover:bg-green-200">
                    <i class="fas fa-check-circle mr-1"></i> Mark as Completed
                </button>
            </form>
            
            <a href="reschedule.php?id=<?php echo $appointmentId; ?>" class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-md text-sm hover:bg-yellow-200">
                <i class="fas fa-calendar-alt mr-1"></i> Reschedule
            </a>
        <?php elseif ($appointment['status'] === 'cancelled'): ?>
            <a href="reschedule.php?id=<?php echo $appointmentId; ?>" class="px-3 py-1 bg-blue-100 text-blue-700 rounded-md text-sm hover:bg-blue-200">
                <i class="fas fa-redo mr-1"></i> Reschedule
            </a>
        <?php endif; ?>
        
        <a href="edit.php?id=<?php echo $appointmentId; ?>" class="px-3 py-1 bg-gray-100 text-gray-700 rounded-md text-sm hover:bg-gray-200">
            <i class="fas fa-edit mr-1"></i> Edit Details
        </a>
        
        <!-- Add Delete Button -->
        <a href="delete.php?id=<?php echo $appointmentId; ?>" class="px-3 py-1 bg-red-100 text-red-700 rounded-md text-sm hover:bg-red-200" onclick="return confirm('Are you sure you want to delete this appointment? This action cannot be undone.');">
            <i class="fas fa-trash-alt mr-1"></i> Delete Appointment
        </a>
    </div>
</div>


<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Appointment Details -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h2 class="text-xl font-semibold text-gray-800"><?php echo htmlspecialchars($appointment['title']); ?></h2>
                    <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo getStatusBadgeClass($appointment['status']); ?>">
                        <?php echo ucfirst($appointment['status']); ?>
                    </span>
                </div>
            </div>
            
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 mb-1">Date & Time</h3>
                        <p class="text-gray-800">
                            <?php echo formatDate($appointment['appointment_date']); ?><br>
                            <?php echo formatTime($appointment['start_time']); ?> - <?php echo formatTime($appointment['end_time']); ?>
                        </p>
                    </div>
                    
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 mb-1">Location</h3>
                        <p class="text-gray-800">
                            <?php echo !empty($appointment['location']) ? htmlspecialchars($appointment['location']) : 'No location specified'; ?>
                        </p>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 mb-1">Client</h3>
                        <p class="text-gray-800">
                            <?php echo htmlspecialchars($appointment['client_name']); ?>
                        </p>
                    </div>
                    
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 mb-1">Related Case</h3>
                        <p class="text-gray-800">
                            <?php if (!empty($appointment['case_id'])): ?>
                                <a href="../cases/view.php?id=<?php echo $appointment['case_id']; ?>" class="text-blue-600 hover:underline">
                                    <?php echo htmlspecialchars($appointment['case_number'] . ' - ' . $appointment['case_title']); ?>
                                </a>
                            <?php else: ?>
                                <span class="text-gray-500">No related case</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                
                <?php if (!empty($appointment['description'])): ?>
                    <div class="mb-6">
                        <h3 class="text-sm font-medium text-gray-500 mb-1">Description</h3>
                        <div class="bg-gray-50 p-4 rounded-lg text-gray-700">
                            <?php echo nl2br(htmlspecialchars($appointment['description'])); ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="border-t border-gray-200 pt-4 mt-4">
                    <h3 class="text-sm font-medium text-gray-500 mb-2">Appointment Actions</h3>
                    <div class="flex flex-wrap gap-2">
                        <?php if ($appointment['status'] === 'scheduled'): ?>
                            <form method="POST" action="" class="inline" onsubmit="return confirm('Are you sure you want to cancel this appointment?');">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="status" value="cancelled">
                                <button type="submit" class="px-3 py-1 bg-red-100 text-red-700 rounded-md text-sm hover:bg-red-200">
                                    <i class="fas fa-times-circle mr-1"></i> Cancel Appointment
                                </button>
                            </form>
                            
                            <form method="POST" action="" class="inline" onsubmit="return confirm('Are you sure you want to mark this appointment as completed?');">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="status" value="completed">
                                <button type="submit" class="px-3 py-1 bg-green-100 text-green-700 rounded-md text-sm hover:bg-green-200">
                                    <i class="fas fa-check-circle mr-1"></i> Mark as Completed
                                </button>
                            </form>
                            
                            <a href="reschedule.php?id=<?php echo $appointmentId; ?>" class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-md text-sm hover:bg-yellow-200">
                                <i class="fas fa-calendar-alt mr-1"></i> Reschedule
                            </a>
                        <?php elseif ($appointment['status'] === 'cancelled'): ?>
                            <a href="reschedule.php?id=<?php echo $appointmentId; ?>" class="px-3 py-1 bg-blue-100 text-blue-700 rounded-md text-sm hover:bg-blue-200">
                                <i class="fas fa-redo mr-1"></i> Reschedule
                            </a>
                        <?php endif; ?>
                        
                        <a href="edit.php?id=<?php echo $appointmentId; ?>" class="px-3 py-1 bg-gray-100 text-gray-700 rounded-md text-sm hover:bg-gray-200">
                            <i class="fas fa-edit mr-1"></i> Edit Details
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Appointment Notes -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mt-6">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">Notes & Updates</h2>
            </div>
            
            <div class="p-6">
                <!-- Add Note Form -->
                <form method="POST" action="" class="mb-6">
                    <input type="hidden" name="action" value="add_note">
                    <div class="mb-4">
                        <label for="note_content" class="block text-sm font-medium text-gray-700 mb-1">Add a Note</label>
                        <textarea id="note_content" name="note_content" rows="3" class="form-textarea w-full" placeholder="Enter notes about this appointment..."></textarea>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            <i class="fas fa-plus mr-2"></i> Add Note
                        </button>
                    </div>
                </form>
                
                <!-- Notes List -->
                <?php if (empty($notes)): ?>
                    <div class="text-center py-6 text-gray-500">
                        <i class="fas fa-sticky-note text-gray-300 text-4xl mb-3"></i>
                        <p>No notes have been added yet.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($notes as $note): ?>
                            <div class="border-l-4 border-blue-500 pl-4 py-3">
                                <div class="text-sm text-gray-500 mb-1">
                                Added by <?php echo htmlspecialchars($note['created_by_name']); ?> on 
                                    <?php echo date('M d, Y \a\t h:i A', strtotime($note['created_at'])); ?>
                                </div>
                                <div class="text-gray-700">
                                    <?php echo nl2br(htmlspecialchars($note['note_content'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Sidebar -->
    <div class="lg:col-span-1">
        <!-- Calendar Widget -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">Appointment Calendar</h2>
            </div>
            
            <div class="p-4">
                <div class="bg-blue-50 p-3 rounded-lg mb-4">
                    <div class="text-center">
                        <div class="text-sm text-gray-500 mb-1">
                            <?php echo date('l', strtotime($appointment['appointment_date'])); ?>
                        </div>
                        <div class="text-3xl font-bold text-blue-700">
                            <?php echo date('d', strtotime($appointment['appointment_date'])); ?>
                        </div>
                        <div class="text-sm text-gray-500">
                            <?php echo date('F Y', strtotime($appointment['appointment_date'])); ?>
                        </div>
                    </div>
                </div>
                
                <div class="text-center">
                    <div class="text-sm text-gray-500 mb-1">Time</div>
                    <div class="text-xl font-semibold text-gray-800">
                        <?php echo formatTime($appointment['start_time']); ?> - <?php echo formatTime($appointment['end_time']); ?>
                    </div>
                    
                    <?php
                    // Calculate duration
                    $start = new DateTime($appointment['appointment_date'] . ' ' . $appointment['start_time']);
                    $end = new DateTime($appointment['appointment_date'] . ' ' . $appointment['end_time']);
                    $duration = $start->diff($end);
                    $hours = $duration->h;
                    $minutes = $duration->i;
                    
                    $durationText = '';
                    if ($hours > 0) {
                        $durationText .= $hours . ' hour' . ($hours > 1 ? 's' : '');
                    }
                    if ($minutes > 0) {
                        if ($hours > 0) $durationText .= ' ';
                        $durationText .= $minutes . ' minute' . ($minutes > 1 ? 's' : '');
                    }
                    ?>
                    
                    <div class="text-sm text-gray-500 mt-1">
                        Duration: <?php echo $durationText; ?>
                    </div>
                </div>
                
                <div class="mt-4 text-center">
                    <a href="ical.php?id=<?php echo $appointmentId; ?>" class="inline-flex items-center px-3 py-2 text-sm text-blue-700 hover:text-blue-800">
                        <i class="fas fa-calendar-plus mr-2"></i> Add to Calendar
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Client Information -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">Client Information</h2>
            </div>
            
            <div class="p-4">
                <div class="flex items-center mb-4">
                    <div class="w-10 h-10 rounded-full bg-blue-500 flex items-center justify-center text-white mr-3">
                        <?php echo strtoupper(substr($appointment['client_name'], 0, 1)); ?>
                    </div>
                    <div>
                        <div class="font-medium text-gray-800"><?php echo htmlspecialchars($appointment['client_name']); ?></div>
                        <div class="text-sm text-gray-500">Client</div>
                    </div>
                </div>
                
                <div class="border-t border-gray-200 pt-4 mt-4">
                    <a href="/advocate/clients/view.php?id=<?php echo $appointment['client_id']; ?>" class="inline-flex items-center px-3 py-2 text-sm text-blue-700 hover:text-blue-800">
                        <i class="fas fa-user mr-2"></i> View Client Profile
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Related Case Information (if applicable) -->
        <?php if (!empty($appointment['case_id'])): ?>
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-800">Related Case</h2>
                </div>
                
                <div class="p-4">
                    <div class="mb-3">
                        <div class="text-sm text-gray-500 mb-1">Case Number</div>
                        <div class="font-medium text-gray-800"><?php echo htmlspecialchars($appointment['case_number']); ?></div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="text-sm text-gray-500 mb-1">Case Title</div>
                        <div class="font-medium text-gray-800"><?php echo htmlspecialchars($appointment['case_title']); ?></div>
                    </div>
                    
                    <div class="border-t border-gray-200 pt-4 mt-4">
                        <a href="../cases/view.php?id=<?php echo $appointment['case_id']; ?>" class="inline-flex items-center px-3 py-2 text-sm text-blue-700 hover:text-blue-800">
                            <i class="fas fa-briefcase mr-2"></i> View Case Details
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
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
