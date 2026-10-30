// assets/js/modules/header-nav.js
// Shared header & navigation behavior: hamburger menu, search overlay, scroll shadow.

export function initHeaderNav() {
  const header = document.getElementById('site-header');
  const hamburger = document.getElementById('hamburger');
  const mobileMenu = document.getElementById('mobile-menu');
  const navLinks = mobileMenu?.querySelectorAll('.mobile-nav-link');
  const searchToggle = document.getElementById('search-toggle');
  const searchOverlay = document.getElementById('search-overlay');
  const searchClose = document.getElementById('search-close');
  const searchInput = document.getElementById('header-search-input');

  let bodyOverflow = '';

  function lockBody(lock) {
    if (lock) {
      bodyOverflow = document.body.style.overflow || '';
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = bodyOverflow;
    }
  }

  function openMobileMenu() {
    if (searchOverlay?.classList.contains('open')) closeSearch();
    mobileMenu?.classList.add('open');
    hamburger?.classList.add('open');
    hamburger?.setAttribute('aria-expanded', 'true');
    hamburger?.setAttribute('aria-label', 'Close navigation menu');
    lockBody(true);
  }

  function closeMobileMenu() {
    mobileMenu?.classList.remove('open');
    hamburger?.classList.remove('open');
    hamburger?.setAttribute('aria-expanded', 'false');
    hamburger?.setAttribute('aria-label', 'Toggle navigation menu');
    lockBody(false);
  }

  function toggleMobileMenu() {
    const expanded = hamburger?.getAttribute('aria-expanded') === 'true';
    expanded ? closeMobileMenu() : openMobileMenu();
  }

  function openSearch() {
    if (mobileMenu?.classList.contains('open')) closeMobileMenu();
    searchOverlay?.classList.add('open');
    searchToggle?.setAttribute('aria-expanded', 'true');
    searchInput?.focus();
    lockBody(true);
  }

  function closeSearch() {
    searchOverlay?.classList.remove('open');
    searchToggle?.setAttribute('aria-expanded', 'false');
    lockBody(false);
  }

  function createMobileCloseButton() {
    if (!mobileMenu || mobileMenu.querySelector('.mobile-menu-close-btn')) return;
    const closeBtn = document.createElement('button');
    closeBtn.className = 'mobile-menu-close-btn';
    closeBtn.id = 'mobile-menu-close';
    closeBtn.setAttribute('aria-label', 'Close menu');
    closeBtn.innerHTML = '✕';
    closeBtn.addEventListener('click', closeMobileMenu);
    mobileMenu.insertBefore(closeBtn, mobileMenu.firstChild);
  }

  hamburger?.addEventListener('click', toggleMobileMenu);
  hamburger?.addEventListener('keydown', e => {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggleMobileMenu(); }
  });

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      if (mobileMenu?.classList.contains('open')) closeMobileMenu();
      else if (searchOverlay?.classList.contains('open')) closeSearch();
    }
  });

  navLinks?.forEach(link => link.addEventListener('click', closeMobileMenu));
  document.addEventListener('click', e => {
    const inMobile = mobileMenu?.contains(e.target);
    const inHamburger = hamburger?.contains(e.target);
    if (mobileMenu?.classList.contains('open') && !inMobile && !inHamburger) {
      closeMobileMenu();
    }
    if (searchOverlay?.classList.contains('open') && e.target !== searchOverlay && !searchOverlay.contains(e.target) && !searchToggle?.contains(e.target)) {
      closeSearch();
    }
  });

  searchToggle?.addEventListener('click', openSearch);
  searchClose?.addEventListener('click', closeSearch);
  searchOverlay?.addEventListener('click', e => {
    if (e.target === searchOverlay) closeSearch();
  });

  createMobileCloseButton();

  if (header) {
    const onScroll = () => header.classList.toggle('scrolled', window.scrollY > 10);
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }
}