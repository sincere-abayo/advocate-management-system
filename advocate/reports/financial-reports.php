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
$pageTitle = "Financial Reports";

// Include header
include_once '../includes/header.php';

// Get database connection
$conn = getDBConnection();

// Initialize filters
$currentYear = date('Y');
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : $currentYear;
$selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : 0;
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
    FROM case_income
    WHERE advocate_id = ? AND payment_method IS NOT NULL
    UNION
    SELECT DISTINCT payment_method 
    FROM advocate_other_income
    WHERE advocate_id = ? AND payment_method IS NOT NULL
    UNION
    SELECT DISTINCT payment_method 
    FROM billings
    WHERE advocate_id = ? AND payment_method IS NOT NULL
    ORDER BY payment_method
";
$paymentMethodsStmt = $conn->prepare($paymentMethodsQuery);
$paymentMethodsStmt->bind_param("iii", $advocateId, $advocateId, $advocateId);
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

// Get financial summary
$financialSummaryQuery = "
    SELECT 
        COALESCE(SUM(CASE WHEN source = 'case_income' THEN amount ELSE 0 END), 0) as case_income,
        COALESCE(SUM(CASE WHEN source = 'other_income' THEN amount ELSE 0 END), 0) as other_income,
        COALESCE(SUM(CASE WHEN source = 'expenses' THEN amount ELSE 0 END), 0) as total_expenses
    FROM (
        SELECT 'case_income' as source, amount, income_date as transaction_date
        FROM case_income 
        WHERE advocate_id = ? AND income_date BETWEEN ? AND ?
        
        UNION ALL
        
        SELECT 'other_income' as source, amount, income_date as transaction_date
        FROM advocate_other_income 
        WHERE advocate_id = ? AND income_date BETWEEN ? AND ?
        
        UNION ALL
        
        SELECT 'expenses' as source, amount, expense_date as transaction_date
        FROM case_expenses 
        WHERE advocate_id = ? AND expense_date BETWEEN ? AND ?
    ) as all_transactions
";

$financialSummaryStmt = $conn->prepare($financialSummaryQuery);
$financialSummaryStmt->bind_param("issiissis", 
    $advocateId, $startDate, $endDate,
    $advocateId, $startDate, $endDate,
    $advocateId, $startDate, $endDate
);
$financialSummaryStmt->execute();
$financialSummary = $financialSummaryStmt->get_result()->fetch_assoc();

// Calculate total income and profit
$totalIncome = $financialSummary['case_income'] + $financialSummary['other_income'];
$totalProfit = $totalIncome - $financialSummary['total_expenses'];
$profitMargin = $totalIncome > 0 ? ($totalProfit / $totalIncome) * 100 : 0;

// Get billing statistics
$billingStatsQuery = "
    SELECT 
        COUNT(*) as total_invoices,
        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_invoices,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_invoices,
        SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue_invoices,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_invoices,
        SUM(amount) as total_billed,
        SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as total_collected
    FROM billings
    WHERE advocate_id = ? AND billing_date BETWEEN ? AND ?
";

$billingStatsStmt = $conn->prepare($billingStatsQuery);
$billingStatsStmt->bind_param("iss", $advocateId, $startDate, $endDate);
$billingStatsStmt->execute();
$billingStats = $billingStatsStmt->get_result()->fetch_assoc();

// Calculate collection rate
$collectionRate = $billingStats['total_invoices'] > 0 ? 
    ($billingStats['paid_invoices'] / $billingStats['total_invoices']) * 100 : 0;
$amountCollectionRate = $billingStats['total_billed'] > 0 ? 
    ($billingStats['total_collected'] / $billingStats['total_billed']) * 100 : 0;

// Get monthly income and expenses data
$monthlyDataQuery = "
    SELECT 
        MONTH(transaction_date) as month,
        SUM(CASE WHEN source = 'income' THEN amount ELSE 0 END) as income,
        SUM(CASE WHEN source = 'expense' THEN amount ELSE 0 END) as expense
    FROM (
        SELECT 'income' as source, amount, income_date as transaction_date
        FROM case_income 
        WHERE advocate_id = ? AND YEAR(income_date) = ?
        
        UNION ALL
        
        SELECT 'income' as source, amount, income_date as transaction_date
        FROM advocate_other_income 
        WHERE advocate_id = ? AND YEAR(income_date) = ?
        
        UNION ALL
        
        SELECT 'expense' as source, amount, expense_date as transaction_date
        FROM case_expenses 
        WHERE advocate_id = ? AND YEAR(expense_date) = ?
    ) as all_transactions
    GROUP BY MONTH(transaction_date)
    ORDER BY MONTH(transaction_date)
";

$monthlyDataStmt = $conn->prepare($monthlyDataQuery);
$monthlyDataStmt->bind_param("iiiiii", 
    $advocateId, $selectedYear,
    $advocateId, $selectedYear,
    $advocateId, $selectedYear
);
$monthlyDataStmt->execute();
$monthlyDataResult = $monthlyDataStmt->get_result();

$monthlyIncome = array_fill(1, 12, 0);
$monthlyExpenses = array_fill(1, 12, 0);
$monthlyProfit = array_fill(1, 12, 0);

while ($row = $monthlyDataResult->fetch_assoc()) {
    $monthlyIncome[$row['month']] = (float)$row['income'];
    $monthlyExpenses[$row['month']] = (float)$row['expense'];
    $monthlyProfit[$row['month']] = (float)$row['income'] - (float)$row['expense'];
}

// Get income by category
$incomeByCategoryQuery = "
    SELECT 
        COALESCE(income_category, 'Uncategorized') as category,
        SUM(amount) as total
    FROM (
        SELECT income_category, amount
        FROM case_income 
        WHERE advocate_id = ? AND income_date BETWEEN ? AND ?
        
        UNION ALL
        
        SELECT income_category, amount
        FROM advocate_other_income 
        WHERE advocate_id = ? AND income_date BETWEEN ? AND ?
    ) as all_income
    GROUP BY category
    ORDER BY total DESC
";

$incomeByCategoryStmt = $conn->prepare($incomeByCategoryQuery);
$incomeByCategoryStmt->bind_param("isissi", 
    $advocateId, $startDate, $endDate,
    $advocateId, $startDate, $endDate
);
$incomeByCategoryStmt->execute();
$incomeByCategoryResult = $incomeByCategoryStmt->get_result();

$incomeCategories = [];
$incomeCategoryAmounts = [];

while ($row = $incomeByCategoryResult->fetch_assoc()) {
    $incomeCategories[] = $row['category'];
    $incomeCategoryAmounts[] = (float)$row['total'];
}

// Get expenses by category
$expensesByCategoryQuery = "
    SELECT 
        COALESCE(expense_category, 'Uncategorized') as category,
        SUM(amount) as total
    FROM case_expenses
    WHERE advocate_id = ? AND expense_date BETWEEN ? AND ?
    GROUP BY category
    ORDER BY total DESC
";

$expensesByCategoryStmt = $conn->prepare($expensesByCategoryQuery);
$expensesByCategoryStmt->bind_param("iss", $advocateId, $startDate, $endDate);
$expensesByCategoryStmt->execute();
$expensesByCategoryResult = $expensesByCategoryStmt->get_result();

$expenseCategories = [];
$expenseCategoryAmounts = [];

while ($row = $expensesByCategoryResult->fetch_assoc()) {
    $expenseCategories[] = $row['category'];
    $expenseCategoryAmounts[] = (float)$row['total'];
}

// Get income by payment method
$incomeByPaymentMethodQuery = "
    SELECT 
        COALESCE(payment_method, 'Not Specified') as method,
        SUM(amount) as total
    FROM (
        SELECT payment_method, amount
        FROM case_income 
        WHERE advocate_id = ? AND income_date BETWEEN ? AND ?
        
        UNION ALL
        
        SELECT payment_method, amount
        FROM advocate_other_income 
        WHERE advocate_id = ? AND income_date BETWEEN ? AND ?
    ) as all_income
    GROUP BY method
    ORDER BY total DESC
";

$incomeByPaymentMethodStmt = $conn->prepare($incomeByPaymentMethodQuery);
$incomeByPaymentMethodStmt->bind_param("isissi", 
    $advocateId, $startDate, $endDate,
    $advocateId, $startDate, $endDate
);
$incomeByPaymentMethodStmt->execute();
$incomeByPaymentMethodResult = $incomeByPaymentMethodStmt->get_result();

$paymentMethods = [];
$paymentMethodAmounts = [];

while ($row = $incomeByPaymentMethodResult->fetch_assoc()) {
    $paymentMethods[] = $row['method'];
    $paymentMethodAmounts[] = (float)$row['total'];
}

// Get top clients by revenue
$topClientsQuery = "
    SELECT 
        cp.client_id,
        u.full_name as client_name,
        SUM(ci.amount) as total_revenue
    FROM case_income ci
    JOIN cases c ON ci.case_id = c.case_id
    JOIN client_profiles cp ON c.client_id = cp.client_id
    JOIN users u ON cp.user_id = u.user_id
    WHERE ci.advocate_id = ? AND ci.income_date BETWEEN ? AND ?
    GROUP BY cp.client_id, u.full_name
    ORDER BY total_revenue DESC
    LIMIT 10
";

$topClientsStmt = $conn->prepare($topClientsQuery);
$topClientsStmt->bind_param("iss", $advocateId, $startDate, $endDate);
$topClientsStmt->execute();
$topClientsResult = $topClientsStmt->get_result();

// Get recent income transactions
$recentIncomeQuery = "
        SELECT 
        'case_income' as source,
        ci.income_id as id,
        ci.case_id,
        c.case_number,
        c.title as case_title,
        NULL as client_id,
        NULL as client_name,
        ci.income_date as transaction_date,
        ci.amount,
        ci.description,
        ci.income_category as category,
        ci.payment_method
    FROM case_income ci
    LEFT JOIN cases c ON ci.case_id = c.case_id
    WHERE ci.advocate_id = ? AND ci.income_date BETWEEN ? AND ?
    
    UNION ALL
    
    SELECT 
        'other_income' as source,
        aoi.income_id as id,
        NULL as case_id,
        NULL as case_number,
        NULL as case_title,
        NULL as client_id,
        NULL as client_name,
        aoi.income_date as transaction_date,
        aoi.amount,
        aoi.description,
        aoi.income_category as category,
        aoi.payment_method
    FROM advocate_other_income aoi
    WHERE aoi.advocate_id = ? AND aoi.income_date BETWEEN ? AND ?
    
    ORDER BY transaction_date DESC
    LIMIT 10
";

$recentIncomeStmt = $conn->prepare($recentIncomeQuery);
$recentIncomeStmt->bind_param("ississ", 
    $advocateId, $startDate, $endDate,
    $advocateId, $startDate, $endDate
);
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
    WHERE ce.advocate_id = ? AND ce.expense_date BETWEEN ? AND ?
    ORDER BY ce.expense_date DESC
    LIMIT 10
";

$recentExpensesStmt = $conn->prepare($recentExpensesQuery);
$recentExpensesStmt->bind_param("iss", $advocateId, $startDate, $endDate);
$recentExpensesStmt->execute();
$recentExpensesResult = $recentExpensesStmt->get_result();

// Get recent invoices
$recentInvoicesQuery = "
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
    WHERE b.advocate_id = ? AND b.billing_date BETWEEN ? AND ?
    ORDER BY b.billing_date DESC
    LIMIT 10
";

$recentInvoicesStmt = $conn->prepare($recentInvoicesQuery);
$recentInvoicesStmt->bind_param("iss", $advocateId, $startDate, $endDate);
$recentInvoicesStmt->execute();
$recentInvoicesResult = $recentInvoicesStmt->get_result();

// Get yearly comparison data (last 3 years)
$yearlyComparisonQuery = "
    SELECT 
        YEAR(transaction_date) as year,
        SUM(CASE WHEN source = 'income' THEN amount ELSE 0 END) as income,
        SUM(CASE WHEN source = 'expense' THEN amount ELSE 0 END) as expense,
        SUM(CASE WHEN source = 'income' THEN amount ELSE -amount END) as profit
    FROM (
        SELECT 'income' as source, amount, income_date as transaction_date
        FROM case_income 
        WHERE advocate_id = ? AND YEAR(income_date) >= ? - 2
        
        UNION ALL
        
        SELECT 'income' as source, amount, income_date as transaction_date
        FROM advocate_other_income 
        WHERE advocate_id = ? AND YEAR(income_date) >= ? - 2
        
        UNION ALL
        
        SELECT 'expense' as source, amount, expense_date as transaction_date
        FROM case_expenses 
        WHERE advocate_id = ? AND YEAR(expense_date) >= ? - 2
    ) as all_transactions
    GROUP BY YEAR(transaction_date)
    ORDER BY YEAR(transaction_date)
";

$yearlyComparisonStmt = $conn->prepare($yearlyComparisonQuery);
$yearlyComparisonStmt->bind_param("iiiiii", 
    $advocateId, $selectedYear,
    $advocateId, $selectedYear,
    $advocateId, $selectedYear
);
$yearlyComparisonStmt->execute();
$yearlyComparisonResult = $yearlyComparisonStmt->get_result();

$yearlyYears = [];
$yearlyIncome = [];
$yearlyExpenses = [];
$yearlyProfit = [];

while ($row = $yearlyComparisonResult->fetch_assoc()) {
    $yearlyYears[] = $row['year'];
    $yearlyIncome[] = (float)$row['income'];
    $yearlyExpenses[] = (float)$row['expense'];
    $yearlyProfit[] = (float)$row['profit'];
}

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
            <a href="../finance/index.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back to Finance
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
                <label for="date_range" class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                <select id="date_range" name="date_range" class="form-select w-full" onchange="toggleDateFields()">
                    <option value="year" <?php echo $dateRange === 'year' ? 'selected' : ''; ?>>Yearly</option>
                    <option value="month" <?php echo $dateRange === 'month' ? 'selected' : ''; ?>>Monthly</option>
                    <option value="custom" <?php echo $dateRange === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
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
            
            <div id="custom_date_filter" class="grid grid-cols-2 gap-2" style="<?php echo $dateRange !== 'custom' ? 'display: none;' : ''; ?>">
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                    <input type="date" id="start_date" name="start_date" class="form-input w-full" value="<?php echo $dateRange === 'custom' ? $startDate : ''; ?>">
                </div>
                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                    <input type="date" id="end_date" name="end_date" class="form-input w-full" value="<?php echo $dateRange === 'custom' ? $endDate : ''; ?>">
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
                <select id="payment_method" name="payment_method" class="form-select w-full">
                    <option value="" <?php echo $selectedPaymentMethod === '' ? 'selected' : ''; ?>>All Payment Methods</option>
                    <?php foreach ($paymentMethods as $method): ?>
                        <?php if (!empty($method)): ?>
                        <option value="<?php echo $method; ?>" <?php echo $selectedPaymentMethod === $method ? 'selected' : ''; ?>>
                            <?php echo $method; ?>
                        </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="md:col-span-3">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg">
                    <i class="fas fa-filter mr-2"></i> Apply Filters
                </button>
                <a href="financial-reports.php" class="ml-2 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg inline-flex items-center">
                    <i class="fas fa-times mr-2"></i> Clear Filters
                </a>
            </div>
        </form>
    </div>
    
    <div id="reportContent">
        <!-- Financial Summary -->
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
                        <p class="text-2xl font-semibold"><?php echo formatCurrency($financialSummary['total_expenses']); ?></p>
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
                        <p class="text-2xl font-semibold <?php echo $totalProfit >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo formatCurrency($totalProfit); ?>
                        </p>
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
                        <p class="text-2xl font-semibold <?php echo $profitMargin >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo number_format($profitMargin, 1); ?>%
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Billing Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-indigo-100 text-indigo-500 mr-4">
                        <i class="fas fa-file-invoice text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Total Invoices</p>
                        <p class="text-2xl font-semibold"><?php echo $billingStats['total_invoices']; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-500 mr-4">
                        <i class="fas fa-check-circle text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Paid Invoices</p>
                        <p class="text-2xl font-semibold"><?php echo $billingStats['paid_invoices']; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-500 mr-4">
                        <i class="fas fa-clock text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Pending Invoices</p>
                        <p class="text-2xl font-semibold"><?php echo $billingStats['pending_invoices']; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-red-100 text-red-500 mr-4">
                        <i class="fas fa-exclamation-circle text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm">Overdue Invoices</p>
                        <p class="text-2xl font-semibold"><?php echo $billingStats['overdue_invoices']; ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Monthly Income and Expenses Chart -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Monthly Income and Expenses (<?php echo $selectedYear; ?>)</h2>
            <div class="h-80">
                <canvas id="monthlyFinancialsChart"></canvas>
            </div>
        </div>
        
        <!-- Income and Expense Distribution Charts -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Income by Category</h2>
                <?php if (empty($incomeCategories)): ?>
                    <div class="text-center py-8 text-gray-500">
                        <p>No income data available for the selected filters.</p>
                    </div>
                <?php else: ?>
                    <div class="h-64">
                        <canvas id="incomeCategoryChart"></canvas>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Expenses by Category</h2>
                <?php if (empty($expenseCategories)): ?>
                    <div class="text-center py-8 text-gray-500">
                        <p>No expense data available for the selected filters.</p>
                    </div>
                <?php else: ?>
                    <div class="h-64">
                        <canvas id="expenseCategoryChart"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Income by Payment Method Chart -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Income by Payment Method</h2>
            <?php if (empty($paymentMethods)): ?>
                <div class="text-center py-8 text-gray-500">
                    <p>No payment method data available for the selected filters.</p>
                </div>
            <?php else: ?>
                <div class="h-64">
                    <canvas id="paymentMethodChart"></canvas>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Yearly Comparison Chart -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Yearly Financial Comparison</h2>
            <?php if (empty($yearlyYears)): ?>
                <div class="text-center py-8 text-gray-500">
                    <p>No yearly comparison data available.</p>
                </div>
            <?php else: ?>
                <div class="h-80">
                    <canvas id="yearlyComparisonChart"></canvas>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Top Clients by Revenue -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Top Clients by Revenue</h2>
            <?php if ($topClientsResult->num_rows === 0): ?>
                <div class="text-center py-8 text-gray-500">
                    <p>No client revenue data available for the selected filters.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">% of Total</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php 
                            $totalClientRevenue = 0;
                            $topClientsData = [];
                            
                            // First pass to calculate total
                            while ($client = $topClientsResult->fetch_assoc()) {
                                $totalClientRevenue += $client['total_revenue'];
                                $topClientsData[] = $client;
                            }
                            
                            // Reset pointer
                            $topClientsResult->data_seek(0);
                            
                            // Second pass to display with percentages
                            foreach ($topClientsData as $client):
                                $percentage = $totalClientRevenue > 0 ? ($client['total_revenue'] / $totalClientRevenue) * 100 : 0;
                            ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <a href="../clients/view.php?id=<?php echo $client['client_id']; ?>" class="text-blue-600 hover:text-blue-900 font-medium">
                                            <?php echo htmlspecialchars($client['client_name']); ?>
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo formatCurrency($client['total_revenue']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <span class="text-sm text-gray-900 font-medium">
                                                <?php echo number_format($percentage, 1); ?>%
                                            </span>
                                            <div class="ml-2 w-24 bg-gray-200 rounded-full h-2">
                                                <div class="bg-blue-500 h-2 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Recent Income Transactions -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold text-gray-800">Recent Income Transactions</h2>
                <a href="../finance/income/index.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
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
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Source</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($income = $recentIncomeResult->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo date('M d, Y', strtotime($income['transaction_date'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php if ($income['source'] === 'case_income' && $income['case_id']): ?>
                                            <a href="../cases/view.php?id=<?php echo $income['case_id']; ?>" class="text-blue-600 hover:text-blue-900">
                                                <?php echo htmlspecialchars($income['case_number']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gray-500">Other Income</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?php echo htmlspecialchars($income['description']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($income['category'] ?? 'Uncategorized'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($income['payment_method'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-green-600">
                                        <?php echo formatCurrency($income['amount']); ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Recent Expenses -->
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
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Case</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
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
                                            <a href="../cases/view.php?id=<?php echo $expense['case_id']; ?>" class="text-blue-600 hover:text-blue-900">
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
        
        <!-- Recent Invoices -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold text-gray-800">Recent Invoices</h2>
                <a href="../finance/invoices/index.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                    View All <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            
            <?php if ($recentInvoicesResult->num_rows === 0): ?>
                <div class="text-center py-8 text-gray-500">
                    <p>No invoices available for the selected filters.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
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
                            <?php while ($invoice = $recentInvoicesResult->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <a href="../finance/invoices/view.php?id=<?php echo $invoice['billing_id']; ?>" class="text-blue-600 hover:text-blue-900">
                                            INV-<?php echo str_pad($invoice['billing_id'], 5, '0', STR_PAD_LEFT); ?>
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <a href="../clients/view.php?id=<?php echo $invoice['client_id']; ?>" class="text-blue-600 hover:text-blue-900">
                                            <?php echo htmlspecialchars($invoice['client_name']); ?>
                                        </a>
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
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
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
    
    // Monthly Income and Expenses Chart
    const monthlyCtx = document.getElementById('monthlyFinancialsChart').getContext('2d');
    const monthlyChart = new Chart(monthlyCtx, {
        type: 'bar',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            datasets: [
                {
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
                },
                {
                    label: 'Profit',
                    data: <?php echo json_encode(array_values($monthlyProfit)); ?>,
                    type: 'line',
                    backgroundColor: 'rgba(59, 130, 246, 0.2)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 2,
                    fill: false,
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
    
    <?php if (!empty($incomeCategories)): ?>
    // Income by Category Chart
    const incomeCategoryCtx = document.getElementById('incomeCategoryChart').getContext('2d');
    const incomeCategoryChart = new Chart(incomeCategoryCtx, {
        type: 'pie',
        data: {
            labels: <?php echo json_encode($incomeCategories); ?>,
            datasets: [{
                data: <?php echo json_encode($incomeCategoryAmounts); ?>,
                backgroundColor: [
                    'rgba(59, 130, 246, 0.7)',   // Blue
                    'rgba(16, 185, 129, 0.7)',   // Green
                    'rgba(245, 158, 11, 0.7)',   // Yellow
                    'rgba(139, 92, 246, 0.7)',   // Purple
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
    <?php endif; ?>
    
    <?php if (!empty($expenseCategories)): ?>
    // Expenses by Category Chart
    const expenseCategoryCtx = document.getElementById('expenseCategoryChart').getContext('2d');
    const expenseCategoryChart = new Chart(expenseCategoryCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($expenseCategories); ?>,
            datasets: [{
                data: <?php echo json_encode($expenseCategoryAmounts); ?>,
                backgroundColor: [
                    'rgba(239, 68, 68, 0.7)',    // Red
                    'rgba(245, 158, 11, 0.7)',   // Yellow
                    'rgba(59, 130, 246, 0.7)',   // Blue
                    'rgba(139, 92, 246, 0.7)',   // Purple
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
    <?php endif; ?>
    
    <?php if (!empty($paymentMethods)): ?>
    // Payment Method Chart
    const paymentMethodCtx = document.getElementById('paymentMethodChart').getContext('2d');
    const paymentMethodChart = new Chart(paymentMethodCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($paymentMethods); ?>,
            datasets: [{
                label: 'Income by Payment Method',
                data: <?php echo json_encode($paymentMethodAmounts); ?>,
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
                    beginAtZero: true
                }
            }
        }
    });
    <?php endif; ?>
    
    <?php if (!empty($yearlyYears)): ?>
    // Yearly Comparison Chart
    const yearlyCtx = document.getElementById('yearlyComparisonChart').getContext('2d');
    const yearlyChart = new Chart(yearlyCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($yearlyYears); ?>,
            datasets: [
                {
                    label: 'Income',
                    data: <?php echo json_encode($yearlyIncome); ?>,
                    backgroundColor: 'rgba(16, 185, 129, 0.7)',
                    borderColor: 'rgba(16, 185, 129, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Expenses',
                    data: <?php echo json_encode($yearlyExpenses); ?>,
                    backgroundColor: 'rgba(239, 68, 68, 0.7)',
                    borderColor: 'rgba(239, 68, 68, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Profit',
                    data: <?php echo json_encode($yearlyProfit); ?>,
                    backgroundColor: 'rgba(59, 130, 246, 0.7)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 1
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
    <?php endif; ?>
    
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
            pdf.text('Financial Report', 105, 15, { align: 'center' });
            
            // Add filters information
            pdf.setFontSize(10);
            pdf.setTextColor(100, 100, 100);
            let filterText = 'Period: ';
            <?php if ($dateRange === 'year'): ?>
                filterText += 'Year <?php echo $selectedYear; ?>';
            <?php elseif ($dateRange === 'month'): ?>
                filterText += '<?php echo date("F Y", strtotime($selectedYear . "-" . $selectedMonth . "-01")); ?>';
            <?php else: ?>
                filterText += '<?php echo date("M d, Y", strtotime($startDate)); ?> to <?php echo date("M d, Y", strtotime($endDate)); ?>';
            <?php endif; ?>
            
            pdf.text(filterText, 105, 22, { align: 'center' });
            
            // Add date generated
            pdf.setFontSize(8);
            pdf.text('Generated on: ' + new Date().toLocaleString(), 105, 26, { align: 'center' });
            
            // Add financial summary
            pdf.setFontSize(14);
            pdf.setTextColor(33, 33, 33);
            pdf.text('Financial Summary', 20, 35);
            
            pdf.setFontSize(10);
            pdf.text('Total Income: <?php echo formatCurrency($totalIncome); ?>', 20, 42);
            pdf.text('Total Expenses: <?php echo formatCurrency($financialSummary['total_expenses']); ?>', 20, 48);
            pdf.text('Net Profit: <?php echo formatCurrency($totalProfit); ?>', 20, 54);
            pdf.text('Profit Margin: <?php echo number_format($profitMargin, 1); ?>%', 20, 60);
            
            // Add billing summary
            pdf.setFontSize(14);
            pdf.text('Billing Summary', 120, 35);
            
            pdf.setFontSize(10);
            pdf.text('Total Invoices: <?php echo $billingStats['total_invoices']; ?>', 120, 42);
            pdf.text('Paid Invoices: <?php echo $billingStats['paid_invoices']; ?>', 120, 48);
            pdf.text('Pending Invoices: <?php echo $billingStats['pending_invoices']; ?>', 120, 54);
            pdf.text('Collection Rate: <?php echo number_format($collectionRate, 1); ?>%', 120, 60);
            
            // Capture charts as images
            html2canvas(document.getElementById('monthlyFinancialsChart')).then(canvas => {
                const monthlyChartImg = canvas.toDataURL('image/png');
                pdf.addImage(monthlyChartImg, 'PNG', 20, 70, 170, 80);
                
                <?php if (!empty($incomeCategories)): ?>
                html2canvas(document.getElementById('incomeCategoryChart')).then(canvas => {
                    const incomeCategoryImg = canvas.toDataURL('image/png');
                    
                    pdf.addPage();
                    pdf.text('Income by Category', 20, 20);
                    pdf.addImage(incomeCategoryImg, 'PNG', 20, 30, 80, 80);
                    
                    <?php if (!empty($expenseCategories)): ?>
                    html2canvas(document.getElementById('expenseCategoryChart')).then(canvas => {
                        const expenseCategoryImg = canvas.toDataURL('image/png');
                        pdf.text('Expenses by Category', 110, 20);
                        pdf.addImage(expenseCategoryImg, 'PNG', 110, 30, 80, 80);
                        
                        <?php if (!empty($paymentMethods)): ?>
                        html2canvas(document.getElementById('paymentMethodChart')).then(canvas => {
                            const paymentMethodImg = canvas.toDataURL('image/png');
                            
                            pdf.addPage();
                            pdf.text('Income by Payment Method', 20, 20);
                            pdf.addImage(paymentMethodImg, 'PNG', 20, 30, 170, 80);
                            
                            <?php if (!empty($yearlyYears)): ?>
                            html2canvas(document.getElementById('yearlyComparisonChart')).then(canvas => {
                                const yearlyComparisonImg = canvas.toDataURL('image/png');
                                
                                pdf.addPage();
                                pdf.text('Yearly Financial Comparison', 20, 20);
                                pdf.addImage(yearlyComparisonImg, 'PNG', 20, 30, 170, 80);
                                
                                // Save the PDF
                                pdf.save('financial-report-<?php echo $selectedYear; ?>.pdf');
                                
                                // Remove loading indicator
                                document.body.removeChild(loadingIndicator);
                            });
                            <?php else: ?>
                            // Save the PDF
                            pdf.save('financial-report-<?php echo $selectedYear; ?>.pdf');
                            
                            // Remove loading indicator
                            document.body.removeChild(loadingIndicator);
                            <?php endif; ?>
                        });
                        <?php else: ?>
                        // Save the PDF
                        pdf.save('financial-report-<?php echo $selectedYear; ?>.pdf');
                        
                        // Remove loading indicator
                        document.body.removeChild(loadingIndicator);
                        <?php endif; ?>
                    });
                    <?php else: ?>
                    // Save the PDF
                    pdf.save('financial-report-<?php echo $selectedYear; ?>.pdf');
                    
                    // Remove loading indicator
                    document.body.removeChild(loadingIndicator);
                    <?php endif; ?>
                });
                <?php else: ?>
                // Save the PDF
                pdf.save('financial-report-<?php echo $selectedYear; ?>.pdf');
                
                // Remove loading indicator
                document.body.removeChild(loadingIndicator);
                <?php endif; ?>
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
