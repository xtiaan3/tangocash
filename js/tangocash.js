/**
 * TangoCash — v0 scaffold JS.
 *
 * Almost-empty for now. Will hold:
 *   - BrainLock popup launcher (Sign in with BrainLock click)
 *   - send / request form submission via fetch
 *   - any micro-interactions on chips / amount field
 *
 * Kept inline-script-free for now so it's easy to follow.
 */
(function () {
  'use strict';

  // Auto-fill recipient input when a "recent" chip is clicked.
  document.querySelectorAll('.tc_recents').forEach(function (chipRow) {
    chipRow.addEventListener('click', function (e) {
      var chip = e.target.closest('.tc_recent_chip');
      if (!chip) return;
      var handle = chip.textContent.trim().replace(/^@/, '').replace(/^[A-Z]\s+@?/, '');
      // Pull the @handle from the chip's text, strip avatar initial.
      var match = chip.textContent.match(/@([\w_-]+)/);
      if (!match) return;
      var input = chipRow.previousElementSibling && chipRow.previousElementSibling.querySelector('input[name="recipient"]');
      if (input) {
        input.value = '@' + match[1];
        input.focus();
      }
    });
  });

  // Amount input — strip non-numeric, allow one decimal.
  document.querySelectorAll('.tc_amount_input').forEach(function (input) {
    input.addEventListener('input', function () {
      var v = this.value.replace(/[^\d.]/g, '');
      var parts = v.split('.');
      if (parts.length > 2) v = parts[0] + '.' + parts.slice(1).join('');
      this.value = v;
    });
  });
})();
