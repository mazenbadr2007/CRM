<?php
/**
 * Property Inventory helper.
 *
 * Self-healing (CREATE TABLE IF NOT EXISTS), scoped by company_id like
 * every other tenant-aware table.
 */

function ensure_properties_table($conn) {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $conn->query("CREATE TABLE IF NOT EXISTS `properties` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `company_id` int(11) DEFAULT NULL,
        `user_id` int(11) DEFAULT NULL,
        `developer` varchar(150) NOT NULL,
        `project` varchar(150) NOT NULL,
        `unit_type` varchar(100) NOT NULL,
        `area` decimal(10,2) DEFAULT NULL,
        `bedrooms` int(11) DEFAULT NULL,
        `price` decimal(14,2) DEFAULT NULL,
        `down_payment` decimal(14,2) DEFAULT NULL,
        `installments` varchar(150) DEFAULT NULL,
        `status` varchar(30) NOT NULL DEFAULT 'available',
        `company_commission_type` varchar(10) NOT NULL DEFAULT 'percent',
        `company_commission_value` decimal(10,2) DEFAULT NULL,
        `sales_commission_type` varchar(10) NOT NULL DEFAULT 'percent',
        `sales_commission_value` decimal(10,2) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `company_id` (`company_id`),
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Self-healing: add commission columns for installs that already have
    // a `properties` table from before the Commission Calculator feature.
    require_once __DIR__ . '/tenant_helper.php';
    _ensure_column($conn, 'properties', 'company_commission_type', "varchar(10) NOT NULL DEFAULT 'percent'");
    _ensure_column($conn, 'properties', 'company_commission_value', 'decimal(10,2) DEFAULT NULL');
    _ensure_column($conn, 'properties', 'sales_commission_type', "varchar(10) NOT NULL DEFAULT 'percent'");
    _ensure_column($conn, 'properties', 'sales_commission_value', 'decimal(10,2) DEFAULT NULL');
}

function get_properties($conn, $company_id) {
    ensure_properties_table($conn);
    $stmt = $conn->prepare("SELECT * FROM properties WHERE company_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function get_property($conn, $property_id, $company_id) {
    ensure_properties_table($conn);
    $stmt = $conn->prepare("SELECT * FROM properties WHERE id = ? AND company_id = ?");
    $stmt->bind_param("ii", $property_id, $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->num_rows > 0 ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row;
}

function create_property($conn, $company_id, $user_id, $data) {
    ensure_properties_table($conn);
    $stmt = $conn->prepare(
        "INSERT INTO properties (company_id, user_id, developer, project, unit_type, area, bedrooms, price, down_payment, installments, status, company_commission_type, company_commission_value, sales_commission_type, sales_commission_value)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        "iisssdidsssdssd",
        $company_id,
        $user_id,
        $data['developer'],
        $data['project'],
        $data['unit_type'],
        $data['area'],
        $data['bedrooms'],
        $data['price'],
        $data['down_payment'],
        $data['installments'],
        $data['status'],
        $data['company_commission_type'],
        $data['company_commission_value'],
        $data['sales_commission_type'],
        $data['sales_commission_value']
    );
    $ok = $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();
    return $ok ? $id : null;
}

function update_property($conn, $property_id, $company_id, $data) {
    ensure_properties_table($conn);
    $stmt = $conn->prepare(
        "UPDATE properties SET developer = ?, project = ?, unit_type = ?, area = ?, bedrooms = ?, price = ?, down_payment = ?, installments = ?, status = ?, company_commission_type = ?, company_commission_value = ?, sales_commission_type = ?, sales_commission_value = ?
         WHERE id = ? AND company_id = ?"
    );
    $stmt->bind_param(
        "sssdidsssdssdii",
        $data['developer'],
        $data['project'],
        $data['unit_type'],
        $data['area'],
        $data['bedrooms'],
        $data['price'],
        $data['down_payment'],
        $data['installments'],
        $data['status'],
        $data['company_commission_type'],
        $data['company_commission_value'],
        $data['sales_commission_type'],
        $data['sales_commission_value'],
        $property_id,
        $company_id
    );
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function delete_property($conn, $property_id, $company_id) {
    ensure_properties_table($conn);
    $stmt = $conn->prepare("DELETE FROM properties WHERE id = ? AND company_id = ?");
    $stmt->bind_param("ii", $property_id, $company_id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}
