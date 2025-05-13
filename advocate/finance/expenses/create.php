<?php
// Set page title
$pageTitle = "Create Expense";

// Include header
include_once '../../includes/header.php';

// Get advocate ID
$advocateId = $_SESSION['advocate_id'];

// Get database connection
$conn = getDBConnection();

// Initialize variables
$errors = [];
$formData = [
    'case_id' => isset($_GET['case_id']) ? (int)$_GET['case_id'] : '',
    'expense_date' => date('Y-m-d'),
    'amount' => '',
    'description' => '',
    'expense_category' => '',
    'receipt_file' => ''
];

// Check if case_id is provided in URL
$caseId = isset($_GET['case_id']) ? (int)$_GET['case_id'] : 0;
$caseDetails = null;

// If case_id is provided, verify it exists and advocate is assigned to it
if ($caseId > 0) {
    $caseStmt = $conn->prepare("
        SELECT c.case_id, c.title, c.case_number, c.client_id, u.full_name as client_name
        FROM cases c
        JOIN case_assignments ca ON c.case_id = ca.case_id
        JOIN client_profiles cp ON c.client_id = cp.client_id
        JOIN users u ON cp.user_id = u.user_id
        WHERE c.case_id = ? AND ca.advocate_id = ?
        LIMIT 1
    ");
    $caseStmt->bind_param("ii", $caseId, $advocateId);
    $caseStmt->execute();
    $caseResult = $caseStmt->get_result();
    
    if ($caseResult->num_rows > 0) {
        $caseDetails = $caseResult->fetch_assoc();
        $formData['case_id'] = $caseId;
    }
}

// Get all expense categories for dropdown
$categoriesQuery = "
    SELECT DISTINCT expense_category 
    FROM case_expenses 
    WHERE advocate_id = ? AND expense_category IS NOT NULL AND expense_category != ''
    ORDER BY expense_category
";
$categoriesStmt = $conn->prepare($categoriesQuery);
$categoriesStmt->bind_param("i", $advocateId);
$categoriesStmt->execute();
$categoriesResult = $categoriesStmt->get_result();
$categories = [];
while ($category = $categoriesResult->fetch_assoc()) {
    $categories[] = $category['expense_category'];
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate case (optional)
    $formData['case_id'] = !empty($_POST['case_id']) ? (int)$_POST['case_id'] : null;
    
    // Validate expense date
    if (empty($_POST['expense_date'])) {
        $errors['expense_date'] = 'Expense date is required';
    } else {
        $formData['expense_date'] = $_POST['expense_date'];
    }
    
    // Validate amount
    if (empty($_POST['amount'])) {
        $errors['amount'] = 'Amount is required';
    } elseif (!is_numeric($_POST['amount']) || $_POST['amount'] <= 0) {
        $errors['amount'] = 'Amount must be a positive number';
    } else {
        $formData['amount'] = (float)$_POST['amount'];
    }
    
    // Validate description
    if (empty($_POST['description'])) {
        $errors['description'] = 'Description is required';
    } else {
        $formData['description'] = $_POST['description'];
    }
    
    // Validate category
    if (empty($_POST['expense_category'])) {
        $errors['expense_category'] = 'Category is required';
    } else {
        $formData['expense_category'] = $_POST['expense_category'];
    }
    
    // Handle receipt file upload
    if (!empty($_FILES['receipt_file']['name'])) {
        $targetDir = "../../../uploads/receipts/";
        
        // Create directory if it doesn't exist
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        
        $fileExtension = strtolower(pathinfo($_FILES['receipt_file']['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
        
        if (!in_array($fileExtension, $allowedExtensions)) {
            $errors['receipt_file'] = 'Only JPG, JPEG, PNG, and PDF files are allowed';
        } elseif ($_FILES['receipt_file']['size'] > 5000000) { // 5MB max
            $errors['receipt_file'] = 'File size must be less than 5MB';
        } else {
            $fileName = 'receipt_' . time() . '_' . uniqid() . '.' . $fileExtension;
            $targetFile = $targetDir . $fileName;
            
            if (move_uploaded_file($_FILES['receipt_file']['tmp_name'], $targetFile)) {
                $formData['receipt_file'] = $fileName;
            } else {
                $errors['receipt_file'] = 'Failed to upload file';
            }
        }
    }
    
    // If no errors, insert expense
    if (empty($errors)) {
        try {
            // Start transaction
            $conn->begin_transaction();
            
            // Insert expense
            $stmt = $conn->prepare("
                INSERT INTO case_expenses (
                    case_id, advocate_id, expense_date, amount, 
                    description, expense_category, receipt_file
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->bind_param(
                "iisdsss",  // Added an extra 's' for receipt_file
                $formData['case_id'],
                $advocateId,
                $formData['expense_date'],
                $formData['amount'],
                $formData['description'],
                $formData['expense_category'],
                $formData['receipt_file']
            );
            
            
            $stmt->execute();
            $expenseId = $conn->insert_id;
            
            // Update case financials if case_id is provided
            if ($formData['case_id']) {
                $updateCaseStmt = $conn->prepare("
                    UPDATE cases 
                    SET total_expenses = total_expenses + ?,
                        profit = total_income - (total_expenses + ?)
                    WHERE case_id = ?
                ");
                
                $updateCaseStmt->bind_param(
                    "ddi",
                    $formData['amount'],
                    $formData['amount'],
                    $formData['case_id']
                );
                
                $updateCaseStmt->execute();
                
                // Add case activity
                $activityDesc = "Expense added: " . formatCurrency($formData['amount']) . " - " . $formData['description'];
                addCaseActivity($formData['case_id'], $_SESSION['user_id'], 'update', $activityDesc);
            }
            
            // Update advocate's yearly expenses
            $updateAdvocateStmt = $conn->prepare("
                UPDATE advocate_profiles 
                SET total_expenses_ytd = total_expenses_ytd + ?,
                    profit_ytd = total_income_ytd - (total_expenses_ytd + ?)
                WHERE advocate_id = ?
            ");
            
            $updateAdvocateStmt->bind_param(
                "ddi",
                $formData['amount'],
                $formData['amount'],
                $advocateId
            );
            
            $updateAdvocateStmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            // Redirect to expense list page
            $_SESSION['flash_message'] = "Expense created successfully.";
            $_SESSION['flash_type'] = "success";
            
            if ($formData['case_id']) {
                header("Location: ../../cases/view.php?id=" . $formData['case_id'] . "#expenses");
            } else {
                header("Location: index.php");
            }
            exit;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $errors['general'] = "Error creating expense: " . $e->getMessage();
        }
    }
}

// Get all cases for dropdown
$casesQuery = "
    SELECT c.case_id, c.case_number, c.title 
    FROM cases c
    JOIN case_assignments ca ON c.case_id = ca.case_id
    WHERE ca.advocate_id = ?
    ORDER BY c.filing_date DESC
";
$casesStmt = $conn->prepare($casesQuery);
$casesStmt->bind_param("i", $advocateId);
$casesStmt->execute();
$casesResult = $casesStmt->get_result();
$cases = [];
while ($case = $casesResult->fetch_assoc()) {
    $cases[] = $case;
}

// Close database connection
$conn->close();
?>

<div class="mb-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Create Expense</h1>
            <p class="text-gray-600">Record a new expense<?php echo $caseId ? ' for case ' . htmlspecialchars($caseDetails['case_number']) : ''; ?></p>
        </div>
    </div>
</div>

<div class="bg-white rounded-lg shadow-md p-6">
    <?php if (isset($errors['general'])): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p><?php echo $errors['general']; ?></p>
        </div>
    <?php endif; ?>
    
    <form method="POST" action="" enctype="multipart/form-data">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label for="case_id" class="block text-sm font-medium text-gray-700 mb-1">Related Case</label>
                <select id="case_id" name="case_id" class="form-select w-full" <?php echo $caseId ? 'disabled' : ''; ?>>
                    <option value="">No Related Case</option>
                    <?php foreach ($cases as $case): ?>
                        <option value="<?php echo $case['case_id']; ?>" <?php echo $formData['case_id'] == $case['case_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($case['case_number'] . ' - ' . $case['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($caseId): ?>
                    <input type="hidden" name="case_id" value="<?php echo $caseId; ?>">
                <?php endif; ?>
                
                <?php if ($caseId && $caseDetails): ?>
                    <div class="mt-1 text-sm text-blue-600">
                        <i class="fas fa-info-circle"></i> Creating expense for case: <?php echo htmlspecialchars($caseDetails['case_number']); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div>
                <label for="expense_date" class="block text-sm font-medium text-gray-700 mb-1">Expense Date *</label>
                <input type="date" id="expense_date" name="expense_date" class="form-input w-full <?php echo isset($errors['expense_date']) ? 'border-red-500' : ''; ?>" value="<?php echo $formData['expense_date']; ?>" required>
                <?php if (isset($errors['expense_date'])): ?>
                    <p class="text-red-500 text-sm mt-1"><?php echo $errors['expense_date']; ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label for="amount" class="block text-sm font-medium text-gray-700 mb-1">Amount *</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <span class="text-gray-500 sm:text-sm">$</span>
                    </div>
                    <input type="number" id="amount" name="amount" step="0.01" min="0.01" class="form-input pl-7 w-full <?php echo isset($errors['amount']) ? 'border-red-500' : ''; ?>" placeholder="0.00" value="<?php echo $formData['amount']; ?>" required>
                </div>
                <?php if (isset($errors['amount'])): ?>
                    <p class="text-red-500 text-sm mt-1"><?php echo $errors['amount']; ?></p>
                <?php endif; ?>
            </div>
            
            <div>
                <label for="expense_category" class="block text-sm font-medium text-gray-700 mb-1">Category *</label>
                <div class="relative">
                    <select id="expense_category" name="expense_category" class="form-select w-full <?php echo isset($errors['expense_category']) ? 'border-red-500' : ''; ?>" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $formData['expense_category'] === $category ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category); ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="other" <?php echo !in_array($formData['expense_category'], $categories) && $formData['expense_category'] !== '' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div id="custom_category_container" class="mt-2" style="display: none;">
                    <input type="text" id="custom_category" class="form-input w-full" placeholder="Enter custom category">
                </div>
                <?php if (isset($errors['expense_category'])): ?>
                    <p class="text-red-500 text-sm mt-1"><?php echo $errors['expense_category']; ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="mb-6">
            <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description *</label>
            <textarea id="description" name="description" rows="3" class="form-textarea w-full <?php echo isset($errors['description']) ? 'border-red-500' : ''; ?>" required><?php echo htmlspecialchars($formData['description']); ?></textarea>
            <?php if (isset($errors['description'])): ?>
                <p class="text-red-500 text-sm mt-1"><?php echo $errors['description']; ?></p>
            <?php endif; ?>
        </div>
        
        <div class="mb-6">
            <label for="receipt_file" class="block text-sm font-medium text-gray-700 mb-1">Receipt (Optional)</label>
            <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                <div class="space-y-1 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <div class="flex text-sm text-gray-600">
                        <label for="receipt_file" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                            <span>Upload a file</span>
                            <input id="receipt_file" name="receipt_file" type="file" class="sr-only" accept=".jpg,.jpeg,.png,.pdf">
                        </label>
                        <p class="pl-1">or drag and drop</p>
                    </div>
                    <p class="text-xs text-gray-500">
                        JPG, PNG, PDF up to 5MB
                    </p>
                </div>
            </div>
            <div id="file_name_display" class="mt-2 text-sm text-gray-600 hidden">
                Selected file: <span id="selected_file_name"></span>
                <button type="button" id="remove_file" class="ml-2 text-red-600 hover:text-red-800">
                    <i class="fas fa-times"></i> Remove
                </button>
            </div>
            <?php if (isset($errors['receipt_file'])): ?>
                <p class="text-red-500 text-sm mt-1"><?php echo $errors['receipt_file']; ?></p>
            <?php endif; ?>
        </div>
        
        <div class="flex justify-end space-x-4 mt-8">
            <a href="<?php echo $caseId ? '../cases/view.php?id=' . $caseId : 'index.php'; ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg">
                Cancel
            </a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg">
                Create Expense
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle custom category selection
    const categorySelect = document.getElementById('expense_category');
    const customCategoryContainer = document.getElementById('custom_category_container');
    const customCategoryInput = document.getElementById('custom_category');
    
    categorySelect.addEventListener('change', function() {
        if (this.value === 'other') {
            customCategoryContainer.style.display = 'block';
            customCategoryInput.setAttribute('name', 'expense_category');
            this.removeAttribute('name');
        } else {
            customCategoryContainer.style.display = 'none';
            customCategoryInput.removeAttribute('name');
            this.setAttribute('name', 'expense_category');
        }
    });
    
    // Trigger change event to set initial state
    categorySelect.dispatchEvent(new Event('change'));
    
    // File upload preview
    const receiptFileInput = document.getElementById('receipt_file');
    const fileNameDisplay = document.getElementById('file_name_display');
    const selectedFileName = document.getElementById('selected_file_name');
    const removeFileButton = document.getElementById('remove_file');
    
    receiptFileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            selectedFileName.textContent = this.files[0].name;
            fileNameDisplay.classList.remove('hidden');
        } else {
            fileNameDisplay.classList.add('hidden');
        }
    });
    
    removeFileButton.addEventListener('click', function() {
        receiptFileInput.value = '';
        fileNameDisplay.classList.add('hidden');
    });
    
    // Drag and drop functionality
    const dropZone = document.querySelector('.border-dashed');
    
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, highlight, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, unhighlight, false);
    });
    
    function highlight() {
        dropZone.classList.add('border-blue-300', 'bg-blue-50');
    }
    
    function unhighlight() {
        dropZone.classList.remove('border-blue-300', 'bg-blue-50');
    }
    
    dropZone.addEventListener('drop', handleDrop, false);
    
    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        
        if (files.length > 0) {
            receiptFileInput.files = files;
            selectedFileName.textContent = files[0].name;
            fileNameDisplay.classList.remove('hidden');
        }
    }
});
</script>

<?php
// Include footer
include_once '../../includes/footer.php';
?>
