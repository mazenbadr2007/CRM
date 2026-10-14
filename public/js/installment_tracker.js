// installment_tracker.js
// Handles the Add/Edit Installment modal, the status filter, and the
// instant "mark as paid / unpaid" toggle (AJAX, no page reload) on the
// Installment Tracker page.

function openCreateInstallmentModal() {
    document.getElementById('installmentModalTitle').setAttribute('data-translate-key', 'installment_modal_add_title');
    document.getElementById('installmentModalTitle').textContent = (window.tr ? window.tr('installment_modal_add_title', 'Add Installment') : 'Add Installment');
    document.getElementById('installmentForm').reset();
    document.getElementById('installment_id').value = '';
}

function openEditInstallmentModal(inst) {
    document.getElementById('installmentModalTitle').setAttribute('data-translate-key', 'installment_modal_edit_title');
    document.getElementById('installmentModalTitle').textContent = (window.tr ? window.tr('installment_modal_edit_title', 'Edit Installment') : 'Edit Installment');
    document.getElementById('installment_id').value = inst.id;
    document.getElementById('installment_property_id').value = inst.property_id;
    document.getElementById('installment_due_date').value = inst.due_date || '';
    document.getElementById('installment_amount').value = inst.amount || '';
    document.getElementById('installment_note').value = inst.note || '';
}

document.addEventListener('DOMContentLoaded', function() {
    const filterSelect = document.getElementById('it_status_filter');
    const tableBody = document.getElementById('it_table_body');
    const cardsContainer = document.getElementById('it_cards_container');

    function applyFilter() {
        const value = filterSelect.value;
        document.querySelectorAll('[data-installment-status]').forEach(function(row) {
            const status = row.getAttribute('data-installment-status');
            row.style.display = (value === 'all' || value === status) ? '' : 'none';
        });
    }

    if (filterSelect) {
        filterSelect.addEventListener('change', applyFilter);
    }

    // Instant paid/unpaid toggle via AJAX — updates the badge and icon in
    // place without a page reload, on both the table row and mobile card
    // that share the same installment id.
    function bindToggleButtons() {
        document.querySelectorAll('.it-toggle-paid-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const currentlyPaid = this.getAttribute('data-paid') === '1';
                const nextPaid = !currentlyPaid;

                const formData = new FormData();
                formData.append('installment_id', id);
                formData.append('is_paid', nextPaid ? '1' : '0');

                fetch('../src/actions/handle_installment_toggle_paid.php', {
                    method: 'POST',
                    body: formData
                })
                .then(function(res) { return res.json(); })
                .then(function(json) {
                    if (!json.success) {
                        alert(window.tr ? window.tr('it_toggle_error', 'Could not update installment status.') : 'Could not update installment status.');
                        return;
                    }
                    updateRowStatus(id, json.status, nextPaid);
                })
                .catch(function() {
                    alert(window.tr ? window.tr('it_toggle_error', 'Could not update installment status.') : 'Could not update installment status.');
                });
            });
        });
    }

    function statusToBadgeClass(status) {
        if (status === 'paid') return 'bg-success';
        if (status === 'overdue') return 'bg-danger';
        return 'bg-secondary';
    }

    function statusToKey(status) {
        if (status === 'paid') return 'status_paid';
        if (status === 'overdue') return 'status_overdue';
        return 'status_pending';
    }

    function updateRowStatus(id, status, isPaid) {
        document.querySelectorAll('.it-toggle-paid-btn[data-id="' + id + '"]').forEach(function(btn) {
            btn.setAttribute('data-paid', isPaid ? '1' : '0');
            const icon = btn.querySelector('i');
            if (icon) {
                icon.className = isPaid ? 'fa-solid fa-rotate-left' : 'fa-solid fa-check';
            }
            const titleKey = isPaid ? btn.getAttribute('data-mark-unpaid-key') : btn.getAttribute('data-mark-paid-key');
            if (titleKey && window.tr) {
                btn.title = window.tr(titleKey, isPaid ? 'Mark as unpaid' : 'Mark as paid');
            }

            const container = btn.closest('[data-installment-status]');
            if (container) {
                container.setAttribute('data-installment-status', status);
                const badge = container.querySelector('.it-status-badge');
                if (badge) {
                    badge.className = 'badge it-status-badge ' + statusToBadgeClass(status);
                    const key = statusToKey(status);
                    badge.setAttribute('data-translate-key', key);
                    badge.textContent = (window.tr ? window.tr(key, status) : status);
                }
            }
        });

        if (filterSelect) {
            applyFilter();
        }
    }

    bindToggleButtons();
    applyFilter();

    // Confirm-delete using the current language instead of a hardcoded
    // English confirm() string (same pattern as Property Inventory).
    document.querySelectorAll('.installment-delete-link').forEach(function(link) {
        link.addEventListener('click', function(e) {
            const key = this.getAttribute('data-confirm-key');
            const message = (window.tr ? window.tr(key, 'Delete this installment?') : 'Delete this installment?');
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });

    // Re-run translations whenever the language changes, since this
    // page's table/card rows are rendered by PHP (not re-fetched) and the
    // modal title text is set dynamically above.
    document.addEventListener('languageChanged', function() {
        if (!window.tr) {
            return;
        }
        const modalTitleEl = document.getElementById('installmentModalTitle');
        if (modalTitleEl) {
            const key = modalTitleEl.getAttribute('data-translate-key');
            modalTitleEl.textContent = window.tr(key, modalTitleEl.textContent);
        }
        document.querySelectorAll('.it-toggle-paid-btn').forEach(function(btn) {
            const isPaid = btn.getAttribute('data-paid') === '1';
            const titleKey = isPaid ? btn.getAttribute('data-mark-unpaid-key') : btn.getAttribute('data-mark-paid-key');
            if (titleKey) {
                btn.title = window.tr(titleKey, isPaid ? 'Mark as unpaid' : 'Mark as paid');
            }
        });
    });
});
