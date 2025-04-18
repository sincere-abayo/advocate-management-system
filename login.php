<?php
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

// Initialize variables
$username = $password = "";
$username_err = $password_err = $login_err = "";

// Process form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Check if username is empty
    if(empty(trim($_POST["username"]))) {
        $username_err = "Please enter username.";
    } else {
        $username = trim($_POST["username"]);
    }
    
    // Check if password is empty
    if(empty(trim($_POST["password"]))) {
        $password_err = "Please enter your password.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate credentials
    if(empty($username_err) && empty($password_err)) {
        // Get database connection
        $database = new Database();
        $db = $database->getConnection();
        
        // Prepare a user object
        $user = new User($db);
        $user->username = $username;
        $user->password = $password;
        
       // Attempt to login
       if($user->login()) {
        // Store data in session variables
        $_SESSION["user_id"] = $user->id;
        $_SESSION["username"] = $user->username;
        $_SESSION["role"] = $user->role;
        $_SESSION["first_name"] = $user->first_name;
        $_SESSION["last_name"] = $user->last_name;
        $_SESSION["email"] = $user->email;
        
        // Get additional user details
        $user->id = $_SESSION["user_id"];
        $user->readOne();
        $_SESSION["profile_image"] = $user->profile_image;
        
        // Redirect user based on role
        switch($_SESSION["role"]) {
            case "admin":
                header("Location: index.php");
                break;
            case "advocate":
                header("Location: advocate/advocate-dashboard.php");
                break;
            case "client":
                header("Location: client-dashboard.php");
                break;
            default:
                header("Location: index.php");
        }
        exit();
    } else {
        // Display an error message if credentials are invalid
        $login_err = "Invalid username or password.";
    }
}
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Legal Case Management System</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full bg-white rounded-lg shadow-lg p-8">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Legal Case Management</h1>
            <p class="text-gray-600 mt-2">Sign in to your account</p>
        </div>
        
        <?php if(!empty($login_err)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $login_err; ?></span>
            </div>
        <?php endif; ?>
        
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="mb-6">
                <label for="username" class="block mb-2 text-sm font-medium text-gray-900">Username</label>
                <input type="text" id="username" name="username" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5 <?php echo (!empty($username_err)) ? 'border-red-500' : ''; ?>" value="<?php echo $username; ?>">
                <?php if(!empty($username_err)): ?>
                    <p class="mt-1 text-sm text-red-600"><?php echo $username_err; ?></p>
                <?php endif; ?>
            </div>
            <div class="mb-6">
                <label for="password" class="block mb-2 text-sm font-medium text-gray-900">Password</label>
                <input type="password" id="password" name="password" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary focus:border-primary block w-full p-2.5 <?php echo (!empty($password_err)) ? 'border-red-500' : ''; ?>">
                <?php if(!empty($password_err)): ?>
                    <p class="mt-1 text-sm text-red-600"><?php echo $password_err; ?></p>
                <?php endif; ?>
            </div>
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center">
                    <input id="remember" type="checkbox" class="w-4 h-4 text-primary bg-gray-100 border-gray-300 rounded focus:ring-primary">
                    <label for="remember" class="ml-2 text-sm font-medium text-gray-900">Remember me</label>
                </div>
                <a href="forgot-password.php" class="text-sm font-medium text-primary hover:underline">Forgot password?</a>
            </div>
            <button type="submit" class="w-full text-white bg-blue-600 hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center">Sign in</button>
        </form>
        
        <div class="text-center mt-6">
            <p class="text-sm text-gray-600">Don't have an account? <a href="register.php" class="text-primary hover:underline">Create an account</a></p>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="assets/js/main.js"></script>
</body>
</html>
