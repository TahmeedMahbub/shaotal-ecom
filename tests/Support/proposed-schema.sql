-- TEST/CI DATABASES ONLY. Tables PROPOSED in sql/proposed_changes.sql (section 4) but not yet in sql/ecom.sql, so the
-- return-request code can be tested. Applied after ci-schema-drift.sql. Delete once sql/ecom.sql includes them.
CREATE TABLE IF NOT EXISTS order_return_requests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL UNIQUE,               -- one request per order: no duplicates
 customer_id BIGINT UNSIGNED NULL,
 status ENUM('requested','approved','rejected','completed') NOT NULL DEFAULT 'requested',
 reason VARCHAR(80) NOT NULL,
 details TEXT NULL,
 admin_note TEXT NULL,
 status_changed_at DATETIME NULL,
 changed_by_admin_id BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE,
 FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE SET NULL,
 FOREIGN KEY(changed_by_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL,
 INDEX(customer_id, created_at), INDEX(status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_return_request_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 return_request_id BIGINT UNSIGNED NOT NULL,
 old_status VARCHAR(40) NULL,
 new_status VARCHAR(40) NOT NULL,
 note VARCHAR(500) NULL,
 changed_by_admin_id BIGINT UNSIGNED NULL,               -- NULL = the customer
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(return_request_id) REFERENCES order_return_requests(id) ON DELETE CASCADE,
 FOREIGN KEY(changed_by_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL,
 INDEX(return_request_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
