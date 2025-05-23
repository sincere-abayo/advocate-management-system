<?php
$path_url= "/utb/advocate-management-system/";
ob_start();
// error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is an advocate
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'advocate') {
    header("Location: ../../auth/login.php");
    exit;
}

// Include required files
require_once $_SERVER['DOCUMENT_ROOT'] . '/utb/advocate-management-system/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/utb/advocate-management-system/includes/functions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/utb/advocate-management-system/includes/auth.php';


// Get advocate data
$advocateData = getAdvocateData($_SESSION['user_id']);

// Function to get advocate data

$unreadNotifications = getUnreadNotificationsCount($_SESSION['user_id']);

// Page title
$pageTitle = $pageTitle ?? 'Advocate Dashboard';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Advocate Management System</title>

    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/css/tailwind.css">

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
    <div class="flex h-screen overflow-hidden" x-data="{ sidebarOpen: false }">
        <!-- Sidebar -->
        <?php include_once 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Top Navigation -->
            <header class="bg-white shadow-sm z-10">
                <div class="px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
                    <div class="flex items-center">
                        <button @click="sidebarOpen = !sidebarOpen" class="text-gray-500 focus:outline-none md:hidden">
                            <i class="fas fa-bars text-xl"></i>
                        </button>
                        <h1 class="ml-4 text-xl font-semibold text-gray-800"><?php echo $pageTitle; ?></h1>
                    </div>

                    <div class="flex items-center space-x-4">
                        <!-- Search -->
                        <div class="relative hidden md:hidden">
                            <input type="text" placeholder="Search..."
                                class="w-64 pr-10 pl-4 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <button class="absolute right-0 top-0 mt-2 mr-3 text-gray-400">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>

                        <!-- Notifications -->
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" class="text-gray-500 focus:outline-none relative">
                                <i class="fas fa-bell text-xl"></i>
                                <?php if ($unreadNotifications > 0): ?>
                                <span
                                    class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center"><?php echo $unreadNotifications; ?></span>
                                <?php endif; ?>
                            </button>

                            <div x-show="open" @click.away="open = false"
                                class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg py-2 z-20"
                                style="display: none;">
                                <div class="px-4 py-2 border-b">
                                    <div class="flex justify-between items-center">
                                        <h3 class="text-sm font-semibold text-gray-700">Notifications</h3>
                                        <a href="<?= $path_url ?>advocate/notifications.php"
                                            class="text-xs text-blue-600 hover:underline">View All</a>
                                    </div>
                                </div>

                                <div class="max-h-64 overflow-y-auto">
                                    <!-- Notification items will be loaded here -->
                                    <!-- <div class="px-4 py-3 hover:bg-gray-50 border-b">
                                        <p class="text-sm font-medium text-gray-800">New case assigned</p>
                                        <p class="text-xs text-gray-500">You have been assigned to a new case: Smith vs. Johnson</p>
                                        <p class="text-xs text-gray-400 mt-1">2 hours ago</p>
                                    </div>
                                    
                                    <div class="px-4 py-3 hover:bg-gray-50">
                                        <p class="text-sm font-medium text-gray-800">Upcoming appointment</p>
                                        <p class="text-xs text-gray-500">Reminder: Meeting with client tomorrow at 10:00 AM</p>
                                        <p class="text-xs text-gray-400 mt-1">Yesterday</p>
                                    </div> -->
                                </div>
                            </div>
                        </div>

                        <!-- User Menu -->
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" class="flex items-center space-x-2 focus:outline-none">
                                <div
                                    class="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center text-white">
                                    <?php echo strtoupper(substr($_SESSION['full_name'] ?? 'A', 0, 1)); ?>
                                </div>
                                <span
                                    class="hidden md:inline-block text-sm font-medium text-gray-700"><?php echo $_SESSION['full_name'] ?? 'Advocate'; ?></span>
                                <i class="fas fa-chevron-down text-xs text-gray-400"></i>
                            </button>

                            <div x-show="open" @click.away="open = false"
                                class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-2 z-20"
                                style="display: none;">
                                <a href="<?= $path_url ?>advocate/profile.php"
                                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-user mr-2"></i> My Profile
                                </a>
                                <a href="/advocate/settings/index.php"
                                    class="hidden block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-cog mr-2"></i> Settings
                                </a>
                                <div class="border-t my-1"></div>
                                <a href="<?= $path_url; ?>includes/logout_handler.php"
                                    class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
                <?php displayFlashMessage(); ?>