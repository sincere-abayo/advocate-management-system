<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user is logged in and has appropriate permissions
requireLogin();
requireUserType('advocate');

// Get advocate ID from session
$advocateId = $_SESSION['advocate_id'];

// Set page title
$pageTitle = "Reports Dashboard";

// Include header
include_once '../includes/header.php';

// Get database connection
$conn = getDBConnection();

// Get current year and month
$currentYear = date('Y');
$currentMonth = date('n');

// Get total cases
$casesQuery = "
    SELECT 
        COUNT(*) as total_cases,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_cases,
        SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_cases,
        SUM(CASE WHEN status = 'won' THEN 1 ELSE 0 END) as won_cases,
        SUM(CASE WHEN status = 'lost' THEN 1 ELSE 0 END) as lost_cases
    FROM cases c
    JOIN case_assignments ca ON c.case_id = ca.case_id
    WHERE ca.advocate_id = ?
";
$casesStmt = $conn->prepare($casesQuery);
$casesStmt->bind_param("i", $advocateId);
$casesStmt->execute();
$casesData = $casesStmt->get_result()->fetch_assoc();

// Get monthly case statistics for the current year
$monthlyCasesQuery = "
    SELECT 
        MONTH(c.filing_date) as month,
        COUNT(*) as new_cases
    FROM cases c
    JOIN case_assignments ca ON c.case_id = ca.case_id
    WHERE ca.advocate_id = ? AND YEAR(c.filing_date) = ?
    GROUP BY MONTH(c.filing_date)
";
$monthlyCasesStmt = $conn->prepare($monthlyCasesQuery);
$monthlyCasesStmt->bind_param("ii", $advocateId, $currentYear);
$monthlyCasesStmt->execute();
$monthlyCasesResult = $monthlyCasesStmt->get_result();

$monthlyCases = array_fill(1, 12, 0);
while ($row = $monthlyCasesResult->fetch_assoc()) {
    $monthlyCases[$row['month']] = (int)$row['new_cases'];
}

// Get financial summary for current year
$financialQuery = "
    SELECT 
        COALESCE(SUM(ci.amount), 0) as total_income,
        COALESCE(SUM(ce.amount), 0) as total_expenses
    FROM advocate_profiles ap
    LEFT JOIN case_income ci ON ci.advocate_id = ap.advocate_id AND YEAR(ci.income_date) = ?
    LEFT JOIN case_expenses ce ON ce.advocate_id = ap.advocate_id AND YEAR(ce.expense_date) = ?
    WHERE ap.advocate_id = ?
";
$financialStmt = $conn->prepare($financialQuery);
$financialStmt->bind_param("iii", $currentYear, $currentYear, $advocateId);
$financialStmt->execute();
$financialData = $financialStmt->get_result()->fetch_assoc();

$totalIncome = $financialData['total_income'];
$totalExpenses = $financialData['total_expenses'];
$netProfit = $totalIncome - $totalExpenses;
$profitMargin = $totalIncome > 0 ? ($netProfit / $totalIncome) * 100 : 0;

// Get monthly financial data
$monthlyFinancialQuery = "
    SELECT 
        MONTH(income_date) as month,
        SUM(amount) as income
    FROM case_income
    WHERE advocate_id = ? AND YEAR(income_date) = ?
    GROUP BY MONTH(income_date)
";
$monthlyFinancialStmt = $conn->prepare($monthlyFinancialQuery);
$monthlyFinancialStmt->bind_param("ii", $advocateId, $currentYear);
$monthlyFinancialStmt->execute();
$monthlyIncomeResult = $monthlyFinancialStmt->get_result();

$monthlyIncome = array_fill(1, 12, 0);
while ($row = $monthlyIncomeResult->fetch_assoc()) {
    $monthlyIncome[$row['month']] = (float)$row['income'];
}

$monthlyExpenseQuery = "
    SELECT 
        MONTH(expense_date) as month,
        SUM(amount) as expenses
    FROM case_expenses
    WHERE advocate_id = ? AND YEAR(expense_date) = ?
    GROUP BY MONTH(expense_date)
";
$monthlyExpenseStmt = $conn->prepare($monthlyExpenseQuery);
$monthlyExpenseStmt->bind_param("ii", $advocateId, $currentYear);
$monthlyExpenseStmt->execute();
$monthlyExpenseResult = $monthlyExpenseStmt->get_result();

$monthlyExpenses = array_fill(1, 12, 0);
while ($row = $monthlyExpenseResult->fetch_assoc()) {
    $monthlyExpenses[$row['month']] = (float)$row['expenses'];
}

// Get upcoming appointments
$appointmentsQuery = "
    SELECT 
        a.appointment_id,
        a.title,
        a.appointment_date,
        a.start_time,
        a.end_time,
        a.status,
        u.full_name as client_name,
        c.case_number,
        c.title as case_title
    FROM appointments a
    JOIN client_profiles cp ON a.client_id = cp.client_id
    JOIN users u ON cp.user_id = u.user_id
    LEFT JOIN cases c ON a.case_id = c.case_id
    WHERE a.advocate_id = ? AND a.appointment_date >= CURDATE()
    ORDER BY a.appointment_date, a.start_time
    LIMIT 5
";
$appointmentsStmt = $conn->prepare($appointmentsQuery);
$appointmentsStmt->bind_param("i", $advocateId);
$appointmentsStmt->execute();
$appointmentsResult = $appointmentsStmt->get_result();

// Get recent case activities
$activitiesQuery = "
    SELECT 
        ca.activity_id,
        ca.case_id,
        c.case_number,
        c.title as case_title,
        ca.activity_type,
        ca.description,
        ca.activity_date,
        u.full_name as user_name
    FROM case_activities ca
    JOIN cases c ON ca.case_id = c.case_id
    JOIN case_assignments cas ON c.case_id = cas.case_id
    JOIN users u ON ca.user_id = u.user_id
    WHERE cas.advocate_id = ?
    ORDER BY ca.activity_date DESC
    LIMIT 10
";
$activitiesStmt = $conn->prepare($activitiesQuery);
$activitiesStmt->bind_param("i", $advocateId);
$activitiesStmt->execute();
$activitiesResult = $activitiesStmt->get_result();

// Close database connection
$conn->close();
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Reports Dashboard</h1>
            <p class="text-gray-600">View performance metrics and analytics</p>
        </div>
        
        <div class="mt-4 md:mt-0 flex space-x-2">
            <a href="case-reports.php" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-file-alt mr-2"></i> Case Reports
            </a>
            <a href="financial-reports.php" class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-chart-line mr-2"></i> Financial Reports
            </a>
        </div>
    </div>
    
    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-500 mr-4">
                    <i class="fas fa-briefcase text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Total Cases</p>
                    <p class="text-2xl font-semibold"><?php echo $casesData['total_cases']; ?></p>
                </div>
            </div>
            <div class="mt-4 flex justify-between text-sm">
                <div>
                    <span class="text-green-500 font-medium"><?php echo $casesData['active_cases']; ?></span>
                    <span class="text-gray-500">Active</span>
                </div>
                <div>
                    <span class="text-blue-500 font-medium"><?php echo $casesData['closed_cases']; ?></span>
                    <span class="text-gray-500">Closed</span>
                </div>
                <div>
                    <span class="text-green-500 font-medium"><?php echo $casesData['won_cases']; ?></span>
                    <span class="text-gray-500">Won</span>
                </div>
                <div>
                    <span class="text-red-500 font-medium"><?php echo $casesData['lost_cases']; ?></span>
                    <span class="text-gray-500">Lost</span>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-500 mr-4">
                    <i class="fas fa-dollar-sign text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Total Income (<?php echo $currentYear; ?>)</p>
                    <p class="text-2xl font-semibold"><?php echo formatCurrency($totalIncome); ?></p>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex justify-between mb-1">
                    <span class="text-sm text-gray-500">Monthly Average</span>
                    <span class="text-sm text-gray-700 font-medium"><?php echo formatCurrency($totalIncome / 12); ?></span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-green-500 h-2 rounded-full" style="width: <?php echo min(100, ($monthlyIncome[$currentMonth] / ($totalIncome / 12)) * 100); ?>%"></div>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-red-100 text-red-500 mr-4">
                    <i class="fas fa-file-invoice-dollar text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Total Expenses (<?php echo $currentYear; ?>)</p>
                    <p class="text-2xl font-semibold"><?php echo formatCurrency($totalExpenses); ?></p>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex justify-between mb-1">
                    <span class="text-sm text-gray-500">Monthly Average</span>
                    <span class="text-sm text-gray-700 font-medium"><?php echo formatCurrency($totalExpenses / 12); ?></span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-red-500 h-2 rounded-full" style="width: <?php echo min(100, ($monthlyExpenses[$currentMonth] / ($totalExpenses / 12)) * 100); ?>%"></div>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-100 text-purple-500 mr-4">
                    <i class="fas fa-chart-pie text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Net Profit (<?php echo $currentYear; ?>)</p>
                    <p class="text-2xl font-semibold"><?php echo formatCurrency($netProfit); ?></p>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex justify-between mb-1">
                    <span class="text-sm text-gray-500">Profit Margin</span>
                    <span class="text-sm text-gray-700 font-medium"><?php echo number_format($profitMargin, 1); ?>%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-purple-500 h-2 rounded-full" style="width: <?php echo min(100, $profitMargin); ?>%"></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Case Statistics Chart -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Case Statistics (<?php echo $currentYear; ?>)</h2>
            <canvas id="caseStatisticsChart" height="300"></canvas>
        </div>
        
            <!-- Financial Overview Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Financial Overview (<?php echo $currentYear; ?>)</h2>
            <canvas id="financialOverviewChart" height="300"></canvas>
        </div>
    </div>
    
    <!-- Recent Activity and Upcoming Sections -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Upcoming Appointments -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Upcoming Appointments</h2>
            
            <?php if ($appointmentsResult->num_rows === 0): ?>
                <div class="text-center py-6">
                    <div class="text-gray-400 mb-2">
                        <i class="far fa-calendar-alt text-4xl"></i>
                    </div>
                    <p class="text-gray-500">No upcoming appointments</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php while ($appointment = $appointmentsResult->fetch_assoc()): ?>
                        <div class="border-l-4 border-blue-500 pl-4 py-2">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="font-medium text-gray-900"><?php echo htmlspecialchars($appointment['title']); ?></h3>
                                    <p class="text-sm text-gray-600">
                                        <i class="far fa-user mr-1"></i> 
                                        <?php echo htmlspecialchars($appointment['client_name']); ?>
                                    </p>
                                    <?php if ($appointment['case_number']): ?>
                                        <p class="text-sm text-gray-600">
                                            <i class="far fa-folder mr-1"></i> 
                                            <?php echo htmlspecialchars($appointment['case_number'] . ' - ' . $appointment['case_title']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-medium text-gray-900">
                                        <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?>
                                    </p>
                                    <p class="text-sm text-gray-600">
                                        <?php echo date('g:i A', strtotime($appointment['start_time'])); ?> - 
                                        <?php echo date('g:i A', strtotime($appointment['end_time'])); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                <div class="mt-4 text-right">
                    <a href="../appointments/index.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                        View All Appointments <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Recent Case Activities -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Recent Case Activities</h2>
            
            <?php if ($activitiesResult->num_rows === 0): ?>
                <div class="text-center py-6">
                    <div class="text-gray-400 mb-2">
                        <i class="far fa-clipboard text-4xl"></i>
                    </div>
                    <p class="text-gray-500">No recent activities</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php while ($activity = $activitiesResult->fetch_assoc()): ?>
                        <div class="border-l-4 
                            <?php 
                            switch ($activity['activity_type']) {
                                case 'update': echo 'border-blue-500'; break;
                                case 'document': echo 'border-green-500'; break;
                                case 'hearing': echo 'border-purple-500'; break;
                                case 'note': echo 'border-yellow-500'; break;
                                case 'status_change': echo 'border-red-500'; break;
                                default: echo 'border-gray-500';
                            }
                            ?> pl-4 py-2">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="font-medium text-gray-900">
                                        <a href="../cases/view.php?id=<?php echo $activity['case_id']; ?>" class="hover:text-blue-600">
                                            <?php echo htmlspecialchars($activity['case_number'] . ' - ' . $activity['case_title']); ?>
                                        </a>
                                    </h3>
                                    <p class="text-sm text-gray-600">
                                        <?php echo htmlspecialchars($activity['description']); ?>
                                    </p>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <i class="far fa-user mr-1"></i> 
                                        <?php echo htmlspecialchars($activity['user_name']); ?>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm text-gray-600">
                                        <?php echo date('M d, Y', strtotime($activity['activity_date'])); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                <div class="mt-4 text-right">
                    <a href="../cases/index.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                        View All Cases <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Case Statistics Chart
    const caseCtx = document.getElementById('caseStatisticsChart').getContext('2d');
    const caseChart = new Chart(caseCtx, {
        type: 'bar',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            datasets: [{
                label: 'New Cases',
                data: [
                    <?php echo implode(', ', $monthlyCases); ?>
                ],
                backgroundColor: 'rgba(59, 130, 246, 0.5)',
                borderColor: 'rgba(59, 130, 246, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
    
    // Financial Overview Chart
    const financialCtx = document.getElementById('financialOverviewChart').getContext('2d');
    const financialChart = new Chart(financialCtx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            datasets: [
                {
                    label: 'Income',
                    data: [
                        <?php echo implode(', ', $monthlyIncome); ?>
                    ],
                    backgroundColor: 'rgba(16, 185, 129, 0.2)',
                    borderColor: 'rgba(16, 185, 129, 1)',
                    borderWidth: 2,
                    tension: 0.1
                },
                {
                    label: 'Expenses',
                    data: [
                        <?php echo implode(', ', $monthlyExpenses); ?>
                    ],
                    backgroundColor: 'rgba(239, 68, 68, 0.2)',
                    borderColor: 'rgba(239, 68, 68, 1)',
                    borderWidth: 2,
                    tension: 0.1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
});
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>
