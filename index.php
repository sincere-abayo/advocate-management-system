<?php
// Start session
session_start();

// If user is already logged in, redirect to appropriate dashboard
if(isset($_SESSION['user_id'])) {
    if($_SESSION['role'] == 'advocate') {
        header("Location: advocate/advocate-dashboard.php");
    } else if($_SESSION['role'] == 'client') {
        header("Location: client/client-dashboard.php");
    } else {
        header("Location: index.php");
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LegalEase - Client Portal for Legal Case Management</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            scroll-behavior: smooth;
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
        }
        
        .hero-pattern {
            background-color: #f9fafb;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='100' height='100' viewBox='0 0 100 100'%3E%3Cg fill-rule='evenodd'%3E%3Cg fill='%234f46e5' fill-opacity='0.05'%3E%3Cpath opacity='.5' d='M96 95h4v1h-4v4h-1v-4h-9v4h-1v-4h-9v4h-1v-4h-9v4h-1v-4h-9v4h-1v-4h-9v4h-1v-4h-9v4h-1v-4h-9v4h-1v-4h-9v4h-1v-4H0v-1h15v-9H0v-1h15v-9H0v-1h15v-9H0v-1h15v-9H0v-1h15v-9H0v-1h15v-9H0v-1h15v-9H0v-1h15v-9H0v-1h15V0h1v15h9V0h1v15h9V0h1v15h9V0h1v15h9V0h1v15h9V0h1v15h9V0h1v15h9V0h1v15h9V0h1v15h4v1h-4v9h4v1h-4v9h4v1h-4v9h4v1h-4v9h4v1h-4v9h4v1h-4v9h4v1h-4v9h4v1h-4v9zm-1 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-9-10h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm9-10v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-9-10h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm9-10v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-9-10h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9z'/%3E%3Cpath d='M6 5V0H5v5H0v1h5v94h1V6h94V5H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        
        .feature-card {
            transition: all 0.3s ease;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        .testimonial-card {
            transition: all 0.3s ease;
        }
        
        .testimonial-card:hover {
            transform: scale(1.03);
        }
        
        .step-card {
            transition: all 0.3s ease;
        }
        
        .step-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .cta-gradient {
            background: linear-gradient(135deg, #4338ca 0%, #6d28d9 100%);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="bg-white shadow-md fixed w-full z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <a href="index.php" class="flex items-center">
                            <i class="fas fa-balance-scale text-indigo-600 text-2xl mr-2"></i>
                            <span class="text-xl font-bold text-gray-800">LegalEase</span>
                        </a>
                    </div>
                    <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                        <a href="#benefits" class="border-transparent text-gray-600 hover:text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Benefits
                        </a>
                        <a href="#features" class="border-transparent text-gray-600 hover:text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Features
                        </a>
                        <a href="#how-it-works" class="border-transparent text-gray-600 hover:text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            How It Works
                        </a>
                        <a href="#testimonials" class="border-transparent text-gray-600 hover:text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Testimonials
                        </a>
                        <a href="#faq" class="border-transparent text-gray-600 hover:text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            FAQ
                        </a>
                    </div>
                </div>
                <div class="hidden sm:ml-6 sm:flex sm:items-center sm:space-x-4">
                    <a href="login.php" class="text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium">
                        Sign In
                    </a>
                    <a href="register.php?type=client" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        Client Registration
                    </a>
                </div>
                <div class="-mr-2 flex items-center sm:hidden">
                    <button type="button" class="mobile-menu-button inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500" aria-expanded="false">
                        <span class="sr-only">Open main menu</span>
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile menu, show/hide based on menu state. -->
        <div class="mobile-menu hidden sm:hidden">
            <div class="px-2 pt-2 pb-3 space-y-1">
                <a href="#benefits" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 block px-3 py-2 rounded-md text-base font-medium">
                    Benefits
                </a>
                <a href="#features" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 block px-3 py-2 rounded-md text-base font-medium">
                    Features
                </a>
                <a href="#how-it-works" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 block px-3 py-2 rounded-md text-base font-medium">
                    How It Works
                </a>
                <a href="#testimonials" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 block px-3 py-2 rounded-md text-base font-medium">
                    Testimonials
                </a>
                <a href="#faq" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 block px-3 py-2 rounded-md text-base font-medium">
                    FAQ
                </a>
            </div>
            <div class="pt-4 pb-3 border-t border-gray-200">
                <div class="flex items-center px-4 space-x-3">
                    <a href="login.php" class="text-gray-600 hover:text-gray-900 block px-3 py-2 rounded-md text-base font-medium">
                        Sign In
                    </a>
                    <a href="register.php?type=client" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md text-base font-medium">
                        Client Registration
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-pattern pt-32 pb-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="lg:flex lg:items-center lg:justify-between">
                <div class="lg:w-1/2" data-aos="fade-right" data-aos-duration="1000">
                    <h1 class="text-4xl font-extrabold text-gray-900 sm:text-5xl md:text-6xl">
                        <span class="block">Stay Connected</span>
                        <span class="block text-indigo-600">With Your Legal Case</span>
                    </h1>
                    <p class="mt-3 max-w-md mx-auto text-lg text-gray-500 sm:text-xl md:mt-5 md:max-w-3xl">
                        A secure client portal that gives you 24/7 access to your case information, documents, and direct communication with your advocate.
                    </p>
                    <div class="mt-8 flex flex-col sm:flex-row sm:space-x-4">
                        <a href="register.php?type=client" class="inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                            Register as a Client
                            <i class="fas fa-user-plus ml-2"></i>
                        </a>
                        <a href="login.php" class="mt-3 sm:mt-0 inline-flex items-center justify-center px-5 py-3 border border-gray-300 text-base font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Sign In
                            <i class="fas fa-sign-in-alt ml-2"></i>
                        </a>
                    </div>
                </div>
                <div class="mt-10 lg:mt-0 lg:w-1/2" data-aos="fade-left" data-aos-duration="1000">
                    <img class="rounded-lg shadow-xl" src="assets/img/client-portal.jpg" alt="Client Portal Preview" onerror="this.src='https://via.placeholder.com/600x400?text=Client+Portal+Preview'">
                </div>
            </div>
        </div>
    </section>

    <!-- Benefits Section -->
    <section id="benefits" class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-extrabold text-gray-900 sm:text-4xl" data-aos="fade-up">
                    Benefits for Clients
                </h2>
                <p class="mt-4 text-lg text-gray-600" data-aos="fade-up" data-aos-delay="100">
                    Why our clients love using our platform
                </p>
            </div>

            <div class="grid grid-cols-1 gap-8 md:grid-cols-3">
                <!-- Benefit 1 -->
                <div class="bg-gray-50 rounded-lg p-8 shadow-sm feature-card" data-aos="fade-up" data-aos-delay="150">
                    <div class="w-12 h-12 rounded-md bg-indigo-100 text-indigo-600 flex items-center justify-center mb-4">
                        <i class="fas fa-clock text-xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">24/7 Access</h3>
                    <p class="text-gray-600">
                        Access your case information anytime, anywhere. No more waiting for office hours or phone calls to get updates on your case.
                    </p>
                </div>

                <!-- Benefit 2 -->
                <div class="bg-gray-50 rounded-lg p-8 shadow-sm feature-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="w-12 h-12 rounded-md bg-indigo-100 text-indigo-600 flex items-center justify-center mb-4">
                        <i class="fas fa-shield-alt text-xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Secure Communication</h3>
                    <p class="text-gray-600">
                        All communications and document sharing happen in a secure environment, protecting your confidential information.
                    </p>
                </div>

                <!-- Benefit 3 -->
                <div class="bg-gray-50 rounded-lg p-8 shadow-sm feature-card" data-aos="fade-up" data-aos-delay="250">
                    <div class="w-12 h-12 rounded-md bg-indigo-100 text-indigo-600 flex items-center justify-center mb-4">
                        <i class="fas fa-file-alt text-xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Document Access</h3>
                    <p class="text-gray-600">
                        View and download all your case-related documents in one place. No more searching through emails or paper files.
                    </p>
                </div>

                <!-- Benefit 4 -->
                <div class="bg-gray-50 rounded-lg p-8 shadow-sm feature-card" data-aos="fade-up" data-aos-delay="300">
                    <div class="w-12 h-12 rounded-md bg-indigo-100 text-indigo-600 flex items-center justify-center mb-4">
                        <i class="fas fa-calendar-alt text-xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Calendar & Reminders</h3>
                    <p class="text-gray-600">
                        Never miss important dates with our integrated calendar. Get reminders for court dates, meetings, and deadlines.
                    </p>
                </div>

                <!-- Benefit 5 -->
                <div class="bg-gray-50 rounded-lg p-8 shadow-sm feature-card" data-aos="fade-up" data-aos-delay="350">
                    <div class="w-12 h-12 rounded-md bg-indigo-100 text-indigo-600 flex items-center justify-center mb-4">
                        <i class="fas fa-comments text-xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Direct Messaging</h3>
                    <p class="text-gray-600">
                        Communicate directly with your advocate through our secure messaging system. Ask questions and get updates quickly.
                    </p>
                </div>

                <!-- Benefit 6 -->
                <div class="bg-gray-50 rounded-lg p-8 shadow-sm feature-card" data-aos="fade-up" data-aos-delay="400">
                    <div class="w-12 h-12 rounded-md bg-indigo-100 text-indigo-600 flex items-center justify-center mb-4">
                        <i class="fas fa-chart-line text-xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Case Progress Tracking</h3>
                    <p class="text-gray-600">
                        See real-time updates on your case status and progress. Stay informed about every development in your legal matter.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-16 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-extrabold text-gray-900 sm:text-4xl" data-aos="fade-up">
                    Client Portal Features
                </h2>
                <p class="mt-4 text-lg text-gray-600" data-aos="fade-up" data-aos-delay="100">
                    Everything you need to stay connected with your legal case
                </p>
            </div>

            <div class="mt-16">
                <!-- Feature 1 -->
                <div class="lg:flex lg:items-center lg:space-x-8 mb-20" data-aos="fade-up">
                    <div class="lg:w-1/2 mb-8 lg:mb-0">
                        <img src="assets/img/case-dashboard.jpg" alt="Case Dashboard" class="rounded-lg shadow-lg" onerror="this.src='https://via.placeholder.com/600x400?text=Case+Dashboard'">
                    </div>
                    <div class="lg:w-1/2">
                        <h3 class="text-2xl font-bold text-gray-900 mb-4">Case Dashboard</h3>
                        <p class="text-lg text-gray-600 mb-6">
                            Your personalized dashboard gives you a complete overview of your case status, upcoming events, recent activities, and important documents.
                        </p>
                        <ul class="space-y-4">
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mt-1 mr-3"></i>
                                <span class="text-gray-600">Real-time case status updates</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mt-1 mr-3"></i>
                                <span class="text-gray-600">Timeline of case activities and milestones</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mt-1 mr-3"></i>
                                <span class="text-gray-600">Quick access to important case information</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Feature 2 -->
                <div class="lg:flex lg:items-center lg:space-x-8 mb-20 flex-row-reverse" data-aos="fade-up">
                    <div class="lg:w-1/2 mb-8 lg:mb-0">
                        <img src="assets/img/document-access.jpg" alt="Document Access" class="rounded-lg shadow-lg" onerror="this.src='https://via.placeholder.com/600x400?text=Document+Access'">
                    </div>
                    <div class="lg:w-1/2">
                        <h3 class="text-2xl font-bold text-gray-900 mb-4">Secure Document Access</h3>
                        <p class="text-lg text-gray-600 mb-6">
                            Access all your case-related documents in one secure location. View, download, and upload documents as needed.
                        </p>
                        <ul class="space-y-4">
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mt-1 mr-3"></i>
                                <span class="text-gray-600">Organized document categories for easy navigation</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mt-1 mr-3"></i>
                                <span class="text-gray-600">Secure document sharing with your advocate</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mt-1 mr-3"></i>
                                <span class="text-gray-600">Document version history and tracking</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Feature 3 -->
                <div class="lg:flex lg:items-center lg:space-x-8 mb-20" data-aos="fade-up">
                    <div class="lg:w-1/2 mb-8 lg:mb-0">
                        <img src="assets/img/messaging.jpg" alt="Secure Messaging" class="rounded-lg shadow-lg" onerror="this.src='https://via.placeholder.com/600x400?text=Secure+Messaging'">
                    </div>
                    <div class="lg:w-1/2">
                        <h3 class="text-2xl font-bold text-gray-900 mb-4">Secure Messaging</h3>
                        <p class="text-lg text-gray-600 mb-6">
                            Communicate directly with your advocate through our encrypted messaging system. All conversations are organized by case and topic.
                        </p>
                        <ul class="space-y-4">
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mt-1 mr-3"></i>
                                <span class="text-gray-600">End-to-end encrypted communications</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mt-1 mr-3"></i>
                                <span class="text-gray-600">File attachment capabilities</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mt-1 mr-3"></i>
                                <span class="text-gray-600">Message notifications and read receipts</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Feature 4 -->
                <div class="lg:flex lg:items-center lg:space-x-8 flex-row-reverse" data-aos="fade-up">
                    <div class="lg:w-1/2 mb-8 lg:mb-0">
                        <img src="assets/img/calendar.jpg" alt="Calendar & Events" class="rounded-lg shadow-lg" onerror="this.src='https://via.placeholder.com/600x400?text=Calendar+Events'">
                    </div>
                    <div class="lg:w-1/2">
                        <h3 class="text-2xl font-bold text-gray-900 mb-4">Calendar & Events</h3>
                        <p class="text-lg text-gray-600 mb-6">
                            Keep track of all important dates related to your case. Receive reminders for court appearances, meetings, and document deadlines.
                        </p>
                        <ul class="space-y-4">
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mt-1 mr-3"></i>
                                <span class="text-gray-600">Integrated calendar with all case events</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mt-1 mr-3"></i>
                                <span class="text-gray-600">Email and SMS reminders for important dates</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-check-circle text-green-500 mt-1 mr-3"></i>
                                <span class="text-gray-600">Sync with your personal calendar (Google, Outlook)</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section id="how-it-works" class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-extrabold text-gray-900 sm:text-4xl" data-aos="fade-up">
                    How It Works for Clients
                </h2>
                <p class="mt-4 text-lg text-gray-600" data-aos="fade-up" data-aos-delay="100">
                    Getting started is simple and takes just a few minutes
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Step 1 -->
                <div class="bg-white rounded-lg shadow p-6 step-card" data-aos="fade-up" data-aos-delay="150">
                    <div class="w-12 h-12 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center mb-4 text-xl font-bold">
                        1
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Register Your Account</h3>
                    <p class="text-gray-600">
                        Create your client account with basic information. Your advocate will link your account to your case(s).
                    </p>
                    <a href="register.php?type=client" class="mt-4 inline-flex items-center text-indigo-600 hover:text-indigo-800">
                        Register Now
                        <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>

                <!-- Step 2 -->
                <div class="bg-white rounded-lg shadow p-6 step-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="w-12 h-12 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center mb-4 text-xl font-bold">
                        2
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Access Your Dashboard</h3>
                    <p class="text-gray-600">
                        Log in to view your personalized dashboard with all your case information, documents, and communications.
                    </p>
                    <a href="login.php" class="mt-4 inline-flex items-center text-indigo-600 hover:text-indigo-800">
                        Sign In
                        <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>

                <!-- Step 3 -->
                <div class="bg-white rounded-lg shadow p-6 step-card" data-aos="fade-up" data-aos-delay="250">
                    <div class="w-12 h-12 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center mb-4 text-xl font-bold">
                        3
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Stay Connected</h3>
                    <p class="text-gray-600">
                        Receive updates, communicate with your advocate, and access documents anytime from any device.
                    </p>
                    <a href="#features" class="mt-4 inline-flex items-center text-indigo-600 hover:text-indigo-800">
                        Learn More
                        <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section id="testimonials" class="py-16 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-extrabold text-gray-900 sm:text-4xl" data-aos="fade-up">
                    What Our Clients Say
                </h2>
                <p class="mt-4 text-lg text-gray-600" data-aos="fade-up" data-aos-delay="100">
                    Real experiences from clients using our platform
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Testimonial 1 -->
                <div class="bg-white rounded-lg shadow-lg p-6 testimonial-card" data-aos="fade-up" data-aos-delay="150">
                    <div class="flex items-center mb-4">
                        <div class="h-12 w-12 rounded-full overflow-hidden bg-gray-200 flex-shrink-0">
                            <img src="assets/img/testimonial-1.jpg" alt="Client" class="h-full w-full object-cover" onerror="this.src='https://via.placeholder.com/48?text=J'">
                        </div>
                        <div class="ml-4">
                            <h4 class="text-lg font-semibold text-gray-900">James Wilson</h4>
                            <p class="text-sm text-gray-600">Family Law Client</p>
                        </div>
                    </div>
                    <div class="text-yellow-400 mb-3">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                    <p class="text-gray-600 italic">
                        "During my divorce case, having 24/7 access to documents and updates was invaluable. I could check case progress anytime without calling the office, and the secure messaging feature made communication so much easier."
                    </p>
                </div>

                <!-- Testimonial 2 -->
                <div class="bg-white rounded-lg shadow-lg p-6 testimonial-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="flex items-center mb-4">
                        <div class="h-12 w-12 rounded-full overflow-hidden bg-gray-200 flex-shrink-0">
                            <img src="assets/img/testimonial-2.jpg" alt="Client" class="h-full w-full object-cover" onerror="this.src='https://via.placeholder.com/48?text=S'">
                        </div>
                        <div class="ml-4">
                            <h4 class="text-lg font-semibold text-gray-900">Sarah Johnson</h4>
                            <p class="text-sm text-gray-600">Personal Injury Client</p>
                        </div>
                    </div>
                    <div class="text-yellow-400 mb-3">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star-half-alt"></i>
                    </div>
                    <p class="text-gray-600 italic">
                        "After my accident, keeping track of medical records and insurance documents was overwhelming. This portal kept everything organized in one place, and I could easily share new documents with my advocate."
                    </p>
                </div>

                <!-- Testimonial 3 -->
                <div class="bg-white rounded-lg shadow-lg p-6 testimonial-card" data-aos="fade-up" data-aos-delay="250">
                    <div class="flex items-center mb-4">
                        <div class="h-12 w-12 rounded-full overflow-hidden bg-gray-200 flex-shrink-0">
                            <img src="assets/img/testimonial-3.jpg" alt="Client" class="h-full w-full object-cover" onerror="this.src='https://via.placeholder.com/48?text=M'">
                        </div>
                        <div class="ml-4">
                            <h4 class="text-lg font-semibold text-gray-900">Michael Chen</h4>
                            <p class="text-sm text-gray-600">Business Law Client</p>
                        </div>
                    </div>
                    <div class="text-yellow-400 mb-3">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                    <p class="text-gray-600 italic">
                        "As a business owner, I appreciate efficiency. The calendar feature with reminders for deadlines and meetings has been incredibly helpful, and I love being able to review documents outside of business hours."
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section id="faq" class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-extrabold text-gray-900 sm:text-4xl" data-aos="fade-up">
                    Frequently Asked Questions
                </h2>
                <p class="mt-4 text-lg text-gray-600" data-aos="fade-up" data-aos-delay="100">
                    Common questions from our clients
                </p>
            </div>

            <div class="max-w-3xl mx-auto divide-y divide-gray-200">
                <!-- FAQ Item 1 -->
                <div class="py-6" data-aos="fade-up" data-aos-delay="150">
                    <details class="group">
                        <summary class="flex justify-between items-center font-medium cursor-pointer list-none">
                            <span>How secure is my information in the client portal?</span>
                            <span class="transition group-open:rotate-180">
                                <i class="fas fa-chevron-down"></i>
                            </span>
                        </summary>
                        <p class="text-gray-600 mt-3 group-open:animate-fadeIn">
                            Your information is protected with bank-level security measures. We use encryption for all data, both in transit and at rest. Our platform complies with legal industry security standards, and access is strictly controlled through secure authentication methods.
                        </p>
                    </details>
                </div>

                <!-- FAQ Item 2 -->
                <div class="py-6" data-aos="fade-up" data-aos-delay="200">
                    <details class="group">
                        <summary class="flex justify-between items-center font-medium cursor-pointer list-none">
                            <span>How do I get access to the client portal?</span>
                            <span class="transition group-open:rotate-180">
                                <i class="fas fa-chevron-down"></i>
                            </span>
                        </summary>
                        <p class="text-gray-600 mt-3 group-open:animate-fadeIn">
                            You can register for an account directly on our website. Once registered, your advocate will link your account to your case(s). You'll receive an email notification when your case information is available in the portal.
                        </p>
                    </details>
                </div>

                <!-- FAQ Item 3 -->
                <div class="py-6" data-aos="fade-up" data-aos-delay="250">
                    <details class="group">
                        <summary class="flex justify-between items-center font-medium cursor-pointer list-none">
                            <span>Can I upload documents to share with my advocate?</span>
                            <span class="transition group-open:rotate-180">
                                <i class="fas fa-chevron-down"></i>
                            </span>
                        </summary>
                        <p class="text-gray-600 mt-3 group-open:animate-fadeIn">
                            Yes, you can upload documents directly through the portal. Your advocate will be notified when new documents are uploaded. This is a secure way to share sensitive information without using email.
                        </p>
                    </details>
                </div>

                <!-- FAQ Item 4 -->
                <div class="py-6" data-aos="fade-up" data-aos-delay="300">
                    <details class="group">
                        <summary class="flex justify-between items-center font-medium cursor-pointer list-none">
                            <span>Will I be notified of updates to my case?</span>
                            <span class="transition group-open:rotate-180">
                                <i class="fas fa-chevron-down"></i>
                            </span>
                        </summary>
                        <p class="text-gray-600 mt-3 group-open:animate-fadeIn">
                            Yes, you'll receive email notifications when there are updates to your case, new documents are shared, or when you receive messages from your advocate. You can customize your notification preferences in your account settings.
                        </p>
                    </details>
                </div>

                <!-- FAQ Item 5 -->
                <div class="py-6" data-aos="fade-up" data-aos-delay="350">
                    <details class="group">
                        <summary class="flex justify-between items-center font-medium cursor-pointer list-none">
                            <span>Can I access the portal from my mobile device?</span>
                            <span class="transition group-open:rotate-180">
                                <i class="fas fa-chevron-down"></i>
                            </span>
                        </summary>
                        <p class="text-gray-600 mt-3 group-open:animate-fadeIn">
                            Yes, our client portal is fully responsive and works on all devices including smartphones and tablets. You can access your case information anytime, anywhere, from any device with an internet connection.
                        </p>
                    </details>
                </div>

                <!-- FAQ Item 6 -->
                <div class="py-6" data-aos="fade-up" data-aos-delay="400">
                    <details class="group">
                        <summary class="flex justify-between items-center font-medium cursor-pointer list-none">
                            <span>Is there a cost to use the client portal?</span>
                            <span class="transition group-open:rotate-180">
                                <i class="fas fa-chevron-down"></i>
                            </span>
                        </summary>
                        <p class="text-gray-600 mt-3 group-open:animate-fadeIn">
                            No, access to the client portal is provided as a complimentary service to all clients. There are no additional fees for using the portal or its features.
                        </p>
                    </details>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-20 cta-gradient">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl font-extrabold text-white sm:text-4xl" data-aos="fade-up">
                Ready to Stay Connected with Your Case?
            </h2>
            <p class="mt-4 text-xl text-indigo-100" data-aos="fade-up" data-aos-delay="100">
                Register today to access your case information securely.
            </p>
            <div class="mt-8 flex justify-center flex-col sm:flex-row sm:space-x-4 space-y-4 sm:space-y-0" data-aos="fade-up" data-aos-delay="200">
            <a href="register.php?type=client" class="inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-indigo-700 bg-white hover:bg-indigo-50">
                    Register as a Client
                    <i class="fas fa-user-plus ml-2"></i>
                </a>
                <a href="login.php" class="inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                    Sign In
                    <i class="fas fa-sign-in-alt ml-2"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <div class="flex items-center">
                        <i class="fas fa-balance-scale text-indigo-400 text-2xl mr-2"></i>
                        <span class="text-xl font-bold">LegalEase</span>
                    </div>
                    <p class="mt-4 text-gray-400">
                        Connecting clients with their legal cases through secure, accessible technology.
                    </p>
                    <div class="mt-6 flex space-x-4">
                        <a href="#" class="text-gray-400 hover:text-white">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-white">
                            <i class="fab fa-instagram"></i>
                        </a>
                    </div>
                </div>
                
                <div>
                    <h3 class="text-lg font-semibold mb-4">Quick Links</h3>
                    <ul class="space-y-2">
                        <li><a href="#benefits" class="text-gray-400 hover:text-white">Benefits</a></li>
                        <li><a href="#features" class="text-gray-400 hover:text-white">Features</a></li>
                        <li><a href="#how-it-works" class="text-gray-400 hover:text-white">How It Works</a></li>
                        <li><a href="#testimonials" class="text-gray-400 hover:text-white">Testimonials</a></li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="text-lg font-semibold mb-4">Support</h3>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-gray-400 hover:text-white">Help Center</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Client Resources</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Contact Us</a></li>
                        <li><a href="#faq" class="text-gray-400 hover:text-white">FAQ</a></li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="text-lg font-semibold mb-4">Legal</h3>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-gray-400 hover:text-white">Privacy Policy</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Terms of Service</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Cookie Policy</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Data Protection</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="mt-12 pt-8 border-t border-gray-700 text-center text-gray-400">
                <p>&copy; <?php echo date('Y'); ?> LegalEase. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- AOS Animation Library -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Initialize AOS animation
        AOS.init({
            once: true,
            duration: 800,
        });
        
        // Mobile menu toggle
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuButton = document.querySelector('.mobile-menu-button');
            const mobileMenu = document.querySelector('.mobile-menu');
            
            mobileMenuButton.addEventListener('click', function() {
                mobileMenu.classList.toggle('hidden');
            });
            
            // Close mobile menu when clicking on a link
            const mobileLinks = document.querySelectorAll('.mobile-menu a');
            mobileLinks.forEach(link => {
                link.addEventListener('click', function() {
                    mobileMenu.classList.add('hidden');
                });
            });
        });
    </script>
</body>
</html>
