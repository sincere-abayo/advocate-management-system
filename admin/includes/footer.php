</main>
            
            <!-- Footer -->
            <footer class="bg-white p-4 shadow-inner mt-auto">
                <div class="container mx-auto">
                    <div class="flex flex-col md:flex-row justify-between items-center">
                        <div class="text-sm text-gray-600">
                            © <?php echo date('Y'); ?> Advocate Management System. All rights reserved.
                        </div>
                        <div class="text-sm text-gray-500 mt-2 md:mt-0">
                            <a href="<?php echo $path_url; ?>admin/help.php" class="hover:text-blue-600 mr-4">Help</a>
                            <a href="<?php echo $path_url; ?>admin/settings/index.php" class="hover:text-blue-600">System Settings</a>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>
    
    <!-- Load notifications via AJAX -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Load notifications when dropdown is opened
        const notificationButton = document.querySelector('[aria-expanded="false"]');
        if (notificationButton) {
            notificationButton.addEventListener('click', function() {
                loadNotifications();
            });
        }
        
        function loadNotifications() {
            const notificationList = document.getElementById('notification-list');
            if (!notificationList) return;
            
            fetch('<?php echo $path_url; ?>admin/get-notifications.php')
                .then(response => response.text())
                .then(html => {
                    notificationList.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error loading notifications:', error);
                    notificationList.innerHTML = '<div class="px-4 py-3 text-center text-sm text-red-500">Failed to load notifications</div>';
                });
        }
    });
    </script>
    
    <!-- Custom JavaScript -->
    <script src="<?php echo $path_url; ?>assets/js/main.js"></script>
</body>
</html>
<?php
ob_end_flush();
?>