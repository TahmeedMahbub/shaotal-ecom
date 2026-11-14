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

-- Section 9 of sql/proposed_changes.sql (central Australian locations). Test/CI DBs only.
CREATE TABLE IF NOT EXISTS au_states (
 id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
 code VARCHAR(3) NOT NULL UNIQUE,
 name VARCHAR(60) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS au_localities (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 state_id TINYINT UNSIGNED NOT NULL,
 source_pid VARCHAR(40) NOT NULL UNIQUE,            -- G-NAF locality_pid: makes re-imports idempotent
 name VARCHAR(120) NOT NULL,                        -- display case ("Mount Isa")
 class_code CHAR(1) NULL,                           -- G-NAF class: G=gazetted, U=unofficial, I=indigenous, T=topo, D=distributed, H=historic
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(state_id) REFERENCES au_states(id),
 INDEX(state_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS au_locality_postcodes (
 locality_id INT UNSIGNED NOT NULL,
 postcode CHAR(4) NOT NULL,                         -- text: keeps leading zeros (0800)
 PRIMARY KEY(locality_id, postcode),
 INDEX(postcode),
 FOREIGN KEY(locality_id) REFERENCES au_localities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS au_location_imports (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 source VARCHAR(160) NOT NULL, dataset_version VARCHAR(60) NOT NULL, release_date DATE NULL, file_sha256 CHAR(64) NOT NULL,
 states_count SMALLINT UNSIGNED NOT NULL, localities_count INT UNSIGNED NOT NULL, postcodes_count INT UNSIGNED NOT NULL,
 inserted INT UNSIGNED NOT NULL, updated INT UNSIGNED NOT NULL, skipped INT UNSIGNED NOT NULL, invalid INT UNSIGNED NOT NULL,
 imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
