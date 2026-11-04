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

  const mount = () => {
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

    document.querySelectorAll('[data-ui-password-toggle]').forEach((button) => {
      button.addEventListener('click', () => {
        const value = button.previousElementSibling;
        if (!value) return;
        const show = value.textContent === '••••••••';
        value.textContent = show ? value.dataset.password || '' : '••••••••';
        button.innerHTML = show
          ? '<svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3l18 18"/><path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"/><path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c5.2 0 8.8 4.8 9.5 7-.3 1-1.1 2.2-2.3 3.3"/><path d="M6.2 6.2C3.8 7.7 2.8 10.1 2.5 12c.5 1.8 4.1 7 9.5 7 1 0 2-.2 2.9-.6"/></svg>'
          : '<svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z"/><circle cx="12" cy="12" r="3"/></svg>';
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
