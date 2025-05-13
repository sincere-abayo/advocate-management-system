<div class="bg-gray-900 text-white w-64 space-y-2 py-5 px-2 absolute inset-y-0 left-0 transform -translate-x-full md:relative md:translate-x-0 transition duration-200 ease-in-out z-20 shadow-xl" id="sidebar">
    <!-- Profile Section -->
    <div class="px-4 mb-6">
        <div class="flex items-center space-x-3 mb-3">
            <div class="w-12 h-12 rounded-full bg-gradient-to-r from-blue-600 to-blue-400 flex items-center justify-center text-white font-bold text-xl shadow-md">
                <?php echo strtoupper(substr($_SESSION['full_name'] ?? 'A', 0, 1)); ?>
            </div>
            <div>
                <div class="text-sm font-semibold text-white"><?php echo $_SESSION['full_name'] ?? 'Advocate'; ?></div>
                <div class="text-xs text-gray-400 flex items-center">
                    <span class="inline-block w-2 h-2 rounded-full bg-green-500 mr-1"></span>
                    <span>Active Advocate</span>
                </div>
            </div>
        </div>
        <div class="border-b border-gray-700 mb-2"></div>
    </div>
    
    <!-- Navigation Menu -->
    <nav class="px-2 space-y-1">
        <!-- Dashboard -->
        <a href="<?php echo $path_url ?>advocate/index.php" class="group flex items-center px-3 py-2.5 rounded-lg transition duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'bg-gradient-to-r from-blue-700 to-blue-600 text-white shadow-md' : 'text-gray-300 hover:bg-gray-800'; ?>">
            <div class="flex items-center justify-center w-8">
                <i class="fas fa-tachometer-alt"></i>
            </div>
            <span class="ml-3 font-medium">Dashboard</span>
        </a>
        
      <!-- Cases -->
<div x-data="{ open: <?php echo strpos($_SERVER['PHP_SELF'], 'cases/') !== false ? 'true' : 'false'; ?> }">
    <button @click="open = !open" class="w-full group flex items-center px-3 py-2.5 rounded-lg transition duration-200 <?php echo strpos($_SERVER['PHP_SELF'], '/cases/') !== false ? 'bg-gradient-to-r from-blue-700 to-blue-600 text-white shadow-md' : 'text-gray-300 hover:bg-gray-800'; ?>">
        <div class="flex items-center justify-center w-8">
            <i class="fas fa-gavel"></i>
        </div>
        <span class="ml-3 font-medium">Cases</span>
        <i class="fas fa-chevron-down ml-auto transition-transform duration-200" :class="open ? 'transform rotate-180' : ''"></i>
    </button>
    <div x-show="open" class="pl-10 mt-1 space-y-1" style="display: none;">
        <a href="<?php echo $path_url ?>advocate/cases/index.php" class="block px-3 py-2 rounded-lg text-sm text-gray-400 hover:text-white hover:bg-gray-800 transition duration-200">
            All Cases
        </a>
       
        <a href="<?php echo $path_url ?>advocate/cases/active.php" class="hidden px-3 py-2 rounded-lg text-sm text-gray-400 hover:text-white hover:bg-gray-800 transition duration-200">
            Active Cases
        </a>
        <a href="<?php echo $path_url ?>advocate/cases/hearings.php" class="block px-3 py-2 rounded-lg text-sm text-gray-400 hover:text-white hover:bg-gray-800 transition duration-200">
            Upcoming Hearings
        </a>
    </div>
</div>

        <!-- Clients -->
        <div x-data="{ open: <?php echo strpos($_SERVER['PHP_SELF'], '/clients/') !== false ? 'true' : 'false'; ?> }">
            <button @click="open = !open" class="w-full group flex items-center px-3 py-2.5 rounded-lg transition duration-200 <?php echo strpos($_SERVER['PHP_SELF'], '/clients/') !== false ? 'bg-gradient-to-r from-blue-700 to-blue-600 text-white shadow-md' : 'text-gray-300 hover:bg-gray-800'; ?>">
                <div class="flex items-center justify-center w-8">
                    <i class="fas fa-users"></i>
                </div>
                <span class="ml-3 font-medium">Clients</span>
                <i class="fas fa-chevron-down ml-auto transition-transform duration-200" :class="open ? 'transform rotate-180' : ''"></i>
            </button>
            <div x-show="open" class="pl-10 mt-1 space-y-1" style="display: none;">
                <a href="<?php echo $path_url ?>advocate/clients/index.php" class="block px-3 py-2 rounded-lg text-sm text-gray-400 hover:text-white hover:bg-gray-800 transition duration-200">
                    All Clients
                </a>
                <a href="<?php echo $path_url ?>advocate/clients/add.php" class="block px-3 py-2 rounded-lg text-sm text-gray-400 hover:text-white hover:bg-gray-800 transition duration-200">
                    Add Client
                </a>
            </div>
        </div>
        
        <!-- Appointments -->
        <a href="<?php echo $path_url ?>advocate/appointments/index.php" class="group flex items-center px-3 py-2.5 rounded-lg transition duration-200 <?php echo strpos($_SERVER['PHP_SELF'], '/appointments/') !== false ? 'bg-gradient-to-r from-blue-700 to-blue-600 text-white shadow-md' : 'text-gray-300 hover:bg-gray-800'; ?>">
            <div class="flex items-center justify-center w-8">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <span class="ml-3 font-medium">Appointments</span>
        </a>
        
        <!-- Documents -->
        <a href="<?php echo $path_url ?>advocate/documents/index.php" class="group flex items-center px-3 py-2.5 rounded-lg transition duration-200 <?php echo strpos($_SERVER['PHP_SELF'], '/documents/') !== false ? 'bg-gradient-to-r from-blue-700 to-blue-600 text-white shadow-md' : 'text-gray-300 hover:bg-gray-800'; ?>">
            <div class="flex items-center justify-center w-8">
                <i class="fas fa-file-alt"></i>
            </div>
            <span class="ml-3 font-medium">Documents</span>
        </a>
        
        <!-- Time Tracking -->
        <a href="<?php echo $path_url ?>advocate/time-tracking/index.php" class="group hidden items-center px-3 py-2.5 rounded-lg transition duration-200 <?php echo strpos($_SERVER['PHP_SELF'], '/time-tracking/') !== false ? 'bg-gradient-to-r from-blue-700 to-blue-600 text-white shadow-md' : 'text-gray-300 hover:bg-gray-800'; ?>">
            <div class="flex items-center justify-center w-8">
                <i class="fas fa-clock"></i>
            </div>
            <span class="ml-3 font-medium hidden">Time Tracking</span>
        </a>
        
      <!-- Finance -->
<div x-data="{ open: <?php echo strpos($_SERVER['PHP_SELF'], '/finance/') !== false ? 'true' : 'false'; ?> }">
    <button @click="open = !open" class="w-full group flex items-center px-3 py-2.5 rounded-lg transition duration-200 <?php echo strpos($_SERVER['PHP_SELF'], '/finance/') !== false ? 'bg-gradient-to-r from-blue-700 to-blue-600 text-white shadow-md' : 'text-gray-300 hover:bg-gray-800'; ?>">
        <div class="flex items-center justify-center w-8">
            <i class="fas fa-dollar-sign"></i>
        </div>
        <span class="ml-3 font-medium">Finance</span>
        <i class="fas fa-chevron-down ml-auto transition-transform duration-200" :class="open ? 'transform rotate-180' : ''"></i>
    </button>
    <div x-show="open" class="pl-10 mt-1 space-y-1" style="display: none;">
        <a href="<?php echo $path_url ?>advocate/finance/index.php" class="block px-3 py-2 rounded-lg text-sm text-gray-400 hover:text-white hover:bg-gray-800 transition duration-200">
            Dashboard
        </a>
        <a href="<?php echo $path_url ?>advocate/finance/invoices/index.php" class="block px-3 py-2 rounded-lg text-sm text-gray-400 hover:text-white hover:bg-gray-800 transition duration-200">
            Invoices
        </a>
        <a href="<?php echo $path_url ?>advocate/finance/expenses/index.php" class="block px-3 py-2 rounded-lg text-sm text-gray-400 hover:text-white hover:bg-gray-800 transition duration-200">
            Expenses
        </a>
        <a href="<?php echo $path_url ?>advocate/finance/reports.php" class="block px-3 py-2 rounded-lg text-sm text-gray-400 hover:text-white hover:bg-gray-800 transition duration-200">
            Financial Reports
        </a>
    </div>
</div>

        
<?php 
// Display unread message count if any
$unreadMessages = 0;
if (isLoggedIn()) {
    $conn = getDBConnection();
    $userId = $_SESSION['user_id'];
    
    $unreadQuery = "
        SELECT COUNT(*) as count
        FROM messages m
        JOIN conversations c ON m.conversation_id = c.conversation_id
        WHERE m.is_read = 0 
        AND m.sender_id != ? 
        AND (c.initiator_id = ? OR c.recipient_id = ?)
    ";
    
    $stmt = $conn->prepare($unreadQuery);
    $stmt->bind_param("iii", $userId, $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $unreadMessages = $row['count'];
}

if ($unreadMessages > 0): 
?>
<!-- Messages -->
<a href="<?php echo $path_url ?>advocate/messages/index.php" class="group flex items-center px-3 py-2.5 rounded-lg transition duration-200 <?php echo strpos($_SERVER['PHP_SELF'], '/messages/') !== false ? 'bg-gradient-to-r from-blue-700 to-blue-600 text-white shadow-md' : 'text-gray-300 hover:bg-gray-800'; ?>">
    <div class="flex items-center justify-center w-8">
        <i class="fas fa-envelope"></i>
    </div>
    <span class="ml-3 font-medium">Messages</span>
    <?php if ($unreadMessages > 0): ?>
    <span class="ml-auto bg-red-500 text-white text-xs font-semibold px-2 py-0.5 rounded-full">
        <?php echo $unreadMessages; ?>
    </span>
    <?php endif; ?>
</a>

<?php endif; ?>

        
        <!-- Reports -->
        <a href="<?php echo $path_url ?>advocate/reports/index.php" class="group flex items-center px-3 py-2.5 rounded-lg transition duration-200 <?php echo strpos($_SERVER['PHP_SELF'], '/reports/') !== false ? 'bg-gradient-to-r from-blue-700 to-blue-600 text-white shadow-md' : 'text-gray-300 hover:bg-gray-800'; ?>">
            <div class="flex items-center justify-center w-8">
                <i class="fas fa-chart-bar"></i>
            </div>
            <span class="ml-3 font-medium">Reports</span>
        </a>
        
        <!-- Settings -->
        <a href="<?php echo $path_url ?>advocate/settings/index.php" class="hidden group flex items-center px-3 py-2.5 rounded-lg transition duration-200 <?php echo strpos($_SERVER['PHP_SELF'], '/settings/') !== false ? 'bg-gradient-to-r from-blue-700 to-blue-600 text-white shadow-md' : 'text-gray-300 hover:bg-gray-800'; ?>">
            <div class="flex items-center justify-center w-8">
                <i class="fas fa-cog"></i>
            </div>
            <span class="ml-3 font-medium">Settings</span>
        </a>
    </nav>
    
    <!-- Bottom Section -->
    <div class="mt-auto pt-4 px-4">
        <div class="border-t border-gray-700 pt-4">
            <a href="<?= $path_url ?>includes/logout_handler.php" class="flex items-center px-3 py-2.5 rounded-lg text-red-400 hover:bg-red-900 hover:bg-opacity-30 hover:text-red-300 transition duration-200">
                <div class="flex items-center justify-center w-8">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                <span class="ml-3 font-medium">Logout</span>
            </a>
        </div>

        
        <!-- System Status -->
        <div class="mt-4 bg-gray-800 rounded-lg p-3 text-xs text-gray-400">
            <div class="flex items-center justify-between mb-2">
                <span>System Status</span>
                <span class="flex items-center text-green-400">
                    <span class="w-2 h-2 bg-green-400 rounded-full mr-1"></span>
                    Online
                </span>
            </div>
            <div class="flex items-center justify-between">
                <span>Version</span>
                <span>1.2.5</span>
            </div>
        </div>
    </div>
</div>
