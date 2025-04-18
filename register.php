<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Start session
session_start();

// Check if user is already logged in
if(isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Include database and user class
include_once 'config/database.php';
include_once 'classes/User.php';
include_once 'classes/Client.php';
include_once 'classes/Advocate.php';

// Initialize variables
$username = $password = $confirm_password = $email = $first_name = $last_name = $phone = $address = "";
$username_err = $password_err = $confirm_password_err = $email_err = $first_name_err = $last_name_err = "";
$register_success = $register_err = "";
$user_type = isset($_POST["user_type"]) ? $_POST["user_type"] : "client"; // Default to client

// Process form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Get database connection
    $database = new Database();
    $db = $database->getConnection();
    
    // Prepare a user object
    $user = new User($db);
    
    // Validate username
    if(empty(trim($_POST["username"]))) {
        $username_err = "Please enter a username.";
    } else {
        // Check if username exists
        $user->username = trim($_POST["username"]);
        if($user->usernameExists()) {
            $username_err = "This username is already taken.";
        } else {
            $username = trim($_POST["username"]);
        }
    }
    
    // Validate email
    if(empty(trim($_POST["email"]))) {
        $email_err = "Please enter an email.";
    } elseif(!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        $email_err = "Please enter a valid email address.";
    } else {
        // Check if email exists
        $user->email = trim($_POST["email"]);
        if($user->emailExists()) {
            $email_err = "This email is already registered.";
        } else {
            $email = trim($_POST["email"]);
        }
    }
    
    // Validate first name
    if(empty(trim($_POST["first_name"]))) {
        $first_name_err = "Please enter your first name.";
    } else {
        $first_name = trim($_POST["first_name"]);
    }
    
    // Validate last name
    if(empty(trim($_POST["last_name"]))) {
        $last_name_err = "Please enter your last name.";
    } else {
        $last_name = trim($_POST["last_name"]);
    }
    
    // Validate password
    if(empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";     
    } elseif(strlen(trim($_POST["password"])) < 6) {
        $password_err = "Password must have at least 6 characters.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate confirm password
    if(empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm password.";     
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if(empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Password did not match.";
        }
    }
    
    // Check input errors before inserting in database
    if(empty($username_err) && empty($password_err) && empty($confirm_password_err) && empty($email_err) && empty($first_name_err) && empty($last_name_err)) {
        
        // Set user properties
        $user->email = $email;
        $user->username = $username;
        $user->password = $password;
        $user->role = $user_type; // Set role based on selection
        $user->first_name = $first_name;
        $user->last_name = $last_name;
        $user->phone = trim($_POST["phone"]);
        $user->address = trim($_POST["address"]);
        $user->is_active = 1; // Active by default
        
        // Create the user
        $user_id = $user->create();
        if($user_id !== false && $user_id > 0) {
        //     // Create client or advocate profile based on user type
            if($user_type == "client") {
                $client = new Client($db);
                $client->user_id = $user_id;
                $client->occupation = isset($_POST["occupation"]) ? trim($_POST["occupation"]) : "";
                $client->company = isset($_POST["company"]) ? trim($_POST["company"]) : "";
                $client->reference_source = isset($_POST["reference_source"]) ? trim($_POST["reference_source"]) : "";
                $client->notes = "";
                if($client->create()) {
                    $register_success = "Client Account created successfully with Username '$username'. You can now <a href='login.php' class='text-primary hover:underline'>log in</a>.";
                } else {
                    $register_err = "Something went wrong. Please try again later.";
                }
            } 
          else if($user_type == "advocate") {
                $advocate = new Advocate($db);
                $advocate->user_id = $user_id;
                $advocate->license_number = isset($_POST["license_number"]) ? trim($_POST["license_number"]) : "";
                $advocate->specialization = isset($_POST["specialization"]) ? trim($_POST["specialization"]) : "";
                $advocate->experience_years = isset($_POST["experience_years"]) ? trim($_POST["experience_years"]) : 0;
                $advocate->education = isset($_POST["education"]) ? trim($_POST["education"]) : "";
                $advocate->bio = "";
                $advocate->hourly_rate = isset($_POST["hourly_rate"]) ? trim($_POST["hourly_rate"]) : 0;
               if($advocate->create()) {
                $register_success = "Client Account created successfully with Username '$username'. You can now <a href='login.php' class='text-primary hover:underline'>log in</a>.";
            } else {
                    $register_err = "Something went wrong. Please try again later.";
                }
            }
            else {
                $register_err = "Invalid user type $user_type. Please try again.";
            }
            $register_success = "Client Account created successfully with Username '$username'. You can now <a href='login.php' class='text-primary hover:underline'>log in</a>.";
            // Clear form data
            $username = $password = $confirm_password = $email = $first_name = $last_name = $phone = $address = "";
        } else {
            $register_err = "Something went wrong. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Legal Case Management System</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center py-12">
    <div class="max-w-full w-1/2 bg-white rounded-lg shadow-lg p-8">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Create an Account</h1>
            <p class="text-gray-600 mt-2">Sign up to get started with Legal Case Management</p>
        </div>
        
        <?php if(!empty($register_err)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $register_err; ?></span>
            </div>
        <?php endif; ?>
        
        <?php if(!empty($register_success)): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
        <span class="block sm:inline"><?php echo $register_success; ?></span>
        <p class="mt-2 text-sm">You will be redirected to the login page in 3 seconds...</p>
    </div>
    <!--  head section when registration is successful -->
    <script>

       setTimeout(function() {
            window.location.href = "login.php";
        }, 3000);
    
    </script>
<?php else: ?>

        
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <!-- User Type Selection -->
            <div class="mb-6">
                <label class="block mb-2 text-sm font-medium text-gray-900">I am registering as:</label>
                <div class="flex space-x-4">
                    <div class="flex items-center">
                        <input type="radio" id="client" name="user_type" value="client" class="w-4 h-4 text-primary" <?php echo ($user_type == "client") ? "checked" : ""; ?> onchange="toggleUserTypeFields()">
                        <label for="client" class="ml-2 text-sm font-medium text-gray-900">Client</label>
                    </div>
                    <div class="flex items-center">
                        <input type="radio" id="advocate" name="user_type" value="advocate" class="w-4 h-4 text-primary" <?php echo ($user_type == "advocate") ? "checked" : ""; ?> onchange="toggleUserTypeFields()">
                        <label for="advocate" class="ml-2 text-sm font-medium text-gray-900">Advocate</label>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="first_name" class="block mb-2 text-sm font-medium text-gray-900">First Name</label>
                    <input type="text" id="first_name" name="first_name" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5 <?php echo (!empty($first_name_err)) ? 'border-red-500' : ''; ?>" value="<?php echo $first_name; ?>">
                    <?php if(!empty($first_name_err)): ?>
                        <p class="mt-1 text-sm text-red-600"><?php echo $first_name_err; ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <label for="last_name" class="block mb-2 text-sm font-medium text-gray-900">Last Name</label>
                    <input type="text" id="last_name" name="last_name" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5 <?php echo (!empty($last_name_err)) ? 'border-red-500' : ''; ?>" value="<?php echo $last_name; ?>">
                    <?php if(!empty($last_name_err)): ?>
                        <p class="mt-1 text-sm text-red-600"><?php echo $last_name_err; ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div >
                <label for="email" class="block mb-2 text-sm font-medium text-gray-900">Email</label>
                <input type="email" id="email" name="email" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5 <?php echo (!empty($email_err)) ? 'border-red-500' : ''; ?>" value="<?php echo $email; ?>">
                <?php if(!empty($email_err)): ?>
                    <p class="mt-1 text-sm text-red-600"><?php echo $email_err; ?></p>
                <?php endif; ?>
            </div>
            
            <div >
                <label for="username" class="block mb-2 text-sm font-medium text-gray-900">Username</label>
                <input type="text" id="username" name="username" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5 <?php echo (!empty($username_err)) ? 'border-red-500' : ''; ?>" value="<?php echo $username; ?>">
                <?php if(!empty($username_err)): ?>
                    <p class="mt-1 text-sm text-red-600"><?php echo $username_err; ?></p>
                <?php endif; ?>
            </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label for="password" class="block mb-2 text-sm font-medium text-gray-900">Password</label>
                <input type="password" id="password" name="password" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5 <?php echo (!empty($password_err)) ? 'border-red-500' : ''; ?>">
                <?php if(!empty($password_err)): ?>
                    <p class="mt-1 text-sm text-red-600"><?php echo $password_err; ?></p>
                <?php endif; ?>
            </div>
            
            <div>
                <label for="confirm_password" class="block mb-2 text-sm font-medium text-gray-900">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5 <?php echo (!empty($confirm_password_err)) ? 'border-red-500' : ''; ?>">
                <?php if(!empty($confirm_password_err)): ?>
                    <p class="mt-1 text-sm text-red-600"><?php echo $confirm_password_err; ?></p>
                <?php endif; ?>
            </div>
            </div>
            
            <div class="mb-4">
                <label for="phone" class="block mb-2 text-sm font-medium text-gray-900">Phone (optional)</label>
                <input type="text" id="phone" name="phone" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5" value="<?php echo $phone; ?>">
            </div>
            
            <div class="mb-6">
                <label for="address" class="block mb-2 text-sm font-medium text-gray-900">Address (optional)</label>
                <textarea id="address" name="address" rows="3" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5"><?php echo $address; ?></textarea>
            </div>
                 <!-- Client-specific fields -->
                 <div id="client-fields" class="<?php echo ($user_type == "advocate") ? 'hidden' : ''; ?> mb-6">
                <h3 class="text-lg font-medium text-gray-900 mb-3">Client Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="occupation" class="block mb-2 text-sm font-medium text-gray-900">Occupation</label>
                        <input type="text" id="occupation" name="occupation" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">
                    </div>
                    <div>
                        <label for="company" class="block mb-2 text-sm font-medium text-gray-900">Company</label>
                        <input type="text" id="company" name="company" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">
                    </div>
                </div>
                <div>
                    <label for="reference_source" class="block mb-2 text-sm font-medium text-gray-900">How did you hear about us?</label>
                    <input type="text" id="reference_source" name="reference_source" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">
                </div>
            </div>
            
            <!-- Advocate-specific fields -->
            <div id="advocate-fields" class="<?php echo ($user_type == "client") ? 'hidden' : ''; ?> mb-6">
                <h3 class="text-lg font-medium text-gray-900 mb-3">Advocate Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="specialization" class="block mb-2 text-sm font-medium text-gray-900">Specialization</label>
                        <input type="text" id="specialization" name="specialization" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">
                    </div>
                    <div>
                        <label for="experience_years" class="block mb-2 text-sm font-medium text-gray-900">Years of Experience</label>
                        <input type="number" id="experience_years" name="experience_years" min="0" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="license_number" class="block mb-2 text-sm font-medium text-gray-900">License Number</label>
                    <input type="text" id="license_number" name="license_number" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">
                </div>
                <div>
                    <label for="education" class="block mb-2 text-sm font-medium text-gray-900">Education</label>
                    <input type="text" id="education" name="education" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">
                </div>
                </div>
                <div>
                    <label for="hourly_rate" class="block mb-2 text-sm font-medium text-gray-900">Hourly Rate ($)</label>
                    <input type="number" id="hourly_rate" name="hourly_rate" min="0" step="0.01" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5">
                </div>
            </div>
            
            <button type="submit" class="w-full text-white bg-blue-600 hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center">Create Account</button>
        </form>
        
        <?php endif; ?>
        
        <div class="text-center mt-6">
            <p class="text-sm text-gray-600">Already have an account? <a href="login.php" class="text-primary hover:underline">Sign in</a></p>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script>
        function toggleUserTypeFields() {
            const userType = document.querySelector('input[name="user_type"]:checked').value;
            const clientFields = document.getElementById('client-fields');
            const advocateFields = document.getElementById('advocate-fields');
            
            if (userType === 'client') {
                clientFields.classList.remove('hidden');
                advocateFields.classList.add('hidden');
            } else {
                clientFields.classList.add('hidden');
                advocateFields.classList.remove('hidden');
            }
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleUserTypeFields();
        });
    </script>
    <script src="assets/js/main.js"></script>
</body>
</html>
