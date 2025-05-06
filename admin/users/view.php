<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user is logged in and is an admin
requireLogin();
requireUserType('admin');

// Check if user ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirectWithMessage('index.php', 'User ID is required', 'error');
    exit;
}

$userId = (int)$_GET['id'];

// Get database connection
$conn = getDBConnection();

// Get user details
$userQuery = "
    SELECT 
        u.*,
        CASE 
            WHEN u.user_type = 'advocate' THEN ap.license_number
            ELSE NULL
        END as license_number,
        CASE 
            WHEN u.user_type = 'advocate' THEN ap.specialization
            ELSE NULL
        END as specialization,
        CASE 
            WHEN u.user_type = 'advocate' THEN ap.experience_years
            ELSE NULL
        END as experience_years,
        CASE 
            WHEN u.user_type = 'advocate' THEN ap.education
            ELSE NULL
        END as education,
        CASE 
            WHEN u.user_type = 'advocate' THEN ap.bio
            ELSE NULL
        END as bio,
        CASE 
            WHEN u.user_type = 'advocate' THEN ap.hourly_rate
            ELSE NULL
        END as hourly_rate,
        CASE 
            WHEN u.user_type = 'client' THEN cp.occupation
            ELSE NULL
        END as occupation,
        CASE 
            WHEN u.user_type = 'client' THEN cp.date_of_birth
            ELSE NULL
        END as date_of_birth,
        CASE 
            WHEN u.user_type = 'client' THEN cp.reference_source
            ELSE NULL
        END as reference_source
    FROM users u
    LEFT JOIN advocate_profiles ap ON u.user_id = ap.user_id AND u.user_type = 'advocate'
    LEFT JOIN client_profiles cp ON u.user_id = cp.user_id AND u.user_type = 'client'
    WHERE u.user_id = ?
";

$stmt = $conn->prepare($userQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    redirectWithMessage('index.php', 'User not found', 'error');
    exit;
}

$user = $result->fetch_assoc();

// Set page title
$pageTitle = "View User: " . $user['full_name'];
include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">User Details</h1>
            <p class="text-gray-600">Viewing information for <?php echo htmlspecialchars($user['full_name']); ?></p>
        </div>
        
        <div class="mt-4 md:mt-0 flex space-x-2">
            <a href="index.php" class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back to Users
            </a>
            <a href="edit.php?id=<?php echo $user['user_id']; ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-edit mr-2"></i> Edit User
            </a>
            <?php if ($user['status'] === 'active'): ?>
                <a href="status.php?id=<?php echo $user['user_id']; ?>&action=suspend" class="bg-yellow-600 hover:bg-yellow-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center" onclick="return confirm('Are you sure you want to suspend this user?');">
                    <i class="fas fa-ban mr-2"></i> Suspend
                </a>
            <?php elseif ($user['status'] === 'suspended'): ?>
                <a href="status.php?id=<?php echo $user['user_id']; ?>&action=activate" class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center" onclick="return confirm('Are you sure you want to activate this user?');">
                    <i class="fas fa-check-circle mr-2"></i> Activate
                </a>
            <?php elseif ($user['status'] === 'pending'): ?>
                <a href="status.php?id=<?php echo $user['user_id']; ?>&action=approve" class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center" onclick="return confirm('Are you sure you want to approve this user?');">
                    <i class="fas fa-check-circle mr-2"></i> Approve
                </a>
            <?php endif; ?>
            <?php if ($user['user_id'] !== $_SESSION['user_id']): ?>
                <a href="delete.php?id=<?php echo $user['user_id']; ?>" class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-lg inline-flex items-center" onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                    <i class="fas fa-trash-alt mr-2"></i> Delete
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- User Basic Information -->
        <div class="md:col-span-1">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h2 class="text-lg font-semibold text-gray-800">User Information</h2>
                </div>
                
                <div class="p-6">
                    <div class="flex flex-col items-center mb-6">
                        <div class="w-32 h-32 rounded-full bg-gray-200 flex items-center justify-center text-gray-600 overflow-hidden mb-4">
                            <?php if (!empty($user['profile_image'])): ?>
                                <img src="<?php echo $path_url . $user['profile_image']; ?>" alt="Profile" class="h-full w-full object-cover">
                            <?php else: ?>
                                <i class="fas fa-user text-5xl"></i>
                            <?php endif; ?>
                        </div>
                        
                        <h3 class="text-xl font-semibold text-gray-800"><?php echo htmlspecialchars($user['full_name']); ?></h3>
                        <p class="text-gray-500">@<?php echo htmlspecialchars($user['username']); ?></p>
                        
                        <?php
                        $typeClass = 'bg-gray-100 text-gray-800';
                        switch ($user['user_type']) {
                            case 'advocate':
                                $typeClass = 'bg-blue-100 text-blue-800';
                                break;
                            case 'client':
                                $typeClass = 'bg-green-100 text-green-800';
                                break;
                            case 'admin':
                                $typeClass = 'bg-purple-100 text-purple-800';
                                break;
                        }
                        
                        $statusClass = 'bg-gray-100 text-gray-800';
                        switch ($user['status']) {
                            case 'active':
                                $statusClass = 'bg-green-100 text-green-800';
                                break;
                            case 'pending':
                                $statusClass = 'bg-yellow-100 text-yellow-800';
                                break;
                            case 'suspended':
                                $statusClass = 'bg-red-100 text-red-800';
                                break;
                            case 'inactive':
                                $statusClass = 'bg-gray-100 text-gray-800';
                                break;
                        }
                        ?>
                        
                        <div class="flex mt-2 space-x-2">
                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $typeClass; ?>">
                                <?php echo ucfirst($user['user_type']); ?>
                            </span>
                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                <?php echo ucfirst($user['status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="space-y-3">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Email</p>
                            <p class="text-sm text-gray-900">
                                <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>" class="text-blue-600 hover:underline">
                                    <?php echo htmlspecialchars($user['email']); ?>
                                </a>
                            </p>
                        </div>
                        
                        <?php if (!empty($user['phone'])): ?>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Phone</p>
                            <p class="text-sm text-gray-900">
                                <a href="tel:<?php echo htmlspecialchars($user['phone']); ?>" class="text-blue-600 hover:underline">
                                    <?php echo htmlspecialchars($user['phone']); ?>
                                </a>
                            </p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($user['address'])): ?>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Address</p>
                            <p class="text-sm text-gray-900"><?php echo nl2br(htmlspecialchars($user['address'])); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <div>
                            <p class="text-sm font-medium text-gray-500">Registered On</p>
                            <p class="text-sm text-gray-900"><?php echo formatDateTime($user['created_at']); ?></p>
                        </div>
                        
                        <div>
                            <p class="text-sm font-medium text-gray-500">Last Updated</p>
                            <p class="text-sm text-gray-900"><?php echo formatDateTime($user['updated_at']); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- User Type Specific Information -->
        <div class="md:col-span-2">
            <?php if ($user['user_type'] === 'advocate'): ?>
                <!-- Advocate Profile -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                        <h2 class="text-lg font-semibold text-gray-800">Advocate Profile</h2>
                    </div>
                    
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <p class="text-sm font-medium text-gray-500">License Number</p>
                                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($user['license_number'] ?? 'Not provided'); ?></p>
                            </div>
                            
                            <div>
                                <p class="text-sm font-medium text-gray-500">Specialization</p>
                                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($user['specialization'] ?? 'Not provided'); ?></p>
                            </div>
                            
                            <div>
                                <p class="text-sm font-medium text-gray-500">Experience</p>
                                <p class="text-sm text-gray-900">
                                    <?php echo !empty($user['experience_years']) ? htmlspecialchars($user['experience_years']) . ' years' : 'Not provided'; ?>
                                </p>
                            </div>
                            
                            <div>
                                <p class="text-sm font-medium text-gray-500">Hourly Rate</p>
                                <p class="text-sm text-gray-900">
                                    <?php echo !empty($user['hourly_rate']) ? formatCurrency($user['hourly_rate']) : 'Not provided'; ?>
                                </p>
                            </div>
                            
                            <?php if (!empty($user['education'])): ?>
                            <div class="md:col-span-2">
                                <p class="text-sm font-medium text-gray-500">Education</p>
                                <p class="text-sm text-gray-900"><?php echo nl2br(htmlspecialchars($user['education'])); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($user['bio'])): ?>
                            <div class="md:col-span-2">
                                <p class="text-sm font-medium text-gray-500">Biography</p>
                                <p class="text-sm text-gray-900"><?php echo nl2br(htmlspecialchars($user['bio'])); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
            <?php elseif ($user['user_type'] === 'client'): ?>
                <!-- Client Profile -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                        <h2 class="text-lg font-semibold text-gray-800">Client Profile</h2>
                    </div>
                    
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <?php if (!empty($user['occupation'])): ?>
                                <div>
                                <p class="text-sm font-medium text-gray-500">Occupation</p>
                                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($user['occupation']); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($user['date_of_birth'])): ?>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Date of Birth</p>
                                <p class="text-sm text-gray-900"><?php echo formatDate($user['date_of_birth']); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($user['reference_source'])): ?>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Reference Source</p>
                                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($user['reference_source']); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Client Cases -->
                <?php
                // Get client cases
                $clientCasesQuery = "
                    SELECT c.*, 
                           (SELECT COUNT(*) FROM case_activities WHERE case_id = c.case_id) as activity_count,
                           (SELECT COUNT(*) FROM documents WHERE case_id = c.case_id) as document_count
                    FROM cases c
                    JOIN client_profiles cp ON c.client_id = cp.client_id
                    WHERE cp.user_id = ?
                    ORDER BY c.created_at DESC
                ";
                $clientCasesStmt = $conn->prepare($clientCasesQuery);
                $clientCasesStmt->bind_param("i", $userId);
                $clientCasesStmt->execute();
                $clientCasesResult = $clientCasesStmt->get_result();
                ?>
                
                <?php if ($clientCasesResult->num_rows > 0): ?>
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                        <h2 class="text-lg font-semibold text-gray-800">Client Cases</h2>
                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                            <?php echo $clientCasesResult->num_rows; ?> Cases
                        </span>
                    </div>
                    
                    <div class="divide-y divide-gray-200">
                        <?php while ($case = $clientCasesResult->fetch_assoc()): ?>
                            <div class="p-6">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-900">
                                            <a href="../cases/view.php?id=<?php echo $case['case_id']; ?>" class="text-blue-600 hover:underline">
                                                <?php echo htmlspecialchars($case['case_number']); ?>: <?php echo htmlspecialchars($case['title']); ?>
                                            </a>
                                        </h3>
                                        <p class="text-sm text-gray-500">
                                            <?php echo htmlspecialchars($case['case_type']); ?> • 
                                            Filed on <?php echo formatDate($case['filing_date']); ?>
                                        </p>
                                    </div>
                                    <div>
                                        <?php
                                        $statusClass = 'bg-gray-100 text-gray-800';
                                        switch ($case['status']) {
                                            case 'pending':
                                                $statusClass = 'bg-yellow-100 text-yellow-800';
                                                break;
                                            case 'active':
                                                $statusClass = 'bg-blue-100 text-blue-800';
                                                break;
                                            case 'closed':
                                                $statusClass = 'bg-gray-100 text-gray-800';
                                                break;
                                            case 'won':
                                                $statusClass = 'bg-green-100 text-green-800';
                                                break;
                                            case 'lost':
                                                $statusClass = 'bg-red-100 text-red-800';
                                                break;
                                            case 'settled':
                                                $statusClass = 'bg-indigo-100 text-indigo-800';
                                                break;
                                        }
                                        ?>
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                            <?php echo ucfirst($case['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="mt-4 flex items-center text-sm text-gray-500">
                                    <div class="mr-6">
                                        <i class="fas fa-file-alt mr-1"></i> <?php echo $case['document_count']; ?> Documents
                                    </div>
                                    <div>
                                        <i class="fas fa-history mr-1"></i> <?php echo $case['activity_count']; ?> Activities
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                <?php endif; ?>
                
            <?php elseif ($user['user_type'] === 'admin'): ?>
                <!-- Admin Information -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                        <h2 class="text-lg font-semibold text-gray-800">Administrator Information</h2>
                    </div>
                    
                    <div class="p-6">
                        <p class="text-gray-700">
                            This user has administrator privileges and can manage all aspects of the system.
                        </p>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Recent Activity -->
            <?php
            // Get user activity
            $activityQuery = "
                SELECT 
                    ca.activity_id, 
                    ca.case_id, 
                    ca.activity_type, 
                    ca.description, 
                    ca.activity_date,
                    c.case_number,
                    c.title as case_title
                FROM case_activities ca
                JOIN cases c ON ca.case_id = c.case_id
                WHERE ca.user_id = ?
                ORDER BY ca.activity_date DESC
                LIMIT 10
            ";
            $activityStmt = $conn->prepare($activityQuery);
            $activityStmt->bind_param("i", $userId);
            $activityStmt->execute();
            $activityResult = $activityStmt->get_result();
            ?>
            
            <?php if ($activityResult->num_rows > 0): ?>
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h2 class="text-lg font-semibold text-gray-800">Recent Activity</h2>
                </div>
                
                <div class="divide-y divide-gray-200">
                    <?php while ($activity = $activityResult->fetch_assoc()): ?>
                        <div class="px-6 py-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <?php
                                    $activityIconClass = 'fas fa-info-circle text-blue-500';
                                    switch ($activity['activity_type']) {
                                        case 'update':
                                            $activityIconClass = 'fas fa-edit text-blue-500';
                                            break;
                                        case 'document':
                                            $activityIconClass = 'fas fa-file-alt text-yellow-500';
                                            break;
                                        case 'hearing':
                                            $activityIconClass = 'fas fa-gavel text-purple-500';
                                            break;
                                        case 'note':
                                            $activityIconClass = 'fas fa-sticky-note text-green-500';
                                            break;
                                        case 'status_change':
                                            $activityIconClass = 'fas fa-exchange-alt text-red-500';
                                            break;
                                    }
                                    ?>
                                    <div class="h-8 w-8 rounded-full bg-gray-100 flex items-center justify-center">
                                        <i class="<?php echo $activityIconClass; ?>"></i>
                                    </div>
                                </div>
                                <div class="ml-4 flex-1">
                                    <div class="text-sm text-gray-900">
                                        <span class="font-medium">
                                            <?php
                                            switch ($activity['activity_type']) {
                                                case 'update':
                                                    echo 'Updated';
                                                    break;
                                                case 'document':
                                                    echo 'Uploaded a document to';
                                                    break;
                                                case 'hearing':
                                                    echo 'Added a hearing to';
                                                    break;
                                                case 'note':
                                                    echo 'Added a note to';
                                                    break;
                                                case 'status_change':
                                                    echo 'Changed the status of';
                                                    break;
                                                default:
                                                    echo 'Modified';
                                            }
                                            ?>
                                        </span>
                                        <a href="#" class="font-medium text-blue-600 hover:underline">
                                            <?php echo htmlspecialchars($activity['case_number']); ?>
                                        </a>
                                    </div>
                                    <div class="mt-1 text-sm text-gray-600">
                                        <?php echo htmlspecialchars($activity['description']); ?>
                                    </div>
                                    <div class="mt-1 text-xs text-gray-500">
                                        <?php echo formatDateTimeRelative($activity['activity_date']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
include '../includes/footer.php';
?>
