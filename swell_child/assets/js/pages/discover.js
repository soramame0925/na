(() => {
  if (window.__soraDiscoverBound) return; window.__soraDiscoverBound = true;
  document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    const players = document.querySelectorAll('.short-audio-player[data-embed]');

    const decodeHTML = html => {
      const tmp = document.createElement('textarea');
      tmp.innerHTML = html;
      return tmp.value;
    };

    const parseNumber = val => {
      if (!val) return null;
      const num = parseFloat(String(val).replace(/[^\d.]/g, ''));
      return Number.isFinite(num) ? num : null;
    };

    const getDimensionsFromEmbed = embed => {
      if (!embed) return null;
      const wrapper = document.createElement('div');
      wrapper.innerHTML = decodeHTML(embed);
      const iframe = wrapper.querySelector('iframe');
      if (!iframe) return null;
      const w = parseNumber(iframe.getAttribute('width') || iframe.style.width);
      const h = parseNumber(iframe.getAttribute('height') || iframe.style.height);
      if (!w || !h) return null;
      return { width: w, height: h, ratio: h / w };
    };

    const setPlaceholder = (el, dims) => {
      if (!el || !dims) return;
      const { ratio, width, height } = dims;
      el.style.aspectRatio = `${width} / ${height}`;
      const resize = () => {
        el.style.height = el.offsetWidth * ratio + 'px';
      };
      resize();
      window.addEventListener('resize', resize);
    };

    const insertIframe = el => {
      if (!el || !el.dataset.embed) return;

      const html = decodeHTML(el.dataset.embed);
      el.innerHTML = html;

      const iframe = el.querySelector('iframe');
      const dims = getDimensionsFromEmbed(el.dataset.embed);

      if (iframe) {
        iframe.style.width = '100%';
        iframe.style.height = '100%';
        iframe.removeAttribute('width');
        iframe.removeAttribute('height');
      }

      if (dims) {
        const { ratio, width, height } = dims;
        el.style.aspectRatio = `${width} / ${height}`;
        const resize = () => {
          el.style.height = el.offsetWidth * ratio + 'px';
        };
        resize();
        window.addEventListener('resize', resize);
      }

      el.removeAttribute('data-embed');
    };

    players.forEach(el => {
      if (!el || !el.dataset.embed) return;
      const dims = getDimensionsFromEmbed(el.dataset.embed);
      if (dims) setPlaceholder(el, dims);
    });

    if (!('IntersectionObserver' in window) || players.length === 0) {
      players.forEach(el => insertIframe(el));
    } else {
      const io = new IntersectionObserver(entries => {
        entries.forEach(entry => {
          if (entry.isIntersecting && entry.target) {
            insertIframe(entry.target);
            io.unobserve(entry.target);
          }
        });
      }, { rootMargin: '200px 0px' });

      players.forEach(el => {
        if (el) io.observe(el);
      });
    }
  });
})();
