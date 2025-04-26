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
include_once '../classes/User.php';
include_once '../classes/Advocate.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

// Initialize objects
$user_obj = new User($db);
$advocate_obj = new Advocate($db);

// Get user and advocate details
$user_obj->id = $_SESSION['user_id'];
$user_obj->readOne();

$advocate_obj->user_id = $_SESSION['user_id'];
$advocate_obj->readByUserId();

// Process form submission
$update_success = $update_error = '';
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Handle profile image upload
    $profile_image = $user_obj->profile_image; // Keep existing image by default
    
    if(isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $allowed = array('jpg', 'jpeg', 'png');
        $filename = $_FILES['profile_image']['name'];
        $file_ext = pathinfo($filename, PATHINFO_EXTENSION);
        
        // Validate file extension
        if(in_array(strtolower($file_ext), $allowed)) {
            // Create upload directory if it doesn't exist
            $upload_dir = '../uploads/profile/';
            if(!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $new_filename = uniqid() . '_' . $filename;
            $upload_path = $upload_dir . $new_filename;
            
            // Move uploaded file
            if(move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                // Delete old profile image if exists
                if(!empty($user_obj->profile_image) && file_exists('../uploads/profile/' . $user_obj->profile_image)) {
                    unlink('../uploads/profile/' . $user_obj->profile_image);
                }
                
                $profile_image = $new_filename;
            } else {
                $update_error = "Failed to upload profile image.";
            }
        } else {
            $update_error = "Invalid file type. Allowed types: jpg, jpeg, png";
        }
    }
    
    if(empty($update_error)) {
        // Update user information
        $user_obj->first_name = $_POST['first_name'];
        $user_obj->last_name = $_POST['last_name'];
        $user_obj->email = $_POST['email'];
        $user_obj->phone = $_POST['phone'];
        $user_obj->address = $_POST['address'];
        $user_obj->profile_image = $profile_image;
        
        // Update advocate information
        $advocate_obj->license_number = $_POST['license_number'];
        $advocate_obj->specialization = $_POST['specialization'];
        $advocate_obj->experience_years = $_POST['experience_years'];
        $advocate_obj->education = $_POST['education'];
        $advocate_obj->bio = $_POST['bio'];
        $advocate_obj->hourly_rate = $_POST['hourly_rate'];
        
        // Update password if provided
        $password_updated = false;
        if(!empty($_POST['new_password']) && !empty($_POST['confirm_password'])) {
            if($_POST['new_password'] == $_POST['confirm_password']) {
                if(strlen($_POST['new_password']) >= 6) {
                    $user_obj->password = $_POST['new_password'];
                    $password_updated = true;
                } else {
                    $update_error = "Password must be at least 6 characters long.";
                }
            } else {
                $update_error = "Passwords do not match.";
            }
        }
        
        if(empty($update_error)) {
            // Update user record
            if($user_obj->update($password_updated)) {
                // Update advocate record
                if($advocate_obj->update()) {
                    // Update session variables
                    $_SESSION['first_name'] = $user_obj->first_name;
                    $_SESSION['last_name'] = $user_obj->last_name;
                    $_SESSION['email'] = $user_obj->email;
                    
                    $update_success = "Profile updated successfully.";
                } else {
                    $update_error = "Failed to update advocate information.";
                }
            } else {
                $update_error = "Failed to update user information.";
            }
        }
    }
}

// Set page title
$page_title = "My Profile - Legal Case Management System";

// Include header
include_once '../templates/advocate-header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-semibold text-gray-800">My Profile</h1>
        <a href="advocate-dashboard.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
            <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
        </a>
    </div>
    
    <!-- Include the message block -->
    <?php include_once '../templates/message-block.php'; ?>
    
    <?php if(!empty($update_success)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p><?php echo $update_success; ?></p>
        </div>
    <?php endif; ?>
    
    <?php if(!empty($update_error)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p><?php echo $update_error; ?></p>
        </div>
    <?php endif; ?>
    
    <!-- Profile Form -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="p-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Profile Information</h2>
        </div>
        <div class="p-4">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
                <div class="flex flex-col md:flex-row mb-6">
                    <div class="w-full md:w-1/3 flex justify-center md:justify-start mb-4 md:mb-0">
                        <div class="relative">
                            <div class="w-40 h-40 rounded-full overflow-hidden bg-gray-200">
                                <?php if(!empty($user_obj->profile_image)): ?>
                                    <img src="../uploads/profile/<?php echo $user_obj->profile_image; ?>" alt="Profile Image" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center bg-blue-100 text-blue-500">
                                        <i class="fas fa-user-circle text-6xl"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <label for="profile_image" class="absolute bottom-0 right-0 bg-blue-500 text-white rounded-full w-10 h-10 flex items-center justify-center cursor-pointer hover:bg-blue-600">
                                <i class="fas fa-camera"></i>
                                <input type="file" id="profile_image" name="profile_image" class="hidden" accept="image/jpeg,image/png">
                            </label>
                        </div>
                    </div>
                    <div class="w-full md:w-2/3">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                                <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user_obj->first_name); ?>" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                            </div>
                            <div>
                                <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                                <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user_obj->last_name); ?>" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                            </div>
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_obj->email); ?>" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                            </div>
                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($user_obj->phone ?? ''); ?>" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                            </div>
                        </div>
                        <div class="mt-4">
                            <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                            <textarea id="address" name="address" rows="2" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"><?php echo htmlspecialchars($user_obj->address ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="border-t border-gray-200 pt-4 mt-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Professional Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="license_number" class="block text-sm font-medium text-gray-700 mb-1">License Number</label>
                            <input type="text" id="license_number" name="license_number" value="<?php echo htmlspecialchars($advocate_obj->license_number); ?>" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                        </div>
                        <div>
                            <label for="specialization" class="block text-sm font-medium text-gray-700 mb-1">Specialization</label>
                            <input type="text" id="specialization" name="specialization" value="<?php echo htmlspecialchars($advocate_obj->specialization); ?>" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                        </div>
                        <div>
                            <label for="experience_years" class="block text-sm font-medium text-gray-700 mb-1">Years of Experience</label>
                            <input type="number" id="experience_years" name="experience_years" value="<?php echo htmlspecialchars($advocate_obj->experience_years); ?>" min="0" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                        </div>
                        <div>
                            <label for="hourly_rate" class="block text-sm font-medium text-gray-700 mb-1">Hourly Rate ($)</label>
                            <input type="number" id="hourly_rate" name="hourly_rate" value="<?php echo htmlspecialchars($advocate_obj->hourly_rate); ?>" min="0" step="0.01" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label for="education" class="block text-sm font-medium text-gray-700 mb-1">Education</label>
                        <input type="text" id="education" name="education" value="<?php echo htmlspecialchars($advocate_obj->education); ?>" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                    </div>
                    <div class="mb-4">
                        <label for="bio" class="block text-sm font-medium text-gray-700 mb-1">Professional Bio</label>
                        <textarea id="bio" name="bio" rows="4" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5"><?php echo htmlspecialchars($advocate_obj->bio); ?></textarea>
                    </div>
                </div>
                
                <div class="border-t border-gray-200 pt-4 mt-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Change Password</h3>
                    <p class="text-sm text-gray-600 mb-4">Leave blank if you don't want to change your password</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                            <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                            <input type="password" id="new_password" name="new_password" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                        </div>
                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="bg-white border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end mt-6">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-medium rounded-lg text-sm px-5 py-2.5 text-center">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Preview profile image before upload
document.getElementById('profile_image').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(event) {
            const imgElement = document.querySelector('.w-40.h-40 img');
            if (imgElement) {
                imgElement.src = event.target.result;
            } else {
                const iconContainer = document.querySelector('.w-40.h-40 div');
                if (iconContainer) {
                    iconContainer.innerHTML = '';
                    const newImg = document.createElement('img');
                    newImg.src = event.target.result;
                    newImg.className = 'w-full h-full object-cover';
                    iconContainer.appendChild(newImg);
                }
            }
        };
        reader.readAsDataURL(file);
    }
});
</script>

<?php
// Include footer
include_once '../templates/footer.php';
?>
