/**
 * Home Page Script (User)
 * - Plan A: Smart-Fill blur for announcements (no black bars + no cropping)
 */

const ANN_API = 'https://bulaservicesgsc.com/php/announce_api_proxy.php';
const PRICES_API = '/php/get_public_prices.php';

let currentSlide = 0, slideInterval = null, slides = [], dots = [], totalSlides = 0;

document.addEventListener('DOMContentLoaded', function () {
  setupProfileDropdown();
  // (Notifications stub left as-is; does nothing if elements not present)
  setupNotificationsDropdown?.();
  setupContactForm();
  hydrateCarouselFromAPI();
  injectPricesIntoBadges();
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
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    alert('Thank you for your message! We will get back to you soon.');
    form.reset();
  });
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
  try {
    const res = await fetch(`${ANN_API}?action=list&limit=${limit}`);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Failed to load announcements');
    return data.data?.items || [];
  } catch (err) {
    console.warn('[announcements] load error:', err);
    return [];
  }
}

/** Plan A: build slides with a blurred background fill (CSS uses --slide-url) */
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
      const src = normalizeImageSrc(a);
      const slide = document.createElement('div');
      slide.className = `slide${idx === 0 ? ' active' : ''}`;
      // CSS reads this for the blurred background
      slide.style.setProperty('--slide-url', `url("${src}")`);

      const img = document.createElement('img');
      img.src = src;
      img.alt = a.title || 'Announcement';
      img.loading = 'lazy';

      img.onerror = function () {
        // Fallback placeholder if image fails
        this.style.display = 'none';
        const ph = document.createElement('div');
        ph.className = 'image-placeholder';
        ph.innerHTML = '<i class="fas fa-image"></i>';
        ph.style.cssText = 'width:100%;min-height:280px;display:flex;align-items:center;justify-content:center;background:#f8fafc;color:#cbd5e1;font-size:2rem;';
        slide.appendChild(ph);
      };

      slide.appendChild(img);
      imagesWrap.appendChild(slide);

      const dot = document.createElement('div');
      dot.className = `dot ${idx === 0 ? 'active' : ''}`;
      dot.setAttribute('role', 'button');
      dot.setAttribute('aria-label', `Go to slide ${idx + 1}`);
      dot.addEventListener('click', () => { goToSlide(idx); resetCarouselTimer(); });
      dotsWrap.appendChild(dot);
    });

    slides = Array.from(imagesWrap.querySelectorAll('.slide'));
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

// -------- Map (unchanged) --------
function initBarangayMap() {
  const mapContainer = document.getElementById('barangayMap');
  if (!mapContainer) return;

  const barangayBulaCoords = [6.104012766602646, 125.19345833311019];
  const map = L.map('barangayMap').setView(barangayBulaCoords, 16);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 18
  }).addTo(map);

  const barangayIcon = L.divIcon({
    className: 'custom-marker',
    html: '<i class="fas fa-map-marker-alt" style="color: white; font-size: 16px; margin: 8px;"></i>',
    iconSize: [40, 40],
    iconAnchor: [20, 40]
  });

  const marker = L.marker(barangayBulaCoords, { icon: barangayIcon }).addTo(map);
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

  L.circle(barangayBulaCoords, { color: '#2563eb', fillColor: '#2563eb', fillOpacity: 0.1, radius: 300 }).addTo(map);

  const bounds = L.latLngBounds([6.0990, 125.1884],[6.1090, 125.1984]);
  map.fitBounds(bounds);
  window.barangayMap = map;
}
function getDirections() {
  const destination = '6.104012766602646,125.19345833311019';
  const url = `https://www.google.com/maps/dir/?api=1&destination=${destination}`;
  window.open(url, '_blank');
}
function openInGoogleMaps() {
  const coords = '6.104012766602646,125.19345833311019';
  const url = `https://www.google.com/maps?q=${coords}`;
  window.open(url, '_blank');
}

// ---------- dynamic prices (unchanged) ----------
async function injectPricesIntoBadges() {
  try {
    const res = await fetch(PRICES_API, { credentials: 'same-origin' });
    if (!res.ok) {
      console.warn('[prices] HTTP', res.status);
      return;
    }
    const data = await res.json();
    if (!data || data.success !== true || !data.prices || typeof data.prices !== 'object') {
      console.warn('[prices] Unexpected payload:', data);
      return;
    }
    const prices = data.prices;
    const toNum = (v) => { const s = String(v ?? '').replace(/,/g, '').trim(); const n = Number(s); return Number.isFinite(n) ? n : NaN; };
    const fmt = (n) => `₱${n.toLocaleString('en-PH', { maximumFractionDigits: 0 })}`;

    document.querySelectorAll('[data-price-key]').forEach(el => {
      const key = el.getAttribute('data-price-key');
      if (!key || !(key in prices)) return;
      const n = toNum(prices[key]);
      if (!Number.isFinite(n)) return;

      if (key === 'gym_morning') {
        el.innerHTML = `${fmt(n)} <small>(7AM–5PM)</small>`;
        el.title = 'Morning rate';
      } else if (key === 'gym_evening') {
        el.innerHTML = `${fmt(n)} <small>(5PM–10PM)</small>`;
        el.title = 'Evening rate';
      } else {
        el.textContent = fmt(n);
        if (!el.title) el.title = 'Current price';
      }
    });
  } catch (err) {
    console.warn('[prices] error:', err);
  }
}

/* ------- Logout confirm wiring (uses existing CSS in file) ------- */
function confirmLogout(e){
  e.preventDefault();
  const overlay = document.getElementById('logoutDialog');
  if (!overlay) return (window.location.href = 'logout.php'); // fallback
  overlay.style.display = 'flex';

  const cleanup = () => { overlay.style.display = 'none'; document.removeEventListener('keydown', esc); };
  const esc = (ev) => { if (ev.key === 'Escape') cleanup(); };

  document.getElementById('logoutCancelBtn')?.addEventListener('click', cleanup, { once: true });
  document.getElementById('logoutConfirmBtn')?.addEventListener('click', () => { window.location.href = 'logout.php'; }, { once: true });
  overlay.addEventListener('click', (ev) => { if (ev.target === overlay) cleanup(); }, { once: true });
  document.addEventListener('keydown', esc);
}
window.confirmLogout = confirmLogout;
