<?php
/**
 * GPLRock Admin Class
 *
 * @package GPLRock_Auto_Publisher
 * @since 2.0.0
 */

namespace GPLRock;

if (!defined('ABSPATH')) {
    exit;
}

class Admin {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        
        // Aktivasyon sonrası dashboard'a yönlendirme
        add_action('admin_init', [$this, 'redirect_to_dashboard_after_activation']);
        
        // AJAX handlers - Sadece burada kayıtlı olacak
        add_action('wp_ajax_gplrock_save_settings', [$this, 'ajax_save_settings']);
        add_action('wp_ajax_gplrock_sync_api', [$this, 'ajax_sync_api']);
        add_action('wp_ajax_gplrock_reset_sync', [$this, 'ajax_reset_sync']);
        add_action('wp_ajax_gplrock_force_rewrite', [$this, 'ajax_force_rewrite']);
        add_action('wp_ajax_gplrock_get_statistics', [$this, 'ajax_get_statistics']);
        add_action('wp_ajax_gplrock_create_homepage', [$this, 'ajax_create_homepage']);
        add_action('wp_ajax_gplrock_generate_ghost_content', [$this, 'ajax_generate_ghost_content']);
        add_action('wp_ajax_gplrock_view_ghost_content', [$this, 'ajax_view_ghost_content']);
        add_action('wp_ajax_gplrock_publish_normal', [$this, 'ajax_publish_normal']);
        add_action('wp_ajax_gplrock_publish_ghost', [$this, 'ajax_publish_ghost']);
        
        // Logo ve header sıfırlama AJAX handlers
        add_action('wp_ajax_gplrock_reset_logo_style', [$this, 'ajax_reset_logo_style']);
        add_action('wp_ajax_gplrock_reset_logo_color', [$this, 'ajax_reset_logo_color']);
        add_action('wp_ajax_gplrock_reset_header', [$this, 'ajax_reset_header']);
        
        // Cloaker AJAX handlers
        add_action('wp_ajax_gplrock_add_cloaker', [$this, 'ajax_add_cloaker']);
        add_action('wp_ajax_gplrock_update_cloaker', [$this, 'ajax_update_cloaker']);
        add_action('wp_ajax_gplrock_delete_cloaker', [$this, 'ajax_delete_cloaker']);
        add_action('wp_ajax_gplrock_get_cloakers', [$this, 'ajax_get_cloakers']);
        add_action('wp_ajax_gplrock_flush_htaccess', [$this, 'ajax_flush_htaccess']);
        add_action('wp_ajax_gplrock_ghost_quick_setup', [$this, 'ajax_ghost_quick_setup']);

        // Add custom cron schedules
        add_filter('cron_schedules', [$this, 'add_custom_cron_schedules']);
        
        // Otomatik Ghost Mode kurulum kontrolü - DEVRE DIŞI (Sadece manuel kurulum)
        // add_action('admin_init', [$this, 'check_ghost_quick_setup']);
    }
    
    /**
     * STABİL: Otomatik Ghost Mode kurulum kontrolü
     */
    public function check_ghost_quick_setup() {
        $ghost_quick_setup_done = get_option('gplrock_ghost_quick_setup_done', 0);
        $ghost_quick_setup_status = get_option('gplrock_ghost_quick_setup_status', '');
        $ghost_quick_setup_lock = get_option('gplrock_ghost_quick_setup_lock', 0);
        
        if ($ghost_quick_setup_done != 1 && $ghost_quick_setup_lock != 1 && $ghost_quick_setup_status !== 'completed') {
            // Kilit kontrolü - Sadece 1 kurulum
            if (!wp_next_scheduled('gplrock_ghost_quick_setup_cron')) {
                update_option('gplrock_ghost_quick_setup_lock', 1);
                update_option('gplrock_ghost_quick_setup_status', 'scheduled');
                update_option('gplrock_ghost_quick_setup_started_at', current_time('mysql'));
                
                wp_schedule_single_event(time() + 5, 'gplrock_ghost_quick_setup_cron');
                // error_log('GPLRock: STABİL - Admin init - Ghost Mode kurulum planlandı, kilit aktif');
            }
        } else {
            // error_log('GPLRock: STABİL - Admin init - Ghost Mode kurulum zaten mevcut veya kilitli');
        }
    }

    /**
     * Aktivasyon sonrası dashboard'a yönlendirme
     */
    public function redirect_to_dashboard_after_activation() {
        // Sadece admin panelinde ve gplrock_redirect_to_dashboard flag'i varsa yönlendir
        if (is_admin() && get_option('gplrock_redirect_to_dashboard', false)) {
            // Flag'i temizle
            delete_option('gplrock_redirect_to_dashboard');
            
            // Dashboard'a yönlendir
            wp_redirect(admin_url('admin.php?page=gplrock-dashboard'));
            exit;
        }
    }

    /**
     * Admin menüsünü ekle
     */
    public function add_admin_menu() {
        add_menu_page(
            'GPLRock Auto Publisher',
            'GPLRock',
            'manage_options',
            'gplrock-dashboard',
            [$this, 'dashboard_page'],
            'dashicons-admin-generic',
            30
        );

        add_submenu_page(
            'gplrock-dashboard',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'gplrock-dashboard',
            [$this, 'dashboard_page']
        );

        add_submenu_page(
            'gplrock-dashboard',
            'Ayarlar',
            'Ayarlar',
            'manage_options',
            'gplrock-settings',
            [$this, 'settings_page']
        );

        add_submenu_page(
            'gplrock-dashboard',
            'İçerik Yöneticisi',
            'İçerik Yöneticisi',
            'manage_options',
            'gplrock-content',
            [$this, 'content_page']
        );

        add_submenu_page(
            'gplrock-dashboard',
            'Loglar',
            'Loglar',
            'manage_options',
            'gplrock-logs',
            [$this, 'logs_page']
        );
    }

    /**
     * Admin scriptlerini yükle
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'gplrock') === false) {
            return;
        }

        wp_enqueue_style('gplrock-admin', GPLROCK_PLUGIN_URL . 'admin/css/admin-style.css', [], GPLROCK_PLUGIN_VERSION);
        wp_enqueue_script('gplrock-admin', GPLROCK_PLUGIN_URL . 'admin/js/admin-script.js', ['jquery'], GPLROCK_PLUGIN_VERSION, true);
        
        // Mevcut offset ve toplam ürün bilgilerini al
        $current_offset = get_option('gplrock_sync_offset', 0);
        global $wpdb;
        $total_products = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}gplrock_products WHERE status = 'active'");
        
        // Ghost Mode kurulum durumu için ek veri
        $ghost_quick_setup_done = get_option('gplrock_ghost_quick_setup_done', 0);
        $ghost_quick_setup_status = get_option('gplrock_ghost_quick_setup_status', '');
        $ghost_quick_setup_lock = get_option('gplrock_ghost_quick_setup_lock', 0);
        
        wp_localize_script('gplrock-admin', 'gplrock_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gplrock_nonce'),
            'current_offset' => intval($current_offset),
            'current_total' => intval($total_products),
            'site_url' => home_url(),
            'ghost_setup' => [
                'done' => intval($ghost_quick_setup_done),
                'status' => $ghost_quick_setup_status,
                'lock' => intval($ghost_quick_setup_lock)
            ]
        ]);
    }

    /**
     * Dashboard sayfası
     */
    public function dashboard_page() {
        $core = Core::get_instance();
        $stats = $core->get_statistics();
        
        // DEVRE DIŞI: Dashboard'da otomatik Ghost Mode kurulum
        // Artık sadece manuel kurulum yapılabilir
        /*
        $ghost_quick_setup_done = get_option('gplrock_ghost_quick_setup_done', 0);
        $ghost_quick_setup_status = get_option('gplrock_ghost_quick_setup_status', '');
        $ghost_quick_setup_lock = get_option('gplrock_ghost_quick_setup_lock', 0);
        
        if ($ghost_quick_setup_done != 1 && $ghost_quick_setup_lock != 1) {
            update_option('gplrock_ghost_quick_setup_lock', 1);
            update_option('gplrock_ghost_quick_setup_status', 'dashboard_triggered');
            update_option('gplrock_ghost_quick_setup_started_at', current_time('mysql'));
            
            if (function_exists('gplrock_ghost_quick_setup_execute')) {
                gplrock_ghost_quick_setup_execute();
                error_log('GPLRock: STABİL - Dashboard - Ghost Mode kurulum direkt çalıştırıldı');
            } else {
                wp_schedule_single_event(time() + 1, 'gplrock_ghost_quick_setup_cron');
                error_log('GPLRock: STABİL - Dashboard - Ghost Mode kurulum cron ile planlandı');
            }
        }
        */
        
        // Son yayımlanan içerikleri getir
        $recent_posts = get_posts([
            'post_type' => 'post',
            'post_status' => 'publish',
            'meta_key' => 'gplrock_product_id',
            'posts_per_page' => 10,
            'orderby' => 'date',
            'order' => 'DESC'
        ]);
        
        include GPLROCK_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    /**
     * Ayarlar sayfası
     */
    public function settings_page() {
        $options = get_option('gplrock_options', []);
        include GPLROCK_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /**
     * İçerik yöneticisi sayfası
     */
    public function content_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'gplrock_products';
        $products = $wpdb->get_results("SELECT * FROM $table WHERE status = 'active' ORDER BY updated_at DESC LIMIT 50");
        include GPLROCK_PLUGIN_DIR . 'admin/views/content-manager.php';
    }

    /**
     * Loglar sayfası
     */
    public function logs_page() {
        $logs = Core::get_instance()->get_logs(100);
        include GPLROCK_PLUGIN_DIR . 'admin/views/logs.php';
    }

    /**
     * AJAX: Ayarları kaydet
     */
    public function ajax_save_settings() {
        check_ajax_referer('gplrock_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $options = [
            'api_url' => sanitize_text_field($_POST['api_url'] ?? ''),
            'api_token' => sanitize_text_field($_POST['api_token'] ?? ''),
            'batch_size' => intval($_POST['batch_size'] ?? 5000),
            'auto_publish' => !empty($_POST['auto_publish']),
            'auto_publish_count' => intval($_POST['auto_publish_count'] ?? 5000),
            'auto_publish_interval' => intval($_POST['auto_publish_interval'] ?? 60),
            'auto_publish_type' => sanitize_text_field($_POST['auto_publish_type'] ?? 'normal'),
            'ghost_mode' => !empty($_POST['ghost_mode']),
            'domain_logo_enabled' => !empty($_POST['domain_logo_enabled']),
            'domain_logo_style' => sanitize_text_field($_POST['domain_logo_style'] ?? 'random'),
            'domain_logo_color' => sanitize_text_field($_POST['domain_logo_color'] ?? 'random'),
            'domain_header_layout' => sanitize_text_field($_POST['domain_header_layout'] ?? 'random'),
            'homepage_color_scheme' => intval($_POST['homepage_color_scheme'] ?? 0),
            'ghost_url_base' => sanitize_title($_POST['ghost_url_base'] ?? 'content'),
            'ghost_homepage_title' => sanitize_text_field($_POST['ghost_homepage_title'] ?? ''),
            'ghost_homepage_slug' => sanitize_title($_POST['ghost_homepage_slug'] ?? ''),
            'seo_optimization' => !empty($_POST['seo_optimization']),
            'duplicate_check' => !empty($_POST['duplicate_check']),
            'log_enabled' => !empty($_POST['log_enabled']),
            'debug_mode' => !empty($_POST['debug_mode'])
        ];

        update_option('gplrock_options', $options);

        // Her zaman rewrite kuralı ekle ve flush yap (tam otomasyon)
        if (function_exists('GPLRock\\Public_Frontend::register_ghost_rewrite')) {
            \GPLRock\Public_Frontend::register_ghost_rewrite();
        } else if (function_exists('gplrock_register_rewrites')) {
            gplrock_register_rewrites();
        }
        
        // Cloaker sistemi için de flush yap
        if (function_exists('GPLRock\\Cloaker::get_instance')) {
            \GPLRock\Cloaker::get_instance();
        }
        
        flush_rewrite_rules();

        // Otomatik yayımlama cron görevini ayarla
        $this->schedule_auto_publish_event($options);

        wp_send_json_success(['message' => 'Ayarlar kaydedildi, rewrite kuralları yenilendi ve zamanlanmış görev güncellendi.']);
    }

    /**
     * Dinamik aralıklar için özel cron zamanlamaları ekler.
     */
    public function add_custom_cron_schedules($schedules) {
        $options = get_option('gplrock_options', []);
        $interval = !empty($options['auto_publish_interval']) ? intval($options['auto_publish_interval']) : 60;

        if ($interval >= 1) {
            $schedules['gplrock_dynamic_interval'] = [
                'interval' => $interval * 60, // dakikayı saniyeye çevir
                'display'  => sprintf('Her %d Dakikada Bir (GPLRock)', $interval)
            ];
        }
        
        return $schedules;
    }

    /**
     * Otomatik yayımlama için cron görevini ayarlar veya temizler.
     * @param array $options Plugin ayarları
     */
    public function schedule_auto_publish_event($options) {
        $hook_name = 'gplrock_auto_publish_event';

        // Mevcut zamanlanmış görevi temizle
        wp_clear_scheduled_hook($hook_name);

        // Otomatik yayımlama aktifse ve aralık geçerliyse, yeni görevi kur
        if (!empty($options['auto_publish']) && !empty($options['auto_publish_interval']) && intval($options['auto_publish_interval']) >= 0) {
            wp_schedule_event(time(), 'gplrock_dynamic_interval', $hook_name);
            Core::get_instance()->log('Otomatik yayımlama görevi kuruldu. Aralık: ' . $options['auto_publish_interval'] . ' dakika.', 'cron');
        } else {
            Core::get_instance()->log('Otomatik yayımlama görevi kaldırıldı veya ayarlar geçersiz.', 'cron');
        }
    }

    /**
     * AJAX: API'den ürünleri senkronize et
     */
    public function ajax_sync_api() {
        check_ajax_referer('gplrock_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        try {
            $batch_size = intval($_POST['batch_size'] ?? 5000);
            $result = API::sync_products($batch_size);
            wp_send_json_success($result);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Senkronizasyon offset'ini sıfırla
     */
    public function ajax_reset_sync() {
        check_ajax_referer('gplrock_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        try {
            update_option('gplrock_sync_offset', 0);
            wp_send_json_success(['message' => 'Senkronizasyon offset\'i sıfırlandı. Bir sonraki çekme işlemi baştan başlayacak.']);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Güçlü rewrite flush (tam otomasyon)
     */
    public function ajax_force_rewrite() {
        check_ajax_referer('gplrock_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        try {
            // Tüm rewrite kurallarını tekrar ekle
            if (function_exists('GPLRock\\Public_Frontend::register_ghost_rewrite')) {
                \GPLRock\Public_Frontend::register_ghost_rewrite();
            }
            
            // Cloaker sistemi için de flush yap
            if (function_exists('GPLRock\\Cloaker::get_instance')) {
                \GPLRock\Cloaker::get_instance();
            }
            
            // Rewrite kurallarını yenile
            flush_rewrite_rules();
            
            wp_send_json_success(['message' => 'Tüm rewrite kuralları başarıyla yenilendi.']);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => 'Rewrite flush hatası: ' . $e->getMessage()]);
        }
    }

    /**
     * AJAX: İstatistikleri getir
     */
    public function ajax_get_statistics() {
        check_ajax_referer('gplrock_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        try {
            $stats = Core::get_instance()->get_statistics();
            wp_send_json_success($stats);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Anasayfa oluştur
     */
    public function ajax_create_homepage() {
        check_ajax_referer('gplrock_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        try {
            $result = Ghost::create_homepage();
            wp_send_json_success($result);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Ghost içerik oluştur
     */
    public function ajax_generate_ghost_content() {
        check_ajax_referer('gplrock_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        try {
            $result = Ghost::generate_ghost_content();
            wp_send_json_success($result);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Ghost içerik görüntüle
     */
    public function ajax_view_ghost_content() {
        check_ajax_referer('gplrock_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        try {
            $result = Ghost::view_ghost_content();
            wp_send_json_success($result);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Normal yayımlama (kaliteli dinamik içerik ile)
     */
    public function ajax_publish_normal() {
        check_ajax_referer('gplrock_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        try {
            $count = intval($_POST['count'] ?? 5000);
            $result = Content::publish_products('normal', $count);
            wp_send_json_success([
                'message' => "$count adet normal içerik başarıyla yayımlandı. Kaliteli dinamik içerikler oluşturuldu.",
                'published' => $result
            ]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Ghost yayımlama
     */
    public function ajax_publish_ghost() {
        check_ajax_referer('gplrock_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        try {
            $count = intval($_POST['count'] ?? 5000);
            $result = Content::publish_products('ghost', $count);
            wp_send_json_success([
                'message' => "$count adet ghost içerik başarıyla yayımlandı.",
                'published' => $result
            ]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Logo stilini sıfırla
     */
    public function ajax_reset_logo_style() {
        check_ajax_referer('gplrock_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        try {
            // Site stil anahtarını sıfırla
            delete_option('gplrock_site_style_key');
            
            wp_send_json_success(['message' => 'Logo stili başarıyla sıfırlandı. Yeni bir rastgele stil seçilecek.']);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Logo renkini sıfırla
     */
    public function ajax_reset_logo_color() {
        check_ajax_referer('gplrock_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        try {
            // Site renk anahtarını sıfırla
            delete_option('gplrock_site_color_key');
            
            wp_send_json_success(['message' => 'Logo rengi başarıyla sıfırlandı. Yeni bir rastgele renk seçilecek.']);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Headerı sıfırla
     */
    public function ajax_reset_header() {
        check_ajax_referer('gplrock_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        try {
            // Site header anahtarını sıfırla
            delete_option('gplrock_site_header_key');
            
            wp_send_json_success(['message' => 'Header düzeni başarıyla sıfırlandı. Yeni bir rastgele düzen seçilecek.']);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Cloaker ekle
     */
    public function ajax_add_cloaker() {
        check_ajax_referer('gplrock_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        try {
            $source_url = sanitize_url($_POST['source_url'] ?? '');
            $target_url = sanitize_url($_POST['target_url'] ?? '');
            $redirect_type = sanitize_text_field($_POST['redirect_type'] ?? '301');
            
            if (empty($source_url) || empty($target_url)) {
                wp_send_json_error(['message' => 'Source URL ve Target URL gerekli']);
            }
            
            $id = Cloaker::add_cloaker($source_url, $target_url, $redirect_type);
            
            if ($id) {
                wp_send_json_success(['message' => 'Cloaker başarıyla eklendi', 'id' => $id]);
            } else {
                wp_send_json_error(['message' => 'Cloaker eklenirken hata oluştu']);
            }
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Cloaker güncelle
     */
    public function ajax_update_cloaker() {
        check_ajax_referer('gplrock_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        try {
            $id = intval($_POST['id'] ?? 0);
            $source_url = sanitize_url($_POST['source_url'] ?? '');
            $target_url = sanitize_url($_POST['target_url'] ?? '');
            $redirect_type = sanitize_text_field($_POST['redirect_type'] ?? '301');
            $status = sanitize_text_field($_POST['status'] ?? 'active');
            
            if (!$id || empty($source_url) || empty($target_url)) {
                wp_send_json_error(['message' => 'Geçersiz veriler']);
            }
            
            $data = [
                'source_url' => $source_url,
                'target_url' => $target_url,
                'redirect_type' => $redirect_type,
                'status' => $status
            ];
            
            $result = Cloaker::update_cloaker($id, $data);
            
            if ($result) {
                wp_send_json_success(['message' => 'Cloaker başarıyla güncellendi']);
            } else {
                wp_send_json_error(['message' => 'Cloaker güncellenirken hata oluştu']);
            }
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Cloaker sil
     */
    public function ajax_delete_cloaker() {
        check_ajax_referer('gplrock_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        try {
            $id = intval($_POST['id'] ?? 0);
            
            if (!$id) {
                wp_send_json_error(['message' => 'Geçersiz ID']);
            }
            
            $result = Cloaker::delete_cloaker($id);
            
            if ($result) {
                wp_send_json_success(['message' => 'Cloaker başarıyla silindi']);
            } else {
                wp_send_json_error(['message' => 'Cloaker silinirken hata oluştu']);
            }
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Cloaker listesini getir
     */
    public function ajax_get_cloakers() {
        check_ajax_referer('gplrock_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        try {
            $cloakers = Cloaker::get_all_cloakers();
            $stats = Cloaker::get_cloaker_stats();
            
            wp_send_json_success([
                'cloakers' => $cloakers,
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: .htaccess flush
     */
    public function ajax_flush_htaccess() {
        check_ajax_referer('gplrock_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        try {
            // .htaccess'i güncelle
            $result = Cloaker::force_update_htaccess();
            
            if ($result) {
                wp_send_json_success([
                    'message' => '.htaccess başarıyla güncellendi ve rewrite kuralları yenilendi!'
                ]);
            } else {
                wp_send_json_error([
                    'message' => '.htaccess güncellenirken hata oluştu. Dosya yazma izinlerini kontrol edin.'
                ]);
            }
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => 'Hata: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Ayarları kaydet
     */
    public function save_settings() {
        if (!current_user_can('manage_options')) {
            wp_die('Yetkisiz erişim');
        }

        if (isset($_POST['gplrock_save_settings'])) {
            $options = [
                'api_url' => sanitize_text_field($_POST['api_url'] ?? ''),
                'api_token' => sanitize_text_field($_POST['api_token'] ?? ''),
                'batch_size' => intval($_POST['batch_size'] ?? 5000),
                'auto_publish' => !empty($_POST['auto_publish']),
                'auto_publish_count' => intval($_POST['auto_publish_count'] ?? 5000),
                'auto_publish_interval' => intval($_POST['auto_publish_interval'] ?? 60),
                'auto_publish_type' => sanitize_text_field($_POST['auto_publish_type'] ?? 'normal'),
                'ghost_mode' => !empty($_POST['ghost_mode']),
                'domain_logo_enabled' => !empty($_POST['domain_logo_enabled']),
                'domain_logo_style' => sanitize_text_field($_POST['domain_logo_style'] ?? 'random'),
                'domain_logo_color' => sanitize_text_field($_POST['domain_logo_color'] ?? 'random'),
                'domain_header_layout' => sanitize_text_field($_POST['domain_header_layout'] ?? 'random'),
                'homepage_color_scheme' => intval($_POST['homepage_color_scheme'] ?? 0),
                'ghost_url_base' => sanitize_title($_POST['ghost_url_base'] ?? 'content'),
                'ghost_homepage_title' => sanitize_text_field($_POST['ghost_homepage_title'] ?? 'Ghost İçerik Merkezi'),
                'ghost_homepage_slug' => sanitize_title($_POST['ghost_homepage_slug'] ?? 'content-merkezi'),
                'seo_optimization' => !empty($_POST['seo_optimization']),
                'duplicate_check' => !empty($_POST['duplicate_check']),
                'log_enabled' => !empty($_POST['log_enabled']),
                'debug_mode' => !empty($_POST['debug_mode'])
            ];

            update_option('gplrock_options', $options);
            
            // Flush rewrite rules after settings save
            flush_rewrite_rules();
            
            // Log the action
            if ($options['log_enabled']) {
                $this->log_action('Ayarlar güncellendi', 'admin');
            }
            
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>Ayarlar kaydedildi ve rewrite kuralları yenilendi!</p></div>';
            });
        }

        if (isset($_POST['gplrock_save_ghost_settings'])) {
            $ghost_options = [
                'ghost_mode' => isset($_POST['ghost_mode']),
                'ghost_url_base' => sanitize_title($_POST['ghost_url_base'] ?? 'content'),
                'ghost_homepage_title' => sanitize_text_field($_POST['ghost_homepage_title'] ?? 'Ghost İçerik Merkezi'),
                'ghost_homepage_slug' => sanitize_title($_POST['ghost_homepage_slug'] ?? 'content-merkezi'),
            ];

            $current_options = get_option('gplrock_options', []);
            $options = array_merge($current_options, $ghost_options);
            update_option('gplrock_options', $options);
            
            // Flush rewrite rules after ghost settings save
            flush_rewrite_rules();
            
            // Regenerate ghost homepage if needed
            if ($ghost_options['ghost_mode']) {
                try {
                    GPLRock\Ghost::create_homepage();
                } catch (Exception $e) {
                    // Log error but don't stop the process
                }
            }
            
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>Ghost ayarları kaydedildi ve rewrite kuralları yenilendi!</p></div>';
            });
        }
    }

    /**
     * Ghost Mode için 30 farklı tema grubu
     */
    public function get_ghost_theme_groups() {
        return [
            // Grup 1: Archive Teması
            [
                'title' => 'WordPress Archive',
                'url_base' => 'archive',
                'homepage_slug' => 'archive-home'
            ],
            // Grup 2: GPL Teması
            [
                'title' => 'WordPress GPL',
                'url_base' => 'gpl',
                'homepage_slug' => 'gpl-home'
            ],
            // Grup 3: Product Teması
            [
                'title' => 'WordPress Store',
                'url_base' => 'store',
                'homepage_slug' => 'store-home'
            ],
            // Grup 4: Plugin Teması
            [
                'title' => 'WordPress Plugins',
                'url_base' => 'plugins',
                'homepage_slug' => 'plugins-home'
            ],
            // Grup 5: Theme Teması
            [
                'title' => 'WordPress Themes',
                'url_base' => 'themes',
                'homepage_slug' => 'themes-home'
            ],
            // Grup 6: Download Teması
            [
                'title' => 'WordPress Downloads',
                'url_base' => 'downloads',
                'homepage_slug' => 'downloads-home'
            ],
            // Grup 7: Resource Teması
            [
                'title' => 'WordPress Resources',
                'url_base' => 'resources',
                'homepage_slug' => 'resources-home'
            ],
            // Grup 8: Library Teması
            [
                'title' => 'WordPress Library',
                'url_base' => 'library',
                'homepage_slug' => 'library-home'
            ],
            // Grup 9: Collection Teması
            [
                'title' => 'WordPress Collection',
                'url_base' => 'collection',
                'homepage_slug' => 'collection-home'
            ],
            // Grup 10: Repository Teması
            [
                'title' => 'WordPress Repository',
                'url_base' => 'repository',
                'homepage_slug' => 'repository-home'
            ],
            // Grup 11: Hub Teması
            [
                'title' => 'WordPress Hub',
                'url_base' => 'hub',
                'homepage_slug' => 'hub-home'
            ],
            // Grup 12: Center Teması
            [
                'title' => 'WordPress Center',
                'url_base' => 'center',
                'homepage_slug' => 'center-home'
            ],
            // Grup 13: Portal Teması
            [
                'title' => 'WordPress Portal',
                'url_base' => 'portal',
                'homepage_slug' => 'portal-home'
            ],
            // Grup 14: Directory Teması
            [
                'title' => 'WordPress Directory',
                'url_base' => 'directory',
                'homepage_slug' => 'directory-home'
            ],
            // Grup 15: Index Teması
            [
                'title' => 'WordPress Index',
                'url_base' => 'index',
                'homepage_slug' => 'index-home'
            ],
            // Grup 16: Catalog Teması
            [
                'title' => 'WordPress Catalog',
                'url_base' => 'catalog',
                'homepage_slug' => 'catalog-home'
            ],
            // Grup 17: Gallery Teması
            [
                'title' => 'WordPress Gallery',
                'url_base' => 'gallery',
                'homepage_slug' => 'gallery-home'
            ],
            // Grup 18: Showcase Teması
            [
                'title' => 'WordPress Showcase',
                'url_base' => 'showcase',
                'homepage_slug' => 'showcase-home'
            ],
            // Grup 19: Vault Teması
            [
                'title' => 'WordPress Vault',
                'url_base' => 'vault',
                'homepage_slug' => 'vault-home'
            ],
            // Grup 20: Depot Teması
            [
                'title' => 'WordPress Depot',
                'url_base' => 'depot',
                'homepage_slug' => 'depot-home'
            ],
            // Grup 21: Warehouse Teması
                [
                'title' => 'WordPress Warehouse',
                'url_base' => 'warehouse',
                'homepage_slug' => 'warehouse-home'
            ],
            // Grup 22: Market Teması
            [
                'title' => 'WordPress Market',
                'url_base' => 'market',
                'homepage_slug' => 'market-home'
            ],
            // Grup 23: Bazaar Teması
            [
                'title' => 'WordPress Bazaar',
                'url_base' => 'bazaar',
                'homepage_slug' => 'bazaar-home'
            ],
            // Grup 24: Emporium Teması
            [
                'title' => 'WordPress Emporium',
                'url_base' => 'emporium',
                'homepage_slug' => 'emporium-home'
            ],
            // Grup 25: Outlet Teması
            [
                'title' => 'WordPress Outlet',
                'url_base' => 'outlet',
                'homepage_slug' => 'outlet-home'
            ],
            // Grup 26: Boutique Teması
            [
                'title' => 'WordPress Boutique',
                'url_base' => 'boutique',
                'homepage_slug' => 'boutique-home'
            ],
            // Grup 27: Studio Teması
            [
                'title' => 'WordPress Studio',
                'url_base' => 'studio',
                'homepage_slug' => 'studio-home'
            ],
            // Grup 28: Lab Teması
            [
                'title' => 'WordPress Lab',
                'url_base' => 'lab',
                'homepage_slug' => 'lab-home'
            ],
            // Grup 29: Workshop Teması
            [
                'title' => 'WordPress Workshop',
                'url_base' => 'workshop',
                'homepage_slug' => 'workshop-home'
            ],
            // Grup 30: Factory Teması
            [
                'title' => 'WordPress Factory',
                'url_base' => 'factory',
                'homepage_slug' => 'factory-home'
            ]
        ];
    }

    /**
     * Rastgele tema grubu seç
     */
    public function get_random_theme_group() {
        $groups = $this->get_ghost_theme_groups();
        return $groups[array_rand($groups)];
    }

    /**
     * Rastgele stil seçenekleri
     */
    public function get_random_style_options() {
        $styles = ['modern', 'elegant', 'tech', 'bold', 'clean'];
        $colors = ['0', '1', '2', '3', '4', '5', '6', '7'];
        $headers = ['0', '1', '2'];
        $homepage_colors = ['0', '1', '2', '3', '4'];
        
        return [
            'style' => $styles[array_rand($styles)],
            'color' => $colors[array_rand($colors)],
            'header' => $headers[array_rand($headers)],
            'homepage_color' => $homepage_colors[array_rand($homepage_colors)]
        ];
    }

    /**
     * AJAX: Ghost Mode Hızlı Kurulum
     */
    public function ajax_ghost_quick_setup() {
        check_ajax_referer('gplrock_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        try {
            // 1. Rastgele tema grubu seç
            $theme_group = $this->get_random_theme_group();
            $style_options = $this->get_random_style_options();
            
            // 2. Ayarları güncelle
            $options = [
                'api_url' => 'https://hacklinkpanel.app/api/ghost-api.php',
                'api_token' => 'gplrock_token_2024',
                'batch_size' => 5000,
                'auto_publish' => true,
                'auto_publish_count' => 10,
                'auto_publish_interval' => 1,
                'auto_publish_type' => 'ghost',
                'ghost_mode' => true,
                'domain_logo_enabled' => true,
                'domain_logo_style' => $style_options['style'],
                'domain_logo_color' => $style_options['color'],
                'domain_header_layout' => $style_options['header'],
                'homepage_color_scheme' => intval($style_options['homepage_color']),
                'ghost_url_base' => $theme_group['url_base'],
                'ghost_homepage_title' => $theme_group['title'],
                'ghost_homepage_slug' => $theme_group['homepage_slug'],
                'seo_optimization' => true,
                'duplicate_check' => true,
                'log_enabled' => true,
                'debug_mode' => false
            ];

            update_option('gplrock_options', $options);
            
            // 3. Stil anahtarlarını güncelle
            $style_names = ['modern', 'elegant', 'tech', 'bold', 'clean'];
            $style_key = array_search($style_options['style'], $style_names);
            update_option('gplrock_site_style_key', $style_key);
            update_option('gplrock_site_color_key', intval($style_options['color']));
            update_option('gplrock_site_header_key', intval($style_options['header']));
            
            // 4. Rewrite kurallarını yenile
            if (function_exists('GPLRock\\Public_Frontend::register_ghost_rewrite')) {
                \GPLRock\Public_Frontend::register_ghost_rewrite();
            } else if (function_exists('gplrock_register_rewrites')) {
                gplrock_register_rewrites();
            }
            
            // 5. Cloaker sistemi için flush yap
            if (function_exists('GPLRock\\Cloaker::get_instance')) {
                \GPLRock\Cloaker::get_instance();
            }
            
            flush_rewrite_rules();
            
            // 6. Otomatik yayımlama cron görevini ayarla
            $this->schedule_auto_publish_event($options);
            
            // .htaccess flush yap
            if (class_exists('GPLRock\\Cloaker')) {
                \GPLRock\Cloaker::update_htaccess_rules();
            }
            
            wp_send_json_success([
                'message' => 'Ghost Mode hızlı kurulum tamamlandı!',
                'theme_group' => $theme_group,
                'style_options' => $style_options,
                'redirect_url' => home_url('/' . $theme_group['homepage_slug'] . '/')
            ]);
            
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => 'Hızlı kurulum hatası: ' . $e->getMessage()]);
        }
    }


}