<div class="bg-gray-900 text-white w-64 space-y-2 py-5 px-2 absolute inset-y-0 left-0 transform -translate-x-full md:relative md:translate-x-0 transition duration-200 ease-in-out z-20 shadow-xl" id="sidebar">
    <!-- Profile Section -->
    <div class="px-4 mb-6">
        <div class="flex items-center space-x-3 mb-3">
            <div class="w-12 h-12 rounded-full bg-gradient-to-r from-green-600 to-green-400 flex items-center justify-center text-white font-bold text-xl shadow-md">
                <?php echo strtoupper(substr($_SESSION['full_name'] ?? 'C', 0, 1)); ?>
            </div>
            <div>
                <div class="text-sm font-semibold text-white"><?php echo $_SESSION['full_name'] ?? 'Client'; ?></div>
                <div class="text-xs text-gray-400 flex items-center">
                    <span class="inline-block w-2 h-2 rounded-full bg-green-500 mr-1"></span>
                    <span>Client Portal</span>
                </div>
            </div>
        </div>
        <div class="border-b border-gray-700 mb-2"></div>
    </div>
    
    <!-- Navigation Menu -->
    <nav class="px-2 space-y-1">
        <!-- Dashboard -->
        <a href="<?php echo $path_url ?>client/index.php" class="group flex items-center px-3 py-2.5 rounded-lg transition duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'bg-gradient-to-r from-green-700 to-green-600 text-white shadow-md' : 'text-gray-300 hover:bg-gray-800'; ?>">
            <div class="flex items-center justify-center w-8">
                <i class="fas fa-tachometer-alt"></i>
            </div>
            <span class="ml-3 font-medium">Dashboard</span>
        </a>
        
        <!-- My Cases -->
        <div x-data="{ open: <?php echo strpos($_SERVER['PHP_SELF'], '/cases/') !== false ? 'true' : 'false'; ?> }">
            <button @click="open = !open" class="w-full group flex items-center px-3 py-2.5 rounded-lg transition duration-200 <?php echo strpos($_SERVER['PHP_SELF'], '/cases/') !== false ? 'bg-gradient-to-r from-green-700 to-green-600 text-white shadow-md' : 'text-gray-300 hover:bg-gray-800'; ?>">
                <div class="flex items-center justify-center w-8">
                    <i class="fas fa-briefcase"></i>
                </div>
                <span class="ml-3 font-medium">My Cases</span>
                <i class="fas fa-chevron-down ml-auto transition-transform duration-200" :class="open ? 'transform rotate-180' : ''"></i>
            </button>
            <div x-show="open" class="pl-10 mt-1 space-y-1" style="display: none;">
                <a href="<?php echo $path_url ?>client/cases/index.php" class="block px-3 py-2 rounded-lg text-sm text-gray-400 hover:text-white hover:bg-gray-800 transition duration-200">
                    All Cases
                </a>
                <a href="<?php echo $path_url ?>client/cases/active.php" class="hidden px-3 py-2 rounded-lg text-sm text-gray-400 hover:text-white hover:bg-gray-800 transition duration-200">
                    Active Cases
                </a>
                <a href="<?php echo $path_url ?>client/cases/hearings.php" class="block px-3 py-2 rounded-lg text-sm text-gray-400 hover:text-white hover:bg-gray-800 transition duration-200">
                    Upcoming Hearings
                </a>
            </div>
        </div>
        
        <!-- Documents -->
        <a href="<?php echo $path_url ?>client/documents/index.php" class="group flex items-center px-3 py-2.5 rounded-lg transition duration-200 <?php echo strpos($_SERVER['PHP_SELF'], '/documents/') !== false ? 'bg-gradient-to-r from-green-700 to-green-600 text-white shadow-md' : 'text-gray-300 hover:bg-gray-800'; ?>">
            <div class="flex items-center justify-center w-8">
                <i class="fas fa-file-alt"></i>
            </div>
            <span class="ml-3 font-medium">Documents</span>
        </a>
        
        <!-- Appointments -->
        <a href="<?php echo $path_url ?>client/appointments/index.php" class="group flex items-center px-3 py-2.5 rounded-lg transition duration-200 <?php echo strpos($_SERVER['PHP_SELF'], '/appointments/') !== false ? 'bg-gradient-to-r from-green-700 to-green-600 text-white shadow-md' : 'text-gray-300 hover:bg-gray-800'; ?>">
            <div class="flex items-center justify-center w-8">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <span class="ml-3 font-medium">Appointments</span>
        </a>
        
        <!-- Invoices & Payments -->
        <div>
            <button @click="open = !open" class="w-full group flex items-center px-3 py-2.5 rounded-lg transition duration-200 <?php echo strpos($_SERVER['PHP_SELF'], '/invoices/') !== false ? 'bg-gradient-to-r from-green-700 to-green-600 text-white shadow-md' : 'text-gray-300 hover:bg-gray-800'; ?>">
                <div class="flex items-center justify-center w-8">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <span class="ml-3 font-medium text-sm">Invoices & Payments </span>
                <i class="fas fa-chevron-down ml-auto transition-transform duration-200" :class="open ? 'transform rotate-180' : ''"></i>
            </button>
            <div x-show="open" class="pl-10 mt-1 space-y-1" style="display: none;">
                <a href="<?php echo $path_url ?>client/invoices/index.php" class="block px-3 py-2 rounded-lg text-sm text-gray-400 hover:text-white hover:bg-gray-800 transition duration-200">
                    All Invoices
                </a>
                <a href="<?php echo $path_url ?>client/invoices/pending.php" class="hidden  px-3 py-2 rounded-lg text-sm text-gray-400 hover:text-white hover:bg-gray-800 transition duration-200">
                    Pending Payments
                </a>
                <a href="<?php echo $path_url ?>client/invoices/payment-history.php" class="block px-3 py-2 rounded-lg text-sm text-gray-400 hover:text-white hover:bg-gray-800 transition duration-200">
                    Payment History
                </a>
            </div>
        </div>
        
        <!-- Messages -->
        <a href="<?php echo $path_url ?>client/messages/index.php" class="group flex items-center px-3 py-2.5 rounded-lg transition duration-200 <?php echo strpos($_SERVER['PHP_SELF'], '/messages/') !== false ? 'bg-gradient-to-r from-green-700 to-green-600 text-white shadow-md' : 'text-gray-300 hover:bg-gray-800'; ?>">
            <div class="flex items-center justify-center w-8">
                <i class="fas fa-envelope"></i>
            </div>
            <span class="ml-3 font-medium">Messages</span>
            <?php 
            // Display unread message count if any
            if (isset($unreadMessages) && $unreadMessages > 0): 
            ?>
            <span class="ml-auto bg-red-500 text-white text-xs font-semibold px-2 py-0.5 rounded-full">
                <?php echo $unreadMessages; ?>
            </span>
            <?php endif; ?>
        </a>
        
        <!-- Profile -->
        <a href="<?php echo $path_url ?>client/profile.php" class="group flex items-center px-3 py-2.5 rounded-lg transition duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'bg-gradient-to-r from-green-700 to-green-600 text-white shadow-md' : 'text-gray-300 hover:bg-gray-800'; ?>">
            <div class="flex items-center justify-center w-8">
                <i class="fas fa-user"></i>
            </div>
            <span class="ml-3 font-medium">My Profile</span>
        </a>
    </nav>
    
    <div class="mt-auto pt-4 px-4">
        <div class="border-t border-gray-700 pt-4">
            <a href="<?= $path_url ?>includes/logout_handler.php" class="flex items-center px-3 py-2.5 rounded-lg text-red-400 hover:bg-red-900 hover:bg-opacity-30 hover:text-red-300 transition duration-200">
                <div class="flex items-center justify-center w-8">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                <span class="ml-3 font-medium">Logout</span>
            </a>
        </div>
    </div>
</div>
