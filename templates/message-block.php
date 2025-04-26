<?php
/**
 * Reusable message block for displaying success, error, and info messages
 * Usage: Include this file in any page where you want to display messages
 * 
 * Supported message types:
 * - success: Green message for successful operations
 * - error: Red message for errors
 * - info: Blue message for informational messages
 * - warning: Yellow message for warnings
 * 
 * URL parameters:
 * - success=1: Display success message
 * - error=1: Display error message
 * - info=1: Display info message
 * - warning=1: Display warning message
 * - msg: Custom message text (optional)
 */
?>

<!-- Success message -->
<?php if(isset($_GET['success'])): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
        <p class="font-medium">Success!</p>
        <p><?php echo isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : 'The operation was completed successfully.'; ?></p>
    </div>
<?php endif; ?>

<!-- Error message -->
<?php if(isset($_GET['error'])): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
        <p class="font-medium">Error!</p>
        <p><?php echo isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : 'An error occurred while processing your request.'; ?></p>
    </div>
<?php endif; ?>

<!-- Info message -->
<?php if(isset($_GET['info'])): ?>
    <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-6" role="alert">
        <p class="font-medium">Information</p>
        <p><?php echo isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : 'Please note this important information.'; ?></p>
    </div>
<?php endif; ?>

<!-- Warning message -->
<?php if(isset($_GET['warning'])): ?>
    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6" role="alert">
        <p class="font-medium">Warning!</p>
        <p><?php echo isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : 'Please be aware of this warning.'; ?></p>
    </div>
<?php endif; ?>