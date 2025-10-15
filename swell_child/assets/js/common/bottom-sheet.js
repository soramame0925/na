/* bottom-sheet.js */
(function(){
  if (window.__soraBsBound) return; window.__soraBsBound = true;
  'use strict';

  function init(){
    // ===== DOM =====
    var sheet   = document.getElementById('sora-review-sheet');
    var overlay = document.getElementById('sora-review-overlay');
    var bodyEl  = document.getElementById('sora-bs-content');
    var titleEl = document.getElementById('sora-bs-title');
    var pageWrap = document.getElementById('all_wrapp');
    var lastTrigger = null;

    if(!sheet || !overlay || !bodyEl) return;

    function addClass(el, c){ if(el && el.classList) el.classList.add(c); }
    function removeClass(el, c){ if(el && el.classList) el.classList.remove(c); }

    function openSheet(){
      addClass(sheet, 'is-open');
      addClass(overlay, 'is-open');
      addClass(document.body, 'is-bottomsheet-open');
      overlay.setAttribute('aria-hidden','false');
      sheet.setAttribute('aria-hidden','false');
      if (pageWrap) pageWrap.setAttribute('inert','');
      var closeBtn = sheet.querySelector('.sora-bs-close');
      var focusTarget = closeBtn;
      if (!focusTarget) {
        var focusable = sheet.querySelector('[tabindex], a, button, input, select, textarea');
        if (focusable) {
          focusTarget = focusable;
        } else {
          sheet.setAttribute('tabindex', '-1');
          focusTarget = sheet;
        }
      }
      if (focusTarget && typeof focusTarget.focus === 'function') {
        focusTarget.focus();
      }
    }

    function closeSheet(){
      if (!sheet || !overlay || !bodyEl) return;
      var focusEl = lastTrigger && typeof lastTrigger.focus === 'function' ? lastTrigger : document.body;
      if (focusEl && typeof focusEl.focus === 'function') focusEl.focus();
      removeClass(sheet, 'is-open');
      removeClass(overlay, 'is-open');
      removeClass(document.body, 'is-bottomsheet-open');
      if (pageWrap) pageWrap.removeAttribute('inert');
      bodyEl.innerHTML = '';
      if(titleEl) titleEl.textContent = 'Review';
      requestAnimationFrame(function(){
        overlay.setAttribute('aria-hidden','true');
        sheet.setAttribute('aria-hidden','true');
      });
    }

    // close handlers
    function handleClose(e){
      if (e) {
        e.preventDefault();
        e.stopPropagation();
      }
      closeSheet();
    }

    overlay.addEventListener('click', handleClose);
    var closeBtn = sheet.querySelector('.sora-bs-close');
    if (closeBtn) closeBtn.addEventListener('click', handleClose);

    document.addEventListener('keydown', function(e){
      if ((e.key === 'Escape' || e.keyCode === 27) && sheet.classList.contains('is-open')) closeSheet();
    });

    sheet.addEventListener('keydown', function(e){
      if (!sheet.classList.contains('is-open')) return;
      if (e.key !== 'Tab' && e.keyCode !== 9) return;
      var focusable = sheet.querySelectorAll('a[href], area[href], input:not([disabled]):not([tabindex="-1"]), select:not([disabled]):not([tabindex="-1"]), textarea:not([disabled]):not([tabindex="-1"]), button:not([disabled]):not([tabindex="-1"]), [tabindex]:not([tabindex="-1"])');
      if (!focusable.length) return;
      var first = focusable[0];
      var last = focusable[focusable.length - 1];
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    });

    // swipe down to close
    var startY = null;
    sheet.addEventListener('touchstart', function(e){
      if (e.touches && e.touches[0]) startY = e.touches[0].clientY;
    }, {passive: true});

    sheet.addEventListener('touchmove', function(e){
      if (startY === null) return;
      var currentY = (e.touches && e.touches[0]) ? e.touches[0].clientY : 0;
      var dy = currentY - startY;
      var atTop = bodyEl.scrollTop <= 0;
      if (dy > 40 && atTop) {
        closeSheet();
        startY = null;
      }
    }, {passive: true});

    sheet.addEventListener('touchend', function(){ startY = null; });
    // Ajax load linked review into bottom sheet
    function loadLinkedReviewIntoSheet(postId, containerEl) {
      if (!window.SORA_BS) return;
      var fd = new FormData();
      fd.append('action', 'sora_get_linked_review');
      fd.append('post_id', postId);
      fd.append('_ajax_nonce', SORA_BS.nonce);

      containerEl.innerHTML = '<div class="sora-bs-loading">Loading…</div>';

      fetch(SORA_BS.ajax_url, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function(res){ return res.json(); })
        .then(function(json){
          if (!json || !json.success) {
            var msg = json && json.data && json.data.message ? json.data.message : 'Failed to load review.';
            throw new Error(msg);
          }
          containerEl.innerHTML = json.data.html;
        })
        .catch(function(err){
          containerEl.innerHTML = '<div class="sora-bs-error">' + String(err.message || err) + '</div>';
        });
    }

    document.addEventListener('click', function(e){
      var btn = e.target.closest('.review-button[data-post-id]');
      if (!btn) return;

      var postId = btn.getAttribute('data-post-id');
      if (!postId) return;

      e.preventDefault();
      lastTrigger = btn;

      openSheet();
      loadLinkedReviewIntoSheet(postId, bodyEl);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
