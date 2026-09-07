import './bootstrap';
import 'bootstrap';
import $ from 'jquery';
import DataTable from 'datatables.net-bs5';

window.$ = window.jQuery = $;
window.DataTable = DataTable;

window.confirmDelete = function (message = 'آیا از حذف این مورد مطمئن هستید؟') {
    return window.confirm(message);
};

const setSidebar = (open) => {
    document.body.classList.toggle('sidebar-open', open);
    document.querySelector('[data-sidebar-open]')?.setAttribute('aria-expanded', open ? 'true' : 'false');
};

const lockInquiryForm = (form) => {
    if (form.dataset.submitting === '1') return false;
    if (!form.checkValidity()) return true;

    form.dataset.submitting = '1';
    const button = form.querySelector('[data-inquiry-submit-button], button[type="submit"]');
    if (button) {
        button.dataset.originalHtml = button.innerHTML;
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> در حال دریافت پاسخ…';
    }

    return true;
};

const resetInquiryForms = () => {
    document.querySelectorAll('form[data-inquiry-submit]').forEach((form) => {
        if (form.dataset.submitting !== '1') return;

        form.dataset.submitting = '0';
        const button = form.querySelector('[data-inquiry-submit-button], button[type="submit"]');
        if (!button) return;
        if (button.dataset.originalHtml) button.innerHTML = button.dataset.originalHtml;
        button.removeAttribute('aria-busy');
        button.disabled = false;
    });
};

document.addEventListener('submit', (event) => {
    const form = event.target.closest('form[data-inquiry-submit]');
    if (!form) return;

    if (form.dataset.submitting === '1') {
        event.preventDefault();
        return;
    }

    lockInquiryForm(form);
});

document.addEventListener('click', (event) => {
    if (event.target.closest('[data-sidebar-open]')) setSidebar(true);
    if (event.target.closest('[data-sidebar-close]') || event.target.closest('.app-sidebar .sidebar-link')) setSidebar(false);
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') setSidebar(false);
});

window.addEventListener('pageshow', (event) => {
    if (event.persisted) resetInquiryForms();
});
