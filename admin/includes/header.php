<?php
// error reporting 
error_reporting(E_ALL);
ini_set('display_errors', 1);
$path_url= "/utb/advocate-management-system/";
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include required files if not already included
if (!function_exists('getDBConnection')) {
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../includes/functions.php';
    require_once __DIR__ . '/../../includes/auth.php';
}

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    // Redirect to login page
    header("Location: " . $path_url . "auth/login.php");
    exit;
}

// Get admin data
$adminId = $_SESSION['user_id'];
$adminData = getUserById($adminId);

// Get unread notifications count
$conn = getDBConnection();
$notificationsQuery = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
$stmt = $conn->prepare($notificationsQuery);
$stmt->bind_param("i", $adminId);
$stmt->execute();
$result = $stmt->get_result();
$unreadNotifications = $result->fetch_assoc()['count'];


// Define path URL if not already defined
if (!isset($path_url)) {
    $path_url = '/';
}

// Get current page for navigation highlighting
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>Admin Panel | Advocate Management System</title>
    
    
    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
        
    <!-- Alpine.js -->
    <script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.8.2/dist/alpine.min.js" defer></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        
    <!-- Custom Styles -->
    <style>
        .sidebar-active {
            background-color: #2563eb;
            color: white;
        }
        
        @media (max-width: 768px) {
            .sidebar-open {
                transform: translateX(0);
            }
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <div x-data="{ sidebarOpen: false }" class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Top Navigation -->
            <header class="bg-white shadow-sm z-10">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between h-16">
                        <div class="flex">
                            <!-- Mobile menu button -->
                            <div class="flex items-center md:hidden">
                                <button @click="sidebarOpen = !sidebarOpen" type="button" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500" aria-controls="mobile-menu" aria-expanded="false">
                                    <span class="sr-only">Open main menu</span>
                                    <i class="fas fa-bars"></i>
                                </button>
                            </div>
                            
                            <!-- Logo -->
                            <div class="flex-shrink-0 flex items-center">
                                <a href="<?php echo $path_url; ?>admin/index.php" class="text-xl font-bold text-blue-600">
                                    <span class="hidden md:inline">Admin Panel</span>
                                    <span class="md:hidden">AMS</span>
                                </a>
                            </div>
                        </div>
                        
                        <!-- Right side navigation items -->
                        <div class="flex items-center">
                            <!-- Notifications -->
                            <div class="relative ml-3" x-data="{ open: false }">
                                <div>
                                    <button @click="open = !open" class="flex text-gray-400 hover:text-gray-500 focus:outline-none focus:text-gray-500 p-1 rounded-full hover:bg-gray-100" aria-expanded="false">
                                        <span class="sr-only">View notifications</span>
                                        <i class="fas fa-bell text-xl"></i>
                                        <?php if ($unreadNotifications > 0): ?>
                                            <span class="absolute top-0 right-0 block h-5 w-5 rounded-full bg-red-500 text-white text-xs font-medium flex items-center justify-center">
                                                <?php echo $unreadNotifications > 9 ? '9+' : $unreadNotifications; ?>
                                            </span>
                                        <?php endif; ?>
                                    </button>
                                </div>
                                
                                <div x-show="open" 
                                     @click.away="open = false"
                                     x-transition:enter="transition ease-out duration-100"
                                     x-transition:enter-start="transform opacity-0 scale-95"
                                     x-transition:enter-end="transform opacity-100 scale-100"
                                     x-transition:leave="transition ease-in duration-75"
                                     x-transition:leave-start="transform opacity-100 scale-100"
                                     x-transition:leave-end="transform opacity-0 scale-95"
                                     class="origin-top-right absolute right-0 mt-2 w-80 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none" 
                                     style="display: none;">
                                    <div class="py-1 divide-y divide-gray-200 hid">
                                        <div class="px-4 py-2 flex justify-between items-center">
                                            <h3 class="text-sm font-medium text-gray-900">Notifications</h3>
                                            <a href="<?php echo $path_url; ?>admin/notifications.php" class="text-xs text-blue-600 hover:text-blue-800">View All</a>
                                        </div>
                                        
                                        <div class="max-h-60 overflow-y-auto">
                                            <!-- Notifications will be loaded via AJAX -->
                                            <div id="notification-list" class="divide-y divide-gray-200">
                                                <div class="px-4 py-3 text-center text-sm text-gray-500">
                                                    <i class="fas fa-spinner fa-spin mr-2"></i> Loading notifications...
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="px-4 py-2 text-center">
                                            <a href="<?php echo $path_url; ?>admin/notifications.php" class="text-sm text-blue-600 hover:text-blue-800">See all notifications</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Profile dropdown -->
                            <div class="ml-3 relative" x-data="{ open: false }">
                                <div>
                                    <button @click="open = !open" class="flex items-center max-w-xs text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500" id="user-menu-button" aria-expanded="false" aria-haspopup="true">
                                        <span class="sr-only">Open user menu</span>
                                        <div class="h-8 w-8 rounded-full bg-gray-200 flex items-center justify-center text-gray-600 overflow-hidden">
                                            <?php if (!empty($adminData['profile_image'])): ?>
                                                <img src="<?php echo $path_url . $adminData['profile_image']; ?>" alt="Profile" class="h-full w-full object-cover">
                                            <?php else: ?>
                                                <i class="fas fa-user"></i>
                                            <?php endif; ?>
                                        </div>
                                        <span class="ml-2 text-gray-700 font-medium hidden md:block"><?php echo htmlspecialchars($adminData['full_name']); ?></span>
                                        <i class="fas fa-chevron-down text-gray-400 ml-1 hidden md:block"></i>
                                    </button>
                                </div>
                                
                                <div x-show="open" 
                                     @click.away="open = false"
                                     x-transition:enter="transition ease-out duration-100"
                                     x-transition:enter-start="transform opacity-0 scale-95"
                                     x-transition:enter-end="transform opacity-100 scale-100"
                                     x-transition:leave="transition ease-in duration-75"
                                     x-transition:leave-start="transform opacity-100 scale-100"
                                     x-transition:leave-end="transform opacity-0 scale-95"
                                     class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none" 
                                     style="display: none;">
                                    <div class="py-1">
                                        <a href="<?php echo $path_url; ?>admin/profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            <i class="fas fa-user-circle mr-2"></i> Your Profile
                                        </a>
                                        <a href="<?php echo $path_url; ?>admin/settings/index.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            <i class="fas fa-cog mr-2"></i> Settings
                                        </a>
                                        <div class="border-t border-gray-100"></div>
                                        <a href="<?php echo $path_url; ?>auth/logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                                            <i class="fas fa-sign-out-alt mr-2"></i> Sign out
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Flash Messages -->
            <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
                    <div class="rounded-md p-4 <?php echo $_SESSION['flash_type'] === 'success' ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800'; ?>">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <?php if ($_SESSION['flash_type'] === 'success'): ?>
                                    <i class="fas fa-check-circle text-green-400"></i>
                                <?php else: ?>
                                    <i class="fas fa-exclamation-circle text-red-400"></i>
                                <?php endif; ?>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium">
                                    <?php echo $_SESSION['flash_message']; ?>
                                </p>
                            </div>
                            <div class="ml-auto pl-3">
                                <div class="-mx-1.5 -my-1.5">
                                    <button onclick="this.parentElement.parentElement.parentElement.remove()" class="inline-flex rounded-md p-1.5 <?php echo $_SESSION['flash_type'] === 'success' ? 'text-green-500 hover:bg-green-100' : 'text-red-500 hover:bg-red-100'; ?> focus:outline-none">
                                        <span class="sr-only">Dismiss</span>
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
            <?php endif; ?>
            
             <!-- Main Content Area -->
             <main class="flex-1 overflow-y-auto p-8 sm:p-6 lg:p-8">