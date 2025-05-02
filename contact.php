
<?php
// error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Include required files
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Start session

// Initialize variables
$name = $email = $phone = $company = $message = $request_type = '';
$errors = [];
$success = false;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate name
    if (empty($_POST['name'])) {
        $errors['name'] = 'Name is required';
    } else {
        $name = sanitizeInput($_POST['name']);
    }
    
    // Validate email
    if (empty($_POST['email'])) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address';
    } else {
        $email = sanitizeInput($_POST['email']);
    }
    
    // Validate phone (optional)
    if (!empty($_POST['phone'])) {
        $phone = sanitizeInput($_POST['phone']);
    }
    
    // Validate company (optional)
    if (!empty($_POST['company'])) {
        $company = sanitizeInput($_POST['company']);
    }
    
    // Validate message
    if (empty($_POST['message'])) {
        $errors['message'] = 'Message is required';
    } else {
        $message = sanitizeInput($_POST['message']);
    }
    
    // Validate request type
    if (empty($_POST['request_type'])) {
        $errors['request_type'] = 'Please select a request type';
    } else {
        $request_type = sanitizeInput($_POST['request_type']);
    }
    
    // If no errors, save to database
    if (empty($errors)) {
        $conn = getDBConnection();
        
        $stmt = $conn->prepare("INSERT INTO contact_requests (name, email, phone, company, message, request_type) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $name, $email, $phone, $company, $message, $request_type);
        
        if ($stmt->execute()) {
            // Set success message
            $_SESSION['flash_message'] = "Thank you for contacting us! Our sales team will get back to you shortly.";
            $_SESSION['flash_type'] = "success";
            
            // Send notification email to admin
            $to = "sales@advocatemanagement.com";
            $subject = "New Contact Request: " . $request_type;
            $email_message = "Name: $name\n";
            $email_message .= "Email: $email\n";
            $email_message .= "Phone: $phone\n";
            $email_message .= "Company: $company\n";
            $email_message .= "Request Type: $request_type\n\n";
            $email_message .= "Message:\n$message";
            $headers = "From: noreply@advocatemanagement.com";
            
            // Uncomment to enable email sending in production
            // mail($to, $subject, $email_message, $headers);
            
            // Clear form fields
            $name = $email = $phone = $company = $message = $request_type = '';
            $success = true;
        } else {
            $errors['db'] = "Sorry, there was an error submitting your request. Please try again.";
        }
        
        $stmt->close();
        $conn->close();
    }
}

// Include header
include 'includes/header.php';
?>

<!-- Contact Sales Page -->
<section class="py-16 bg-gray-50">
    <div class="container mx-auto px-4">
        <div class="max-w-4xl mx-auto">
            <div class="text-center mb-12">
                <h1 class="text-3xl md:text-4xl font-bold text-gray-800 mb-4">Contact Our Sales Team</h1>
                <p class="text-xl text-gray-600">Have questions about our platform or pricing? Our sales team is here to help.</p>
            </div>
            
            <?php if ($success): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                    <p class="font-medium">Thank you for contacting us!</p>
                    <p>Our sales team will get back to you shortly.</p>
                </div>
            <?php endif; ?>
            
            <?php displayFlashMessage(); ?>
            
            <?php if (isset($errors['db'])): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p><?php echo $errors['db']; ?></p>
                </div>
            <?php endif; ?>
            
            <div class="bg-white rounded-lg shadow-lg p-8">
                <form method="POST" action="" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="block text-gray-700 font-medium mb-2">Full Name *</label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <?php if (isset($errors['name'])): ?>
                                <p class="text-red-500 text-sm mt-1"><?php echo $errors['name']; ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <label for="email" class="block text-gray-700 font-medium mb-2">Email Address *</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <?php if (isset($errors['email'])): ?>
                                <p class="text-red-500 text-sm mt-1"><?php echo $errors['email']; ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <label for="phone" class="block text-gray-700 font-medium mb-2">Phone Number</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label for="company" class="block text-gray-700 font-medium mb-2">Company/Organization</label>
                            <input type="text" id="company" name="company" value="<?php echo htmlspecialchars($company); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    
                    <div>
                        <label for="request_type" class="block text-gray-700 font-medium mb-2">Request Type *</label>
                        <select id="request_type" name="request_type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="" <?php echo empty($request_type) ? 'selected' : ''; ?>>Select a request type</option>
                            <option value="pricing_inquiry" <?php echo $request_type === 'pricing_inquiry' ? 'selected' : ''; ?>>Pricing Inquiry</option>
                            <option value="product_demo" <?php echo $request_type === 'product_demo' ? 'selected' : ''; ?>>Product Demo</option>
                            <option value="custom_solution" <?php echo $request_type === 'custom_solution' ? 'selected' : ''; ?>>Custom Solution</option>
                            <option value="enterprise_plan" <?php echo $request_type === 'enterprise_plan' ? 'selected' : ''; ?>>Enterprise Plan</option>
                            <option value="other" <?php echo $request_type === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                        <?php if (isset($errors['request_type'])): ?>
                            <p class="text-red-500 text-sm mt-1"><?php echo $errors['request_type']; ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <label for="message" class="block text-gray-700 font-medium mb-2">Message *</label>
                        <textarea id="message" name="message" rows="5" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required><?php echo htmlspecialchars($message); ?></textarea>
                        <?php if (isset($errors['message'])): ?>
                            <p class="text-red-500 text-sm mt-1"><?php echo $errors['message']; ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="flex items-center">
                        <input type="checkbox" id="consent" name="consent" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" required>
                        <label for="consent" class="ml-2 block text-sm text-gray-700">
                            I agree to the <a href="/privacy-policy.php" class="text-blue-600 hover:underline">Privacy Policy</a> and consent to being contacted by the sales team.
                        </label>
                    </div>
                    
                    <div>
                        <button type="submit" class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-300">
                            Submit Request
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="mt-12 grid grid-cols-1 md:grid-cols-3 gap-8 text-center">
                <div class="p-6">
                    <div class="text-blue-600 mb-4">
                        <i class="fas fa-phone-alt text-3xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Call Us</h3>
                    <p class="text-gray-600">+250 788 123 456</p>
                    <p class="text-gray-600">Mon-Fri, 9am-5pm</p>
                </div>
                
                <div class="p-6">
                    <div class="text-blue-600 mb-4">
                        <i class="fas fa-envelope text-3xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Email Us</h3>
                    <p class="text-gray-600">sales@advocatemanagement.com</p>
                    <p class="text-gray-600">support@advocatemanagement.com</p>
                </div>
                
                <div class="p-6">
                    <div class="text-blue-600 mb-4">
                        <i class="fas fa-map-marker-alt text-3xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Visit Us</h3>
                    <p class="text-gray-600">KN 5 Ave, Kigali</p>
                    <p class="text-gray-600">Rwanda</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FAQ Section -->
<?php
include 'includes/faq.php';
?>

<!-- Testimonials Section -->
<?php
include 'includes/testimonial.php';
?>

<?php
// Include footer
include 'includes/footer.php';
?>