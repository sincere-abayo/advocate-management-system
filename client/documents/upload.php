<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user is logged in and is a client
requireLogin();
requireUserType('client');

// Get client ID from session
$clientId = $_SESSION['client_id'];

// Initialize variables
$errors = [];
$success = false;
$formData = [
    'case_id' => isset($_GET['case_id']) ? (int)$_GET['case_id'] : 0,
    'title' => '',
    'document_type' => '',
    'description' => ''
];

// Connect to database
$conn = getDBConnection();

// Get cases for dropdown
$casesQuery = "SELECT case_id, case_number, title FROM cases WHERE client_id = ? ORDER BY case_number";
$casesStmt = $conn->prepare($casesQuery);
$casesStmt->bind_param("i", $clientId);
$casesStmt->execute();
$casesResult = $casesStmt->get_result();

$cases = [];
while ($case = $casesResult->fetch_assoc()) {
    $cases[] = $case;
}

// Get document types for dropdown
$typesQuery = "
    SELECT DISTINCT document_type 
    FROM documents 
    WHERE document_type IS NOT NULL AND document_type != ''
    ORDER BY document_type
";
$typesResult = $conn->query($typesQuery);

$documentTypes = [];
while ($type = $typesResult->fetch_assoc()) {
    $documentTypes[] = $type['document_type'];
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate case ID
    if (empty($_POST['case_id'])) {
        $errors['case_id'] = 'Please select a case';
    } else {
        $formData['case_id'] = (int)$_POST['case_id'];
        
        // Verify the case belongs to the client
        $caseCheckStmt = $conn->prepare("SELECT case_id FROM cases WHERE case_id = ? AND client_id = ?");
        $caseCheckStmt->bind_param("ii", $formData['case_id'], $clientId);
        $caseCheckStmt->execute();
        if ($caseCheckStmt->get_result()->num_rows === 0) {
            $errors['case_id'] = 'Invalid case selected';
        }
    }
    
    // Validate title
    if (empty($_POST['title'])) {
        $errors['title'] = 'Document title is required';
    } else {
        $formData['title'] = $_POST['title'];
    }
    
    // Document type (optional)
    $formData['document_type'] = $_POST['document_type'] ?? '';
    
    // Description (optional)
    $formData['description'] = $_POST['description'] ?? '';
    
    // Validate file upload
    if (empty($_FILES['document']) || $_FILES['document']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors['document'] = 'Please select a file to upload';
    } else if ($_FILES['document']['error'] !== UPLOAD_ERR_OK) {
        switch ($_FILES['document']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errors['document'] = 'The file is too large';
                break;
            default:
                $errors['document'] = 'Error uploading file. Please try again.';
        }
    } else {
        // Check file size (limit to 10MB)
        if ($_FILES['document']['size'] > 10 * 1024 * 1024) {
            $errors['document'] = 'File size must be less than 10MB';
        }
        
        // Check file type
        $allowedTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'image/jpeg',
            'image/png',
            'application/zip',
            'application/x-rar-compressed',
            'text/plain'
        ];
        
        $fileType = $_FILES['document']['type'];
        if (!in_array($fileType, $allowedTypes)) {
            $errors['document'] = 'Invalid file type. Allowed types: PDF, Word, Excel, PowerPoint, Images, ZIP, RAR, and Text files';
        }
        
        // Check file extension as an additional security measure
        $fileExtension = strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'zip', 'rar', 'txt'];
        
        if (!in_array($fileExtension, $allowedExtensions)) {
            $errors['document'] = 'Invalid file extension. Allowed extensions: pdf, doc, docx, xls, xlsx, ppt, pptx, jpg, jpeg, png, zip, rar, txt';
        }
    }
    
    // If no errors, process the upload
    if (empty($errors)) {
        try {
            // Create upload directory if it doesn't exist
            $uploadDir = '../../uploads/documents/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Generate a unique filename
            $fileName = uniqid('doc_') . '_' . time() . '.' . $fileExtension;
            $filePath = $uploadDir . $fileName;
            
            // Move the uploaded file
            if (move_uploaded_file($_FILES['document']['tmp_name'], $filePath)) {
                // Store relative path in database
                $dbFilePath = 'uploads/documents/' . $fileName;
                
                // Insert document record
                $stmt = $conn->prepare("
                    INSERT INTO documents (
                        case_id, title, file_path, document_type, 
                        description, uploaded_by, upload_date
                    ) VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $userId = $_SESSION['user_id'];
                $stmt->bind_param(
                    "issssi",
                    $formData['case_id'],
                    $formData['title'],
                    $dbFilePath,
                    $formData['document_type'],
                    $formData['description'],
                    $userId
                );
                
                if ($stmt->execute()) {
                    $documentId = $conn->insert_id;
                    
                    // Add case activity
                    $activityDesc = "Document uploaded: " . $formData['title'];
                    addCaseActivity($formData['case_id'], $userId, 'document', $activityDesc);
                    
                    // Set success message
                    $success = true;
                    
                    // Reset form data
                    $formData = [
                        'case_id' => $formData['case_id'], // Keep the case ID for convenience
                        'title' => '',
                        'document_type' => '',
                        'description' => ''
                    ];
                    
                    // Redirect to the document view page
                    $_SESSION['flash_message'] = "Document uploaded successfully!";
                    $_SESSION['flash_type'] = "success";
                    header("Location: view.php?id=" . $documentId);
                    exit;
                } else {
                    $errors['general'] = "Error saving document: " . $conn->error;
                    // Remove the uploaded file if database insert fails
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                }
            } else {
                $errors['document'] = "Failed to move uploaded file";
            }
        } catch (Exception $e) {
            $errors['general'] = "An error occurred: " . $e->getMessage();
        }
    }
}

// Close connection
$conn->close();

// Set page title
$pageTitle = "Upload Document";
include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Upload Document</h1>
            <p class="text-gray-600">Add a new document to your case</p>
        </div>
        
        <a href="index.php<?php echo $formData['case_id'] ? '?case_id=' . $formData['case_id'] : ''; ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg inline-flex items-center">
            <i class="fas fa-arrow-left mr-2"></i> Back to Documents
        </a>
    </div>
    
    <div class="bg-white rounded-lg shadow-md p-6">
        <?php if ($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p>Document uploaded successfully!</p>
            </div>
        <?php endif; ?>
        
        <?php if (isset($errors['general'])): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p><?php echo $errors['general']; ?></p>
            </div>
        <?php endif; ?>
        
        <form action="" method="POST" enctype="multipart/form-data" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="case_id" class="block text-sm font-medium text-gray-700 mb-1">Case *</label>
                    <select id="case_id" name="case_id" class="form-select w-full <?php echo isset($errors['case_id']) ? 'border-red-500' : ''; ?>" required>
                        <option value="">Select Case</option>
                        <?php foreach ($cases as $case): ?>
                            <option value="<?php echo $case['case_id']; ?>" <?php echo $formData['case_id'] == $case['case_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($case['case_number'] . ' - ' . $case['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['case_id'])): ?>
                        <p class="text-red-500 text-sm mt-1"><?php echo $errors['case_id']; ?></p>
                    <?php endif; ?>
                </div>
                
                <div>
                    <label for="document_type" class="block text-sm font-medium text-gray-700 mb-1">Document Type</label>
                    <select id="document_type" name="document_type" class="form-select w-full">
                        <option value="">Select Type (Optional)</option>
                        <?php foreach ($documentTypes as $type): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $formData['document_type'] === $type ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type); ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="other" <?php echo !in_array($formData['document_type'], $documentTypes) && !empty($formData['document_type']) ? 'selected' : ''; ?>>Other</option>
                    </select>
                    <div id="custom_type_container" class="mt-2" style="display: none;">
                        <input type="text" id="custom_document_type" class="form-input w-full" placeholder="Enter custom document type">
                    </div>
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
                <textarea id="description" name="description" rows="3" class="form-textarea w-full" placeholder="Optional description of the document"><?php echo htmlspecialchars($formData['description']); ?></textarea>
            </div>
            
            <div>
                <label for="document" class="block text-sm font-medium text-gray-700 mb-1">Document File *</label>
                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                    <div class="space-y-1 text-center">
                        <i class="fas fa-file-upload text-gray-400 text-3xl mb-2"></i>
                        <div class="flex text-sm text-gray-600">
                            <label for="document" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none">
                                <span>Upload a file</span>
                                <input id="document" name="document" type="file" class="sr-only" required>
                            </label>
                            <p class="pl-1">or drag and drop</p>
                        </div>
                        <p class="text-xs text-gray-500">
                            PDF, Word, Excel, PowerPoint, Images, ZIP, RAR up to 10MB
                        </p>
                        <div id="file-name" class="text-sm text-gray-800 mt-2 hidden">
                            Selected file: <span class="font-medium"></span>
                        </div>
                    </div>
                </div>
                <?php if (isset($errors['document'])): ?>
                    <p class="text-red-500 text-sm mt-1"><?php echo $errors['document']; ?></p>
                <?php endif; ?>
            </div>
            
            <div class="flex items-center justify-end space-x-3 pt-4">
                <a href="index.php<?php echo $formData['case_id'] ? '?case_id=' . $formData['case_id'] : ''; ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg">
                    Cancel
                </a>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg">
                    <i class="fas fa-upload mr-2"></i> Upload Document
                </button>
            </div>
        </form>
    </div>
    
    <!-- Document Upload Tips -->
    <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
        <h3 class="text-lg font-medium text-blue-800 mb-2">Document Upload Tips</h3>
        <ul class="list-disc pl-5 text-blue-700 space-y-1">
            <li>Make sure to select the correct case for your document</li>
            <li>Use descriptive titles to easily identify documents later</li>
            <li>Supported file types: PDF, Word, Excel, PowerPoint, Images, ZIP, RAR, and Text files</li>
            <li>Maximum file size is 10MB</li>
            <li>For larger files, please contact your advocate directly</li>
        </ul>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle custom document type
    const documentTypeSelect = document.getElementById('document_type');
    const customTypeContainer = document.getElementById('custom_type_container');
    const customTypeInput = document.getElementById('custom_document_type');
    
    documentTypeSelect.addEventListener('change', function() {
        if (this.value === 'other') {
            customTypeContainer.style.display = 'block';
            customTypeInput.setAttribute('name', 'document_type');
            documentTypeSelect.removeAttribute('name');
        } else {
            customTypeContainer.style.display = 'none';
            customTypeInput.removeAttribute('name');
            documentTypeSelect.setAttribute('name', 'document_type');
        }
    });
    
    // Trigger change event to set initial state
    documentTypeSelect.dispatchEvent(new Event('change'));
    
    // Display selected file name
    const fileInput = document.getElementById('document');
    const fileNameContainer = document.getElementById('file-name');
    const fileNameSpan = fileNameContainer.querySelector('span');
    
    fileInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            fileNameSpan.textContent = this.files[0].name;
            fileNameContainer.classList.remove('hidden');
        } else {
            fileNameContainer.classList.add('hidden');
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
        dropZone.classList.add('border-blue-300', 'bg-blue-50');
    }
    
    function unhighlight() {
        dropZone.classList.remove('border-blue-300', 'bg-blue-50');
    }
    
    dropZone.addEventListener('drop', handleDrop, false);
    
    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        
        if (files.length) {
            fileInput.files = files;
            fileNameSpan.textContent = files[0].name;
            fileNameContainer.classList.remove('hidden');
        }
    }
});
</script>

<?php
// Include footer
include '../includes/footer.php';
?>
