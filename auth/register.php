<?php
// error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Include required files
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is already logged in
if (isLoggedIn()) {
    header("Location: /index.php");
    exit;
}

$errors = [];
$success = false;

// Process registration form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $firstName = sanitizeInput($_POST['first_name']);
    $lastName = sanitizeInput($_POST['last_name']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $userType = sanitizeInput($_POST['user_type']);
    
    // Validate first name
    if (empty($firstName)) {
        $errors['first_name'] = 'First name is required';
    }
    
    // Validate last name
    if (empty($lastName)) {
        $errors['last_name'] = 'Last name is required';
    }
    
    // Validate email
    if (empty($email)) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address';
    } else {
        // Check if email already exists
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $errors['email'] = 'Email address is already registered';
        }
        
        $stmt->close();
    }
    
    // Validate phone (optional)
    if (!empty($phone) && !preg_match('/^[0-9+\-\s()]{7,15}$/', $phone)) {
        $errors['phone'] = 'Please enter a valid phone number';
    }
    
    // Validate username
    if (empty($username)) {
        $errors['username'] = 'Username is required';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        $errors['username'] = 'Username must be 3-20 characters and can only contain letters, numbers, and underscores';
    } else {
        // Check if username already exists
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $errors['username'] = 'Username is already taken';
        }
        
        $stmt->close();
    }
    
    // Validate password
    if (empty($password)) {
        $errors['password'] = 'Password is required';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters long';
    }
    
    // Validate confirm password
    if ($password !== $confirmPassword) {
        $errors['confirm_password'] = 'Passwords do not match';
    }
    
    // Validate user type
    if (empty($userType) || !in_array($userType, ['client', 'advocate'])) {
        $errors['user_type'] = 'Please select a valid user type';
    }
    
    // If no errors, register the user
    if (empty($errors)) {
        $conn = getDBConnection();
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Generate verification token
        $verificationToken = bin2hex(random_bytes(32));
        
// Insert user into database
$fullName = $firstName . ' ' . $lastName;
$stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, username, password, user_type, status, verification_token, created_at) VALUES (?, ?, ?, ?, ?, ?, 'active', ?, NOW())");
$stmt->bind_param("sssssss", $fullName, $email, $phone, $username, $hashedPassword, $userType, $verificationToken);

        if ($stmt->execute()) {
            $userId = $conn->insert_id;
            
          // Create user profile based on user type
if ($userType === 'advocate') {
    // Generate a temporary license number that can be updated later
    $tempLicenseNumber = 'TEMP-' . strtoupper(substr(md5($userId . time()), 0, 10));
    
    $stmt = $conn->prepare("INSERT INTO advocate_profiles (user_id, license_number) VALUES (?, ?)");
    $stmt->bind_param("is", $userId, $tempLicenseNumber);
    $stmt->execute();
} elseif ($userType === 'client') {
    $stmt = $conn->prepare("INSERT INTO client_profiles (user_id) VALUES (?)");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
}

 elseif ($userType === 'client') {
    $stmt = $conn->prepare("INSERT INTO client_profiles (user_id) VALUES (?)");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
}

            
            // Send verification email
            $verificationLink = "http://" . $_SERVER['HTTP_HOST'] . "/auth/verify.php?token=" . $verificationToken;
            $to = $email;
            $subject = "Verify Your Account - Advocate Management System";
            $message = "Dear $firstName $lastName,\n\n";
            $message .= "Thank you for registering with the Advocate Management System. Please click the link below to verify your account:\n\n";
            $message .= $verificationLink . "\n\n";
            $message .= "If you did not register for an account, please ignore this email.\n\n";
            $message .= "Best regards,\nThe Advocate Management Team";
            $headers = "From: noreply@advocatemanagement.com";
            
            // Uncomment to enable email sending in production
            // mail($to, $subject, $message, $headers);
            
            $success = true;
        } else {
            $errors['db'] = "Registration failed. Please try again.";
        }
        
        $stmt->close();
        $conn->close();
    }
}

// Set page title for header
$pageTitle = "Register - Advocate Management System";

// Include header (without navigation for registration page)
include_once '../includes/header-minimal.php';
?>

<div class="bg-gray-100 min-h-screen py-12">
    <div class="container mx-auto px-4">
        <div class="max-w-2xl mx-auto">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-blue-800">Advocate Management System</h1>
                <p class="text-gray-600">Create a new account</p>
            </div>

            <?php if ($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p class="font-medium">Registration successful!</p>
                <p>A verification email has been sent to your email address. Please check your inbox and follow the
                    instructions to verify your account.</p>
                <p class="mt-4"><a href="login.php" class="text-green-700 font-semibold hover:underline">Go to
                        Login</a></p>
                <p class="text-sm mt-2">You will be redirected to the login page in <span id="countdown">3</span>
                    seconds...</p>

                <!-- Meta refresh tag (works even if JavaScript is disabled) -->
                <meta http-equiv="refresh" content="3;url=/auth/login.php">

                <!-- JavaScript countdown and redirect -->
                <script>
                // Countdown timer
                let seconds = 3;
                const countdownElement = document.getElementById('countdown');

                const countdownInterval = setInterval(function() {
                    seconds--;
                    countdownElement.textContent = seconds;

                    if (seconds <= 0) {
                        clearInterval(countdownInterval);
                        window.location.href = 'login.php';
                    }
                }, 1000);
                </script>
            </div>

            <?php else: ?>
            <div class="bg-white rounded-lg shadow-lg p-8">
                <?php if (isset($errors['db'])): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p><?php echo $errors['db']; ?></p>
                </div>
                <?php endif; ?>

                <form method="POST" action="" data-validate class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="first_name" class="block text-gray-700 font-medium mb-2">First Name *</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-user text-gray-400"></i>
                                </div>
                                <input type="text" id="first_name" name="first_name"
                                    class="form-input pl-10 w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-150"
                                    required placeholder="Enter your first name"
                                    value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                            </div>
                            <?php if (isset($errors['first_name'])): ?>
                            <p class="text-red-500 text-sm mt-1"><?php echo $errors['first_name']; ?></p>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label for="last_name" class="block text-gray-700 font-medium mb-2">Last Name *</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-user text-gray-400"></i>
                                </div>
                                <input type="text" id="last_name" name="last_name"
                                    class="form-input pl-10 w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-150"
                                    required placeholder="Enter your last name"
                                    value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                            </div>
                            <?php if (isset($errors['last_name'])): ?>
                            <p class="text-red-500 text-sm mt-1"><?php echo $errors['last_name']; ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="email" class="block text-gray-700 font-medium mb-2">Email Address *</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-envelope text-gray-400"></i>
                                </div>
                                <input type="email" id="email" name="email"
                                    class="form-input pl-10 w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-150"
                                    required placeholder="Enter your email address"
                                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            </div>
                            <?php if (isset($errors['email'])): ?>
                            <p class="text-red-500 text-sm mt-1"><?php echo $errors['email']; ?></p>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label for="phone" class="block text-gray-700 font-medium mb-2">Phone Number</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-phone text-gray-400"></i>
                                </div>
                                <input type="tel" id="phone" name="phone"
                                    class="form-input pl-10 w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-150"
                                    placeholder="Enter your phone number (optional)"
                                    value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                            </div>
                            <?php if (isset($errors['phone'])): ?>
                            <p class="text-red-500 text-sm mt-1"><?php echo $errors['phone']; ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div>
                        <label for="username" class="block text-gray-700 font-medium mb-2">Username *</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-at text-gray-400"></i>
                            </div>
                            <input type="text" id="username" name="username"
                                class="form-input pl-10 w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-150"
                                required placeholder="Choose a username"
                                value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        </div>
                        <p class="text-gray-500 text-sm mt-1">3-20 characters, letters, numbers, and underscores only
                        </p>
                        <?php if (isset($errors['username'])): ?>
                        <p class="text-red-500 text-sm mt-1"><?php echo $errors['username']; ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="password" class="block text-gray-700 font-medium mb-2">Password *</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-lock text-gray-400"></i>
                                </div>
                                <input type="password" id="password" name="password"
                                    class="form-input pl-10 w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-150"
                                    required placeholder="Create a password">
                                <button type="button"
                                    class="toggle-password absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 focus:outline-none"
                                    data-target="#password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <p class="text-gray-500 text-sm mt-1">Minimum 8 characters</p>
                            <?php if (isset($errors['password'])): ?>
                            <p class="text-red-500 text-sm mt-1"><?php echo $errors['password']; ?></p>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label for="confirm_password" class="block text-gray-700 font-medium mb-2">Confirm Password
                                *</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-lock text-gray-400"></i>
                                </div>
                                <input type="password" id="confirm_password" name="confirm_password"
                                    class="form-input pl-10 w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-150"
                                    required placeholder="Confirm your password">
                                <button type="button"
                                    class="toggle-password absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 focus:outline-none"
                                    data-target="#confirm_password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <?php if (isset($errors['confirm_password'])): ?>
                            <p class="text-red-500 text-sm mt-1"><?php echo $errors['confirm_password']; ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="bg-gray-50 p-5 rounded-lg border border-gray-200">
                        <label class="block text-gray-700 font-medium mb-3">I am registering as *</label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="border rounded-lg p-4 cursor-pointer user-type-option hover:border-blue-400 hover:bg-blue-50 transition duration-200 <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'client') ? 'border-blue-500 bg-blue-50' : 'border-gray-200 bg-white'; ?>"
                                data-value="client">
                                <input type="radio" id="user_type_client" name="user_type" value="client" class="hidden"
                                    <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'client') ? 'checked' : ''; ?>>
                                <label for="user_type_client" class="flex items-center cursor-pointer">
                                    <div
                                        class="w-6 h-6 rounded-full border border-gray-300 flex items-center justify-center mr-3 radio-circle">
                                        <div
                                            class="w-4 h-4 rounded-full bg-blue-500 <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'client') ? '' : 'hidden'; ?> radio-dot">
                                        </div>
                                    </div>
                                    <div>
                                        <span class="font-medium block">Client</span>
                                        <span class="text-sm text-gray-500">I need legal services</span>
                                    </div>
                                </label>
                            </div>

                            <div class="border rounded-lg p-4 cursor-pointer user-type-option hover:border-blue-400 hover:bg-blue-50 transition duration-200 <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'advocate') ? 'border-blue-500 bg-blue-50' : 'border-gray-200 bg-white'; ?>"
                                data-value="advocate">
                                <input type="radio" id="user_type_advocate" name="user_type" value="advocate"
                                    class="hidden"
                                    <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'advocate') ? 'checked' : ''; ?>>
                                <label for="user_type_advocate" class="flex items-center cursor-pointer">
                                    <div
                                        class="w-6 h-6 rounded-full border border-gray-300 flex items-center justify-center mr-3 radio-circle">
                                        <div
                                            class="w-4 h-4 rounded-full bg-blue-500 <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'advocate') ? '' : 'hidden'; ?> radio-dot">
                                        </div>
                                    </div>
                                    <div>
                                        <span class="font-medium block">Advocate</span>
                                        <span class="text-sm text-gray-500">I provide legal services</span>
                                    </div>
                                </label>
                            </div>
                        </div>
                        <?php if (isset($errors['user_type'])): ?>
                        <p class="text-red-500 text-sm mt-2"><?php echo $errors['user_type']; ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="bg-gray-50 p-5 rounded-lg border border-gray-200">
                        <div class="flex items-center">
                            <input type="checkbox" id="terms" name="terms"
                                class="h-5 w-5 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" required>
                            <label for="terms" class="ml-3 text-gray-600">
                                I agree to the <a href="/terms.php"
                                    class="text-blue-600 hover:text-blue-800 hover:underline">Terms of Service</a> and
                                <a href="/privacy-policy.php"
                                    class="text-blue-600 hover:text-blue-800 hover:underline">Privacy Policy</a>
                            </label>
                        </div>
                    </div>

                    <button type="submit"
                        class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-300 flex items-center justify-center">
                        <i class="fas fa-user-plus mr-2"></i>
                        Create Account
                    </button>
                </form>

                <div class="mt-6 text-center text-sm text-gray-600">
                    Already have an account? <a href="login.php" class="text-blue-600 hover:underline">Sign in here</a>
                </div>
                <!-- Add this right after the "Already have an account? Sign in here" section -->
                <div class="mt-4 text-center">
                    <a href="../index.php"
                        class="inline-flex items-center text-blue-600 hover:text-blue-800 hover:underline">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Home
                    </a>
                </div>

            </div>
            <?php endif; ?>

            <div class="mt-8 text-center text-sm text-gray-500">
                © <?php echo date('Y'); ?> Advocate Management System. All rights reserved.
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle user type selection
    const userTypeOptions = document.querySelectorAll('.user-type-option');
    userTypeOptions.forEach(option => {
        option.addEventListener('click', function() {
            // Remove selected class from all options
            userTypeOptions.forEach(opt => {
                opt.classList.remove('border-blue-500', 'bg-blue-50');
                opt.querySelector('.radio-dot').classList.add('hidden');
            });

            // Add selected class to clicked option
            this.classList.add('border-blue-500', 'bg-blue-50');
            this.querySelector('.radio-dot').classList.remove('hidden');

            // Check the radio button
            const radio = this.querySelector('input[type="radio"]');
            radio.checked = true;
        });
    });
});
</script>

<?php
// Include footer
include_once '../includes/footer-minimal.php';
?>
<script src="/assets/js/main.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle password visibility
    const toggleButtons = document.querySelectorAll('.toggle-password');
    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const passwordInput = document.querySelector(targetId);

            // Toggle password visibility
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                this.innerHTML = '<i class="fas fa-eye-slash"></i>';
            } else {
                passwordInput.type = 'password';
                this.innerHTML = '<i class="fas fa-eye"></i>';
            }
        });
    });
});
</script>