<?php
/**
 * Lead Source Management helper.
 *
 * Provides the canonical list of supported lead sources plus small helpers
 * used across the registration form and the dashboard statistics.
 *
 * The `lead_source` column itself is added to `clients` self-healingly by
 * ensure_company_id_columns() in tenant_helper.php, exactly like every
 * other additive column in this app (pipeline_stage, company_id, ...).
 */

require_once __DIR__ . '/access_helper.php';

// Canonical, ordered list of supported lead sources.
if (!defined('LEAD_SOURCES')) {
    define('LEAD_SOURCES', [
        'Facebook',
        'Instagram',
        'TikTok',
        'Website',
        'WhatsApp',
        'Referral',
    ]);
}

/**
 * Font Awesome icon class for each lead source, used by the registration
 * form and the dashboard statistics cards/chart.
 */
function get_lead_source_icon($source) {
    $icons = [
        'Facebook' => 'fa-brands fa-facebook',
        'Instagram' => 'fa-brands fa-instagram',
        'TikTok' => 'fa-brands fa-tiktok',
        'Website' => 'fa-solid fa-globe',
        'WhatsApp' => 'fa-brands fa-whatsapp',
        'Referral' => 'fa-solid fa-user-group',
    ];
    return $icons[$source] ?? 'fa-solid fa-circle-question';
}

/**
 * Brand-ish accent color for each lead source, used to color the stat card
 * icon badges and the chart segments consistently.
 */
function get_lead_source_color($source) {
    $colors = [
        'Facebook' => '#1877F2',
        'Instagram' => '#E1306C',
        'TikTok' => '#010101',
        'Website' => '#0d6efd',
        'WhatsApp' => '#25D366',
        'Referral' => '#B48C42',
    ];
    return $colors[$source] ?? '#6c757d';
}

/**
 * Returns true if the given value is one of the canonical lead sources.
 */
function is_valid_lead_source($source) {
    return in_array($source, LEAD_SOURCES, true);
}

/**
 * Normalizes any lead source coming from the database or a form submission:
 * unknown / empty values (e.g. clients created before this feature existed)
 * fall back to "Website" so every client always has a valid source.
 */
function normalize_lead_source($source) {
    $source = trim((string) $source);
    return is_valid_lead_source($source) ? $source : 'Website';
}

/**
 * Returns lead counts per source (always includes every canonical source,
 * even ones with zero leads, so charts/cards render a stable, complete set).
 *
 * Scope matches the rest of the app: company admins see the whole
 * company, sales managers see their team, employees see their own clients.
 */
function get_lead_source_stats($conn, $company_id, $user_id = null, $is_manager = null) {
    $counts = array_fill_keys(LEAD_SOURCES, 0);
    $total = 0;

    $filter = access_user_filter($conn, 'user_id');
    $sql = "SELECT lead_source, COUNT(*) as count FROM clients WHERE company_id = ?" . $filter['sql'] . " GROUP BY lead_source";
    $stmt = $conn->prepare($sql);
    $types = 'i' . $filter['types'];
    $params = array_merge([$company_id], $filter['params']);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $source = normalize_lead_source($row['lead_source'] ?? '');
        $counts[$source] += (int) $row['count'];
        $total += (int) $row['count'];
    }
    $stmt->close();

    return [
        'counts' => $counts,
        'total' => $total,
        'generated_at' => date('c'),
    ];
}
