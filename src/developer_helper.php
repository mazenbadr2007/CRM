<?php
/**
 * Developers module helper.
 *
 * Structure: Developer -> has many Projects -> each Project links to
 * properties in the existing `properties` table via `project_id`.
 * Self-healing (CREATE TABLE IF NOT EXISTS) and scoped by company_id,
 * following the same pattern as property_helper.php.
 */

function ensure_developers_tables($conn) {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $conn->query("CREATE TABLE IF NOT EXISTS `developers` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `company_id` int(11) DEFAULT NULL,
        `user_id` int(11) DEFAULT NULL,
        `name` varchar(150) NOT NULL,
        `phone` varchar(50) DEFAULT NULL,
        `email` varchar(150) DEFAULT NULL,
        `notes` text DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `company_id` (`company_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS `developer_projects` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `company_id` int(11) DEFAULT NULL,
        `developer_id` int(11) NOT NULL,
        `name` varchar(150) NOT NULL,
        `location` varchar(150) DEFAULT NULL,
        `description` text DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `company_id` (`company_id`),
        KEY `developer_id` (`developer_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Link properties to a project. Added defensively since the
    // `properties` table already exists from the Property Inventory module.
    $col_check = $conn->query("SHOW COLUMNS FROM `properties` LIKE 'project_id'");
    if ($col_check && $col_check->num_rows === 0) {
        $conn->query("ALTER TABLE `properties` ADD COLUMN `project_id` int(11) DEFAULT NULL AFTER `project`");
        $conn->query("ALTER TABLE `properties` ADD KEY `project_id` (`project_id`)");
    }
}

/* ---------------- Developers ---------------- */

function get_developers($conn, $company_id) {
    ensure_developers_tables($conn);
    $stmt = $conn->prepare(
        "SELECT d.*,
                (SELECT COUNT(*) FROM developer_projects p WHERE p.developer_id = d.id) AS projects_count
         FROM developers d
         WHERE d.company_id = ?
         ORDER BY d.created_at DESC"
    );
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function get_developer($conn, $developer_id, $company_id) {
    ensure_developers_tables($conn);
    $stmt = $conn->prepare("SELECT * FROM developers WHERE id = ? AND company_id = ?");
    $stmt->bind_param("ii", $developer_id, $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->num_rows > 0 ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row;
}

function create_developer($conn, $company_id, $user_id, $data) {
    ensure_developers_tables($conn);
    $stmt = $conn->prepare(
        "INSERT INTO developers (company_id, user_id, name, phone, email, notes) VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        "iissss",
        $company_id,
        $user_id,
        $data['name'],
        $data['phone'],
        $data['email'],
        $data['notes']
    );
    $ok = $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();
    return $ok ? $id : null;
}

function update_developer($conn, $developer_id, $company_id, $data) {
    ensure_developers_tables($conn);
    $stmt = $conn->prepare(
        "UPDATE developers SET name = ?, phone = ?, email = ?, notes = ? WHERE id = ? AND company_id = ?"
    );
    $stmt->bind_param(
        "ssssii",
        $data['name'],
        $data['phone'],
        $data['email'],
        $data['notes'],
        $developer_id,
        $company_id
    );
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function delete_developer($conn, $developer_id, $company_id) {
    ensure_developers_tables($conn);
    // Detach properties linked through this developer's projects, then
    // remove the projects, then the developer itself.
    $stmt = $conn->prepare(
        "UPDATE properties p
         JOIN developer_projects dp ON p.project_id = dp.id
         SET p.project_id = NULL
         WHERE dp.developer_id = ? AND p.company_id = ?"
    );
    $stmt->bind_param("ii", $developer_id, $company_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM developer_projects WHERE developer_id = ? AND company_id = ?");
    $stmt->bind_param("ii", $developer_id, $company_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM developers WHERE id = ? AND company_id = ?");
    $stmt->bind_param("ii", $developer_id, $company_id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/* ---------------- Projects ---------------- */

function get_projects_by_developer($conn, $developer_id, $company_id) {
    ensure_developers_tables($conn);
    $stmt = $conn->prepare(
        "SELECT p.*,
                (SELECT COUNT(*) FROM properties pr WHERE pr.project_id = p.id) AS properties_count
         FROM developer_projects p
         WHERE p.developer_id = ? AND p.company_id = ?
         ORDER BY p.created_at DESC"
    );
    $stmt->bind_param("ii", $developer_id, $company_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function get_all_projects($conn, $company_id) {
    ensure_developers_tables($conn);
    $stmt = $conn->prepare(
        "SELECT p.*, d.name AS developer_name,
                (SELECT COUNT(*) FROM properties pr WHERE pr.project_id = p.id) AS properties_count
         FROM developer_projects p
         JOIN developers d ON d.id = p.developer_id
         WHERE p.company_id = ?
         ORDER BY p.created_at DESC"
    );
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function get_project($conn, $project_id, $company_id) {
    ensure_developers_tables($conn);
    $stmt = $conn->prepare("SELECT * FROM developer_projects WHERE id = ? AND company_id = ?");
    $stmt->bind_param("ii", $project_id, $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->num_rows > 0 ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row;
}

function create_project($conn, $company_id, $developer_id, $data) {
    ensure_developers_tables($conn);
    $stmt = $conn->prepare(
        "INSERT INTO developer_projects (company_id, developer_id, name, location, description) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        "iisss",
        $company_id,
        $developer_id,
        $data['name'],
        $data['location'],
        $data['description']
    );
    $ok = $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();
    return $ok ? $id : null;
}

function update_project($conn, $project_id, $company_id, $data) {
    ensure_developers_tables($conn);
    $stmt = $conn->prepare(
        "UPDATE developer_projects SET name = ?, location = ?, description = ? WHERE id = ? AND company_id = ?"
    );
    $stmt->bind_param(
        "sssii",
        $data['name'],
        $data['location'],
        $data['description'],
        $project_id,
        $company_id
    );
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function delete_project($conn, $project_id, $company_id) {
    ensure_developers_tables($conn);
    $stmt = $conn->prepare("UPDATE properties SET project_id = NULL WHERE project_id = ? AND company_id = ?");
    $stmt->bind_param("ii", $project_id, $company_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM developer_projects WHERE id = ? AND company_id = ?");
    $stmt->bind_param("ii", $project_id, $company_id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/* ---------------- Properties linked to a project ---------------- */

function get_properties_by_project($conn, $project_id, $company_id) {
    ensure_developers_tables($conn);
    $stmt = $conn->prepare("SELECT * FROM properties WHERE project_id = ? AND company_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("ii", $project_id, $company_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}
