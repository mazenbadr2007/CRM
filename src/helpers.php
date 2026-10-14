<?php
function T($value_from_database, $key_override = null) {
    if (empty($value_from_database)) {
        return 'N/A';
    }
    if ($key_override !== null) {
        $translation_key = $key_override;
    } else {
        $translation_key = str_replace(' ', '_', $value_from_database);
        $translation_key = str_replace('-', '_', $translation_key);
    }

    return "<span data-translate-key=\"" . htmlspecialchars($translation_key) . "\">" . htmlspecialchars($value_from_database) . "</span>";
}

/**
 * Renders a reusable "WhatsApp" modal for a given client: lets the user
 * pick a quick message template, edit it, and either send it (opens
 * wa.me with the text pre-filled) or just open the chat directly.
 * Include the returned markup once per client, then trigger it from any
 * button/link with data-bs-toggle="modal" data-bs-target="#<id>".
 * Requires js/whatsapp.js (loaded in footer.php) to wire up the buttons.
 */
function whatsapp_modal_id($client_id) {
    return 'whatsappModal' . (int)$client_id;
}

function render_whatsapp_modal($client) {
    $modalId = whatsapp_modal_id($client['id'] ?? 0);
    $phoneDigits = preg_replace('/\D/', '', $client['phone_number'] ?? '');
    $name = htmlspecialchars($client['client_name'] ?? '', ENT_QUOTES);
    $project = htmlspecialchars($client['project_name'] ?? '', ENT_QUOTES);

    ob_start();
    ?>
    <div class="modal fade whatsapp-modal" id="<?php echo $modalId; ?>" tabindex="-1"
         aria-labelledby="<?php echo $modalId; ?>Label" aria-hidden="true"
         data-phone="<?php echo htmlspecialchars($phoneDigits); ?>"
         data-client-name="<?php echo $name; ?>"
         data-project-name="<?php echo $project; ?>">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="<?php echo $modalId; ?>Label">
                        <i class="fa-brands fa-whatsapp me-2" style="color:#25D366;"></i>
                        <span data-translate-key="whatsapp_modal_title">WhatsApp</span>
                        &mdash; <?php echo htmlspecialchars($client['client_name'] ?? ''); ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (empty($phoneDigits)): ?>
                        <p class="text-muted mb-0" data-translate-key="whatsapp_no_phone">No phone number on file for this client.</p>
                    <?php else: ?>
                        <div class="whatsapp-templates mb-3">
                            <label class="form-label small text-muted" data-translate-key="whatsapp_quick_templates">Quick Templates</label>
                            <div class="whatsapp-template-chips d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary whatsapp-template-btn" data-template-key="tpl_followup"><span data-translate-key="wa_tpl_followup">Follow-up</span></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary whatsapp-template-btn" data-template-key="tpl_meeting"><span data-translate-key="wa_tpl_meeting">Meeting Reminder</span></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary whatsapp-template-btn" data-template-key="tpl_thanks"><span data-translate-key="wa_tpl_thanks">Thank You</span></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary whatsapp-template-btn" data-template-key="tpl_payment"><span data-translate-key="wa_tpl_payment">Payment Reminder</span></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary whatsapp-template-btn" data-template-key="tpl_offer"><span data-translate-key="wa_tpl_offer">New Offer</span></button>
                            </div>
                        </div>
                        <div class="mb-1">
                            <label class="form-label small text-muted" data-translate-key="whatsapp_message_label">Message</label>
                            <textarea class="form-control whatsapp-message-input" rows="4" data-translate-key="whatsapp_message_ph" placeholder="Type a message or pick a template above..."></textarea>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($phoneDigits)): ?>
                <div class="modal-footer">
                    <a href="https://wa.me/<?php echo htmlspecialchars($phoneDigits); ?>" target="_blank" class="btn btn-sm btn-outline-success whatsapp-open-chat-btn">
                        <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> <span data-translate-key="whatsapp_open_chat_btn">Open Chat</span>
                    </a>
                    <button type="button" class="btn btn-sm btn-whatsapp whatsapp-send-btn">
                        <i class="fa-brands fa-whatsapp me-1"></i> <span data-translate-key="whatsapp_send_btn">Send Message</span>
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}