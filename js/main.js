/**
 * Mon Réseau IDF - JavaScript principal
 */

document.addEventListener('DOMContentLoaded', function() {
    // Swap departure/arrival
    const swapBtn = document.querySelector('.swap-btn');
    if (swapBtn) {
        swapBtn.addEventListener('click', function() {
            const departure = document.getElementById('departure');
            const arrival = document.getElementById('arrival');
            if (departure && arrival) {
                const temp = departure.value;
                departure.value = arrival.value;
                arrival.value = temp;
            }
        });
    }

    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.3s ease';
            setTimeout(function() {
                alert.remove();
            }, 300);
        }, 5000);
    });

    // Confirm delete actions
    const deleteButtons = document.querySelectorAll('[data-confirm]');
    deleteButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm') || 'Êtes-vous sûr de vouloir effectuer cette action ?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });

    // Mobile menu toggle
    const menuToggle = document.querySelector('.menu-toggle');
    const navMain = document.querySelector('.nav-main');
    if (menuToggle && navMain) {
        menuToggle.addEventListener('click', function() {
            navMain.classList.toggle('active');
        });
    }

    // Animate stats on scroll
    const animateStats = function() {
        const stats = document.querySelectorAll('.stat-number');
        stats.forEach(function(stat) {
            const target = parseInt(stat.getAttribute('data-target'));
            if (target && !stat.classList.contains('animated')) {
                const rect = stat.getBoundingClientRect();
                if (rect.top < window.innerHeight && rect.bottom > 0) {
                    stat.classList.add('animated');
                    animateValue(stat, 0, target, 2000);
                }
            }
        });
    };

    const animateValue = function(element, start, end, duration) {
        const range = end - start;
        const increment = end > start ? 1 : -1;
        const stepTime = Math.abs(Math.floor(duration / range));
        let current = start;
        
        const timer = setInterval(function() {
            current += increment;
            element.textContent = current.toLocaleString('fr-FR');
            if (current === end) {
                clearInterval(timer);
            }
        }, stepTime);
    };

    window.addEventListener('scroll', animateStats);
    animateStats();
});
