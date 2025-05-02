<!-- FAQ Section -->
<section class="py-16 bg-white">
    <div class="container mx-auto px-4">
        <div class="text-center mb-16">
            <h2 class="text-3xl font-bold text-gray-800 mb-4">Frequently Asked Questions</h2>
            <p class="text-xl text-gray-600 max-w-3xl mx-auto">Find answers to common questions about our platform.</p>
        </div>
        
        <div class="max-w-4xl mx-auto" x-data="{selected:null}">
            <div class="mb-5">
                <button @click="selected !== 1 ? selected = 1 : selected = null" class="flex justify-between items-center w-full p-5 font-semibold text-left bg-gray-50 hover:bg-gray-100 rounded-lg focus:outline-none">
                    <span>How secure is the platform for sensitive legal documents?</span>
                    <i class="fas" :class="selected == 1 ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                </button>
                <div x-show="selected == 1" class="p-5 bg-gray-50 rounded-b-lg mt-1">
                    <p class="text-gray-600">Our platform employs bank-level security measures including 256-bit encryption for all data, both in transit and at rest. We are compliant with industry standards for data protection and regularly undergo security audits. All documents are stored in secure, redundant cloud storage with strict access controls.</p>
                </div>
            </div>
            
            <div class="mb-5">
                <button @click="selected !== 2 ? selected = 2 : selected = null" class="flex justify-between items-center w-full p-5 font-semibold text-left bg-gray-50 hover:bg-gray-100 rounded-lg focus:outline-none">
                    <span>Can I migrate my existing case data to the platform?</span>
                    <i class="fas" :class="selected == 2 ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                </button>
                <div x-show="selected == 2" class="p-5 bg-gray-50 rounded-b-lg mt-1">
                    <p class="text-gray-600">Yes, we offer data migration services for all subscription plans. Our team will work with you to import your existing case data, client information, and documents into the platform. We support imports from most common legal practice management systems and can also handle custom CSV imports.</p>
                </div>
            </div>
            
            <div class="mb-5">
                <button @click="selected !== 3 ? selected = 3 : selected = null" class="flex justify-between items-center w-full p-5 font-semibold text-left bg-gray-50 hover:bg-gray-100 rounded-lg focus:outline-none">
                    <span>How does the billing system work for advocates?</span>
                    <i class="fas" :class="selected == 3 ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                </button>
                <div x-show="selected == 3" class="p-5 bg-gray-50 rounded-b-lg mt-1">
                    <p class="text-gray-600">Our billing system allows advocates to track time, create professional invoices, and manage payments. You can set up hourly rates, flat fees, or retainer arrangements. The system supports automatic time tracking, expense recording, and can generate detailed billing reports. Clients can view and pay invoices directly through their portal, and the system tracks payment status and history.</p>
                </div>
            </div>
            <div class="mb-5">
                <button @click="selected !== 4 ? selected = 4 : selected = null" class="flex justify-between items-center w-full p-5 font-semibold text-left bg-gray-50 hover:bg-gray-100 rounded-lg focus:outline-none">
                    <span>Is there a mobile app available?</span>
                    <i class="fas" :class="selected == 4 ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                </button>
                <div x-show="selected == 4" class="p-5 bg-gray-50 rounded-b-lg mt-1">
                    <p class="text-gray-600">No, but we're working on it! Our mobile apps for both iOS and Android devices are currently in development and coming soon. These apps will provide access to most platform features, including case updates, document viewing, messaging, and calendar management with push notifications for important updates. In the meantime, our responsive web interface works well on mobile browsers.</p>
                </div>
            </div>

            <div class="mb-5">
                <button @click="selected !== 5 ? selected = 5 : selected = null" class="flex justify-between items-center w-full p-5 font-semibold text-left bg-gray-50 hover:bg-gray-100 rounded-lg focus:outline-none">
                    <span>Can I customize the platform for my specific practice area?</span>
                    <i class="fas" :class="selected == 5 ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                </button>
                             <div x-show="selected == 5" class="p-5 bg-gray-50 rounded-b-lg mt-1">
                    <p class="text-gray-600">Absolutely. The platform offers customization options for different practice areas including family law, corporate law, criminal defense, real estate, and more. You can customize case types, document templates, workflow stages, and client intake forms to match your specific practice needs. Professional and Enterprise plans include additional customization options and the ability to create custom fields.</p>
                </div>
            </div>
            
            <div class="mb-5">
                <button @click="selected !== 6 ? selected = 6 : selected = null" class="flex justify-between items-center w-full p-5 font-semibold text-left bg-gray-50 hover:bg-gray-100 rounded-lg focus:outline-none">
                    <span>What kind of support is available?</span>
                    <i class="fas" :class="selected == 6 ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                </button>
                <div x-show="selected == 6" class="p-5 bg-gray-50 rounded-b-lg mt-1">
                    <p class="text-gray-600">All plans include access to our knowledge base, video tutorials, and email support. Professional plans add phone support during business hours, while Enterprise plans include 24/7 priority support with a dedicated account manager. We also offer onboarding assistance and training sessions to help you get the most out of the platform.</p>
                </div>
            </div>
        </div>
    </div>
</section>