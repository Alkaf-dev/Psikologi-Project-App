-- SQL untuk membuat tabel sheet_materials pada database server (cPanel / phpMyAdmin)
-- Database: bysherza_psikologiconnect_api

CREATE TABLE IF NOT EXISTS `sheet_materials` (
  `_row_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id` longtext DEFAULT NULL,
  `course_id` longtext DEFAULT NULL,
  `title` longtext DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `url` longtext DEFAULT NULL,
  `meeting_number` longtext DEFAULT NULL,
  `created_by` longtext DEFAULT NULL,
  `created_at` longtext DEFAULT NULL,
  `updated_at` longtext DEFAULT NULL,
  `deleted_at` longtext DEFAULT NULL,
  PRIMARY KEY (`_row_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
