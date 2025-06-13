<?php
// Include necessary files
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check if user is logged in and has appropriate permissions
requireLogin();
requireUserType('advocate');

// Get user ID from session
$userId = $_SESSION['user_id'];
$advocateId = $_SESSION['advocate_id'];

// Initialize variables
$errors = [];
$success = false;
$userData = [];
$advocateData = [];

// Connect to database
$conn = getDBConnection();

// Get user data
$userStmt = $conn->prepare("
    SELECT u.*, ap.* 
    FROM users u
    JOIN advocate_profiles ap ON u.user_id = ap.user_id
    WHERE u.user_id = ?
");
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$result = $userStmt->get_result();

if ($result->num_rows > 0) {
    $userData = $result->fetch_assoc();
    $advocateData = [
        'advocate_id' => $userData['advocate_id'],
        'license_number' => $userData['license_number'],
        'specialization' => $userData['specialization'],
        'experience_years' => $userData['experience_years'],
        'education' => $userData['education'],
        'bio' => $userData['bio'],
        'hourly_rate' => $userData['hourly_rate']
    ];
} else {
    // Redirect if user not found
    redirectWithMessage('../auth/login.php', 'User not found', 'error');
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Determine which form was submitted
    if (isset($_POST['update_profile'])) {
        // Basic profile update
        $fullName = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        
        // Validate inputs
        if (empty($fullName)) {
            $errors['full_name'] = 'Full name is required';
        }
        
        if (empty($email)) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        } elseif ($email !== $userData['email']) {
            // Check if email already exists
            $checkEmailStmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $checkEmailStmt->bind_param("si", $email, $userId);
            $checkEmailStmt->execute();
            if ($checkEmailStmt->get_result()->num_rows > 0) {
                $errors['email'] = 'Email already in use';
            }
        }
        
        // Professional details
        $specialization = trim($_POST['specialization']);
        $experienceYears = (int)$_POST['experience_years'];
        $education = trim($_POST['education']);
        $bio = trim($_POST['bio']);
        $hourlyRate = (float)$_POST['hourly_rate'];
        
        // Handle profile image upload
        $profileImage = $userData['profile_image'];
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['size'] > 0) {
            $uploadDir = '../uploads/profiles/';
            $fileName = time() . '_' . basename($_FILES['profile_image']['name']);
            $targetFile = $uploadDir . $fileName;
            $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
            
            // Check if image file is a valid image
            $validExtensions = ['jpg', 'jpeg', 'png', 'gif'];
            if (!in_array($imageFileType, $validExtensions)) {
                $errors['profile_image'] = 'Only JPG, JPEG, PNG & GIF files are allowed';
            } elseif ($_FILES['profile_image']['size'] > 5000000) { // 5MB max
                $errors['profile_image'] = 'File is too large (max 5MB)';
            } else {
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetFile)) {
                    $profileImage = $fileName;
                    
                    // Delete old profile image if exists
                    if (!empty($userData['profile_image']) && file_exists($uploadDir . $userData['profile_image'])) {
                        unlink($uploadDir . $userData['profile_image']);
                    }
                } else {
                    $errors['profile_image'] = 'Failed to upload image';
                }
            }
        }
        
        // If no errors, update profile
        if (empty($errors)) {
            try {
                // Begin transaction
                $conn->begin_transaction();
                
                // Update users table
                $updateUserStmt = $conn->prepare("
                    UPDATE users 
                    SET full_name = ?, email = ?, phone = ?, address = ?, profile_image = ?
                    WHERE user_id = ?
                ");
                $updateUserStmt->bind_param("sssssi", $fullName, $email, $phone, $address, $profileImage, $userId);
                $updateUserStmt->execute();
                
                // Update advocate_profiles table
                $updateAdvocateStmt = $conn->prepare("
                    UPDATE advocate_profiles 
                    SET specialization = ?, experience_years = ?, education = ?, bio = ?, hourly_rate = ?
                    WHERE user_id = ?
                ");
                $updateAdvocateStmt->bind_param("sissdi", $specialization, $experienceYears, $education, $bio, $hourlyRate, $userId);
                $updateAdvocateStmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                // Update session data
                $_SESSION['full_name'] = $fullName;
                
                // Set success message
                $_SESSION['flash_message'] = 'Profile updated successfully';
                $_SESSION['flash_type'] = 'success';
                
                // Refresh page to show updated data
                header("Location: profile.php");
                exit;
                
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $errors['general'] = 'Error updating profile: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['change_password'])) {
        // Password change
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        // Validate inputs
        if (empty($currentPassword)) {
            $errors['current_password'] = 'Current password is required';
        }
        
        if (empty($newPassword)) {
            $errors['new_password'] = 'New password is required';
        } elseif (strlen($newPassword) < 8) {
            $errors['new_password'] = 'Password must be at least 8 characters';
        }
        
        if ($newPassword !== $confirmPassword) {
            $errors['confirm_password'] = 'Passwords do not match';
        }
        
        // If no errors, change password
        if (empty($errors)) {
            $result = changePassword($userId, $currentPassword, $newPassword);
            
            if ($result === true || (is_array($result) && $result['success'])) {
                // Set success message
                $_SESSION['flash_message'] = 'Password changed successfully';
                $_SESSION['flash_type'] = 'success';
                
                // Refresh page
                header("Location: profile.php");
                exit;
            } else {
                $errors['current_password'] = is_array($result) ? $result['message'] : 'Current password is incorrect';
            }
        }
    }
}

// Set page title
$pageTitle = "My Profile";
include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">My Profile</h1>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <?php if (isset($errors['general'])): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p><?php echo $errors['general']; ?></p>
        </div>
    <?php endif; ?>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Profile Summary Card -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="text-center">
                <div class="mb-4">
                    <?php if (!empty($userData['profile_image'])): ?>
                        <img src="../uploads/profiles/<?php echo $userData['profile_image']; ?>" alt="Profile Image" class="h-32 w-32 rounded-full mx-auto object-cover border-4 border-blue-100">
                    <?php else: ?>
                        <div class="h-32 w-32 rounded-full mx-auto bg-blue-100 flex items-center justify-center">
                            <i class="fas fa-user text-blue-500 text-4xl"></i>
                        </div>
                    <?php endif; ?>
                </div>
                
                <h2 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($userData['full_name']); ?></h2>
                <p class="text-gray-600"><?php echo htmlspecialchars($userData['email']); ?></p>
                
                <?php if (!empty($userData['phone'])): ?>
                    <p class="text-gray-600 mt-1">
                        <i class="fas fa-phone-alt mr-2 text-blue-500"></i><?php echo htmlspecialchars($userData['phone']); ?>
                    </p>
                <?php endif; ?>
                
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <p class="text-sm text-gray-500">License Number</p>
                    <p class="font-medium"><?php echo htmlspecialchars($advocateData['license_number'] ?? 'N/A'); ?></p>
                </div>
                
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <p class="text-sm text-gray-500">Specialization</p>
                    <p class="font-medium"><?php echo htmlspecialchars($advocateData['specialization'] ?? 'N/A'); ?></p>
                </div>
                
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <p class="text-sm text-gray-500">Experience</p>
                    <p class="font-medium"><?php echo $advocateData['experience_years'] ?? 'N/A' ?> years</p>
                </div>
                
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <p class="text-sm text-gray-500">Hourly Rate</p>
                    <p class="font-medium"><?php echo formatCurrency($advocateData['hourly_rate'] ?? '0'); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Profile Edit Form -->
        <div class="md:col-span-2">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-800">Edit Profile</h2>
                </div>
                
                <form method="POST" action="" enctype="multipart/form-data" class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label for="full_name" class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                            <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($userData['full_name']); ?>" class="form-input w-full <?php echo isset($errors['full_name']) ? 'border-red-500' : ''; ?>" required>
                            <?php if (isset($errors['full_name'])): ?>
                                <p class="text-red-500 text-sm mt-1"><?php echo $errors['full_name']; ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($userData['email']); ?>" class="form-input w-full <?php echo isset($errors['email']) ? 'border-red-500' : ''; ?>" required>
                            <?php if (isset($errors['email'])): ?>
                                <p class="text-red-500 text-sm mt-1"><?php echo $errors['email']; ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($userData['phone']); ?>" class="form-input w-full">
                        </div>
                        
                        <div>
                            <label for="profile_image" class="block text-sm font-medium text-gray-700 mb-1">Profile Image</label>
                            <input type="file" id="profile_image" name="profile_image" class="form-input w-full <?php echo isset($errors['profile_image']) ? 'border-red-500' : ''; ?>" accept="image/*">
                            <?php if (isset($errors['profile_image'])): ?>
                                <p class="text-red-500 text-sm mt-1"><?php echo $errors['profile_image']; ?></p>
                            <?php endif; ?>
                            <p class="text-sm text-gray-500 mt-1">Max file size: 5MB. Supported formats: JPG, PNG, GIF</p>
                        </div>
                        
                        <div class="md:col-span-2">
                            <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                            <textarea id="address" name="address" rows="3" class="form-textarea w-full"><?php echo htmlspecialchars($userData['address'] ?? 'adress'); ?></textarea>
                        </div>
                    </div>
                    
                    <h3 class="text-lg font-medium text-gray-800 mb-4 border-b pb-2">Professional Details</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label for="specialization" class="block text-sm font-medium text-gray-700 mb-1">Specialization</label>
                            <input type="text" id="specialization" name="specialization" value="<?php echo htmlspecialchars($advocateData['specialization'] ?? 'specialization'); ?>" class="form-input w-full">
                        </div>
                        
                        <div>
                            <label for="experience_years" class="block text-sm font-medium text-gray-700 mb-1">Years of Experience</label>
                            <input type="number" id="experience_years" name="experience_years" value="<?php echo $advocateData['experience_years'] ?? '0'; ?>" class="form-input w-full" min="0">
                        </div>
                        
                        <div>
                            <label for="hourly_rate" class="block text-sm font-medium text-gray-700 mb-1">Hourly Rate</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 sm:text-sm">$</span>
                                </div>
                                <input type="number" id="hourly_rate" name="hourly_rate" value="<?php echo $advocateData['hourly_rate'] ?? '0'; ?>" class="form-input pl-7 w-full" min="0" step="0.01">
                            </div>
                        </div>
                        
                        <div>
                            <label for="education" class="block text-sm font-medium text-gray-700 mb-1">Education</label>
                            <input type="text" id="education" name="education" value="<?php echo htmlspecialchars($advocateData['education'] ?? 'education'); ?>" class="form-input w-full">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label for="bio" class="block text-sm font-medium text-gray-700 mb-1">Professional Bio</label>
                            <textarea id="bio" name="bio" rows="4" class="form-textarea w-full"><?php echo htmlspecialchars($advocateData['bio'] ?? 'bio'); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="flex justify-end">
                        <button type="submit" name="update_profile" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg">
                            <i class="fas fa-save mr-2"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Change Password Form -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden mt-6">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-800">Change Password</h2>
                </div>
                
                <form method="POST" action="" class="p-6">
                    <div class="space-y-4">
                        <div>
                            <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">Current Password *</label>
                            <input type="password" id="current_password" name="current_password" class="form-input w-full <?php echo isset($errors['current_password']) ? 'border-red-500' : ''; ?>" required>
                            <?php if (isset($errors['current_password'])): ?>
                                <p class="text-red-500 text-sm mt-1"><?php echo $errors['current_password']; ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">New Password *</label>
                            <input type="password" id="new_password" name="new_password" class="form-input w-full <?php echo isset($errors['new_password']) ? 'border-red-500' : ''; ?>" required>
                            <?php if (isset($errors['new_password'])): ?>
                                <p class="text-red-500 text-sm mt-1"><?php echo $errors['new_password']; ?></p>
                            <?php endif; ?>
                            <p class="text-sm text-gray-500 mt-1">Password must be at least 8 characters long</p>
                        </div>
                        
                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password *</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-input w-full <?php echo isset($errors['confirm_password']) ? 'border-red-500' : ''; ?>" required>
                            <?php if (isset($errors['confirm_password'])): ?>
                                <p class="text-red-500 text-sm mt-1"><?php echo $errors['confirm_password']; ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="flex justify-end mt-6">
                        <button type="submit" name="change_password" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg">
                            <i class="fas fa-key mr-2"></i> Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php

// Close connection
$conn->close();

// Include footer
include 'includes/footer.php';
?>