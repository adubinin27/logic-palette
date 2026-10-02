/* Logic Palette — inline editor (loaded only for a logged-in admin) · @author STM Webcode Systems (stm-project.ru) */
(() => {
  const meta = (name) => document.querySelector(`meta[name="${name}"]`)?.content || '';
  const csrf = meta('csrf-token');
  const lang = meta('editor-lang') || 'ru';
  const uploadMax = parseInt(meta('upload-max'), 10) || 2 * 1024 * 1024;
  const fields = [];
  const saveBtn = document.querySelector('[data-ed="save"]');
  const toast = document.querySelector('.ed-toast');
  const original = new Map();
  const dirty = new Set();
  const editedEl = new Map();

  const valueOf = (el) => (el.hasAttribute('data-html')
    ? el.innerHTML.replace(/<(\/?)strong\b[^>]*>/gi, '<$1b>').replace(/&nbsp;/g, ' ')
    : el.innerText).replace(/\s+/g, ' ').trim();
  const same = (field) => fields.filter((el) => el.dataset.field === field);
  const mb = (bytes) => `${(bytes / 1048576).toFixed(1).replace(/\.0$/, '')} МБ`;

  let toastTimer = 0;
  const notify = (text, isError = false) => {
    if (!toast) return;
    toast.textContent = text;
    toast.classList.toggle('is-error', isError);
    toast.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toast.hidden = true; }, isError ? 5000 : 1800);
  };

  const refreshSaveBtn = () => {
    if (!saveBtn) return;
    saveBtn.disabled = dirty.size === 0;
    saveBtn.textContent = dirty.size ? `Сохранить (${dirty.size})` : 'Сохранить';
  };

  const request = async (url, init) => {
    const res = await fetch(url, { method: 'POST', credentials: 'same-origin', ...init });
    let data = null;
    try { data = await res.json(); } catch (_) { /* non-JSON error page */ }
    if (!res.ok || !data?.ok) throw new Error(data?.error || `Ошибка ${res.status}`);
    return data;
  };

  const api = (payload) => request('save.php', {
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
    body: JSON.stringify({ lang, ...payload }),
  });

  const applyCounter = (c) => {
    if (!c) return;
    const el = document.querySelector('[data-counter]');
    if (el) {
      el.querySelector('[data-counter-num]').textContent = c.number;
      el.querySelector('[data-counter-label]').textContent = c.label;
      el.classList.toggle('counter--off', !c.enabled);
      el.title = c.enabled ? '' : 'Скрыт от посетителей';
    }
    const real = document.querySelector('[data-ed="real"]');
    const total = document.querySelector('[data-ed="total"]');
    if (real) real.textContent = String(c.real);
    if (total) total.textContent = new Intl.NumberFormat('ru-RU').format(c.total);
  };

  const applySaved = (saved) => {
    saved.forEach(({ field, content }) => {
      same(field).forEach((el) => {
        if (el.hasAttribute('data-html')) el.innerHTML = content; else el.textContent = content;
        original.set(el, valueOf(el));
        el.classList.remove('is-dirty');
      });
      dirty.delete(field);
      if (field === 'meta_title') document.title = content;
    });
    refreshSaveBtn();
  };

  const saveFields = async (list, quiet = false) => {
    if (!list.length) return;
    const changes = list.map((field) => ({ field, content: valueOf(editedEl.get(field) || same(field)[0]) }));
    try {
      const data = await api({ changes });
      applySaved(data.saved);
      if (!quiet || data.saved.length) notify('Сохранено');
    } catch (err) {
      notify(err.message, true);
    }
  };

  const bindField = (el) => {
    fields.push(el);
    original.set(el, valueOf(el));

    el.addEventListener('input', () => {
      const changed = valueOf(el) !== original.get(el);
      editedEl.set(el.dataset.field, el);
      el.classList.toggle('is-dirty', changed);
      if (changed) dirty.add(el.dataset.field); else dirty.delete(el.dataset.field);
      refreshSaveBtn();
    });

    el.addEventListener('blur', () => {
      if (dirty.has(el.dataset.field)) saveFields([el.dataset.field], true);
    });

    el.addEventListener('keydown', (ev) => {
      if (ev.key === 'Enter') { ev.preventDefault(); el.blur(); }
      if (ev.key === 'Escape') {
        ev.preventDefault();
        const prev = original.get(el);
        if (el.hasAttribute('data-html')) el.innerHTML = prev; else el.textContent = prev;
        el.classList.remove('is-dirty');
        dirty.delete(el.dataset.field);
        refreshSaveBtn();
        el.blur();
      }
      if (ev.key === ' ' && el.closest('summary')) ev.stopPropagation();
    });

    el.addEventListener('paste', (ev) => {
      ev.preventDefault();
      const text = (ev.clipboardData || window.clipboardData).getData('text/plain').replace(/\s+/g, ' ');
      document.execCommand('insertText', false, text);
    });

    el.addEventListener('drop', (ev) => ev.preventDefault());
  };

  const unbindFields = (root) => {
    root.querySelectorAll('[data-editable]').forEach((el) => {
      const i = fields.indexOf(el);
      if (i >= 0) fields.splice(i, 1);
      if (!same(el.dataset.field).length) dirty.delete(el.dataset.field);
    });
    refreshSaveBtn();
  };

  document.querySelectorAll('[data-editable]').forEach(bindField);

  document.addEventListener('click', (ev) => {
    const editable = ev.target.closest('[data-editable]');
    if (!editable) return;
    const link = editable.closest('a, summary');
    if (link && !(ev.metaKey || ev.ctrlKey)) ev.preventDefault();
  }, true);

  saveBtn?.addEventListener('click', () => saveFields([...dirty]));

  document.addEventListener('keydown', (ev) => {
    if ((ev.metaKey || ev.ctrlKey) && ev.key.toLowerCase() === 's') {
      ev.preventDefault();
      document.activeElement?.blur?.();
      saveFields([...dirty]);
    }
  });

  window.addEventListener('beforeunload', (ev) => {
    if (dirty.size) { ev.preventDefault(); ev.returnValue = ''; }
  });

  const counterForm = document.querySelector('[data-ed="counter"]');
  counterForm?.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const base = Math.max(0, Math.min(10000000, parseInt(counterForm.base.value, 10) || 0));
    counterForm.base.value = String(base);
    try {
      const data = await api({ counter: { base, enabled: counterForm.enabled.checked } });
      applyCounter(data.counter);
      notify(data.counter.enabled ? `Счётчик: ${data.counter.number}` : 'Счётчик скрыт от посетителей');
    } catch (err) {
      notify(err.message, true);
    }
  });

  const bar = document.querySelector('.ed-bar');
  if (bar && 'ResizeObserver' in window) {
    new ResizeObserver(() => {
      document.documentElement.style.setProperty('--ed-h', `${bar.offsetHeight}px`);
    }).observe(bar);
  }

  const seoToggle = document.querySelector('[data-ed="seo-toggle"]');
  const seoForm = document.querySelector('[data-ed="seo"]');
  seoToggle?.addEventListener('click', () => {
    seoForm.hidden = !seoForm.hidden;
    seoToggle.setAttribute('aria-expanded', String(!seoForm.hidden));
    if (!seoForm.hidden) seoForm.meta_title.focus();
  });
  seoForm?.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    try {
      const data = await api({ changes: [
        { field: 'meta_title', content: seoForm.meta_title.value },
        { field: 'meta_desc', content: seoForm.meta_desc.value },
      ] });
      data.saved.forEach(({ field, content }) => {
        seoForm[field].value = content;
        if (field === 'meta_title') document.title = content;
      });
      notify('SEO сохранено');
    } catch (err) {
      notify(err.message, true);
    }
  });

  /* ---------- Images: drag & drop replacement and the screenshot gallery ---------- */

  const IMAGE_TYPES = /^image\/(jpeg|png|webp|gif)$/;
  const MAX_SIDE = 3200;

  /** Downscales in the browser when the file exceeds the server upload limit. */
  const prepareFile = async (file) => {
    if (!IMAGE_TYPES.test(file.type)) throw new Error(`«${file.name}»: подходят JPG, PNG, WebP.`);
    if (file.size <= uploadMax) return file;
    if (!('createImageBitmap' in window)) throw new Error(`«${file.name}» больше ${mb(uploadMax)}.`);
    const bmp = await createImageBitmap(file);
    let scale = Math.min(1, MAX_SIDE / Math.max(bmp.width, bmp.height));
    for (let i = 0; i < 6; i += 1) {
      const canvas = document.createElement('canvas');
      canvas.width = Math.max(1, Math.round(bmp.width * scale));
      canvas.height = Math.max(1, Math.round(bmp.height * scale));
      const ctx = canvas.getContext('2d');
      const type = file.type === 'image/png' && i < 2 ? 'image/png' : 'image/jpeg';
      if (type === 'image/jpeg') { ctx.fillStyle = '#161618'; ctx.fillRect(0, 0, canvas.width, canvas.height); }
      ctx.drawImage(bmp, 0, 0, canvas.width, canvas.height);
      const blob = await new Promise((resolve) => canvas.toBlob(resolve, type, 0.88));
      if (blob && blob.size <= uploadMax) {
        return new File([blob], file.name.replace(/\.\w+$/, type === 'image/png' ? '.png' : '.jpg'), { type });
      }
      scale *= 0.8;
    }
    throw new Error(`«${file.name}» не удалось ужать до ${mb(uploadMax)}.`);
  };

  const media = async (fieldsData, file) => {
    const fd = new FormData();
    Object.entries({ lang, ...fieldsData }).forEach(([k, v]) => fd.append(k, String(v)));
    if (file) {
      const ready = await prepareFile(file);
      fd.append('image', ready, ready.name);
    }
    return request('media.php', { headers: { 'X-CSRF-Token': csrf }, body: fd });
  };

  const busy = async (el, fn) => {
    el.classList.add('is-busy');
    try { return await fn(); } finally { el.classList.remove('is-busy'); }
  };

  const pickFiles = (multiple = false) => new Promise((resolve) => {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/jpeg,image/png,image/webp,image/gif';
    input.multiple = multiple;
    input.addEventListener('change', () => resolve([...input.files]));
    input.click();
  });

  const hasFiles = (ev) => [...(ev.dataTransfer?.types || [])].includes('Files');

  const dropZone = (el, onFiles, label, multiple = false) => {
    let depth = 0;
    el.dataset.dropLabel = label;
    const reset = () => { depth = 0; el.classList.remove('is-drop'); };
    el.addEventListener('dragenter', (ev) => {
      if (!hasFiles(ev)) return;
      ev.preventDefault();
      ev.stopPropagation();
      depth += 1;
      el.classList.add('is-drop');
    });
    el.addEventListener('dragover', (ev) => {
      if (!hasFiles(ev)) return;
      ev.preventDefault();
      ev.stopPropagation();
      ev.dataTransfer.dropEffect = 'copy';
    });
    el.addEventListener('dragleave', (ev) => {
      if (!hasFiles(ev)) return;
      ev.stopPropagation();
      depth = Math.max(0, depth - 1);
      if (!depth) el.classList.remove('is-drop');
    });
    el.addEventListener('drop', (ev) => {
      if (!hasFiles(ev)) return;
      ev.preventDefault();
      ev.stopPropagation();
      reset();
      const files = [...ev.dataTransfer.files].filter((f) => f.type.startsWith('image/'));
      if (!files.length) { notify('Перетащите файл картинки (JPG, PNG, WebP).', true); return; }
      onFiles(multiple ? files : files.slice(0, 1));
    });
  };

  document.addEventListener('dragover', (ev) => { if (hasFiles(ev)) ev.preventDefault(); });
  document.addEventListener('drop', (ev) => {
    if (!hasFiles(ev)) return;
    ev.preventDefault();
    notify('Перетащите картинку на нужное изображение или в галерею.', true);
  });
  document.addEventListener('dragend', () => document.querySelectorAll('.is-drop').forEach((el) => el.classList.remove('is-drop')));

  const makeBar = (buttons) => {
    const wrap = document.createElement('div');
    wrap.className = 'ed-media__bar';
    buttons.forEach(({ text, title, cls, onClick }) => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = `ed-media__btn${cls ? ` ${cls}` : ''}`;
      b.textContent = text;
      b.title = title;
      b.setAttribute('aria-label', title);
      b.addEventListener('click', (ev) => { ev.preventDefault(); ev.stopPropagation(); onClick(); });
      wrap.append(b);
    });
    return wrap;
  };

  const setImage = (img, link, { src, srcset = '', w, h }) => {
    img.removeAttribute('sizes');
    if (srcset) img.srcset = srcset; else img.removeAttribute('srcset');
    img.src = src;
    if (w && h) { img.width = w; img.height = h; }
    if (link) link.href = src;
  };

  /* Single images (hero, about, MacBook) */
  document.querySelectorAll('[data-img-slot]').forEach((node) => {
    let box = node;
    if (node.tagName === 'IMG') {
      box = document.createElement('span');
      box.className = 'ed-media--inline';
      box.dataset.imgSlot = node.dataset.imgSlot;
      if (node.hasAttribute('data-img-custom')) box.setAttribute('data-img-custom', '');
      node.removeAttribute('data-img-slot');
      node.removeAttribute('data-img-custom');
      node.replaceWith(box);
      box.append(node);
    }
    box.classList.add('ed-media');
    const slot = box.dataset.imgSlot;
    const img = box.querySelector('img');

    const apply = (image) => {
      setImage(img, null, image);
      const link = box.querySelector('a[data-lightbox]');
      if (link) link.href = image.full;
      box.toggleAttribute('data-img-custom', image.custom);
    };
    const upload = (file) => busy(box, async () => {
      try {
        apply((await media({ action: 'slot_upload', slot }, file)).image);
        notify('Картинка заменена');
      } catch (err) { notify(err.message, true); }
    });
    const reset = () => {
      if (!window.confirm('Вернуть исходную картинку?')) return;
      busy(box, async () => {
        try {
          apply((await media({ action: 'slot_reset', slot })).image);
          notify('Исходная картинка возвращена');
        } catch (err) { notify(err.message, true); }
      });
    };

    box.append(makeBar([
      { text: 'Заменить', title: 'Заменить картинку (или перетащите файл сюда)', onClick: async () => { const [f] = await pickFiles(); if (f) upload(f); } },
      { text: '↺', title: 'Вернуть исходную картинку', cls: 'ed-media__reset', onClick: reset },
    ]));
    dropZone(box, ([f]) => upload(f), 'Отпустите — заменить картинку');
  });

  /* Screenshot gallery */
  const gallery = document.querySelector('[data-gallery]');
  if (gallery) {
    const addTile = document.createElement('button');
    addTile.type = 'button';
    addTile.className = 'ed-gallery-add';
    addTile.innerHTML = '<b aria-hidden="true">+</b><span>Добавить скриншот</span><small>Перетащите файлы сюда или нажмите</small>';

    const buildShot = (item) => {
      const fig = document.createElement('figure');
      fig.className = item.wide ? 'screen screen--wide' : 'screen';
      fig.dataset.galleryId = String(item.id);
      const a = document.createElement('a');
      a.href = item.src;
      a.dataset.lightbox = '';
      const img = document.createElement('img');
      img.src = item.src;
      img.alt = item.caption;
      img.loading = 'lazy';
      if (item.w && item.h) { img.width = item.w; img.height = item.h; }
      a.append(img);
      const cap = document.createElement('figcaption');
      Object.assign(cap.dataset, { editable: '', section: 'text', field: item.field, placeholder: 'Подпись к скриншоту' });
      cap.contentEditable = 'true';
      cap.spellcheck = true;
      cap.textContent = item.caption;
      fig.append(a, cap);
      bindField(cap);
      return fig;
    };

    const neighbour = (fig, dir) => {
      const sib = dir < 0 ? fig.previousElementSibling : fig.nextElementSibling;
      return sib?.matches('[data-gallery-id]') ? sib : null;
    };

    const setupShot = (fig) => {
      const id = () => fig.dataset.galleryId;
      const move = (dir) => {
        const sib = neighbour(fig, dir);
        if (!sib) return;
        busy(fig, async () => {
          try {
            await media({ action: 'gallery_move', id: id(), dir });
            if (dir < 0) sib.before(fig); else sib.after(fig);
          } catch (err) { notify(err.message, true); }
        });
      };
      const replace = (file) => busy(fig, async () => {
        try {
          const { item } = await media({ action: 'gallery_replace', id: id() }, file);
          setImage(fig.querySelector('img'), fig.querySelector('a[data-lightbox]'), item);
          setWide(item.wide);
          notify('Скриншот заменён');
        } catch (err) { notify(err.message, true); }
      });
      const remove = () => {
        if (!window.confirm('Удалить этот скриншот из галереи?')) return;
        busy(fig, async () => {
          try {
            await media({ action: 'gallery_delete', id: id() });
            unbindFields(fig);
            fig.remove();
            notify('Скриншот удалён');
          } catch (err) { notify(err.message, true); }
        });
      };

      const toggleWide = () => busy(fig, async () => {
        try {
          const { item } = await media({ action: 'gallery_wide', id: id(), wide: fig.classList.contains('screen--wide') ? 0 : 1 });
          setWide(item.wide);
          notify(item.wide ? 'Превью на всю ширину' : 'Обычное превью');
        } catch (err) { notify(err.message, true); }
      });

      fig.classList.add('ed-media');
      const bar = makeBar([
        { text: '‹', title: 'Переместить левее', onClick: () => move(-1) },
        { text: '›', title: 'Переместить правее', onClick: () => move(1) },
        { text: '⇔', title: 'Превью на всю ширину галереи (вкл/выкл)', cls: 'ed-media__wide', onClick: toggleWide },
        { text: 'Заменить', title: 'Заменить скриншот (или перетащите файл сюда)', onClick: async () => { const [f] = await pickFiles(); if (f) replace(f); } },
        { text: '✕', title: 'Удалить скриншот', cls: 'ed-media__btn--danger', onClick: remove },
      ]);
      const wideBtn = bar.querySelector('.ed-media__wide');
      function setWide(on) {
        fig.classList.toggle('screen--wide', on);
        wideBtn.classList.toggle('is-on', on);
        wideBtn.setAttribute('aria-pressed', String(on));
      }
      setWide(fig.classList.contains('screen--wide'));
      fig.append(bar);
      dropZone(fig, ([f]) => replace(f), 'Отпустите — заменить скриншот');
    };

    const addFiles = (files) => busy(addTile, async () => {
      let added = 0;
      for (const file of files) {
        try {
          const { item } = await media({ action: 'gallery_add' }, file);
          const fig = buildShot(item);
          addTile.before(fig);
          setupShot(fig);
          added += 1;
        } catch (err) {
          notify(err.message, true);
          return;
        }
      }
      if (added) notify(added > 1 ? `Добавлено скриншотов: ${added}` : 'Скриншот добавлен — допишите подпись');
    });

    gallery.querySelectorAll('[data-gallery-id]').forEach(setupShot);
    gallery.append(addTile);
    addTile.addEventListener('click', async () => { const files = await pickFiles(true); if (files.length) addFiles(files); });
    dropZone(addTile, addFiles, 'Отпустите — добавить', true);
    dropZone(gallery, addFiles, 'Отпустите — добавить в галерею', true);
  }
})();
