<?php
// Set page title
$pageTitle = "Documents";

// Include header
include_once '../includes/header.php';

// Get advocate ID
$advocateId = $advocateData['advocate_id'];

// Get database connection
$conn = getDBConnection();

// Set default filter values
$caseId = isset($_GET['case_id']) ? (int)$_GET['case_id'] : null;
$documentType = isset($_GET['document_type']) ? sanitizeInput($_GET['document_type']) : '';
$searchTerm = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$sortBy = isset($_GET['sort']) ? sanitizeInput($_GET['sort']) : 'd.upload_date';
$sortOrder = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Build the query
$query = "
    SELECT d.*, c.case_number, c.title as case_title, u.full_name as uploaded_by_name
    FROM documents d
    JOIN cases c ON d.case_id = c.case_id
    JOIN case_assignments ca ON c.case_id = ca.case_id
    JOIN users u ON d.uploaded_by = u.user_id
    WHERE ca.advocate_id = ?
";

$countQuery = "
    SELECT COUNT(*) as total
    FROM documents d
    JOIN cases c ON d.case_id = c.case_id
    JOIN case_assignments ca ON c.case_id = ca.case_id
    WHERE ca.advocate_id = ?
";

$params = [$advocateId];
$types = "i";

// Add filters if provided
if ($caseId) {
    $query .= " AND d.case_id = ?";
    $countQuery .= " AND d.case_id = ?";
    $params[] = $caseId;
    $types .= "i";
}

if (!empty($documentType)) {
    $query .= " AND d.document_type = ?";
    $countQuery .= " AND d.document_type = ?";
    $params[] = $documentType;
    $types .= "s";
}

if (!empty($searchTerm)) {
    $query .= " AND (d.title LIKE ? OR d.description LIKE ? OR c.case_number LIKE ?)";
    $countQuery .= " AND (d.title LIKE ? OR d.description LIKE ? OR c.case_number LIKE ?)";
    $searchParam = "%$searchTerm%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "sss";
}

// Add sorting
$query .= " ORDER BY " . $sortBy . " " . $sortOrder;

// Add pagination
$query .= " LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$types .= "ii";

/// Get total count for pagination
$countStmt = $conn->prepare($countQuery);

// Create a copy of the params array for count query (without LIMIT and OFFSET)
$countParams = array_slice($params, 0, count($params) - 2);
// Create a type string for count query (without 'ii' for LIMIT and OFFSET)
$countTypes = substr($types, 0, strlen($types) - 2);

// Prepare bind parameters for count query
$bindParams = array($countTypes);
foreach ($countParams as $key => $value) {
    $bindParams[] = &$countParams[$key];
}

call_user_func_array([$countStmt, 'bind_param'], $bindParams);
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalCount = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalCount / $perPage);

// Get documents
$stmt = $conn->prepare($query);
$bindParams = array($types);
foreach ($params as $key => $value) {
    $bindParams[] = &$params[$key];
}

call_user_func_array([$stmt, 'bind_param'], $bindParams);
$stmt->execute();
$result = $stmt->get_result();

// Get cases for filter dropdown
$casesStmt = $conn->prepare("
    SELECT c.case_id, c.case_number, c.title
    FROM cases c
    JOIN case_assignments ca ON c.case_id = ca.case_id
    WHERE ca.advocate_id = ?
    ORDER BY c.created_at DESC
");
$casesStmt->bind_param("i", $advocateId);
$casesStmt->execute();
$casesResult = $casesStmt->get_result();

// Get document types for filter dropdown
$documentTypesStmt = $conn->prepare("
    SELECT DISTINCT document_type
    FROM documents d
    JOIN cases c ON d.case_id = c.case_id
    JOIN case_assignments ca ON c.case_id = ca.case_id
    WHERE ca.advocate_id = ?
    ORDER BY document_type
");
$documentTypesStmt->bind_param("i", $advocateId);
$documentTypesStmt->execute();
$documentTypesResult = $documentTypesStmt->get_result();

// Helper function to generate sort URL
function getSortUrl($column) {
    global $sortBy, $sortOrder;
    $newOrder = ($sortBy === $column && $sortOrder === 'DESC') ? 'asc' : 'desc';
    $params = $_GET;
    $params['sort'] = $column;
    $params['order'] = $newOrder;
    return '?' . http_build_query($params);
}

// Helper function to generate sort icon
function getSortIcon($column) {
    global $sortBy, $sortOrder;
    if ($sortBy !== $column) {
        return '<i class="fas fa-sort text-gray-400 ml-1"></i>';
    }
    return ($sortOrder === 'DESC') 
        ? '<i class="fas fa-sort-down text-blue-500 ml-1"></i>' 
        : '<i class="fas fa-sort-up text-blue-500 ml-1"></i>';
}

// Helper function to get file icon based on document type
function getFileIcon($documentType) {
    switch (strtolower($documentType)) {
        case 'pleading':
        case 'motion':
        case 'order':
        case 'judgment':
        case 'brief':
            return '<i class="fas fa-file-alt text-blue-500"></i>';
        case 'contract':
        case 'settlement agreement':
            return '<i class="fas fa-file-contract text-green-500"></i>';
        case 'correspondence':
            return '<i class="fas fa-envelope text-purple-500"></i>';
        case 'evidence':
        case 'exhibit':
            return '<i class="fas fa-file-image text-orange-500"></i>';
        case 'affidavit':
        case 'declaration':
            return '<i class="fas fa-file-signature text-red-500"></i>';
        case 'transcript':
            return '<i class="fas fa-file-audio text-yellow-500"></i>';
        case 'financial record':
            return '<i class="fas fa-file-invoice-dollar text-green-700"></i>';
        case 'medical record':
            return '<i class="fas fa-file-medical text-red-600"></i>';
        case 'police report':
            return '<i class="fas fa-file-shield text-blue-700"></i>';
        case 'expert report':
        case 'witness statement':
            return '<i class="fas fa-file-user text-indigo-500"></i>';
        case 'legal research':
            return '<i class="fas fa-file-search text-teal-500"></i>';
        default:
            return '<i class="fas fa-file text-gray-500"></i>';
    }
}

// Close database connections
$stmt->close();
$countStmt->close();
$casesStmt->close();
$documentTypesStmt->close();
$conn->close();
?>

<div class="mb-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Documents</h1>
            <p class="text-gray-600">Manage and access all case documents</p>
        </div>
        
        <div class="mt-4 md:mt-0">
            <a href="upload.php" class="btn-primary">
                <i class="fas fa-upload mr-2"></i> Upload New Document
            </a>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">Filter Documents</h2>
    
    <form method="GET" action="" class="space-y-4 md:space-y-0 md:flex md:flex-wrap md:items-end md:gap-4">
        <div class="w-full md:w-auto md:flex-1">
            <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="fas fa-search text-gray-400"></i>
                </div>
                <input type="text" id="search" name="search" class="form-input pl-10 w-full" placeholder="Search by title, description or case number" value="<?php echo htmlspecialchars($searchTerm); ?>">
            </div>
        </div>
        
        <div class="w-full md:w-auto md:flex-1">
            <label for="case_id" class="block text-sm font-medium text-gray-700 mb-1">Case</label>
            <select id="case_id" name="case_id" class="form-select w-full">
                <option value="">All Cases</option>
                <?php while ($case = $casesResult->fetch_assoc()): ?>
                    <option value="<?php echo $case['case_id']; ?>" <?php echo $caseId == $case['case_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($case['case_number'] . ' - ' . $case['title']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div class="w-full md:w-auto md:flex-1">
            <label for="document_type" class="block text-sm font-medium text-gray-700 mb-1">Document Type</label>
            <select id="document_type" name="document_type" class="form-select w-full">
                <option value="">All Types</option>
                <?php while ($type = $documentTypesResult->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($type['document_type']); ?>" <?php echo $documentType === $type['document_type'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($type['document_type']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div class="w-full md:w-auto">
            <button type="submit" class="btn-primary w-full md:w-auto">
                <i class="fas fa-filter mr-2"></i> Apply Filters
            </button>
        </div>
        
        <?php if (!empty($searchTerm) || $caseId || !empty($documentType)): ?>
            <div class="w-full md:w-auto">
                <a href="index.php" class="btn-secondary w-full md:w-auto inline-block text-center">
                    <i class="fas fa-times mr-2"></i> Clear Filters
                </a>
            </div>
        <?php endif; ?>
    </form>
</div>

<!-- Documents Table -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <?php if ($result->num_rows > 0): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('d.title'); ?>" class="flex items-center">
                                Document <?php echo getSortIcon('d.title'); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('c.case_number'); ?>" class="flex items-center">
                                Case <?php echo getSortIcon('c.case_number'); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('d.document_type'); ?>" class="flex items-center">
                                Type <?php echo getSortIcon('d.document_type'); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <a href="<?php echo getSortUrl('d.upload_date'); ?>" class="flex items-center">
                                Uploaded <?php echo getSortIcon('d.upload_date'); ?>
                            </a>
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Uploaded By
                        </th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php while ($document = $result->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center">
                                        <?php echo getFileIcon($document['document_type']); ?>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($document['title']); ?>
                                        </div>
                                        <?php if (!empty($document['description'])): ?>
                                                                                       <div class="text-sm text-gray-500 truncate max-w-xs">
                                                <?php echo htmlspecialchars($document['description']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <a href="../cases/view.php?id=<?php echo $document['case_id']; ?>" class="hover:text-blue-600 hover:underline">
                                        <?php echo htmlspecialchars($document['case_number']); ?>
                                    </a>
                                </div>
                                <div class="text-xs text-gray-500">
                                    <?php echo htmlspecialchars($document['case_title']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                    <?php echo htmlspecialchars($document['document_type']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo date('M d, Y', strtotime($document['upload_date'])); ?>
                                <div class="text-xs text-gray-400">
                                    <?php echo date('h:i A', strtotime($document['upload_date'])); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($document['uploaded_by_name']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="../../uploads/documents/<?php echo $document['file_path']; ?>" target="_blank" class="text-blue-600 hover:text-blue-900 mr-3" title="View Document">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="../../uploads/documents/<?php echo $document['file_path']; ?>" download class="text-green-600 hover:text-green-900 mr-3" title="Download Document">
                                    <i class="fas fa-download"></i>
                                </a>
                                <a href="edit.php?id=<?php echo $document['document_id']; ?>" class="text-indigo-600 hover:text-indigo-900 mr-3" title="Edit Document Details">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button onclick="confirmDelete(<?php echo $document['document_id']; ?>, '<?php echo addslashes($document['title']); ?>')" class="text-red-600 hover:text-red-900" title="Delete Document">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                <div class="flex justify-between items-center">
                    <div class="text-sm text-gray-500">
                        Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $perPage, $totalCount); ?> of <?php echo $totalCount; ?> documents
                    </div>
                    <div class="flex space-x-1">
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="px-3 py-1 rounded-md bg-white text-gray-600 border border-gray-300 hover:bg-gray-50">
                                Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="px-3 py-1 rounded-md <?php echo $i === $page ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 border border-gray-300 hover:bg-gray-50'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="px-3 py-1 rounded-md bg-white text-gray-600 border border-gray-300 hover:bg-gray-50">
                                Next
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        <div class="text-center py-8">
            <div class="text-gray-400 mb-2"><i class="fas fa-file-alt text-5xl"></i></div>
            <h3 class="text-lg font-medium text-gray-900">No documents found</h3>
            <p class="text-gray-500 mt-1">
                <?php if (!empty($searchTerm) || $caseId || !empty($documentType)): ?>
                    Try adjusting your filters or
                <?php endif; ?>
                upload a new document to get started.
            </p>
            <?php if (!empty($searchTerm) || $caseId || !empty($documentType)): ?>
                <div class="mt-4">
                    <a href="index.php" class="text-blue-600 hover:text-blue-800">
                        <i class="fas fa-times mr-1"></i> Clear all filters
                    </a>
                </div>
            <?php else: ?>
                <div class="mt-4">
                    <a href="/advocate/documents/upload.php" class="btn-primary">
                        <i class="fas fa-upload mr-2"></i> Upload New Document
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <div class="text-center">
            <div class="text-red-500 mb-4"><i class="fas fa-exclamation-triangle text-4xl"></i></div>
            <h3 class="text-xl font-bold text-gray-900 mb-2">Confirm Delete</h3>
            <p class="text-gray-600 mb-6">Are you sure you want to delete the document "<span id="documentTitle"></span>"? This action cannot be undone.</p>
        </div>
        <div class="flex justify-end space-x-4">
            <button onclick="closeDeleteModal()" class="btn-secondary">
                Cancel
            </button>
            <form id="deleteForm" method="POST" action="delete.php">
                <input type="hidden" id="documentId" name="document_id" value="">
                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-lg transition duration-150 inline-flex items-center">
                    <i class="fas fa-trash-alt mr-2"></i> Delete
                </button>
            </form>
        </div>
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
function confirmDelete(documentId, documentTitle) {
    document.getElementById('documentId').value = documentId;
    document.getElementById('documentTitle').textContent = documentTitle;
    document.getElementById('deleteModal').classList.remove('hidden');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
}

// Close modal when clicking outside
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeDeleteModal();
    }
});

// Prevent event propagation from modal content
document.querySelector('#deleteModal > div').addEventListener('click', function(e) {
    e.stopPropagation();
});

// Handle Escape key press
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && !document.getElementById('deleteModal').classList.contains('hidden')) {
        closeDeleteModal();
    }
});
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>