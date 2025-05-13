<?php
// Include necessary files
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/auth.php';

// Check if user is logged in and has appropriate permissions
requireLogin();
requireUserType('advocate');

// Get advocate ID from session
$advocateId = $_SESSION['advocate_id'];

// Check if expense ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['flash_message'] = "Invalid expense ID.";
    $_SESSION['flash_type'] = "error";
    header("Location: index.php");
    exit;
}

$expenseId = (int)$_GET['id'];

// Get database connection
$conn = getDBConnection();

// Get expense details before deletion
$expenseStmt = $conn->prepare("
    SELECT ce.*, c.case_number, c.title as case_title
    FROM case_expenses ce
    LEFT JOIN cases c ON ce.case_id = c.case_id
    WHERE ce.expense_id = ? AND ce.advocate_id = ?
");
$expenseStmt->bind_param("ii", $expenseId, $advocateId);
$expenseStmt->execute();
$expenseResult = $expenseStmt->get_result();

// Check if expense exists and belongs to the advocate
if ($expenseResult->num_rows === 0) {
    $_SESSION['flash_message'] = "Expense not found or you don't have permission to delete it.";
    $_SESSION['flash_type'] = "error";
    header("Location: index.php");
    exit;
}

// Get expense data
$expense = $expenseResult->fetch_assoc();
$amount = $expense['amount'];
$caseId = $expense['case_id'];
$description = $expense['description'];
$receiptFile = $expense['receipt_file'];

// Process deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Delete the expense
        $deleteStmt = $conn->prepare("DELETE FROM case_expenses WHERE expense_id = ? AND advocate_id = ?");
        $deleteStmt->bind_param("ii", $expenseId, $advocateId);
        $deleteStmt->execute();
        
        if ($deleteStmt->affected_rows === 0) {
            throw new Exception("Failed to delete expense.");
        }
        
        // Update case financials if case_id exists
        if ($caseId) {
            $updateCaseStmt = $conn->prepare("
                UPDATE cases 
                SET total_expenses = total_expenses - ?,
                    profit = total_income - (total_expenses - ?)
                WHERE case_id = ?
            ");
            
            $updateCaseStmt->bind_param(
                "ddi",
                $amount,
                $amount,
                $caseId
            );
            
            $updateCaseStmt->execute();
            
            // Add case activity
            $activityDesc = "Expense deleted: " . formatCurrency($amount) . " - " . $description;
            addCaseActivity($caseId, $_SESSION['user_id'], 'update', $activityDesc);
        }
        
        // Update advocate's yearly expenses
        $updateAdvocateStmt = $conn->prepare("
            UPDATE advocate_profiles 
            SET total_expenses_ytd = total_expenses_ytd - ?,
                profit_ytd = total_income_ytd - (total_expenses_ytd - ?)
            WHERE advocate_id = ?
        ");
        
        $updateAdvocateStmt->bind_param(
            "ddi",
            $amount,
            $amount,
            $advocateId
        );
        
        $updateAdvocateStmt->execute();
        
        // Delete receipt file if exists
        if (!empty($receiptFile)) {
            $filePath = "../../../uploads/receipts/" . $receiptFile;
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        // Set success message
        $_SESSION['flash_message'] = "Expense deleted successfully.";
        $_SESSION['flash_type'] = "success";
        
        // Redirect based on referrer
        if (isset($_POST['redirect_to']) && !empty($_POST['redirect_to'])) {
            header("Location: " . $_POST['redirect_to']);
        } else {
            header("Location: index.php");
        }
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        // Set error message
        $_SESSION['flash_message'] = "Error deleting expense: " . $e->getMessage();
        $_SESSION['flash_type'] = "error";
        
        // Redirect back to expense list
        header("Location: index.php");
        exit;
    }
}

// Get referrer for cancel button
$referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';

// Include header
$pageTitle = "Delete Expense";
include '../../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="p-6 bg-red-50 border-b border-red-100">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-red-600 text-3xl"></i>
                    </div>
                    <div class="ml-4">
                        <h1 class="text-xl font-bold text-gray-800">Delete Expense</h1>
                        <p class="text-gray-600">Are you sure you want to delete this expense? This action cannot be undone.</p>
                    </div>
                </div>
            </div>
            
            <div class="p-6">
                <div class="mb-6">
                    <h2 class="text-lg font-semibold text-gray-800 mb-2">Expense Details</h2>
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-500">Amount</p>
                                <p class="font-medium text-gray-800"><?php echo formatCurrency($expense['amount']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Date</p>
                                <p class="font-medium text-gray-800"><?php echo formatDate($expense['expense_date']); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Category</p>
                                <p class="font-medium text-gray-800"><?php echo htmlspecialchars($expense['expense_category'] ?? 'Uncategorized'); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Related Case</p>
                                <p class="font-medium text-gray-800">
                                    <?php if ($expense['case_id']): ?>
                                        <a href="<?php echo $path_url; ?>advocate/cases/view.php?id=<?php echo $expense['case_id']; ?>" class="text-blue-600 hover:text-blue-800">
                                            <?php echo htmlspecialchars($expense['case_number']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-500">No Case</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        <div class="mt-4">
                            <p class="text-sm text-gray-500">Description</p>
                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($expense['description']); ?></p>
                        </div>
                        
                        <?php if (!empty($expense['receipt_file'])): ?>
                            <div class="mt-4">
                                <p class="text-sm text-gray-500">Receipt</p>
                                <div class="flex items-center mt-1">
                                    <i class="fas fa-file-alt text-blue-500 mr-2"></i>
                                    <a href="<?php echo $path_url; ?>uploads/receipts/<?php echo $expense['receipt_file']; ?>" target="_blank" class="text-blue-600 hover:text-blue-800">
                                        <?php echo htmlspecialchars($expense['receipt_file']); ?>
                                    </a>
                                </div>
                                
                                <?php 
                                // Check if file is an image
                                $fileExtension = strtolower(pathinfo($expense['receipt_file'], PATHINFO_EXTENSION));
                                $isImage = in_array($fileExtension, ['jpg', 'jpeg', 'png']);
                                
                                if ($isImage): 
                                ?>
                                    <div class="mt-3">
                                        <img src="<?php echo $path_url; ?>uploads/receipts/<?php echo $expense['receipt_file']; ?>" 
                                             alt="Receipt" 
                                             class="max-w-full h-auto max-h-64 rounded border border-gray-200">
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="flex flex-col-reverse sm:flex-row sm:justify-between sm:space-x-4">
                    <a href="<?php echo htmlspecialchars($referrer); ?>" class="mt-3 sm:mt-0 inline-flex justify-center items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Cancel
                    </a>
                    
                    <form method="POST" action="" class="inline-block">
                        <input type="hidden" name="confirm_delete" value="1">
                        <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($referrer); ?>">
                        <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500" onclick="return confirm('Are you absolutely sure you want to delete this expense?');">
                            <i class="fas fa-trash-alt mr-2"></i> Delete Expense
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Close connection
$conn->close();

// Include footer
include '../../includes/footer.php';
?>