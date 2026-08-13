{{-- Small shared behaviours available on every authenticated page. --}}
<script>
(function () {
    /* Auto-dismiss flash alerts (validation errors stay put - the user needs
       to read those while fixing the form). */
    document.querySelectorAll('.alert-success, .alert-info').forEach((alert) => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.45s ease, transform 0.45s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-8px)';
            setTimeout(() => alert.remove(), 450);
        }, 5000);
    });

    /* Confirm before submitting anything marked data-confirm. */
    document.querySelectorAll('[data-confirm]').forEach((el) => {
        el.addEventListener('submit', (e) => {
            if (!window.confirm(el.dataset.confirm)) e.preventDefault();
        });
        el.addEventListener('click', (e) => {
            if (el.tagName !== 'FORM' && !window.confirm(el.dataset.confirm)) e.preventDefault();
        });
    });

    /* Auto-submit filter forms when a select changes, so filters work without
       a separate "Terapkan" button. */
    document.querySelectorAll('[data-auto-submit]').forEach((el) => {
        el.addEventListener('change', () => el.closest('form')?.submit());
    });

    /* Animate progress bars and chart bars into place on first paint. */
    requestAnimationFrame(() => {
        document.querySelectorAll('[data-width]').forEach((el) => {
            el.style.width = el.dataset.width + '%';
        });
        document.querySelectorAll('[data-height]').forEach((el) => {
            el.style.height = el.dataset.height + '%';
        });
    });
})();
</script>
