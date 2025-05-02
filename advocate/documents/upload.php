<?php
// Set page title
$pageTitle = "Upload Document";

// Include header
include_once '../includes/header.php';

// Get advocate ID
$advocateId = $advocateData['advocate_id'];

// Check if case ID is provided
$caseId = isset($_GET['case_id']) ? (int)$_GET['case_id'] : null;
$caseDetails = null;

// Get database connection
$conn = getDBConnection();

// If case ID is provided, verify that the advocate has access to this case
if ($caseId) {
    $caseStmt = $conn->prepare("
        SELECT c.*, cp.client_id, u.full_name as client_name
        FROM cases c
        JOIN case_assignments ca ON c.case_id = ca.case_id
        JOIN client_profiles cp ON c.client_id = cp.client_id
        JOIN users u ON cp.user_id = u.user_id
        WHERE c.case_id = ? AND ca.advocate_id = ?
    ");
    $caseStmt->bind_param("ii", $caseId, $advocateId);
    $caseStmt->execute();
    $caseResult = $caseStmt->get_result();
    
    if ($caseResult->num_rows === 0) {
        redirectWithMessage('/advocate/cases/index.php', 'You do not have access to this case', 'error');
        exit;
    }
    
    $caseDetails = $caseResult->fetch_assoc();
    $caseStmt->close();
}

// Get all cases assigned to the advocate for the dropdown
$casesStmt = $conn->prepare("
    SELECT c.case_id, c.case_number, c.title, u.full_name as client_name
    FROM cases c
    JOIN case_assignments ca ON c.case_id = ca.case_id
    JOIN client_profiles cp ON c.client_id = cp.client_id
    JOIN users u ON cp.user_id = u.user_id
    WHERE ca.advocate_id = ?
    ORDER BY c.created_at DESC
");
$casesStmt->bind_param("i", $advocateId);
$casesStmt->execute();
$casesResult = $casesStmt->get_result();
$cases = [];
while ($case = $casesResult->fetch_assoc()) {
    $cases[] = $case;
}
$casesStmt->close();

// Get document types for dropdown
$documentTypes = [
    'Pleading', 'Motion', 'Order', 'Judgment', 'Contract', 'Correspondence', 
    'Evidence', 'Exhibit', 'Affidavit', 'Declaration', 'Brief', 'Transcript',
    'Settlement Agreement', 'Financial Record', 'Medical Record', 'Police Report',
    'Expert Report', 'Witness Statement', 'Legal Research', 'Other'
];

// Initialize form data and errors
$formData = [
    'case_id' => $caseId,
    'title' => '',
    'document_type' => '',
    'description' => ''
];
$errors = [];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form data
    $formData['case_id'] = isset($_POST['case_id']) ? (int)$_POST['case_id'] : null;
    $formData['title'] = sanitizeInput($_POST['title'] ?? '');
    $formData['document_type'] = sanitizeInput($_POST['document_type'] ?? '');
    $formData['description'] = sanitizeInput($_POST['description'] ?? '');
    
    // Validate case ID
    if (empty($formData['case_id'])) {
        $errors['case_id'] = 'Please select a case';
    } else {
        // Verify advocate has access to this case
        $caseCheckStmt = $conn->prepare("
            SELECT c.case_id
            FROM cases c
            JOIN case_assignments ca ON c.case_id = ca.case_id
            WHERE c.case_id = ? AND ca.advocate_id = ?
        ");
        $caseCheckStmt->bind_param("ii", $formData['case_id'], $advocateId);
        $caseCheckStmt->execute();
        $caseCheckResult = $caseCheckStmt->get_result();
        
        if ($caseCheckResult->num_rows === 0) {
            $errors['case_id'] = 'You do not have access to this case';
        }
        
        $caseCheckStmt->close();
    }
    
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
    
    // Validate file upload
    if (!isset($_FILES['document']) || $_FILES['document']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors['document'] = 'Please select a file to upload';
    } elseif ($_FILES['document']['error'] !== UPLOAD_ERR_OK) {
        $errors['document'] = 'File upload failed: ' . getFileUploadErrorMessage($_FILES['document']['error']);
    } else {
        // Check file size (max 10MB)
        if ($_FILES['document']['size'] > 10 * 1024 * 1024) {
            $errors['document'] = 'File size cannot exceed 10MB';
        }
        
        // Check file type
        $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 
                         'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                         'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                         'image/jpeg', 'image/png', 'text/plain', 'application/zip', 'application/x-rar-compressed'];
        
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $fileType = $finfo->file($_FILES['document']['tmp_name']);
        
        if (!in_array($fileType, $allowedTypes)) {
            $errors['document'] = 'Invalid file type. Allowed types: PDF, Word, Excel, PowerPoint, Images, Text, ZIP, RAR';
        }
    }
    
    // If no errors, upload the file and save document info
    if (empty($errors)) {
        // Create upload directory if it doesn't exist
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . $path_url . '/uploads/documents/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generate unique filename
        $fileExtension = pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION);
        $fileName = 'doc_' . time() . '_' . uniqid() . '.' . $fileExtension;
        $filePath = $fileName;
        $fullPath = $uploadDir . $fileName;
        
        // Move uploaded file
        if (move_uploaded_file($_FILES['document']['tmp_name'], $fullPath)) {
            // Insert document record
            $stmt = $conn->prepare("
                INSERT INTO documents (case_id, title, file_path, document_type, description, uploaded_by, upload_date)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $userId = $_SESSION['user_id'];
            $stmt->bind_param("issssi", 
                $formData['case_id'], 
                $formData['title'], 
                $filePath, 
                $formData['document_type'], 
                $formData['description'], 
                $userId
            );
            
            if ($stmt->execute()) {
                $documentId = $stmt->insert_id;
                
                // Add case activity
                $activityDesc = "Document uploaded: " . $formData['title'];
                addCaseActivity($formData['case_id'], $_SESSION['user_id'], 'document', $activityDesc);
                
                // Set success message and redirect
                $_SESSION['flash_message'] = "Document uploaded successfully.";
                $_SESSION['flash_type'] = "success";
                
                if ($caseId) {
                    header("Location: ../cases/view.php?id=$caseId");
                } else {
                    header("Location: index.php");
                }
                exit;
            } else {
                $errors['general'] = "Failed to save document information. Please try again.";
                // Delete the uploaded file if database insert fails
                unlink($fullPath);
            }
            
            $stmt->close();
        } else {
            $errors['document'] = "Failed to upload file. Please try again.";
        }
    }
}

// Helper function to get file upload error message
function getFileUploadErrorMessage($errorCode) {
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
            return "The uploaded file exceeds the upload_max_filesize directive in php.ini";
        case UPLOAD_ERR_FORM_SIZE:
            return "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form";
        case UPLOAD_ERR_PARTIAL:
            return "The uploaded file was only partially uploaded";
        case UPLOAD_ERR_NO_FILE:
            return "No file was uploaded";
        case UPLOAD_ERR_NO_TMP_DIR:
            return "Missing a temporary folder";
        case UPLOAD_ERR_CANT_WRITE:
            return "Failed to write file to disk";
        case UPLOAD_ERR_EXTENSION:
            return "A PHP extension stopped the file upload";
        default:
            return "Unknown upload error";
    }
}

// Close database connection
$conn->close();
?>

<div class="mb-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Upload Document</h1>
            <p class="text-gray-600">
                <?php if ($caseId): ?>
                    Upload a document for case: <?php echo htmlspecialchars($caseDetails['case_number'] . ' - ' . $caseDetails['title']); ?>
                <?php else: ?>
                    Upload a document to a case
                <?php endif; ?>
            </p>
        </div>
        
        <div class="mt-4 md:mt-0">
            <?php if ($caseId): ?>
                <a href="../cases/view.php?id=<?php echo $caseId; ?>" class="btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Case
                </a>
            <?php else: ?>
                <a href="index.php" class="btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Documents
                </a>
            <?php endif; ?>
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
        
        <form method="POST" action="" enctype="multipart/form-data" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="case_id" class="block text-sm font-medium text-gray-700 mb-1">Case *</label>
                    <select id="case_id" name="case_id" class="form-select w-full <?php echo isset($errors['case_id']) ? 'border-red-500' : ''; ?>" <?php echo $caseId ? 'disabled' : 'required'; ?>>
                        <option value="">Select a Case</option>
                        <?php foreach ($cases as $case): ?>
                            <option value="<?php echo $case['case_id']; ?>" <?php echo $formData['case_id'] == $case['case_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($case['case_number'] . ' - ' . $case['title'] . ' (' . $case['client_name'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($caseId): ?>
                        <input type="hidden" name="case_id" value="<?php echo $caseId; ?>">
                    <?php endif; ?>
                    <?php if (isset($errors['case_id'])): ?>
                        <p class="text-red-500 text-sm mt-1"><?php echo $errors['case_id']; ?></p>
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
            </div>
            
            <div>
                <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Document Title *</label>
                <input type="text" id="title" name="title" class="form-input w-full <?php echo isset($errors['title']) ? 'border-red-500' : ''; ?>" value="<?php echo htmlspecialchars($formData['title']); ?>" required>
                <?php if (isset($errors['title'])): ?>
                    <p class="text-red-500 text-sm mt-1"><?php echo $errors['title']; ?></p>
                <?php endif; ?>
            </div>
            
            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea id="description" name="description" rows="3" class="form-textarea w-full"><?php echo htmlspecialchars($formData['description']); ?></textarea>
            </div>
            
            <div>
                <label for="document" class="block text-sm font-medium text-gray-700 mb-1">Document File *</label>
                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                    <div class="space-y-1 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <div class="flex text-sm text-gray-600">
                            <label for="document" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                <span>Upload a file</span>
                                <input id="document" name="document" type="file" class="sr-only">
                            </label>
                            <p class="pl-1">or drag and drop</p>
                        </div>
                        <p class="text-xs text-gray-500">
                            PDF, Word, Excel, PowerPoint, Images, Text, ZIP, RAR up to 10MB
                        </p>
                    </div>
                </div>
                <?php if (isset($errors['document'])): ?>
                    <p class="text-red-500 text-sm mt-1"><?php echo $errors['document']; ?></p>
                <?php endif; ?>
            </div>
            
            <div class="flex justify-end space-x-4">
                <?php if ($caseId): ?>
                    <a href="/advocate/cases/view.php?id=<?php echo $caseId; ?>" class="btn-secondary">
                        Cancel
                    </a>
                <?php else: ?>
                    <a href="/advocate/documents/index.php" class="btn-secondary">
                        Cancel
                    </a>
                <?php endif; ?>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-upload mr-2"></i> Upload Document
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // File input preview and validation
    const fileInput = document.getElementById('document');
    const fileInputLabel = document.querySelector('label[for="document"]');
    
    fileInput.addEventListener('change', function(e) {
        const fileName = e.target.files[0]?.name;
        if (fileName) {
            fileInputLabel.querySelector('span').textContent = 'Selected: ' + fileName;
        } else {
            fileInputLabel.querySelector('span').textContent = 'Upload a file';
        }
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
        dropZone.classList.add('border-blue-500', 'bg-blue-50');
    }
    
    function unhighlight() {
        dropZone.classList.remove('border-blue-500', 'bg-blue-50');
    }
    
    dropZone.addEventListener('drop', handleDrop, false);
    
    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        
        if (files.length) {
            fileInput.files = files;
            const event = new Event('change');
            fileInput.dispatchEvent(event);
        }
    }
});
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>