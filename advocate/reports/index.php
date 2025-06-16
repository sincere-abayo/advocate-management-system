<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireLogin();
requireUserType('advocate');

$advocateId = $_SESSION['advocate_id'];
$pageTitle = "Reports Dashboard";
include_once '../includes/header.php';

$conn = getDBConnection();
$currentYear = date('Y');

// --- Summary Cards Data ---

// Total, Active, Closed, Won, Lost Cases
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

// --- Financial Summary ---
// Total Income: sum of paid billings for this advocate in this year
$incomeQuery = "
    SELECT COALESCE(SUM(b.amount), 0) as total_income
    FROM billings b
    WHERE b.advocate_id = ? AND b.status = 'paid' AND YEAR(b.payment_date) = ?
";
$incomeStmt = $conn->prepare($incomeQuery);
$incomeStmt->bind_param("ii", $advocateId, $currentYear);
$incomeStmt->execute();
$incomeData = $incomeStmt->get_result()->fetch_assoc();
$totalIncome = $incomeData['total_income'];

// Expenses
$expensesQuery = "
    SELECT COALESCE(SUM(amount), 0) as total_expenses
    FROM case_expenses
    WHERE advocate_id = ? AND YEAR(expense_date) = ?
";
$expensesStmt = $conn->prepare($expensesQuery);
$expensesStmt->bind_param("ii", $advocateId, $currentYear);
$expensesStmt->execute();
$expensesData = $expensesStmt->get_result()->fetch_assoc();
$totalExpenses = $expensesData['total_expenses'];

$netProfit = $totalIncome - $totalExpenses;
$monthlyAvgIncome = $totalIncome / 12;

// --- Monthly Cases Chart Data ---
$monthlyCasesQuery = "
    SELECT MONTH(c.filing_date) as month, COUNT(*) as new_cases
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
    $monthlyCases[(int)$row['month']] = (int)$row['new_cases'];
}

// --- Recent Appointments ---
$appointmentsQuery = "
    SELECT 
        a.appointment_date, a.start_time, a.end_time,
        c.title as case_title, c.case_id
    FROM appointments a
    LEFT JOIN cases c ON a.case_id = c.case_id
    WHERE a.advocate_id = ? AND a.appointment_date >= CURDATE()
    ORDER BY a.appointment_date, a.start_time
    LIMIT 5
";
$appointmentsStmt = $conn->prepare($appointmentsQuery);
$appointmentsStmt->bind_param("i", $advocateId);
$appointmentsStmt->execute();
$appointmentsResult = $appointmentsStmt->get_result();

// --- Recent Case Activities ---
$activitiesQuery = "
    SELECT 
        ca.activity_type, ca.activity_date, c.title as case_title, c.case_id, u.full_name as user_name
    FROM case_activities ca
    JOIN cases c ON ca.case_id = c.case_id
    JOIN case_assignments cas ON c.case_id = cas.case_id
    JOIN users u ON ca.user_id = u.user_id
    WHERE cas.advocate_id = ?
    ORDER BY ca.activity_date DESC
    LIMIT 5
";
$activitiesStmt = $conn->prepare($activitiesQuery);
$activitiesStmt->bind_param("i", $advocateId);
$activitiesStmt->execute();
$activitiesResult = $activitiesStmt->get_result();

$conn->close();
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Reports Dashboard</h1>
            <p class="text-gray-600">View performance metrics and analytics</p>
        </div>
        <div class="mt-4 md:mt-0 flex space-x-2">
            <a href="case-reports.php"
                class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg">Case Reports</a>
            <a href="financial-reports.php"
                class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg">Financial
                Reports</a>
        </div>
    </div>
    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center mb-2">
                <span class="text-blue-500 mr-2"><i class="fas fa-briefcase"></i></span>
                <span class="text-gray-500">Total Cases</span>
            </div>
            <div class="text-2xl font-bold"><?= $casesData['total_cases'] ?? 0 ?></div>
            <div class="flex space-x-4 mt-2 text-sm">
                <span class="text-green-600"><?= $casesData['active_cases'] ?? 0 ?> Active</span>
                <span class="text-gray-600"><?= $casesData['closed_cases'] ?? 0 ?> Closed</span>
                <span class="text-blue-600"><?= $casesData['won_cases'] ?? 0 ?> Won</span>
                <span class="text-red-600"><?= $casesData['lost_cases'] ?? 0 ?> Lost</span>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center mb-2">
                <span class="text-green-400 mr-2"><i class="fas fa-dollar-sign"></i></span>
                <span class="text-gray-500">Total Income (<?= $currentYear ?>)</span>
            </div>
            <div class="text-2xl font-bold">RWF <?= number_format($totalIncome, 2) ?></div>
            <div class="text-xs text-gray-500 mt-2">Monthly Average</div>
            <div class="w-full bg-gray-100 rounded-full h-2.5 mt-1">
                <div class="bg-green-400 h-2.5 rounded-full"
                    style="width:<?= min(100, ($monthlyAvgIncome > 0 ? ($monthlyAvgIncome / max($totalIncome,1)) * 100 : 0)) ?>%">
                </div>
            </div>
            <div class="text-xs text-right text-gray-500 mt-1">RWF <?= number_format($monthlyAvgIncome, 2) ?></div>
        </div>
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center mb-2">
                <span class="text-indigo-400 mr-2"><i class="fas fa-coins"></i></span>
                <span class="text-gray-500">Net Profit (<?= $currentYear ?>)</span>
            </div>
            <div class="text-2xl font-bold">RWF <?= number_format($netProfit, 2) ?></div>
        </div>
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center mb-2">
                <span class="text-red-400 mr-2"><i class="fas fa-money-bill-wave"></i></span>
                <span class="text-gray-500">Total Expenses (<?= $currentYear ?>)</span>
            </div>
            <div class="text-2xl font-bold">RWF <?= number_format($totalExpenses, 2) ?></div>
        </div>
    </div>
    <!-- Monthly Cases Chart -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-lg font-semibold mb-4">Monthly New Cases (<?= $currentYear ?>)</h2>
        <canvas id="monthlyCasesChart" height="120"></canvas>
    </div>
    <!-- Recent Appointments -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">Upcoming Appointments</h2>
        <ul>
            <?php if ($appointmentsResult->num_rows === 0): ?>
            <li class="text-gray-500">No upcoming appointments.</li>
            <?php else: while ($row = $appointmentsResult->fetch_assoc()): ?>
            <li class="mb-2">
                <span class="font-medium"><?= htmlspecialchars($row['case_title'] ?? 'General') ?></span>
                <span class="text-gray-500">on <?= date('d M Y', strtotime($row['appointment_date'])) ?></span>
                <?php if (!empty($row['start_time'])): ?>
                <span class="text-gray-400">at <?= date('H:i', strtotime($row['start_time'])) ?></span>
                <?php endif; ?>
            </li>
            <?php endwhile; endif; ?>
        </ul>
        <a href="../appointments/index.php" class="text-blue-600 hover:underline text-sm">View all appointments</a>
    </div>
    <!-- Recent Case Activities -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">Recent Case Activities</h2>
        <ul>
            <?php if ($activitiesResult->num_rows === 0): ?>
            <li class="text-gray-500">No recent activities.</li>
            <?php else: while ($row = $activitiesResult->fetch_assoc()): ?>
            <li class="mb-2">
                <span class="font-medium"><?= htmlspecialchars($row['activity_type']) ?></span>
                <span class="text-gray-500">on <?= date('d M Y', strtotime($row['activity_date'])) ?></span>
                <span class="text-gray-400">by <?= htmlspecialchars($row['user_name']) ?></span>
                <?php if (!empty($row['case_title'])): ?>
                <span class="text-blue-600">[<?= htmlspecialchars($row['case_title']) ?>]</span>
                <?php endif; ?>
            </li>
            <?php endwhile; endif; ?>
        </ul>
        <a href="../cases/" class="text-blue-600 hover:underline text-sm">View all activities</a>
    </div>
</div>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('monthlyCasesChart').getContext('2d');
    const monthlyCases = <?= json_encode(array_values($monthlyCases)) ?>;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: [
                <?= implode(',', array_map(fn($m) => "'".date('M', mktime(0,0,0,$m,1))."'", range(1,12))) ?>
            ],
            datasets: [{
                label: 'New Cases',
                data: monthlyCases,
                backgroundColor: '#2563eb'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
});
</script>
<?php include_once '../includes/footer.php'; ?>