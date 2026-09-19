-- Guest relation labels (run if Git Deploy migrate fails)
-- phpMyAdmin → production DB

CREATE TABLE IF NOT EXISTS `guest_relations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `event_id` BIGINT UNSIGNED NOT NULL,
  `label` VARCHAR(255) NOT NULL,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  UNIQUE KEY `guest_relations_event_id_label_unique` (`event_id`, `label`),
  KEY `guest_relations_event_id_sort_order_index` (`event_id`, `sort_order`),
  CONSTRAINT `guest_relations_event_id_foreign`
    FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `guests`
  ADD COLUMN `guest_relation_id` BIGINT UNSIGNED NULL AFTER `guest_type`,
  ADD CONSTRAINT `guests_guest_relation_id_foreign`
    FOREIGN KEY (`guest_relation_id`) REFERENCES `guest_relations` (`id`) ON DELETE SET NULL;
