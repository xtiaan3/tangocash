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

  // ---------- BrainLock sign-in (in-page iframe) ----------
  // EXPERIMENTAL: instead of a popup window we inject a full-viewport
  // iframe + dim backdrop. BrainLock serves the embed=iframe page with
  // a `CSP: frame-ancestors https://tangocash.etonica.com` header that
  // lets us frame it. When the user finishes, the iframe postMessages
  // back; we tear it down and navigate to /auth/callback.php with the
  // token.
  //
  // Fallback to the old popup flow if anything blocks iframe init.
  var BL_ORIGIN = 'https://brainlock.id';
  var SIGNIN_START_URL = '/auth/start.json.php';

  function blOpenSignin(e) {
    e.preventDefault();

    // 1. Build + mount the iframe modal IMMEDIATELY (no flash).
    var backdrop = document.createElement('div');
    backdrop.id = 'bl_signin_iframe_backdrop';
    backdrop.style.cssText = [
      'position:fixed',
      'inset:0',
      'z-index:9998',
      'background:rgba(5,7,16,0.78)',
      'backdrop-filter:blur(6px)',
      '-webkit-backdrop-filter:blur(6px)',
      'opacity:0',
      'transition:opacity 200ms ease'
    ].join(';');

    var iframeWrap = document.createElement('div');
    iframeWrap.style.cssText = [
      'position:fixed',
      'inset:0',
      'z-index:9999',
      'display:grid',
      'place-items:center',
      'padding:24px',
      'pointer-events:none'
    ].join(';');

    var iframe = document.createElement('iframe');
    iframe.id = 'bl_signin_iframe';
    iframe.title = 'Sign in with BrainLock';
    iframe.style.cssText = [
      'width:min(480px,100%)',
      'height:min(720px,100%)',
      'max-height:96vh',
      'border:0',
      'border-radius:20px',
      'background:#0a0e1f',
      'box-shadow:0 30px 80px -20px rgba(0,0,0,0.6)',
      'opacity:0',
      'transform:translateY(10px)',
      'transition:opacity 220ms ease, transform 220ms ease',
      'pointer-events:auto'
    ].join(';');
    iframeWrap.appendChild(iframe);
    document.body.appendChild(backdrop);
    document.body.appendChild(iframeWrap);
    document.body.style.overflow = 'hidden';

    // Animate in.
    requestAnimationFrame(function () {
      backdrop.style.opacity = '1';
      iframe.style.opacity = '1';
      iframe.style.transform = 'translateY(0)';
    });

    function teardown() {
      backdrop.style.opacity = '0';
      iframe.style.opacity = '0';
      setTimeout(function () {
        try { document.body.removeChild(backdrop); } catch (e) {}
        try { document.body.removeChild(iframeWrap); } catch (e) {}
        document.body.style.overflow = '';
      }, 220);
    }

    // Esc / backdrop click closes the iframe (cancels sign-in).
    backdrop.addEventListener('click', function () {
      window.removeEventListener('message', onMessage);
      teardown();
    });
    var onKey = function (ev) {
      if (ev.key === 'Escape') {
        window.removeEventListener('keydown', onKey);
        window.removeEventListener('message', onMessage);
        teardown();
      }
    };
    window.addEventListener('keydown', onKey);

    // 2. Listen for the BrainLock postMessage handoff.
    var onMessage = function (ev) {
      if (ev.origin !== BL_ORIGIN) return;
      var data = ev.data || {};
      if (data.type !== 'brainlock:auth' || !data.url) return;
      window.removeEventListener('message', onMessage);
      window.removeEventListener('keydown', onKey);
      teardown();
      window.location.href = data.url;
    };
    window.addEventListener('message', onMessage);

    // 3. Fetch the auth URL and point the iframe at it.
    fetch(SIGNIN_START_URL, { method: 'POST', credentials: 'same-origin' })
      .then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then(function (data) {
        if (!data.url) throw new Error('No URL in start_session response');
        var sep = data.url.indexOf('?') === -1 ? '?' : '&';
        iframe.src = data.url + sep + 'embed=iframe';
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
