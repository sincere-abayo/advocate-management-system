<?php
// Set page title
$pageTitle = "Financial Dashboard";

// Include header
include_once '../includes/header.php';

// Get advocate ID
$advocateId = $advocateData['advocate_id'];

// Get database connection
$conn = getDBConnection();

// Get financial summary
function getFinancialSummary($advocateId, $conn) {
    // Current month summary
    $currentMonthStmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN i.status = 'paid' THEN i.amount ELSE 0 END), 0) as income_paid,
            COALESCE(SUM(CASE WHEN i.status = 'pending' THEN i.amount ELSE 0 END), 0) as income_pending,
            COALESCE(SUM(CASE WHEN i.status = 'overdue' THEN i.amount ELSE 0 END), 0) as income_overdue,
            COALESCE(SUM(i.amount), 0) as total_income
        FROM billings i
        WHERE i.advocate_id = ? 
        AND MONTH(i.billing_date) = MONTH(CURRENT_DATE()) 
        AND YEAR(i.billing_date) = YEAR(CURRENT_DATE())
    ");
    $currentMonthStmt->bind_param("i", $advocateId);
    $currentMonthStmt->execute();
    $currentMonth = $currentMonthStmt->get_result()->fetch_assoc();
    
    // Current month expenses
    $expensesStmt = $conn->prepare("
        SELECT COALESCE(SUM(amount), 0) as total_expenses
        FROM case_expenses
        WHERE advocate_id = ? 
        AND MONTH(expense_date) = MONTH(CURRENT_DATE()) 
        AND YEAR(expense_date) = YEAR(CURRENT_DATE())
    ");
    $expensesStmt->bind_param("i", $advocateId);
    $expensesStmt->execute();
    $expenses = $expensesStmt->get_result()->fetch_assoc();
    
    // Year to date
    $ytdStmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN i.status = 'paid' THEN i.amount ELSE 0 END), 0) as income_paid,
            COALESCE(SUM(i.amount), 0) as total_income
        FROM billings i
        WHERE i.advocate_id = ? 
        AND YEAR(i.billing_date) = YEAR(CURRENT_DATE())
    ");
    $ytdStmt->bind_param("i", $advocateId);
    $ytdStmt->execute();
    $ytd = $ytdStmt->get_result()->fetch_assoc();
    
    // Year to date expenses
    $ytdExpensesStmt = $conn->prepare("
        SELECT COALESCE(SUM(amount), 0) as total_expenses
        FROM case_expenses
        WHERE advocate_id = ? 
        AND YEAR(expense_date) = YEAR(CURRENT_DATE())
    ");
    $ytdExpensesStmt->bind_param("i", $advocateId);
    $ytdExpensesStmt->execute();
    $ytdExpenses = $ytdExpensesStmt->get_result()->fetch_assoc();
    
    return [
        'current_month' => [
            'income_paid' => $currentMonth['income_paid'],
            'income_pending' => $currentMonth['income_pending'],
            'income_overdue' => $currentMonth['income_overdue'],
            'total_income' => $currentMonth['total_income'],
            'total_expenses' => $expenses['total_expenses'],
            'profit' => $currentMonth['income_paid'] - $expenses['total_expenses']
        ],
        'ytd' => [
            'income_paid' => $ytd['income_paid'],
            'total_income' => $ytd['total_income'],
            'total_expenses' => $ytdExpenses['total_expenses'],
            'profit' => $ytd['income_paid'] - $ytdExpenses['total_expenses']
        ]
    ];
}

// Get recent invoices
function getRecentInvoices($advocateId, $conn, $limit = 5) {
    $stmt = $conn->prepare("
        SELECT b.*, c.full_name as client_name, cs.title as case_title
        FROM billings b
        JOIN client_profiles cp ON b.client_id = cp.client_id
        JOIN users c ON cp.user_id = c.user_id
        LEFT JOIN cases cs ON b.case_id = cs.case_id
        WHERE b.advocate_id = ?
        ORDER BY b.billing_date DESC
        LIMIT ?
    ");
    $stmt->bind_param("ii", $advocateId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $invoices = [];
    while ($row = $result->fetch_assoc()) {
        $invoices[] = $row;
    }
    
    return $invoices;
}

// Get recent expenses
function getRecentExpenses($advocateId, $conn, $limit = 5) {
    $stmt = $conn->prepare("
        SELECT e.*, cs.title as case_title
        FROM case_expenses e
        LEFT JOIN cases cs ON e.case_id = cs.case_id
        WHERE e.advocate_id = ?
        ORDER BY e.expense_date DESC
        LIMIT ?
    ");
    $stmt->bind_param("ii", $advocateId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $expenses = [];
    while ($row = $result->fetch_assoc()) {
        $expenses[] = $row;
    }
    
    return $expenses;
}

// Get monthly income data for chart
function getMonthlyIncomeData($advocateId, $conn) {
    $stmt = $conn->prepare("
        SELECT 
            MONTH(billing_date) as month,
            COALESCE(SUM(amount), 0) as total_amount
        FROM billings
        WHERE advocate_id = ? 
        AND YEAR(billing_date) = YEAR(CURRENT_DATE())
        GROUP BY MONTH(billing_date)
        ORDER BY MONTH(billing_date)
    ");
    $stmt->bind_param("i", $advocateId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $monthlyData = array_fill(0, 12, 0); // Initialize with zeros for all months
    
    while ($row = $result->fetch_assoc()) {
        $monthIndex = (int)$row['month'] - 1; // Convert to 0-based index
        $monthlyData[$monthIndex] = (float)$row['total_amount'];
    }
    
    return $monthlyData;
}

// Get monthly expense data for chart
function getMonthlyExpenseData($advocateId, $conn) {
    $stmt = $conn->prepare("
        SELECT 
            MONTH(expense_date) as month,
            COALESCE(SUM(amount), 0) as total_amount
        FROM case_expenses
        WHERE advocate_id = ? 
        AND YEAR(expense_date) = YEAR(CURRENT_DATE())
        GROUP BY MONTH(expense_date)
        ORDER BY MONTH(expense_date)
    ");
    $stmt->bind_param("i", $advocateId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $monthlyData = array_fill(0, 12, 0); // Initialize with zeros for all months
    
    while ($row = $result->fetch_assoc()) {
        $monthIndex = (int)$row['month'] - 1; // Convert to 0-based index
        $monthlyData[$monthIndex] = (float)$row['total_amount'];
    }
    
    return $monthlyData;
}

// Get data for the dashboard
$financialSummary = getFinancialSummary($advocateId, $conn);
$recentInvoices = getRecentInvoices($advocateId, $conn);
$recentExpenses = getRecentExpenses($advocateId, $conn);
$monthlyIncomeData = getMonthlyIncomeData($advocateId, $conn);
$monthlyExpenseData = getMonthlyExpenseData($advocateId, $conn);

// Close database connection
$conn->close();
?>

<div class="mb-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Financial Dashboard</h1>
            <p class="text-gray-600">Manage your finances, invoices, and expenses</p>
        </div>
        
        <div class="mt-4 md:mt-0 flex flex-wrap gap-2">
            <a href="invoices/create.php" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-file-invoice-dollar mr-2"></i> Create Invoice
            </a>
            
            <a href="expenses/create.php" class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-plus mr-2"></i> Add Expense
            </a>
            
            <a href="reports.php" class="bg-purple-600 hover:bg-purple-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-chart-bar mr-2"></i> View Reports
            </a>
        </div>
    </div>
</div>

<!-- Financial Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
    <!-- Monthly Income -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 text-blue-500 mr-4">
                <i class="fas fa-dollar-sign text-xl"></i>
            </div>
            <div>
                <p class="text-gray-500 text-sm">Monthly Income</p>
                <p class="text-2xl font-semibold"><?php echo formatCurrency($financialSummary['current_month']['total_income']); ?></p>
            </div>
        </div>
        <div class="mt-4">
            <div class="flex justify-between text-sm">
                <span class="text-green-500">Paid: <?php echo formatCurrency($financialSummary['current_month']['income_paid']); ?></span>
                <span class="text-yellow-500">Pending: <?php echo formatCurrency($financialSummary['current_month']['income_pending']); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Monthly Expenses -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-red-100 text-red-500 mr-4">
                <i class="fas fa-file-invoice text-xl"></i>
            </div>
            <div>
                <p class="text-gray-500 text-sm">Monthly Expenses</p>
                <p class="text-2xl font-semibold"><?php echo formatCurrency($financialSummary['current_month']['total_expenses']); ?></p>
            </div>
        </div>
        <div class="mt-4">
            <div class="flex justify-between text-sm">
                <span class="<?php echo $financialSummary['current_month']['profit'] >= 0 ? 'text-green-500' : 'text-red-500'; ?>">
                    Profit: <?php echo formatCurrency($financialSummary['current_month']['profit']); ?>
                </span>
            </div>
        </div>
    </div>
    
    <!-- YTD Income -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-100 text-green-500 mr-4">
                <i class="fas fa-chart-line text-xl"></i>
            </div>
            <div>
                <p class="text-gray-500 text-sm">YTD Income</p>
                <p class="text-2xl font-semibold"><?php echo formatCurrency($financialSummary['ytd']['total_income']); ?></p>
            </div>
        </div>
        <div class="mt-4">
            <div class="flex justify-between text-sm">
                <span class="text-green-500">Paid: <?php echo formatCurrency($financialSummary['ytd']['income_paid']); ?></span>
            </div>
        </div>
    </div>
    
    <!-- YTD Profit -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-purple-100 text-purple-500 mr-4">
                <i class="fas fa-piggy-bank text-xl"></i>
            </div>
            <div>
                <p class="text-gray-500 text-sm">YTD Profit</p>
                <p class="text-2xl font-semibold <?php echo $financialSummary['ytd']['profit'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                    <?php echo formatCurrency($financialSummary['ytd']['profit']); ?>
                </p>
            </div>
        </div>
        <div class="mt-4">
            <div class="flex justify-between text-sm">
                <span class="text-gray-500">Expenses: <?php echo formatCurrency($financialSummary['ytd']['total_expenses']); ?></span>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Financial Chart -->
    <div class="bg-white rounded-lg shadow p-6 lg:col-span-2">
        <h2 class="text-lg font-semibold mb-4">Monthly Financial Overview</h2>
        <div class="h-80">
            <canvas id="financialChart"></canvas>
        </div>
    </div>
    
    <!-- Invoice Status -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-4">Invoice Status</h2>
               <h2 class="text-lg font-semibold mb-4">Invoice Status</h2>
        <div class="h-80">
            <canvas id="invoiceStatusChart"></canvas>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Recent Invoices -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="bg-blue-600 text-white px-6 py-4 flex justify-between items-center">
            <h2 class="text-lg font-semibold">Recent Invoices</h2>
            <a href="finance/invoices/index.php" class="text-white hover:text-blue-100">
                <i class="fas fa-external-link-alt"></i>
            </a>
        </div>
        
        <?php if (count($recentInvoices) > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Invoice #
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Client
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Amount
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Date
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($recentInvoices as $invoice): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <a href="finance/invoices/view.php?id=<?php echo $invoice['billing_id']; ?>" class="text-blue-600 hover:underline">
                                        INV-<?php echo str_pad($invoice['billing_id'], 5, '0', STR_PAD_LEFT); ?>
                                    </a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($invoice['client_name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo formatCurrency($invoice['amount']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo formatDate($invoice['billing_date']); ?>
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
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-8">
                <div class="text-gray-400 mb-2">
                    <i class="fas fa-file-invoice-dollar text-5xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-1">No invoices yet</h3>
                <p class="text-gray-500">Start by creating your first invoice</p>
                <div class="mt-4">
                    <a href="finance/invoices/create.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-plus mr-2"></i> Create Invoice
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Recent Expenses -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="bg-green-600 text-white px-6 py-4 flex justify-between items-center">
            <h2 class="text-lg font-semibold">Recent Expenses</h2>
            <a href="expenses/index.php" class="text-white hover:text-green-100">
                <i class="fas fa-external-link-alt"></i>
            </a>
        </div>
        
        <?php if (count($recentExpenses) > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Description
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Category
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Case
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Amount
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Date
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($recentExpenses as $expense): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($expense['description']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($expense['expense_category']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo !empty($expense['case_title']) ? htmlspecialchars($expense['case_title']) : 'N/A'; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo formatCurrency($expense['amount']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo formatDate($expense['expense_date']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-8">
                <div class="text-gray-400 mb-2">
                    <i class="fas fa-file-invoice text-5xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-1">No expenses recorded</h3>
                <p class="text-gray-500">Start tracking your expenses</p>
                <div class="mt-4">
                    <a href="expenses/create.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        <i class="fas fa-plus mr-2"></i> Add Expense
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- JavaScript for Charts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Financial Chart - Monthly Income vs Expenses
    const financialCtx = document.getElementById('financialChart').getContext('2d');
    const financialChart = new Chart(financialCtx, {
        type: 'bar',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            datasets: [
                {
                    label: 'Income',
                    data: <?php echo json_encode($monthlyIncomeData); ?>,
                    backgroundColor: 'rgba(59, 130, 246, 0.7)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Expenses',
                    data: <?php echo json_encode($monthlyExpenseData); ?>,
                    backgroundColor: 'rgba(239, 68, 68, 0.7)',
                    borderColor: 'rgba(239, 68, 68, 1)',
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
                            return '$' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
    
    // Invoice Status Chart
    const invoiceCtx = document.getElementById('invoiceStatusChart').getContext('2d');
    const invoiceStatusChart = new Chart(invoiceCtx, {
        type: 'doughnut',
        data: {
            labels: ['Paid', 'Pending', 'Overdue'],
            datasets: [{
                data: [
                    <?php echo $financialSummary['current_month']['income_paid']; ?>,
                    <?php echo $financialSummary['current_month']['income_pending']; ?>,
                    <?php echo $financialSummary['current_month']['income_overdue']; ?>
                ],
                backgroundColor: [
                    '#10B981', // green
                    '#F59E0B', // amber
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
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            let value = context.raw || 0;
                            let total = context.dataset.data.reduce((a, b) => a + b, 0);
                            let percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                            return `${label}: $${value.toLocaleString()} (${percentage}%)`;
                        }
                    }
                }
            },
            cutout: '70%'
        }
    });
});
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>