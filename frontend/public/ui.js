'use strict';

(function() {
  const cMenuButton = document.querySelector('[data-sidebar-toggle]');
  const cMobile = window.matchMedia('(max-width: 900px)');
  if (!cMenuButton) return;

  function fUpdateMenu() {
    const cExpanded = cMobile.matches
      ? document.body.classList.contains('sidebar-open')
      : !document.body.classList.contains('hide-sidebar');
    cMenuButton.setAttribute('aria-expanded', String(cExpanded));
  }

  function fCloseMenu() {
    document.body.classList.remove('sidebar-open');
    fUpdateMenu();
  }

  cMenuButton.addEventListener('click', function() {
    document.body.classList.toggle(cMobile.matches ? 'sidebar-open' : 'hide-sidebar');
    fUpdateMenu();
  });
  document.querySelector('[data-sidebar-close]').addEventListener('click', fCloseMenu);
  document.querySelectorAll('#app-sidebar a').forEach(function(pLink) {
    pLink.addEventListener('click', fCloseMenu);
  });
  document.addEventListener('keydown', function(pEvent) {
    if (pEvent.key === 'Escape') {
      fCloseMenu();
      document.querySelectorAll('.tb-menu').forEach(function(pMenu) { pMenu.open = false; });
      cMenuButton.focus();
    }
  });
  document.addEventListener('click', function(pEvent) {
    document.querySelectorAll('.tb-menu').forEach(function(pMenu) {
      if (!pMenu.contains(pEvent.target)) pMenu.open = false;
    });
  });
  cMobile.addEventListener('change', fCloseMenu);
  fUpdateMenu();
}());
