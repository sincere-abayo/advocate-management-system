<?php

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

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
$pageTitle = "Financial Reports";

// Include header
include_once '../includes/header.php';

// Get database connection
$conn = getDBConnection();

// Initialize filters
$currentYear = date('Y');
$selectedYear = isset($_GET['year']) ? (int) $_GET['year'] : $currentYear;
$selectedMonth = isset($_GET['month']) ? (int) $_GET['month'] : 0;
$selectedCategory = isset($_GET['category']) ? $_GET['category'] : '';
$selectedPaymentMethod = isset($_GET['payment_method']) ? $_GET['payment_method'] : '';
$dateRange = isset($_GET['date_range']) ? $_GET['date_range'] : 'year';

// Get available years for filter (last 5 years)
$years = [];
for ($i = $currentYear; $i >= $currentYear - 4; $i--) {
    $years[] = $i;
}

// Get income categories for filter
$incomeCategoriesQuery = "
    SELECT DISTINCT income_category 
    FROM case_income
    WHERE advocate_id = ? AND income_category IS NOT NULL
    UNION
    SELECT DISTINCT income_category 
    FROM advocate_other_income
    WHERE advocate_id = ? AND income_category IS NOT NULL
    ORDER BY income_category
";
$incomeCategoriesStmt = $conn->prepare($incomeCategoriesQuery);
$incomeCategoriesStmt->bind_param("ii", $advocateId, $advocateId);
$incomeCategoriesStmt->execute();
$incomeCategoriesResult = $incomeCategoriesStmt->get_result();

$incomeCategories = [];
while ($category = $incomeCategoriesResult->fetch_assoc()) {
    $incomeCategories[] = $category['income_category'];
}

// Get expense categories for filter
$expenseCategoriesQuery = "
    SELECT DISTINCT expense_category 
    FROM case_expenses
    WHERE advocate_id = ? AND expense_category IS NOT NULL
    ORDER BY expense_category
";
$expenseCategoriesStmt = $conn->prepare($expenseCategoriesQuery);
$expenseCategoriesStmt->bind_param("i", $advocateId);
$expenseCategoriesStmt->execute();
$expenseCategoriesResult = $expenseCategoriesStmt->get_result();

$expenseCategories = [];
while ($category = $expenseCategoriesResult->fetch_assoc()) {
    $expenseCategories[] = $category['expense_category'];
}

// Get payment methods for filter
$paymentMethodsQuery = "
    SELECT DISTINCT payment_method
    FROM billings
    WHERE advocate_id = ?
    AND payment_method IS NOT NULL 
    AND payment_method != ''
    AND status = 'paid'
    ORDER BY payment_method
";
$paymentMethodsStmt = $conn->prepare($paymentMethodsQuery);
$paymentMethodsStmt->bind_param("i", $advocateId);
$paymentMethodsStmt->execute();
$paymentMethodsResult = $paymentMethodsStmt->get_result();

$paymentMethods = [];
while ($method = $paymentMethodsResult->fetch_assoc()) {
    $paymentMethods[] = $method['payment_method'];
}

// Build date filter based on selection
$startDate = '';
$endDate = '';

if ($dateRange === 'year' && $selectedYear > 0) {
    $startDate = $selectedYear . '-01-01';
    $endDate = $selectedYear . '-12-31';
} elseif ($dateRange === 'month' && $selectedYear > 0 && $selectedMonth > 0) {
    $startDate = $selectedYear . '-' . str_pad($selectedMonth, 2, '0', STR_PAD_LEFT) . '-01';
    $endDate = date('Y-m-t', strtotime($startDate));
} elseif ($dateRange === 'custom' && isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $startDate = $_GET['start_date'];
    $endDate = $_GET['end_date'];
} else {
    // Default to current year
    $startDate = $currentYear . '-01-01';
    $endDate = $currentYear . '-12-31';
}

// Get financial statistics
$incomeQuery = "
    SELECT 
        COALESCE(SUM(b.amount), 0) as total_income,
        COALESCE(SUM(CASE WHEN b.payment_method IS NOT NULL AND b.payment_method != '' THEN b.amount ELSE 0 END), 0) as income_with_payment_method
    FROM billings b
    WHERE b.advocate_id = ? 
    AND b.status = 'paid' 
    AND b.payment_date IS NOT NULL
    AND YEAR(b.payment_date) = ?
";
$incomeStmt = $conn->prepare($incomeQuery);
$incomeStmt->bind_param("ii", $advocateId, $selectedYear);
$incomeStmt->execute();
$incomeData = $incomeStmt->get_result()->fetch_assoc();
$totalIncome = $incomeData['total_income'];
$incomeWithPaymentMethod = $incomeData['income_with_payment_method'];

// Get expenses with payment method
$expensesQuery = "
    SELECT 
        COALESCE(SUM(amount), 0) as total_expenses,
        COALESCE(SUM(CASE WHEN expense_category IS NOT NULL AND expense_category != '' THEN amount ELSE 0 END), 0) as expenses_with_category
    FROM case_expenses 
    WHERE advocate_id = ? 
    AND YEAR(expense_date) = ?
";
$expensesStmt = $conn->prepare($expensesQuery);
$expensesStmt->bind_param("ii", $advocateId, $selectedYear);
$expensesStmt->execute();
$expensesData = $expensesStmt->get_result()->fetch_assoc();
$totalExpenses = $expensesData['total_expenses'];
$expensesWithCategory = $expensesData['expenses_with_category'];

// Get invoice counts with payment method status
$invoicesQuery = "
    SELECT 
        COUNT(*) as total_invoices,
        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_invoices,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_invoices,
        SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue_invoices,
        SUM(CASE WHEN payment_method IS NOT NULL AND payment_method != '' THEN 1 ELSE 0 END) as invoices_with_payment_method
    FROM billings 
    WHERE advocate_id = ? 
    AND YEAR(billing_date) = ?
";
$invoicesStmt = $conn->prepare($invoicesQuery);
$invoicesStmt->bind_param("ii", $advocateId, $selectedYear);
$invoicesStmt->execute();
$invoicesData = $invoicesStmt->get_result()->fetch_assoc();

// Calculate additional metrics
$netProfit = $totalIncome - $totalExpenses;
$monthlyAvgIncome = $totalIncome / 12;
$profitMargin = $totalIncome > 0 ? ($netProfit / $totalIncome) * 100 : 0;
$collectionRate = $invoicesData['total_invoices'] > 0 ? ($invoicesData['paid_invoices'] / $invoicesData['total_invoices']) * 100 : 0;

// Store all financial stats in one array for easy access
$financialStats = array_merge(
    [
        'total_income' => $totalIncome,
        'income_with_payment_method' => $incomeWithPaymentMethod,
        'total_expenses' => $totalExpenses,
        'expenses_with_category' => $expensesWithCategory,
        'net_profit' => $netProfit,
        'monthly_avg_income' => $monthlyAvgIncome,
        'profit_margin' => $profitMargin
    ],
    $invoicesData
);

// Get payment method distribution
$paymentMethodsQuery = "
    SELECT 
        COALESCE(NULLIF(payment_method, ''), 'Not Specified') as payment_method,
        COUNT(*) as count,
        SUM(amount) as total_amount
    FROM billings
    WHERE advocate_id = ? 
    AND YEAR(billing_date) = ?
    AND status = 'paid'
    GROUP BY COALESCE(NULLIF(payment_method, ''), 'Not Specified')
    ORDER BY total_amount DESC
";
$paymentMethodsStmt = $conn->prepare($paymentMethodsQuery);
$paymentMethodsStmt->bind_param("ii", $advocateId, $selectedYear);
$paymentMethodsStmt->execute();
$paymentMethodsResult = $paymentMethodsStmt->get_result();
$paymentMethods = [];
while ($row = $paymentMethodsResult->fetch_assoc()) {
    $paymentMethods[] = $row;
}

// Get monthly income data
$monthlyIncomeQuery = "
    SELECT 
        MONTH(payment_date) as month,
        SUM(amount) as total_amount
    FROM billings 
    WHERE advocate_id = ? 
    AND status = 'paid' 
    AND YEAR(payment_date) = ?
    GROUP BY MONTH(payment_date)
    ORDER BY MONTH(payment_date)
";

$monthlyIncomeStmt = $conn->prepare($monthlyIncomeQuery);
$monthlyIncomeStmt->bind_param("ii", $advocateId, $selectedYear);
$monthlyIncomeStmt->execute();
$monthlyIncomeResult = $monthlyIncomeStmt->get_result();

$monthlyIncome = array_fill(1, 12, 0);
while ($row = $monthlyIncomeResult->fetch_assoc()) {
    $monthlyIncome[$row['month']] = (float) $row['total_amount'];
}

// Get monthly expenses data
$monthlyExpensesQuery = "
    SELECT 
        MONTH(expense_date) as month,
        SUM(amount) as total_amount
    FROM case_expenses 
    WHERE advocate_id = ? 
    AND YEAR(expense_date) = ?
    GROUP BY MONTH(expense_date)
    ORDER BY MONTH(expense_date)
";

$monthlyExpensesStmt = $conn->prepare($monthlyExpensesQuery);
$monthlyExpensesStmt->bind_param("ii", $advocateId, $selectedYear);
$monthlyExpensesStmt->execute();
$monthlyExpensesResult = $monthlyExpensesStmt->get_result();

$monthlyExpenses = array_fill(1, 12, 0);
while ($row = $monthlyExpensesResult->fetch_assoc()) {
    $monthlyExpenses[$row['month']] = (float) $row['total_amount'];
}

// Get income by case type
$incomeByCaseTypeQuery = "
    SELECT 
        c.case_type,
        SUM(b.amount) as total_amount
    FROM billings b
    JOIN cases c ON b.case_id = c.case_id
    WHERE b.advocate_id = ? 
    AND b.status = 'paid' 
    AND YEAR(b.payment_date) = ?
    GROUP BY c.case_type
    ORDER BY total_amount DESC
";

$incomeByCaseTypeStmt = $conn->prepare($incomeByCaseTypeQuery);
$incomeByCaseTypeStmt->bind_param("ii", $advocateId, $selectedYear);
$incomeByCaseTypeStmt->execute();
$incomeByCaseTypeResult = $incomeByCaseTypeStmt->get_result();

$caseTypeLabels = [];
$caseTypeAmounts = [];
while ($row = $incomeByCaseTypeResult->fetch_assoc()) {
    $caseTypeLabels[] = $row['case_type'];
    $caseTypeAmounts[] = (float) $row['total_amount'];
}

// Get recent income
$recentIncomeQuery = "
    SELECT 
        b.billing_id,
        b.case_id,
        c.case_number,
        c.title as case_title,
        b.amount,
        b.payment_method,
        b.payment_date,
        u.full_name as client_name
    FROM billings b
    LEFT JOIN cases c ON b.case_id = c.case_id
    LEFT JOIN client_profiles cp ON b.client_id = cp.client_id
    LEFT JOIN users u ON cp.user_id = u.user_id
    WHERE b.advocate_id = ? 
    AND b.status = 'paid' 
    AND YEAR(b.payment_date) = ?
    ORDER BY b.payment_date DESC
    LIMIT 10
";

$recentIncomeStmt = $conn->prepare($recentIncomeQuery);
$recentIncomeStmt->bind_param("ii", $advocateId, $selectedYear);
$recentIncomeStmt->execute();
$recentIncomeResult = $recentIncomeStmt->get_result();

// Get recent expenses
$recentExpensesQuery = "
    SELECT 
        ce.expense_id,
        ce.case_id,
        c.case_number,
        c.title as case_title,
        ce.expense_date,
        ce.amount,
        ce.description,
        ce.expense_category,
        ce.receipt_file
    FROM case_expenses ce
    LEFT JOIN cases c ON ce.case_id = c.case_id
    WHERE ce.advocate_id = ? 
    AND YEAR(ce.expense_date) = ?
    ORDER BY ce.expense_date DESC
    LIMIT 10
";

$recentExpensesStmt = $conn->prepare($recentExpensesQuery);
$recentExpensesStmt->bind_param("ii", $advocateId, $selectedYear);
$recentExpensesStmt->execute();
$recentExpensesResult = $recentExpensesStmt->get_result();

// Get pending payments
$pendingPaymentsQuery = "
    SELECT 
        b.billing_id,
        b.case_id,
        c.case_number,
        c.title as case_title,
        b.amount,
        b.billing_date,
        b.due_date,
        b.status,
        u.full_name as client_name
    FROM billings b
    LEFT JOIN cases c ON b.case_id = c.case_id
    LEFT JOIN client_profiles cp ON b.client_id = cp.client_id
    LEFT JOIN users u ON cp.user_id = u.user_id
    WHERE b.advocate_id = ? 
    AND b.status IN ('pending', 'overdue')
    AND YEAR(b.billing_date) = ?
    ORDER BY b.due_date ASC
    LIMIT 10
";

$pendingPaymentsStmt = $conn->prepare($pendingPaymentsQuery);
$pendingPaymentsStmt->bind_param("ii", $advocateId, $selectedYear);
$pendingPaymentsStmt->execute();
$pendingPaymentsResult = $pendingPaymentsStmt->get_result();

// Close database connection
$conn->close();
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Financial Reports</h1>
            <p class="text-gray-600">Analyze your financial performance and trends</p>
        </div>

        <div class="mt-4 md:mt-0 flex space-x-2">
            <a href="../finance/index.php"
                class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back to Finance
            </a>
            <button id="printReport"
                class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-print mr-2"></i> Print Report
            </button>
            <button id="downloadPdf"
                class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-file-pdf mr-2"></i> Download PDF
            </button>
        </div>
    </div>

    <!-- Report Filters -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Report Filters</h2>
        <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label for="date_range" class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                <select id="date_range" name="date_range" class="form-select w-full" onchange="toggleDateFields()">
                    <option value="year" <?php echo $dateRange === 'year' ? 'selected' : ''; ?>>Yearly</option>
                    <option value="month" <?php echo $dateRange === 'month' ? 'selected' : ''; ?>>Monthly</option>
                    <option value="custom" <?php echo $dateRange === 'custom' ? 'selected' : ''; ?>>Custom Range
                    </option>
                </select>
            </div>

            <div id="year_filter">
                <label for="year" class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                <select id="year" name="year" class="form-select w-full">
                    <?php foreach ($years as $year): ?>
                        <option value="<?php echo $year; ?>" <?php echo $selectedYear == $year ? 'selected' : ''; ?>>
                            <?php echo $year; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="month_filter" style="<?php echo $dateRange !== 'month' ? 'display: none;' : ''; ?>">
                <label for="month" class="block text-sm font-medium text-gray-700 mb-1">Month</label>
                <select id="month" name="month" class="form-select w-full">
                    <option value="1" <?php echo $selectedMonth == 1 ? 'selected' : ''; ?>>January</option>
                    <option value="2" <?php echo $selectedMonth == 2 ? 'selected' : ''; ?>>February</option>
                    <option value="3" <?php echo $selectedMonth == 3 ? 'selected' : ''; ?>>March</option>
                    <option value="4" <?php echo $selectedMonth == 4 ? 'selected' : ''; ?>>April</option>
                    <option value="5" <?php echo $selectedMonth == 5 ? 'selected' : ''; ?>>May</option>
                    <option value="6" <?php echo $selectedMonth == 6 ? 'selected' : ''; ?>>June</option>
                    <option value="7" <?php echo $selectedMonth == 7 ? 'selected' : ''; ?>>July</option>
                    <option value="8" <?php echo $selectedMonth == 8 ? 'selected' : ''; ?>>August</option>
                    <option value="9" <?php echo $selectedMonth == 9 ? 'selected' : ''; ?>>September</option>
                    <option value="10" <?php echo $selectedMonth == 10 ? 'selected' : ''; ?>>October</option>
                    <option value="11" <?php echo $selectedMonth == 11 ? 'selected' : ''; ?>>November</option>
                    <option value="12" <?php echo $selectedMonth == 12 ? 'selected' : ''; ?>>December</option>
                </select>
            </div>

            <div id="custom_date_filter" class="grid grid-cols-2 gap-2"
                style="<?php echo $dateRange !== 'custom' ? 'display: none;' : ''; ?>">
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                    <input type="date" id="start_date" name="start_date" class="form-input w-full"
                        value="<?php echo $dateRange === 'custom' ? $startDate : ''; ?>">
                </div>
                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                    <input type="date" id="end_date" name="end_date" class="form-input w-full"
                        value="<?php echo $dateRange === 'custom' ? $endDate : ''; ?>">
                </div>
            </div>

            <div>
                <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                <select id="category" name="category" class="form-select w-full">
                    <option value="" <?php echo $selectedCategory === '' ? 'selected' : ''; ?>>All Categories</option>
                    <?php foreach (array_merge($incomeCategories, $expenseCategories) as $category): ?>
                        <?php if (!empty($category)): ?>
                            <option value="<?php echo $category; ?>" <?php echo $selectedCategory === $category ? 'selected' : ''; ?>>
                                <?php echo $category; ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                <select name="payment_method" class="border rounded px-2 py-1">
                    <option value="">All Payment Methods</option>
                    <?php while ($pm = $paymentMethodsResult->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($pm['payment_method']) ?>"
                            <?= $selectedPaymentMethod == $pm['payment_method'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($pm['payment_method']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="md:col-span-3">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg">
                    <i class="fas fa-filter mr-2"></i> Apply Filters
                </button>
                <a href="financial-reports.php"
                    class="ml-2 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg inline-flex items-center">
                    <i class="fas fa-times mr-2"></i> Clear Filters
                </a>
            </div>
        </form>
    </div>

    <div id="reportContent">
        <!-- Financial Summary -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Income Card -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center mb-2">
                    <span class="text-green-400 mr-2"><i class="fas fa-dollar-sign"></i></span>
                    <span class="text-gray-500">Total Income (<?= $selectedYear ?>)</span>
                </div>
                <div class="text-2xl font-bold">RWF <?= number_format($financialStats['total_income'], 2) ?></div>
                <!-- Monthly Average Progress Bar -->
                <div class="text-xs text-gray-500 mt-2">Monthly Average</div>
                <div class="w-full bg-gray-100 rounded-full h-2.5 mt-1">
                    <div class="bg-green-400 h-2.5 rounded-full"
                        style="width:<?= min(100, ($financialStats['monthly_avg_income'] > 0 ? ($financialStats['monthly_avg_income'] / max($financialStats['total_income'], 1)) * 100 : 0)) ?>%">
                    </div>
                </div>
                <div class="text-xs text-right text-gray-500 mt-1">RWF
                    <?= number_format($financialStats['monthly_avg_income'], 2) ?>
                </div>
                <!-- Payment Method Info -->
                <div class="text-xs text-gray-500 mt-2">
                    <?= $financialStats['income_with_payment_method'] > 0 ?
                        number_format(($financialStats['income_with_payment_method'] / max($financialStats['total_income'], 1)) * 100, 1) . '% with payment method' :
                        'No payment methods recorded' ?>
                </div>
            </div>

            <!-- Net Profit Card -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center mb-2">
                    <span class="text-indigo-400 mr-2"><i class="fas fa-coins"></i></span>
                    <span class="text-gray-500">Net Profit (<?= $selectedYear ?>)</span>
                </div>
                <div
                    class="text-2xl font-bold <?= $financialStats['net_profit'] >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                    RWF <?= number_format($financialStats['net_profit'], 2) ?>
                </div>
                <div class="text-xs text-gray-500 mt-2">Profit Margin</div>
                <div
                    class="text-sm font-medium <?= $financialStats['profit_margin'] >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                    <?= number_format($financialStats['profit_margin'], 1) ?>%
                </div>
            </div>

            <!-- Total Expenses Card -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center mb-2">
                    <span class="text-red-400 mr-2"><i class="fas fa-money-bill-wave"></i></span>
                    <span class="text-gray-500">Total Expenses (<?= $selectedYear ?>)</span>
                </div>
                <div class="text-2xl font-bold">RWF <?= number_format($financialStats['total_expenses'], 2) ?></div>
                <!-- Expense Category Info -->
                <div class="text-xs text-gray-500 mt-2">
                    <?= $financialStats['expenses_with_category'] > 0 ?
                        number_format(($financialStats['expenses_with_category'] / max($financialStats['total_expenses'], 1)) * 100, 1) . '% categorized' :
                        'No expense categories recorded' ?>
                </div>
            </div>

            <!-- Invoice Status Card -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center mb-2">
                    <span class="text-blue-400 mr-2"><i class="fas fa-file-invoice"></i></span>
                    <span class="text-gray-500">Invoices (<?= $selectedYear ?>)</span>
                </div>
                <div class="text-2xl font-bold"><?= $financialStats['total_invoices'] ?></div>
                <div class="flex space-x-4 mt-2 text-sm">
                    <span class="text-green-600"><?= $financialStats['paid_invoices'] ?> Paid</span>
                    <span class="text-yellow-600"><?= $financialStats['pending_invoices'] ?> Pending</span>
                    <span class="text-red-600"><?= $financialStats['overdue_invoices'] ?> Overdue</span>
                </div>
                <!-- Payment Method Info -->
                <div class="text-xs text-gray-500 mt-2">
                    <?= $financialStats['invoices_with_payment_method'] > 0 ?
                        number_format(($financialStats['invoices_with_payment_method'] / max($financialStats['total_invoices'], 1)) * 100, 1) . '% with payment method' :
                        'No payment methods recorded' ?>
                </div>
            </div>
        </div>

        <!-- Monthly Income vs Expenses Chart -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Monthly Income vs Expenses
                (<?php echo $selectedYear; ?>)</h2>
            <div class="h-80">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>

        <!-- Income by Case Type Chart -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Income by Case Type</h2>
            <?php if (empty($caseTypeLabels)): ?>
                <div class="text-center py-8 text-gray-500">
                    <p>No income data available for the selected filters.</p>
                </div>
            <?php else: ?>
                <div class="h-64">
                    <canvas id="caseTypeChart"></canvas>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Income Table -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold text-gray-800">Recent Income</h2>
                <a href="../finance/income.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                    View All <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>

            <?php if ($recentIncomeResult->num_rows === 0): ?>
                <div class="text-center py-8 text-gray-500">
                    <p>No income transactions available for the selected filters.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Date</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Client</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Case</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Amount</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Payment Method</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($income = $recentIncomeResult->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo date('M d, Y', strtotime($income['payment_date'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($income['client_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?php if ($income['case_id']): ?>
                                            <a href="../cases/view.php?id=<?php echo $income['case_id']; ?>"
                                                class="text-blue-600 hover:text-blue-900">
                                                <?php echo htmlspecialchars($income['case_number']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gray-500">No Case</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-green-600">
                                        <?php echo formatCurrency($income['amount']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($income['payment_method'] ?? 'N/A'); ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Expenses Table -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold text-gray-800">Recent Expenses</h2>
                <a href="../finance/expenses/index.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                    View All <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>

            <?php if ($recentExpensesResult->num_rows === 0): ?>
                <div class="text-center py-8 text-gray-500">
                    <p>No expenses available for the selected filters.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Date</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Case</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Description</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Category</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Amount</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($expense = $recentExpensesResult->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo date('M d, Y', strtotime($expense['expense_date'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php if ($expense['case_id']): ?>
                                            <a href="../cases/view.php?id=<?php echo $expense['case_id']; ?>"
                                                class="text-blue-600 hover:text-blue-900">
                                                <?php echo htmlspecialchars($expense['case_number']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gray-500">No Case</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?php echo htmlspecialchars($expense['description']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($expense['expense_category'] ?? 'Uncategorized'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-red-600">
                                        <?php echo formatCurrency($expense['amount']); ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pending Payments Table -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold text-gray-800">Pending Payments</h2>
                <a href="../finance/invoices/index.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                    View All <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>

            <?php if ($pendingPaymentsResult->num_rows === 0): ?>
                <div class="text-center py-8 text-gray-500">
                    <p>No pending payments available for the selected filters.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Due Date</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Client</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Case</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Amount</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($payment = $pendingPaymentsResult->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo date('M d, Y', strtotime($payment['due_date'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($payment['client_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php if ($payment['case_id']): ?>
                                            <a href="../cases/view.php?id=<?php echo $payment['case_id']; ?>"
                                                class="text-blue-600 hover:text-blue-900">
                                                <?php echo htmlspecialchars($payment['case_number']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gray-500">No Case</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo formatCurrency($payment['amount']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="status-badge <?php echo $payment['status']; ?>">
                                            <?php echo ucfirst($payment['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Payment Methods Distribution -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-lg font-semibold mb-4">Payment Methods Distribution</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($paymentMethods as $method): ?>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="text-sm font-medium text-gray-700"><?= htmlspecialchars($method['payment_method']) ?>
                        </div>
                        <div class="text-lg font-bold text-gray-900">RWF <?= number_format($method['total_amount'], 2) ?>
                        </div>
                        <div class="text-xs text-gray-500"><?= $method['count'] ?> invoices</div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Toggle date filter fields based on selection
        function toggleDateFields() {
            const dateRange = document.getElementById('date_range').value;

            document.getElementById('year_filter').style.display =
                (dateRange === 'year' || dateRange === 'month') ? 'block' : 'none';

            document.getElementById('month_filter').style.display =
                dateRange === 'month' ? 'block' : 'none';

            document.getElementById('custom_date_filter').style.display =
                dateRange === 'custom' ? 'grid' : 'none';
        }

        // Attach event listener
        document.getElementById('date_range').addEventListener('change', toggleDateFields);

        // Monthly Income vs Expenses Chart
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov',
                    'Dec'
                ],
                datasets: [{
                    label: 'Income',
                    data: <?php echo json_encode(array_values($monthlyIncome)); ?>,
                    backgroundColor: 'rgba(16, 185, 129, 0.7)',
                    borderColor: 'rgba(16, 185, 129, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Expenses',
                    data: <?php echo json_encode(array_values($monthlyExpenses)); ?>,
                    backgroundColor: 'rgba(239, 68, 68, 0.7)',
                    borderColor: 'rgba(239, 68, 68, 1)',
                    borderWidth: 1
                }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Income by Case Type Chart
        const caseTypeCtx = document.getElementById('caseTypeChart').getContext('2d');
        new Chart(caseTypeCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($caseTypeLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($caseTypeAmounts); ?>,
                    backgroundColor: [
                        'rgba(59, 130, 246, 0.7)', // Blue
                        'rgba(16, 185, 129, 0.7)', // Green
                        'rgba(239, 68, 68, 0.7)', // Red
                        'rgba(245, 158, 11, 0.7)', // Yellow
                        'rgba(139, 92, 246, 0.7)', // Purple
                        'rgba(107, 114, 128, 0.7)' // Gray
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right'
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                const value = context.raw;
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Print report functionality
        document.getElementById('printReport').addEventListener('click', function () {
            window.print();
        });

        // Download PDF functionality
        document.getElementById('downloadPdf').addEventListener('click', function () {
            // Show loading indicator
            const loadingIndicator = document.createElement('div');
            loadingIndicator.className =
                'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            loadingIndicator.innerHTML = `
            <div class="bg-white p-5 rounded-lg shadow-lg text-center">
                <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-blue-600 mx-auto mb-3"></div>
                <p class="text-gray-700">Generating PDF...</p>
            </div>
        `;
            document.body.appendChild(loadingIndicator);

            // Use setTimeout to allow the loading indicator to render
            setTimeout(function () {
                generatePDF();
            }, 100);

            function generatePDF() {
                const {
                    jsPDF
                } = window.jspdf;
                const reportContent = document.getElementById('reportContent');

                // Create a new PDF document
                const pdf = new jsPDF('p', 'mm', 'a4');

                // Add title
                pdf.setFontSize(18);
                pdf.setTextColor(33, 33, 33);
                pdf.text('Financial Report', 105, 15, {
                    align: 'center'
                });

                // Add filters information
                pdf.setFontSize(10);
                pdf.setTextColor(100, 100, 100);
                let filterText = 'Period: ';
                <?php if ($dateRange === 'year'): ?>
                    filterText += 'Year <?php echo $selectedYear; ?>';
                <?php elseif ($dateRange === 'month'): ?>
                    filterText +=
                        '<?php echo date("F Y", strtotime($selectedYear . "-" . $selectedMonth . "-01")); ?>';
                <?php else: ?>
                    filterText +=
                        '<?php echo date("M d, Y", strtotime($startDate)); ?> to <?php echo date("M d, Y", strtotime($endDate)); ?>';
                <?php endif; ?>

                pdf.text(filterText, 105, 22, {
                    align: 'center'
                });

                // Add date generated
                pdf.setFontSize(8);
                pdf.text('Generated on: ' + new Date().toLocaleString(), 105, 26, {
                    align: 'center'
                });

                // Add financial summary
                pdf.setFontSize(14);
                pdf.setTextColor(33, 33, 33);
                pdf.text('Financial Summary', 20, 35);

                pdf.setFontSize(10);
                pdf.text('Total Income: <?php echo formatCurrency($financialStats['total_income']); ?>', 20,
                    42);
                pdf.text('Total Expenses: <?php echo formatCurrency($financialStats['total_expenses']); ?>',
                    20, 48);
                pdf.text('Net Profit: <?php echo formatCurrency($netProfit); ?>', 20, 54);
                pdf.text('Profit Margin: <?php echo number_format($profitMargin, 1); ?>%', 20, 60);

                // Capture charts as images
                html2canvas(document.getElementById('monthlyChart')).then(canvas => {
                    const monthlyChartImg = canvas.toDataURL('image/png');
                    pdf.addImage(monthlyChartImg, 'PNG', 20, 70, 170, 80);

                    // Income by Case Type Chart
                    html2canvas(document.getElementById('caseTypeChart')).then(canvas => {
                        const caseTypeImg = canvas.toDataURL('image/png');

                        pdf.addPage();
                        pdf.text('Income by Case Type', 20, 20);
                        pdf.addImage(caseTypeImg, 'PNG', 20, 30, 170, 80);

                        // Save the PDF
                        pdf.save('financial-report-<?php echo $selectedYear; ?>.pdf');

                        // Remove loading indicator
                        document.body.removeChild(loadingIndicator);
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

        #reportContent,
        #reportContent * {
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