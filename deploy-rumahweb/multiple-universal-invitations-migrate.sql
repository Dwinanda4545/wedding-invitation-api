-- Multiple universal invitations (run if Git Deploy migrate fails)
-- Drops old single-link columns on events; create universal_invitations table.
-- WARNING: existing single universal links stop working; recreate in admin.

CREATE TABLE IF NOT EXISTS `universal_invitations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `event_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `greeting` VARCHAR(255) NULL,
  `token` VARCHAR(64) NOT NULL,
  `enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  UNIQUE KEY `universal_invitations_token_unique` (`token`),
  KEY `universal_invitations_event_id_sort_order_index` (`event_id`, `sort_order`),
  CONSTRAINT `universal_invitations_event_id_foreign`
    FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `events` DROP INDEX `events_universal_invitation_token_unique`;
ALTER TABLE `events`
  DROP COLUMN `universal_invitation_token`,
  DROP COLUMN `universal_invitation_enabled`,
  DROP COLUMN `universal_greeting`;
