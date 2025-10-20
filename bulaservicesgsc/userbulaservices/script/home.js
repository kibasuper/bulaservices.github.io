/**
 * Home Page Script (User) - uses same-origin proxy for announcements
 */

const ANN_API = 'https://bulaservicesgsc.com/php/announce_api_proxy.php';

let currentSlide = 0, slideInterval = null, slides = [], dots = [], totalSlides = 0;

document.addEventListener('DOMContentLoaded', function () {
    setupProfileDropdown();
    setupNotificationsDropdown();
    setupContactForm();
    hydrateCarouselFromAPI();

     // Initialize map with a small delay to ensure DOM is ready
    setTimeout(initBarangayMap, 100);
});

function setupProfileDropdown() {
    const profileBtn = document.querySelector('.profile-btn');
    const dropdownMenu = document.getElementById('dropdownMenu');
    if (profileBtn && dropdownMenu) {
        profileBtn.addEventListener('click', (e) => { e.stopPropagation(); dropdownMenu.classList.toggle('show'); });
        document.addEventListener('click', () => dropdownMenu.classList.remove('show'));
        dropdownMenu.addEventListener('click', (e) => e.stopPropagation());
    }
}
function setupNotificationsDropdown() {
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationDropdown = document.getElementById('notificationDropdown');
    const dropdownMenu = document.getElementById('dropdownMenu');
    if (notificationBtn && notificationDropdown) {
        notificationBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            notificationDropdown.classList.toggle('show');
            if (dropdownMenu?.classList.contains('show')) dropdownMenu.classList.remove('show');
        });
        document.addEventListener('click', () => notificationDropdown.classList.remove('show'));
        document.querySelector('.mark-all-read')?.addEventListener('click', () => {
            document.querySelectorAll('.notification-item.unread').forEach(i => i.classList.remove('unread'));
            const badge = document.querySelector('.notification-badge');
            if (badge) { badge.textContent = '0'; badge.style.display = 'none'; }
        });
    }
}
function setupContactForm() {
    const form = document.getElementById('contactForm');
    if (!form) return;
    form.addEventListener('submit', (e) => { e.preventDefault(); alert('Thank you for your message! We will get back to you soon.'); form.reset(); });
}

// Prefer server-computed absolute URL; fallback to main site
function normalizeImageSrc(a) {
    if (a.image_url) return a.image_url;
    const p = a.image_path || '';
    if (!p) return '';
    if (/^https?:\/\//i.test(p)) return p;
    return 'https://bulaservicesgsc.com' + (p.startsWith('/') ? p : '/' + p);
}

async function fetchPublicAnnouncements(limit = 6) {
    const res = await fetch(`${ANN_API}?action=list&limit=${limit}`);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Failed to load announcements');
    return data.data?.items || [];
}

async function hydrateCarouselFromAPI() {
    const section = document.getElementById('annCarousel');
    const imagesWrap = document.querySelector('.carousel-images');
    const dotsWrap = document.querySelector('.carousel-dots');
    if (!section || !imagesWrap || !dotsWrap) return;

    try {
        const items = await fetchPublicAnnouncements(6);
        if (!items.length) { section.style.display = 'none'; slides = []; dots = []; totalSlides = 0; return; }

        imagesWrap.innerHTML = '';
        dotsWrap.innerHTML = '';

        items.forEach((a, idx) => {
            const img = document.createElement('img');
            img.src = normalizeImageSrc(a);
            img.alt = a.title || 'Announcement';
            img.loading = 'lazy';
            img.onerror = function () {
                this.style.display = 'none';
                const ph = document.createElement('div');
                ph.className = 'image-placeholder';
                ph.innerHTML = '<i class="fas fa-image"></i>';
                ph.style.cssText = 'width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#f8fafc;color:#cbd5e1;font-size:2rem;';
                this.parentNode.appendChild(ph);
            };
            if (idx === 0) img.classList.add('active');
            imagesWrap.appendChild(img);

            const dot = document.createElement('div');
            dot.className = `dot ${idx === 0 ? 'active' : ''}`;
            dot.addEventListener('click', () => { goToSlide(idx); resetCarouselTimer(); });
            dotsWrap.appendChild(dot);
        });

        slides = Array.from(imagesWrap.querySelectorAll('img'));
        dots = Array.from(dotsWrap.querySelectorAll('.dot'));
        totalSlides = slides.length;
        currentSlide = 0;

        section.style.display = '';
        showSlide(0);
        resetCarouselTimer();

        document.querySelector('.prev')?.addEventListener('click', () => { prevSlide(); resetCarouselTimer(); });
        document.querySelector('.next')?.addEventListener('click', () => { nextSlide(); resetCarouselTimer(); });

        const carousel = document.querySelector('.carousel');
        carousel?.addEventListener('mouseenter', stopCarousel);
        carousel?.addEventListener('mouseleave', startCarousel);
    } catch (e) {
        console.warn('Announcements unavailable:', e.message);
        section.style.display = 'none';
    }
}

function showSlide(n) {
    if (!slides.length || totalSlides === 0) return;
    slides.forEach(s => s.classList.remove('active'));
    dots.forEach(d => d.classList.remove('active'));
    currentSlide = (n + totalSlides) % totalSlides;
    slides[currentSlide].classList.add('active');
    dots[currentSlide].classList.add('active');
}
function nextSlide() { showSlide(currentSlide + 1); }
function prevSlide() { showSlide(currentSlide - 1); }
function goToSlide(n) { showSlide(n); }
function startCarousel() { if (slideInterval) clearInterval(slideInterval); if (totalSlides > 1) slideInterval = setInterval(nextSlide, 5000); }
function stopCarousel() { if (slideInterval) { clearInterval(slideInterval); slideInterval = null; } }
function resetCarouselTimer() { stopCarousel(); startCarousel(); }

// Optional helpers
window.refreshAnnouncements = () => hydrateCarouselFromAPI();
window.showAnnouncementSlide = (i) => goToSlide(i);

// Map functionality for Barangay Bula location
function initBarangayMap() {
    const mapContainer = document.getElementById('barangayMap');
    if (!mapContainer) return;

    // Updated Barangay Bula coordinates in General Santos City
    const barangayBulaCoords = [6.104012766602646, 125.19345833311019];
    
    // Initialize the map
    const map = L.map('barangayMap').setView(barangayBulaCoords, 16);

    // Add OpenStreetMap tiles (free and open source)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 18
    }).addTo(map);

    // Custom icon for Barangay Bula
    const barangayIcon = L.divIcon({
        className: 'custom-marker',
        html: '<i class="fas fa-map-marker-alt" style="color: white; font-size: 16px; margin: 8px;"></i>',
        iconSize: [40, 40],
        iconAnchor: [20, 40]
    });

    // Add marker for Barangay Bula
    const marker = L.marker(barangayBulaCoords, { icon: barangayIcon }).addTo(map);
    
    // Add popup with information
    marker.bindPopup(`
        <div style="min-width: 200px;">
            <h3>Barangay Bula Hall</h3>
            <p><strong>Address:</strong> Edilberto Lopez Sr. St, General Santos City</p>
            <p><strong>Phone:</strong> (083) 552-9692</p>
            <p><strong>Hours:</strong> Mon-Fri 8AM-5PM</p>
            <button onclick="getDirections()" style="width: 100%; padding: 8px; background: #2563eb; color: white; border: none; border-radius: 4px; cursor: pointer; margin-top: 8px;">
                Get Directions
            </button>
        </div>
    `).openPopup();

    // Add a circle to highlight the area
    L.circle(barangayBulaCoords, {
        color: '#2563eb',
        fillColor: '#2563eb',
        fillOpacity: 0.1,
        radius: 300
    }).addTo(map);

    // Fit bounds to show the area around the new coordinates
    const bounds = L.latLngBounds(
        [6.0990, 125.1884],  // Southwest corner
        [6.1090, 125.1984]   // Northeast corner
    );
    map.fitBounds(bounds);

    // Store map instance for other functions
    window.barangayMap = map;
}

// Get directions function - updated coordinates
function getDirections() {
    const destination = '6.104012766602646,125.19345833311019';
    const url = `https://www.google.com/maps/dir/?api=1&destination=${destination}`;
    window.open(url, '_blank');
}

// Open in Google Maps function - updated coordinates
function openInGoogleMaps() {
    const coords = '6.104012766602646,125.19345833311019';
    const url = `https://www.google.com/maps?q=${coords}`;
    window.open(url, '_blank');
}
