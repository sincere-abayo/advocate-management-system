<?php
// Set page title
$pageTitle = "Edit Document";

// Include header
include_once '../includes/header.php';

// Get advocate ID
$advocateId = $advocateData['advocate_id'];

// Check if document ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirectWithMessage('index.php', 'Invalid document ID', 'error');
    exit;
}

$documentId = (int)$_GET['id'];

// Get database connection
$conn = getDBConnection();

// Get document details and verify advocate has access
$stmt = $conn->prepare("
    SELECT d.*, c.case_id, c.case_number, c.title as case_title
    FROM documents d
    JOIN cases c ON d.case_id = c.case_id
    JOIN case_assignments ca ON c.case_id = ca.case_id
    WHERE d.document_id = ? AND ca.advocate_id = ?
");
$stmt->bind_param("ii", $documentId, $advocateId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    redirectWithMessage('index.php', 'You do not have access to this document', 'error');
    exit;
}

$document = $result->fetch_assoc();
$stmt->close();

// Get document types for dropdown
$documentTypes = [
    'Pleading', 'Motion', 'Order', 'Judgment', 'Contract', 'Correspondence', 
    'Evidence', 'Exhibit', 'Affidavit', 'Declaration', 'Brief', 'Transcript',
    'Settlement Agreement', 'Financial Record', 'Medical Record', 'Police Report',
    'Expert Report', 'Witness Statement', 'Legal Research', 'Other'
];

// Initialize form data and errors
$formData = [
    'title' => $document['title'],
    'document_type' => $document['document_type'],
    'description' => $document['description']
];
$errors = [];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form data
    $formData['title'] = sanitizeInput($_POST['title'] ?? '');
    $formData['document_type'] = sanitizeInput($_POST['document_type'] ?? '');
    $formData['description'] = sanitizeInput($_POST['description'] ?? '');
    
    // Validate title
    if (empty($formData['title'])) {
        $errors['title'] = 'Document title is required';
    } elseif (strlen($formData['title']) > 255) {
        $errors['title'] = 'Document title cannot exceed 255 characters';
    }
    
    // Validate document type
    if (empty($formData['document_type'])) {
        $errors['document_type'] = 'Document type is required';
    }
    
    // If no errors, update document info
    if (empty($errors)) {
        $updateStmt = $conn->prepare("
            UPDATE documents 
            SET title = ?, document_type = ?, description = ?
            WHERE document_id = ?
        ");
        $updateStmt->bind_param("sssi", 
            $formData['title'], 
            $formData['document_type'], 
            $formData['description'], 
            $documentId
        );
        
        if ($updateStmt->execute()) {
            // Add case activity
            $activityDesc = "Document updated: " . $formData['title'];
            addCaseActivity($document['case_id'], $_SESSION['user_id'], 'document', $activityDesc);
            
            // Set success message and redirect
            redirectWithMessage('index.php', 'Document updated successfully', 'success');
            exit;
        } else {
            $errors['general'] = "Failed to update document information. Please try again.";
        }
        
        $updateStmt->close();
    }
}

// Close database connection
$conn->close();
?>

<div class="mb-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Edit Document</h1>
            <p class="text-gray-600">
                Update document details for: <?php echo htmlspecialchars($document['title']); ?>
            </p>
        </div>
        
        <div class="mt-4 md:mt-0">
            <a href="index.php" class="btn-secondary">
                <i class="fas fa-arrow-left mr-2"></i> Back to Documents
            </a>
        </div>
    </div>
</div>

<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="p-6">
        <?php if (isset($errors['general'])): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p><?php echo $errors['general']; ?></p>
            </div>
        <?php endif; ?>
        
        <div class="mb-6">
            <div class="flex items-center p-4 bg-blue-50 rounded-lg">
                <div class="flex-shrink-0 mr-4">
                    <?php
                    $fileExtension = pathinfo($document['file_path'], PATHINFO_EXTENSION);
                    $iconClass = 'fas fa-file text-blue-500';
                    
                    switch (strtolower($fileExtension)) {
                        case 'pdf':
                            $iconClass = 'fas fa-file-pdf text-red-500';
                            break;
                        case 'doc':
                        case 'docx':
                            $iconClass = 'fas fa-file-word text-blue-600';
                            break;
                        case 'xls':
                        case 'xlsx':
                            $iconClass = 'fas fa-file-excel text-green-600';
                            break;
                        case 'ppt':
                        case 'pptx':
                            $iconClass = 'fas fa-file-powerpoint text-orange-500';
                            break;
                        case 'jpg':
                        case 'jpeg':
                        case 'png':
                        case 'gif':
                            $iconClass = 'fas fa-file-image text-purple-500';
                            break;
                        case 'zip':
                        case 'rar':
                            $iconClass = 'fas fa-file-archive text-yellow-600';
                            break;
                        case 'txt':
                            $iconClass = 'fas fa-file-alt text-gray-600';
                            break;
                    }
                    ?>
                    <i class="<?php echo $iconClass; ?> text-3xl"></i>
                </div>
                <div>
                    <h3 class="font-medium text-blue-800">Current Document</h3>
                    <p class="text-sm text-blue-600">
                        <?php echo htmlspecialchars(basename($document['file_path'])); ?>
                    </p>
                    <div class="mt-2 flex space-x-3">
                        <a href="../../uploads/documents/<?php echo $document['file_path']; ?>" target="_blank" class="text-sm text-blue-600 hover:text-blue-800">
                            <i class="fas fa-eye mr-1"></i> View
                        </a>
                        <a href="../../uploads/?php echo $document['file_path']; ?>" download class="text-sm text-blue-600 hover:text-blue-800">
                            <i class="fas fa-download mr-1"></i> Download
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <form method="POST" action="" class="space-y-6">
            <div>
                <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Document Title *</label>
                <input type="text" id="title" name="title" class="form-input w-full <?php echo isset($errors['title']) ? 'border-red-500' : ''; ?>" value="<?php echo htmlspecialchars($formData['title']); ?>" required>
                <?php if (isset($errors['title'])): ?>
                    <p class="text-red-500 text-sm mt-1"><?php echo $errors['title']; ?></p>
                <?php endif; ?>
            </div>
            
            <div>
                <label for="document_type" class="block text-sm font-medium text-gray-700 mb-1">Document Type *</label>
                <select id="document_type" name="document_type" class="form-select w-full <?php echo isset($errors['document_type']) ? 'border-red-500' : ''; ?>" required>
                    <option value="">Select Document Type</option>
                    <?php foreach ($documentTypes as $type): ?>
                        <option value="<?php echo $type; ?>" <?php echo $formData['document_type'] === $type ? 'selected' : ''; ?>>
                            <?php echo $type; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['document_type'])): ?>
                    <p class="text-red-500 text-sm mt-1"><?php echo $errors['document_type']; ?></p>
                <?php endif; ?>
            </div>
            
            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea id="description" name="description" rows="4" class="form-textarea w-full"><?php echo htmlspecialchars($formData['description']); ?></textarea>
            </div>
            
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="text-sm font-medium text-gray-700 mb-2">Case Information</h3>
                <p class="text-sm text-gray-600">
                    <span class="font-medium">Case Number:</span> 
                    <a href="../cases/view.php?id=<?php echo $document['case_id']; ?>" class="text-blue-600 hover:underline">
                        <?php echo htmlspecialchars($document['case_number']); ?>
                    </a>
                </p>
                <p class="text-sm text-gray-600">
                    <span class="font-medium">Case Title:</span> 
                    <?php echo htmlspecialchars($document['case_title']); ?>
                </p>
                <p class="text-sm text-gray-500 mt-1">
                    <i class="fas fa-info-circle mr-1"></i> To move this document to a different case, please delete it and upload it to the new case.
                </p>
            </div>
            
            <div class="flex justify-end space-x-4">
                <a href="index.php" class="btn-secondary">
                    Cancel
                </a>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save mr-2"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.btn-primary {
    @apply bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition duration-150 inline-flex items-center;
}

.btn-secondary {
    @apply bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg transition duration-150 inline-flex items-center;
}

.form-input, .form-select, .form-textarea {
    @apply mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500;
}
</style>

<?php
// Include footer
include_once '../includes/footer.php';
?>