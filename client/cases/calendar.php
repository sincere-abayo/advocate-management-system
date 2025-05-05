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

// Connect to database
$conn = getDBConnection();

// Get current month and year (from query parameters or default to current month)
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Validate month and year
if ($month < 1 || $month > 12) {
    $month = (int)date('m');
}
if ($year < 2000 || $year > 2100) {
    $year = (int)date('Y');
}

// Calculate previous and next month/year
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

// Get all hearings for the selected month
$query = "
    SELECT 
        ch.*,
        c.case_number,
        c.title as case_title,
        c.case_type
    FROM case_hearings ch
    JOIN cases c ON ch.case_id = c.case_id
    WHERE c.client_id = ?
    AND MONTH(ch.hearing_date) = ?
    AND YEAR(ch.hearing_date) = ?
    ORDER BY ch.hearing_date ASC, ch.hearing_time ASC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $clientId, $month, $year);
$stmt->execute();
$result = $stmt->get_result();

// Organize hearings by date
$hearingsByDate = [];
while ($hearing = $result->fetch_assoc()) {
    $date = $hearing['hearing_date'];
    if (!isset($hearingsByDate[$date])) {
        $hearingsByDate[$date] = [];
    }
    $hearingsByDate[$date][] = $hearing;
}

// Close connection
$conn->close();

// Set page title
$pageTitle = "Hearings Calendar";
include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Hearings Calendar</h1>
            <p class="text-gray-600">View your court hearings in a calendar format</p>
        </div>
        
        <div class="mt-4 md:mt-0">
            <a href="hearings.php" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-list mr-2"></i> Switch to List View
            </a>
        </div>
    </div>
    
    <!-- Month Navigation -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="flex justify-between items-center">
            <a href="?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-chevron-left mr-2"></i> Previous Month
            </a>
            
            <h2 class="text-xl font-bold text-gray-800">
                <?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?>
            </h2>
            
            <a href="?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg inline-flex items-center">
                Next Month <i class="fas fa-chevron-right ml-2"></i>
            </a>
        </div>
    </div>
    
    <!-- Calendar -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <!-- Calendar Header -->
        <div class="grid grid-cols-7 gap-px bg-gray-200">
            <?php
            $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            foreach ($dayNames as $dayName):
            ?>
                <div class="bg-gray-50 py-2 text-center text-sm font-medium text-gray-700">
                    <?php echo $dayName; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Calendar Grid -->
        <div class="grid grid-cols-7 gap-px bg-gray-200">
            <?php
            // Get the first day of the month
            $firstDayOfMonth = mktime(0, 0, 0, $month, 1, $year);
            $numberDays = date('t', $firstDayOfMonth);
            $firstDayOfWeek = date('w', $firstDayOfMonth);
            
            // Add empty cells for days before the first day of the month
            for ($i = 0; $i < $firstDayOfWeek; $i++):
            ?>
                <div class="bg-gray-100 min-h-[120px] p-2"></div>
            <?php endfor; 
            // Generate calendar days
            for ($day = 1; $day <= $numberDays; $day++):
                $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $isToday = ($date == date('Y-m-d'));
                $hasHearings = isset($hearingsByDate[$date]);
                $cellClass = $isToday ? 'bg-blue-50 border border-blue-200' : 'bg-white';
            ?>
                <div class="<?php echo $cellClass; ?> min-h-[120px] p-2">
                    <div class="flex justify-between items-center mb-2">
                        <span class="<?php echo $isToday ? 'font-bold text-blue-600' : 'font-medium text-gray-700'; ?>">
                            <?php echo $day; ?>
                        </span>
                        <?php if ($hasHearings): ?>
                            <span class="bg-green-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                                <?php echo count($hearingsByDate[$date]); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($hasHearings): ?>
                        <div class="space-y-1">
                            <?php foreach ($hearingsByDate[$date] as $hearing): ?>
                                <a href="hearing-details.php?id=<?php echo $hearing['hearing_id']; ?>" 
                                   class="block text-xs p-1 rounded truncate
                                   <?php
                                   switch ($hearing['status']) {
                                       case 'scheduled':
                                           echo 'bg-blue-100 text-blue-800 hover:bg-blue-200';
                                           break;
                                       case 'completed':
                                           echo 'bg-green-100 text-green-800 hover:bg-green-200';
                                           break;
                                       case 'cancelled':
                                           echo 'bg-red-100 text-red-800 hover:bg-red-200';
                                           break;
                                       case 'postponed':
                                           echo 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200';
                                           break;
                                       default:
                                           echo 'bg-gray-100 text-gray-800 hover:bg-gray-200';
                                   }
                                   ?>">
                                    <div class="font-medium"><?php echo date('h:i A', strtotime($hearing['hearing_time'])); ?></div>
                                    <div class="truncate"><?php echo htmlspecialchars($hearing['hearing_type']); ?></div>
                                    <div class="truncate text-gray-600"><?php echo htmlspecialchars($hearing['case_number']); ?></div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
            
            <?php
            // Add empty cells for days after the last day of the month
            $lastDayOfWeek = date('w', mktime(0, 0, 0, $month, $numberDays, $year));
            for ($i = $lastDayOfWeek + 1; $i < 7; $i++):
            ?>
                <div class="bg-gray-100 min-h-[120px] p-2"></div>
            <?php endfor; ?>
        </div>
    </div>
    
    <!-- Legend -->
    <div class="mt-6 bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-medium text-gray-800 mb-4">Status Legend</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="flex items-center">
                <div class="w-4 h-4 rounded bg-blue-100 mr-2"></div>
                <span class="text-sm text-gray-700">Scheduled</span>
            </div>
            <div class="flex items-center">
                <div class="w-4 h-4 rounded bg-green-100 mr-2"></div>
                <span class="text-sm text-gray-700">Completed</span>
            </div>
            <div class="flex items-center">
                <div class="w-4 h-4 rounded bg-yellow-100 mr-2"></div>
                <span class="text-sm text-gray-700">Postponed</span>
            </div>
            <div class="flex items-center">
                <div class="w-4 h-4 rounded bg-red-100 mr-2"></div>
                <span class="text-sm text-gray-700">Cancelled</span>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include '../includes/footer.php';
?>