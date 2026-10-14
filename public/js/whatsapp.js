// whatsapp.js
// Powers every ".whatsapp-modal" rendered by render_whatsapp_modal() (src/helpers.php):
//   - Quick Templates: fills the message box with a pre-written, translated message
//   - Send Message: opens wa.me with the typed/templated text pre-filled
//   - Open Chat: plain wa.me link (already wired via href, no JS needed)
document.addEventListener('DOMContentLoaded', function () {
    // Trigger buttons use data-whatsapp-target (not data-bs-toggle) so we can
    // cleanly close a parent modal (e.g. "Client Details") before opening the
    // WhatsApp modal, instead of stacking two Bootstrap modals/backdrops.
    document.querySelectorAll('.whatsapp-trigger-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (typeof bootstrap === 'undefined') return;
            const targetEl = document.querySelector(btn.dataset.whatsappTarget);
            if (!targetEl) return;
            const targetModal = bootstrap.Modal.getOrCreateInstance(targetEl);
            const parentEl = btn.closest('.modal');

            if (parentEl && parentEl !== targetEl) {
                const parentModal = bootstrap.Modal.getInstance(parentEl);
                if (parentModal) {
                    parentEl.addEventListener('hidden.bs.modal', function onHidden() {
                        parentEl.removeEventListener('hidden.bs.modal', onHidden);
                        targetModal.show();
                    });
                    parentModal.hide();
                    return;
                }
            }
            targetModal.show();
        });
    });

    const modals = document.querySelectorAll('.whatsapp-modal');
    if (!modals.length) return;

    // Maps each quick-template button to its message translation key.
    const TEMPLATE_MESSAGE_KEYS = {
        tpl_followup: 'wa_tpl_followup_msg',
        tpl_meeting: 'wa_tpl_meeting_msg',
        tpl_thanks: 'wa_tpl_thanks_msg',
        tpl_payment: 'wa_tpl_payment_msg',
        tpl_offer: 'wa_tpl_offer_msg',
    };

    function currentLang() {
        return document.documentElement.lang || 'en';
    }

    function translate(key, fallback) {
        if (typeof window.tr === 'function') {
            return window.tr(key, fallback);
        }
        if (typeof translations !== 'undefined' && translations[key] && translations[key][currentLang()]) {
            return translations[key][currentLang()];
        }
        return fallback !== undefined ? fallback : '';
    }

    function fillPlaceholders(str, data) {
        return (str || '')
            .replace(/\{name\}/g, data.name || '')
            .replace(/\{project\}/g, data.project || '');
    }

    modals.forEach(function (modal) {
        const phone = modal.dataset.phone || '';
        const clientName = modal.dataset.clientName || '';
        const projectName = modal.dataset.projectName || '';
        const textarea = modal.querySelector('.whatsapp-message-input');
        const sendBtn = modal.querySelector('.whatsapp-send-btn');
        const templateBtns = modal.querySelectorAll('.whatsapp-template-btn');

        templateBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!textarea) return;
                const msgKey = TEMPLATE_MESSAGE_KEYS[btn.dataset.templateKey];
                const rawMessage = translate(msgKey, '');
                textarea.value = fillPlaceholders(rawMessage, {
                    name: clientName,
                    project: projectName || translate('whatsapp_default_project', 'our properties'),
                });
                textarea.focus();

                templateBtns.forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
            });
        });

        if (sendBtn) {
            sendBtn.addEventListener('click', function () {
                if (!phone) return;
                const message = textarea ? textarea.value.trim() : '';
                const url = 'https://wa.me/' + phone + (message ? ('?text=' + encodeURIComponent(message)) : '');
                window.open(url, '_blank', 'noopener');
            });
        }
    });
});
