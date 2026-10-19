'use strict';

(() => {
  const root = document.getElementById('prm-mag');
  if (!root || root.dataset.prmInitialized === 'true') return;
  root.dataset.prmInitialized = 'true';
  const $ = (selector) => root.querySelector(selector);
  const $$ = (selector) => [...root.querySelectorAll(selector)];
  const numbers = new Intl.NumberFormat('fa-IR');
  const scriptURL = document.currentScript && document.currentScript.src;
  const sections = { all: 'همهٔ مقاله‌ها', buying: 'آموزش حسابداری', start: 'شروع حسابداری', skills: 'مدیریت مالی', checklists: 'چک‌لیست حسابداری' };
  const owns = (object, key) => Object.prototype.hasOwnProperty.call(object, key);
  const element = (tag, className, text) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  };
  const arrow = () => {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.classList.add('icon'); svg.setAttribute('aria-hidden', 'true');
    const use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
    use.setAttribute('href', '#prm-i-arrow'); svg.append(use); return svg;
  };
  const safeURL = (value) => {
    try {
      const url = new URL(String(value || ''), window.location.href);
      return ['https:', 'http:'].includes(url.protocol) ? url.href : '';
    } catch { return ''; }
  };
  let config = null;
  try { config = JSON.parse($('#prm-config')?.textContent || 'null'); } catch { /* Native links and forms remain available. */ }

  function initializeArticles() {
    const grid = $('#prm-article-grid');
    const status = $('#prm-result-status');
    const search = $('#prm-search-input');
    const form = $('#prm-top-search');
    if (!config || !config.restUrl || !grid || !status || !search || !form || !window.fetch || !window.AbortController) return;
    const endpoint = safeURL(config.restUrl);
    if (!endpoint) return;
    const initial = config.initial || {};
    const state = {
      section: owns(sections, initial.section) ? initial.section : 'all',
      q: typeof initial.q === 'string' ? initial.q : '',
      page: Math.max(1, Number(initial.page) || 1),
      total: Math.max(0, Number(initial.total) || 0),
      hasMore: initial.has_more === true
    };
    let intent = { section: state.section, q: state.q, page: state.page };
    let firstVisiblePage = state.page;
    let controller, sequence = 0, loading = false, searchTimer;
    const fallbackImage = safeURL(config.fallbackImage) || (scriptURL ? new URL('fallback.svg', scriptURL).href : '');
    const loadMore = $('#prm-load-more');
    const empty = $('#prm-empty-state');
    const toolbar = $('#prm-results-toolbar');
    const description = $('#prm-filter-description');
    const heading = $('#prm-articles-title');
    const reset = $('#prm-reset-filters');
    const emptyReset = $('#prm-empty-reset');
    const latest = $('#prm-articles');
    const resultsHome = document.createComment('Article list position');
    latest?.before(resultsHome);
    const feedback = element('span', 'search-feedback');
    feedback.setAttribute('role', 'status'); form.append(feedback);
    const clearSearch = element('button', 'search-clear', '×');
    clearSearch.type = 'button'; clearSearch.setAttribute('aria-label', 'پاک کردن جست‌وجو');
    clearSearch.title = 'پاک کردن جست‌وجو'; search.after(clearSearch);
    function clearQuery() {
      clearTimeout(searchTimer); search.value = ''; clearSearch.hidden = true;
      search.focus(); requestArticles({ section: 'all', q: '', page: 1 });
    }
    clearSearch.addEventListener('click', clearQuery);
    search.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && search.value) { event.preventDefault(); clearQuery(); }
    });
    function pageURL(selection, page = 1) {
      const url = new URL(window.location.href);
      ['prm_section', 'prm_q', 'prm_page'].forEach((key) => url.searchParams.delete(key));
      if (selection.section !== 'all') url.searchParams.set('prm_section', selection.section);
      if (selection.q) url.searchParams.set('prm_q', selection.q);
      if (page > 1) url.searchParams.set('prm_page', String(page));
      url.hash = 'prm-articles';
      return url;
    }
    function articleCard(article) {
      if (!article || typeof article.title !== 'string' || !safeURL(article.url)) return null;
      const node = element('article', 'article-card');
      const media = element('a', 'article-image'); media.href = safeURL(article.url); media.setAttribute('aria-label', article.title);
      const img = element('img'); img.src = safeURL(article.image) || fallbackImage;
      img.alt = article.title; img.width = 800; img.height = 520; img.loading = 'lazy'; img.decoding = 'async';
      if (fallbackImage) img.addEventListener('error', () => { if (img.src !== fallbackImage) img.src = fallbackImage; }, { once: true });
      media.append(img);
      const content = element('div', 'article-content');
      const meta = element('div', 'article-meta'); meta.append(element('span', 'article-category', String(article.category || '')));
      if (article.date) meta.append(element('time', '', String(article.date)));
      const title = element('h3'); const link = element('a', '', article.title); link.href = safeURL(article.url); title.append(link);
      const more = element('a', 'story-link', 'خواندن مقاله'); more.href = safeURL(article.url); more.append(arrow());
      content.append(meta, title, element('p', 'article-excerpt', String(article.description || '')), more);
      node.append(media, content); return node;
    }
    function updateControls() {
      clearSearch.hidden = !search.value;
      const filtered = state.section !== 'all' || Boolean(state.q);
      latest?.classList.toggle('search-results-active', filtered);
      if (latest && filtered) $('.topic-nav')?.parentElement.after(latest);
      else if (latest) resultsHome.after(latest);
      feedback.textContent = state.q ? numbers.format(state.total) + ' مقاله برای «' + state.q + '»' : '';
      if (heading) heading.textContent = filtered ? 'راهنماهای مرتبط' : 'تازه‌های مجله';
      if (toolbar) toolbar.hidden = !filtered;
      if (description) {
        const parts = [];
        if (state.section !== 'all') parts.push('موضوع: ' + sections[state.section]);
        if (state.q) parts.push('جست‌وجو: «' + state.q + '»');
        description.textContent = parts.join(' · ');
      }
      status.textContent = numbers.format(state.total) + ' مقاله';
      if (empty) empty.hidden = state.total > 0;
      if (loadMore) { loadMore.hidden = !state.hasMore; loadMore.href = pageURL(state, state.page + 1).href; }
      if (reset) reset.href = pageURL({ section: 'all', q: '' }).href;
      if (emptyReset) emptyReset.href = pageURL({ section: 'all', q: '' }).href;
      $$('.topic-button[data-section]').forEach((anchor) => {
        const active = anchor.dataset.section === state.section;
        anchor.classList.toggle('is-active', active);
        if (active) anchor.setAttribute('aria-current', 'true'); else anchor.removeAttribute('aria-current');
        anchor.href = pageURL({ section: anchor.dataset.section, q: state.q }).href;
      });
      const sectionField = form.querySelector('[name="prm_section"]');
      if (sectionField) sectionField.value = state.section;
    }
    function showResults() {
      latest?.scrollIntoView({ block: 'start', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
    }
    async function requestArticles(selection, options = {}) {
      const append = options.append === true;
      if (append && loading) return;
      const requested = { section: owns(sections, selection.section) ? selection.section : 'all', q: String(selection.q || '').trim().slice(0, 120), page: Math.max(1, Number(selection.page) || 1) };
      intent = requested;
      const token = ++sequence;
      controller?.abort(); controller = new AbortController();
      const url = new URL(endpoint);
      url.searchParams.set('section', requested.section); url.searchParams.set('q', requested.q); url.searchParams.set('page', String(requested.page));
      loading = true; grid.setAttribute('aria-busy', 'true');
      if (loadMore) loadMore.setAttribute('aria-disabled', 'true');
      status.textContent = 'در حال دریافت مقاله‌ها…';
      feedback.textContent = requested.q ? 'در حال جست‌وجو…' : '';
      try {
        const response = await fetch(url.href, { signal: controller.signal, credentials: 'same-origin', headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Article request failed');
        const data = await response.json();
        if (!data || !Array.isArray(data.items) || !Number.isFinite(Number(data.total))) throw new Error('Invalid article response');
        if (token !== sequence) return;
        const cards = data.items.map(articleCard).filter(Boolean);
        const fragment = document.createDocumentFragment(); cards.forEach((card) => fragment.append(card));
        if (append) grid.append(fragment); else grid.replaceChildren(fragment);
        Object.assign(state, requested, { page: Math.max(1, Number(data.page) || requested.page), total: Math.max(0, Number(data.total)), hasMore: data.has_more === true });
        if (!append) firstVisiblePage = state.page;
        intent = { section: state.section, q: state.q, page: state.page };
        updateControls();
        try { window.history.replaceState(window.history.state, '', pageURL(state, firstVisiblePage).href); } catch { /* The native pagination URLs still work. */ }
        if (append) cards[0]?.querySelector('h3 a')?.focus();
        else if (options.scroll) showResults();
      } catch (error) {
        if (token !== sequence || error.name === 'AbortError') return;
        status.textContent = 'دریافت مقاله‌ها انجام نشد. ';
        feedback.textContent = 'جست‌وجو انجام نشد؛ دوباره تلاش کنید.';
        const fallback = element('a', 'request-fallback', 'باز کردن نتیجه در صفحه'); fallback.href = pageURL(requested, requested.page).href;
        status.append(fallback);
      } finally {
        if (token === sequence) {
          loading = false; grid.setAttribute('aria-busy', 'false');
          if (loadMore) loadMore.removeAttribute('aria-disabled');
        }
      }
    }
    root.addEventListener('click', (event) => {
      if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || !(event.target instanceof Element)) return;
      const anchor = event.target.closest('a[data-section], a#prm-reset-filters, a#prm-empty-reset, a#prm-load-more');
      if (!anchor || !root.contains(anchor) || anchor.hasAttribute('download') || (anchor.target && anchor.target !== '_self')) return;
      if (anchor.id === 'prm-load-more') {
        event.preventDefault(); clearTimeout(searchTimer);
        if (search.value.trim() !== state.q || intent.section !== state.section) requestArticles({ section: intent.section, q: search.value, page: 1 }, { scroll: true });
        else if (state.hasMore) requestArticles({ section: state.section, q: state.q, page: state.page + 1 }, { append: true });
      } else if (anchor.id === 'prm-reset-filters' || anchor.id === 'prm-empty-reset') {
        event.preventDefault(); clearTimeout(searchTimer); search.value = '';
        requestArticles({ section: 'all', q: '', page: 1 }, { scroll: true });
      } else if (owns(sections, anchor.dataset.section)) {
        event.preventDefault(); clearTimeout(searchTimer);
        const q = anchor.classList.contains('topic-button') ? search.value.trim() : '';
        search.value = q; requestArticles({ section: anchor.dataset.section, q, page: 1 }, { scroll: true });
      }
    });
    form.addEventListener('submit', (event) => {
      event.preventDefault(); clearTimeout(searchTimer);
      requestArticles({ section: 'all', q: search.value, page: 1 }, { scroll: true });
    });
    search.addEventListener('input', () => {
      clearSearch.hidden = !search.value;
      clearTimeout(searchTimer);
      searchTimer = setTimeout(() => requestArticles({ section: 'all', q: search.value, page: 1 }), 300);
    });
    updateControls();
  }

  function initializeMenu() {
    const button = $('.menu-toggle'); const nav = $('#prm-primary-nav');
    if (!button || !nav) return;
    const close = (returnFocus = false) => {
      button.setAttribute('aria-expanded', 'false'); button.setAttribute('aria-label', 'باز کردن منوی مجله'); nav.classList.remove('is-open');
      if (returnFocus) button.focus();
    };
    button.addEventListener('click', () => {
      const open = button.getAttribute('aria-expanded') !== 'true';
      button.setAttribute('aria-expanded', String(open)); button.setAttribute('aria-label', open ? 'بستن منوی مجله' : 'باز کردن منوی مجله'); nav.classList.toggle('is-open', open);
    });
    nav.querySelectorAll('a').forEach((anchor) => anchor.addEventListener('click', () => close()));
    root.addEventListener('keydown', (event) => { if (event.key === 'Escape' && nav.classList.contains('is-open')) { const focused = nav.contains(document.activeElement); close(focused); } });
    document.addEventListener('click', (event) => { if (!(event.target instanceof Element) || !event.target.closest('#prm-mag .site-header')) close(); });
  }

  function initializeChecklist() {
    const dialog = $('#prm-checklist-dialog'); const items = $('#prm-checklist-items'); const notes = $('#prm-checklist-notes');
    const open = $('#prm-open-checklist'); const close = $('#prm-close-checklist'); const progress = $('#prm-checklist-progress');
    if (!dialog || !items || !notes || !open || !close || !progress) return;
    const storageKey = 'parmis-month-end-checklist-v1';
    const checklist = [
      'اسناد و فاکتورهای دوره را جمع‌آوری و مرتب کرده‌ام.',
      'ثبت‌های خرید، فروش، دریافت و پرداخت را تکمیل کرده‌ام.',
      'ماندهٔ حساب‌های بانکی را با صورتحساب بانک تطبیق داده‌ام.',
      'موجودی صندوق و تنخواه را کنترل کرده‌ام.',
      'ماندهٔ بدهکاران و بستانکاران را بررسی کرده‌ام.',
      'موجودی کالا و مغایرت‌های انبار را بررسی کرده‌ام.',
      'اسناد حقوق و دستمزد دوره را کنترل کرده‌ام.',
      'هزینه‌ها و درآمدهای ثبت‌نشده را بررسی کرده‌ام.',
      'پیش‌پرداخت‌ها و پیش‌دریافت‌ها را مرور کرده‌ام.',
      'اسناد اصلاحی لازم را با مسئول مالی بررسی کرده‌ام.',
      'تراز آزمایشی و گزارش‌های دوره را مرور کرده‌ام.',
      'از اطلاعات مالی نسخهٔ پشتیبان گرفته‌ام.'
    ];
    if (!items.querySelector('input[type="checkbox"]')) checklist.forEach((text, index) => {
      const label = element('label', 'checklist-item'); const input = element('input'); input.type = 'checkbox'; input.value = String(index);
      label.append(input, element('span', '', text)); items.append(label);
    });
    const inputs = [...items.querySelectorAll('input[type="checkbox"]')];
    try {
      const stored = JSON.parse(localStorage.getItem(storageKey));
      if (stored && Array.isArray(stored.checked)) {
        inputs.forEach((input, index) => { input.checked = stored.checked.includes(index); });
        notes.value = String(stored.notes || '').slice(0, 4000);
      }
    } catch { /* Browsing without persistent storage is supported. */ }
    notes.maxLength = 4000;
    function updateProgress() { progress.textContent = numbers.format(inputs.filter((input) => input.checked).length) + ' از ' + numbers.format(inputs.length) + ' مورد آماده است'; }
    function save() {
      const checked = inputs.flatMap((input, index) => input.checked ? [index] : []);
      try { localStorage.setItem(storageKey, JSON.stringify({ checked, notes: notes.value })); } catch { /* Keep the checklist usable. */ }
      updateProgress();
    }
    inputs.forEach((input) => input.addEventListener('change', save)); notes.addEventListener('input', save); updateProgress();
    open.addEventListener('click', () => {
      if (typeof dialog.showModal === 'function') dialog.showModal(); else dialog.setAttribute('open', '');
      document.body.classList.add('prm-dialog-open');
    });
    const closeDialog = () => {
      if (typeof dialog.close === 'function') dialog.close(); else dialog.removeAttribute('open');
      document.body.classList.remove('prm-dialog-open', 'prm-printing'); open.focus();
    };
    close.addEventListener('click', closeDialog);
    dialog.addEventListener('close', () => { document.body.classList.remove('prm-dialog-open', 'prm-printing'); open.focus(); });
    dialog.addEventListener('click', (event) => {
      if (event.target !== dialog) return;
      const rect = dialog.getBoundingClientRect();
      if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) closeDialog();
    });
    $('#prm-clear-checklist')?.addEventListener('click', () => { inputs.forEach((input) => { input.checked = false; }); save(); });
    let printNotes = $('.print-notes');
    if (!printNotes) { printNotes = element('div', 'print-notes'); printNotes.setAttribute('aria-hidden', 'true'); notes.insertAdjacentElement('afterend', printNotes); }
    window.addEventListener('beforeprint', () => {
      if (dialog.open) { printNotes.textContent = notes.value || ' '; document.body.classList.add('prm-printing'); }
    });
    window.addEventListener('afterprint', () => document.body.classList.remove('prm-printing'));
    $('#prm-print-checklist')?.addEventListener('click', () => window.print());
  }
  function initializeFullWidthChrome() {
    let scheduled = false;
    const update = () => {
      scheduled = false;
      const viewport = document.documentElement.clientWidth;
      const bounds = root.getBoundingClientRect();
      root.style.setProperty('--prm-viewport-width', viewport + 'px');
      root.style.setProperty('--prm-edge-offset', (bounds.right - viewport) + 'px');
    };
    const schedule = () => {
      if (!scheduled) { scheduled = true; requestAnimationFrame(update); }
    };
    update();
    window.addEventListener('resize', schedule, { passive: true });
    if (typeof ResizeObserver !== 'undefined') new ResizeObserver(schedule).observe(root);
  }
  initializeFullWidthChrome();
  initializeArticles(); initializeMenu(); initializeChecklist();
})();
