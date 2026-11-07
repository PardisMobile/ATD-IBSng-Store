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
    return /ایمیل|کد رهگیری|شناسه سفارش|نام کاربری|رمز عبور|رمز اکانت|شماره تماس|شماره تلفن|آدرس|نشانی|دامنه|پورت|آی.?پی|کد چهاررقمی|توکن|کلید|قیمت|حجم|اعتبار|ترتیب نمایش|طول .*نام کاربری/i.test(label?.textContent || '');
  };

  const applyTextDirection = (scope = document) => {
    const fields = [];
    if (scope.matches?.('input,textarea')) fields.push(scope);
    scope.querySelectorAll?.('input,textarea').forEach((field) => fields.push(field));
    fields.forEach((field) => {
      if (isTextEntry(field) && !field.hasAttribute('dir')) {
        field.setAttribute('dir', isTechnicalEntry(field) ? 'ltr' : 'auto');
      }
    });
  };

  const initializeTextDirection = () => {
    applyTextDirection();
    const observer = new MutationObserver((records) => records.forEach((record) => record.addedNodes.forEach((node) => {
      if (node.nodeType === Node.ELEMENT_NODE) applyTextDirection(node);
    })));
    observer.observe(document.body, { childList: true, subtree: true });
  };

  const initializeSelectMenus = () => {
    const selectSelector = 'select:not([multiple]):not([size]):not([data-native-select])';
    const initialized = new WeakSet();
    let activeSelect = null;
    let activeWrapper = null;
    let activeTrigger = null;
    let activeMenu = null;
    let activeOptions = [];
    let activeIndex = -1;
    let optionObserver = null;
    let generatedId = 0;

    const nextId = (prefix) => prefix + '-' + (++generatedId);
    const visibleOptions = (select) => Array.from(select.options)
      .map((option, index) => ({ option, index, group: option.parentElement instanceof HTMLOptGroupElement ? option.parentElement : null }))
      .filter(({ option, group }) => !option.hidden && !group?.hidden);

    const close = (returnFocus = false) => {
      if (!activeSelect) return;
      activeTrigger?.setAttribute('aria-expanded', 'false');
      activeTrigger?.removeAttribute('aria-activedescendant');
      activeMenu?.remove();
      optionObserver?.disconnect();
      const trigger = activeTrigger;
      activeSelect = activeWrapper = activeTrigger = activeMenu = null;
      activeOptions = [];
      activeIndex = -1;
      optionObserver = null;
      if (returnFocus) trigger?.focus({ preventScroll: true });
    };

    const positionMenu = () => {
      if (!activeTrigger || !activeMenu) return;
      const rect = activeTrigger.getBoundingClientRect();
      const viewport = window.visualViewport;
      const viewportHeight = viewport?.height || window.innerHeight;
      const viewportWidth = viewport?.width || window.innerWidth;
      const offsetTop = viewport?.offsetTop || 0;
      const offsetLeft = viewport?.offsetLeft || 0;
      const pad = 8;
      const gap = 5;
      const below = offsetTop + viewportHeight - rect.bottom - pad - gap;
      const above = rect.top - offsetTop - pad - gap;
      const openAbove = below < 180 && above > below;
      const maxHeight = Math.max(90, Math.min(300, openAbove ? above : below));
      const width = Math.min(rect.width, viewportWidth - pad * 2);
      const left = Math.max(offsetLeft + pad, Math.min(rect.left, offsetLeft + viewportWidth - width - pad));
      activeMenu.style.width = width + 'px';
      activeMenu.style.maxHeight = maxHeight + 'px';
      activeMenu.style.left = left + 'px';
      activeMenu.style.top = (openAbove
        ? Math.max(offsetTop + pad, rect.top - Math.min(activeMenu.scrollHeight, maxHeight) - gap)
        : rect.bottom + gap) + 'px';
    };

    const syncTrigger = (select, trigger) => {
      const option = select.options[select.selectedIndex];
      trigger.querySelector('.atd-select-value').textContent = option?.label || option?.textContent?.trim() || '';
      trigger.disabled = select.disabled;
      trigger.setAttribute('aria-invalid', String(select.getAttribute('aria-invalid') === 'true'));
    };

    const renderOptions = () => {
      if (!activeSelect || !activeMenu) return;
      activeOptions = visibleOptions(activeSelect);
      activeMenu.replaceChildren();
      let lastGroup = null;
      activeOptions.forEach(({ option, index, group }) => {
        if (group && group !== lastGroup) {
          const heading = document.createElement('div');
          heading.className = 'atd-select-group';
          heading.textContent = group.label;
          activeMenu.appendChild(heading);
        }
        lastGroup = group;
        const item = document.createElement('div');
        item.className = 'atd-select-option';
        item.id = activeMenu.id + '-option-' + index;
        item.setAttribute('role', 'option');
        item.dataset.optionIndex = String(index);
        item.setAttribute('aria-selected', String(index === activeSelect.selectedIndex));
        const disabled = option.disabled || Boolean(group?.disabled);
        item.setAttribute('aria-disabled', String(disabled));
        item.textContent = option.label || option.textContent.trim();
        if (disabled) item.classList.add('is-disabled');
        if (index === activeSelect.selectedIndex) item.classList.add('is-selected');
        if (index === activeIndex) item.classList.add('is-active');
        activeMenu.appendChild(item);
      });
      const current = activeMenu.querySelector('[data-option-index="' + activeIndex + '"]:not(.is-disabled)');
      if (current) {
        activeTrigger.setAttribute('aria-activedescendant', current.id);
        current.scrollIntoView({ block: 'nearest' });
      } else {
        activeTrigger.removeAttribute('aria-activedescendant');
      }
      positionMenu();
    };

    const selectOption = (index) => {
      if (!activeSelect) return;
      const option = activeSelect.options[index];
      const group = option?.parentElement instanceof HTMLOptGroupElement ? option.parentElement : null;
      if (!option || option.disabled || group?.disabled || option.hidden || group?.hidden) return;
      const select = activeSelect;
      const trigger = activeTrigger;
      select.selectedIndex = index;
      select.dispatchEvent(new Event('input', { bubbles: true }));
      select.dispatchEvent(new Event('change', { bubbles: true }));
      close();
      trigger?.focus({ preventScroll: true });
    };

    const moveActive = (step) => {
      const candidates = activeOptions.filter(({ option, group }) => !option.disabled && !group?.disabled);
      if (!candidates.length) return;
      const current = candidates.findIndex(({ index }) => index === activeIndex);
      activeIndex = candidates[(current + step + candidates.length) % candidates.length].index;
      renderOptions();
    };

    const open = (select, wrapper, trigger, edge = 'selected') => {
      if (select.disabled || select.options.length === 0) return;
      if (activeSelect === select) { close(); return; }
      close();
      activeSelect = select;
      activeWrapper = wrapper;
      activeTrigger = trigger;
      activeIndex = edge === 'last'
        ? (visibleOptions(select).filter(({ option, group }) => !option.disabled && !group?.disabled).at(-1)?.index ?? -1)
        : Math.max(0, select.selectedIndex);
      activeMenu = document.createElement('div');
      activeMenu.className = 'atd-select-options';
      activeMenu.id = nextId('atd-select-options');
      activeMenu.setAttribute('role', 'listbox');
      activeMenu.setAttribute('aria-label', trigger.getAttribute('aria-label') || trigger.getAttribute('aria-labelledby') || '');
      activeMenu.dir = getComputedStyle(select).direction;
      document.body.appendChild(activeMenu);
      trigger.setAttribute('aria-expanded', 'true');
      trigger.setAttribute('aria-controls', activeMenu.id);
      renderOptions();
      optionObserver = new MutationObserver(() => {
        syncTrigger(select, trigger);
        renderOptions();
      });
      optionObserver.observe(select, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['disabled', 'hidden', 'label', 'selected']
      });
    };

    const bindSelect = (select) => {
      if (initialized.has(select) || select.multiple || select.hasAttribute('size') || select.hasAttribute('data-native-select')) return;

      // A cloned product-mapping row also clones generated markup and data attributes,
      // but not event listeners. Unwrap that clone before creating a fresh component.
      if (select.parentElement?.classList.contains('atd-select') && !initialized.has(select)) {
        const staleWrapper = select.parentElement;
        staleWrapper.parentNode.insertBefore(select, staleWrapper);
        staleWrapper.remove();
      }

      initialized.add(select);
      const wrapper = document.createElement('div');
      wrapper.className = 'atd-select';
      wrapper.dataset.atdSelectWrapper = 'true';
      const trigger = document.createElement('button');
      trigger.type = 'button';
      trigger.className = 'atd-select-trigger';
      trigger.id = nextId('atd-select-trigger');
      trigger.setAttribute('role', 'combobox');
      trigger.setAttribute('aria-haspopup', 'listbox');
      trigger.setAttribute('aria-expanded', 'false');
      trigger.setAttribute('aria-controls', nextId('atd-select-list'));
      const value = document.createElement('span');
      value.className = 'atd-select-value';
      const arrow = document.createElement('span');
      arrow.className = 'atd-select-arrow';
      arrow.setAttribute('aria-hidden', 'true');
      trigger.append(value, arrow);

      const labels = Array.from(select.labels || []);
      if (!labels.length) {
        const fallbackLabel = select.closest('.field')?.querySelector('label');
        if (fallbackLabel) labels.push(fallbackLabel);
      }
      const labelIds = [];
      labels.forEach((label) => {
        if (!label.id) label.id = nextId('atd-select-label');
        labelIds.push(label.id);
        label.htmlFor = trigger.id;
      });
      if (labelIds.length) trigger.setAttribute('aria-labelledby', labelIds.join(' '));
      else if (select.getAttribute('aria-label')) trigger.setAttribute('aria-label', select.getAttribute('aria-label'));
      const describedBy = select.getAttribute('aria-describedby');
      if (describedBy) trigger.setAttribute('aria-describedby', describedBy);
      if (select.required) trigger.setAttribute('aria-required', 'true');

      select.dataset.atdSelectReady = 'true';
      select.classList.add('atd-select-native');
      select.tabIndex = -1;
      select.setAttribute('aria-hidden', 'true');
      select.parentNode.insertBefore(wrapper, select);
      wrapper.append(trigger, select);
      syncTrigger(select, trigger);

      trigger.addEventListener('click', () => {
        if (activeSelect === select) close();
        else open(select, wrapper, trigger);
      });
      trigger.addEventListener('keydown', (event) => {
        if (activeSelect !== select) {
          if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            open(select, wrapper, trigger, 'selected');
          } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            open(select, wrapper, trigger, 'last');
          }
          return;
        }
        if (event.key === 'Escape') {
          event.preventDefault();
          close(true);
        } else if (event.key === 'Tab') {
          close();
        } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
          event.preventDefault();
          moveActive(event.key === 'ArrowDown' ? 1 : -1);
        } else if (event.key === 'Home' || event.key === 'End') {
          event.preventDefault();
          const candidates = activeOptions.filter(({ option, group }) => !option.disabled && !group?.disabled);
          if (candidates.length) {
            activeIndex = event.key === 'Home' ? candidates[0].index : candidates[candidates.length - 1].index;
            renderOptions();
          }
        } else if (event.key === 'Enter') {
          event.preventDefault();
          selectOption(activeIndex);
        } else if (event.key.length === 1 && !event.altKey && !event.ctrlKey && !event.metaKey) {
          const needle = event.key.toLocaleLowerCase();
          const startAt = activeIndex + 1;
          const match = activeOptions.find(({ option, group, index }) =>
            index >= startAt && !option.disabled && !group?.disabled && (option.label || option.textContent).trim().toLocaleLowerCase().startsWith(needle))
            || activeOptions.find(({ option, group }) =>
              !option.disabled && !group?.disabled && (option.label || option.textContent).trim().toLocaleLowerCase().startsWith(needle));
          if (match) {
            activeIndex = match.index;
            renderOptions();
          }
        }
      });

      trigger.addEventListener('invalid', (event) => event.preventDefault());
      select.addEventListener('invalid', (event) => {
        event.preventDefault();
        trigger.setAttribute('aria-invalid', 'true');
        trigger.focus({ preventScroll: true });
      });
      select.addEventListener('change', () => {
        syncTrigger(select, trigger);
        if (activeSelect === select) renderOptions();
      });
      const selectObserver = new MutationObserver(() => syncTrigger(select, trigger));
      selectObserver.observe(select, { attributes: true, attributeFilter: ['disabled', 'required', 'aria-invalid'] });
    };

    document.querySelectorAll(selectSelector).forEach(bindSelect);
    document.addEventListener('pointerdown', (event) => {
      if (!activeSelect || activeTrigger?.contains(event.target) || activeMenu?.contains(event.target)) return;
      close();
    });
    document.addEventListener('click', (event) => {
      const item = event.target.closest?.('.atd-select-option[data-option-index]');
      if (!item || !activeSelect || !activeMenu?.contains(item) || item.classList.contains('is-disabled')) return;
      selectOption(Number(item.dataset.optionIndex));
    });
    document.addEventListener('pointermove', (event) => {
      const item = event.target.closest?.('.atd-select-option[data-option-index]');
      if (!item || !activeMenu?.contains(item) || item.classList.contains('is-disabled')) return;
      const index = Number(item.dataset.optionIndex);
      if (index !== activeIndex) {
        activeIndex = index;
        activeMenu.querySelectorAll('.atd-select-option').forEach((option) => {
          const active = Number(option.dataset.optionIndex) === activeIndex;
          option.classList.toggle('is-active', active);
          if (active) activeTrigger.setAttribute('aria-activedescendant', option.id);
        });
      }
    });
    window.addEventListener('resize', positionMenu, { passive: true });
    window.addEventListener('scroll', positionMenu, true);
    window.visualViewport?.addEventListener('resize', positionMenu, { passive: true });
    window.visualViewport?.addEventListener('scroll', positionMenu, { passive: true });

    const addedObserver = new MutationObserver((records) => records.forEach((record) => record.addedNodes.forEach((node) => {
      if (node.nodeType !== Node.ELEMENT_NODE) return;
      if (node.matches?.(selectSelector)) bindSelect(node);
      node.querySelectorAll?.(selectSelector).forEach(bindSelect);
    })));
    addedObserver.observe(document.body, { childList: true, subtree: true });
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
