<?php
/**
 * GPLRock Database Class
 *
 * @package GPLRock_Auto_Publisher
 * @since 2.0.0
 */

namespace GPLRock;

if (!defined('ABSPATH')) {
    exit;
}

class Database {
    /**
     * Tabloları oluştur - SQL dosyasıyla tam uyumlu
     */
    public static function create_tables() {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charset_collate = $wpdb->get_charset_collate();
        $cloaker_table = $wpdb->prefix . 'gplrock_cloaker';
        $ghost_content_table = $wpdb->prefix . 'gplrock_ghost_content';
        $logs_table = $wpdb->prefix . 'gplrock_logs';
        $products_table = $wpdb->prefix . 'gplrock_products';

        $sql = [];
        
        // gplrock_cloaker tablosu
        $sql[] = "CREATE TABLE `$cloaker_table` (
          `id` int(11) NOT NULL,
          `source_url` varchar(500) NOT NULL,
          `target_url` varchar(500) NOT NULL,
          `redirect_type` enum('301','302') DEFAULT '301',
          `status` enum('active','inactive') DEFAULT 'active',
          `hit_count` int(11) DEFAULT 0,
          `created_at` datetime DEFAULT current_timestamp(),
          `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;";

        // gplrock_ghost_content tablosu
        $sql[] = "CREATE TABLE `$ghost_content_table` (
          `id` int(11) NOT NULL,
          `product_id` varchar(255) NOT NULL,
          `url_slug` varchar(255) DEFAULT NULL,
          `title` varchar(500) DEFAULT NULL,
          `content` longtext DEFAULT NULL,
          `meta_description` text DEFAULT NULL,
          `meta_keywords` text DEFAULT NULL,
          `status` varchar(20) DEFAULT 'active',
          `created_at` datetime DEFAULT current_timestamp(),
          `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          `ghost_lokal_product_image` text DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;";

        // gplrock_logs tablosu  
        $sql[] = "CREATE TABLE `$logs_table` (
          `id` bigint(20) UNSIGNED NOT NULL,
          `timestamp` datetime NOT NULL,
          `type` varchar(20) NOT NULL,
          `message` text NOT NULL,
          `user_id` bigint(20) UNSIGNED DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;";

        // gplrock_products tablosu
        $sql[] = "CREATE TABLE `$products_table` (
          `id` bigint(20) UNSIGNED NOT NULL,
          `product_id` varchar(191) NOT NULL,
          `title` text NOT NULL,
          `category` varchar(100) NOT NULL,
          `description` longtext DEFAULT NULL,
          `features` longtext DEFAULT NULL,
          `version` varchar(50) DEFAULT NULL,
          `price` decimal(10,2) DEFAULT 0.00,
          `rating` decimal(3,2) DEFAULT 0.00,
          `downloads_count` int(11) DEFAULT 0,
          `image_url` text DEFAULT NULL,
          `download_url` text DEFAULT NULL,
          `demo_url` text DEFAULT NULL,
          `status` varchar(20) DEFAULT 'active',
          `created_at` datetime DEFAULT current_timestamp(),
          `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          `local_image_path` text DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;";

        // İndeksler - Ayrı komutlar halinde
        $sql[] = "ALTER TABLE `$cloaker_table`
          ADD PRIMARY KEY (`id`),
          ADD KEY `source_url` (`source_url`(191)),
          ADD KEY `status` (`status`);";

        $sql[] = "ALTER TABLE `$ghost_content_table`
          ADD PRIMARY KEY (`id`),
          ADD UNIQUE KEY `product_id` (`product_id`),
          ADD KEY `url_slug` (`url_slug`),
          ADD KEY `status` (`status`);";

        $sql[] = "ALTER TABLE `$logs_table`
          ADD PRIMARY KEY (`id`),
          ADD KEY `idx_timestamp` (`timestamp`),
          ADD KEY `idx_type` (`type`);";

        $sql[] = "ALTER TABLE `$products_table`
          ADD PRIMARY KEY (`id`),
          ADD UNIQUE KEY `product_id` (`product_id`),
          ADD KEY `category` (`category`),
          ADD KEY `status` (`status`),
          ADD KEY `updated_at` (`updated_at`);";

        // AUTO_INCREMENT değerleri
        $sql[] = "ALTER TABLE `$cloaker_table` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;";
        $sql[] = "ALTER TABLE `$ghost_content_table` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;";
        $sql[] = "ALTER TABLE `$logs_table` MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;";
        $sql[] = "ALTER TABLE `$products_table` MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;";

        foreach ($sql as $query) {
            dbDelta($query);
        }

        // Initial log data - dbDelta sonrası ayrı işlem
        $wpdb->query("INSERT INTO `$logs_table` (`id`, `timestamp`, `type`, `message`, `user_id`) VALUES
            (1, '2025-07-01 02:00:18', 'info', 'Plugin initialized successfully', 1) 
            ON DUPLICATE KEY UPDATE `id`=`id`");
        
        // Tablo oluşturma sonrası kontrol
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$products_table'");
        if (!$table_exists) {
            error_log("GPLRock: Veritabanı tablosu oluşturulamadı: $products_table");
        } else {
            error_log("GPLRock: Veritabanı tablosu başarıyla oluşturuldu: $products_table");
        }
    }

    /**
     * Tabloları sil
     */
    public static function drop_tables() {
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}gplrock_cloaker");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}gplrock_ghost_content");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}gplrock_affiliate_content");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}gplrock_logs");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}gplrock_products");
    }

    /**
     * Sürüm kontrolü (ileride kullanılabilir)
     */
    public static function check_version() {
        // Gerekirse tablo şeması güncelleme işlemleri
    }
} 