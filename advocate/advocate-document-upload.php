<?php
// Start session
session_start();

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Check if user has advocate role
if($_SESSION['role'] != 'advocate') {
    header("Location: ../login.php");
    exit();
}

// Check if case ID is provided
if(!isset($_GET['case_id']) || empty($_GET['case_id'])) {
    header("Location: advocate-cases.php");
    exit();
}

// Include database and required classes
include_once '../config/database.php';
include_once '../classes/Advocate.php';
include_once '../classes/Case.php';
include_once '../classes/Document.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Initialize objects
$advocate_obj = new Advocate($db);
$case_obj = new LegalCase($db);
$document_obj = new Document($db);

// Get advocate ID
$advocate_obj->user_id = $_SESSION['user_id'];
if(!$advocate_obj->readByUserId()) {
    header("Location: advocate-dashboard.php");
    exit();
}

// Set case ID
$case_obj->id = $_GET['case_id'];

// Read case details
if(!$case_obj->readOne()) {
    header("Location: advocate-cases.php");
    exit();
}

// Check if the case belongs to the logged-in advocate
if($case_obj->advocate_id != $advocate_obj->id) {
    header("Location: advocate-cases.php");
    exit();
}

// Process form submission
$upload_success = $upload_error = '';
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check if file was uploaded without errors
    if(isset($_FILES['document']) && $_FILES['document']['error'] == 0) {
        $allowed = array('pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png');
        $filename = $_FILES['document']['name'];
        $file_ext = pathinfo($filename, PATHINFO_EXTENSION);
        
        // Validate file extension
        if(in_array(strtolower($file_ext), $allowed)) {
            // Create upload directory if it doesn't exist
            $upload_dir = '../uploads/documents/';
            if(!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $new_filename = uniqid() . '_' . $filename;
            $upload_path = $upload_dir . $new_filename;
            
            // Move uploaded file
            if(move_uploaded_file($_FILES['document']['tmp_name'], $upload_path)) {
                // Set document properties
                $document_obj->case_id = $case_obj->id;
                $document_obj->title = $_POST['title'];
                $document_obj->file_path = 'uploads/documents/' . $new_filename;
                $document_obj->file_type = $file_ext;
                $document_obj->file_size = $_FILES['document']['size'];
                $document_obj->description = $_POST['description'];
                $document_obj->uploaded_by = $_SESSION['user_id'];
                
                // Create document record
                if($document_obj->create()) {
                    // Add to case history
                    $case_obj->addToHistory($case_obj->id, "Document uploaded", "Document '{$_POST['title']}' was uploaded");
                    
                    $upload_success = "Document uploaded successfully.";
                } else {
                    $upload_error = "Failed to save document information.";
                }
            } else {
                $upload_error = "Failed to upload document.";
            }
        } else {
            $upload_error = "Invalid file type. Allowed types: " . implode(', ', $allowed);
        }
    } else {
        $upload_error = "Please select a document to upload.";
    }
}

// Get existing documents for this case
$document_obj->case_id = $case_obj->id;
$documents = $document_obj->readByCaseId();

// Set page title
$page_title = "Upload Document - " . $case_obj->case_number;

// Include header
include_once '../templates/advocate-header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-semibold text-gray-800">Upload Document</h1>
        <a href="advocate-case-view.php?id=<?php echo $case_obj->id; ?>" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
            <i class="fas fa-arrow-left mr-2"></i>Back to Case
        </a>
    </div>
    
    <!-- Case Information -->
    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
        <div class="p-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Case Information</h2>
        </div>
        <div class="p-4">
            <div class="flex flex-wrap">
                <div class="w-full md:w-1/2 lg:w-1/3 mb-4 pr-2">
                    <p class="text-sm font-medium text-gray-500">Case Number</p>
                    <p class="text-base text-gray-900"><?php echo htmlspecialchars($case_obj->case_number); ?></p>
                </div>
                <div class="w-full md:w-1/2 lg:w-1/3 mb-4 pr-2">
                    <p class="text-sm font-medium text-gray-500">Title</p>
                    <p class="text-base text-gray-900"><?php echo htmlspecialchars($case_obj->title); ?></p>
                </div>
                <div class="w-full md:w-1/2 lg:w-1/3 mb-4 pr-2">
                    <p class="text-sm font-medium text-gray-500">Client</p>
                    <p class="text-base text-gray-900">
                        <?php echo isset($case_obj->client_name) ? htmlspecialchars($case_obj->client_name) : 'N/A'; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <?php if(!empty($upload_success)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p><?php echo $upload_success; ?></p>
        </div>
    <?php endif; ?>
    
    <?php if(!empty($upload_error)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p><?php echo $upload_error; ?></p>
        </div>
    <?php endif; ?>
    
    <!-- Upload Form -->
    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
        <div class="p-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Upload New Document</h2>
        </div>
        <div class="p-4">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?case_id=" . $case_obj->id); ?>" method="post" enctype="multipart/form-data">
                <div class="mb-4">
                    <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Document Title</label>
                    <input type="text" id="title" name="title" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                </div>
                
                <div class="mb-4">
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea id="description" name="description" rows="3" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"></textarea>
                </div>
                
                <div class="mb-4">
                    <label for="document" class="block text-sm font-medium text-gray-700 mb-1">Document File</label>
                    <input type="file" id="document" name="document" class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none" required>
                    <p class="mt-1 text-sm text-gray-500">Allowed file types: PDF, DOC, DOCX, JPG, JPEG, PNG</p>
                </div>
                
                <div class="flex justify-end">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-medium rounded-lg text-sm px-5 py-2.5 text-center">
                        Upload Document
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Existing Documents -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Case Documents</h2>
        </div>
        <div class="p-4">
            <?php if($documents && $documents->rowCount() > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Size</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Uploaded By</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while($document = $documents->fetch(PDO::FETCH_ASSOC)): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($document['title']); ?>
                                        <?php if(!empty($document['description'])): ?>
                                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($document['description']); ?></p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo strtoupper(htmlspecialchars($document['file_type'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php 
                                            $size_kb = round($document['file_size'] / 1024, 2);
                                            echo $size_kb > 1024 ? round($size_kb / 1024, 2) . ' MB' : $size_kb . ' KB'; 
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($document['uploader_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M d, Y', strtotime($document['upload_date'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="../<?php echo $document['file_path']; ?>" target="_blank" class="text-blue-600 hover:text-blue-900 mr-3">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="../<?php echo $document['file_path']; ?>" download class="text-green-600 hover:text-green-900">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <p class="text-gray-500">No documents uploaded for this case yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../templates/footer.php';
?>
