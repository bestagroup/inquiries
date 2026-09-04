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

document.addEventListener('click', (event) => {
    if (event.target.closest('[data-sidebar-open]')) setSidebar(true);
    if (event.target.closest('[data-sidebar-close]') || event.target.closest('.app-sidebar .sidebar-link')) setSidebar(false);
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') setSidebar(false);
});
