document.addEventListener('DOMContentLoaded', function () {
    const board = document.getElementById('kanbanBoard');
    if (!board) return;

    const toast = document.getElementById('kanbanToast');
    let toastTimer = null;

    function showToast(message, isError) {
        if (!toast) return;
        toast.textContent = message;
        toast.classList.toggle('error', !!isError);
        toast.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () {
            toast.classList.remove('show');
        }, 2500);
    }

    function updateColumnCounts() {
        board.querySelectorAll('.kanban-column').forEach(function (column) {
            const stage = column.getAttribute('data-stage');
            const body = column.querySelector('.kanban-column-body');
            const cardCount = body.querySelectorAll('.kanban-card').length;
            const counter = column.querySelector('.kanban-count[data-count-for="' + CSS.escape(stage) + '"]');
            if (counter) counter.textContent = cardCount;

            let emptyMsg = body.querySelector('.kanban-empty');
            if (cardCount === 0) {
                if (!emptyMsg) {
                    emptyMsg = document.createElement('div');
                    emptyMsg.className = 'kanban-empty';
                    emptyMsg.setAttribute('data-translate-key', 'pipeline_empty_column');
                    emptyMsg.textContent = (window.tr ? window.tr('pipeline_empty_column', 'No clients here yet.') : 'No clients here yet.');
                    body.appendChild(emptyMsg);
                }
            } else if (emptyMsg) {
                emptyMsg.remove();
            }
        });
    }

    function saveStage(clientId, newStage, card, revertFn) {
        const formData = new FormData();
        formData.append('client_id', clientId);
        formData.append('new_stage', newStage);

        fetch('../src/actions/handle_pipeline_update.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
            .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
            .then(function (result) {
                if (!result.ok || !result.data.success) {
                    throw new Error((result.data && result.data.error) || 'Update failed');
                }
                if (card) {
                    card.classList.add('just-moved');
                    setTimeout(function () { card.classList.remove('just-moved'); }, 450);
                }
                showToast('Status updated to "' + newStage + '"', false);
            })
            .catch(function (err) {
                console.error('Pipeline update error:', err);
                showToast('Could not save the change. Reverting.', true);
                if (typeof revertFn === 'function') revertFn();
                updateColumnCounts();
            });
    }

    function moveCardTo(card, targetStage, targetBody) {
        const clientId = card.getAttribute('data-client-id');
        const originalBody = card.parentElement;
        const originalNextSibling = card.nextSibling;

        targetBody.appendChild(card);
        // Keep the per-card select in sync with its new column.
        const select = card.querySelector('.kanban-stage-select');
        if (select) select.value = targetStage;

        updateColumnCounts();

        saveStage(clientId, targetStage, card, function revert() {
            if (originalNextSibling) {
                originalBody.insertBefore(card, originalNextSibling);
            } else {
                originalBody.appendChild(card);
            }
            if (select) {
                const originalStage = originalBody.getAttribute('data-stage-dropzone');
                select.value = originalStage;
            }
        });
    }

    // ---- Native HTML5 drag & drop (desktop) ----
    let draggedCard = null;

    board.addEventListener('dragstart', function (e) {
        const card = e.target.closest('.kanban-card');
        if (!card) return;
        draggedCard = card;
        card.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        try { e.dataTransfer.setData('text/plain', card.getAttribute('data-client-id')); } catch (err) { /* noop */ }
    });

    board.addEventListener('dragend', function (e) {
        const card = e.target.closest('.kanban-card');
        if (card) card.classList.remove('dragging');
        board.querySelectorAll('.kanban-column-body.drag-over').forEach(function (el) {
            el.classList.remove('drag-over');
        });
        draggedCard = null;
    });

    board.querySelectorAll('.kanban-column-body').forEach(function (body) {
        body.addEventListener('dragover', function (e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            body.classList.add('drag-over');
        });
        body.addEventListener('dragleave', function () {
            body.classList.remove('drag-over');
        });
        body.addEventListener('drop', function (e) {
            e.preventDefault();
            body.classList.remove('drag-over');
            if (!draggedCard) return;
            const targetStage = body.getAttribute('data-stage-dropzone');
            const sourceBody = draggedCard.parentElement;
            if (sourceBody === body) return; // dropped back in the same column
            moveCardTo(draggedCard, targetStage, body);
        });
    });

    // ---- Dropdown fallback (mobile / accessibility) ----
    board.addEventListener('change', function (e) {
        const select = e.target.closest('.kanban-stage-select');
        if (!select) return;
        const card = select.closest('.kanban-card');
        const targetStage = select.value;
        const targetBody = board.querySelector('.kanban-column-body[data-stage-dropzone="' + CSS.escape(targetStage) + '"]');
        if (!card || !targetBody) return;
        if (card.parentElement === targetBody) return;
        moveCardTo(card, targetStage, targetBody);
    });
});
