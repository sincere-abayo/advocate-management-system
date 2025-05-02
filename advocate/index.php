<?php
// Set page title
$pageTitle = "Dashboard";

// Include header
include_once 'includes/header.php';

// Get advocate ID
$advocateId = $advocateData['advocate_id'];
$conn = getDBConnection();
// Function to get case statistics
function getCaseStatistics($advocateId, $conn) {
  
    
    // Get total cases
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_cases,
            SUM(CASE WHEN c.status = 'active' THEN 1 ELSE 0 END) as active_cases,
            SUM(CASE WHEN c.status = 'pending' THEN 1 ELSE 0 END) as pending_cases,
            SUM(CASE WHEN c.status = 'closed' THEN 1 ELSE 0 END) as closed_cases,
            SUM(CASE WHEN c.status = 'won' THEN 1 ELSE 0 END) as won_cases,
            SUM(CASE WHEN c.status = 'lost' THEN 1 ELSE 0 END) as lost_cases
        FROM cases c
        JOIN case_assignments ca ON c.case_id = ca.case_id
        WHERE ca.advocate_id = ?
    ");
    
    $stmt->bind_param("i", $advocateId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();
    
    $stmt->close();
    
    return $stats;
}

// Function to get upcoming appointments
function getUpcomingAppointments($advocateId, $conn, $limit = 5) {
    
    $stmt = $conn->prepare("
        SELECT a.*, c.full_name as client_name, cs.title as case_title
        FROM appointments a
        JOIN client_profiles cp ON a.client_id = cp.client_id
        JOIN users c ON cp.user_id = c.user_id
        LEFT JOIN cases cs ON a.case_id = cs.case_id
        WHERE a.advocate_id = ? AND a.appointment_date >= CURDATE() AND a.status != 'cancelled'
        ORDER BY a.appointment_date ASC, a.start_time ASC
        LIMIT ?
    ");
    
    $stmt->bind_param("ii", $advocateId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $appointments = [];
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }
    
    $stmt->close();
    
    return $appointments;
}

// Function to get recent activities
function getRecentActivities($advocateId, $conn, $limit = 5) {
    
    $stmt = $conn->prepare("
        SELECT ca.*, c.title as case_title
        FROM case_activities ca
        JOIN cases c ON ca.case_id = c.case_id
        JOIN case_assignments cas ON c.case_id = cas.case_id
        WHERE cas.advocate_id = ?
        ORDER BY ca.activity_date DESC
        LIMIT ?
    ");
    
    $stmt->bind_param("ii", $advocateId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $activities = [];
    while ($row = $result->fetch_assoc()) {
        $activities[] = $row;
    }
    
    
    return $activities;
}

// Function to get financial summary
function getFinancialSummary($advocateId, $conn) {
    $conn = getDBConnection();
    
    // Get current month's data
    $stmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(amount), 0) as month_income
        FROM case_income
        WHERE advocate_id = ? AND MONTH(income_date) = MONTH(CURRENT_DATE()) AND YEAR(income_date) = YEAR(CURRENT_DATE())
    ");
    
    $stmt->bind_param("i", $advocateId);
    $stmt->execute();
    $result = $stmt->get_result();
    $monthIncome = $result->fetch_assoc()['month_income'];
    
    // Get current month's expenses
    $stmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(amount), 0) as month_expenses
        FROM case_expenses
        WHERE advocate_id = ? AND MONTH(expense_date) = MONTH(CURRENT_DATE()) AND YEAR(expense_date) = YEAR(CURRENT_DATE())
    ");
    
    $stmt->bind_param("i", $advocateId);
    $stmt->execute();
    $result = $stmt->get_result();
    $monthExpenses = $result->fetch_assoc()['month_expenses'];
    
    // Get year to date income
    $stmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(amount), 0) as ytd_income
        FROM case_income
        WHERE advocate_id = ? AND YEAR(income_date) = YEAR(CURRENT_DATE())
    ");
    
    $stmt->bind_param("i", $advocateId);
    $stmt->execute();
    $result = $stmt->get_result();
    $ytdIncome = $result->fetch_assoc()['ytd_income'];
    
    // Get year to date expenses
    $stmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(amount), 0) as ytd_expenses
        FROM case_expenses
        WHERE advocate_id = ? AND YEAR(expense_date) = YEAR(CURRENT_DATE())
    ");
    
    $stmt->bind_param("i", $advocateId);
    $stmt->execute();
    $result = $stmt->get_result();
    $ytdExpenses = $result->fetch_assoc()['ytd_expenses'];
    
    $stmt->close();
    
    return [
        'month_income' => $monthIncome,
        'month_expenses' => $monthExpenses,
        'month_profit' => $monthIncome - $monthExpenses,
        'ytd_income' => $ytdIncome,
        'ytd_expenses' => $ytdExpenses,
        'ytd_profit' => $ytdIncome - $ytdExpenses
    ];
}

// Get data for dashboard
$caseStats = getCaseStatistics($advocateId, $conn);
$upcomingAppointments = getUpcomingAppointments($advocateId, $conn);
$recentActivities = getRecentActivities($advocateId, $conn);
$financialSummary = getFinancialSummary($advocateId, $conn);

// Close the connection at the end of the page (after all database operations)
$conn->close();
?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
    <!-- Total Cases -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 text-blue-500 mr-4">
                <i class="fas fa-briefcase text-xl"></i>
            </div>
            <div>
                <p class="text-gray-500 text-sm">Total Cases</p>
                <p class="text-2xl font-semibold"><?php echo $caseStats['total_cases']; ?></p>
            </div>
        </div>
        <div class="mt-4">
            <div class="flex justify-between text-sm">
                <span class="text-green-500"><?php echo $caseStats['active_cases']; ?> Active</span>
                <span class="text-yellow-500"><?php echo $caseStats['pending_cases']; ?> Pending</span>
                <span class="text-gray-500"><?php echo $caseStats['closed_cases']; ?> Closed</span>
            </div>
        </div>
    </div>
    
    <!-- Monthly Income -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-100 text-green-500 mr-4">
                <i class="fas fa-dollar-sign text-xl"></i>
            </div>
            <div>
                <p class="text-gray-500 text-sm">Monthly Income</p>
                <p class="text-2xl font-semibold"><?php echo formatCurrency($financialSummary['month_income']); ?></p>
            </div>
        </div>
        <div class="mt-4">
            <div class="flex justify-between text-sm">
                <span class="text-gray-500">Expenses: <?php echo formatCurrency($financialSummary['month_expenses']); ?></span>
                <span class="<?php echo $financialSummary['month_profit'] >= 0 ? 'text-green-500' : 'text-red-500'; ?>">
                    Profit: <?php echo formatCurrency($financialSummary['month_profit']); ?>
                </span>
            </div>
        </div>
    </div>
    
    <!-- YTD Income -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-purple-100 text-purple-500 mr-4">
                <i class="fas fa-chart-line text-xl"></i>
            </div>
            <div>
                <p class="text-gray-500 text-sm">YTD Income</p>
                <p class="text-2xl font-semibold"><?php echo formatCurrency($financialSummary['ytd_income']); ?></p>
            </div>
        </div>
        <div class="mt-4">
            <div class="flex justify-between text-sm">
                <span class="text-gray-500">Expenses: <?php echo formatCurrency($financialSummary['ytd_expenses']); ?></span>
                <span class="<?php echo $financialSummary['ytd_profit'] >= 0 ? 'text-green-500' : 'text-red-500'; ?>">
                    Profit: <?php echo formatCurrency($financialSummary['ytd_profit']); ?>
                </span>
            </div>
        </div>
    </div>
    
    <!-- Upcoming Appointments -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-orange-100 text-orange-500 mr-4">
                <i class="fas fa-calendar-alt text-xl"></i>
            </div>
            <div>
                <p class="text-gray-500 text-sm">Upcoming Appointments</p>
                <p class="text-2xl font-semibold"><?php echo count($upcomingAppointments); ?></p>
            </div>
        </div>
        <div class="mt-4">
            <?php if (!empty($upcomingAppointments)): ?>
                <p class="text-sm text-gray-600">Next: <?php echo formatDate($upcomingAppointments[0]['appointment_date']); ?> at <?php echo formatTime($upcomingAppointments[0]['start_time']); ?></p>
            <?php else: ?>
                <p class="text-sm text-gray-600">No upcoming appointments</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Case Status Chart -->
    <div class="bg-white rounded-lg shadow p-6 lg:col-span-1">
        <h2 class="text-lg font-semibold mb-4">Case Status</h2>
        <div class="h-64">
            <canvas id="caseStatusChart"></canvas>
        </div>
    </div>
    
    <!-- Monthly Income Chart -->
    <div class="bg-white rounded-lg shadow p-6 lg:col-span-2">
        <div class="h-64">
        <h2 class="text-lg font-semibold mb-4">Monthly Financial Overview</h2>
        <div class="h-64">
            <canvas id="financialChart"></canvas>
        </div>
    </div>
</div>
</div>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Upcoming Appointments -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-semibold">Upcoming Appointments</h2>
            <a href="/advocate/appointments/index.php" class="text-sm text-blue-600 hover:underline">View All</a>
        </div>
        
        <?php if (empty($upcomingAppointments)): ?>
            <div class="text-center py-6">
                <div class="text-gray-400 mb-2"><i class="fas fa-calendar-times text-3xl"></i></div>
                <p class="text-gray-500">No upcoming appointments</p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($upcomingAppointments as $appointment): ?>
                    <div class="border-l-4 border-blue-500 pl-4 py-2">
                        <div class="flex justify-between">
                            <p class="font-medium"><?php echo htmlspecialchars($appointment['title']); ?></p>
                            <span class="text-sm text-gray-500"><?php echo formatTime($appointment['start_time']); ?></span>
                        </div>
                        <p class="text-sm text-gray-600">
                            <?php echo formatDate($appointment['appointment_date']); ?>
                        </p>
                        <p class="text-sm text-gray-600">
                            Client: <?php echo htmlspecialchars($appointment['client_name']); ?>
                        </p>
                        <?php if (!empty($appointment['case_title'])): ?>
                            <p class="text-xs text-gray-500 mt-1">
                                Case: <?php echo htmlspecialchars($appointment['case_title']); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Recent Activities -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-semibold">Recent Activities</h2>
            <a href="/advocate/cases/activities.php" class="text-sm text-blue-600 hover:underline">View All</a>
        </div>
        
        <?php if (empty($recentActivities)): ?>
            <div class="text-center py-6">
                <div class="text-gray-400 mb-2"><i class="fas fa-history text-3xl"></i></div>
                <p class="text-gray-500">No recent activities</p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($recentActivities as $activity): ?>
                    <div class="flex">
                        <div class="flex-shrink-0 mr-3">
                            <?php
                            $iconClass = 'fas fa-info-circle text-blue-500';
                            switch ($activity['activity_type']) {
                                case 'update':
                                    $iconClass = 'fas fa-edit text-blue-500';
                                    break;
                                case 'document':
                                    $iconClass = 'fas fa-file-alt text-green-500';
                                    break;
                                case 'hearing':
                                    $iconClass = 'fas fa-gavel text-purple-500';
                                    break;
                                case 'note':
                                    $iconClass = 'fas fa-sticky-note text-yellow-500';
                                    break;
                                case 'status_change':
                                    $iconClass = 'fas fa-exchange-alt text-red-500';
                                    break;
                            }
                            ?>
                            <div class="bg-gray-100 rounded-full p-2">
                                <i class="<?php echo $iconClass; ?>"></i>
                            </div>
                        </div>
                        <div>
                            <p class="text-sm font-medium">
                                <?php echo htmlspecialchars($activity['case_title']); ?>
                            </p>
                            <p class="text-xs text-gray-500">
                                <?php echo htmlspecialchars($activity['description']); ?>
                            </p>
                            <p class="text-xs text-gray-400 mt-1">
                                <?php echo date('M d, Y h:i A', strtotime($activity['activity_date'])); ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Quick Actions -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-4">Quick Actions</h2>
        
        <div class="grid grid-cols-2 gap-4">
            <a href="/advocate/cases/create.php" class="flex flex-col items-center justify-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition duration-200">
                <div class="text-blue-500 mb-2"><i class="fas fa-briefcase text-2xl"></i></div>
                <span class="text-sm font-medium text-gray-700">New Case</span>
            </a>
            
            <a href="/advocate/appointments/create.php" class="flex flex-col items-center justify-center p-4 bg-green-50 rounded-lg hover:bg-green-100 transition duration-200">
                <div class="text-green-500 mb-2"><i class="fas fa-calendar-plus text-2xl"></i></div>
                <span class="text-sm font-medium text-gray-700">Schedule Appointment</span>
            </a>
            
            <a href="/advocate/documents/upload.php" class="flex flex-col items-center justify-center p-4 bg-purple-50 rounded-lg hover:bg-purple-100 transition duration-200">
                <div class="text-purple-500 mb-2"><i class="fas fa-file-upload text-2xl"></i></div>
                <span class="text-sm font-medium text-gray-700">Upload Document</span>
            </a>
            
            <a href="/advocate/time-tracking/log-activity.php" class="flex flex-col items-center justify-center p-4 bg-orange-50 rounded-lg hover:bg-orange-100 transition duration-200">
                <div class="text-orange-500 mb-2"><i class="fas fa-clock text-2xl"></i></div>
                <span class="text-sm font-medium text-gray-700">Log Time</span>
            </a>
            
            <a href="/advocate/finance/invoices/create.php" class="flex flex-col items-center justify-center p-4 bg-red-50 rounded-lg hover:bg-red-100 transition duration-200">
                <div class="text-red-500 mb-2"><i class="fas fa-file-invoice-dollar text-2xl"></i></div>
                <span class="text-sm font-medium text-gray-700">Create Invoice</span>
            </a>
            
            <a href="/advocate/clients/index.php" class="flex flex-col items-center justify-center p-4 bg-yellow-50 rounded-lg hover:bg-yellow-100 transition duration-200">
                <div class="text-yellow-500 mb-2"><i class="fas fa-users text-2xl"></i></div>
                <span class="text-sm font-medium text-gray-700">View Clients</span>
            </a>
        </div>
    </div>
</div>

<!-- JavaScript for Charts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Case Status Chart
    const caseStatusCtx = document.getElementById('caseStatusChart').getContext('2d');
    const caseStatusChart = new Chart(caseStatusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Active', 'Pending', 'Closed', 'Won', 'Lost'],
            datasets: [{
                data: [
                    <?php echo $caseStats['active_cases']; ?>,
                    <?php echo $caseStats['pending_cases']; ?>,
                    <?php echo $caseStats['closed_cases']; ?>,
                    <?php echo $caseStats['won_cases']; ?>,
                    <?php echo $caseStats['lost_cases']; ?>
                ],
                backgroundColor: [
                    '#3B82F6', // blue
                    '#F59E0B', // amber
                    '#6B7280', // gray
                    '#10B981', // green
                    '#EF4444'  // red
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        padding: 15
                    }
                }
            },
            cutout: '70%'
        }
    });
    
    // Financial Chart - Last 6 months
    const financialCtx = document.getElementById('financialChart').getContext('2d');
    const financialChart = new Chart(financialCtx, {
        type: 'bar',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [
                {
                    label: 'Income',
                    data: [4500, 5200, 3800, 5100, 6200, 4800],
                    backgroundColor: 'rgba(59, 130, 246, 0.7)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Expenses',
                    data: [3200, 3800, 2900, 3600, 4100, 3500],
                    backgroundColor: 'rgba(239, 68, 68, 0.7)',
                    borderColor: 'rgba(239, 68, 68, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Profit',
                    data: [1300, 1400, 900, 1500, 2100, 1300],
                    backgroundColor: 'rgba(16, 185, 129, 0.7)',
                    borderColor: 'rgba(16, 185, 129, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$' + value;
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php
// Include footer
include_once 'includes/footer.php';
?>
