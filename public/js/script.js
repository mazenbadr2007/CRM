document.addEventListener('DOMContentLoaded', function() {
   
    // Find ALL slideshow containers on the page
    const slideshowContainers = document.querySelectorAll('.slideshow-container');
    
    if (slideshowContainers.length > 0) {
       const imageSources = [
    'https://images.unsplash.com/photo-1580587771525-78b9dba3b914?q=80&w=1974&auto=format&fit=crop',
    'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?q=80&w=2070&auto=format&fit=crop',
    'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?q=80&w=2070&auto=format&fit=crop',
    'https://images.unsplash.com/photo-1570129477492-45c003edd2be?q=80&w=2070&auto=format&fit=crop',
    'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?q=80&w=2070&auto=format&fit=crop',
    'https://images.unsplash.com/photo-1448630360428-65456885c650?q=80&w=2070&auto=format&fit=crop',
    'https://images.unsplash.com/photo-1605146769289-440113cc3d00?q=80&w=2070&auto=format&fit=crop',
    'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?q=80&w=2070&auto=format&fit=crop',
    'https://images.unsplash.com/photo-1480074568708-e7b720bb3f09?q=80&w=2070&auto=format&fit=crop',
    'https://images.unsplash.com/photo-1600566753190-17f0e278a056?q=80&w=1974&auto=format&fit=crop',
    'https://images.unsplash.com/photo-1494526585095-c41746248156?q=80&w=2070&auto=format&fit=crop'
];
        slideshowContainers.forEach(container => {
            let currentImageIndex = 0;

            imageSources.forEach((src, index) => {
                const img = document.createElement('img');
                img.src = src;
                img.alt = "Real estate property";
                img.className = 'slideshow-image';
            
                if (index === 0) {
                    img.classList.add('active');
                }
                container.appendChild(img);
            });

            const images = container.querySelectorAll('.slideshow-image');

            setInterval(() => {
                if (images.length > 0) {
                    images[currentImageIndex].classList.remove('active');
                    currentImageIndex = (currentImageIndex + 1) % images.length;
                    images[currentImageIndex].classList.add('active');
                }
            }, 5000); // Change image every 5 seconds
        });
    }
    
    // This part for the sidebar menu toggle remains the same.
    const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
    const sidebar = document.getElementById('sidebar');

    if (mobileMenuToggle && sidebar) {
        mobileMenuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('is-open');
        });
    }
});