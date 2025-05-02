</main>
            
            <!-- Footer -->
            <footer class="bg-white p-4 shadow-inner mt-auto">
                <div class="container mx-auto">
                    <div class="flex flex-col md:flex-row justify-between items-center">
                        <div class="text-sm text-gray-600">
                            © <?php echo date('Y'); ?> Advocate Management System. All rights reserved.
                        </div>
                        <div class="text-sm text-gray-500 mt-2 md:mt-0">
                            <a href="/help.php" class="hover:text-blue-600 mr-4">Help</a>
                            <a href="/privacy-policy.php" class="hover:text-blue-600 mr-4">Privacy Policy</a>
                            <a href="/terms.php" class="hover:text-blue-600">Terms of Service</a>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>
    
    <!-- Custom JavaScript -->
    <script src="/assets/js/main.js"></script>
</body>
</html>
<?php

ob_end_flush();
?>