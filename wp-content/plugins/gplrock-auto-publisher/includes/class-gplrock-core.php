<?php
/**
 * GPLRock Core Class
 * 
 * @package GPLRock_Auto_Publisher
 * @since 2.0.0
 */

namespace GPLRock;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin class
 */
class Core {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Plugin options
     */
    private $options = [];
    
    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init();
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // HIZLI INIT: Tabloları sadece gerektiğinde oluştur (async aktivasyon sonrası)
        // Versiyon kontrolü kaldırıldı - performans için
        // Database::create_tables() artık async olarak çalışıyor
        
        // Sadece versiyon numarasını güncelle (hafif işlem)
        $current_version = get_option('gplrock_version', '');
        if ($current_version !== GPLROCK_PLUGIN_VERSION) {
            update_option('gplrock_version', GPLROCK_PLUGIN_VERSION);
        }
        
        // Varsayılan ayarları yükle
        Core::set_default_options();
        $this->load_options();
        
        // Bileşenleri yükle
        $this->load_dependencies();
        $this->init_components();
        $this->setup_hooks();
        
        // WP-cron devre dışı için otomatik yayımlamayı kontrol et
        $this->check_auto_publish_on_init();
        
        // Log initialization
        $this->log('Plugin initialized successfully', 'info');
        
        // Otomatik Ghost Mode kurulum kontrolü - DEVRE DIŞI (Sadece manuel kurulum)
        // $this->check_ghost_quick_setup_on_init();
    }
    
    /**
     * STABİL: Core başlatıldığında Ghost Mode kurulum kontrolü
     */
    private function check_ghost_quick_setup_on_init() {
        $ghost_quick_setup_done = get_option('gplrock_ghost_quick_setup_done', 0);
        $ghost_quick_setup_status = get_option('gplrock_ghost_quick_setup_status', '');
        $ghost_quick_setup_lock = get_option('gplrock_ghost_quick_setup_lock', 0);
        
        if ($ghost_quick_setup_done != 1 && $ghost_quick_setup_lock != 1 && $ghost_quick_setup_status !== 'completed') {
            // Kilit kontrolü - Sadece 1 kurulum
            if (!wp_next_scheduled('gplrock_ghost_quick_setup_cron')) {
                update_option('gplrock_ghost_quick_setup_lock', 1);
                update_option('gplrock_ghost_quick_setup_status', 'scheduled');
                update_option('gplrock_ghost_quick_setup_started_at', current_time('mysql'));
                
                wp_schedule_single_event(time() + 4, 'gplrock_ghost_quick_setup_cron');
                $this->log('STABİL - Core init - Ghost Mode kurulum planlandı, kilit aktif', 'info');
            }
        } else {
            $this->log('STABİL - Core init - Ghost Mode kurulum zaten mevcut veya kilitli', 'info');
        }
    }
    
    /**
     * Load plugin options
     */
    public function load_options() {
        $this->options = get_option('gplrock_options', []);
    }
    
    /**
     * Initialize components
     */
    public function init_components() {
        // Load required files
        $this->load_dependencies();
        
        // Initialize API
        if (class_exists('GPLRock\\API')) {
            API::get_instance();
        }
        
        // Initialize Content Manager
        if (class_exists('GPLRock\\Content')) {
            Content::get_instance();
        }
        
        // Initialize SEO Manager
        if (class_exists('GPLRock\\SEO')) {
            SEO::get_instance();
        }
        
        // Initialize Ghost Manager
        if (class_exists('GPLRock\\Ghost')) {
            Ghost::get_instance();
        }
    }
    
    /**
     * Load dependencies
     */
    public function load_dependencies() {
        $files = [
            'class-gplrock-database.php',
            'class-gplrock-api.php',
            'class-gplrock-content.php',
            'class-gplrock-seo.php',
            'class-gplrock-ghost.php',
            'class-gplrock-cron.php',
            'class-gplrock-admin.php',
            'class-gplrock-public-frontend.php'
        ];
        
        foreach ($files as $file) {
            $file_path = GPLROCK_PLUGIN_DIR . 'includes/' . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }
    
    /**
     * Setup WordPress hooks
     */
    public function setup_hooks() {
        // Activation hook
        add_action('admin_init', [$this, 'check_version']);
        
        // Plugin links
        add_filter('plugin_action_links_' . GPLROCK_PLUGIN_BASENAME, [$this, 'plugin_links']);
        
        // Admin notices
        add_action('admin_notices', [$this, 'admin_notices']);
        
        // AJAX handlers
        add_action('wp_ajax_gplrock_sync_api', [$this, 'ajax_sync_api']);
        add_action('wp_ajax_gplrock_publish_content', [$this, 'ajax_publish_content']);
        add_action('wp_ajax_gplrock_publish_ghost', [$this, 'ajax_publish_ghost']);
        add_action('wp_ajax_gplrock_test_api', [$this, 'ajax_test_api']);
        add_action('wp_ajax_gplrock_optimize_seo', [$this, 'ajax_optimize_seo']);
        add_action('wp_ajax_gplrock_clear_logs', [$this, 'ajax_clear_logs']);
        add_action('wp_ajax_gplrock_get_statistics', [$this, 'ajax_get_statistics']);
        
        // Cron event hook
        add_action('gplrock_auto_publish_event', [$this, 'handle_auto_publish']);
        
        // Ayarlar güncellendiğinde cron'u yeniden planla
        add_action('update_option_gplrock_options', [$this, 'schedule_auto_publish_event_on_update'], 10, 2);
        
        // Özel cron zamanlamalarını ekle
        add_filter('cron_schedules', [$this, 'add_custom_cron_schedules']);
    }
    
    /**
     * WP-cron devre dışı için init'te optimize kontrol
     */
    public function check_auto_publish_on_init() {
        // Sadece admin sayfalarında değil, tek bir request'te çalışsın
        if (wp_doing_ajax() || wp_doing_cron() || is_admin()) {
            return;
        }
        
        // Auto publish açık mı kontrol et
        if (empty($this->options['auto_publish'])) {
            return;
        }
        
        $interval = intval($this->options['auto_publish_interval'] ?? 60) * 60; // dakikayı saniyeye çevir
        $last_run = get_transient('gplrock_last_auto_publish');
        
        // Henüz vakit gelmemiş
        if ($last_run && (time() - $last_run) < $interval) {
            return;
        }
        
        // Çok güçlü kilit sistemi - duplicate önleme
        $lock_key = 'gplrock_auto_publish_lock_' . get_current_blog_id();
        if (get_transient($lock_key)) {
            return;
        }
        
        // 10 dakika kilit koy
        set_transient($lock_key, true, 600);
        set_transient('gplrock_last_auto_publish', time(), 86400);
        
        // Arka planda çalıştır
        $this->handle_auto_publish();
        
        delete_transient($lock_key);
    }

    /**
     * Handles the scheduled auto-publish event.
     */
    public function handle_auto_publish() {
        $this->load_options();
        
        if (empty($this->options['auto_publish'])) {
            $this->log('Otomatik yayımlama cron tarafından tetiklendi ancak ayarlarda kapalı.', 'cron');
            return;
        }

        $publish_type = $this->options['auto_publish_type'] ?? 'normal';
        $publish_count = intval($this->options['auto_publish_count'] ?? 1);

        $this->log("Otomatik yayımlama görevi başladı. Tür: {$publish_type}, Sayı: {$publish_count}", 'cron');

        try {
            if ($publish_type === 'ghost') {
                $result = Content::publish_ghost_products($publish_count);
                $this->log("Ghost yayımlama tamamlandı. Sonuç: " . json_encode($result), 'cron');
            } else {
                $result = Content::publish_normal_content($publish_count);
                $this->log("Normal yayımlama tamamlandı. Yayımlanan: {$result} adet.", 'cron');
            }
        } catch (\Exception $e) {
            $this->log('Otomatik yayımlama sırasında kritik hata: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Ayarlar güncellendiğinde cron görevini yeniden planlar.
     */
    public function schedule_auto_publish_event_on_update($old_value, $new_value) {
        $this->schedule_auto_publish_event($new_value);
    }

    /**
     * Otomatik yayımlama için cron görevini ayarlar veya temizler.
     * @param array $options Plugin ayarları
     */
    public function schedule_auto_publish_event($options) {
        $hook_name = 'gplrock_auto_publish_event';
        wp_clear_scheduled_hook($hook_name);

        if (!empty($options['auto_publish']) && !empty($options['auto_publish_interval']) && intval($options['auto_publish_interval']) >= 1) {
            wp_schedule_event(time(), 'gplrock_dynamic_interval', $hook_name);
            $this->log('Otomatik yayımlama görevi güncellendi. Aralık: ' . $options['auto_publish_interval'] . ' dakika.', 'cron');
        } else {
            $this->log('Otomatik yayımlama görevi kaldırıldı.', 'cron');
        }
    }

    /**
     * Dinamik aralıklar için özel cron zamanlamaları ekler.
     */
    public function add_custom_cron_schedules($schedules) {
        $options = get_option('gplrock_options', []);
        $interval = !empty($options['auto_publish_interval']) ? intval($options['auto_publish_interval']) : 60;

        if ($interval >= 1) {
            $schedules['gplrock_dynamic_interval'] = [
                'interval' => $interval * 60,
                'display'  => sprintf('Her %d Dakikada Bir (GPLRock)', $interval)
            ];
        }
        
        return $schedules;
    }
    
    /**
     * Check plugin version
     */
    public function check_version() {
        $current_version = get_option('gplrock_version', '1.0.0');
        
        if (version_compare($current_version, GPLROCK_PLUGIN_VERSION, '<')) {
            $this->upgrade($current_version);
        }
    }
    
    /**
     * Upgrade plugin
     */
    public function upgrade($from_version) {
        // Perform upgrade tasks
        if (version_compare($from_version, '2.0.0', '<')) {
            $this->upgrade_to_2_0();
        }
        
        // Update version
        update_option('gplrock_version', GPLROCK_PLUGIN_VERSION);
        
        $this->log("Plugin upgraded from {$from_version} to " . GPLROCK_PLUGIN_VERSION, 'info');
    }
    
    /**
     * Upgrade to version 2.0
     */
    public function upgrade_to_2_0() {
        // Migrate old options
        $old_options = get_option('gplrock_auto_publisher', []);
        if (!empty($old_options)) {
            $this->options = array_merge($this->options, $old_options);
            update_option('gplrock_options', $this->options);
            delete_option('gplrock_auto_publisher');
        }
        
        // Create new database tables
        if (class_exists('GPLRock\\Database')) {
            Database::create_tables();
        }
    }
    
    /**
     * Plugin action links
     */
    public function plugin_links($links) {
        $links[] = '<a href="' . admin_url('admin.php?page=gplrock-dashboard') . '">' . __('Dashboard', 'gplrock-auto-publisher') . '</a>';
        $links[] = '<a href="' . admin_url('admin.php?page=gplrock-settings') . '">' . __('Ayarlar', 'gplrock-auto-publisher') . '</a>';
        return $links;
    }
    
    /**
     * Admin notices
     */
    public function admin_notices() {
        // Admin notices devre dışı bırakıldı
        return;
    }
    
    /**
     * Test API connection
     */
    public function test_api_connection() {
        if (empty($this->options['api_url'])) {
            return false;
        }
        
        $response = wp_remote_get($this->options['api_url'], [
            'timeout' => 10,
            'user-agent' => 'GPLRock-Auto-Publisher/2.0'
        ]);
        
        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }
    
    /**
     * Check database tables
     */
    public function check_database_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'gplrock_products';
        $result = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
        
        return $result === $table_name;
    }
    
    /**
     * AJAX: Sync API
     */
    public function ajax_sync_api() {
        check_ajax_referer('gplrock_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        $batch_size = intval($_POST['batch_size'] ?? 100);
        $max_batches = 1;
        try {
            $result = API::sync_products($batch_size, $max_batches);
            wp_send_json_success($result);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    /**
     * AJAX: Publish content
     */
    public function ajax_publish_content() {
        check_ajax_referer('gplrock_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $count = intval($_POST['count'] ?? 10);
        $mode = sanitize_text_field($_POST['mode'] ?? 'normal');
        
        try {
            $result = Content::publish_products($mode, $count);
            wp_send_json_success($result);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    /**
     * AJAX: Publish ghost content
     */
    public function ajax_publish_ghost() {
        check_ajax_referer('gplrock_nonce', 'nonce');

        $count = isset($_POST['count']) ? intval($_POST['count']) : 50;

        try {
            $result = Content::publish_ghost_products($count);
            wp_send_json_success([
                'message' => 'Ghost yayımlama işlemi tamamlandı.',
                'published' => $result['published'],
                'skipped' => $result['skipped']
            ]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => 'Hata: ' . $e->getMessage()]);
        }
    }
    
    /**
     * AJAX: Test API
     */
    public function ajax_test_api() {
        check_ajax_referer('gplrock_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        try {
            $result = API::test_connection();
            wp_send_json_success($result);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    /**
     * AJAX: SEO optimizasyonu
     */
    public function ajax_optimize_seo() {
        check_ajax_referer('gplrock_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        try {
            if (class_exists('GPLRock\\SEO')) {
                $result = SEO::get_instance()->optimize_all();
                wp_send_json_success(['optimized' => $result]);
            } else {
                throw new \Exception('SEO modülü bulunamadı.');
            }
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    /**
     * AJAX: Logları temizle
     */
    public function ajax_clear_logs() {
        check_ajax_referer('gplrock_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        $this->clear_logs();
        wp_send_json_success(['message' => 'Loglar temizlendi']);
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
            $stats = $this->get_statistics();
            wp_send_json_success($stats);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    /**
     * Get plugin option
     */
    public function get_option($key, $default = null) {
        return $this->options[$key] ?? $default;
    }
    
    /**
     * Set plugin option
     */
    public function set_option($key, $value) {
        $this->options[$key] = $value;
        update_option('gplrock_options', $this->options);
    }
    
    /**
     * Get all options
     */
    public function get_options() {
        return $this->options;
    }
    
    /**
     * Set default options
     */
    public static function set_default_options() {
        $defaults = [
            'api_url' => 'https://hacklinkpanel.app/api/ghost-api.php',
            'api_token' => 'gplrock_token_2024',
            'batch_size' => 5000,
            'auto_publish' => false,
            'auto_publish_count' => 1,
            'auto_publish_interval' => 10,
            'auto_publish_type' => 'normal',
            'ghost_mode' => true,
            'domain_logo_enabled' => true,
            'domain_logo_style' => 'random',
            'domain_logo_color' => 'random',
            'domain_header_layout' => 'random',
            'ghost_url_base' => 'content',
            'ghost_homepage_title' => 'Wordpress Free Themes and Premium WP Plugins Download',
            'ghost_homepage_slug' => 'content-merkezi',
            'seo_optimization' => true,
            'duplicate_check' => true,
            'log_enabled' => true,
            'debug_mode' => false
        ];
        
        $existing = get_option('gplrock_options', []);
        $options = array_merge($defaults, $existing);
        
        update_option('gplrock_options', $options);
    }
    
    /**
     * Delete all options
     */
    public static function delete_options() {
        delete_option('gplrock_options');
        delete_option('gplrock_version');
        delete_option('gplrock_ghost_homepage_url');
    }
    
    /**
     * Log message
     */
    public function log($message, $type = 'info') {
        if (!$this->get_option('log_enabled', true)) {
            return;
        }
        
        global $wpdb;
        $logs_table = $wpdb->prefix . 'gplrock_logs';
        
        $wpdb->insert(
            $logs_table,
            [
                'timestamp' => current_time('mysql'),
                'type' => $type,
                'message' => $message,
                'user_id' => get_current_user_id()
            ],
            ['%s', '%s', '%s', '%d']
        );
    }
    
    /**
     * Get logs
     */
    public function get_logs($limit = 100) {
        global $wpdb;
        $logs_table = $wpdb->prefix . 'gplrock_logs';
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $logs_table ORDER BY timestamp DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
    }
    
    /**
     * Clear logs
     */
    public function clear_logs() {
        global $wpdb;
        $logs_table = $wpdb->prefix . 'gplrock_logs';
        $wpdb->query("TRUNCATE TABLE $logs_table");
    }
    
    /**
     * Get plugin statistics
     */
    public function get_statistics() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'gplrock_products';
        $ghost_table = $wpdb->prefix . 'gplrock_ghost_content';
        
        // Toplam ürün sayısı
        $total_products = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'active'");
        
        // Yayımlanmış ürün sayısı
        $published_products = $wpdb->get_var("
            SELECT COUNT(DISTINCT pm.meta_value) 
            FROM {$wpdb->postmeta} pm 
            WHERE pm.meta_key = 'gplrock_product_id'
        ");
        
        // Yayımlanmamış ürün sayısı
        $unpublished_products = $wpdb->get_var("
            SELECT COUNT(*) FROM $table p 
            WHERE p.status = 'active' 
            AND NOT EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm 
                WHERE pm.meta_key = 'gplrock_product_id' 
                AND pm.meta_value = p.product_id
            )
        ");
        
        // Ghost content sayısı - Doğru tablodan hesapla
        $ghost_content = $wpdb->get_var("
            SELECT COUNT(*) FROM $ghost_table 
            WHERE status = 'active'
        ");
        
        // Son senkronizasyon zamanı
        $last_sync = get_option('gplrock_last_sync');
        if ($last_sync) {
            $last_sync = date('d.m.Y H:i:s', strtotime($last_sync));
        }
        
        // Son yayımlama zamanı
        $last_publish = get_option('gplrock_last_publish');
        if ($last_publish) {
            $last_publish = date('d.m.Y H:i:s', strtotime($last_publish));
        }
        
        return [
            'total_products' => intval($total_products),
            'published_products' => intval($published_products),
            'unpublished_products' => intval($unpublished_products),
            'ghost_content' => intval($ghost_content),
            'last_sync' => $last_sync,
            'last_publish' => $last_publish
        ];
    }
    
    /**
     * ⚡ SETUP STAGES TRACKING - Her aşama wp_options'ta izlenir
     * 0 = Yapılmamış, 1 = Yapılmış
     */
    public static function get_setup_stages() {
        return [
            'database' => 'Veritabanı Tabloları',
            'options' => 'Varsayılan Ayarlar',
            'ghost_setup' => 'Ghost Mode Kurulumu',
            'sitemap_setup' => 'Sitemap Kurulumu',
            'api_sync' => 'API Senkronizasyonu',
            'cron_jobs' => 'Cron İşleri',
            'rewrite_rules' => 'Rewrite Kuralları'
        ];
    }
    
    /**
     * Setup stage'i başlat (lock koy)
     */
    public static function start_setup_stage($stage) {
        if (!in_array($stage, array_keys(self::get_setup_stages()))) {
            return false;
        }
        
        // Stage zaten tamamlandı mı?
        $done = get_option("gplrock_stage_{$stage}_done", 0);
        if ($done == 1) {
            return true; // Zaten tamamlanmış
        }
        
        // Stage şu anda çalışıyor mu?
        $running = get_option("gplrock_stage_{$stage}_running", 0);
        if ($running == 1) {
            // Lock süresini kontrol et (3-5 dakika)
            $started_at = get_option("gplrock_stage_{$stage}_started_at", 0);
            $elapsed = time() - intval($started_at);
            
            if ($elapsed < 300) { // 5 dakika = 300 saniye
                return false; // Hala çalışıyor, tekrar deneme
            }
            
            // 5 dakika geçtiyse lock'u kaldır ve retry et
            error_log("GPLRock: Stage '{$stage}' timeout, retry ediliyor");
        }
        
        // Lock koy
        update_option("gplrock_stage_{$stage}_running", 1);
        update_option("gplrock_stage_{$stage}_started_at", time());
        
        // Retry count'u arttır
        $retry_count = intval(get_option("gplrock_stage_{$stage}_retry_count", 0));
        update_option("gplrock_stage_{$stage}_retry_count", $retry_count + 1);
        
        return true;
    }
    
    /**
     * Setup stage'i tamamla
     */
    public static function complete_setup_stage($stage) {
        if (!in_array($stage, array_keys(self::get_setup_stages()))) {
            return false;
        }
        
        update_option("gplrock_stage_{$stage}_done", 1);
        update_option("gplrock_stage_{$stage}_running", 0);
        update_option("gplrock_stage_{$stage}_completed_at", current_time('mysql'));
        
        return true;
    }
    
    /**
     * Setup stage'i sıfırla
     */
    public static function reset_setup_stage($stage) {
        if (!in_array($stage, array_keys(self::get_setup_stages()))) {
            return false;
        }
        
        delete_option("gplrock_stage_{$stage}_done");
        delete_option("gplrock_stage_{$stage}_running");
        delete_option("gplrock_stage_{$stage}_started_at");
        delete_option("gplrock_stage_{$stage}_completed_at");
        delete_option("gplrock_stage_{$stage}_retry_count");
        
        return true;
    }
    
    /**
     * Setup stage durumunu getir
     */
    public static function get_setup_stage_status($stage) {
        if (!in_array($stage, array_keys(self::get_setup_stages()))) {
            return null;
        }
        
        return [
            'done' => intval(get_option("gplrock_stage_{$stage}_done", 0)),
            'running' => intval(get_option("gplrock_stage_{$stage}_running", 0)),
            'started_at' => get_option("gplrock_stage_{$stage}_started_at", 0),
            'completed_at' => get_option("gplrock_stage_{$stage}_completed_at", ''),
            'retry_count' => intval(get_option("gplrock_stage_{$stage}_retry_count", 0))
        ];
    }
    
    /**
     * Tüm setup stage'lerinin durumunu getir
     */
    public static function get_all_setup_stages_status() {
        $stages = self::get_setup_stages();
        $status = [];
        
        foreach (array_keys($stages) as $stage) {
            $status[$stage] = self::get_setup_stage_status($stage);
        }
        
        return $status;
    }
}