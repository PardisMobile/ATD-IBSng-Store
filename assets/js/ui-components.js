(() => {
  'use strict';

  const root = document.documentElement;
  const language = () => root.lang === 'en' ? 'en' : 'fa';

  const toast = (message, type = 'info') => {
    if (!message) return;
    let element = document.querySelector('.atd-toast');
    if (!element) {
      element = document.createElement('div');
      element.className = 'atd-toast';
      element.setAttribute('role', 'status');
      element.setAttribute('aria-live', 'polite');
      document.body.appendChild(element);
    }
    element.dataset.type = type;
    element.textContent = message;
    element.classList.add('is-visible');
    clearTimeout(toast.timer);
    toast.timer = setTimeout(() => element.classList.remove('is-visible'), 5200);
  };

  window.ATDUI = Object.assign(window.ATDUI || {}, { toast });


  const isTextEntry = (element) => {
    if (!(element instanceof HTMLInputElement) && !(element instanceof HTMLTextAreaElement)) return false;
    if (element instanceof HTMLTextAreaElement) return true;
    return ['text', 'search', 'email', 'url', 'tel', 'password', 'number', 'date', 'time', 'datetime-local', 'month', 'week'].includes((element.type || 'text').toLowerCase());
  };

  const isTechnicalEntry = (element) => {
    if (element instanceof HTMLInputElement) {
      const type = (element.type || 'text').toLowerCase();
      if (['email', 'url', 'tel', 'password', 'number', 'date', 'time', 'datetime-local', 'month', 'week'].includes(type)) return true;
      if (element.hasAttribute('data-numeric') || element.inputMode === 'numeric' || element.inputMode === 'decimal') return true;
    }

    const identity = [element.id, element.name, element.getAttribute('autocomplete'), element.getAttribute('placeholder')]
      .filter(Boolean).join(' ').toLowerCase();
    const technicalIdentity = /(?:^|[\\s_\\-])(email|e-mail|username|user_name|account|tracking|order|lookup|code|captcha|otp|host|domain|ip|port|url|uri|token|secret|api[_-]?key|merchant|terminal|telegram|whatsapp|phone|mobile|credit|price|quota|sort|length|prefix|remote[_-]?user[_-]?id)(?:$|[\\s_\\-])/i;
    if (technicalIdentity.test(identity)) return true;

    const label = element.id
      ? document.querySelector('label[for="' + CSS.escape(element.id) + '"]')
      : element.closest('.field')?.querySelector('label');
    const labelText = (label?.textContent || '').toLowerCase();
    return /ایمیل|کد رهگیری|شناسه سفارش|نام کاربری|رمز عبور|رمز اکانت|شماره تماس|شماره تلفن|آدرس|نشانی|دامنه|پورت|آی.?پی|کد چهاررقمی|توکن|کلید|قیمت|حجم|اعتبار|ترتیب نمایش|طول .*نام کاربری/i.test(labelText);
  };

  const applyTextDirection = (scope = document) => {
    const fields = [];
    if (scope.matches?.('input,textarea')) fields.push(scope);
    scope.querySelectorAll?.('input,textarea').forEach((field) => fields.push(field));
    fields.forEach((field) => {
      if (!isTextEntry(field) || field.hasAttribute('dir')) return;
      field.setAttribute('dir', isTechnicalEntry(field) ? 'ltr' : 'auto');
    });
  };

  const initializeTextDirection = () => {
    applyTextDirection();
    const observer = new MutationObserver((records) => {
      records.forEach((record) => record.addedNodes.forEach((node) => {
        if (node.nodeType === Node.ELEMENT_NODE) applyTextDirection(node);
      }));
    });
    observer.observe(document.body, { childList: true, subtree: true });
  };

  const initializeSelectMenus = () => {
    const selectSelector = 'select:not([multiple]):not([size]):not([data-native-select])';
    let activeSelect = null;
    let activeMenu = null;
    let activeOptions = [];
    let selectedIndex = 0;
    let mutationObserver = null;

    const close = () => {
      if (!activeSelect) return;
      activeSelect.setAttribute('aria-expanded', 'false');
      activeSelect.removeAttribute('aria-controls');
      activeMenu?.remove();
      mutationObserver?.disconnect();
      activeSelect = null;
      activeMenu = null;
      activeOptions = [];
      mutationObserver = null;
    };

    const visibleOptions = (select) => Array.from(select.options)
      .map((option, index) => ({ option, index }))
      .filter(({ option }) => !option.hidden && option.parentElement?.hidden !== true);

    const positionMenu = () => {
      if (!activeSelect || !activeMenu) return;
      const rect = activeSelect.getBoundingClientRect();
      const gap = 5;
      const viewportPadding = 8;
      const availableBelow = window.innerHeight - rect.bottom - viewportPadding - gap;
      const availableAbove = rect.top - viewportPadding - gap;
      const openAbove = availableBelow < 180 && availableAbove > availableBelow;
      const maxHeight = Math.max(90, Math.min(300, openAbove ? availableAbove : availableBelow));
      activeMenu.style.width = Math.min(rect.width, window.innerWidth - viewportPadding * 2) + 'px';
      activeMenu.style.maxHeight = maxHeight + 'px';
      activeMenu.style.left = Math.max(viewportPadding, Math.min(rect.left, window.innerWidth - rect.width - viewportPadding)) + 'px';
      activeMenu.style.top = (openAbove
        ? Math.max(viewportPadding, rect.top - Math.min(activeMenu.scrollHeight, maxHeight) - gap)
        : rect.bottom + gap) + 'px';
    };

    const renderOptions = () => {
      if (!activeSelect || !activeMenu) return;
      activeOptions = visibleOptions(activeSelect);
      activeMenu.replaceChildren();
      let lastGroup = null;
      activeOptions.forEach(({ option, index }) => {
        const group = option.parentElement instanceof HTMLOptGroupElement ? option.parentElement : null;
        if (group && group !== lastGroup) {
          const heading = document.createElement('div');
          heading.className = 'atd-select-group';
          heading.textContent = group.label;
          activeMenu.appendChild(heading);
        }
        lastGroup = group;
        const item = document.createElement('div');
        item.className = 'atd-select-option';
        item.setAttribute('role', 'option');
        item.dataset.optionIndex = String(index);
        item.setAttribute('aria-selected', String(index === activeSelect.selectedIndex));
        item.setAttribute('aria-disabled', String(option.disabled || group?.disabled || false));
        item.textContent = option.label || option.textContent;
        if (option.disabled || group?.disabled) item.classList.add('is-disabled');
        if (index === activeSelect.selectedIndex) item.classList.add('is-selected');
        activeMenu.appendChild(item);
      });
      positionMenu();
    };

    const open = (select) => {
      if (select.disabled || select.options.length === 0) return;
      if (activeSelect === select) { close(); return; }
      close();
      activeSelect = select;
      selectedIndex = Math.max(0, select.selectedIndex);
      activeMenu = document.createElement('div');
      activeMenu.className = 'atd-select-menu';
      activeMenu.id = 'atd-select-menu-' + Math.random().toString(36).slice(2);
      activeMenu.setAttribute('role', 'listbox');
      activeMenu.setAttribute('aria-label', select.getAttribute('aria-label') || select.labels?.[0]?.textContent?.trim() || '');
      activeMenu.dir = getComputedStyle(select).direction;
      document.body.appendChild(activeMenu);
      select.setAttribute('aria-expanded', 'true');
      select.setAttribute('aria-controls', activeMenu.id);
      renderOptions();
      const selected = activeMenu.querySelector('.is-selected');
      if (selected) selected.scrollIntoView({ block: 'nearest' });
      mutationObserver = new MutationObserver(renderOptions);
      mutationObserver.observe(select, { childList: true, subtree: true, attributes: true, attributeFilter: ['disabled', 'hidden', 'label', 'selected'] });
    };

    document.querySelectorAll(selectSelector).forEach((select) => {
      if (select.dataset.atdSelectReady === 'true') return;
      select.dataset.atdSelectReady = 'true';
      select.setAttribute('aria-haspopup', 'listbox');
      select.setAttribute('aria-expanded', 'false');
      select.addEventListener('click', (event) => {
        event.preventDefault();
        select.focus({ preventScroll: true });
        open(select);
      });
      select.addEventListener('keydown', (event) => {
        if (!activeSelect || activeSelect !== select) {
          if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
            event.preventDefault();
            open(select);
          }
          return;
        }
        if (event.key === 'Escape' || event.key === 'Tab') {
          if (event.key === 'Escape') event.preventDefault();
          close();
          return;
        }
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
          event.preventDefault();
          const step = event.key === 'ArrowDown' ? 1 : -1;
          let next = selectedIndex;
          for (let count = 0; count < activeOptions.length; count++) {
            next = (next + step + activeSelect.options.length) % activeSelect.options.length;
            const candidate = activeSelect.options[next];
            if (!candidate.disabled && !candidate.parentElement?.disabled && !candidate.hidden) break;
          }
          selectedIndex = next;
          activeMenu.querySelectorAll('[role="option"]').forEach((item) => {
            const selected = Number(item.dataset.optionIndex) === selectedIndex;
            item.classList.toggle('is-active', selected);
            item.setAttribute('aria-selected', String(selected));
            if (selected) item.scrollIntoView({ block: 'nearest' });
          });
          return;
        }
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          const candidate = activeSelect.options[selectedIndex];
          if (candidate && !candidate.disabled && !candidate.parentElement?.disabled) {
            activeSelect.selectedIndex = selectedIndex;
            activeSelect.dispatchEvent(new Event('input', { bubbles: true }));
            activeSelect.dispatchEvent(new Event('change', { bubbles: true }));
          }
          close();
          return;
        }
        if (event.key.length === 1) {
          const start = activeSelect.selectedIndex + 1;
          const needle = event.key.toLocaleLowerCase();
          const options = Array.from(activeSelect.options);
          for (let offset = 0; offset < options.length; offset++) {
            const index = (start + offset) % options.length;
            if (!options[index].disabled && options[index].textContent.trim().toLocaleLowerCase().startsWith(needle)) {
              selectedIndex = index;
              activeMenu.querySelectorAll('[role="option"]').forEach((item) => {
                const selected = Number(item.dataset.optionIndex) === index;
                item.classList.toggle('is-active', selected);
                item.setAttribute('aria-selected', String(selected));
                if (selected) item.scrollIntoView({ block: 'nearest' });
              });
              break;
            }
          }
        }
      });
      select.addEventListener('change', () => {
        if (activeSelect === select) renderOptions();
      });
    });

    document.addEventListener('click', (event) => {
      if (!activeSelect) return;
      if (event.target === activeSelect || activeMenu?.contains(event.target)) return;
      close();
    });
    document.addEventListener('mousedown', (event) => {
      if (activeMenu?.contains(event.target)) event.preventDefault();
    });
    document.addEventListener('click', (event) => {
      const item = event.target.closest?.('.atd-select-option[data-option-index]');
      if (!item || !activeSelect || item.classList.contains('is-disabled')) return;
      const index = Number(item.dataset.optionIndex);
      if (!Number.isInteger(index) || !activeSelect.options[index]) return;
      activeSelect.selectedIndex = index;
      activeSelect.dispatchEvent(new Event('input', { bubbles: true }));
      activeSelect.dispatchEvent(new Event('change', { bubbles: true }));
      close();
    });
    window.addEventListener('resize', positionMenu, { passive: true });
    window.addEventListener('scroll', positionMenu, true);
    const observer = new MutationObserver((records) => records.forEach((record) => record.addedNodes.forEach((node) => {
      if (node.nodeType !== Node.ELEMENT_NODE) return;
      if (node.matches?.(selectSelector)) initializeSelectMenusForNode(node);
      node.querySelectorAll?.(selectSelector).forEach((select) => initializeSelectMenusForNode(select));
    })));
    const initializeSelectMenusForNode = (select) => {
      if (select.dataset.atdSelectReady === 'true') return;
      select.dataset.atdSelectReady = 'true';
      select.setAttribute('aria-haspopup', 'listbox');
      select.setAttribute('aria-expanded', 'false');
      select.addEventListener('click', (event) => { event.preventDefault(); select.focus({ preventScroll: true }); open(select); });
      select.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp' || event.key === 'Enter' || event.key === ' ') { event.preventDefault(); open(select); }
      });
      select.addEventListener('change', () => { if (activeSelect === select) renderOptions(); });
    };
    observer.observe(document.body, { childList: true, subtree: true });
  };

  const mount = () => {
    initializeTextDirection();
    initializeSelectMenus();
    const dialog = document.createElement('dialog');
    dialog.className = 'atd-confirm-dialog';
    dialog.innerHTML = '<form method="dialog" class="atd-confirm-card">'
      + '<div class="atd-confirm-icon" aria-hidden="true">!</div>'
      + '<div><h2></h2><p></p></div>'
      + '<div class="atd-confirm-actions">'
      + '<button class="button atd-confirm-cancel" value="cancel"></button>'
      + '<button class="button primary atd-confirm-submit" value="confirm"></button>'
      + '</div></form>';
    document.body.appendChild(dialog);

    let pendingForm = null;
    let pendingSubmitter = null;

    const openConfirm = (form, submitter) => {
      pendingForm = form;
      pendingSubmitter = submitter;
      dialog.querySelector('h2').textContent = language() === 'fa' ? 'تأیید انجام عملیات' : 'Please confirm';
      dialog.querySelector('p').textContent = language() === 'fa'
        ? (form.dataset.confirmFa || form.dataset.confirm || '')
        : (form.dataset.confirmEn || form.dataset.confirm || '');
      dialog.querySelector('.atd-confirm-cancel').textContent = language() === 'fa' ? 'انصراف' : 'Cancel';
      dialog.querySelector('.atd-confirm-submit').textContent = language() === 'fa' ? 'ادامه' : 'Continue';

      if (typeof dialog.showModal === 'function') {
        dialog.showModal();
        return;
      }

      if (window.confirm(dialog.querySelector('p').textContent)) {
        form.dataset.confirmed = 'true';
        form.requestSubmit ? form.requestSubmit(submitter) : form.submit();
        delete form.dataset.confirmed;
      }
    };

    document.addEventListener('click', (event) => {
      const submitter = event.target.closest('button[type="submit"],input[type="submit"]');
      const form = submitter?.form;
      if (!form || (!form.dataset.confirm && !form.dataset.confirmFa && !form.dataset.confirmEn) || form.dataset.confirmed === 'true') return;
      event.preventDefault();
      openConfirm(form, submitter);
    });

    dialog.addEventListener('close', () => {
      if (dialog.returnValue === 'confirm' && pendingForm) {
        const form = pendingForm;
        const submitter = pendingSubmitter;
        form.dataset.confirmed = 'true';
        form.requestSubmit ? form.requestSubmit(submitter) : form.submit();
        queueMicrotask(() => { delete form.dataset.confirmed; });
      }
      pendingForm = null;
      pendingSubmitter = null;
    });

    const eyeIcon = '<svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z"/><circle cx="12" cy="12" r="3"/></svg>';
    const eyeOffIcon = '<svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3l18 18"/><path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"/><path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c5.2 0 8.8 4.8 9.5 7-.3 1-1.1 2.2-2.3 3.3"/><path d="M6.2 6.2C3.8 7.7 2.8 10.1 2.5 12c.5 1.8 4.1 7 9.5 7 1 0 2-.2 2.9-.6"/></svg>';

    document.querySelectorAll('[data-ui-password-toggle]').forEach((button) => {
      const value = button.previousElementSibling;
      if (!value) return;
      const initiallyVisible = value.textContent !== '••••••••';
      button.innerHTML = initiallyVisible ? eyeOffIcon : eyeIcon;
      button.setAttribute('aria-pressed', String(initiallyVisible));
      button.setAttribute('aria-label', language() === 'en'
        ? (initiallyVisible ? 'Hide password' : 'Show password')
        : (initiallyVisible ? 'مخفی کردن رمز' : 'نمایش رمز'));
      button.addEventListener('click', () => {
        const show = value.textContent === '••••••••';
        value.textContent = show ? value.dataset.password || '' : '••••••••';
        button.innerHTML = show ? eyeOffIcon : eyeIcon;
        button.setAttribute('aria-label', language() === 'en'
          ? (show ? 'Hide password' : 'Show password')
          : (show ? 'مخفی کردن رمز' : 'نمایش رمز'));
        button.setAttribute('aria-pressed', String(show));
      });
    });

    document.querySelectorAll('[data-toggle-password-form]').forEach((button) => {
      button.addEventListener('click', () => {
        const form = document.getElementById(button.dataset.togglePasswordForm || '');
        if (!form) return;
        const opening = form.hidden;
        form.hidden = !opening;
        button.setAttribute('aria-expanded', String(opening));
        if (opening) form.querySelector('input')?.focus();
      });
    });

    const syncTableLabels = () => {
      document.querySelectorAll('.table-wrap').forEach((wrap) => {
        wrap.tabIndex = 0;
        wrap.setAttribute('aria-label', language() === 'fa' ? 'جدول قابل پیمایش افقی' : 'Horizontally scrollable table');
      });
    };
    syncTableLabels();
    document.addEventListener('atd:language-change', syncTableLabels);
  };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mount, { once: true });
  else mount();
})();
