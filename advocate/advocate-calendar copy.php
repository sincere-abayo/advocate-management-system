
<?php
// Start session
session_start();

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Check if user has advocate role
if($_SESSION['role'] != 'advocate') {
    header("Location: ../login.php");
    exit();
}

// Include database and required classes
include_once '../config/database.php';
include_once '../classes/Advocate.php';
include_once '../classes/Event.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Initialize objects
$advocate_obj = new Advocate($db);
$event_obj = new Event($db);

// Get advocate ID
$advocate_obj->user_id = $_SESSION['user_id'];
if(!$advocate_obj->readByUserId()) {
    header("Location: advocate-dashboard.php");
    exit();
}

// Get events for this advocate
$event_obj->advocate_id = $advocate_obj->id;
$events = $event_obj->readByAdvocate();

// Format events for calendar
$calendar_events = [];
if($events && $events->rowCount() > 0) {
    while($event = $events->fetch(PDO::FETCH_ASSOC)) {
        $end_time = !empty($event['end_time']) ? 'T' . $event['end_time'] : '';
        $end_date = !empty($event['end_time']) ? $event['event_date'] . $end_time : '';
        
        $color = '#3788d8'; // Default blue
        
        // Set different colors based on event type
        if(!empty($event['event_type'])) {
            switch($event['event_type']) {
                case 'Hearing':
                    $color = '#e74c3c'; // Red
                    break;
                case 'Meeting':
                    $color = '#2ecc71'; // Green
                    break;
                case 'Deadline':
                    $color = '#f39c12'; // Orange
                    break;
                case 'Reminder':
                    $color = '#9b59b6'; // Purple
                    break;
            }
        }
        
        $calendar_events[] = [
            'id' => $event['id'],
            'title' => $event['title'],
            'start' => $event['event_date'] . 'T' . $event['event_time'],
            'end' => $end_date,
            'url' => 'advocate-event-view.php?id=' . $event['id'],
            'backgroundColor' => $color,
            'borderColor' => $color,
            'extendedProps' => [
                'location' => $event['location'],
                'case_number' => $event['case_number']
            ]
        ];
    }
}

// Convert to JSON for JavaScript
$events_json = json_encode($calendar_events);

// Set page title
$page_title = "Calendar - Legal Case Management System";

// Include header
include_once '../templates/advocate-header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-semibold text-gray-800">Calendar</h1>
        <a href="advocate-event-add.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
            <i class="fas fa-plus mr-2"></i>Add Event
        </a>
    </div>
    
    <!-- Calendar -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div id="calendar" class="p-4"></div>
    </div>
</div>

<!-- FullCalendar JS -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
        },
        events: <?php echo $events_json; ?>,
        eventTimeFormat: {
            hour: '2-digit',
            minute: '2-digit',
            meridiem: 'short'
        },
        eventClick: function(info) {
            info.jsEvent.preventDefault(); // prevent browser from following the link
            window.location.href = info.event.url;
        },
        eventDidMount: function(info) {
            // Add tooltip with additional information
            if(info.event.extendedProps.location || info.event.extendedProps.case_number) {
                var tooltip = '';
                
                if(info.event.extendedProps.case_number) {
                    tooltip += 'Case: ' + info.event.extendedProps.case_number + '<br>';
                }
                
                if(info.event.extendedProps.location) {
                    tooltip += 'Location: ' + info.event.extendedProps.location;
                }
                
                $(info.el).tooltip({
                    title: tooltip,
                    placement: 'top',
                    trigger: 'hover',
                    container: 'body',
                    html: true
                });
            }
        }
    });
    
    calendar.render();
});
</script>

<?php
// Include footer
include_once '../templates/footer.php';
?>
