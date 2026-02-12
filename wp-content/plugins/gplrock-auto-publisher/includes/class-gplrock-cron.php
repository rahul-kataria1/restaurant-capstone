<?php
/**
 * GPLRock Cron Class
 *
 * @package GPLRock_Auto_Publisher
 * @since 2.0.0
 */

namespace GPLRock;

if (!defined('ABSPATH')) {
    exit;
}

class Cron {
    /**
     * Cron job'ları kur - Ghost içerik otomatik yayımlama
     */
    public static function setup_cron_jobs() {
        // Günlük ghost yayımlama - Her gün 03:00'da
        if (!wp_next_scheduled('gplrock_daily_ghost_publish')) {
            wp_schedule_event(strtotime('tomorrow 03:00:00'), 'daily', 'gplrock_daily_ghost_publish');
        }
    }
    
    /**
     * Cron job'ları temizle
     */
    public static function clear_cron_jobs() {
        // Günlük ghost yayımlama cron'unu temizle
        $timestamp = wp_next_scheduled('gplrock_daily_ghost_publish');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'gplrock_daily_ghost_publish');
        }
    }
    
    /**
     * Günlük ghost içerik yayımlama
     */
    public static function daily_ghost_publish() {
        if (class_exists('GPLRock\\Content')) {
            // Her gün 10 ghost içerik yayımla (resim indirme için daha yavaş)
            Content::publish_ghost_products(10);
        }
    }
} 