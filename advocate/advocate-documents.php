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

// Include database and required classes
include_once '../config/database.php';
include_once '../classes/Document.php';
include_once '../classes/Case.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Initialize objects
$document_obj = new Document($db);
$case_obj = new LegalCase($db);

// Handle search
$search_term = '';
if(isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = $_GET['search'];
    $documents = $document_obj->search($search_term);
} else {
    // Get all documents
    $documents = $document_obj->readAll();
}

// Set page title
$page_title = "Documents - Legal Case Management System";

// Include header
include_once '../templates/advocate-header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-semibold text-gray-800">Documents</h1>
        <div class="flex space-x-2">
            <form class="flex items-center">
                <div class="relative mr-2">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                        <i class="fas fa-search text-gray-400"></i>
                    </div>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search_term); ?>" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full pl-10 p-2.5" placeholder="Search documents...">
                </div>
                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
                    Search
                </button>
            </form>
        </div>
    </div>
    
    <!-- Include the message block -->
    <?php include_once '../templates/message-block.php'; ?>
    
    <!-- Documents Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">
                <?php 
                if(!empty($search_term)) {
                    echo "Search Results for \"" . htmlspecialchars($search_term) . "\"";
                } else {
                    echo "All Documents";
                }
                ?>
            </h2>
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
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Related Case</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Uploaded On</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while($document = $documents->fetch(PDO::FETCH_ASSOC)): ?>
                                <tr>
                                <td class="px-6 py-4 whitespace-normal text-sm font-medium text-gray-900 max-w-xs">
                                        <div class="truncate">
                                            <?php echo htmlspecialchars($document['title']); ?>
                                            <?php if(strlen($document['title']) > 50): ?>
                                                <button type="button" onclick="showFullText('<?php echo addslashes(htmlspecialchars($document['title'])); ?>', 'Document Title')" class="ml-1 text-blue-500 hover:text-blue-700">
                                                    <i class="fas fa-expand-alt text-xs"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        <?php if(!empty($document['description'])): ?>
                                            <p class="text-xs text-gray-500 truncate">
                                                <?php echo htmlspecialchars($document['description']); ?>
                                                <?php if(strlen($document['description']) > 50): ?>
                                                    <button type="button" onclick="showFullText('<?php echo addslashes(htmlspecialchars($document['description'])); ?>', 'Description')" class="ml-1 text-blue-500 hover:text-blue-700">
                                                        <i class="fas fa-expand-alt text-xs"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </p>
                                        <?php endif; ?>
                                    </td>


                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php 
                                        $file_type = strtoupper($document['file_type']);
                                        $type_class = '';
                                        
                                        switch(strtolower($document['file_type'])) {
                                            case 'pdf':
                                                $type_class = 'bg-red-100 text-red-800';
                                                break;
                                            case 'doc':
                                            case 'docx':
                                                $type_class = 'bg-blue-100 text-blue-800';
                                                break;
                                            case 'jpg':
                                            case 'jpeg':
                                            case 'png':
                                                $type_class = 'bg-green-100 text-green-800';
                                                break;
                                            default:
                                                $type_class = 'bg-gray-100 text-gray-800';
                                        }
                                        ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $type_class; ?>">
                                            <?php echo $file_type; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php 
                                        $size_kb = round($document['file_size'] / 1024, 2);
                                        echo $size_kb > 1024 ? round($size_kb / 1024, 2) . ' MB' : $size_kb . ' KB'; 
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php if(!empty($document['case_id']) && !empty($document['case_number'])): ?>
                                            <a href="advocate-case-view.php?id=<?php echo $document['case_id']; ?>" class="text-blue-600 hover:text-blue-800">
                                                <?php echo htmlspecialchars($document['case_number']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gray-400">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M d, Y', strtotime($document['upload_date'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="../<?php echo $document['file_path']; ?>" target="_blank" class="text-blue-600 hover:text-blue-900 mr-3" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="../<?php echo $document['file_path']; ?>" download class="text-green-600 hover:text-green-900 mr-3" title="Download">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <a href="#" onclick="confirmDelete(<?php echo $document['id']; ?>); return false;" class="text-red-600 hover:text-red-900" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <p class="text-gray-500 text-lg">
                        <?php 
                        if(!empty($search_term)) {
                            echo "No documents found matching \"" . htmlspecialchars($search_term) . "\".";
                        } else {
                            echo "No documents found.";
                        }
                        ?>
                    </p>
                    <a href="advocate-document-upload.php" class="mt-2 inline-block bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
                        <i class="fas fa-upload mr-2"></i>Upload Your First Document
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function confirmDelete(id) {
    if(confirm('Are you sure you want to delete this document? This action cannot be undone.')) {
        window.location.href = 'advocate-document-delete.php?id=' + id;
    }
}

function showFullText(text, title) {
    // Create modal backdrop
    const backdrop = document.createElement('div');
    backdrop.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center';
    
    // Create modal content
    const modal = document.createElement('div');
    modal.className = 'bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 overflow-hidden';
    
    // Create modal header
    const header = document.createElement('div');
    header.className = 'px-6 py-4 bg-gray-100 border-b flex justify-between items-center';
    header.innerHTML = `
        <h3 class="text-lg font-medium text-gray-900">${title}</h3>
        <button type="button" class="text-gray-400 hover:text-gray-500">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    // Create modal body
    const body = document.createElement('div');
    body.className = 'px-6 py-4 max-h-96 overflow-y-auto';
    body.innerText = text;
    
    // Assemble modal
    modal.appendChild(header);
    modal.appendChild(body);
    backdrop.appendChild(modal);
    
    // Add to document
    document.body.appendChild(backdrop);
    
    // Close modal when clicking close button or backdrop
    header.querySelector('button').addEventListener('click', () => {
        document.body.removeChild(backdrop);
    });
    backdrop.addEventListener('click', (e) => {
        if (e.target === backdrop) {
            document.body.removeChild(backdrop);
        }
    });
}

function confirmDelete(id) {
    if(confirm('Are you sure you want to delete this document? This action cannot be undone.')) {
        window.location.href = 'advocate-document-delete.php?id=' + id;
    }
}
</script>

<?php
// Include footer
include_once '../templates/footer.php';
?>