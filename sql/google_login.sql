-- PROPOSED, NOT EXECUTED. Required before Google login can be enabled (run manually on the shared database).
-- Links a customer to an external identity by the provider's stable user id (Google "sub"),
-- so sign-in never depends on the email address alone.
CREATE TABLE customer_social_accounts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 customer_id BIGINT UNSIGNED NOT NULL,
 provider VARCHAR(30) NOT NULL,
 provider_user_id VARCHAR(190) NOT NULL,
 provider_email VARCHAR(190) NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_social_identity (provider, provider_user_id),
 UNIQUE KEY uq_social_customer_provider (customer_id, provider),
 FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
