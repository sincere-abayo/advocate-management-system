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
    <title>LegalEase - Advanced Legal Case Management System</title>
    
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
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='100' height='100' viewBox='0 0 100 100'%3E%3Cg fill-rule='evenodd'%3E%3Cg fill='%234f46e5' fill-opacity='0.05'%3E%3Cpath opacity='.5' d='M96 95h4v1h-4v4h-1v-4h-9v4h-1v-4h-9v4h-1v-4h-9v4h-1v-4h-9v4h-1v-4h-9v4h-1v-4h-9v4h-1v-4h-9v4h-1v-4h-9v4h-1v-4H0v-1h15v-9H0v-1h15v-9H0v-1h15v-9H0v-1h15v-9H0v-1h15v-9H0v-1h15v-9H0v-1h15v-9H0v-1h15v-9H0v-1h15V0h1v15h9V0h1v15h9V0h1v15h9V0h1v15h9V0h1v15h9V0h1v15h9V0h1v15h9V0h1v15h9V0h1v15h4v1h-4v9h4v1h-4v9h4v1h-4v9h4v1h-4v9h4v1h-4v9h4v1h-4v9h4v1h-4v9h4v1h-4v9zm-1 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-9-10h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm9-10v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-9-10h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm9-10v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-9-10h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm9-10v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-9-10h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9z'/%3E%3Cpath d='M6 5V0H5v5H0v1h5v94h1V6h94V5H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
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
        
        .pricing-card {
            transition: all 0.3s ease;
        }
        
        .pricing-card:hover {
            transform: scale(1.05);
            z-index: 10;
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
                        <a href="#features" class="border-transparent text-gray-600 hover:text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Features
                        </a>
                        <a href="#how-it-works" class="border-transparent text-gray-600 hover:text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            How It Works
                        </a>
                        <a href="#testimonials" class="border-transparent text-gray-600 hover:text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Testimonials
                        </a>
                        <a href="#pricing" class="border-transparent text-gray-600 hover:text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Pricing
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
                    <a href="register.php" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                        Get Started
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
                <a href="#features" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 block px-3 py-2 rounded-md text-base font-medium">
                    Features
                </a>
                <a href="#how-it-works" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 block px-3 py-2 rounded-md text-base font-medium">
                    How It Works
                </a>
                <a href="#testimonials" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 block px-3 py-2 rounded-md text-base font-medium">
                    Testimonials
                </a>
                <a href="#pricing" class="text-gray-600 hover:bg-gray-50 hover:text-gray-900 block px-3 py-2 rounded-md text-base font-medium">
                    Pricing
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
                    <a href="register.php" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md text-base font-medium">
                        Get Started
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
                        <span class="block">Streamline Your</span>
                        <span class="block text-indigo-600">Legal Practice</span>
                    </h1>
                    <p class="mt-3 max-w-md mx-auto text-lg text-gray-500 sm:text-xl md:mt-5 md:max-w-3xl">
                        Manage cases, clients, documents, and communications in one powerful platform. Designed for modern legal professionals.
                    </p>
                    <div class="mt-8 flex flex-col sm:flex-row sm:space-x-4">
                        <a href="register.php" class="inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                            Get Started Free
                            <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                        <a href="#how-it-works" class="mt-3 sm:mt-0 inline-flex items-center justify-center px-5 py-3 border border-gray-300 text-base font-medium rounded-md text-indigo-700 bg-white hover:bg-gray-50">
                            Learn More
                            <i class="fas fa-info-circle ml-2"></i>
                        </a>
                    </div>
                </div>
                <div class="mt-10 lg:mt-0 lg:w-1/2" data-aos="fade-left" data-aos-duration="1000">
                    <img class="rounded-lg shadow-xl" src="assets/img/dashboard-preview.png" alt="Dashboard Preview" onerror="this.src='https://via.placeholder.com/600x400?text=Dashboard+Preview'">
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="bg-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4 md:gap-8">
                <div class="bg-gray-50 rounded-lg p-6 text-center" data-aos="zoom-in" data-aos-delay="100">
                    <div class="text-indigo-600 text-4xl font-bold mb-2">98%</div>
                    <div class="text-gray-600">Client Satisfaction</div>
                </div>
                <div class="bg-gray-50 rounded-lg p-6 text-center" data-aos="zoom-in" data-aos-delay="200">
                    <div class="text-indigo-600 text-4xl font-bold mb-2">30%</div>
                    <div class="text-gray-600">Time Saved</div>
                </div>
                <div class="bg-gray-50 rounded-lg p-6 text-center" data-aos="zoom-in" data-aos-delay="300">
                    <div class="text-indigo-600 text-4xl font-bold mb-2">5000+</div>
                    <div class="text-gray-600">Active Users</div>
                </div>
                <div class="bg-gray-50 rounded-lg p-6 text-center" data-aos="zoom-in" data-aos-delay="400">
                    <div class="text-indigo-600 text-4xl font-bold mb-2">24/7</div>
                    <div class="text-gray-600">Support Available</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-16 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-extrabold text-gray-900 sm:text-4xl" data-aos="fade-up">
                    Powerful Features for Legal Professionals
                </h2>
                <p class="mt-4 text-lg text-gray-600" data-aos="fade-up" data-aos-delay="100">
                    Everything you need to manage your legal practice efficiently and effectively.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-3">
                <!-- Feature 1 -->
                <div class="bg-white rounded-lg shadow-lg overflow-hidden feature-card" data-aos="fade-up" data-aos-delay="150">
                    <div class="p-6">
                        <div class="w-12 h-12 rounded-md bg-indigo-100 text-indigo-600 flex items-center justify-center mb-4">
                            <i class="fas fa-folder-open text-xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Case Management</h3>
                        <p class="text-gray-600">
                            Organize all your cases in one place. Track status, deadlines, and important details with ease.
                        </p>
                    </div>
                </div>

                <!-- Feature 2 -->
                <div class="bg-white rounded-lg shadow-lg overflow-hidden feature-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="p-6">
                        <div class="w-12 h-12 rounded-md bg-indigo-100 text-indigo-600 flex items-center justify-center mb-4">
                            <i class="fas fa-users text-xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Client Portal</h3>
                        <p class="text-gray-600">
                            Give clients secure access to their case information, documents, and communication history.
                        </p>
                    </div>
                </div>

                <!-- Feature 3 -->
                <div class="bg-white rounded-lg shadow-lg overflow-hidden feature-card" data-aos="fade-up" data-aos-delay="250">
                    <div class="p-6">
                        <div class="w-12 h-12 rounded-md bg-indigo-100 text-indigo-600 flex items-center justify-center mb-4">
                            <i class="fas fa-file-alt text-xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Document Management</h3>
                        <p class="text-gray-600">
                            Store, organize, and share legal documents securely. Never lose an important file again.
                        </p>
                    </div>
                </div>

                <!-- Feature 4 -->
                <div class="bg-white rounded-lg shadow-lg overflow-hidden feature-card" data-aos="fade-up" data-aos-delay="300">
                    <div class="p-6">
                        <div class="w-12 h-12 rounded-md bg-indigo-100 text-indigo-600 flex items-center justify-center mb-4">
                            <i class="fas fa-calendar-alt text-xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Calendar & Scheduling</h3>
                        <p class="text-gray-600">
                            Manage appointments, court dates, and deadlines. Sync with your favorite calendar apps.
                        </p>
                    </div>
                </div>

                <!-- Feature 5 -->
                <div class="bg-white rounded-lg shadow-lg overflow-hidden feature-card" data-aos="fade-up" data-aos-delay="350">
                    <div class="p-6">
                        <div class="w-12 h-12 rounded-md bg-indigo-100 text-indigo-600 flex items-center justify-center mb-4">
                            <i class="fas fa-comments text-xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Secure Messaging</h3>
                        <p class="text-gray-600">
                            Communicate with clients and team members securely within the platform. Keep all conversations organized.
                        </p>
                    </div>
                </div>

                <!-- Feature 6 -->
                <div class="bg-white rounded-lg shadow-lg overflow-hidden feature-card" data-aos="fade-up" data-aos-delay="400">
                    <div class="p-6">
                        <div class="w-12 h-12 rounded-md bg-indigo-100 text-indigo-600 flex items-center justify-center mb-4">
                            <i class="fas fa-tasks text-xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Task Management</h3>
                        <p class="text-gray-600">
                            Create, assign, and track tasks for your team. Ensure nothing falls through the cracks.
                        </p>
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
                    How It Works
                </h2>
                <p class="mt-4 text-lg text-gray-600" data-aos="fade-up" data-aos-delay="100">
                    Get started in minutes and transform your legal practice
                </p>
            </div>

            <div class="relative">
                <!-- Timeline Line -->
                <div class="hidden md:block absolute left-1/2 transform -translate-x-1/2 h-full w-1 bg-indigo-100"></div>
                
                <!-- Step 1 -->
                <div class="relative mb-12 md:mb-20" data-aos="fade-right">
                    <div class="md:flex md:items-center">
                        <div class="md:w-1/2 pr-8 md:text-right">
                            <h3 class="text-xl font-semibold text-gray-900 mb-2">Create Your Account</h3>
                            <p class="text-gray-600">
                                Sign up for free and set up your profile with your practice information.
                            </p>
                        </div>
                        <div class="hidden md:block absolute left-1/2 transform -translate-x-1/2 w-12 h-12 rounded-full bg-indigo-600 text-white flex items-center justify-center">
                            <span class="font-bold">1</span>
                        </div>
                        <div class="md:w-1/2 pl-8 mt-4 md:mt-0">
                            <img src="assets/img/signup-preview.png" alt="Sign Up" class="rounded-lg shadow-md" onerror="this.src='https://via.placeholder.com/400x250?text=Sign+Up+Preview'">
                        </div>
                    </div>
                </div>
                
                <!-- Step 2 -->
                <div class="relative mb-12 md:mb-20" data-aos="fade-left">
                    <div class="md:flex md:items-center">
                        <div class="md:w-1/2 pr-8 md:text-right order-last md:pl-8 md:pr-0">
                            <img src="assets/img/cases-preview.png" alt="Add Cases" class="rounded-lg shadow-md" onerror="this.src='https://via.placeholder.com/400x250?text=Add+Cases+Preview'">
                        </div>
                        <div class="hidden md:block absolute left-1/2 transform -translate-x-1/2 w-12 h-12 rounded-full bg-indigo-600 text-white flex items-center justify-center">
                            <span class="font-bold">2</span>
                        </div>
                        <div class="md:w-1/2 pl-8 md:pr-8 md:pl-0 md:text-right mt-4 md:mt-0 order-first">
                            <h3 class="text-xl font-semibold text-gray-900 mb-2">Add Your Cases</h3>
                            <p class="text-gray-600">
                                Import existing cases or create new ones. Add all relevant details and documents.
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Step 3 -->
                <div class="relative mb-12 md:mb-20" data-aos="fade-right">
                    <div class="md:flex md:items-center">
                        <div class="md:w-1/2 pr-8 md:text-right">
                            <h3 class="text-xl font-semibold text-gray-900 mb-2">Invite Clients</h3>
                            <p class="text-gray-600">
                                Add your clients to the platform so they can access their case information securely.
                            </p>
                        </div>
                        <div class="hidden md:block absolute left-1/2 transform -translate-x-1/2 w-12 h-12 rounded-full bg-indigo-600 text-white flex items-center justify-center">
                            <span class="font-bold">3</span>
                        </div>
                        <div class="md:w-1/2 pl-8 mt-4 md:mt-0">
                            <img src="assets/img/clients-preview.png" alt="Invite Clients" class="rounded-lg shadow-md" onerror="this.src='https://via.placeholder.com/400x250?text=Invite+Clients+Preview'">
                        </div>
                    </div>
                </div>
                
                <!-- Step 4 -->
                <div class="relative" data-aos="fade-left">
                    <div class="md:flex md:items-center">
                        <div class="md:w-1/2 pr-8 md:text-right order-last md:pl-8 md:pr-0">
                            <img src="assets/img/manage-preview.png" alt="Manage Everything" class="rounded-lg shadow-md" onerror="this.src='https://via.placeholder.com/400x250?text=Manage+Everything+Preview'">
                        </div>
                        <div class="hidden md:block absolute left-1/2 transform -translate-x-1/2 w-12 h-12 rounded-full bg-indigo-600 text-white flex items-center justify-center">
                            <span class="font-bold">4</span>
                        </div>
                        <div class="md:w-1/2 pl-8 md:pr-8 md:pl-0 md:text-right mt-4 md:mt-0 order-first">
                            <h3 class="text-xl font-semibold text-gray-900 mb-2">Manage Everything</h3>
                            <p class="text-gray-600">
                                Handle cases, documents, communications, and tasks all in one place. Stay organized and efficient.
                            </p>
                        </div>
                    </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section id="testimonials" class="py-16 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-extrabold text-gray-900 sm:text-4xl" data-aos="fade-up">
                    What Our Users Say
                </h2>
                <p class="mt-4 text-lg text-gray-600" data-aos="fade-up" data-aos-delay="100">
                    Trusted by legal professionals around the world
                </p>
            </div>

            <div class="grid grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-3">
                <!-- Testimonial 1 -->
                <div class="bg-white rounded-lg shadow-lg p-6 testimonial-card" data-aos="fade-up" data-aos-delay="150">
                    <div class="flex items-center mb-4">
                        <div class="h-12 w-12 rounded-full overflow-hidden bg-gray-200 flex-shrink-0">
                            <img src="assets/img/testimonial-1.jpg" alt="User" class="h-full w-full object-cover" onerror="this.src='https://via.placeholder.com/48?text=J'">
                        </div>
                        <div class="ml-4">
                            <h4 class="text-lg font-semibold text-gray-900">John Doe</h4>
                            <p class="text-sm text-gray-600">Family Law Attorney</p>
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
                        "This platform has completely transformed how I manage my practice. I'm saving hours each week on administrative tasks, and my clients love the transparency."
                    </p>
                </div>

                <!-- Testimonial 2 -->
                <div class="bg-white rounded-lg shadow-lg p-6 testimonial-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="flex items-center mb-4">
                        <div class="h-12 w-12 rounded-full overflow-hidden bg-gray-200 flex-shrink-0">
                            <img src="assets/img/testimonial-2.jpg" alt="User" class="h-full w-full object-cover" onerror="this.src='https://via.placeholder.com/48?text=S'">
                        </div>
                        <div class="ml-4">
                            <h4 class="text-lg font-semibold text-gray-900">Sarah Johnson</h4>
                            <p class="text-sm text-gray-600">Corporate Lawyer</p>
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
                        "The document management system is exceptional. I can access everything I need from anywhere, and sharing with clients is seamless and secure."
                    </p>
                </div>

                <!-- Testimonial 3 -->
                <div class="bg-white rounded-lg shadow-lg p-6 testimonial-card" data-aos="fade-up" data-aos-delay="250">
                    <div class="flex items-center mb-4">
                        <div class="h-12 w-12 rounded-full overflow-hidden bg-gray-200 flex-shrink-0">
                            <img src="assets/img/testimonial-3.jpg" alt="User" class="h-full w-full object-cover" onerror="this.src='https://via.placeholder.com/48?text=M'">
                        </div>
                        <div class="ml-4">
                            <h4 class="text-lg font-semibold text-gray-900">Michael Chen</h4>
                            <p class="text-sm text-gray-600">Immigration Attorney</p>
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
                        "The case tracking features have been invaluable for my immigration practice. I can keep clients updated on their case status in real-time."
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-extrabold text-gray-900 sm:text-4xl" data-aos="fade-up">
                    Simple, Transparent Pricing
                </h2>
                <p class="mt-4 text-lg text-gray-600" data-aos="fade-up" data-aos-delay="100">
                    Choose the plan that works best for your practice
                </p>
            </div>

            <div class="grid grid-cols-1 gap-8 md:grid-cols-3">
                <!-- Basic Plan -->
                <div class="bg-white rounded-lg shadow-lg overflow-hidden pricing-card border border-gray-200" data-aos="fade-up" data-aos-delay="150">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-2xl font-bold text-gray-900">Basic</h3>
                        <p class="text-gray-600 mt-1">For solo practitioners</p>
                        <div class="mt-4">
                            <span class="text-4xl font-extrabold text-gray-900">$29</span>
                            <span class="text-gray-600">/month</span>
                        </div>
                    </div>
                    <div class="p-6">
                        <ul class="space-y-4">
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                <span class="text-gray-600">Up to 25 active cases</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                <span class="text-gray-600">5GB document storage</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                <span class="text-gray-600">Client portal</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                <span class="text-gray-600">Basic reporting</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                <span class="text-gray-600">Email support</span>
                            </li>
                        </ul>
                        <div class="mt-8">
                            <a href="register.php" class="block w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-3 px-4 rounded-md text-center">
                                Get Started
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Professional Plan -->
                <div class="bg-white rounded-lg shadow-xl overflow-hidden pricing-card border-2 border-indigo-500 transform scale-105 z-10" data-aos="fade-up" data-aos-delay="200">
                    <div class="bg-indigo-500 text-white text-center py-1 text-sm font-medium">
                        MOST POPULAR
                    </div>
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-2xl font-bold text-gray-900">Professional</h3>
                        <p class="text-gray-600 mt-1">For growing practices</p>
                        <div class="mt-4">
                            <span class="text-4xl font-extrabold text-gray-900">$79</span>
                            <span class="text-gray-600">/month</span>
                        </div>
                    </div>
                    <div class="p-6">
                        <ul class="space-y-4">
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                <span class="text-gray-600">Up to 100 active cases</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                <span class="text-gray-600">25GB document storage</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                <span class="text-gray-600">Advanced client portal</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                <span class="text-gray-600">Custom reporting</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                <span class="text-gray-600">Priority email & phone support</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                <span class="text-gray-600">Team collaboration tools</span>
                            </li>
                        </ul>
                        <div class="mt-8">
                            <a href="register.php" class="block w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-3 px-4 rounded-md text-center">
                                Get Started
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Enterprise Plan -->
                <div class="bg-white rounded-lg shadow-lg overflow-hidden pricing-card border border-gray-200" data-aos="fade-up" data-aos-delay="250">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-2xl font-bold text-gray-900">Enterprise</h3>
                        <p class="text-gray-600 mt-1">For law firms</p>
                        <div class="mt-4">
                            <span class="text-4xl font-extrabold text-gray-900">$199</span>
                            <span class="text-gray-600">/month</span>
                        </div>
                    </div>
                    <div class="p-6">
                        <ul class="space-y-4">
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                <span class="text-gray-600">Unlimited active cases</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                <span class="text-gray-600">100GB document storage</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                <span class="text-gray-600">White-labeled client portal</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                <span class="text-gray-600">Advanced analytics & reporting</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                <span class="text-gray-600">24/7 priority support</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                <span class="text-gray-600">Advanced security features</span>
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-3"></i>
                                <span class="text-gray-600">API access</span>
                            </li>
                        </ul>
                        <div class="mt-8">
                            <a href="register.php" class="block w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-3 px-4 rounded-md text-center">
                                Get Started
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section id="faq" class="py-16 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-extrabold text-gray-900 sm:text-4xl" data-aos="fade-up">
                    Frequently Asked Questions
                </h2>
                <p class="mt-4 text-lg text-gray-600" data-aos="fade-up" data-aos-delay="100">
                    Find answers to common questions about our platform
                </p>
            </div>

            <div class="max-w-3xl mx-auto divide-y divide-gray-200">
                <!-- FAQ Item 1 -->
                <div class="py-6" data-aos="fade-up" data-aos-delay="150">
                <details class="group">
                        <summary class="flex justify-between items-center font-medium cursor-pointer list-none">
                            <span>Is my data secure on your platform?</span>
                            <span class="transition group-open:rotate-180">
                                <i class="fas fa-chevron-down"></i>
                            </span>
                        </summary>
                        <p class="text-gray-600 mt-3 group-open:animate-fadeIn">
                            Absolutely. We take security very seriously. All data is encrypted both in transit and at rest. We use industry-standard security practices, regular security audits, and maintain strict access controls. Your client information and case details are always protected.
                        </p>
                    </details>
                </div>

                <!-- FAQ Item 2 -->
                <div class="py-6" data-aos="fade-up" data-aos-delay="200">
                    <details class="group">
                        <summary class="flex justify-between items-center font-medium cursor-pointer list-none">
                            <span>Can I migrate my existing case data?</span>
                            <span class="transition group-open:rotate-180">
                                <i class="fas fa-chevron-down"></i>
                            </span>
                        </summary>
                        <p class="text-gray-600 mt-3 group-open:animate-fadeIn">
                            Yes, we offer data migration services to help you transition smoothly. Our team can assist with importing your existing cases, client information, and documents from other systems. For larger practices, we provide personalized onboarding assistance.
                        </p>
                    </details>
                </div>

                <!-- FAQ Item 3 -->
                <div class="py-6" data-aos="fade-up" data-aos-delay="250">
                    <details class="group">
                        <summary class="flex justify-between items-center font-medium cursor-pointer list-none">
                            <span>How does the client portal work?</span>
                            <span class="transition group-open:rotate-180">
                                <i class="fas fa-chevron-down"></i>
                            </span>
                        </summary>
                        <p class="text-gray-600 mt-3 group-open:animate-fadeIn">
                            The client portal provides your clients with secure access to their case information. You control what they can see, including documents, case status, upcoming events, and communications. Clients receive login credentials and can access their portal 24/7 from any device.
                        </p>
                    </details>
                </div>

                <!-- FAQ Item 4 -->
                <div class="py-6" data-aos="fade-up" data-aos-delay="300">
                    <details class="group">
                        <summary class="flex justify-between items-center font-medium cursor-pointer list-none">
                            <span>Can I customize the platform for my practice?</span>
                            <span class="transition group-open:rotate-180">
                                <i class="fas fa-chevron-down"></i>
                            </span>
                        </summary>
                        <p class="text-gray-600 mt-3 group-open:animate-fadeIn">
                            Yes, our platform is highly customizable. You can configure case types, document categories, custom fields, and workflows to match your practice's specific needs. Enterprise plans include additional customization options, including white-labeling.
                        </p>
                    </details>
                </div>

                <!-- FAQ Item 5 -->
                <div class="py-6" data-aos="fade-up" data-aos-delay="350">
                    <details class="group">
                        <summary class="flex justify-between items-center font-medium cursor-pointer list-none">
                            <span>What kind of support do you offer?</span>
                            <span class="transition group-open:rotate-180">
                                <i class="fas fa-chevron-down"></i>
                            </span>
                        </summary>
                        <p class="text-gray-600 mt-3 group-open:animate-fadeIn">
                            All plans include email support. Professional and Enterprise plans include phone support with priority response times. We also provide comprehensive documentation, video tutorials, and regular webinars to help you get the most out of the platform.
                        </p>
                    </details>
                </div>

                <!-- FAQ Item 6 -->
                <div class="py-6" data-aos="fade-up" data-aos-delay="400">
                    <details class="group">
                        <summary class="flex justify-between items-center font-medium cursor-pointer list-none">
                            <span>Can I cancel my subscription at any time?</span>
                            <span class="transition group-open:rotate-180">
                                <i class="fas fa-chevron-down"></i>
                            </span>
                        </summary>
                        <p class="text-gray-600 mt-3 group-open:animate-fadeIn">
                            Yes, you can cancel your subscription at any time. There are no long-term contracts or cancellation fees. If you cancel, you'll have access until the end of your current billing period, and you can export your data before that time.
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
                Ready to Transform Your Legal Practice?
            </h2>
            <p class="mt-4 text-xl text-indigo-100" data-aos="fade-up" data-aos-delay="100">
                Join thousands of legal professionals who are working smarter, not harder.
            </p>
            <div class="mt-8 flex justify-center" data-aos="fade-up" data-aos-delay="200">
                <a href="register.php" class="inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-indigo-700 bg-white hover:bg-indigo-50">
                    Get Started Free
                    <i class="fas fa-arrow-right ml-2"></i>
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
                        Streamlining legal practice management for modern professionals.
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
                    <h3 class="text-lg font-semibold mb-4">Product</h3>
                    <ul class="space-y-2">
                        <li><a href="#features" class="text-gray-400 hover:text-white">Features</a></li>
                        <li><a href="#pricing" class="text-gray-400 hover:text-white">Pricing</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Security</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Roadmap</a></li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="text-lg font-semibold mb-4">Support</h3>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-gray-400 hover:text-white">Help Center</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Documentation</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Tutorials</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Contact Us</a></li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="text-lg font-semibold mb-4">Legal</h3>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-gray-400 hover:text-white">Privacy Policy</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Terms of Service</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Cookie Policy</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">GDPR Compliance</a></li>
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
