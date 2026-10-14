<?php
/**
 * Shared bootstrap for every REST API endpoint.
 *
 * Deliberately does NOT include src/auth/auth_check.php — the API is
 * authenticated by API key (see api_auth_helper.php), not by the
 * browser session that the rest of the CRM uses. Existing web pages
 * and actions are untouched and keep working exactly as before.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../tenant_helper.php';
require_once __DIR__ . '/../lead_source_helper.php';
require_once __DIR__ . '/../property_helper.php';
require_once __DIR__ . '/api_auth_helper.php';

// Every endpoint responds as JSON, including fatal errors.
header('Content-Type: application/json');

$company_id = require_api_auth($conn);
