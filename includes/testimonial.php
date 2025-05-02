<?php

function getTestimonials() {
    return 
        [        [
            'name' => 'Sarah Johnson',
            'role' => 'Corporate Client',
            'content' => 'This system has transformed how we manage our legal affairs. The dashboard gives us real-time updates on all our cases, and communication with our advocates has never been easier.',
            'image' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=688&q=80'
        ],
        [
            'name' => 'Michael Chen',
            'role' => 'Senior Advocate',
            'content' => 'As someone managing multiple cases simultaneously, this platform has been a game-changer. Document management, client communication, and billing are all streamlined in one place.',
            'image' => 'https://randomuser.me/api/portraits/men/44.jpg'

        ],
        [
            'name' => 'Rebecca Williams',
            'role' => 'Family Law Client',
            'content' => 'During a difficult divorce case, having all documents and communications in one secure place gave me peace of mind. The appointment scheduling feature is particularly helpful.',
            'image' => 'https://images.unsplash.com/photo-1580489944761-15a19d654956?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=761&q=80'
        ]

    ];
}

?>

<!-- Testimonials Section -->
<section class="py-16 bg-white">
    <div class="container mx-auto px-4">
        <div class="text-center mb-16">
            <h2 class="text-3xl font-bold text-gray-800 mb-4">What Our Users Say</h2>
            <p class="text-xl text-gray-600 max-w-3xl mx-auto">Hear from advocates and clients who have transformed their legal practice with our platform.</p>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <?php foreach(getTestimonials() as $testimonial): ?>
            <div class="bg-gray-50 rounded-lg p-8 shadow-md">
                <div class="flex items-center mb-4">
                    <img src="<?php echo $testimonial['image']; ?>" alt="<?php echo $testimonial['name']; ?>" class="w-16 h-16 rounded-full object-cover mr-4">
                    <div>
                        <h4 class="font-semibold text-lg"><?php echo $testimonial['name']; ?></h4>
                        <p class="text-blue-600"><?php echo $testimonial['role']; ?></p>
                    </div>
                </div>
                <p class="text-gray-600 italic">"<?php echo $testimonial['content']; ?>"</p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
