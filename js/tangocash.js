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

  // ---------- BrainLock sign-in (in-page iframe via same-origin proxy) ----------
  // The iframe loads from our OWN origin under /_bl/ — the BrainLock
  // SDK's handleEmbed() route proxies every request through to
  // brainlock.id. Because the iframe is same-origin with this page,
  // cookies behave as first-party. No CHIPS, no Storage Access, no
  // popup. Works in every browser including Safari.
  var SIGNIN_START_URL = '/auth/start.json.php';

  function blOpenSignin(e) {
    e.preventDefault();

    // 1. Build + mount the iframe modal IMMEDIATELY (no flash).
    // Full-viewport iframe. The BrainLock overlay inside it has its own
    // dim/backdrop and scales itself to whatever viewport we give it —
    // same as visiting brainlock.id directly. No outer dim, no centered
    // card; just hand it the whole screen.
    var iframe = document.createElement('iframe');
    iframe.id = 'bl_signin_iframe';
    iframe.title = 'Sign in with BrainLock';
    iframe.style.cssText = [
      'position:fixed',
      'inset:0',
      'width:100vw',
      'height:100vh',
      'border:0',
      'z-index:9999',
      'background:transparent',
      'opacity:0',
      'transition:opacity 220ms ease'
    ].join(';');
    document.body.appendChild(iframe);
    document.body.style.overflow = 'hidden';

    // Animate in.
    requestAnimationFrame(function () { iframe.style.opacity = '1'; });

    function teardown() {
      iframe.style.opacity = '0';
      setTimeout(function () {
        try { document.body.removeChild(iframe); } catch (e) {}
        document.body.style.overflow = '';
      }, 220);
    }

    // Esc closes the iframe (cancels sign-in). No backdrop click since
    // the iframe is the whole viewport — the user uses the X inside
    // the BrainLock UI to cancel.
    var onKey = function (ev) {
      if (ev.key === 'Escape') {
        window.removeEventListener('keydown', onKey);
        window.removeEventListener('message', onMessage);
        teardown();
      }
    };
    window.addEventListener('keydown', onKey);

    // 2. Listen for the BrainLock postMessage handoff. In proxy mode
    //    the iframe lives on OUR origin, so the message comes from
    //    window.location.origin — not brainlock.id.
    var SAME_ORIGIN = window.location.origin;
    var onMessage = function (ev) {
      if (ev.origin !== SAME_ORIGIN) return;
      var data = ev.data || {};
      if (data.type !== 'brainlock:auth' || !data.url) return;
      window.removeEventListener('message', onMessage);
      window.removeEventListener('keydown', onKey);
      teardown();
      window.location.href = data.url;
    };
    window.addEventListener('message', onMessage);

    // 3. Fetch the auth URL and point the iframe at it. Server returns
    //    iframe_url which is /_bl/auth/<sid>?embed=iframe&proxy_base=…
    //    already, so we just assign it directly.
    fetch(SIGNIN_START_URL, { method: 'POST', credentials: 'same-origin' })
      .then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then(function (data) {
        if (!data.iframe_url) throw new Error('No iframe_url in start_session response');
        iframe.src = data.iframe_url;
      })
      .catch(function (err) {
        console.error('[bl_signin] failed to start session:', err);
        window.removeEventListener('message', onMessage);
        window.removeEventListener('keydown', onKey);
        teardown();
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
