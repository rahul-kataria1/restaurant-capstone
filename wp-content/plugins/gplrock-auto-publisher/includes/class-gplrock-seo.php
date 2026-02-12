<?php
/**
 * GPLRock SEO Class
 *
 * @package GPLRock_Auto_Publisher
 * @since 2.0.0
 */

namespace GPLRock;

if (!defined('ABSPATH')) {
    exit;
}

class SEO {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Tüm GPLRock içeriklerinde Yoast SEO meta alanlarını güncelle
     */
    public function optimize_all() {
        global $wpdb;
        $count = 0;
        $posts = $wpdb->get_results("SELECT p.ID, p.post_title, p.post_content FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE pm.meta_key = 'gplrock_product_id' AND p.post_status = 'publish'");
        foreach ($posts as $post) {
            $title = $post->post_title;
            $desc = wp_trim_words(strip_tags($post->post_content), 32, '...');
            $keywords = $this->generate_keywords($title, $desc);
            update_post_meta($post->ID, '_yoast_wpseo_title', $title);
            update_post_meta($post->ID, '_yoast_wpseo_metadesc', $desc);
            update_post_meta($post->ID, '_yoast_wpseo_focuskw', $keywords);
            $count++;
        }
        return $count;
    }

    /**
     * Basit anahtar kelime üretici
     */
    public function generate_keywords($title, $desc) {
        $words = array_unique(array_filter(explode(' ', strtolower($title . ' ' . $desc))));
        $words = array_filter($words, function($w) {
            return mb_strlen($w) > 3 && !in_array($w, ['ve', 'ile', 'için', 'gibi', 'olan', 'olarak', 'bir', 'the', 'and', 'with', 'from', 'that', 'this', 'have', 'has', 'are', 'was', 'but', 'not', 'you', 'all', 'can', 'will', 'your', 'our', 'www', 'com']);
        });
        return implode(', ', array_slice($words, 0, 8));
    }
} 