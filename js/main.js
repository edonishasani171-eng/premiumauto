// js/main.js
document.addEventListener('DOMContentLoaded', () => {
    // Search functionality
    const searchInput = document.getElementById('search-input');
    const carCards = document.querySelectorAll('.car-card');

    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const searchTerm = e.target.value.toLowerCase();
            let hasVisibleCars = false;

            carCards.forEach(card => {
                const title = card.querySelector('.car-title').innerText.toLowerCase();
                const make = card.dataset.make ? card.dataset.make.toLowerCase() : '';
                const model = card.dataset.model ? card.dataset.model.toLowerCase() : '';
                
                if (title.includes(searchTerm) || make.includes(searchTerm) || model.includes(searchTerm)) {
                    card.style.display = 'block';
                    hasVisibleCars = true;
                } else {
                    card.style.display = 'none';
                }
            });

            // Handle empty state for search
            let emptyMessage = document.getElementById('no-search-results');
            if (!hasVisibleCars && carCards.length > 0) {
                if (!emptyMessage) {
                    emptyMessage = document.createElement('div');
                    emptyMessage.id = 'no-search-results';
                    emptyMessage.className = 'no-cars';
                    emptyMessage.innerText = 'No cars match your search criteria.';
                    document.querySelector('.cars-grid').appendChild(emptyMessage);
                } else {
                    emptyMessage.style.display = 'block';
                }
            } else if (emptyMessage) {
                emptyMessage.style.display = 'none';
            }
        });
    }

    // Scroll animation for car cards
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target); // Animate only once
            }
        });
    }, {
        threshold: 0.1
    });

    carCards.forEach((card, index) => {
        // Add a slight delay based on index for a staggered animation effect
        card.style.animationDelay = `${index * 0.1}s`;
        observer.observe(card);
    });
});
