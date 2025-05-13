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
$pageTitle = "Case Reports";

// Include header
include_once '../includes/header.php';

// Get database connection
$conn = getDBConnection();

// Initialize filters
$currentYear = date('Y');
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : $currentYear;
$selectedStatus = isset($_GET['status']) ? $_GET['status'] : '';
$selectedType = isset($_GET['case_type']) ? $_GET['case_type'] : '';

// Get available years for filter (last 5 years)
$years = [];
for ($i = $currentYear; $i >= $currentYear - 4; $i--) {
    $years[] = $i;
}

// Get case types for filter
$caseTypesQuery = "
    SELECT DISTINCT case_type 
    FROM cases c
    JOIN case_assignments ca ON c.case_id = ca.case_id
    WHERE ca.advocate_id = ?
    ORDER BY case_type
";
$caseTypesStmt = $conn->prepare($caseTypesQuery);
$caseTypesStmt->bind_param("i", $advocateId);
$caseTypesStmt->execute();
$caseTypesResult = $caseTypesStmt->get_result();

$caseTypes = [];
while ($type = $caseTypesResult->fetch_assoc()) {
    $caseTypes[] = $type['case_type'];
}

// Build WHERE clause based on filters
$whereClause = "ca.advocate_id = ?";
$params = [$advocateId];
$types = "i";

if ($selectedYear > 0) {
    $whereClause .= " AND YEAR(c.filing_date) = ?";
    $params[] = $selectedYear;
    $types .= "i";
}

if (!empty($selectedStatus)) {
    $whereClause .= " AND c.status = ?";
    $params[] = $selectedStatus;
    $types .= "s";
}

if (!empty($selectedType)) {
    $whereClause .= " AND c.case_type = ?";
    $params[] = $selectedType;
    $types .= "s";
}

// Get case statistics
$statsQuery = "
    SELECT 
        COUNT(*) as total_cases,
        SUM(CASE WHEN c.status = 'pending' THEN 1 ELSE 0 END) as pending_cases,
        SUM(CASE WHEN c.status = 'active' THEN 1 ELSE 0 END) as active_cases,
        SUM(CASE WHEN c.status = 'closed' THEN 1 ELSE 0 END) as closed_cases,
        SUM(CASE WHEN c.status = 'won' THEN 1 ELSE 0 END) as won_cases,
        SUM(CASE WHEN c.status = 'lost' THEN 1 ELSE 0 END) as lost_cases,
        SUM(CASE WHEN c.status = 'settled' THEN 1 ELSE 0 END) as settled_cases,
        AVG(DATEDIFF(
            CASE 
                WHEN c.status IN ('closed', 'won', 'lost', 'settled') THEN c.updated_at
                ELSE CURDATE() 
            END, 
            c.filing_date
        )) as avg_case_duration
    FROM cases c
    JOIN case_assignments ca ON c.case_id = ca.case_id
    WHERE $whereClause
";

$statsStmt = $conn->prepare($statsQuery);
$statsStmt->bind_param($types, ...$params);
$statsStmt->execute();
$statsData = $statsStmt->get_result()->fetch_assoc();

// Get case type distribution
$typeDistributionQuery = "
    SELECT 
        c.case_type,
        COUNT(*) as count
    FROM cases c
    JOIN case_assignments ca ON c.case_id = ca.case_id
    WHERE $whereClause
    GROUP BY c.case_type
    ORDER BY count DESC
";
$typeDistributionStmt = $conn->prepare($typeDistributionQuery);
$typeDistributionStmt->bind_param($types, ...$params);
$typeDistributionStmt->execute();
$typeDistributionResult = $typeDistributionStmt->get_result();

$caseTypeLabels = [];
$caseTypeCounts = [];
while ($row = $typeDistributionResult->fetch_assoc()) {
    $caseTypeLabels[] = $row['case_type'];
    $caseTypeCounts[] = (int)$row['count'];
}

// Get case status distribution
$statusDistributionQuery = "
    SELECT 
        c.status,
        COUNT(*) as count
    FROM cases c
    JOIN case_assignments ca ON c.case_id = ca.case_id
    WHERE $whereClause
    GROUP BY c.status
    ORDER BY count DESC
";
$statusDistributionStmt = $conn->prepare($statusDistributionQuery);
$statusDistributionStmt->bind_param($types, ...$params);
$statusDistributionStmt->execute();
$statusDistributionResult = $statusDistributionStmt->get_result();

$caseStatusLabels = [];
$caseStatusCounts = [];
while ($row = $statusDistributionResult->fetch_assoc()) {
    $caseStatusLabels[] = ucfirst($row['status']);
    $caseStatusCounts[] = (int)$row['count'];
}

// Get monthly case filing data
$monthlyFilingsQuery = "
    SELECT 
        MONTH(c.filing_date) as month,
        COUNT(*) as count
    FROM cases c
    JOIN case_assignments ca ON c.case_id = ca.case_id
    WHERE ca.advocate_id = ? AND YEAR(c.filing_date) = ?
    GROUP BY MONTH(c.filing_date)
    ORDER BY MONTH(c.filing_date)
";
$monthlyFilingsStmt = $conn->prepare($monthlyFilingsQuery);
$monthlyFilingsStmt->bind_param("ii", $advocateId, $selectedYear);
$monthlyFilingsStmt->execute();
$monthlyFilingsResult = $monthlyFilingsStmt->get_result();

$monthlyFilings = array_fill(1, 12, 0);
while ($row = $monthlyFilingsResult->fetch_assoc()) {
    $monthlyFilings[$row['month']] = (int)$row['count'];
}

// Get case outcome data (for won/lost/settled cases)
$outcomeQuery = "
    SELECT 
        YEAR(c.updated_at) as year,
        SUM(CASE WHEN c.status = 'won' THEN 1 ELSE 0 END) as won,
        SUM(CASE WHEN c.status = 'lost' THEN 1 ELSE 0 END) as lost,
        SUM(CASE WHEN c.status = 'settled' THEN 1 ELSE 0 END) as settled
    FROM cases c
    JOIN case_assignments ca ON c.case_id = ca.case_id
    WHERE ca.advocate_id = ? AND c.status IN ('won', 'lost', 'settled')
    GROUP BY YEAR(c.updated_at)
    ORDER BY YEAR(c.updated_at) DESC
    LIMIT 5
";

$outcomeStmt = $conn->prepare($outcomeQuery);
$outcomeStmt->bind_param("i", $advocateId);
$outcomeStmt->execute();
$outcomeResult = $outcomeStmt->get_result();

$outcomeYears = [];
$outcomeWon = [];
$outcomeLost = [];
$outcomeSettled = [];
while ($row = $outcomeResult->fetch_assoc()) {
    $outcomeYears[] = $row['year'];
    $outcomeWon[] = (int)$row['won'];
    $outcomeLost[] = (int)$row['lost'];
    $outcomeSettled[] = (int)$row['settled'];
}
// Reverse arrays to show oldest to newest
$outcomeYears = array_reverse($outcomeYears);
$outcomeWon = array_reverse($outcomeWon);
$outcomeLost = array_reverse($outcomeLost);
$outcomeSettled = array_reverse($outcomeSettled);

// Get top performing cases (by financial metrics)
$topCasesQuery = "
    SELECT 
        c.case_id,
        c.case_number,
        c.title,
        c.status,
        c.total_income,
        c.total_expenses,
        c.profit,
        CASE WHEN c.total_income > 0 THEN (c.profit / c.total_income) * 100 ELSE 0 END as margin
    FROM cases c
    JOIN case_assignments ca ON c.case_id = ca.case_id
    WHERE ca.advocate_id = ?
    ORDER BY c.profit DESC
    LIMIT 10
";
$topCasesStmt = $conn->prepare($topCasesQuery);
$topCasesStmt->bind_param("i", $advocateId);
$topCasesStmt->execute();
$topCasesResult = $topCasesStmt->get_result();

// Get expense data
$expensesQuery = "
    SELECT 
        ce.expense_id,
        ce.case_id,
        c.case_number,
        c.title as case_title,
        ce.expense_date,
        ce.amount,
        ce.description,
        ce.expense_category
    FROM case_expenses ce
    LEFT JOIN cases c ON ce.case_id = c.case_id
    WHERE ce.advocate_id = ? AND YEAR(ce.expense_date) = ?
    ORDER BY ce.expense_date DESC
    LIMIT 10
";
$expensesStmt = $conn->prepare($expensesQuery);
$expensesStmt->bind_param("ii", $advocateId, $selectedYear);
$expensesStmt->execute();
$expensesResult = $expensesStmt->get_result();

// Get income data
$incomeQuery = "
    SELECT 
        ci.income_id,
        ci.case_id,
        c.case_number,
        c.title as case_title,
        ci.income_date,
        ci.amount,
        ci.description,
        ci.income_category,
        ci.payment_method
    FROM case_income ci
    LEFT JOIN cases c ON ci.case_id = c.case_id
    WHERE ci.advocate_id = ? AND YEAR(ci.income_date) = ?
    ORDER BY ci.income_date DESC
    LIMIT 10
";
$incomeStmt = $conn->prepare($incomeQuery);
$incomeStmt->bind_param("ii", $advocateId, $selectedYear);
$incomeStmt->execute();
$incomeResult = $incomeStmt->get_result();

// Get invoice data
$invoicesQuery = "
    SELECT 
        b.billing_id,
        b.case_id,
        c.case_number,
        c.title as case_title,
        cp.client_id,
        u.full_name as client_name,
        b.amount,
        b.billing_date,
        b.due_date,
        b.status,
        b.payment_method,
        b.payment_date
    FROM billings b
    LEFT JOIN cases c ON b.case_id = c.case_id
    JOIN client_profiles cp ON b.client_id = cp.client_id
    JOIN users u ON cp.user_id = u.user_id
    WHERE b.advocate_id = ? AND YEAR(b.billing_date) = ?
    ORDER BY b.billing_date DESC
    LIMIT 10
";
$invoicesStmt = $conn->prepare($invoicesQuery);
$invoicesStmt->bind_param("ii", $advocateId, $selectedYear);
$invoicesStmt->execute();
$invoicesResult = $invoicesStmt->get_result();

// Get financial statistics
$financialStatsQuery = "
    SELECT 
        COALESCE(SUM(ci.amount), 0) as total_income,
        (SELECT COALESCE(SUM(amount), 0) FROM case_expenses WHERE advocate_id = ? AND YEAR(expense_date) = ?) as total_expenses,
        (SELECT COUNT(*) FROM billings WHERE advocate_id = ? AND YEAR(billing_date) = ?) as total_invoices,
        (SELECT COUNT(*) FROM billings WHERE advocate_id = ? AND YEAR(billing_date) = ? AND status = 'paid') as paid_invoices,
        (SELECT COUNT(*) FROM billings WHERE advocate_id = ? AND YEAR(billing_date) = ? AND status = 'pending') as pending_invoices,
        (SELECT COUNT(*) FROM billings WHERE advocate_id = ? AND YEAR(billing_date) = ? AND status = 'overdue') as overdue_invoices,
        (SELECT COALESCE(SUM(amount), 0) FROM billings WHERE advocate_id = ? AND YEAR(billing_date) = ? AND status = 'paid') as paid_amount
    FROM case_income ci
    WHERE ci.advocate_id = ? AND YEAR(ci.income_date) = ?
";
$financialStatsStmt = $conn->prepare($financialStatsQuery);
$financialStatsStmt->bind_param("iiiiiiiiiiiiii", 
    $advocateId, $selectedYear, 
    $advocateId, $selectedYear, 
    $advocateId, $selectedYear, 
    $advocateId, $selectedYear, 
    $advocateId, $selectedYear, 
    $advocateId, $selectedYear,
    $advocateId, $selectedYear
);
$financialStatsStmt->execute();
$financialStats = $financialStatsStmt->get_result()->fetch_assoc();

// Calculate profit and collection rate
$totalProfit = $financialStats['total_income'] - $financialStats['total_expenses'];
$profitMargin = $financialStats['total_income'] > 0 ? ($totalProfit / $financialStats['total_income']) * 100 : 0;
$collectionRate = $financialStats['total_invoices'] > 0 ? ($financialStats['paid_invoices'] / $financialStats['total_invoices']) * 100 : 0;
// Close database connection
$conn->close();
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Case Reports</h1>
            <p class="text-gray-600">Analyze case performance and outcomes</p>
        </div>
        
<div class="mt-4 md:mt-0 flex space-x-2">
    <a href="index.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg inline-flex items-center">
        <i class="fas fa-arrow-left mr-2"></i> Back to Reports
    </a>
    <button id="printReport" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
        <i class="fas fa-print mr-2"></i> Print Report
    </button>
    <button id="downloadPdf" class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
        <i class="fas fa-file-pdf mr-2"></i> Download PDF
    </button>
</div>

    </div>
    
    <!-- Report Filters -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Report Filters</h2>
        <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label for="year" class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                <select id="year" name="year" class="form-select w-full">
                    <?php foreach ($years as $year): ?>
                        <option value="<?php echo $year; ?>" <?php echo $selectedYear == $year ? 'selected' : ''; ?>>
                            <?php echo $year; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Case Status</label>
                <select id="status" name="status" class="form-select w-full">
                    <option value="" <?php echo $selectedStatus === '' ? 'selected' : ''; ?>>All Statuses</option>
                    <option value="pending" <?php echo $selectedStatus === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="active" <?php echo $selectedStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="closed" <?php echo $selectedStatus === 'closed' ? 'selected' : ''; ?>>Closed</option>
                    <option value="won" <?php echo $selectedStatus === 'won' ? 'selected' : ''; ?>>Won</option>
                    <option value="lost" <?php echo $selectedStatus === 'lost' ? 'selected' : ''; ?>>Lost</option>
                    <option value="settled" <?php echo $selectedStatus === 'settled' ? 'selected' : ''; ?>>Settled</option>
                </select>
            </div>
            
            <div>
                <label for="case_type" class="block text-sm font-medium text-gray-700 mb-1">Case Type</label>
                <select id="case_type" name="case_type" class="form-select w-full">
                    <option value="" <?php echo $selectedType === '' ? 'selected' : ''; ?>>All Types</option>
                    <?php foreach ($caseTypes as $type): ?>
                        <option value="<?php echo $type; ?>" <?php echo $selectedType === $type ? 'selected' : ''; ?>>
                            <?php echo $type; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="md:col-span-3">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg">
                    <i class="fas fa-filter mr-2"></i> Apply Filters
                </button>
                <a href="case-reports.php" class="ml-2 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg inline-flex items-center">
                    <i class="fas fa-times mr-2"></i> Clear Filters
                </a>
            </div>
        </form>
    </div>
    
    <div id="reportContent">
        <!-- Case Statistics Summary -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-500 mr-4">
                        <i class="fas fa-briefcase text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Total Cases</p>
                        <p class="text-2xl font-semibold"><?php echo $statsData['total_cases']; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-500 mr-4">
                        <i class="fas fa-trophy text-xl"></i>
                    </div>
                    <div>
                    <p class="text-gray-500 text-sm">Success Rate</p>
                        <?php 
                        $closedCases = $statsData['won_cases'] + $statsData['lost_cases'] + $statsData['settled_cases'];
                        $successRate = $closedCases > 0 ? ($statsData['won_cases'] / $closedCases) * 100 : 0;
                        ?>
                        <p class="text-2xl font-semibold"><?php echo number_format($successRate, 1); ?>%</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-500 mr-4">
                        <i class="fas fa-calendar-alt text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Avg. Case Duration</p>
                        <?php 
                        $avgDuration = round($statsData['avg_case_duration'] ?? 0);
                        ?>
                        <p class="text-2xl font-semibold"><?php echo $avgDuration; ?> days</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-500 mr-4">
                        <i class="fas fa-balance-scale text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Active vs. Closed</p>
                        <?php 
                        $activeRatio = $statsData['total_cases'] > 0 ? 
                            ($statsData['active_cases'] / $statsData['total_cases']) * 100 : 0;
                        ?>
                        <p class="text-2xl font-semibold"><?php echo number_format($activeRatio, 1); ?>% active</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Case Status Distribution Chart -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Case Status Distribution</h2>
                <div class="h-64">
                    <canvas id="caseStatusChart"></canvas>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Case Type Distribution</h2>
                <div class="h-64">
                    <canvas id="caseTypeChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Monthly Case Filings Chart -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Monthly Case Filings (<?php echo $selectedYear; ?>)</h2>
            <div class="h-80">
                <canvas id="monthlyFilingsChart"></canvas>
            </div>
        </div>
        
        <!-- Case Outcomes Chart -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Case Outcomes by Year</h2>
            <?php if (empty($outcomeYears)): ?>
                <div class="text-center py-8 text-gray-500">
                    <p>No outcome data available for the selected filters.</p>
                </div>
            <?php else: ?>
                <div class="h-80">
                    <canvas id="caseOutcomesChart"></canvas>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Top Performing Cases Table -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Top Performing Cases (Financial)</h2>
            <?php if ($topCasesResult->num_rows === 0): ?>
                <div class="text-center py-8 text-gray-500">
                    <p>No financial data available for the selected filters.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Case</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Income</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expenses</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Profit</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Margin</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($case = $topCasesResult->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <a href="../cases/view.php?id=<?php echo $case['case_id']; ?>" class="text-blue-600 hover:text-blue-900 font-medium">
                                            <?php echo htmlspecialchars($case['case_number']); ?>
                                        </a>
                                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($case['title']); ?></p>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        $statusClass = 'bg-gray-100 text-gray-800';
                                        switch ($case['status']) {
                                            case 'active': $statusClass = 'bg-blue-100 text-blue-800'; break;
                                            case 'pending': $statusClass = 'bg-yellow-100 text-yellow-800'; break;
                                            case 'won': $statusClass = 'bg-green-100 text-green-800'; break;
                                            case 'lost': $statusClass = 'bg-red-100 text-red-800'; break;
                                            case 'settled': $statusClass = 'bg-purple-100 text-purple-800'; break;
                                            case 'closed': $statusClass = 'bg-gray-100 text-gray-800'; break;
                                        }
                                        ?>
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                            <?php echo ucfirst($case['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo formatCurrency($case['total_income']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo formatCurrency($case['total_expenses']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium <?php echo $case['profit'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                        <?php echo formatCurrency($case['profit']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <span class="text-sm <?php echo $case['margin'] >= 0 ? 'text-green-600' : 'text-red-600'; ?> font-medium">
                                                <?php echo number_format($case['margin'], 1); ?>%
                                            </span>
                                            <div class="ml-2 w-16 bg-gray-200 rounded-full h-2">
                                                <div class="<?php echo $case['margin'] >= 0 ? 'bg-green-500' : 'bg-red-500'; ?> h-2 rounded-full" style="width: <?php echo min(abs($case['margin']), 100); ?>%"></div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

<!-- Financial Statistics Section -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-semibold text-gray-800">Financial Statistics (<?php echo $selectedYear; ?>)</h2>
        <button class="download-section-pdf bg-green-600 hover:bg-green-700 text-white font-medium py-1 px-3 rounded-lg text-sm inline-flex items-center" data-section="financial-stats">
            <i class="fas fa-file-pdf mr-1"></i> Download PDF
        </button>
    </div>
    
    <div id="financial-stats" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-gray-50 rounded-lg p-4">
            <p class="text-gray-500 text-sm">Total Income</p>
            <p class="text-xl font-semibold"><?php echo formatCurrency($financialStats['total_income']); ?></p>
        </div>
        
        <div class="bg-gray-50 rounded-lg p-4">
            <p class="text-gray-500 text-sm">Total Expenses</p>
            <p class="text-xl font-semibold"><?php echo formatCurrency($financialStats['total_expenses']); ?></p>
        </div>
        
        <div class="bg-gray-50 rounded-lg p-4">
            <p class="text-gray-500 text-sm">Net Profit</p>
            <p class="text-xl font-semibold <?php echo $totalProfit >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                <?php echo formatCurrency($totalProfit); ?>
            </p>
        </div>
        
        <div class="bg-gray-50 rounded-lg p-4">
            <p class="text-gray-500 text-sm">Profit Margin</p>
            <p class="text-xl font-semibold <?php echo $profitMargin >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                <?php echo number_format($profitMargin, 1); ?>%
            </p>
        </div>
        
        <div class="bg-gray-50 rounded-lg p-4">
            <p class="text-gray-500 text-sm">Total Invoices</p>
            <p class="text-xl font-semibold"><?php echo $financialStats['total_invoices']; ?></p>
        </div>
        
        <div class="bg-gray-50 rounded-lg p-4">
            <p class="text-gray-500 text-sm">Paid Invoices</p>
            <p class="text-xl font-semibold text-green-600"><?php echo $financialStats['paid_invoices']; ?></p>
        </div>
        
        <div class="bg-gray-50 rounded-lg p-4">
            <p class="text-gray-500 text-sm">Pending Invoices</p>
            <p class="text-xl font-semibold text-yellow-600"><?php echo $financialStats['pending_invoices']; ?></p>
        </div>
        
        <div class="bg-gray-50 rounded-lg p-4">
            <p class="text-gray-500 text-sm">Collection Rate</p>
            <p class="text-xl font-semibold"><?php echo number_format($collectionRate, 1); ?>%</p>
        </div>
    </div>
</div>

<!-- Expenses Table -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-semibold text-gray-800">Recent Expenses (<?php echo $selectedYear; ?>)</h2>
        <div class="flex space-x-2">
            <a href="../finance/expenses/index.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                View All <i class="fas fa-arrow-right ml-1"></i>
            </a>
            <button class="download-section-pdf bg-green-600 hover:bg-green-700 text-white font-medium py-1 px-3 rounded-lg text-sm inline-flex items-center" data-section="expenses-table">
                <i class="fas fa-file-pdf mr-1"></i> Download PDF
            </button>
        </div>
    </div>
    
    <div id="expenses-table" class="overflow-x-auto">
        <?php if ($expensesResult->num_rows === 0): ?>
            <div class="text-center py-8 text-gray-500">
                <p>No expense data available for the selected year.</p>
            </div>
        <?php else: ?>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Case</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php while ($expense = $expensesResult->fetch_assoc()): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo date('M d, Y', strtotime($expense['expense_date'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php if ($expense['case_id']): ?>
                                    <a href="../cases/view.php?id=<?php echo $expense['case_id']; ?>" class="text-blue-600 hover:text-blue-900">
                                        <?php echo htmlspecialchars($expense['case_number']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-gray-500">No Case</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo htmlspecialchars($expense['expense_category'] ?? 'Uncategorized'); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <?php echo htmlspecialchars($expense['description']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                                <?php echo formatCurrency($expense['amount']); ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Income Table -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-semibold text-gray-800">Recent Income (<?php echo $selectedYear; ?>)</h2>
        <div class="flex space-x-2">
            <a href="../finance/income/index.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                View All <i class="fas fa-arrow-right ml-1"></i>
            </a>
            <button class="download-section-pdf bg-green-600 hover:bg-green-700 text-white font-medium py-1 px-3 rounded-lg text-sm inline-flex items-center" data-section="income-table">
                <i class="fas fa-file-pdf mr-1"></i> Download PDF
            </button>
        </div>
    </div>
    
    <div id="income-table" class="overflow-x-auto">
        <?php if ($incomeResult->num_rows === 0): ?>
            <div class="text-center py-8 text-gray-500">
                <p>No income data available for the selected year.</p>
            </div>
        <?php else: ?>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Case</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php while ($income = $incomeResult->fetch_assoc()): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo date('M d, Y', strtotime($income['income_date'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php if ($income['case_id']): ?>
                                    <a href="../cases/view.php?id=<?php echo $income['case_id']; ?>" class="text-blue-600 hover:text-blue-900">
                                        <?php echo htmlspecialchars($income['case_number']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-gray-500">No Case</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo htmlspecialchars($income['income_category'] ?? 'Uncategorized'); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <?php echo htmlspecialchars($income['description']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                                <?php echo formatCurrency($income['amount']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo htmlspecialchars($income['payment_method'] ?? 'N/A'); ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Invoices Table -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-semibold text-gray-800">Recent Invoices (<?php echo $selectedYear; ?>)</h2>
        <div class="flex space-x-2">
            <a href="../finance/invoices/index.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                View All <i class="fas fa-arrow-right ml-1"></i>
            </a>
            <button class="download-section-pdf bg-green-600 hover:bg-green-700 text-white font-medium py-1 px-3 rounded-lg text-sm inline-flex items-center" data-section="invoices-table">
                <i class="fas fa-file-pdf mr-1"></i> Download PDF
            </button>
        </div>
    </div>
    
    <div id="invoices-table" class="overflow-x-auto">
        <?php if ($invoicesResult->num_rows === 0): ?>
            <div class="text-center py-8 text-gray-500">
                <p>No invoice data available for the selected year.</p>
            </div>
        <?php else: ?>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice #</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Case</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php while ($invoice = $invoicesResult->fetch_assoc()): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <a href="../finance/invoices/view.php?id=<?php echo $invoice['billing_id']; ?>" class="text-blue-600 hover:text-blue-900">
                                    INV-<?php echo str_pad($invoice['billing_id'], 5, '0', STR_PAD_LEFT); ?>
                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo htmlspecialchars($invoice['client_name']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php if ($invoice['case_id']): ?>
                                    <a href="../cases/view.php?id=<?php echo $invoice['case_id']; ?>" class="text-blue-600 hover:text-blue-900">
                                        <?php echo htmlspecialchars($invoice['case_number']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-gray-500">No Case</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo date('M d, Y', strtotime($invoice['billing_date'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo date('M d, Y', strtotime($invoice['due_date'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo formatCurrency($invoice['amount']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $statusClass = 'bg-gray-100 text-gray-800';
                                switch ($invoice['status']) {
                                    case 'paid':
                                        $statusClass = 'bg-green-100 text-green-800';
                                        break;
                                    case 'pending':
                                        $statusClass = 'bg-yellow-100 text-yellow-800';
                                        break;
                                    case 'overdue':
                                        $statusClass = 'bg-red-100 text-red-800';
                                        break;
                                    case 'cancelled':
                                        $statusClass = 'bg-gray-100 text-gray-800';
                                        break;
                                }
                                ?>
                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                    <?php echo ucfirst($invoice['status']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Add JavaScript for section-specific PDF downloads -->
<script>
// Add this to your existing DOMContentLoaded event handler
document.addEventListener('DOMContentLoaded', function() {
    // Existing chart initialization code...
    
    // Section-specific PDF downloads
    document.querySelectorAll('.download-section-pdf').forEach(button => {
        button.addEventListener('click', function() {
            const sectionId = this.getAttribute('data-section');
            const sectionElement = document.getElementById(sectionId);
            const sectionTitle = this.closest('.bg-white').querySelector('h2').textContent;
            
            // Show loading indicator
            const loadingIndicator = document.createElement('div');
            loadingIndicator.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            loadingIndicator.innerHTML = `
                <div class="bg-white p-5 rounded-lg shadow-lg text-center">
                    <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-blue-600 mx-auto mb-3"></div>
                    <p class="text-gray-700">Generating PDF...</p>
                </div>
            `;
            document.body.appendChild(loadingIndicator);
            
            // Use setTimeout to allow the loading indicator to render
            setTimeout(function() {
                generateSectionPDF(sectionId, sectionTitle, loadingIndicator);
            }, 100);
        });
    });
    
    function generateSectionPDF(sectionId, sectionTitle, loadingIndicator) {
        const { jsPDF } = window.jspdf;
        const sectionElement = document.getElementById(sectionId);
        
        // Create a new PDF document
        const pdf = new jsPDF('p', 'mm', 'a4');
        
        // Add title
        pdf.setFontSize(18);
        pdf.setTextColor(33, 33, 33);
        pdf.text(sectionTitle, 105, 15, { align: 'center' });
        
        // Add filters information
        pdf.setFontSize(10);
        pdf.setTextColor(100, 100, 100);
        let filterText = 'Filters: ';
        filterText += 'Year: <?php echo $selectedYear; ?>';
        filterText += '<?php echo !empty($selectedStatus) ? ' | Status: ' . ucfirst($selectedStatus) : ''; ?>';
        filterText += '<?php echo !empty($selectedType) ? ' | Type: ' . $selectedType : ''; ?>';
        pdf.text(filterText, 105, 22, { align: 'center' });
        
        // Add date generated
        pdf.setFontSize(8);
        pdf.text('Generated on: ' + new Date().toLocaleString(), 105, 26, { align: 'center' });
        
        if (sectionId === 'financial-stats') {
            // Financial statistics
            pdf.setFontSize(12);
            pdf.setTextColor(33, 33, 33);
            
            pdf.text('Total Income: <?php echo formatCurrency($financialStats['total_income']); ?>', 20, 40);
            pdf.text('Total Expenses: <?php echo formatCurrency($financialStats['total_expenses']); ?>', 20, 48);
            pdf.text('Net Profit: <?php echo formatCurrency($totalProfit); ?>', 20, 56);
            pdf.text('Profit Margin: <?php echo number_format($profitMargin, 1); ?>%', 20, 64);
            
            pdf.text('Total Invoices: <?php echo $financialStats['total_invoices']; ?>', 120, 40);
            pdf.text('Paid Invoices: <?php echo $financialStats['paid_invoices']; ?>', 120, 48);
            pdf.text('Pending Invoices: <?php echo $financialStats['pending_invoices']; ?>', 120, 56);
            pdf.text('Collection Rate: <?php echo number_format($collectionRate, 1); ?>%', 120, 64);
            
            pdf.save('financial-stats-<?php echo $selectedYear; ?>.pdf');
            document.body.removeChild(loadingIndicator);
        } 
        else {
            // For tables (expenses, income, invoices)
            html2canvas(sectionElement).then(canvas => {
                const imgData = canvas.toDataURL('image/png');
                
                // Calculate the width and height to fit the page
                const imgWidth = 190;
                const pageHeight = 295;  
                const imgHeight = canvas.height * imgWidth / canvas.width;
                let heightLeft = imgHeight;
                let position = 30;
                
                // Add image to the first page
                pdf.addImage(imgData, 'PNG', 10, position, imgWidth, imgHeight);
                heightLeft -= pageHeight - position;
                
                // Add new pages if the table is too long
                while (heightLeft > 0) {
                    position = 0;
                    pdf.addPage();
                    pdf.addImage(imgData, 'PNG', 10, position - (pageHeight - 30), imgWidth, imgHeight);
                    heightLeft -= pageHeight;
                }
                
                // Save the PDF
                pdf.save(`${sectionId}-<?php echo $selectedYear; ?>.pdf`);
                
                // Remove loading indicator
                document.body.removeChild(loadingIndicator);
            });
        }
    }
});
</script>




</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Case Status Distribution Chart
    const statusCtx = document.getElementById('caseStatusChart').getContext('2d');
    const statusChart = new Chart(statusCtx, {
        type: 'pie',
        data: {
            labels: <?php echo json_encode($caseStatusLabels); ?>,
            datasets: [{
                data: <?php echo json_encode($caseStatusCounts); ?>,
                backgroundColor: [
                    'rgba(59, 130, 246, 0.7)',   // Blue
                    'rgba(16, 185, 129, 0.7)',   // Green
                    'rgba(239, 68, 68, 0.7)',    // Red
                    'rgba(245, 158, 11, 0.7)',   // Yellow
                    'rgba(139, 92, 246, 0.7)',   // Purple
                    'rgba(107, 114, 128, 0.7)'   // Gray
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right'
                }
            }
        }
    });
    
    // Case Type Distribution Chart
    const typeCtx = document.getElementById('caseTypeChart').getContext('2d');
    const typeChart = new Chart(typeCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($caseTypeLabels); ?>,
            datasets: [{
                data: <?php echo json_encode($caseTypeCounts); ?>,
                backgroundColor: [
                    'rgba(59, 130, 246, 0.7)',   // Blue
                    'rgba(16, 185, 129, 0.7)',   // Green
                    'rgba(239, 68, 68, 0.7)',    // Red
                    'rgba(245, 158, 11, 0.7)',   // Yellow
                    'rgba(139, 92, 246, 0.7)',   // Purple
                    'rgba(107, 114, 128, 0.7)',  // Gray
                    'rgba(236, 72, 153, 0.7)',   // Pink
                    'rgba(6, 182, 212, 0.7)',    // Cyan
                    'rgba(249, 115, 22, 0.7)',   // Orange
                    'rgba(5, 150, 105, 0.7)'     // Emerald
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right'
                }
            }
        }
    });
    
    // Monthly Case Filings Chart
    const monthlyCtx = document.getElementById('monthlyFilingsChart').getContext('2d');
    const monthlyChart = new Chart(monthlyCtx, {
        type: 'bar',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            datasets: [{
                label: 'New Cases',
                data: <?php echo json_encode(array_values($monthlyFilings)); ?>,
                backgroundColor: 'rgba(59, 130, 246, 0.7)',
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
    
    <?php if (!empty($outcomeYears)): ?>
    // Case Outcomes Chart
    const outcomesCtx = document.getElementById('caseOutcomesChart').getContext('2d');
    const outcomesChart = new Chart(outcomesCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($outcomeYears); ?>,
            datasets: [
                {
                    label: 'Won',
                    data: <?php echo json_encode($outcomeWon); ?>,
                    backgroundColor: 'rgba(16, 185, 129, 0.7)',
                    borderColor: 'rgba(16, 185, 129, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Lost',
                    data: <?php echo json_encode($outcomeLost); ?>,
                    backgroundColor: 'rgba(239, 68, 68, 0.7)',
                    borderColor: 'rgba(239, 68, 68, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Settled',
                    data: <?php echo json_encode($outcomeSettled); ?>,
                    backgroundColor: 'rgba(139, 92, 246, 0.7)',
                    borderColor: 'rgba(139, 92, 246, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    stacked: true
                },
                y: {
                    stacked: true,
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
    <?php endif; ?>
    
    // Print report functionality
    document.getElementById('printReport').addEventListener('click', function() {
        window.print();
    });
});
document.addEventListener('DOMContentLoaded', function() {
    // Existing chart initialization code...
    
    // Print report functionality
    document.getElementById('printReport').addEventListener('click', function() {
        window.print();
    });
    
    // Download PDF functionality
    document.getElementById('downloadPdf').addEventListener('click', function() {
        // Show loading indicator
        const loadingIndicator = document.createElement('div');
        loadingIndicator.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
        loadingIndicator.innerHTML = `
            <div class="bg-white p-5 rounded-lg shadow-lg text-center">
                <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-blue-600 mx-auto mb-3"></div>
                <p class="text-gray-700">Generating PDF...</p>
            </div>
        `;
        document.body.appendChild(loadingIndicator);
        
        // Use setTimeout to allow the loading indicator to render
        setTimeout(function() {
            generatePDF();
        }, 100);
        
        function generatePDF() {
            const { jsPDF } = window.jspdf;
            const reportContent = document.getElementById('reportContent');
            
            // Create a new PDF document
            const pdf = new jsPDF('p', 'mm', 'a4');
            
            // Add title
            pdf.setFontSize(18);
            pdf.setTextColor(33, 33, 33);
            pdf.text('Case Reports', 105, 15, { align: 'center' });
            
            // Add filters information
            pdf.setFontSize(10);
            pdf.setTextColor(100, 100, 100);
            let filterText = 'Filters: ';
            filterText += 'Year: <?php echo $selectedYear; ?>';
            filterText += '<?php echo !empty($selectedStatus) ? ' | Status: ' . ucfirst($selectedStatus) : ''; ?>';
            filterText += '<?php echo !empty($selectedType) ? ' | Type: ' . $selectedType : ''; ?>';
            pdf.text(filterText, 105, 22, { align: 'center' });
            
            // Add date generated
            pdf.setFontSize(8);
            pdf.text('Generated on: ' + new Date().toLocaleString(), 105, 26, { align: 'center' });
            
            // Add summary statistics
            pdf.setFontSize(12);
            pdf.setTextColor(33, 33, 33);
            pdf.text('Summary Statistics', 20, 35);
            
            pdf.setFontSize(10);
            pdf.text('Total Cases: <?php echo $statsData['total_cases']; ?>', 20, 42);
            pdf.text('Success Rate: <?php echo number_format($successRate, 1); ?>%', 20, 48);
            pdf.text('Avg. Case Duration: <?php echo $avgDuration; ?> days', 20, 54);
            pdf.text('Active Cases: <?php echo $statsData['active_cases']; ?>', 20, 60);
            pdf.text('Pending Cases: <?php echo $statsData['pending_cases']; ?>', 20, 66);
            pdf.text('Closed Cases: <?php echo $statsData['closed_cases']; ?>', 20, 72);
            pdf.text('Won Cases: <?php echo $statsData['won_cases']; ?>', 20, 78);
            pdf.text('Lost Cases: <?php echo $statsData['lost_cases']; ?>', 20, 84);
            pdf.text('Settled Cases: <?php echo $statsData['settled_cases']; ?>', 20, 90);
            
            // Capture charts as images
            html2canvas(document.getElementById('caseStatusChart')).then(canvas => {
                const statusChartImg = canvas.toDataURL('image/png');
                pdf.addImage(statusChartImg, 'PNG', 20, 100, 80, 50);
                
                html2canvas(document.getElementById('caseTypeChart')).then(canvas => {
                    const typeChartImg = canvas.toDataURL('image/png');
                    pdf.addImage(typeChartImg, 'PNG', 110, 100, 80, 50);
                    
                    html2canvas(document.getElementById('monthlyFilingsChart')).then(canvas => {
                        const monthlyChartImg = canvas.toDataURL('image/png');
                        pdf.addPage();
                        pdf.text('Monthly Case Filings (<?php echo $selectedYear; ?>)', 20, 20);
                        pdf.addImage(monthlyChartImg, 'PNG', 20, 30, 170, 80);
                        
                        <?php if (!empty($outcomeYears)): ?>
                        html2canvas(document.getElementById('caseOutcomesChart')).then(canvas => {
                            const outcomesChartImg = canvas.toDataURL('image/png');
                            pdf.addPage();
                            pdf.text('Case Outcomes by Year', 20, 20);
                            pdf.addImage(outcomesChartImg, 'PNG', 20, 30, 170, 80);
                            
                            // Add top performing cases table
                            if (<?php echo $topCasesResult->num_rows; ?> > 0) {
                                pdf.addPage();
                                pdf.text('Top Performing Cases (Financial)', 20, 20);
                                
                                // Table headers
                                pdf.setFontSize(9);
                                pdf.setTextColor(100, 100, 100);
                                pdf.text('Case', 20, 30);
                                pdf.text('Status', 70, 30);
                                pdf.text('Income', 100, 30);
                                pdf.text('Expenses', 130, 30);
                                pdf.text('Profit', 160, 30);
                                pdf.text('Margin', 180, 30);
                                
                                // Draw header line
                                pdf.setDrawColor(200, 200, 200);
                                pdf.line(20, 32, 190, 32);
                                
                                // Table data
                                pdf.setTextColor(33, 33, 33);
                                let y = 38;
                                <?php 
                                $topCasesResult->data_seek(0); // Reset result pointer
                                $rowCount = 0;
                                while ($case = $topCasesResult->fetch_assoc()): 
                                    if ($rowCount < 10): // Limit to 10 rows for PDF
                                ?>
                                pdf.text('<?php echo $case['case_number']; ?>', 20, y);
                                pdf.text('<?php echo ucfirst($case['status']); ?>', 70, y);
                                pdf.text('$<?php echo number_format($case['total_income'], 2); ?>', 100, y);
                                pdf.text('$<?php echo number_format($case['total_expenses'], 2); ?>', 130, y);
                                pdf.text('$<?php echo number_format($case['profit'], 2); ?>', 160, y);
                                pdf.text('<?php echo number_format($case['margin'], 1); ?>%', 180, y);
                                
                                // Draw row line
                                pdf.line(20, y + 2, 190, y + 2);
                                
                                y += 10;
                                <?php 
                                    endif;
                                    $rowCount++;
                                endwhile; 
                                ?>
                            }
                            
                            // Save the PDF
                            pdf.save('case-report-<?php echo $selectedYear; ?>.pdf');
                            
                            // Remove loading indicator
                            document.body.removeChild(loadingIndicator);
                        });
                        <?php else: ?>
                        // Save the PDF
                        pdf.save('case-report-<?php echo $selectedYear; ?>.pdf');
                        
                        // Remove loading indicator
                        document.body.removeChild(loadingIndicator);
                        <?php endif; ?>
                    });
                });
            });
        }
    });
});
</script>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    #reportContent, #reportContent * {
        visibility: visible;
    }
    #reportContent {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }
    .no-print {
        display: none !important;
    }
}
</style>

<?php
// Include footer
include_once '../includes/footer.php';
?>
