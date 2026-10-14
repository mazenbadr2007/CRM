<?php
/**
 * Sales Pipeline (Kanban) helper.
 *
 * Adds a `pipeline_stage` column to the existing `clients` table (self-healing,
 * like activity_logger.php does for client_activity_log) and provides the
 * canonical list of pipeline stages used by the Kanban board.
 *
 * This file does not touch or rename any existing column, so every other
 * page (dashboard, registration, timeline, charts, calendar...) keeps
 * working exactly as before.
 */

// Canonical, ordered list of pipeline stages.
define('PIPELINE_STAGES', [
    'New Lead',
    'Contacted',
    'Interested',
    'Visit Scheduled',
    'Negotiation',
    'Won',
    'Lost',
]);

/**
 * Adds the `pipeline_stage` column to `clients` the first time it's needed.
 * Safe to call repeatedly.
 */
function ensure_pipeline_stage_column($conn) {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $check = $conn->query("SHOW COLUMNS FROM `clients` LIKE 'pipeline_stage'");
    if ($check && $check->num_rows === 0) {
        $conn->query(
            "ALTER TABLE `clients` ADD COLUMN `pipeline_stage` VARCHAR(50) NOT NULL DEFAULT 'New Lead' AFTER `final_status`"
        );
    }
}

/**
 * Returns true if the given stage name is one of the canonical stages.
 */
function is_valid_pipeline_stage($stage) {
    return in_array($stage, PIPELINE_STAGES, true);
}

/**
 * Normalizes any stage coming from the database: unknown / empty values
 * (e.g. clients created before this feature existed) fall back to "New Lead"
 * so every client always appears somewhere on the board.
 */
function normalize_pipeline_stage($stage) {
    $stage = trim((string) $stage);
    return is_valid_pipeline_stage($stage) ? $stage : PIPELINE_STAGES[0];
}
