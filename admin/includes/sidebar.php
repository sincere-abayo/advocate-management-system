<div class="bg-gray-900 text-white w-64 space-y-2 py-5 px-2 absolute inset-y-0 left-0 transform -translate-x-full md:relative md:translate-x-0 transition duration-200 ease-in-out z-20 shadow-xl" id="sidebar">
    <!-- Sidebar for desktop -->
    <div class="hidden md:flex md:flex-col md:h-full bg-gray-900">
        <div class="flex-1 flex flex-col min-h-0 overflow-y-auto">
            <!-- Logo -->
            <div class="flex items-center h-16 px-4 bg-gray-900 border-b border-gray-800">
                <a href="<?php echo $path_url; ?>admin/index.php" class="flex items-center text-white">
                    <i class="fas fa-gavel text-2xl text-blue-500 mr-2"></i>
                    <span class="text-xl font-bold">AMS Admin</span>
                </a>
            </div>
            
            <!-- Navigation -->
            <nav class="flex-1 px-2 py-4 space-y-1 hidden">
                <!-- Dashboard -->
                <a href="<?php echo $path_url; ?>admin/index.php" class="group flex items-center px-3 py-2.5 rounded-lg transition duration-200 <?php echo $currentPage === 'index.php' ? 'bg-gradient-to-r from-blue-700 to-blue-600 text-white shadow-md' : 'text-gray-300 hover:bg-gray-800'; ?>">
                    <div class="flex items-center justify-center w-8">
                        <i class="fas fa-tachometer-alt"></i>
                    </div>
                    <span class="ml-3 font-medium">Dashboard</span>
                </a>
                
                <!-- User Management -->
                <a href="<?php echo $path_url; ?>admin/users/index.php" class="w-full group flex items-center px-3 py-2.5 rounded-lg transition duration-200 <?php echo strpos($_SERVER['PHP_SELF'], '/users/') !== false ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-800'; ?>">
                    <div class="flex items-center justify-center w-8">
                        <i class="fas fa-users"></i>
                    </div>
                    <span class="ml-3 font-medium">All Users</span>
                </a>          
                      <!-- hidden block -->
                <!-- Advocate Management -->
                <div x-data="{ open: <?php echo strpos($_SERVER['PHP_SELF'], '/advocates/') !== false ? 'true' : 'false'; ?> }" class="hidden">
                    <button @click="open = !open" class="w-full group flex items-center px-3 py-2.5 rounded-lg transition duration-200 <?php echo strpos($_SERVER['PHP_SELF'], '/advocates/') !== false ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-800'; ?>">
                        <div class="flex items-center justify-center w-8">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <span class="ml-3 font-medium">Advocates</span>
                        <i class="fas fa-chevron-down ml-auto transform transition-transform duration-200" :class="open ? 'rotate-180' : ''"></i>
                    </button>
                    
                    <div x-show="open" class="mt-2 space-y-1 px-3">
                        <a href="<?php echo $path_url; ?>admin/advocates/index.php" class="group flex items-center pl-11 pr-2 py-2 rounded-md text-sm font-medium <?php echo $currentPage === 'index.php' && strpos($_SERVER['PHP_SELF'], '/advocates/') !== false ? 'bg-gray-700 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-white'; ?>">
                            All Advocates
                        </a>
                        <a href="<?php echo $path_url; ?>admin/advocates/pending.php" class="group flex items-center pl-11 pr-2 py-2 rounded-md text-sm font-medium <?php echo $currentPage === 'pending.php' && strpos($_SERVER['PHP_SELF'], '/advocates/') !== false ? 'bg-gray-700 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-white'; ?>">
                            Pending Approvals
                        </a>
                    </div>
                </div>
                
                <!-- Client Management -->
                <div x-data="{ open: <?php echo strpos($_SERVER['PHP_SELF'], '/clients/') !== false ? 'true' : 'false'; ?> }" class="hidden">
                    <button @click="open = !open" class="w-full group flex items-center px-3 py-2.5 rounded-lg transition duration-200 <?php echo strpos($_SERVER['PHP_SELF'], '/clients/') !== false ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-800'; ?>">
                        <div class="flex items-center justify-center w-8">
                            <i class="fas fa-user-friends"></i>
                        </div>
                        <span class="ml-3 font-medium">Clients</span>
                        <i class="fas fa-chevron-down ml-auto transform transition-transform duration-200" :class="open ? 'rotate-180' : ''"></i>
                    </button>
                    
                    <div x-show="open" class="mt-2 space-y-1 px-3">
                        <a href="<?php echo $path_url; ?>admin/clients/index.php" class="group flex items-center pl-11 pr-2 py-2 rounded-md text-sm font-medium <?php echo $currentPage === 'index.php' && strpos($_SERVER['PHP_SELF'], '/clients/') !== false ? 'bg-gray-700 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-white'; ?>">
                            All Clients
                        </a>
                    </div>
                </div>
                
                <!-- Case Management -->
                <div x-data="{ open: <?php echo strpos($_SERVER['PHP_SELF'], '/cases/') !== false ? 'true' : 'false'; ?> }" class="hidden">
                    <button @click="open = !open" class="w-full group flex items-center px-3 py-2.5 rounded-lg transition duration-200 <?php echo strpos($_SERVER['PHP_SELF'], '/cases/') !== false ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-800'; ?>">
                        <div class="flex items-center justify-center w-8">
                            <i class="fas fa-briefcase"></i>
                        </div>
                        <span class="ml-3 font-medium">Cases</span>
                        <i class="fas fa-chevron-down ml-auto transform transition-transform duration-200" :class="open ? 'rotate-180' : ''"></i>
                    </button>
                    
                    <div x-show="open" class="mt-2 space-y-1 px-3">
                        <a href="<?php echo $path_url; ?>admin/cases/index.php" class="group flex items-center pl-11 pr-2 py-2 rounded-md text-sm font-medium <?php echo $currentPage === 'index.php' && strpos($_SERVER['PHP_SELF'], '/cases/') !== false ? 'bg-gray-700 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-white'; ?>">
                            All Cases
                        </a>
                        <a href="<?php echo $path_url; ?>admin/cases/hearings.php" class="group flex items-center pl-11 pr-2 py-2 rounded-md text-sm font-medium <?php echo $currentPage === 'hearings.php' && strpos($_SERVER['PHP_SELF'], '/cases/') !== false ? 'bg-gray-700 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-white'; ?>">
                            Hearings
                        </a>
                    </div>
                </div>
                
                <!-- Finance Management -->
                <div x-data="{ open: <?php echo strpos($_SERVER['PHP_SELF'], '/finance/') !== false ? 'true' : 'false'; ?> }" class="hidden">
                    <button @click="open = !open" class="w-full group flex items-center px-3 py-2.5 rounded-lg transition duration-200 <?php echo strpos($_SERVER['PHP_SELF'], '/finance/') !== false ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-800'; ?>">
                        <div class="flex items-center justify-center w-8">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <span class="ml-3 font-medium">Finance</span>
                        <i class="fas fa-chevron-down ml-auto transform transition-transform duration-200" :class="open ? 'rotate-180' : ''"></i>
                    </button>
                    
                    <div x-show="open" class="mt-2 space-y-1 px-3">
                        <a href="<?php echo $path_url; ?>admin/finance/index.php" class="group flex items-center pl-11 pr-2 py-2 rounded-md text-sm font-medium <?php echo $currentPage === 'index.php' && strpos($_SERVER['PHP_SELF'], '/finance/') !== false ? 'bg-gray-700 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-white'; ?>">
                            Overview
                        </a>
                        <a href="<?php echo $path_url; ?>admin/finance/invoices.php" class="group flex items-center pl-11 pr-2 py-2 rounded-md text-sm font-medium <?php echo $currentPage === 'invoices.php' && strpos($_SERVER['PHP_SELF'], '/finance/') !== false ? 'bg-gray-700 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-white'; ?>">
                            Invoices
                        </a>
                        <a href="<?php echo $path_url; ?>admin/finance/payments.php" class="group flex items-center pl-11 pr-2 py-2 rounded-md text-sm font-medium <?php echo $currentPage === 'payments.php' && strpos($_SERVER['PHP_SELF'], '/finance/') !== false ? 'bg-gray-700 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-white'; ?>">
                            Payments
                        </a>
                    </div>
                </div>
                
                <!-- Reports -->
                <div x-data="{ open: <?php echo strpos($_SERVER['PHP_SELF'], '/reports/') !== false ? 'true' : 'false'; ?> }">
                    <button @click="open = !open" class="w-full group flex items-center px-3 py-2.5 rounded-lg transition duration-200 <?php echo strpos($_SERVER['PHP_SELF'], '/reports/') !== false ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-800'; ?>">
                        <div class="flex items-center justify-center w-8">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <span class="ml-3 font-medium">Reports</span>
                        <i class="fas fa-chevron-down ml-auto transform transition-transform duration-200" :class="open ? 'rotate-180' : ''"></i>
                    </button>
                    
                    <div x-show="open" class="mt-2 space-y-1 px-3">
                        <a href="<?php echo $path_url; ?>admin/reports/index.php" class="group flex items-center pl-11 pr-2 py-2 rounded-md text-sm font-medium <?php echo $currentPage === 'index.php' && strpos($_SERVER['PHP_SELF'], '/reports/') !== false ? 'bg-gray-700 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-white'; ?>">
                            Overview
                        </a>
                        <a href="<?php echo $path_url; ?>admin/reports/user-reports.php" class="group flex items-center pl-11 pr-2 py-2 rounded-md text-sm font-medium <?php echo $currentPage === 'user-reports.php' && strpos($_SERVER['PHP_SELF'], '/reports/') !== false ? 'bg-gray-700 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-white'; ?>">
                            User Reports
                        </a>
                        <a href="<?php echo $path_url; ?>admin/reports/case-reports.php" class="group flex items-center pl-11 pr-2 py-2 rounded-md text-sm font-medium <?php echo $currentPage === 'case-reports.php' && strpos($_SERVER['PHP_SELF'], '/reports/') !== false ? 'bg-gray-700 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-white'; ?>">
                            Case Reports
                        </a>
                        <a href="<?php echo $path_url; ?>admin/reports/financial-reports.php" class="group flex items-center pl-11 pr-2 py-2 rounded-md text-sm font-medium <?php echo $currentPage === 'financial-reports.php' && strpos($_SERVER['PHP_SELF'], '/reports/') !== false ? 'bg-gray-700 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-white'; ?>">
                            Financial Reports
                        </a>
                    </div>
                </div>
                
                <!-- Settings -->
                <a href="<?php echo $path_url; ?>admin/settings/index.php" class="group flex items-center px-3 py-2.5 rounded-lg transition duration-200 <?php echo strpos($_SERVER['PHP_SELF'], '/settings/') !== false ? 'bg-gradient-to-r from-blue-700 to-blue-600 text-white shadow-md' : 'text-gray-300 hover:bg-gray-800'; ?>">
                    <div class="flex items-center justify-center w-8">
                        <i class="fas fa-cog"></i>
                    </div>
                    <span class="ml-3 font-medium">Settings</span>
                </a>
            </nav>
            
            <!-- Logout button at bottom -->
            <div class="mt-auto pt-4 px-4">
                <div class="border-t border-gray-700 pt-4">
                    <a href="<?php echo $path_url; ?>auth/logout.php" class="flex items-center px-3 py-2.5 rounded-lg text-red-400 hover:bg-red-900 hover:bg-opacity-30 hover:text-red-300 transition duration-200">
                    <div class="flex items-center justify-center w-8">
                            <i class="fas fa-sign-out-alt"></i>
                        </div>
                        <span class="ml-3 font-medium">Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Mobile menu, show/hide based on state -->
    <div x-show="sidebarOpen" 
         @click.away="sidebarOpen = false"
         x-transition:enter="transition-opacity ease-linear duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-300"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-gray-600 bg-opacity-75 z-10 md:hidden" 
         style="display: none;"></div>
    
    <div x-show="sidebarOpen"
         x-transition:enter="transition ease-in-out duration-300 transform"
         x-transition:enter-start="-translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in-out duration-300 transform"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="-translate-x-full"
         class="fixed inset-y-0 left-0 flex flex-col z-40 max-w-xs w-full bg-gray-900 overflow-y-auto md:hidden"
         style="display: none;">
        
        <div class="flex items-center justify-between h-16 px-4 bg-gray-900 border-b border-gray-800">
            <a href="<?php echo $path_url; ?>admin/index.php" class="flex items-center text-white">
                <i class="fas fa-gavel text-2xl text-blue-500 mr-2"></i>
                <span class="text-xl font-bold">AMS Admin</span>
            </a>
            <button @click="sidebarOpen = false" class="text-gray-300 hover:text-white">
                <span class="sr-only">Close sidebar</span>
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <!-- Mobile Navigation (same as desktop but for mobile) -->
        <nav class="flex-1 px-2 py-4 space-y-1">
            <!-- Same navigation items as above, but for mobile -->
            <!-- Dashboard -->
            <a href="<?php echo $path_url; ?>admin/index.php" class="group flex items-center px-3 py-2.5 rounded-lg transition duration-200 <?php echo $currentPage === 'index.php' ? 'bg-gradient-to-r from-blue-700 to-blue-600 text-white shadow-md' : 'text-gray-300 hover:bg-gray-800'; ?>">
                <div class="flex items-center justify-center w-8">
                    <i class="fas fa-tachometer-alt"></i>
                </div>
                <span class="ml-3 font-medium">Dashboard</span>
            </a>
            
            <!-- Mobile versions of the dropdown menus -->
            <!-- User Management -->
            <div x-data="{ open: false }">
                <button @click="open = !open" class="w-full group flex items-center px-3 py-2.5 rounded-lg transition duration-200 <?php echo strpos($_SERVER['PHP_SELF'], '/users/') !== false ? 'bg-gray-800 text-white' : 'text-gray-300 hover:bg-gray-800'; ?>">
                    <div class="flex items-center justify-center w-8">
                        <i class="fas fa-users"></i>
                    </div>
                    <span class="ml-3 font-medium">User Management</span>
                    <i class="fas fa-chevron-down ml-auto transform transition-transform duration-200" :class="open ? 'rotate-180' : ''"></i>
                </button>
                
                <div x-show="open" class="mt-2 space-y-1 px-3">
                    <a href="<?php echo $path_url; ?>admin/users/index.php" class="group flex items-center pl-11 pr-2 py-2 rounded-md text-sm font-medium <?php echo $currentPage === 'index.php' && strpos($_SERVER['PHP_SELF'], '/users/') !== false ? 'bg-gray-700 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-white'; ?>">
                        All Users
                    </a>
                    <a href="<?php echo $path_url; ?>admin/users/create.php" class="group flex items-center pl-11 pr-2 py-2 rounded-md text-sm font-medium <?php echo $currentPage === 'create.php' && strpos($_SERVER['PHP_SELF'], '/users/') !== false ? 'bg-gray-700 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-white'; ?>">
                        Add New User
                    </a>
                </div>
            </div>
            
            <!-- Other mobile menu items would follow the same pattern -->
            
            <!-- Logout button at bottom for mobile -->
            <div class="mt-auto pt-4">
                <div class="border-t border-gray-700 pt-4">
                    <a href="<?php echo $path_url; ?>auth/logout.php" class="flex items-center px-3 py-2.5 rounded-lg text-red-400 hover:bg-red-900 hover:bg-opacity-30 hover:text-red-300 transition duration-200">
                        <div class="flex items-center justify-center w-8">
                            <i class="fas fa-sign-out-alt"></i>
                        </div>
                        <span class="ml-3 font-medium">Logout</span>
                    </a>
                </div>
            </div>
        </nav>
    </div>
</div>
