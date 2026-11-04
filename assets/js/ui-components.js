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
        button.textContent = language() === 'en'
          ? (show ? 'Hide password' : 'Show password')
          : (show ? 'مخفی کردن' : 'نمایش رمز');
        button.setAttribute('aria-pressed', String(show));
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
