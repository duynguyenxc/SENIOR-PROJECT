/**
 * Scrollspy — highlights the sidebar nav link matching
 * the section currently visible in the viewport.
 */
(function () {
  const navSubs = Array.from(document.querySelectorAll('.nav-sub'));
  const navLinks = Array.from(document.querySelectorAll('.nav-link'));
  const allNavItems = [...navLinks, ...navSubs];

  // Collect all heading anchors that have a matching sidebar link
  const anchors = allNavItems
    .map(a => {
      const href = a.getAttribute('href') || '';
      const hash = href.includes('#') ? href.split('#')[1] : null;
      if (!hash) return null;
      const el = document.getElementById(hash);
      return el ? { el, a } : null;
    })
    .filter(Boolean);

  if (anchors.length === 0) return;

  let currentActive = null;

  function setActive(entry) {
    if (currentActive === entry) return;
    currentActive = entry;

    // Remove active from all sub-links
    navSubs.forEach(a => a.classList.remove('active'));

    if (entry) {
      entry.a.classList.add('active');
    }
  }

  // Use IntersectionObserver to detect which section is in view
  const observer = new IntersectionObserver(
    (entries) => {
      // Find the topmost entry that is intersecting
      const visible = entries
        .filter(e => e.isIntersecting)
        .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);

      if (visible.length > 0) {
        const id = visible[0].target.id;
        const match = anchors.find(a => a.el.id === id);
        if (match) setActive(match);
      }
    },
    {
      rootMargin: '-10% 0px -75% 0px',
      threshold: 0,
    }
  );

  anchors.forEach(({ el }) => observer.observe(el));

  // Also handle click: smooth-scroll and set active immediately on click
  allNavItems.forEach(a => {
    a.addEventListener('click', function () {
      const href = this.getAttribute('href') || '';
      const hash = href.includes('#') ? href.split('#')[1] : null;
      if (!hash) return;
      const match = anchors.find(x => x.el.id === hash);
      if (match) setActive(match);
    });
  });
})();
