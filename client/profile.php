<?php
// Include necessary files
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check if user is logged in and is a client
requireLogin();
requireUserType('client');

// Get user ID from session
$userId = $_SESSION['user_id'];
$clientId = $_SESSION['client_id'];

// Initialize variables
$errors = [];
$success = false;

// Get client data
$conn = getDBConnection();
$query = "
    SELECT 
        u.*, 
        cp.occupation, 
        cp.date_of_birth, 
        cp.reference_source
    FROM users u
    JOIN client_profiles cp ON u.user_id = cp.user_id
    WHERE u.user_id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['flash_message'] = "Client profile not found";
    $_SESSION['flash_type'] = "error";
    header("Location: index.php");
    exit;
}

$clientData = $result->fetch_assoc();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic validation
    $fullName = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $occupation = trim($_POST['occupation']);
    $dateOfBirth = trim($_POST['date_of_birth']);
    $referenceSource = trim($_POST['reference_source']);
    
    // Validate full name
    if (empty($fullName)) {
        $errors['full_name'] = "Full name is required";
    }
    
    // Validate email
    if (empty($email)) {
        $errors['email'] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format";
    } else {
        // Check if email is already in use by another user
        $emailCheckQuery = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
        $emailCheckStmt = $conn->prepare($emailCheckQuery);
        $emailCheckStmt->bind_param("si", $email, $userId);
        $emailCheckStmt->execute();
        $emailCheckResult = $emailCheckStmt->get_result();
        
        if ($emailCheckResult->num_rows > 0) {
            $errors['email'] = "Email is already in use by another user";
        }
    }
    
    // Validate date of birth (if provided)
    if (!empty($dateOfBirth)) {
        $dobTimestamp = strtotime($dateOfBirth);
        $currentTimestamp = time();
        
        if ($dobTimestamp === false) {
            $errors['date_of_birth'] = "Invalid date format";
        } elseif ($dobTimestamp > $currentTimestamp) {
            $errors['date_of_birth'] = "Date of birth cannot be in the future";
        }
    }
    
    // Handle profile image upload
    $profileImage = $clientData['profile_image']; // Default to current image
    
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        $fileType = $_FILES['profile_image']['type'];
        $fileSize = $_FILES['profile_image']['size'];
        $fileTmpName = $_FILES['profile_image']['tmp_name'];
        
        if (!in_array($fileType, $allowedTypes)) {
            $errors['profile_image'] = "Only JPG, PNG, and GIF images are allowed";
        } elseif ($fileSize > $maxSize) {
            $errors['profile_image'] = "Image size should not exceed 5MB";
        } else {
            // Generate unique filename
            $extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
            $newFilename = 'client_' . $userId . '_' . time() . '.' . $extension;
            $uploadDir = '../uploads/profiles/';
            
            // Create directory if it doesn't exist
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $uploadPath = $uploadDir . $newFilename;
            
            if (move_uploaded_file($fileTmpName, $uploadPath)) {
                // Delete old profile image if it exists
                if (!empty($clientData['profile_image']) && file_exists('../' . $clientData['profile_image'])) {
                    unlink('../' . $clientData['profile_image']);
                }
                
                $profileImage = 'uploads/profiles/' . $newFilename;
            } else {
                $errors['profile_image'] = "Failed to upload image";
            }
        }
    }
    
    // If no errors, update profile
    if (empty($errors)) {
        try {
            // Begin transaction
            $conn->begin_transaction();
            
            // Update users table
            $updateUserQuery = "
                UPDATE users 
                SET full_name = ?, email = ?, phone = ?, address = ?, profile_image = ?
                WHERE user_id = ?
            ";
            $updateUserStmt = $conn->prepare($updateUserQuery);
            $updateUserStmt->bind_param("sssssi", $fullName, $email, $phone, $address, $profileImage, $userId);
            $updateUserStmt->execute();
            
            // Update client_profiles table
            $updateClientQuery = "
                UPDATE client_profiles 
                SET occupation = ?, date_of_birth = ?, reference_source = ?
                WHERE user_id = ?
            ";
            $updateClientStmt = $conn->prepare($updateClientQuery);
            $updateClientStmt->bind_param("sssi", $occupation, $dateOfBirth, $referenceSource, $userId);
            $updateClientStmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            // Update session data
            $_SESSION['full_name'] = $fullName;
            
            // Set success message
            $_SESSION['flash_message'] = "Profile updated successfully";
            $_SESSION['flash_type'] = "success";
            
            // Refresh client data
            $stmt->execute();
            $clientData = $stmt->get_result()->fetch_assoc();
            
            $success = true;
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            
            $errors['general'] = "Error updating profile: " . $e->getMessage();
        }
    }
}

// Close connection
$conn->close();

// Set page title
$pageTitle = "My Profile";
include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">My Profile</h1>
            <p class="text-gray-600">View and update your personal information</p>
        </div>
    </div>
    
    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="mb-6 p-4 rounded-lg <?php echo $_SESSION['flash_type'] === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
            <?php echo $_SESSION['flash_message']; ?>
        </div>
        <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
    <?php endif; ?>
    
    <?php if (isset($errors['general'])): ?>
        <div class="mb-6 p-4 rounded-lg bg-red-100 text-red-700">
            <?php echo $errors['general']; ?>
        </div>
    <?php endif; ?>
    
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <form method="POST" action="" enctype="multipart/form-data" class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Profile Image Section -->
                <div class="md:col-span-1">
                    <div class="flex flex-col items-center">
                        <div class="w-40 h-40 rounded-full overflow-hidden bg-gray-200 mb-4">
                            <?php if (!empty($clientData['profile_image'])): ?>
                                <img src="<?php echo $path_url . htmlspecialchars($clientData['profile_image']); ?>" alt="Profile Image" class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center bg-gray-300 text-gray-600">
                                    <i class="fas fa-user text-5xl"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <label for="profile_image" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg cursor-pointer inline-flex items-center">
                            <i class="fas fa-upload mr-2"></i> Upload Photo
                            <input type="file" id="profile_image" name="profile_image" class="hidden" accept="image/jpeg, image/png, image/gif">
                        </label>
                        
                        <p class="text-sm text-gray-500 mt-2">Max file size: 5MB</p>
                        
                        <?php if (isset($errors['profile_image'])): ?>
                            <p class="text-red-500 text-sm mt-1"><?php echo $errors['profile_image']; ?></p>
                        <?php endif; ?>
                        
                        <div id="image-preview-container" class="mt-4 hidden">
                            <p class="text-sm font-medium text-gray-700 mb-1">Preview:</p>
                            <div class="w-40 h-40 rounded-full overflow-hidden bg-gray-200">
                                <img id="image-preview" src="#" alt="Image Preview" class="w-full h-full object-cover">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Profile Details Section -->
                <div class="md:col-span-2">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="full_name" class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                            <input type="text" id="full_name" name="full_name" class="form-input w-full <?php echo isset($errors['full_name']) ? 'border-red-500' : ''; ?>" value="<?php echo htmlspecialchars($clientData['full_name']); ?>" required>
                            <?php if (isset($errors['full_name'])): ?>
                                <p class="text-red-500 text-sm mt-1"><?php echo $errors['full_name']; ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address *</label>
                            <input type="email" id="email" name="email" class="form-input w-full <?php echo isset($errors['email']) ? 'border-red-500' : ''; ?>" value="<?php echo htmlspecialchars($clientData['email']); ?>" required>
                            <?php if (isset($errors['email'])): ?>
                                <p class="text-red-500 text-sm mt-1"><?php echo $errors['email']; ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                            <input type="tel" id="phone" name="phone" class="form-input w-full" value="<?php echo htmlspecialchars($clientData['phone'] ?? ''); ?>">
                        </div>
                        
                        <div>
                            <label for="date_of_birth" class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                            <input type="date" id="date_of_birth" name="date_of_birth" class="form-input w-full <?php echo isset($errors['date_of_birth']) ? 'border-red-500' : ''; ?>" value="<?php echo htmlspecialchars($clientData['date_of_birth'] ?? ''); ?>">
                            <?php if (isset($errors['date_of_birth'])): ?>
                                <p class="text-red-500 text-sm mt-1"><?php echo $errors['date_of_birth']; ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <label for="occupation" class="block text-sm font-medium text-gray-700 mb-1">Occupation</label>
                            <input type="text" id="occupation" name="occupation" class="form-input w-full" value="<?php echo htmlspecialchars($clientData['occupation'] ?? ''); ?>">
                        </div>
                        
                        <div>
                            <label for="reference_source" class="block text-sm font-medium text-gray-700 mb-1">How did you hear about us?</label>
                            <select id="reference_source" name="reference_source" class="form-select w-full">
                                <option value="">Select an option</option>
                                <option value="Search Engine" <?php echo ($clientData['reference_source'] ?? '') === 'Search Engine' ? 'selected' : ''; ?>>Search Engine</option>
                                <option value="Social Media" <?php echo ($clientData['reference_source'] ?? '') === 'Social Media' ? 'selected' : ''; ?>>Social Media</option>
                                <option value="Friend/Family" <?php echo ($clientData['reference_source'] ?? '') === 'Friend/Family' ? 'selected' : ''; ?>>Friend/Family</option>
                                <option value="Advertisement" <?php echo ($clientData['reference_source'] ?? '') === 'Advertisement' ? 'selected' : ''; ?>>Advertisement</option>
                                <option value="Other" <?php echo ($clientData['reference_source'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="md:col-span-2">
                            <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                            <textarea id="address" name="address" rows="3" class="form-textarea w-full"><?php echo htmlspecialchars($clientData['address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end space-x-4">
                        <a href="index.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg">
                            Cancel
                        </a>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg">
                            Save Changes
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Change Password Section -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mt-8">
        <div class="p-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Change Password</h2>
            
            <form action="change-password.php" method="POST" id="password-form" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">Current Password *</label>
                        <div class="relative">
                            <input type="password" id="current_password" name="current_password" class="form-input w-full pr-10" required>
                            <button type="button" class="toggle-password absolute inset-y-0 right-0 px-3 flex items-center text-gray-500 hover:text-gray-700" data-target="#current_password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div>
                        <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">New Password *</label>
                        <div class="relative">
                            <input type="password" id="new_password" name="new_password" class="form-input w-full pr-10" required minlength="8">
                            <button type="button" class="toggle-password absolute inset-y-0 right-0 px-3 flex items-center text-gray-500 hover:text-gray-700" data-target="#new_password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <p class="text-gray-500 text-xs mt-1">Minimum 8 characters</p>
                    </div>
                    
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password *</label>
                        <div class="relative">
                            <input type="password" id="confirm_password" name="confirm_password" class="form-input w-full pr-10" required minlength="8">
                            <button type="button" class="toggle-password absolute inset-y-0 right-0 px-3 flex items-center text-gray-500 hover:text-gray-700" data-target="#confirm_password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg">
                        Update Password
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Account Deactivation Section -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mt-8">
        <div class="p-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Account Settings</h2>
            
            <div class="border-t border-gray-200 pt-4">
                <div class="flex justify-between items-center">
                    <div>
                        <h3 class="text-lg font-medium text-red-600">Deactivate Account</h3>
                        <p class="text-gray-500 text-sm">Once you deactivate your account, you will not be able to access it again.</p>
                    </div>
                    
                    <button type="button" id="deactivate-account-btn" class="bg-red-100 hover:bg-red-200 text-red-700 font-medium py-2 px-4 rounded-lg">
                        Deactivate Account
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Deactivation Confirmation Modal -->
<div id="deactivation-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
        <div class="p-6">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">Confirm Account Deactivation</h3>
            
            <p class="text-gray-600 mb-6">Are you sure you want to deactivate your account? This action cannot be undone.</p>
            
            <form action="deactivate-account.php" method="POST">
                <div class="mb-4">
                    <label for="deactivation_password" class="block text-sm font-medium text-gray-700 mb-1">Enter your password to confirm *</label>
                    <input type="password" id="deactivation_password" name="password" class="form-input w-full" required>
                </div>
                
                <div class="flex justify-end space-x-4">
                    <button type="button" id="cancel-deactivation" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg">
                        Cancel
                    </button>
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-lg">
                        Deactivate Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Profile image preview
    const profileImageInput = document.getElementById('profile_image');
    const imagePreview = document.getElementById('image-preview');
    const imagePreviewContainer = document.getElementById('image-preview-container');
    
    profileImageInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                imagePreview.src = e.target.result;
                imagePreviewContainer.classList.remove('hidden');
            }
            
            reader.readAsDataURL(this.files[0]);
        }
    });
    
    // Password toggle visibility
    const togglePasswordButtons = document.querySelectorAll('.toggle-password');
    
    togglePasswordButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const passwordInput = document.querySelector(targetId);
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                this.innerHTML = '<i class="fas fa-eye-slash"></i>';
            } else {
                passwordInput.type = 'password';
                this.innerHTML = '<i class="fas fa-eye"></i>';
            }
        });
    });
    
    // Password form validation
    const passwordForm = document.getElementById('password-form');
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    
    passwordForm.addEventListener('submit', function(e) {
        if (newPasswordInput.value !== confirmPasswordInput.value) {
            e.preventDefault();
            alert('New password and confirmation do not match');
        }
    });
    
    // Account deactivation modal
    const deactivateBtn = document.getElementById('deactivate-account-btn');
    const deactivationModal = document.getElementById('deactivation-modal');
    const cancelDeactivationBtn = document.getElementById('cancel-deactivation');
    
    deactivateBtn.addEventListener('click', function() {
        deactivationModal.classList.remove('hidden');
    });
    
    cancelDeactivationBtn.addEventListener('click', function() {
        deactivationModal.classList.add('hidden');
    });
    
    // Close modal when clicking outside
    deactivationModal.addEventListener('click', function(e) {
        if (e.target === deactivationModal) {
            deactivationModal.classList.add('hidden');
        }
    });
});
</script>

<?php
// Include footer
include 'includes/footer.php';
?>
