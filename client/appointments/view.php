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

// Check if appointment ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['flash_message'] = "Invalid appointment ID";
    $_SESSION['flash_type'] = "error";
    header("Location: index.php");
    exit;
}

$appointmentId = (int)$_GET['id'];

// Connect to database
$conn = getDBConnection();

// Get appointment details
$query = "
    SELECT 
        a.*,
        u.full_name as advocate_name,
        u.email as advocate_email,
        u.phone as advocate_phone,
        c.case_number,
        c.title as case_title
    FROM appointments a
    JOIN advocate_profiles ap ON a.advocate_id = ap.advocate_id
    JOIN users u ON ap.user_id = u.user_id
    LEFT JOIN cases c ON a.case_id = c.case_id
    WHERE a.appointment_id = ? AND a.client_id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $appointmentId, $clientId);
$stmt->execute();
$result = $stmt->get_result();

// Check if appointment exists and belongs to the client
if ($result->num_rows === 0) {
    $_SESSION['flash_message'] = "Appointment not found or you don't have permission to view it";
    $_SESSION['flash_type'] = "error";
    header("Location: index.php");
    exit;
}

$appointment = $result->fetch_assoc();

// Close connection
$conn->close();

// Set page title
$pageTitle = "Appointment Details";
include '../includes/header.php';

// Format appointment date and time
$formattedDate = date('l, F j, Y', strtotime($appointment['appointment_date']));
$formattedStartTime = date('g:i A', strtotime($appointment['start_time']));
$formattedEndTime = date('g:i A', strtotime($appointment['end_time']));

// Calculate appointment duration
$startTime = new DateTime($appointment['start_time']);
$endTime = new DateTime($appointment['end_time']);
$duration = $startTime->diff($endTime);
$durationText = '';

if ($duration->h > 0) {
    $durationText .= $duration->h . ' hour' . ($duration->h > 1 ? 's' : '');
}

if ($duration->i > 0) {
    if ($durationText) {
        $durationText .= ' and ';
    }
    $durationText .= $duration->i . ' minute' . ($duration->i > 1 ? 's' : '');
}

// Determine appointment status class and text
$statusClass = 'bg-gray-100 text-gray-800';
$statusText = ucfirst($appointment['status']);
$statusIcon = 'fa-calendar';

$appointmentDate = strtotime($appointment['appointment_date']);
$today = strtotime(date('Y-m-d'));

switch ($appointment['status']) {
    case 'scheduled':
        if ($appointmentDate > $today) {
            $statusClass = 'bg-blue-100 text-blue-800';
            $statusIcon = 'fa-calendar-check';
        } else if ($appointmentDate == $today) {
            $statusClass = 'bg-green-100 text-green-800';
            $statusIcon = 'fa-calendar-day';
            $statusText = 'Today';
        } else {
            $statusClass = 'bg-yellow-100 text-yellow-800';
            $statusIcon = 'fa-calendar-times';
            $statusText = 'Past Due';
        }
        break;
    case 'completed':
        $statusClass = 'bg-green-100 text-green-800';
        $statusIcon = 'fa-check-circle';
        break;
    case 'cancelled':
        $statusClass = 'bg-red-100 text-red-800';
        $statusIcon = 'fa-times-circle';
        break;
    case 'rescheduled':
        $statusClass = 'bg-purple-100 text-purple-800';
        $statusIcon = 'fa-calendar-plus';
        break;
}

// Check if appointment can be cancelled
$canCancel = ($appointment['status'] === 'scheduled' && $appointmentDate > $today);
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Appointment Details</h1>
            <p class="text-gray-600">View information about your appointment</p>
        </div>
        
        <div class="mt-4 md:mt-0 flex space-x-2">
            <a href="index.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back to Appointments
            </a>
            
            <?php if ($canCancel): ?>
                <a href="cancel.php?id=<?php echo $appointmentId; ?>" class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center" onclick="return confirm('Are you sure you want to cancel this appointment?');">
                    <i class="fas fa-times-circle mr-2"></i> Cancel Appointment
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <!-- Appointment Header -->
        <div class="bg-blue-600 text-white p-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-xl font-bold"><?php echo htmlspecialchars($appointment['title']); ?></h2>
                    <p class="text-blue-100 mt-1">
                        <i class="fas fa-user mr-2"></i> With <?php echo htmlspecialchars($appointment['advocate_name']); ?>
                    </p>
                </div>
                
                <div class="mt-4 md:mt-0">
                    <span class="px-3 py-1 inline-flex items-center rounded-full <?php echo $statusClass; ?>">
                        <i class="fas <?php echo $statusIcon; ?> mr-1"></i> <?php echo $statusText; ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Appointment Details -->
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Date & Time</h3>
                    
                    <div class="space-y-3">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 h-5 w-5 text-blue-600">
                                <i class="fas fa-calendar-day"></i>
                            </div>
                            <div class="ml-3 text-gray-700">
                                <span class="font-medium">Date:</span> <?php echo $formattedDate; ?>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="flex-shrink-0 h-5 w-5 text-blue-600">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="ml-3 text-gray-700">
                                <span class="font-medium">Time:</span> <?php echo $formattedStartTime; ?> - <?php echo $formattedEndTime; ?>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="flex-shrink-0 h-5 w-5 text-blue-600">
                                <i class="fas fa-hourglass-half"></i>
                            </div>
                            <div class="ml-3 text-gray-700">
                                <span class="font-medium">Duration:</span> <?php echo $durationText; ?>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="flex-shrink-0 h-5 w-5 text-blue-600">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div class="ml-3 text-gray-700">
                                <span class="font-medium">Location:</span> <?php echo htmlspecialchars($appointment['location']); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Advocate Information</h3>
                    
                    <div class="space-y-3">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 h-5 w-5 text-blue-600">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="ml-3 text-gray-700">
                                <span class="font-medium">Name:</span> <?php echo htmlspecialchars($appointment['advocate_name']); ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($appointment['advocate_email'])): ?>
                            <div class="flex items-start">
                                <div class="flex-shrink-0 h-5 w-5 text-blue-600">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div class="ml-3 text-gray-700">
                                    <span class="font-medium">Email:</span> 
                                    <a href="mailto:<?php echo htmlspecialchars($appointment['advocate_email']); ?>" class="text-blue-600 hover:underline">
                                        <?php echo htmlspecialchars($appointment['advocate_email']); ?>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($appointment['advocate_phone'])): ?>
                            <div class="flex items-start">
                                <div class="flex-shrink-0 h-5 w-5 text-blue-600">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <div class="ml-3 text-gray-700">
                                    <span class="font-medium">Phone:</span> 
                                    <a href="tel:<?php echo htmlspecialchars($appointment['advocate_phone']); ?>" class="text-blue-600 hover:underline">
                                        <?php echo htmlspecialchars($appointment['advocate_phone']); ?>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($appointment['case_id']): ?>
                            <div class="flex items-start">
                                <div class="flex-shrink-0 h-5 w-5 text-blue-600">
                                    <i class="fas fa-gavel"></i>
                                </div>
                                <div class="ml-3 text-gray-700">
                                    <span class="font-medium">Related Case:</span> 
                                    <a href="../cases/view.php?id=<?php echo $appointment['case_id']; ?>" class="text-blue-600 hover:underline">
                                        <?php echo htmlspecialchars($appointment['case_number'] . ' - ' . $appointment['case_title']); ?>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($appointment['description'])): ?>
                <div class="mt-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Description</h3>
                    <div class="bg-gray-50 rounded-lg p-4 text-gray-700">
                        <?php echo nl2br(htmlspecialchars($appointment['description'])); ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Add to Calendar Options -->
            <div class="mt-6 border-t border-gray-200 pt-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Add to Calendar</h3>
                <div class="flex flex-wrap gap-2">
                    <a href="#" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50" onclick="addToGoogleCalendar(); return false;">
                        <i class="fab fa-google text-red-500 mr-2"></i> Google Calendar
                    </a>
                    <a href="#" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50" onclick="addToOutlookCalendar(); return false;">
                        <i class="fab fa-microsoft text-blue-500 mr-2"></i> Outlook
                    </a>
                    <a href="#" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50" onclick="addToAppleCalendar(); return false;">
                        <i class="fab fa-apple text-gray-800 mr-2"></i> Apple Calendar
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
// Functions to add appointment to different calendar services
function addToGoogleCalendar() {
    const title = encodeURIComponent("<?php echo addslashes($appointment['title']); ?>");
    const description = encodeURIComponent("<?php echo addslashes(str_replace(array("\r", "\n"), ' ', $appointment['description'])); ?>");
    const location = encodeURIComponent("<?php echo addslashes($appointment['location']); ?>");
    const startDate = "<?php echo date('Ymd\\THis', strtotime($appointment['appointment_date'] . ' ' . $appointment['start_time'])); ?>";
    const endDate = "<?php echo date('Ymd\\THis', strtotime($appointment['appointment_date'] . ' ' . $appointment['end_time'])); ?>";
    
    const url = `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${title}&details=${description}&location=${location}&dates=${startDate}/${endDate}`;
    window.open(url, '_blank');
}

function addToOutlookCalendar() {
    const title = encodeURIComponent("<?php echo addslashes($appointment['title']); ?>");
    const description = encodeURIComponent("<?php echo addslashes(str_replace(array("\r", "\n"), ' ', $appointment['description'])); ?>");
    const location = encodeURIComponent("<?php echo addslashes($appointment['location']); ?>");
    const startDate = "<?php echo date('Y-m-d', strtotime($appointment['appointment_date'])); ?>T<?php echo date('H:i:s', strtotime($appointment['start_time'])); ?>";
    const endDate = "<?php echo date('Y-m-d', strtotime($appointment['appointment_date'])); ?>T<?php echo date('H:i:s', strtotime($appointment['end_time'])); ?>";
    
    const url = `https://outlook.office.com/calendar/0/deeplink/compose?subject=${title}&body=${description}&location=${location}&startdt=${startDate}&enddt=${endDate}`;
    window.open(url, '_blank');
}

function addToAppleCalendar() {
    const title = encodeURIComponent("<?php echo addslashes($appointment['title']); ?>");
    const description = encodeURIComponent("<?php echo addslashes(str_replace(array("\r", "\n"), ' ', $appointment['description'])); ?>");
    const location = encodeURIComponent("<?php echo addslashes($appointment['location']); ?>");
    const startDate = "<?php echo date('Ymd\\THis', strtotime($appointment['appointment_date'] . ' ' . $appointment['start_time'])); ?>";
    const endDate = "<?php echo date('Ymd\\THis', strtotime($appointment['appointment_date'] . ' ' . $appointment['end_time'])); ?>";
    
    // Create an iCalendar file
    const icsContent = `BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
SUMMARY:${title}
DESCRIPTION:${description}
LOCATION:${location}
DTSTART:${startDate}
DTEND:${endDate}
END:VEVENT
END:VCALENDAR`;
    
    const blob = new Blob([icsContent], { type: 'text/calendar;charset=utf-8' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'appointment.ics';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>


<?php
// Include footer
include '../includes/footer.php';
?>
