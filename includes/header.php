<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once 'functions.php';
require_once 'auth.php';

// Get current page
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advocate Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/tailwind.css">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@2.8.2/dist/alpine.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <header class="bg-blue-800 text-white shadow-md">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <a href="index.php" class="text-2xl font-bold">Advocate MS</a>
            
            <?php if (isLoggedIn()): ?>
                <div class="flex items-center space-x-4">
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="flex items-center space-x-2">
                            <span><?php echo $_SESSION['full_name']; ?></span>
                            <i class="fas fa-chevron-down text-sm"></i>
                        </button>
                        <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-10">
                            <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Profile</a>
                            <a href="auth/logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Logout</a>
                        </div>
                    </div>
                    
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="relative">
                            <i class="fas fa-bell text-xl"></i>
                            <?php $notificationCount = getUnreadNotificationsCount($_SESSION['user_id']); ?>
                            <?php if ($notificationCount > 0): ?>
                                <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                                    <?php echo $notificationCount; ?>
                                </span>
                            <?php endif; ?>
                        </button>
                        <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-80 bg-white rounded-md shadow-lg py-1 z-10">
                            <div class="px-4 py-2 text-sm font-medium text-gray-700 border-b">Notifications</div>
                            <div class="max-h-64 overflow-y-auto">
                                <?php 
                                $notifications = getUserNotifications($_SESSION['user_id'], 5);
                                if (count($notifications) > 0):
                                    foreach ($notifications as $notification):
                                ?>
                                    <div class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 border-b <?php echo $notification['is_read'] ? '' : 'bg-blue-50'; ?>">
                                        <div class="font-medium"><?php echo $notification['title']; ?></div>
                                        <div class="text-xs text-gray-500"><?php echo $notification['message']; ?></div>
                                        <div class="text-xs text-gray-400 mt-1"><?php echo date('M d, Y H:i', strtotime($notification['created_at'])); ?></div>
                                    </div>
                                <?php 
                                    endforeach;
                                else:
                                ?>
                                    <div class="px-4 py-2 text-sm text-gray-700">No notifications</div>
                                <?php endif; ?>
                            </div>
                            <a href="notifications.php" class="block px-4 py-2 text-sm text-center text-blue-600 hover:bg-gray-100 border-t">View All</a>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div>
                    <a href="auth/login.php" class="text-white hover:text-blue-200 mr-4">Login</a>
                    <a href="auth/register.php" class="bg-white text-blue-800 px-4 py-2 rounded hover:bg-blue-100">Register</a>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (isLoggedIn()): ?>
        <nav class="bg-blue-700">
            <div class="container mx-auto px-4">
                <div class="flex space-x-4">
                    <a href="index.php" class="py-3 px-3 text-white hover:bg-blue-600 <?php echo $currentPage == 'index.php' ? 'bg-blue-600' : ''; ?>">Dashboard</a>
                    
                    <?php if ($_SESSION['user_type'] == 'admin'): ?>
                        <a href="admin/users.php" class="py-3 px-3 text-white hover:bg-blue-600 <?php echo strpos($currentPage, 'users.php') !== false ? 'bg-blue-600' : ''; ?>">Users</a>
                    <?php endif; ?>
                    
                    <?php if ($_SESSION['user_type'] == 'admin' || $_SESSION['user_type'] == 'advocate'): ?>
                        <a href="cases/index.php" class="py-3 px-3 text-white hover:bg-blue-600 <?php echo strpos($currentPage, 'cases') !== false ? 'bg-blue-600' : ''; ?>">Cases</a>
                        <a href="appointments/index.php" class="py-3 px-3 text-white hover:bg-blue-600 <?php echo strpos($currentPage, 'appointments') !== false ? 'bg-blue-600' : ''; ?>">Appointments</a>
                        <a href="clients/index.php" class="py-3 px-3 text-white hover:bg-blue-600 <?php echo strpos($currentPage, 'clients') !== false ? 'bg-blue-600' : ''; ?>">Clients</a>
                        <a href="documents/index.php" class="py-3 px-3 text-white hover:bg-blue-600 <?php echo strpos($currentPage, 'documents') !== false ? 'bg-blue-600' : ''; ?>">Documents</a>
                        <a href="billing/index.php" class="py-3 px-3 text-white hover:bg-blue-600 <?php echo strpos($currentPage, 'billing') !== false ? 'bg-blue-600' : ''; ?>">Billing</a>
                    <?php endif; ?>
                    
                    <?php if ($_SESSION['user_type'] == 'client'): ?>
                        <a href="client/cases.php" class="py-3 px-3 text-white hover:bg-blue-600 <?php echo strpos($currentPage, 'cases') !== false ? 'bg-blue-600' : ''; ?>">My Cases</a>
                        <a href="client/appointments.php" class="py-3 px-3 text-white hover:bg-blue-600 <?php echo strpos($currentPage, 'appointments') !== false ? 'bg-blue-600' : ''; ?>">Appointments</a>
                        <a href="client/documents.php" class="py-3 px-3 text-white hover:bg-blue-600 <?php echo strpos($currentPage, 'documents') !== false ? 'bg-blue-600' : ''; ?>">Documents</a>
                        <a href="client/billing.php" class="py-3 px-3 text-white hover:bg-blue-600 <?php echo strpos($currentPage, 'billing') !== false ? 'bg-blue-600' : ''; ?>">Billing</a>
                    <?php endif; ?>
                    
                    <?php if ($_SESSION['user_type'] == 'admin'): ?>
                        <a href="admin/reports.php" class="py-3 px-3 text-white hover:bg-blue-600 <?php echo strpos($currentPage, 'reports') !== false ? 'bg-blue-600' : ''; ?>">Reports</a>
                        <a href="admin/settings.php" class="py-3 px-3 text-white hover:bg-blue-600 <?php echo strpos($currentPage, 'settings') !== false ? 'bg-blue-600' : ''; ?>">Settings</a>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
        <?php endif; ?>
    </header>
    
    <main class="container mx-auto px-4 py-6">
        <?php displayFlashMessage(); ?>
