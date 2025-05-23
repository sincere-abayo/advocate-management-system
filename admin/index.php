<?php
// error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Include necessary files
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';


// Check if user is logged in and is an admin
requireLogin();
requireUserType('admin');

// Get database connection
$conn = getDBConnection();

// Get system statistics
// 1. User counts
$userCountsQuery = "
    SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN user_type = 'advocate' THEN 1 ELSE 0 END) as advocate_count,
        SUM(CASE WHEN user_type = 'client' THEN 1 ELSE 0 END) as client_count,
        SUM(CASE WHEN user_type = 'admin' THEN 1 ELSE 0 END) as admin_count,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_users
    FROM users
";
$userCountsResult = $conn->query($userCountsQuery);
$userCounts = $userCountsResult->fetch_assoc();

// 2. Case statistics
$caseStatsQuery = "
    SELECT 
        COUNT(*) as total_cases,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_cases,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_cases,
        SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_cases,
        SUM(CASE WHEN status = 'won' THEN 1 ELSE 0 END) as won_cases,
        SUM(CASE WHEN status = 'lost' THEN 1 ELSE 0 END) as lost_cases,
        SUM(CASE WHEN status = 'settled' THEN 1 ELSE 0 END) as settled_cases
    FROM cases
";
$caseStatsResult = $conn->query($caseStatsQuery);
$caseStats = $caseStatsResult->fetch_assoc();

// 3. Financial summary
$financialSummaryQuery = "
    SELECT 
        SUM(amount) as total_billed,
        SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as total_paid,
        SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as total_pending,
        SUM(CASE WHEN status = 'overdue' THEN amount ELSE 0 END) as total_overdue
    FROM billings
";
$financialSummaryResult = $conn->query($financialSummaryQuery);
$financialSummary = $financialSummaryResult->fetch_assoc();

// 4. Recent users
$recentUsersQuery = "
    SELECT 
        u.user_id, 
        u.username, 
        u.full_name, 
        u.email, 
        u.user_type, 
        u.status, 
        u.created_at
    FROM users u
    ORDER BY u.created_at DESC
    LIMIT 5
";
$recentUsersResult = $conn->query($recentUsersQuery);

// 5. Recent cases
$recentCasesQuery = "
    SELECT 
        c.case_id, 
        c.case_number, 
        c.title, 
        c.status, 
        c.filing_date,
        u.full_name as client_name
    FROM cases c
    JOIN client_profiles cp ON c.client_id = cp.client_id
    JOIN users u ON cp.user_id = u.user_id
    ORDER BY c.filing_date DESC
    LIMIT 5
";
$recentCasesResult = $conn->query($recentCasesQuery);

// 6. Recent activities
$recentActivitiesQuery = "
    SELECT 
        ca.activity_id, 
        ca.case_id, 
        ca.activity_type, 
        ca.description, 
        ca.activity_date,
        u.full_name as user_name,
        c.case_number,
        c.title as case_title
    FROM case_activities ca
    JOIN users u ON ca.user_id = u.user_id
    JOIN cases c ON ca.case_id = c.case_id
    ORDER BY ca.activity_date DESC
    LIMIT 10
";
$recentActivitiesResult = $conn->query($recentActivitiesQuery);

// 7. Pending advocate approvals
$pendingAdvocatesQuery = "
    SELECT 
        u.user_id, 
        u.full_name, 
        u.email, 
        u.created_at,
        ap.license_number,
        ap.specialization
    FROM users u
    JOIN advocate_profiles ap ON u.user_id = ap.user_id
    WHERE u.user_type = 'advocate' AND u.status = 'pending'
    ORDER BY u.created_at DESC
";
$pendingAdvocatesResult = $conn->query($pendingAdvocatesQuery);
$pendingAdvocatesCount = $pendingAdvocatesResult->num_rows;



// Set page title
$pageTitle = "Admin Dashboard";
include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Admin Dashboard</h1>
            <p class="text-gray-600">Welcome to the Advocate Management System</p>
        </div>

        <div class="mt-4 md:mt-0 flex space-x-3">
            <a href="users/index.php"
                class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-user-plus mr-2"></i> View users
            </a>
            <a href="settings/index.php"
                class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-cog mr-2"></i> Settings
            </a>
        </div>
    </div>

    <?php if ($pendingAdvocatesCount > 0): ?>
    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-8">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-triangle text-yellow-400"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-yellow-700">
                    There are <span class="font-medium"><?php echo $pendingAdvocatesCount; ?></span> advocate
                    registrations pending approval.
                    <a href="advocates/pending.php" class="font-medium underline text-yellow-700 hover:text-yellow-600">
                        Review now
                    </a>
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- User Statistics -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-500 mr-4">
                    <i class="fas fa-users text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Total Users</p>
                    <p class="text-2xl font-semibold"><?php echo number_format($userCounts['total_users']); ?></p>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex justify-between text-sm">
                    <span class="text-blue-500"><?php echo number_format($userCounts['advocate_count']); ?>
                        Advocates</span>
                    <span class="text-green-500"><?php echo number_format($userCounts['client_count']); ?>
                        Clients</span>
                    <span class="text-purple-500"><?php echo number_format($userCounts['admin_count']); ?> Admins</span>
                </div>
            </div>
        </div>

        <!-- Case Statistics -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-500 mr-4">
                    <i class="fas fa-briefcase text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Total Cases</p>
                    <p class="text-2xl font-semibold"><?php echo number_format($caseStats['total_cases']); ?></p>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex justify-between text-sm">
                    <span class="text-yellow-500"><?php echo number_format($caseStats['pending_cases']); ?>
                        Pending</span>
                    <span class="text-blue-500"><?php echo number_format($caseStats['active_cases']); ?> Active</span>
                    <span class="text-gray-500"><?php echo number_format($caseStats['closed_cases']); ?> Closed</span>
                </div>
            </div>
        </div>

        <!-- Financial Statistics -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-indigo-100 text-indigo-500 mr-4">
                    <i class="fas fa-file-invoice-dollar text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Total Billed</p>
                    <p class="text-2xl font-semibold"><?php echo formatCurrency($financialSummary['total_billed']); ?>
                    </p>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex justify-between text-sm">
                    <span class="text-green-500"><?php echo formatCurrency($financialSummary['total_paid']); ?>
                        Paid</span>
                    <span class="text-yellow-500"><?php echo formatCurrency($financialSummary['total_pending']); ?>
                        Pending</span>
                    <span class="text-red-500"><?php echo formatCurrency($financialSummary['total_overdue']); ?>
                        Overdue</span>
                </div>
            </div>
        </div>

        <!-- Case Outcomes -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-red-100 text-red-500 mr-4">
                    <i class="fas fa-chart-pie text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Case Outcomes</p>
                    <p class="text-2xl font-semibold"><?php echo number_format($caseStats['closed_cases']); ?> Closed
                    </p>
                </div>
            </div>
            <div class="mt-4">
                <div class="flex justify-between text-sm">
                    <span class="text-green-500"><?php echo number_format($caseStats['won_cases']); ?> Won</span>
                    <span class="text-red-500"><?php echo number_format($caseStats['lost_cases']); ?> Lost</span>
                    <span class="text-blue-500"><?php echo number_format($caseStats['settled_cases']); ?> Settled</span>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <!-- Recent Users -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-800">Recent Users</h2>
                <a href="users/index.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All</a>
            </div>

            <div class="divide-y divide-gray-200">
                <?php if ($recentUsersResult->num_rows === 0): ?>
                <div class="px-6 py-4 text-center text-gray-500">No users found</div>
                <?php else: ?>
                <?php while ($user = $recentUsersResult->fetch_assoc()): ?>
                <div class="px-6 py-4 flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center">
                            <i class="fas fa-user text-gray-500"></i>
                        </div>
                    </div>
                    <div class="ml-4 flex-1">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($user['full_name']); ?></h3>
                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($user['email']); ?></p>
                            </div>
                            <div class="flex items-center">
                                <?php
                                        $statusClass = 'bg-gray-100 text-gray-800';
                                        switch ($user['status']) {
                                            case 'active':
                                                $statusClass = 'bg-green-100 text-green-800';
                                                break;
                                            case 'pending':
                                                $statusClass = 'bg-yellow-100 text-yellow-800';
                                                break;
                                            case 'suspended':
                                                $statusClass = 'bg-red-100 text-red-800';
                                                break;
                                        }
                                        
                                        $typeClass = 'bg-gray-100 text-gray-800';
                                        switch ($user['user_type']) {
                                            case 'advocate':
                                                $typeClass = 'bg-blue-100 text-blue-800';
                                                break;
                                            case 'client':
                                                $typeClass = 'bg-green-100 text-green-800';
                                                break;
                                            case 'admin':
                                                $typeClass = 'bg-purple-100 text-purple-800';
                                                break;
                                        }
                                        ?>
                                <span
                                    class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $typeClass; ?> mr-2">
                                    <?php echo ucfirst($user['user_type']); ?>
                                </span>
                                <span
                                    class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="mt-1 text-xs text-gray-500">
                            Joined <?php echo formatDateTimeRelative($user['created_at']); ?>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Cases -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-800">Recent Cases</h2>
                <a href="cases/index.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All</a>
            </div>

            <div class="divide-y divide-gray-200">
                <?php if ($recentCasesResult->num_rows === 0): ?>
                <div class="px-6 py-4 text-center text-gray-500">No cases found</div>
                <?php else: ?>
                <?php while ($case = $recentCasesResult->fetch_assoc()): ?>
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-medium text-gray-900">
                                <a href="cases/view.php?id=<?php echo $case['case_id']; ?>" class="hover:underline">
                                    <?php echo htmlspecialchars($case['case_number']); ?>:
                                    <?php echo htmlspecialchars($case['title']); ?>
                                </a>
                            </h3>
                            <p class="text-sm text-gray-500">Client:
                                <?php echo htmlspecialchars($case['client_name']); ?></p>
                        </div>
                        <div>
                            <?php
                                    $caseStatusClass = 'bg-gray-100 text-gray-800';
                                    switch ($case['status']) {
                                        case 'pending':
                                            $caseStatusClass = 'bg-yellow-100 text-yellow-800';
                                            break;
                                        case 'active':
                                            $caseStatusClass = 'bg-blue-100 text-blue-800';
                                            break;
                                        case 'closed':
                                            $caseStatusClass = 'bg-gray-100 text-gray-800';
                                            break;
                                        case 'won':
                                            $caseStatusClass = 'bg-green-100 text-green-800';
                                            break;
                                        case 'lost':
                                            $caseStatusClass = 'bg-red-100 text-red-800';
                                            break;
                                        case 'settled':
                                            $caseStatusClass = 'bg-indigo-100 text-indigo-800';
                                            break;
                                    }
                                    ?>
                            <span
                                class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $caseStatusClass; ?>">
                                <?php echo ucfirst($case['status']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="mt-1 text-xs text-gray-500">
                        Filed on <?php echo formatDate($case['filing_date']); ?>
                    </div>
                </div>
                <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Activities -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h2 class="text-lg font-semibold text-gray-800">Recent Activities</h2>
        </div>

        <div class="divide-y divide-gray-200">
            <?php if ($recentActivitiesResult->num_rows === 0): ?>
            <div class="px-6 py-4 text-center text-gray-500">No activities found</div>
            <?php else: ?>
            <?php while ($activity = $recentActivitiesResult->fetch_assoc()): ?>
            <div class="px-6 py-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <?php
                                $activityIconClass = 'fas fa-info-circle text-blue-500';
                                switch ($activity['activity_type']) {
                                    case 'update':
                                        $activityIconClass = 'fas fa-edit text-blue-500';
                                        break;
                                    case 'document':
                                        $activityIconClass = 'fas fa-file-alt text-yellow-500';
                                        break;
                                    case 'hearing':
                                        $activityIconClass = 'fas fa-gavel text-purple-500';
                                        break;
                                    case 'note':
                                        $activityIconClass = 'fas fa-sticky-note text-green-500';
                                        break;
                                    case 'status_change':
                                        $activityIconClass = 'fas fa-exchange-alt text-red-500';
                                        break;
                                }
                                ?>
                        <div class="h-8 w-8 rounded-full bg-gray-100 flex items-center justify-center">
                            <i class="<?php echo $activityIconClass; ?>"></i>
                        </div>
                    </div>
                    <div class="ml-4 flex-1">
                        <div class="text-sm text-gray-900">
                            <span class="font-medium"><?php echo htmlspecialchars($activity['user_name']); ?></span>
                            <span class="text-gray-600">
                                <?php
                                        switch ($activity['activity_type']) {
                                            case 'update':
                                                echo 'updated';
                                                break;
                                            case 'document':
                                                echo 'uploaded a document to';
                                                break;
                                            case 'hearing':
                                                echo 'added a hearing to';
                                                break;
                                            case 'note':
                                                echo 'added a note to';
                                                break;
                                            case 'status_change':
                                                echo 'changed the status of';
                                                break;
                                            default:
                                                echo 'modified';
                                        }
                                        ?>
                            </span>
                            <a href="cases/view.php?id=<?php echo $activity['case_id']; ?>"
                                class="font-medium text-blue-600 hover:underline">
                                <?php echo htmlspecialchars($activity['case_number']); ?>
                            </a>
                        </div>
                        <div class="mt-1 text-sm text-gray-600">
                            <?php echo htmlspecialchars($activity['description']); ?>
                        </div>
                        <div class="mt-1 text-xs text-gray-500">
                            <?php echo formatDateTimeRelative($activity['activity_date']); ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Pending Advocate Approvals -->
    <?php if ($pendingAdvocatesCount > 0): ?>
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-800">Pending Advocate Approvals</h2>
            <a href="advocates/pending.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All</a>
        </div>

        <div class="divide-y divide-gray-200">
            <?php while ($advocate = $pendingAdvocatesResult->fetch_assoc()): ?>
            <div class="px-6 py-4 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-medium text-gray-900">
                        <?php echo htmlspecialchars($advocate['full_name']); ?></h3>
                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($advocate['email']); ?></p>
                    <div class="mt-1 text-xs text-gray-500">
                        <span class="font-medium">License:</span>
                        <?php echo htmlspecialchars($advocate['license_number']); ?> |
                        <span class="font-medium">Specialization:</span>
                        <?php echo htmlspecialchars($advocate['specialization']); ?> |
                        <span class="font-medium">Registered:</span>
                        <?php echo formatDateTimeRelative($advocate['created_at']); ?>
                    </div>
                </div>
                <div class="flex space-x-2">
                    <a href="advocates/approve.php?id=<?php echo $advocate['user_id']; ?>&action=approve"
                        class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium py-2 px-4 rounded-lg">
                        Approve
                    </a>
                    <a href="advocates/approve.php?id=<?php echo $advocate['user_id']; ?>&action=reject"
                        class="bg-red-600 hover:bg-red-700 text-white text-sm font-medium py-2 px-4 rounded-lg"
                        onclick="return confirm('Are you sure you want to reject this advocate?');">
                        Reject
                    </a>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
// Include footer
include 'includes/footer.php';
?>