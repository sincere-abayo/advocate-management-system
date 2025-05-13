<?php
// Set page title
$pageTitle = "Financial Reports";

// Include header
include_once '../includes/header.php';

// Get advocate ID
$advocateId = $_SESSION['advocate_id'];

// Get database connection
$conn = getDBConnection();

// Initialize filters
$currentYear = date('Y');
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : $currentYear;
$selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : 0; // 0 means all months
$selectedCase = isset($_GET['case_id']) ? (int)$_GET['case_id'] : 0; // 0 means all cases

// Get available years for filter (last 5 years)
$years = [];
for ($i = $currentYear; $i >= $currentYear - 4; $i--) {
    $years[] = $i;
}

// Get all cases for filter dropdown
$casesQuery = "
    SELECT c.case_id, c.case_number, c.title 
    FROM cases c
    JOIN case_assignments ca ON c.case_id = ca.case_id
    WHERE ca.advocate_id = ?
    ORDER BY c.filing_date DESC
";
$casesStmt = $conn->prepare($casesQuery);
$casesStmt->bind_param("i", $advocateId);
$casesStmt->execute();
$casesResult = $casesStmt->get_result();
$cases = [];
while ($case = $casesResult->fetch_assoc()) {
    $cases[] = $case;
}
// Build WHERE clause based on filters
$whereClauseExpense = "advocate_id = ?";
$whereClauseIncome = "advocate_id = ?";
$params = [$advocateId];
$types = "i";

if ($selectedYear > 0) {
    $whereClauseExpense .= " AND YEAR(expense_date) = ?";
    $whereClauseIncome .= " AND YEAR(income_date) = ?";
    $params[] = $selectedYear;
    $types .= "i";
}

if ($selectedMonth > 0) {
    $whereClauseExpense .= " AND MONTH(expense_date) = ?";
    $whereClauseIncome .= " AND MONTH(income_date) = ?";
    $params[] = $selectedMonth;
    $types .= "i";
}

if ($selectedCase > 0) {
    $whereClauseExpense .= " AND case_id = ?";
    $whereClauseIncome .= " AND case_id = ?";
    $params[] = $selectedCase;
    $types .= "i";
}

// Get income data
$incomeQuery = "
    SELECT 
        COALESCE(SUM(amount), 0) as total_income,
        COALESCE(SUM(CASE WHEN case_id IS NOT NULL THEN amount ELSE 0 END), 0) as case_income,
        COALESCE(SUM(CASE WHEN case_id IS NULL THEN amount ELSE 0 END), 0) as other_income
    FROM case_income
    WHERE $whereClauseIncome
";
$incomeStmt = $conn->prepare($incomeQuery);
$incomeStmt->bind_param($types, ...$params);
$incomeStmt->execute();
$incomeData = $incomeStmt->get_result()->fetch_assoc();

// Get expense data
$expenseQuery = "
    SELECT 
        COALESCE(SUM(amount), 0) as total_expenses,
        COALESCE(SUM(CASE WHEN case_id IS NOT NULL THEN amount ELSE 0 END), 0) as case_expenses,
        COALESCE(SUM(CASE WHEN case_id IS NULL THEN amount ELSE 0 END), 0) as other_expenses
    FROM case_expenses
    WHERE $whereClauseExpense
";
$expenseStmt = $conn->prepare($expenseQuery);
$expenseStmt->bind_param($types, ...$params);
$expenseStmt->execute();
$expenseData = $expenseStmt->get_result()->fetch_assoc();


// Calculate profit
$totalIncome = $incomeData['total_income'];
$totalExpenses = $expenseData['total_expenses'];
$netProfit = $totalIncome - $totalExpenses;
$profitMargin = $totalIncome > 0 ? ($netProfit / $totalIncome) * 100 : 0;

// Get monthly income and expense data for charts
$monthlyDataQuery = "
    SELECT 
        MONTH(expense_date) as month,
        COALESCE(SUM(amount), 0) as expenses
    FROM case_expenses
    WHERE advocate_id = ? AND YEAR(expense_date) = ?
    GROUP BY MONTH(expense_date)
    ORDER BY MONTH(expense_date)
";
$monthlyDataStmt = $conn->prepare($monthlyDataQuery);
$monthlyDataStmt->bind_param("ii", $advocateId, $selectedYear);
$monthlyDataStmt->execute();
$expensesByMonth = $monthlyDataStmt->get_result();

$monthlyExpenses = array_fill(1, 12, 0); // Initialize with zeros for all months
while ($row = $expensesByMonth->fetch_assoc()) {
    $monthlyExpenses[$row['month']] = (float)$row['expenses'];
}

// Get monthly income
$monthlyIncomeQuery = "
    SELECT 
        MONTH(income_date) as month,
        COALESCE(SUM(amount), 0) as income
    FROM case_income
    WHERE advocate_id = ? AND YEAR(income_date) = ?
    GROUP BY MONTH(income_date)
    ORDER BY MONTH(income_date)
";
$monthlyIncomeStmt = $conn->prepare($monthlyIncomeQuery);
$monthlyIncomeStmt->bind_param("ii", $advocateId, $selectedYear);
$monthlyIncomeStmt->execute();
$incomeByMonth = $monthlyIncomeStmt->get_result();

$monthlyIncome = array_fill(1, 12, 0); // Initialize with zeros for all months
while ($row = $incomeByMonth->fetch_assoc()) {
    $monthlyIncome[$row['month']] = (float)$row['income'];
}

// Calculate monthly profit
$monthlyProfit = [];
for ($i = 1; $i <= 12; $i++) {
    $monthlyProfit[$i] = $monthlyIncome[$i] - $monthlyExpenses[$i];
}

// Get expense categories breakdown
$categoryQuery = "
    SELECT 
        COALESCE(expense_category, 'Uncategorized') as category,
        COALESCE(SUM(amount), 0) as total
    FROM case_expenses
    WHERE $whereClauseExpense
    GROUP BY expense_category
    ORDER BY total DESC
";
$categoryStmt = $conn->prepare($categoryQuery);
$categoryStmt->bind_param($types, ...$params);
$categoryStmt->execute();
$categoriesResult = $categoryStmt->get_result();

$categories = [];
$categoryAmounts = [];
while ($category = $categoriesResult->fetch_assoc()) {
    $categories[] = $category['category'];
    $categoryAmounts[] = (float)$category['total'];
}


// Get case financial performance
$casePerformanceQuery = "
    SELECT 
        c.case_id,
        c.case_number,
        c.title,
        COALESCE(c.total_income, 0) as income,
        COALESCE(c.total_expenses, 0) as expenses,
        COALESCE(c.profit, 0) as profit,
        CASE WHEN c.total_income > 0 THEN (c.profit / c.total_income) * 100 ELSE 0 END as margin
    FROM cases c
    JOIN case_assignments ca ON c.case_id = ca.case_id
    WHERE ca.advocate_id = ?
    ORDER BY c.profit DESC
    LIMIT 10
";
$casePerformanceStmt = $conn->prepare($casePerformanceQuery);
$casePerformanceStmt->bind_param("i", $advocateId);
$casePerformanceStmt->execute();
$casePerformanceResult = $casePerformanceStmt->get_result();

// Get recent transactions
$recentTransactionsQuery = "
    (SELECT 
        'income' as type,
        ci.income_id as id,
        ci.amount,
        ci.income_date as transaction_date,
        ci.description,
        c.case_number,
        c.case_id
    FROM case_income ci
    LEFT JOIN cases c ON ci.case_id = c.case_id
    WHERE ci.advocate_id = ?
    ORDER BY ci.income_date DESC
    LIMIT 5)
    
    UNION ALL
    
    (SELECT 
        'expense' as type,
        ce.expense_id as id,
        ce.amount,
        ce.expense_date as transaction_date,
        ce.description,
        c.case_number,
        c.case_id
    FROM case_expenses ce
    LEFT JOIN cases c ON ce.case_id = c.case_id
    WHERE ce.advocate_id = ?
    ORDER BY ce.expense_date DESC
    LIMIT 5)
    
    ORDER BY transaction_date DESC
    LIMIT 10
";
$recentTransactionsStmt = $conn->prepare($recentTransactionsQuery);
$recentTransactionsStmt->bind_param("ii", $advocateId, $advocateId);
$recentTransactionsStmt->execute();
$recentTransactionsResult = $recentTransactionsStmt->get_result();

// Close database connection
$conn->close();
?>

<div class="mb-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Financial Reports</h1>
            <p class="text-gray-600">Analyze your financial performance and generate reports</p>
        </div>
        
        <div class="mt-4 md:mt-0 flex space-x-2">
            <button id="printReport" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-print mr-2"></i> Print Report
            </button>
            <button id="downloadPdf" class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-file-pdf mr-2"></i> Download PDF
            </button>
        </div>
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
            <label for="month" class="block text-sm font-medium text-gray-700 mb-1">Month</label>
            <select id="month" name="month" class="form-select w-full">
                <option value="0" <?php echo $selectedMonth == 0 ? 'selected' : ''; ?>>All Months</option>
                <?php for ($i = 1; $i <= 12; $i++): ?>
                    <option value="<?php echo $i; ?>" <?php echo $selectedMonth == $i ? 'selected' : ''; ?>>
                        <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>
        
        <div>
            <label for="case_id" class="block text-sm font-medium text-gray-700 mb-1">Case</label>
            <select id="case_id" name="case_id" class="form-select w-full">
                <option value="0" <?php echo $selectedCase == 0 ? 'selected' : ''; ?>>All Cases</option>
                <?php foreach ($cases as $case): ?>
                    <option value="<?php echo $case['case_id']; ?>" <?php echo $selectedCase == $case['case_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($case['case_number'] . ' - ' . $case['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="md:col-span-3">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg">
                <i class="fas fa-filter mr-2"></i> Apply Filters
            </button>
            <a href="reports.php" class="ml-2 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-times mr-2"></i> Clear Filters
            </a>
        </div>
    </form>
</div>

<div id="reportContent">
    <!-- Financial Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-500 mr-4">
                    <i class="fas fa-money-bill-wave text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Total Income</p>
                    <p class="text-2xl font-semibold"><?php echo formatCurrency($totalIncome); ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-red-100 text-red-500 mr-4">
                    <i class="fas fa-file-invoice-dollar text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Total Expenses</p>
                    <p class="text-2xl font-semibold"><?php echo formatCurrency($totalExpenses); ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-500 mr-4">
                    <i class="fas fa-chart-line text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Net Profit</p>
                    <p class="text-2xl font-semibold"><?php echo formatCurrency($netProfit); ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-100 text-purple-500 mr-4">
                    <i class="fas fa-percentage text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Profit Margin</p>
                    <p class="text-2xl font-semibold"><?php echo number_format($profitMargin, 1); ?>%</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Income vs Expenses Chart -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Monthly Income vs Expenses (<?php echo $selectedYear; ?>)</h2>
            <div class="h-80">
                <canvas id="monthlyComparisonChart"></canvas>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Expense Categories</h2>
            <div class="h-80">
                <canvas id="expenseCategoriesChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Monthly Profit Chart and Income Breakdown -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Monthly Profit (<?php echo $selectedYear; ?>)</h2>
            <div class="h-80">
                <canvas id="monthlyProfitChart"></canvas>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Income Breakdown</h2>
            <div class="h-80">
                <canvas id="incomeBreakdownChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Case Performance Table -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Top Performing Cases</h2>
        <?php if ($casePerformanceResult->num_rows > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Case</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Income</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expenses</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Profit</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Margin</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while ($case = $casePerformanceResult->fetch_assoc()): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <a href="<?php echo $path_url; ?>advocate/cases/view.php?id=<?php echo $case['case_id']; ?>" class="text-blue-600 hover:text-blue-900">
                                        <?php echo htmlspecialchars($case['case_number'] . ' - ' . $case['title']); ?>
                                    </a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo formatCurrency($case['income']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo formatCurrency($case['expenses']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap font-medium <?php echo $case['profit'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                    <?php echo formatCurrency($case['profit']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <span class="<?php echo $case['margin'] >= 0 ? 'text-green-600' : 'text-red-600'; ?> font-medium">
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
        <?php else: ?>
            <div class="text-center py-8 bg-gray-50 rounded-lg">
                <div class="text-gray-400 mb-3"><i class="fas fa-chart-bar text-5xl"></i></div>
                <h3 class="text-lg font-medium text-gray-900 mb-1">No case data available</h3>
                <p class="text-gray-500">Add income and expenses to your cases to see performance metrics</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Recent Transactions -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Recent Transactions</h2>
        <?php if ($recentTransactionsResult->num_rows > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Case</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while ($transaction = $recentTransactionsResult->fetch_assoc()): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php echo date('M d, Y', strtotime($transaction['transaction_date'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($transaction['type'] == 'income'): ?>
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Income
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                            Expense
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap font-medium <?php echo $transaction['type'] == 'income' ? 'text-green-600' : 'text-red-600'; ?>">
                                    <?php echo $transaction['type'] == 'income' ? '+' : '-'; ?><?php echo formatCurrency($transaction['amount']); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php echo htmlspecialchars($transaction['description']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($transaction['case_id']): ?>
                                        <a href="<?php echo $path_url; ?>advocate/cases/view.php?id=<?php echo $transaction['case_id']; ?>" class="text-blue-600 hover:text-blue-900">
                                            <?php echo htmlspecialchars($transaction['case_number']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-500">N/A</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-8 bg-gray-50 rounded-lg">
                <div class="text-gray-400 mb-3"><i class="fas fa-exchange-alt text-5xl"></i></div>
                <h3 class="text-lg font-medium text-gray-900 mb-1">No recent transactions</h3>
                <p class="text-gray-500">Add income and expenses to see your recent transactions</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Monthly Data Table -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">Monthly Financial Data (<?php echo $selectedYear; ?>)</h2>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Month</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Income</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expenses</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Profit</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Margin</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php 
                $yearlyTotalIncome = 0;
                $yearlyTotalExpenses = 0;
                $yearlyTotalProfit = 0;
                
                for ($i = 1; $i <= 12; $i++): 
                    $monthIncome = $monthlyIncome[$i];
                    $monthExpenses = $monthlyExpenses[$i];
                    $monthProfit = $monthlyProfit[$i];
                    $monthMargin = $monthIncome > 0 ? ($monthProfit / $monthIncome) * 100 : 0;
                    
                    $yearlyTotalIncome += $monthIncome;
                    $yearlyTotalExpenses += $monthExpenses;
                    $yearlyTotalProfit += $monthProfit;
                ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">
                            <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php echo formatCurrency($monthIncome); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php echo formatCurrency($monthExpenses); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap font-medium <?php echo $monthProfit >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo formatCurrency($monthProfit); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <span class="<?php echo $monthMargin >= 0 ? 'text-green-600' : 'text-red-600'; ?> font-medium">
                                    <?php echo number_format($monthMargin, 1); ?>%
                                </span>
                                <div class="ml-2 w-16 bg-gray-200 rounded-full h-2">
                                    <div class="<?php echo $monthMargin >= 0 ? 'bg-green-500' : 'bg-red-500'; ?> h-2 rounded-full" style="width: <?php echo min(abs($monthMargin), 100); ?>%"></div>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endfor; ?>

    
    <!-- Expense Categories Breakdown -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Expense Categories Breakdown</h2>
        <?php if (count($categories) > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Percentage</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Distribution</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php 
                        foreach ($categories as $index => $category): 
                            $amount = $categoryAmounts[$index];
                            $percentage = $totalExpenses > 0 ? ($amount / $totalExpenses) * 100 : 0;
                        ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">
                                    <?php echo htmlspecialchars($category); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php echo formatCurrency($amount); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php echo number_format($percentage, 1); ?>%
                                </td>
                                <td class="px-6 py-4">
                                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                                        <div class="bg-blue-600 h-2.5 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-8 bg-gray-50 rounded-lg">
                <div class="text-gray-400 mb-3"><i class="fas fa-tags text-5xl"></i></div>
                <h3 class="text-lg font-medium text-gray-900 mb-1">No expense categories data</h3>
                <p class="text-gray-500">Add categorized expenses to see the breakdown</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
<!-- jsPDF Library for PDF export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Chart color settings
    const chartColors = {
        income: 'rgba(16, 185, 129, 0.7)',     // Green
        incomeLight: 'rgba(16, 185, 129, 0.2)',
        expenses: 'rgba(239, 68, 68, 0.7)',     // Red
        expensesLight: 'rgba(239, 68, 68, 0.2)',
        profit: 'rgba(59, 130, 246, 0.7)',      // Blue
        profitLight: 'rgba(59, 130, 246, 0.2)',
        other: 'rgba(139, 92, 246, 0.7)',       // Purple
        otherLight: 'rgba(139, 92, 246, 0.2)'
    };
    
    // Monthly Income vs Expenses Chart
    const monthlyComparisonCtx = document.getElementById('monthlyComparisonChart').getContext('2d');
    const monthlyComparisonChart = new Chart(monthlyComparisonCtx, {
        type: 'bar',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            datasets: [
                {
                    label: 'Income',
                    data: [
                        <?php echo implode(', ', array_values($monthlyIncome)); ?>
                    ],
                    backgroundColor: chartColors.income,
                    borderColor: chartColors.income,
                    borderWidth: 1
                },
                {
                    label: 'Expenses',
                    data: [
                        <?php echo implode(', ', array_values($monthlyExpenses)); ?>
                    ],
                    backgroundColor: chartColors.expenses,
                    borderColor: chartColors.expenses,
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toLocaleString();
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': $' + context.raw.toLocaleString();
                        }
                    }
                }
            }
        }
    });
    
    // Expense Categories Chart
    const expenseCategoriesCtx = document.getElementById('expenseCategoriesChart').getContext('2d');
    const expenseCategoriesChart = new Chart(expenseCategoriesCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($categories); ?>,
            datasets: [{
                data: <?php echo json_encode($categoryAmounts); ?>,
                backgroundColor: [
                    'rgba(59, 130, 246, 0.7)',   // Blue
                    'rgba(16, 185, 129, 0.7)',   // Green
                    'rgba(239, 68, 68, 0.7)',    // Red
                    'rgba(245, 158, 11, 0.7)',   // Yellow
                    'rgba(139, 92, 246, 0.7)',   // Purple
                    'rgba(236, 72, 153, 0.7)',   // Pink
                    'rgba(6, 182, 212, 0.7)',    // Cyan
                    'rgba(249, 115, 22, 0.7)',   // Orange
                    'rgba(75, 85, 99, 0.7)',     // Gray
                    'rgba(16, 185, 129, 0.5)'    // Light Green
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return label + ': $' + value.toLocaleString() + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
    
    // Monthly Profit Chart
    const monthlyProfitCtx = document.getElementById('monthlyProfitChart').getContext('2d');
    const monthlyProfitChart = new Chart(monthlyProfitCtx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            datasets: [{
                label: 'Profit',
                data: [
                    <?php echo implode(', ', array_values($monthlyProfit)); ?>
                ],
                backgroundColor: chartColors.profitLight,
                borderColor: chartColors.profit,
                borderWidth: 2,
                fill: true,
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toLocaleString();
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': $' + context.raw.toLocaleString();
                        }
                    }
                }
            }
        }
    });
    
    // Income Breakdown Chart
    const incomeBreakdownCtx = document.getElementById('incomeBreakdownChart').getContext('2d');
    const incomeBreakdownChart = new Chart(incomeBreakdownCtx, {
        type: 'pie',
        data: {
            labels: ['Case-Related Income', 'Other Income'],
            datasets: [{
                data: [
                    <?php echo $incomeData['case_income']; ?>,
                    <?php echo $incomeData['other_income']; ?>
                ],
                backgroundColor: [
                    chartColors.income,
                    chartColors.other
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return label + ': $' + value.toLocaleString() + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
    
    // Print Report
    document.getElementById('printReport').addEventListener('click', function() {
        window.print();
    });
    
    // Download PDF
    document.getElementById('downloadPdf').addEventListener('click', function() {
        const { jsPDF } = window.jspdf;
        
        // Create loading indicator
        const loadingEl = document.createElement('div');
        loadingEl.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
        loadingEl.innerHTML = `
            <div class="bg-white p-6 rounded-lg shadow-lg text-center">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
                <p class="text-lg font-semibold">Generating PDF...</p>
                <p class="text-sm text-gray-600">This may take a few moments</p>
            </div>
        `;
        document.body.appendChild(loadingEl);
        
        // Allow the loading indicator to render
        setTimeout(function() {
            const reportContent = document.getElementById('reportContent');
            const pdf = new jsPDF('p', 'pt', 'a4');
            
            // PDF title
            pdf.setFontSize(18);
            pdf.text('Financial Report', 40, 40);
            
            // Add filter information
            pdf.setFontSize(12);
            pdf.text(`Year: ${<?php echo $selectedYear; ?>}`, 40, 70);
            pdf.text(`Month: ${<?php echo $selectedMonth == 0 ? "'All Months'" : "'" . date('F', mktime(0, 0, 0, $selectedMonth, 1)) . "'"; ?>}`, 40, 90);
            pdf.text(`Generated on: ${new Date().toLocaleDateString()}`, 40, 110);
            
            // Add summary data
            pdf.setFontSize(14);
            pdf.text('Financial Summary', 40, 140);
            pdf.setFontSize(10);
            pdf.text(`Total Income: $${<?php echo $totalIncome; ?>}`, 40, 160);
            pdf.text(`Total Expenses: $${<?php echo $totalExpenses; ?>}`, 40, 180);
            pdf.text(`Net Profit: $${<?php echo $netProfit; ?>}`, 40, 200);
            pdf.text(`Profit Margin: ${<?php echo number_format($profitMargin, 1); ?>}%`, 40, 220);
            
            // Use html2canvas to capture the charts and tables
            const captureElement = async function(element, pdfX, pdfY, pdfWidth, pdfHeight) {
                const canvas = await html2canvas(element, {
                    scale: 2,
                    useCORS: true,
                    logging: false
                });
                
                const imgData = canvas.toDataURL('image/png');
                pdf.addImage(imgData, 'PNG', pdfX, pdfY, pdfWidth, pdfHeight);
            };
            
            // Capture each section of the report
            html2canvas(document.querySelector('.grid.grid-cols-1.md\\:grid-cols-2.lg\\:grid-cols-4'), {
                scale: 2,
                useCORS: true,
                logging: false
            }).then(canvas => {
                const imgData = canvas.toDataURL('image/png');
                pdf.addImage(imgData, 'PNG', 40, 240, 520, 100);
                
                // Add a new page for charts
                pdf.addPage();
                
                // Capture monthly comparison chart
                return html2canvas(document.getElementById('monthlyComparisonChart').parentNode, {
                    scale: 2,
                    useCORS: true,
                    logging: false
                });
            }).then(canvas => {
                const imgData = canvas.toDataURL('image/png');
                pdf.addImage(imgData, 'PNG', 40, 40, 520, 250);
                
                // Capture expense categories chart
                return html2canvas(document.getElementById('expenseCategoriesChart').parentNode, {
                    scale: 2,
                    useCORS: true,
                    logging: false
                });
            }).then(canvas => {
                const imgData = canvas.toDataURL('image/png');
                pdf.addImage(imgData, 'PNG', 40, 300, 520, 250);
                
                // Add a new page for more charts
                pdf.addPage();
                
                // Capture monthly profit chart
                return html2canvas(document.getElementById('monthlyProfitChart').parentNode, {
                    scale: 2,
                    useCORS: true,
                    logging: false
                });
            }).then(canvas => {
                const imgData = canvas.toDataURL('image/png');
                pdf.addImage(imgData, 'PNG', 40, 40, 520, 250);
                
                // Capture income breakdown chart
                return html2canvas(document.getElementById('incomeBreakdownChart').parentNode, {
                    scale: 2,
                    useCORS: true,
                    logging: false
                });
            }).then(canvas => {
                const imgData = canvas.toDataURL('image/png');
                pdf.addImage(imgData, 'PNG', 40, 300, 520, 250);
                
                // Add a new page for tables
                pdf.addPage();
                
                // Add monthly data table title
                pdf.setFontSize(14);
                pdf.text('Monthly Financial Data', 40, 40);
                
                // Create table data for monthly financials
                const monthlyData = [];
                monthlyData.push(['Month', 'Income', 'Expenses', 'Profit', 'Margin']);
                
                <?php for ($i = 1; $i <= 12; $i++): ?>
                monthlyData.push([
                    '<?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>',
                    '$<?php echo number_format($monthlyIncome[$i], 2); ?>',
                    '$<?php echo number_format($monthlyExpenses[$i], 2); ?>',
                    '$<?php echo number_format($monthlyProfit[$i], 2); ?>',
                    '<?php echo number_format(($monthlyIncome[$i] > 0 ? ($monthlyProfit[$i] / $monthlyIncome[$i]) * 100 : 0), 1); ?>%'
                ]);
                <?php endfor; ?>
                
                // Add monthly data table
                pdf.autoTable({
                    startY: 60,
                    head: [monthlyData[0]],
                    body: monthlyData.slice(1),
                    theme: 'grid',
                    styles: {
                        fontSize: 8
                    },
                    headStyles: {
                        fillColor: [59, 130, 246],
                        textColor: 255
                    },
                    alternateRowStyles: {
                        fillColor: [240, 240, 240]
                    }
                });
                
                // Save the PDF
                pdf.save('Financial_Report_<?php echo $selectedYear; ?><?php echo $selectedMonth > 0 ? "_" . date("M", mktime(0, 0, 0, $selectedMonth, 1)) : ""; ?>.pdf');
                
                // Remove loading indicator
                document.body.removeChild(loadingEl);
            }).catch(error => {
                console.error('Error generating PDF:', error);
                alert('There was an error generating the PDF. Please try again.');
                document.body.removeChild(loadingEl);
            });
        }, 100);
    });
});
</script>

<style>
/* Print styles */
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
