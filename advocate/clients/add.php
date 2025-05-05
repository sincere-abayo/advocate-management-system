<?php
// Set page title
$pageTitle = "Add New Client";

// Include header
include_once '../includes/header.php';

// Get advocate ID
$advocateId = $advocateData['advocate_id'];

// Initialize form data
$formData = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
    'address' => '',
    'occupation' => '',
    'date_of_birth' => '',
    'reference_source' => '',
    'notes' => ''
];

$errors = [];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form data
    $formData['first_name'] = sanitizeInput($_POST['first_name'] ?? '');
    $formData['last_name'] = sanitizeInput($_POST['last_name'] ?? '');
    $formData['email'] = sanitizeInput($_POST['email'] ?? '');
    $formData['phone'] = sanitizeInput($_POST['phone'] ?? '');
    $formData['address'] = sanitizeInput($_POST['address'] ?? '');
    $formData['occupation'] = sanitizeInput($_POST['occupation'] ?? '');
    $formData['date_of_birth'] = sanitizeInput($_POST['date_of_birth'] ?? '');
    $formData['reference_source'] = sanitizeInput($_POST['reference_source'] ?? '');
    $formData['notes'] = sanitizeInput($_POST['notes'] ?? '');
    
    // Validate required fields
    if (empty($formData['first_name'])) {
        $errors['first_name'] = 'First name is required';
    }
    
    if (empty($formData['last_name'])) {
        $errors['last_name'] = 'Last name is required';
    }
    
    if (empty($formData['email'])) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address';
    }
    
    // Check if email already exists
    if (!empty($formData['email']) && filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $conn = getDBConnection();
        $checkStmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $checkStmt->bind_param("s", $formData['email']);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            $errors['email'] = 'This email address is already registered in the system';
        }
        
        $checkStmt->close();
    }
    
    // If no errors, create client
    if (empty($errors)) {
        $conn = getDBConnection();
        
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Generate a random password
            // $tempPassword = bin2hex(random_bytes(8));
            $tempPassword = 'password123';
            $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
            
            // Generate username from email
            $username = explode('@', $formData['email'])[0] . rand(100, 999);
            
            // Create user record
            $fullName = $formData['first_name'] . ' ' . $formData['last_name'];
            $userStmt = $conn->prepare("
                INSERT INTO users (username, password, email, full_name, phone, address, user_type, status)
                VALUES (?, ?, ?, ?, ?, ?, 'client', 'active')
            ");
            $userStmt->bind_param("ssssss", $username, $hashedPassword, $formData['email'], $fullName, $formData['phone'], $formData['address']);
            $userResult = $userStmt->execute();
            $userId = $conn->insert_id;
            $userStmt->close();
            
            if (!$userResult) {
                throw new Exception("Failed to create user account");
            }
            
            // Create client profile
            $clientStmt = $conn->prepare("
                INSERT INTO client_profiles (user_id, occupation, date_of_birth, reference_source)
                VALUES (?, ?, ?, ?)
            ");
            $clientStmt->bind_param("isss", $userId, $formData['occupation'], $formData['date_of_birth'], $formData['reference_source']);
            $clientResult = $clientStmt->execute();
            $clientId = $conn->insert_id;
            $clientStmt->close();
            
            if (!$clientResult) {
                throw new Exception("Failed to create client profile");
            }
            // After successfully creating the client profile and getting the client_id
// Create a placeholder case for the client
$caseNumber = 'INIT-' . date('Ymd') . '-' . $clientId;
$caseTitle = 'Initial Consultation - ' . $fullName;
$caseDescription = 'Placeholder case created for initial client registration.';
$caseType = 'Consultation';
$status = 'pending';
$priority = 'medium';
$currentDate = date('Y-m-d');

// Insert the case
$caseStmt = $conn->prepare("
    INSERT INTO cases (case_number, title, description, case_type, status, priority, client_id, filing_date)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");
$caseStmt->bind_param("ssssssss", $caseNumber, $caseTitle, $caseDescription, $caseType, $status, $priority, $clientId, $currentDate);
$caseResult = $caseStmt->execute();
$caseId = $conn->insert_id;
$caseStmt->close();

if (!$caseResult) {
    throw new Exception("Failed to create initial case for client");
}

// Assign the case to the advocate
$assignmentStmt = $conn->prepare("
    INSERT INTO case_assignments (case_id, advocate_id, role)
    VALUES (?, ?, 'primary')
");
$assignmentStmt->bind_param("ii", $caseId, $advocateId);
$assignmentResult = $assignmentStmt->execute();
$assignmentStmt->close();

if (!$assignmentResult) {
    throw new Exception("Failed to assign case to advocate");
}

// Add client notes as a case activity if provided
if (!empty($formData['notes'])) {
    $activityStmt = $conn->prepare("
        INSERT INTO case_activities (case_id, user_id, activity_type, description)
        VALUES (?, ?, 'note', ?)
    ");
    $activityStmt->bind_param("iis", $caseId, $_SESSION['user_id'], $formData['notes']);
    $activityResult = $activityStmt->execute();
    $activityStmt->close();
    
    if (!$activityResult) {
        throw new Exception("Failed to add client notes");
    }
}

// Add client notes as a notification if provided
if (!empty($formData['notes'])) {
    $notificationTitle = "Initial Client Notes";
    $notificationMessage = $formData['notes'];
    
    // Create notification for the advocate (to remember these notes)
    $advocateNotifyStmt = $conn->prepare("
        INSERT INTO notifications (user_id, title, message, related_to, related_id)
        VALUES (?, ?, ?, 'client', ?)
    ");
    $advocateNotifyStmt->bind_param("issi", $_SESSION['user_id'], $notificationTitle, $notificationMessage, $clientId);
    $advocateNotifyResult = $advocateNotifyStmt->execute();
    $advocateNotifyStmt->close();
    
    if (!$advocateNotifyResult) {
        throw new Exception("Failed to save client notes");
    }
}
   
            // Create notification for the new client
            $notificationTitle = "Welcome to Advocate Management System";
            $notificationMessage = "Your account has been created by " . $_SESSION['full_name'] . ". You can now log in using your email and the temporary password that has been sent to you.";
            
            $notifyStmt = $conn->prepare("
                INSERT INTO notifications (user_id, title, message)
                VALUES (?, ?, ?)
            ");
            $notifyStmt->bind_param("iss", $userId, $notificationTitle, $notificationMessage);
            $notifyResult = $notifyStmt->execute();
            $notifyStmt->close();
            
            if (!$notifyResult) {
                throw new Exception("Failed to create welcome notification");
            }
            
            // Commit transaction
            $conn->commit();
            
            // Send welcome email with login credentials
            $to = $formData['email'];
            $subject = "Welcome to Advocate Management System";
            $message = "Dear $fullName,\n\n";
            $message .= "Welcome to Advocate Management System. Your account has been created by " . $_SESSION['full_name'] . ".\n\n";
            $message .= "You can log in using the following credentials:\n";
            $message .= "Username: $username\n";
            $message .= "Password: $tempPassword\n\n";
            $message .= "Please change your password after your first login for security reasons.\n\n";
            $message .= "Best regards,\nAdvocate Management Team";
            $headers = "From: noreply@advocatemanagement.com";
            
            // Uncomment to enable email sending in production
            // mail($to, $subject, $message, $headers);
            
            // Store temporary password in session for display (only for development)
            $_SESSION['temp_client_credentials'] = [
                'username' => $username,
                'password' => $tempPassword,
                'email' => $formData['email'],
                'full_name' => $fullName,
                'client_id' => $clientId
            ];
            
            // Redirect with success message
            redirectWithMessage(
                'index.php?id=' . $clientId, 
                'Client added successfully', 
                'success'
            );
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            
            // Set error message
            $errors['general'] = "Failed to add client: " . $e->getMessage();
        }
        
        $conn->close();
    }
}
?>

<div class="mb-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Add New Client</h1>
            <p class="text-gray-600">Create a new client account in the system</p>
        </div>
        
        <div class="mt-4 md:mt-0">
            <a href="index.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg inline-flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back to Clients
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
            <form method="POST" action="" class="space-y-6">
            <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 mb-6 rounded-r-md shadow-sm" role="alert">
                <p class="font-medium flex items-center"><i class="fas fa-info-circle mr-2"></i> Important Information</p>
                <p class="mt-1">When you add a new client, an account will be created automatically with (<b>password123</b></b>).</p>
            </div>
            
            <div class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm">
                <h2 class="text-lg font-medium text-gray-900 border-b pb-2 flex items-center">
                    <i class="fas fa-user-circle text-blue-500 mr-2"></i> Personal Information
                </h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                    <div>
                        <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">First Name <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-user text-gray-400"></i>
                            </div>
                            <input type="text" id="first_name" name="first_name" 
                                class="form-input pl-10 w-full <?php echo isset($errors['first_name']) ? 'border-red-500 ring-red-500' : 'focus:border-blue-500 focus:ring-blue-500'; ?>" 
                                value="<?php echo htmlspecialchars($formData['first_name']); ?>" 
                                required
                                placeholder="Enter first name">
                        </div>
                        <?php if (isset($errors['first_name'])): ?>
                            <p class="text-red-500 text-sm mt-1 flex items-start">
                                <i class="fas fa-exclamation-circle mr-1 mt-0.5"></i> <?php echo $errors['first_name']; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">Last Name <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-user text-gray-400"></i>
                            </div>
                            <input type="text" id="last_name" name="last_name" 
                                class="form-input pl-10 w-full <?php echo isset($errors['last_name']) ? 'border-red-500 ring-red-500' : 'focus:border-blue-500 focus:ring-blue-500'; ?>" 
                                value="<?php echo htmlspecialchars($formData['last_name']); ?>" 
                                required
                                placeholder="Enter last name">
                        </div>
                        <?php if (isset($errors['last_name'])): ?>
                            <p class="text-red-500 text-sm mt-1 flex items-start">
                                <i class="fas fa-exclamation-circle mr-1 mt-0.5"></i> <?php echo $errors['last_name']; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-envelope text-gray-400"></i>
                            </div>
                            <input type="email" id="email" name="email" 
                                class="form-input pl-10 w-full <?php echo isset($errors['email']) ? 'border-red-500 ring-red-500' : 'focus:border-blue-500 focus:ring-blue-500'; ?>" 
                                value="<?php echo htmlspecialchars($formData['email']); ?>" 
                                required
                                placeholder="client@example.com">
                        </div>
                        <?php if (isset($errors['email'])): ?>
                            <p class="text-red-500 text-sm mt-1 flex items-start">
                                <i class="fas fa-exclamation-circle mr-1 mt-0.5"></i> <?php echo $errors['email']; ?>
                            </p>
                        <?php else: ?>
                            <p class="text-sm text-gray-500 mt-1 flex items-start">
                                <i class="fas fa-info-circle mr-1 mt-0.5 text-blue-500"></i> This will be used as the login email for the client
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-phone text-gray-400"></i>
                            </div>
                            <input type="tel" id="phone" name="phone" 
                                class="form-input pl-10 w-full" 
                                value="<?php echo htmlspecialchars($formData['phone']); ?>"
                                placeholder="(123) 456-7890">
                        </div>
                    </div>
                </div>
                
                <div class="mt-4">
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <div class="relative">
                        <div class="absolute top-3 left-3 flex items-start pointer-events-none">
                            <i class="fas fa-map-marker-alt text-gray-400"></i>
                        </div>
                        <textarea id="address" name="address" rows="3" 
                            class="form-textarea pl-10 w-full" 
                            placeholder="Enter client's address"><?php echo htmlspecialchars($formData['address']); ?></textarea>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm">
                <h2 class="text-lg font-medium text-gray-900 border-b pb-2 flex items-center">
                    <i class="fas fa-info-circle text-blue-500 mr-2"></i> Additional Information
                </h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                    <div>
                        <label for="occupation" class="block text-sm font-medium text-gray-700 mb-1">Occupation</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-briefcase text-gray-400"></i>
                            </div>
                            <input type="text" id="occupation" name="occupation" 
                                class="form-input pl-10 w-full" 
                                value="<?php echo htmlspecialchars($formData['occupation']); ?>"
                                placeholder="Enter client's occupation">
                        </div>
                    </div>
                    
                    <div>
                        <label for="date_of_birth" class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-calendar-alt text-gray-400"></i>
                            </div>
                            <input type="date" id="date_of_birth" name="date_of_birth" 
                                class="form-input pl-10 w-full" 
                                value="<?php echo htmlspecialchars($formData['date_of_birth']); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="mt-4">
                    <label for="reference_source" class="block text-sm font-medium text-gray-700 mb-1">Reference Source</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-user-friends text-gray-400"></i>
                        </div>
                        <select id="reference_source" name="reference_source" class="form-select pl-10 w-full">
                            <option value="">How did the client find you?</option>
                            <option value="Referral" <?php echo $formData['reference_source'] === 'Referral' ? 'selected' : ''; ?>>Referral from another client</option>
                            <option value="Website" <?php echo $formData['reference_source'] === 'Website' ? 'selected' : ''; ?>>Website</option>
                            <option value="Social Media" <?php echo $formData['reference_source'] === 'Social Media' ? 'selected' : ''; ?>>Social Media</option>
                            <option value="Search Engine" <?php echo $formData['reference_source'] === 'Search Engine' ? 'selected' : ''; ?>>Search Engine</option>
                            <option value="Advertisement" <?php echo $formData['reference_source'] === 'Advertisement' ? 'selected' : ''; ?>>Advertisement</option>
                            <option value="Bar Association" <?php echo $formData['reference_source'] === 'Bar Association' ? 'selected' : ''; ?>>Bar Association</option>
                            <option value="Court Appointment" <?php echo $formData['reference_source'] === 'Court Appointment' ? 'selected' : ''; ?>>Court Appointment</option>
                            <option value="Other" <?php echo $formData['reference_source'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>
                
                <div class="mt-4">
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Initial Notes</label>
                    <div class="relative">
                        <div class="absolute top-3 left-3 flex items-start pointer-events-none">
                            <i class="fas fa-sticky-note text-gray-400"></i>
                        </div>
                        <textarea id="notes" name="notes" rows="4" 
                            class="form-textarea pl-10 w-full" 
                            placeholder="Enter any initial notes about the client"><?php echo htmlspecialchars($formData['notes']); ?></textarea>
                    </div>
                    <p class="text-sm text-gray-500 mt-1 flex items-start">
                        <i class="fas fa-info-circle mr-1 mt-0.5 text-blue-500"></i> These notes will be saved to the client's record
                    </p>
                </div>
            </div>
            
            <div class="flex justify-end space-x-4">
                <a href="ndex.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg transition duration-150 ease-in-out flex items-center">
                    <i class="fas fa-times mr-2"></i> Cancel
                </a>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition duration-150 ease-in-out flex items-center shadow-sm">
                    <i class="fas fa-user-plus mr-2"></i> Add Client
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.form-input, .form-select, .form-textarea {
    @apply mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500;
}
</style>

<?php
// Include footer
include_once '../includes/footer.php';
?>