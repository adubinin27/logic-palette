/* Logic Palette landing · @author STM Webcode Systems (stm-project.ru) */
(() => {
  const box = document.querySelector('.lightbox');

  if (box) {
    const img = box.querySelector('img');
    const text = box.querySelector('.lightbox__text');
    const count = box.querySelector('.lightbox__count');
    let links = [];
    let single = false;
    let index = 0;
    let opener = null;

    const show = (i) => {
      index = (i + links.length) % links.length;
      const a = links[index];
      const caption = a.closest('figure')?.querySelector('figcaption')?.textContent.trim()
        || a.querySelector('img')?.alt || '';
      img.src = a.getAttribute('href');
      img.alt = caption;
      text.textContent = caption;
      count.textContent = single ? '' : `${index + 1} / ${links.length}`;
    };
    const open = (from) => {
      opener = from;
      links = [...document.querySelectorAll('[data-lightbox]')]
        .filter((a) => a.dataset.lightbox === from.dataset.lightbox);
      single = links.length < 2;
      box.querySelectorAll('.lightbox__nav').forEach((b) => { b.hidden = single; });
      show(links.indexOf(from));
      box.hidden = false;
      document.body.classList.add('is-locked');
      box.querySelector('[data-lb="close"]').focus({ preventScroll: true });
    };
    const close = () => {
      box.hidden = true;
      document.body.classList.remove('is-locked');
      opener?.focus({ preventScroll: true });
    };

    document.addEventListener('click', (ev) => {
      const a = ev.target.closest('[data-lightbox]');
      if (!a || ev.defaultPrevented || ev.metaKey || ev.ctrlKey) return;
      ev.preventDefault();
      open(a);
    });

    box.addEventListener('click', (ev) => {
      const action = ev.target.closest('[data-lb]')?.dataset.lb;
      if (action === 'prev') show(index - 1);
      else if (action === 'next') show(index + 1);
      else if (action === 'close' || ev.target === box) close();
      else if (ev.target === img && !single) show(index + 1);
    });

    document.addEventListener('keydown', (ev) => {
      if (box.hidden) return;
      if (ev.key === 'Escape') close();
      else if (ev.key === 'ArrowLeft') { ev.preventDefault(); show(index - 1); }
      else if (ev.key === 'ArrowRight' || ev.key === ' ') { ev.preventDefault(); show(index + 1); }
      else if (ev.key === 'Home') show(0);
      else if (ev.key === 'End') show(links.length - 1);
    });

    let touchX = null;
    box.addEventListener('touchstart', (ev) => { touchX = ev.touches[0].clientX; }, { passive: true });
    box.addEventListener('touchend', (ev) => {
      if (touchX === null) return;
      const dx = ev.changedTouches[0].clientX - touchX;
      touchX = null;
      if (Math.abs(dx) > 50) show(index + (dx < 0 ? 1 : -1));
    });
  }

  document.querySelectorAll('[data-copy]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const src = document.querySelector(btn.dataset.copy);
      if (!src) return;
      try {
        await navigator.clipboard.writeText(src.textContent);
        btn.classList.add('is-done');
        btn.textContent = '✓';
        setTimeout(() => { btn.classList.remove('is-done'); btn.textContent = '⧉'; }, 1500);
      } catch (_) { /* clipboard unavailable (http) */ }
    });
  });
})();
