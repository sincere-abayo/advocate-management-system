<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Include required files
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is logged in
$isLoggedIn = isLoggedIn();

// Get testimonials


// Get features
function getFeatures() {
    return [
        [
            'title' => 'Case Management',
            'description' => 'Track all your legal cases in one place with detailed status updates, document storage, and activity logs.',
            'icon' => 'fa-balance-scale'
        ],
        [
            'title' => 'Client Portal',
            'description' => 'Clients can view case progress, upload documents, schedule appointments, and communicate securely with their advocates.',
            'icon' => 'fa-user-shield'
        ],
        [
            'title' => 'Document Management',
            'description' => 'Securely store, share, and manage all case-related documents with version control and access permissions.',
            'icon' => 'fa-file-contract'
        ],
        [
            'title' => 'Appointment Scheduling',
            'description' => 'Integrated calendar system for scheduling consultations, hearings, and meetings with automatic reminders.',
            'icon' => 'fa-calendar-alt'
        ],
        [
            'title' => 'Billing & Invoicing',
            'description' => 'Generate professional invoices, track payments, and manage billing records for all client engagements.',
            'icon' => 'fa-file-invoice-dollar'
        ],
        [
            'title' => 'Secure Communication',
            'description' => 'End-to-end encrypted messaging system for confidential communications between advocates and clients.',
            'icon' => 'fa-comments'
        ]
    ];
}

// Include header
include 'includes/header.php';
?>

<!-- Hero Section -->
<section class="bg-gradient-to-r from-blue-800 to-blue-600 text-white py-16">
    <div class="container mx-auto px-4">
        <div class="flex flex-col md:flex-row items-center">
            <div class="md:w-1/2 mb-10 md:mb-0">
                <h1 class="text-4xl md:text-5xl font-bold mb-4">Streamline Your Legal Practice</h1>
                <p class="text-xl mb-8">A comprehensive management system connecting advocates and clients for efficient case handling, document management, and communication.</p>
                
                <?php if (!$isLoggedIn): ?>
                <div class="flex flex-col sm:flex-row space-y-4 sm:space-y-0 sm:space-x-4">
                    <a href="auth/register.php?type=advocate" class="bg-white text-blue-800 hover:bg-blue-100 font-semibold py-3 px-6 rounded-lg shadow-md transition duration-300 text-center">
                        Register as Advocate
                    </a>
                    <a href="auth/register.php?type=client" class="bg-transparent hover:bg-blue-700 text-white border-2 border-white font-semibold py-3 px-6 rounded-lg transition duration-300 text-center">
                        Register as Client
                    </a>
                </div>
                <?php else: ?>
                <a href="<?php echo $_SESSION['user_type']; ?>/index.php" class="bg-white text-blue-800 hover:bg-blue-100 font-semibold py-3 px-6 rounded-lg shadow-md transition duration-300 inline-block">
                    Go to Dashboard
                </a>
                <?php endif; ?>
            </div>
            
            <div class="md:w-1/2">
            <img src="https://images.unsplash.com/photo-1589829545856-d10d557cf95f?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80" alt="Advocate Management System" class="rounded-lg shadow-xl">
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="py-16 bg-gray-50">
    <div class="container mx-auto px-4">
        <div class="text-center mb-16">
            <h2 class="text-3xl font-bold text-gray-800 mb-4">Comprehensive Legal Management</h2>
            <p class="text-xl text-gray-600 max-w-3xl mx-auto">Our platform offers a complete suite of tools designed specifically for legal professionals and their clients.</p>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach(getFeatures() as $feature): ?>
            <div class="bg-white rounded-lg shadow-md p-8 transition duration-300 hover:shadow-lg">
                <div class="text-blue-600 mb-4">
                    <i class="fas <?php echo $feature['icon']; ?> text-4xl"></i>
                </div>
                <h3 class="text-xl font-semibold mb-3"><?php echo $feature['title']; ?></h3>
                <p class="text-gray-600"><?php echo $feature['description']; ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- How It Works Section -->
<section class="py-16 bg-white">
    <div class="container mx-auto px-4">
        <div class="text-center mb-16">
            <h2 class="text-3xl font-bold text-gray-800 mb-4">How It Works</h2>
            <p class="text-xl text-gray-600 max-w-3xl mx-auto">Our platform simplifies the legal process for both advocates and clients.</p>
        </div>
        
        <div class="flex flex-col md:flex-row items-center justify-center space-y-12 md:space-y-0 md:space-x-8">
            <!-- For Advocates -->
            <div class="md:w-1/2 bg-blue-50 rounded-lg p-8">
                <h3 class="text-2xl font-bold text-blue-800 mb-6 flex items-center">
                    <i class="fas fa-gavel mr-3"></i> For Advocates
                </h3>
                <ul class="space-y-4">
                    <li class="flex items-start">
                        <div class="bg-blue-600 text-white rounded-full w-8 h-8 flex items-center justify-center flex-shrink-0 mt-1">1</div>
                        <div class="ml-4">
                            <h4 class="font-semibold text-lg">Register & Create Profile</h4>
                            <p class="text-gray-600">Create your professional profile with your specializations and experience.</p>
                        </div>
                    </li>
                    <li class="flex items-start">
                        <div class="bg-blue-600 text-white rounded-full w-8 h-8 flex items-center justify-center flex-shrink-0 mt-1">2</div>
                        <div class="ml-4">
                            <h4 class="font-semibold text-lg">Manage Cases</h4>
                            <p class="text-gray-600">Create and track cases, upload documents, and record activities.</p>
                        </div>
                    </li>
                    <li class="flex items-start">
                        <div class="bg-blue-600 text-white rounded-full w-8 h-8 flex items-center justify-center flex-shrink-0 mt-1">3</div>
                        <div class="ml-4">
                            <h4 class="font-semibold text-lg">Communicate & Schedule</h4>
                            <p class="text-gray-600">Securely message clients and schedule appointments through the integrated calendar.</p>
                        </div>
                    </li>
                    <li class="flex items-start">
                        <div class="bg-blue-600 text-white rounded-full w-8 h-8 flex items-center justify-center flex-shrink-0 mt-1">4</div>
                        <div class="ml-4">
                            <h4 class="font-semibold text-lg">Generate Invoices</h4>
                            <p class="text-gray-600">Create professional invoices and track payments from clients.</p>
                        </div>
                    </li>
                </ul>
                
                <?php if (!$isLoggedIn): ?>
                <div class="mt-8 text-center">
                    <a href="auth/register.php?type=advocate" class="bg-blue-600 text-white hover:bg-blue-700 font-semibold py-2 px-6 rounded-lg transition duration-300">
                        Register as Advocate
                    </a>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- For Clients -->
            <div class="md:w-1/2 bg-green-50 rounded-lg p-8">
                <h3 class="text-2xl font-bold text-green-800 mb-6 flex items-center">
                    <i class="fas fa-user-tie mr-3"></i> For Clients
                </h3>
                <ul class="space-y-4">
                    <li class="flex items-start">
                        <div class="bg-green-600 text-white rounded-full w-8 h-8 flex items-center justify-center flex-shrink-0 mt-1">1</div>
                        <div class="ml-4">
                            <h4 class="font-semibold text-lg">Create Account</h4>
                            <p class="text-gray-600">Sign up and complete your profile with relevant information.</p>
                        </div>
                    </li>
                    <li class="flex items-start">
                        <div class="bg-green-600 text-white rounded-full w-8 h-8 flex items-center justify-center flex-shrink-0 mt-1">2</div>
                        <div class="ml-4">
                            <h4 class="font-semibold text-lg">View Case Progress</h4>
                            <p class="text-gray-600">Monitor your case status, updates, and upcoming events in real-time.</p>
                        </div>
                    </li>
                    <li class="flex items-start">
                        <div class="bg-green-600 text-white rounded-full w-8 h-8 flex items-center justify-center flex-shrink-0 mt-1">3</div>
                        <div class="ml-4">
                            <h4 class="font-semibold text-lg">Share Documents</h4>
                            <p class="text-gray-600">Securely upload and access case-related documents anytime.</p>
                        </div>
                    </li>
                    <li class="flex items-start">
                        <div class="bg-green-600 text-white rounded-full w-8 h-8 flex items-center justify-center flex-shrink-0 mt-1">4</div>
                        <div class="ml-4">
                            <h4 class="font-semibold text-lg">Communicate & Pay</h4>
                            <p class="text-gray-600">Message your advocate directly and handle payments through the platform.</p>
                        </div>
                    </li>
                </ul>
                
                <?php if (!$isLoggedIn): ?>
                <div class="mt-8 text-center">
                    <a href="auth/register.php?type=client" class="bg-green-600 text-white hover:bg-green-700 font-semibold py-2 px-6 rounded-lg transition duration-300">
                        Register as Client
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Dashboard Preview Section -->
<section class="py-16 bg-gray-50 hidden">
    <div class="container mx-auto px-4">
        <div class="text-center mb-16">
            <h2 class="text-3xl font-bold text-gray-800 mb-4">Powerful Dashboard Interface</h2>
            <p class="text-xl text-gray-600 max-w-3xl mx-auto">Intuitive dashboards designed specifically for advocates and clients.</p>
        </div>
        
        <div class="flex flex-col md:flex-row space-y-8 md:space-y-0 md:space-x-8">
            <div class="md:w-1/2">
                <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                    <div class="bg-blue-800 text-white py-3 px-4">
                        <h3 class="font-semibold">Advocate Dashboard</h3>
                    </div>
                    <img src="https://images.unsplash.com/photo-1542744173-8e7e53415bb0?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1470&q=80" alt="Advocate Dashboard" class="w-full">
                    <div class="p-4">
                        <p class="text-gray-600">Comprehensive view of cases, appointments, client communications, and billing in one place.</p>
                    </div>
                </div>
            </div>
            
            <div class="md:w-1/2">
                <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                    <div class="bg-green-700 text-white py-3 px-4">
                        <h3 class="font-semibold">Client Dashboard</h3>
                    </div>
                    <img src="https://images.unsplash.com/photo-1551288049-bebda4e38f71?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1200&q=80" alt="Advocate Dashboard" class="w-full">
                    <div class="p-4">
                        <p class="text-gray-600">Easy access to case status, document repository, advocate communications, and payment history.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- testimonial -->

<?php
include 'includes/testimonial.php';
?>

<!-- Pricing Section -->
<section class="py-16 bg-gray-50 hidden">
    <div class="container mx-auto px-4">
        <div class="text-center mb-16">
            <h2 class="text-3xl font-bold text-gray-800 mb-4">Simple, Transparent Pricing</h2>
            <p class="text-xl text-gray-600 max-w-3xl mx-auto">Choose the plan that fits your practice needs.</p>
        </div>
        
        <div class="flex flex-col lg:flex-row space-y-8 lg:space-y-0 lg:space-x-8 justify-center">
            <!-- Basic Plan -->
            <div class="bg-white rounded-lg shadow-lg overflow-hidden lg:w-1/3">
                <div class="p-8 border-b">
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">Basic</h3>
                    <p class="text-gray-600 mb-6">For solo practitioners</p>
                    <div class="flex items-end mb-6">
                        <span class="text-4xl font-bold text-gray-800">$29</span>
                        <span class="text-gray-600 ml-2">/month</span>
                    </div>
                    <a href="auth/register.php?plan=basic" class="block text-center bg-blue-600 text-white hover:bg-blue-700 font-semibold py-2 px-6 rounded-lg transition duration-300">
                        Get Started
                    </a>
                </div>
                <div class="p-8">
                    <ul class="space-y-4">
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-500 mt-1 mr-3"></i>
                            <span>Up to 20 active cases</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-500 mt-1 mr-3"></i>
                            <span>5GB document storage</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-500 mt-1 mr-3"></i>
                            <span>Client portal access</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-500 mt-1 mr-3"></i>
                            <span>Basic reporting</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-500 mt-1 mr-3"></i>
                            <span>Email support</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Professional Plan -->
            <div class="bg-white rounded-lg shadow-lg overflow-hidden lg:w-1/3 relative">
                <div class="absolute top-0 right-0 bg-blue-600 text-white text-xs font-bold px-3 py-1 rounded-bl-lg">
                    MOST POPULAR
                </div>
                <div class="p-8 border-b bg-blue-50">
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">Professional</h3>
                    <p class="text-gray-600 mb-6">For small law firms</p>
                    <div class="flex items-end mb-6">
                        <span class="text-4xl font-bold text-gray-800">$79</span>
                        <span class="text-gray-600 ml-2">/month</span>
                    </div>
                    <a href="auth/register.php?plan=professional" class="block text-center bg-blue-600 text-white hover:bg-blue-700 font-semibold py-2 px-6 rounded-lg transition duration-300">
                        Get Started
                    </a>
                </div>
                <div class="p-8">
                    <ul class="space-y-4">
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-500 mt-1 mr-3"></i>
                            <span>Up to 100 active cases</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-500 mt-1 mr-3"></i>
                            <span>25GB document storage</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-500 mt-1 mr-3"></i>
                            <span>Advanced client portal</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-500 mt-1 mr-3"></i>
                            <span>Comprehensive reporting</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-500 mt-1 mr-3"></i>
                            <span>Priority email & phone support</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-500 mt-1 mr-3"></i>
                            <span>Team collaboration tools</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Enterprise Plan -->
            <div class="bg-white rounded-lg shadow-lg overflow-hidden lg:w-1/3">
                <div class="p-8 border-b">
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">Enterprise</h3>
                    <p class="text-gray-600 mb-6">For large law firms</p>
                    <div class="flex items-end mb-6">
                        <span class="text-4xl font-bold text-gray-800">$199</span>
                        <span class="text-gray-600 ml-2">/month</span>
                    </div>
                    <a href="auth/register.php?plan=enterprise" class="block text-center bg-blue-600 text-white hover:bg-blue-700 font-semibold py-2 px-6 rounded-lg transition duration-300">
                        Get Started
                    </a>
                </div>
                <div class="p-8">
                    <ul class="space-y-4">
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-500 mt-1 mr-3"></i>
                            <span>Unlimited active cases</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-500 mt-1 mr-3"></i>
                            <span>100GB document storage</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-500 mt-1 mr-3"></i>
                            <span>Premium client portal</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-500 mt-1 mr-3"></i>
                            <span>Advanced analytics & reporting</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-500 mt-1 mr-3"></i>
                            <span>24/7 priority support</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-500 mt-1 mr-3"></i>
                            <span>Advanced team collaboration</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-500 mt-1 mr-3"></i>
                            <span>Custom integrations</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FAQ Section -->
<?php
include 'includes/faq.php';
?>
<!-- CTA Section -->
<section class="py-20 bg-gradient-to-r from-blue-800 to-blue-600 text-white">
    <div class="container mx-auto px-4 text-center">
        <h2 class="text-3xl md:text-4xl font-bold mb-6">Ready to Transform Your Legal Practice?</h2>
        <p class="text-xl mb-8 max-w-3xl mx-auto">Join thousands of legal professionals who have streamlined their practice with our comprehensive management system.</p>
        
        <?php if (!$isLoggedIn): ?>
        <div class="flex flex-col sm:flex-row justify-center space-y-4 sm:space-y-0 sm:space-x-4">
            <a href="auth/register.php" class="bg-white text-blue-800 hover:bg-blue-100 font-semibold py-3 px-8 rounded-lg shadow-md transition duration-300 text-lg">
                Start Free Trial
            </a>
            <a href="contact.php" class="bg-transparent hover:bg-blue-700 text-white border-2 border-white font-semibold py-3 px-8 rounded-lg transition duration-300 text-lg">
                Contact Sales
            </a>
        </div>
        <?php else: ?>
        <a href="<?php echo $_SESSION['user_type']; ?>/index.php" class="bg-white text-blue-800 hover:bg-blue-100 font-semibold py-3 px-8 rounded-lg shadow-md transition duration-300 text-lg inline-block">
            Go to Dashboard
        </a>
        <?php endif; ?>
    </div>
</section>

<!-- Stats Section -->
<section class="py-16 bg-white">
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
            <div>
                <div class="text-4xl font-bold text-blue-600 mb-2">5,000+</div>
                <p class="text-gray-600">Active Users</p>
            </div>
            <div>
                <div class="text-4xl font-bold text-blue-600 mb-2">50,000+</div>
                <p class="text-gray-600">Cases Managed</p>
            </div>
            <div>
                <div class="text-4xl font-bold text-blue-600 mb-2">99.9%</div>
                <p class="text-gray-600">Uptime</p>
            </div>
            <div>
                <div class="text-4xl font-bold text-blue-600 mb-2">24/7</div>
                <p class="text-gray-600">Customer Support</p>
            </div>
        </div>
    </div>
</section>

<!-- Partners Section -->
<section class="py-16 bg-gray-50">
    <div class="container mx-auto px-4">
        <div class="text-center mb-12">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Trusted by Leading Law Firms</h2>
        </div>
        <div class="flex flex-wrap justify-center items-center gap-8 md:gap-16">
            <img src="data:image/jpeg;base64,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" alt="Bank of Kigali" class="h-12 grayscale opacity-70 hover:grayscale-0 hover:opacity-100 transition duration-300">
            <img src="https://www.rssb.rw/_next/image?url=%2Fassets%2Frssb-full-logo.png&w=256&q=75" alt="Rwanda Social Security Board" class="h-12 grayscale opacity-70 hover:grayscale-0 hover:opacity-100 transition duration-300">
            <img src="https://www.mtn.co.rw/wp-content/uploads/2023/07/mtn-logo-nav-new-scaled-1-2048x1062.webp" alt="MTN Rwanda" class="h-12 grayscale opacity-70 hover:grayscale-0 hover:opacity-100 transition duration-300">
            <img src="https://www.rwandair.com/dist/phoenix/V1.0/img/logorw.png" alt="RwandAir" class="h-12 grayscale opacity-70 hover:grayscale-0 hover:opacity-100 transition duration-300">
            <img src="https://www.bralirwa.co.rw/frontend/assets/img/brlogogreen.jpeg" alt="Bralirwa" class="h-12 grayscale opacity-70 hover:grayscale-0 hover:opacity-100 transition duration-300">
        </div>


    </div>
</section>

<!-- Blog Preview Section -->
<section class="py-16 bg-white">
    <div class="container mx-auto px-4">
        <div class="flex justify-between items-center mb-12">
            <h2 class="text-3xl font-bold text-gray-800">Latest Resources</h2>
            <a href="blog.php" class="text-blue-600 hover:text-blue-800 font-semibold">View All Articles <i class="fas fa-arrow-right ml-2"></i></a>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <img src="https://images.unsplash.com/photo-1589391886645-d51941baf7fb?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1470&q=80" alt="Blog Post" class="w-full h-48 object-cover">
            <div class="p-6">
                    <div class="text-sm text-blue-600 mb-2">Legal Technology</div>
                    <h3 class="text-xl font-semibold mb-3">How Technology is Reshaping Legal Practice in 2023</h3>
                    <p class="text-gray-600 mb-4">Explore how modern legal tech is transforming traditional law practices and improving client service.</p>
                    <a href="blog/post.php?id=1" class="text-blue-600 hover:text-blue-800 font-medium">Read More <i class="fas fa-arrow-right ml-1"></i></a>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <img src="https://images.unsplash.com/photo-1521791136064-7986c2920216?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1469&q=80" alt="Blog Post" class="w-full h-48 object-cover">                <div class="p-6">
                    <div class="text-sm text-blue-600 mb-2">Client Management</div>
                    <h3 class="text-xl font-semibold mb-3">5 Ways to Improve Client Communication in Legal Cases</h3>
                    <p class="text-gray-600 mb-4">Learn effective strategies to enhance communication with clients and build stronger relationships.</p>
                    <a href="blog/post.php?id=2" class="text-blue-600 hover:text-blue-800 font-medium">Read More <i class="fas fa-arrow-right ml-1"></i></a>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <img src="https://images.unsplash.com/photo-1568992687947-868a62a9f521?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1632&q=80" alt="Blog Post" class="w-full h-48 object-cover">
            <div class="p-6">
                    <div class="text-sm text-blue-600 mb-2">Practice Management</div>
                    <h3 class="text-xl font-semibold mb-3">Streamlining Document Management for Legal Professionals</h3>
                    <p class="text-gray-600 mb-4">Discover best practices for organizing and managing legal documents efficiently.</p>
                    <a href="blog/post.php?id=3" class="text-blue-600 hover:text-blue-800 font-medium">Read More <i class="fas fa-arrow-right ml-1"></i></a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Newsletter Section -->
<section class="py-16 bg-gray-50">
    <div class="container mx-auto px-4">
        <div class="max-w-3xl mx-auto text-center">
            <h2 class="text-3xl font-bold text-gray-800 mb-4">Stay Updated</h2>
            <p class="text-xl text-gray-600 mb-8">Subscribe to our newsletter for the latest updates, tips, and industry insights.</p>
            
            <form action="subscribe.php" method="POST" class="flex flex-col sm:flex-row gap-4 justify-center">
                <input type="email" name="email" placeholder="Your email address" required class="px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 flex-grow max-w-md">
                <button type="submit" class="bg-blue-600 text-white hover:bg-blue-700 font-semibold py-3 px-6 rounded-lg transition duration-300">
                    Subscribe
                </button>
            </form>
            
            <p class="text-sm text-gray-500 mt-4">We respect your privacy. Unsubscribe at any time.</p>
        </div>
    </div>
</section>

<?php
// Include footer
include 'includes/footer.php';
?>