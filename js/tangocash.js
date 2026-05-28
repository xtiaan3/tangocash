/**
 * TangoCash — v1 site-wide JS
 *
 * Three concerns at this stage:
 *   1. Drawer (right-side hamburger menu) open/close — backdrop click,
 *      Escape, X button, hamburger toggle. .tc_drawer_open class on body
 *      drives the animation.
 *   2. Code copy buttons — copies the contents of the target <code>
 *      element to clipboard and flashes the button.
 *   3. Recent-recipient chips + amount input behaviors from v0 (kept).
 *
 * Eager initialization — body is already parsed by the time the script
 * tag in _footer.php runs.
 */
(function () {
  'use strict';

  // ---------- BrainLock sign-in popup launcher ----------
  // Any element with [data-brainlock-signin] or .bl_signin_btn opens the
  // BrainLock auth popup on click. Window.open() runs synchronously inside
  // the click handler so browsers don't block it; we then fetch the auth
  // URL from /auth/start.json and redirect the already-open popup. The
  // postMessage handler navigates the parent window to /auth/callback.php
  // when BrainLock posts back its result.
  var BL_ORIGIN = 'https://brainlock.id';
  var SIGNIN_START_URL = '/auth/start.json.php';

  function blOpenSignin(e) {
    e.preventDefault();

    // 1. Open popup IMMEDIATELY (still on the user's click — no blocker).
    var w = 480, h = 720;
    var winW = window.innerWidth  || document.documentElement.clientWidth  || screen.width;
    var winH = window.innerHeight || document.documentElement.clientHeight || screen.height;
    var left = (window.screenLeft || 0) + (winW - w) / 2;
    var top  = (window.screenTop  || 0) + (winH - h) / 2;
    var popup = window.open(
      'about:blank',
      'brainlock_signin',
      'width=' + w + ',height=' + h + ',left=' + left + ',top=' + top + ',resizable=yes,scrollbars=yes'
    );
    if (!popup) {
      alert('Looks like your browser blocked the sign-in popup. Please allow popups for this site and try again.');
      return;
    }

    // 2. Listen for the auth result.
    var onMessage = function (ev) {
      if (ev.origin !== BL_ORIGIN) return;
      var data = ev.data || {};
      if (data.type !== 'brainlock:auth' || !data.url) return;
      window.removeEventListener('message', onMessage);
      window.location.href = data.url;
    };
    window.addEventListener('message', onMessage);

    // 3. Fetch the auth URL and redirect the popup.
    fetch(SIGNIN_START_URL, { method: 'POST', credentials: 'same-origin' })
      .then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then(function (data) {
        if (!data.url) throw new Error('No URL in start_session response');
        var sep = data.url.indexOf('?') === -1 ? '?' : '&';
        popup.location.href = data.url + sep + 'embed=popup';
      })
      .catch(function (err) {
        console.error('[bl_signin] failed to start session:', err);
        try { popup.close(); } catch (e) {}
        window.removeEventListener('message', onMessage);
        alert('We couldn\'t start sign-in. Please try again.');
      });
  }

  document.querySelectorAll('[data-brainlock-signin], .bl_signin_btn').forEach(function (el) {
    el.addEventListener('click', blOpenSignin);
  });

  // ---------- Drawer ----------
  var hamburgerBtn = document.getElementById('tc_hamburger_btn');
  var drawerClose  = document.getElementById('tc_drawer_close');
  var backdrop     = document.getElementById('tc_drawer_backdrop');
  var drawer       = document.getElementById('tc_drawer');

  function openDrawer() {
    document.body.classList.add('tc_drawer_open');
    if (hamburgerBtn) hamburgerBtn.setAttribute('aria-expanded', 'true');
    if (drawer) drawer.setAttribute('aria-hidden', 'false');
    if (backdrop) backdrop.setAttribute('aria-hidden', 'false');
  }
  function closeDrawer() {
    document.body.classList.remove('tc_drawer_open');
    if (hamburgerBtn) hamburgerBtn.setAttribute('aria-expanded', 'false');
    if (drawer) drawer.setAttribute('aria-hidden', 'true');
    if (backdrop) backdrop.setAttribute('aria-hidden', 'true');
  }

  if (hamburgerBtn) {
    hamburgerBtn.addEventListener('click', function () {
      if (document.body.classList.contains('tc_drawer_open')) closeDrawer();
      else openDrawer();
    });
  }
  if (drawerClose) drawerClose.addEventListener('click', closeDrawer);
  if (backdrop)    backdrop.addEventListener('click', closeDrawer);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && document.body.classList.contains('tc_drawer_open')) closeDrawer();
  });
  // Drawer items navigate normally — but if they're #anchors on the same
  // page, close the drawer first so the user actually sees the anchor.
  document.querySelectorAll('.tc_drawer_item').forEach(function (a) {
    a.addEventListener('click', function () {
      // Always close — even external links benefit (drawer doesn't linger).
      closeDrawer();
    });
  });

  // ---------- Code copy buttons ----------
  document.querySelectorAll('.tc_codeblock_copy').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var targetId = btn.getAttribute('data-copy-target');
      if (!targetId) return;
      var target = document.getElementById(targetId);
      if (!target) return;
      var text = target.textContent;
      var fallback = function () {
        try {
          var ta = document.createElement('textarea');
          ta.value = text;
          ta.style.position = 'fixed'; ta.style.opacity = '0';
          document.body.appendChild(ta);
          ta.select();
          document.execCommand('copy');
          document.body.removeChild(ta);
          flash();
        } catch (e) { console.warn('[tc] copy fallback failed', e); }
      };
      var flash = function () {
        var orig = btn.textContent;
        btn.textContent = 'Copied';
        btn.classList.add('is_copied');
        setTimeout(function () {
          btn.textContent = orig;
          btn.classList.remove('is_copied');
        }, 1400);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(flash, fallback);
      } else {
        fallback();
      }
    });
  });

  // ---------- Recipient chips (auto-fill the recipient input) ----------
  document.querySelectorAll('.tc_recents').forEach(function (chipRow) {
    chipRow.addEventListener('click', function (e) {
      var chip = e.target.closest('.tc_recent_chip');
      if (!chip) return;
      var match = chip.textContent.match(/@([\w_-]+)/);
      if (!match) return;
      var input = chipRow.previousElementSibling && chipRow.previousElementSibling.querySelector('input[name="recipient"]');
      if (input) {
        input.value = '@' + match[1];
        input.focus();
      }
    });
  });

  // ---------- Amount input — digits + one decimal ----------
  document.querySelectorAll('.tc_amount_input').forEach(function (input) {
    input.addEventListener('input', function () {
      var v = this.value.replace(/[^\d.]/g, '');
      var parts = v.split('.');
      if (parts.length > 2) v = parts[0] + '.' + parts.slice(1).join('');
      this.value = v;
    });
  });
})();
