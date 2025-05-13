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

// Connect to database
$conn = getDBConnection();

// Initialize filters
$caseId = isset($_GET['case_id']) ? (int)$_GET['case_id'] : 0;
$documentType = isset($_GET['document_type']) ? $_GET['document_type'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query based on filters
$whereConditions = ["c.client_id = ?"];
$params = [$clientId];
$types = "i";

if ($caseId > 0) {
    $whereConditions[] = "d.case_id = ?";
    $params[] = $caseId;
    $types .= "i";
}

if (!empty($documentType)) {
    $whereConditions[] = "d.document_type = ?";
    $params[] = $documentType;
    $types .= "s";
}

if (!empty($search)) {
    $whereConditions[] = "(d.title LIKE ? OR d.description LIKE ? OR c.case_number LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "sss";
}

$whereClause = implode(" AND ", $whereConditions);

// Get documents
$query = "
    SELECT 
        d.*,
        c.case_number,
        c.title as case_title,
        u.full_name as uploaded_by_name,
        u.user_type as uploaded_by_type
    FROM documents d
    JOIN cases c ON d.case_id = c.case_id
    JOIN users u ON d.uploaded_by = u.user_id
    WHERE $whereClause
    ORDER BY d.upload_date DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Get cases for filter dropdown
$casesQuery = "SELECT case_id, case_number, title FROM cases WHERE client_id = ? ORDER BY case_number";
$casesStmt = $conn->prepare($casesQuery);
$casesStmt->bind_param("i", $clientId);
$casesStmt->execute();
$casesResult = $casesStmt->get_result();

$cases = [];
while ($case = $casesResult->fetch_assoc()) {
    $cases[] = $case;
}

// Get document types for filter dropdown
$typesQuery = "
    SELECT DISTINCT document_type 
    FROM documents d
    JOIN cases c ON d.case_id = c.case_id
    WHERE c.client_id = ? AND document_type IS NOT NULL AND document_type != ''
    ORDER BY document_type
";
$typesStmt = $conn->prepare($typesQuery);
$typesStmt->bind_param("i", $clientId);
$typesStmt->execute();
$typesResult = $typesStmt->get_result();

$documentTypes = [];
while ($type = $typesResult->fetch_assoc()) {
    $documentTypes[] = $type['document_type'];
}

// Get document statistics
$statsQuery = "
    SELECT 
        COUNT(*) as total_documents,
        SUM(CASE WHEN DATEDIFF(CURDATE(), d.upload_date) <= 30 THEN 1 ELSE 0 END) as recent_documents,
        COUNT(DISTINCT d.case_id) as cases_with_documents
    FROM documents d
    JOIN cases c ON d.case_id = c.case_id
    WHERE c.client_id = ?
";
$statsStmt = $conn->prepare($statsQuery);
$statsStmt->bind_param("i", $clientId);
$statsStmt->execute();
$documentStats = $statsStmt->get_result()->fetch_assoc();

// Close connection
$conn->close();

// Set page title
$pageTitle = "Case Documents";
include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Case Documents</h1>
            <p class="text-gray-600">Access, view, and manage documents related to your cases</p>
        </div>
        
        <div class="mt-4 md:mt-0">
            <a href="upload.php<?php echo $caseId ? '?case_id=' . $caseId : ''; ?>" class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-upload mr-2"></i> Upload Document
            </a>
        </div>
    </div>
    
    <!-- Document Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-500 mr-4">
                    <i class="fas fa-file-alt text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Total Documents</p>
                    <p class="text-2xl font-semibold"><?php echo $documentStats['total_documents']; ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-500 mr-4">
                    <i class="fas fa-clock text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Recent Documents (30 days)</p>
                    <p class="text-2xl font-semibold"><?php echo $documentStats['recent_documents']; ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-100 text-purple-500 mr-4">
                    <i class="fas fa-briefcase text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Cases with Documents</p>
                    <p class="text-2xl font-semibold"><?php echo $documentStats['cases_with_documents']; ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form action="" method="GET" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="case_id" class="block text-sm font-medium text-gray-700 mb-1">Case</label>
                    <select id="case_id" name="case_id" class="form-select w-full">
                        <option value="0">All Cases</option>
                        <?php foreach ($cases as $case): ?>
                            <option value="<?php echo $case['case_id']; ?>" <?php echo $caseId == $case['case_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($case['case_number'] . ' - ' . $case['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="document_type" class="block text-sm font-medium text-gray-700 mb-1">Document Type</label>
                    <select id="document_type" name="document_type" class="form-select w-full">
                        <option value="">All Types</option>
                        <?php foreach ($documentTypes as $type): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $documentType === $type ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input type="text" id="search" name="search" class="form-input w-full" placeholder="Search by title or description" value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            
            <div class="flex items-center">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg mr-2">
                    <i class="fas fa-search mr-2"></i> Filter
                </button>
                
                <a href="index.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg">
                    <i class="fas fa-times mr-2"></i> Reset
                </a>
            </div>
        </form>
    </div>
    
    <!-- Documents List -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <?php if ($result->num_rows === 0): ?>
            <div class="text-center py-8">
                <div class="text-gray-400 mb-2">
                    <i class="fas fa-file-alt text-5xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-1">No documents found</h3>
                <p class="text-gray-500">
                    <?php if (!empty($search) || !empty($documentType) || $caseId > 0): ?>
                        Try adjusting your search filters
                    <?php else: ?>
                        You don't have any documents yet
                    <?php endif; ?>
                </p>
                <div class="mt-4">
                    <a href="upload.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        <i class="fas fa-upload mr-2"></i> Upload Your First Document
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Document
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Case
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Type
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Uploaded By
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Date
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while ($document = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <?php
                                        // Determine icon based on file extension
                                        $fileExtension = pathinfo($document['file_path'], PATHINFO_EXTENSION);
                                        $iconClass = 'fas fa-file text-gray-400';
                                        
                                        switch (strtolower($fileExtension)) {
                                            case 'pdf':
                                                $iconClass = 'fas fa-file-pdf text-red-500';
                                                break;
                                            case 'doc':
                                            case 'docx':
                                                $iconClass = 'fas fa-file-word text-blue-500';
                                                break;
                                            case 'xls':
                                            case 'xlsx':
                                                $iconClass = 'fas fa-file-excel text-green-500';
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
                                                $iconClass = 'fas fa-file-archive text-yellow-500';
                                                break;
                                        }
                                        ?>
                                        <i class="<?php echo $iconClass; ?> text-xl mr-3"></i>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($document['title']); ?></div>
                                            <?php if (!empty($document['description'])): ?>
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($document['description']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900">
                                    <a href="../cases/view.php?id=<?php echo $document['case_id']; ?>" class="text-blue-600 hover:underline">
                                            <?php echo htmlspecialchars($document['case_number']); ?>
                                        </a>
                                    </div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($document['case_title']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php echo htmlspecialchars($document['document_type'] ?? 'General'); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php echo htmlspecialchars($document['uploaded_by_name']); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?php echo ucfirst($document['uploaded_by_type']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php echo date('M d, Y', strtotime($document['upload_date'])); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?php echo date('h:i A', strtotime($document['upload_date'])); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end space-x-3">
                                        <a href="view.php?id=<?php echo $document['document_id']; ?>" class="text-blue-600 hover:text-blue-900" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="download.php?id=<?php echo $document['document_id']; ?>" class="text-green-600 hover:text-green-900" title="Download">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <?php if (pathinfo($document['file_path'], PATHINFO_EXTENSION) === 'pdf'): ?>
                                            <a href="sign.php?id=<?php echo $document['document_id']; ?>" class="hidden text-purple-600 hover:text-purple-900" title="Sign Document">
                                                <i class="fas fa-signature"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Document Tips -->
    <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
        <h3 class="text-lg font-medium text-blue-800 mb-2">Document Tips</h3>
        <ul class="list-disc pl-5 text-blue-700 space-y-1">
            <li>Keep your documents organized by uploading them to the appropriate case</li>
            <li>You can electronically sign PDF documents using our e-signature tool</li>
            <li>All documents are securely stored and encrypted for your privacy</li>
            <li>Contact your advocate if you need assistance with any document</li>
        </ul>
    </div>
</div>

<?php
// Include footer
include '../includes/footer.php';
?>
