</div>
    </div>

    <!-- JavaScript -->
    <script src="assets/js/main.js"></script>
    <script>
        // Toggle sidebar on mobile
        document.getElementById('toggleSidebarMobile').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('hidden');
        });
        
        // Toggle user dropdown
        document.getElementById('user-menu-button')?.addEventListener('click', function() {
            document.getElementById('dropdown').classList.toggle('hidden');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('dropdown');
            const userMenuButton = document.getElementById('user-menu-button');
            
            if (dropdown && !dropdown.classList.contains('hidden') && 
                userMenuButton && !userMenuButton.contains(event.target) && 
                !dropdown.contains(event.target)) {
                dropdown.classList.add('hidden');
            }
        });
    </script>
</body>
</html>
