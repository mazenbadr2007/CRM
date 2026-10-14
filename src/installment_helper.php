<?php
/**
 * Installment Tracker helper.
 *
 * Self-healing (CREATE TABLE IF NOT EXISTS), scoped by company_id like
 * every other tenant-aware table. Each row is one installment payment
 * linked to a sold property in `properties`.
 *
 * Status ('paid', 'pending', 'overdue') is never trusted from storage for
 * display/filtering purposes — it is always recomputed live from
 * `paid_at` (whether the installment has been marked paid) and `due_date`
 * vs today's date, the same "compute live, don't trust a stale column"
 * approach used by reminder_helper.php. The `status` column is kept in
 * sync as a convenience for simple queries/sorting, but callers should
 * rely on compute_installment_status() for anything shown to the user.
 */

function ensure_installments_table($conn) {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $conn->query("CREATE TABLE IF NOT EXISTS `property_installments` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `company_id` int(11) DEFAULT NULL,
        `user_id` int(11) DEFAULT NULL,
        `property_id` int(11) NOT NULL,
        `due_date` date NOT NULL,
        `amount` decimal(14,2) NOT NULL,
        `status` varchar(20) NOT NULL DEFAULT 'pending',
        `paid_at` timestamp NULL DEFAULT NULL,
        `note` varchar(255) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `company_id` (`company_id`),
        KEY `property_id` (`property_id`),
        KEY `due_date` (`due_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}

/**
 * Computes the live status of an installment row ('paid', 'overdue', or
 * 'pending') from paid_at and due_date, ignoring whatever is stored in
 * the `status` column.
 */
function compute_installment_status($row) {
    if (!empty($row['paid_at'])) {
        return 'paid';
    }
    $today = date('Y-m-d');
    if (!empty($row['due_date']) && $row['due_date'] < $today) {
        return 'overdue';
    }
    return 'pending';
}

/**
 * Re-syncs the stored `status` column for every unpaid installment whose
 * due date has passed, so simple SQL queries/sorts stay consistent with
 * compute_installment_status(). Safe to call on every page load.
 */
function refresh_overdue_installments($conn, $company_id) {
    ensure_installments_table($conn);
    $stmt = $conn->prepare(
        "UPDATE property_installments
         SET status = 'overdue'
         WHERE company_id = ? AND paid_at IS NULL AND due_date < CURDATE() AND status != 'overdue'"
    );
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $stmt->close();

    $stmt2 = $conn->prepare(
        "UPDATE property_installments
         SET status = 'pending'
         WHERE company_id = ? AND paid_at IS NULL AND due_date >= CURDATE() AND status = 'overdue'"
    );
    $stmt2->bind_param("i", $company_id);
    $stmt2->execute();
    $stmt2->close();
}

/**
 * Fetches all installments for a company, joined with their property,
 * each row augmented with a live-computed `computed_status`.
 */
function get_installments($conn, $company_id) {
    ensure_installments_table($conn);
    refresh_overdue_installments($conn, $company_id);

    $stmt = $conn->prepare(
        "SELECT pi.*, p.project, p.developer, p.unit_type, p.status AS property_status
         FROM property_installments pi
         INNER JOIN properties p ON p.id = pi.property_id
         WHERE pi.company_id = ?
         ORDER BY pi.due_date ASC"
    );
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as &$row) {
        $row['computed_status'] = compute_installment_status($row);
    }
    unset($row);

    return $rows;
}

/**
 * Fetches only properties currently marked 'sold' (installments can only
 * be linked to a sold property).
 */
function get_sold_properties($conn, $company_id) {
    $stmt = $conn->prepare(
        "SELECT id, developer, project, unit_type, price FROM properties WHERE company_id = ? AND status = 'sold' ORDER BY project ASC"
    );
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function get_installment($conn, $installment_id, $company_id) {
    ensure_installments_table($conn);
    $stmt = $conn->prepare("SELECT * FROM property_installments WHERE id = ? AND company_id = ?");
    $stmt->bind_param("ii", $installment_id, $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->num_rows > 0 ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row;
}

function create_installment($conn, $company_id, $user_id, $data) {
    ensure_installments_table($conn);
    $stmt = $conn->prepare(
        "INSERT INTO property_installments (company_id, user_id, property_id, due_date, amount, status, note)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $status = 'pending';
    $stmt->bind_param(
        "iiisdss",
        $company_id,
        $user_id,
        $data['property_id'],
        $data['due_date'],
        $data['amount'],
        $status,
        $data['note']
    );
    $ok = $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();

    if ($ok) {
        refresh_overdue_installments($conn, $company_id);
    }

    return $ok ? $id : null;
}

function update_installment($conn, $installment_id, $company_id, $data) {
    ensure_installments_table($conn);
    $stmt = $conn->prepare(
        "UPDATE property_installments SET property_id = ?, due_date = ?, amount = ?, note = ?
         WHERE id = ? AND company_id = ?"
    );
    $stmt->bind_param(
        "isdsii",
        $data['property_id'],
        $data['due_date'],
        $data['amount'],
        $data['note'],
        $installment_id,
        $company_id
    );
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        refresh_overdue_installments($conn, $company_id);
    }

    return $ok;
}

/**
 * Marks an installment as paid (or un-paid) "now" and re-syncs status.
 */
function set_installment_paid($conn, $installment_id, $company_id, $is_paid) {
    ensure_installments_table($conn);
    if ($is_paid) {
        $stmt = $conn->prepare(
            "UPDATE property_installments SET paid_at = NOW(), status = 'paid' WHERE id = ? AND company_id = ?"
        );
    } else {
        $stmt = $conn->prepare(
            "UPDATE property_installments SET paid_at = NULL, status = 'pending' WHERE id = ? AND company_id = ?"
        );
    }
    $stmt->bind_param("ii", $installment_id, $company_id);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        refresh_overdue_installments($conn, $company_id);
    }

    return $ok;
}

function delete_installment($conn, $installment_id, $company_id) {
    ensure_installments_table($conn);
    $stmt = $conn->prepare("DELETE FROM property_installments WHERE id = ? AND company_id = ?");
    $stmt->bind_param("ii", $installment_id, $company_id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}
