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

// Check if document ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['flash_message'] = "Invalid document ID";
    $_SESSION['flash_type'] = "error";
    header("Location: index.php");
    exit;
}

$documentId = (int)$_GET['id'];

// Connect to database
$conn = getDBConnection();

// Get document details
$query = "
    SELECT 
        d.*,
        c.case_number,
        c.title as case_title,
        c.client_id,
        u.full_name as uploaded_by_name,
        u.user_type as uploaded_by_type
    FROM documents d
    JOIN cases c ON d.case_id = c.case_id
    JOIN users u ON d.uploaded_by = u.user_id
    WHERE d.document_id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $documentId);
$stmt->execute();
$result = $stmt->get_result();

// Check if document exists and belongs to the client
if ($result->num_rows === 0) {
    $_SESSION['flash_message'] = "Document not found";
    $_SESSION['flash_type'] = "error";
    header("Location: index.php");
    exit;
}

$document = $result->fetch_assoc();

// Verify the document belongs to the client
if ($document['client_id'] !== $clientId) {
    $_SESSION['flash_message'] = "You don't have permission to view this document";
    $_SESSION['flash_type'] = "error";
    header("Location: index.php");
    exit;
}

// Get file extension
$fileExtension = strtolower(pathinfo($document['file_path'], PATHINFO_EXTENSION));

// Determine if the file can be previewed in browser
$canPreview = in_array($fileExtension, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'txt']);

// We'll skip the signature status check since the table doesn't exist
$signatureStatus = null;

// Close connection
$conn->close();

// Set page title
$pageTitle = "View Document";
include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($document['title']); ?></h1>
            <p class="text-gray-600">
                <a href="../cases/view.php?id=<?php echo $document['case_id']; ?>" class="text-blue-600 hover:underline">
                    <?php echo htmlspecialchars($document['case_number'] . ' - ' . $document['case_title']); ?>
                </a>
            </p>
        </div>
        
        <div class="mt-4 md:mt-0 flex space-x-2">
            <a href="index.php?case_id=<?php echo $document['case_id']; ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back to Documents
            </a>
            
            <a href="download.php?id=<?php echo $documentId; ?>" class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-download mr-2"></i> Download
            </a>
            
            <?php if ($fileExtension === 'pdf'): ?>
                <a href="sign.php?id=<?php echo $documentId; ?>" class="hidden bg-purple-600 hover:bg-purple-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                    <i class="fas fa-signature mr-2"></i> Sign Document
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Document Information -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Document Details</h2>
            <div class="space-y-3">
                <div>
                    <p class="text-sm text-gray-500">Document Type</p>
                    <p class="font-medium"><?php echo htmlspecialchars($document['document_type'] ?? 'General'); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Uploaded By</p>
                    <p class="font-medium"><?php echo htmlspecialchars($document['uploaded_by_name']); ?> (<?php echo ucfirst($document['uploaded_by_type']); ?>)</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Upload Date</p>
                    <p class="font-medium"><?php echo date('F d, Y', strtotime($document['upload_date'])); ?> at <?php echo date('h:i A', strtotime($document['upload_date'])); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">File Type</p>
                    <p class="font-medium"><?php echo strtoupper($fileExtension); ?></p>
                </div>
            </div>
            
            <?php if (!empty($document['description'])): ?>
                <div class="mt-6">
                    <h3 class="text-md font-semibold text-gray-800 mb-2">Description</h3>
                    <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($document['description'])); ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="md:col-span-2 bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Document Preview</h2>
            
            <?php if ($canPreview): ?>
                <?php if (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                    <div class="flex justify-center">
                        <img src="../../<?php echo htmlspecialchars($document['file_path']); ?>" alt="<?php echo htmlspecialchars($document['title']); ?>" class="max-w-full max-h-[600px] object-contain">
                    </div>
                <?php elseif ($fileExtension === 'pdf'): ?>
                    <div class="w-full h-full">
                        <object data="../../<?php echo htmlspecialchars($document['file_path']); ?>" type="application/pdf" width="100%" height="100%">
                            <p>It appears your browser doesn't support embedded PDFs. You can <a href="download.php?id=<?php echo $documentId; ?>" class="text-blue-600 hover:underline">download the PDF</a> to view it.</p>
                        </object>
                    </div>
                <?php elseif ($fileExtension === 'txt'): ?>
                    <div class="bg-gray-100 p-4 rounded-lg overflow-auto max-h-[600px]">
                        <pre class="text-sm text-gray-800"><?php echo htmlspecialchars(file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/' . $document['file_path'])); ?></pre>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-center py-8 bg-gray-100 rounded-lg">
                    <div class="text-gray-400 mb-2">
                        <?php
                        $iconClass = 'fas fa-file text-5xl';
                        switch ($fileExtension) {
                            case 'doc':
                            case 'docx':
                                $iconClass = 'fas fa-file-word text-blue-500 text-5xl';
                                break;
                            case 'xls':
                            case 'xlsx':
                                $iconClass = 'fas fa-file-excel text-green-500 text-5xl';
                                break;
                            case 'ppt':
                            case 'pptx':
                                $iconClass = 'fas fa-file-powerpoint text-orange-500 text-5xl';
                                break;
                            case 'zip':
                            case 'rar':
                                $iconClass = 'fas fa-file-archive text-yellow-500 text-5xl';
                                break;
                        }
                        ?>
                        <i class="<?php echo $iconClass; ?>"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-1">Preview not available</h3>
                    <p class="text-gray-500 mb-4">This file type cannot be previewed in the browser</p>
                    <a href="download.php?id=<?php echo $documentId; ?>" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-download mr-2"></i> Download to View
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Related Documents -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Related Documents</h2>
        
        <div id="related-documents-loading" class="text-center py-4">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900"></div>
            <p class="mt-2 text-gray-600">Loading related documents...</p>
        </div>
        
        <div id="related-documents" class="hidden">
            <!-- Will be populated via AJAX -->
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Load related documents
    fetch('get-related-documents.php?case_id=<?php echo $document['case_id']; ?>&current_id=<?php echo $documentId; ?>')
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('related-documents');
            const loadingIndicator = document.getElementById('related-documents-loading');
            
            loadingIndicator.classList.add('hidden');
            container.classList.remove('hidden');
            
            if (data.length === 0) {
                container.innerHTML = '<p class="text-gray-500 text-center py-4">No other documents found for this case</p>';
                return;
            }
            
            let html = '<div class="grid grid-cols-1 md:grid-cols-3 gap-4">';
            
            data.forEach(doc => {
                // Determine icon based on file extension
                const fileExtension = doc.file_path.split('.').pop().toLowerCase();
                let iconClass = 'fas fa-file text-gray-400';
                
                switch (fileExtension) {
                    case 'pdf':
                        iconClass = 'fas fa-file-pdf text-red-500';
                        break;
                    case 'doc':
                    case 'docx':
                        iconClass = 'fas fa-file-word text-blue-500';
                        break;
                    case 'xls':
                    case 'xlsx':
                        iconClass = 'fas fa-file-excel text-green-500';
                        break;
                    case 'ppt':
                    case 'pptx':
                        iconClass = 'fas fa-file-powerpoint text-orange-500';
                        break;
                    case 'jpg':
                    case 'jpeg':
                    case 'png':
                    case 'gif':
                        iconClass = 'fas fa-file-image text-purple-500';
                        break;
                    case 'zip':
                    case 'rar':
                        iconClass = 'fas fa-file-archive text-yellow-500';
                        break;
                }
                
                html += `
                    <a href="view.php?id=${doc.document_id}" class="flex items-center p-4 border rounded-lg hover:bg-gray-50">
                        <i class="${iconClass} text-2xl mr-3"></i>
                        <div>
                            <div class="font-medium text-gray-900">${doc.title}</div>
                            <div class="text-sm text-gray-500">${doc.document_type || 'General'}</div>
                            <div class="text-xs text-gray-500">Uploaded ${new Date(doc.upload_date).toLocaleDateString()}</div>
                        </div>
                    </a>
                `;
            });
            
            html += '</div>';
            container.innerHTML = html;
        })
        .catch(error => {
            console.error('Error loading related documents:', error);
            const container = document.getElementById('related-documents');
            const loadingIndicator = document.getElementById('related-documents-loading');
            
            loadingIndicator.classList.add('hidden');
            container.classList.remove('hidden');
            container.innerHTML = '<p class="text-red-500 text-center py-4">Error loading related documents</p>';
        });
});
</script>

<?php
// Include footer
include '../includes/footer.php';
?>
