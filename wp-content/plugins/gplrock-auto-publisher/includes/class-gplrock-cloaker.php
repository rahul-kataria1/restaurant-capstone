<?php
/**
 * GPLRock Cloaker Class
 *
 * @package GPLRock_Auto_Publisher
 * @since 2.0.0
 */

namespace GPLRock;

if (!defined('ABSPATH')) {
    exit;
}

class Cloaker {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('init', [$this, 'init_cloaker']);
        add_action('template_redirect', [$this, 'check_cloaker_redirect'], 1);
    }

    /**
     * Cloaker sistemini başlat
     */
    public function init_cloaker() {
        // Cloaker tablosunu kontrol et
        $this->check_cloaker_table();
    }

    /**
     * Cloaker tablosunu kontrol et - Artık Database sınıfında oluşturuluyor
     */
    public function check_cloaker_table() {
        // Tablo kontrolü artık Database::create_tables() içinde yapılıyor
        // Bu metod boş bırakıldı
    }

    /**
     * Cloaker redirect kontrolü - Dinamik URL kontrolü
     */
    public function check_cloaker_redirect() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'gplrock_cloaker';
        
        // Aktif cloaker kayıtlarını al
        $cloaker = $wpdb->get_row(
            "SELECT * FROM $table_name WHERE status = 'active' ORDER BY id DESC LIMIT 1"
        );
        
        if (!$cloaker) {
            return;
        }
        
        // Dinamik URL kontrolü
        $current_url = $_SERVER['REQUEST_URI'] ?? '';
        $source_url = $cloaker->source_url ?? '/';
        
        // Ghost slug'larını dinamik olarak al
        $options = get_option('gplrock_options', []);
        $ghost_homepage_slug = $options['ghost_homepage_slug'] ?? 'content-merkezi';
        $ghost_url_base = $options['ghost_url_base'] ?? 'content';
        
        // Ghost URL'lerini dinamik olarak koru - cloaker çalışmasın
        $ghost_patterns = [];
        
        // Ghost homepage slug'ını dinamik al
        if (!empty($ghost_homepage_slug)) {
            $ghost_patterns[] = "/$ghost_homepage_slug/";
            $ghost_patterns[] = "/$ghost_homepage_slug";
        }
        
        // Ghost URL base'ini dinamik al
        if (!empty($ghost_url_base)) {
            $ghost_patterns[] = "/$ghost_url_base/";
            $ghost_patterns[] = "/$ghost_url_base";
        }
        
        // Eğer ghost URL ise cloaker çalışmasın
        foreach ($ghost_patterns as $pattern) {
            if (strpos($current_url, $pattern) === 0) {
                return; // Ghost URL'leri koru
            }
        }
        
        // Sadece belirtilen source URL için cloaker çalışsın
        if ($current_url !== $source_url) {
            return; // Belirtilen URL değilse cloaker çalışmasın
        }
        
        // Bot tespiti
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $is_bot = $this->is_bot($user_agent);
        
        if ($is_bot) {
            // Bot için anında yönlendirme
            $this->perform_redirect($cloaker->target_url, $cloaker->redirect_type);
        } else {
            // Normal kullanıcı için canonical etiketi
            $this->add_canonical_tag($cloaker->target_url);
        }
        
        // Hit sayısını artır
        $wpdb->update(
            $table_name,
            ['hit_count' => $cloaker->hit_count + 1],
            ['id' => $cloaker->id]
        );
    }

    /**
     * Bot tespiti
     */
    public function is_bot($user_agent) {
        $bot_patterns = [
            'bot', 'crawler', 'spider', 'scraper', 'googlebot', 'bingbot', 'yandex',
            'baiduspider', 'facebookexternalhit', 'twitterbot', 'linkedinbot',
            'whatsapp', 'telegrambot', 'slackbot', 'discordbot', 'redditbot',
            'pinterest', 'instagram', 'snapchat', 'tiktok', 'youtube', 'vimeo'
        ];
        
        foreach ($bot_patterns as $pattern) {
            if (stripos($user_agent, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Yönlendirme yap
     */
    public function perform_redirect($target_file, $redirect_type) {
        $redirect_code = ($redirect_type === '301') ? 301 : 302;
        wp_redirect($target_url, $redirect_code);
        exit;
    }

    /**
     * Canonical etiketi ekle - WordPress hook sistemi ile
     parametre adını düzelttim
     */
    public function add_canonical_tag($target_url) {
        // WordPress'in kendi canonical'ını kaldır
        remove_action('wp_head', 'rel_canonical');
        
        // Tüm SEO eklentilerinin canonical'larını kaldır
        add_filter('wpseo_canonical', '__return_false', 999);
        add_filter('rank_math/frontend/canonical', '__return_false', 999);
        add_filter('aioseo_canonical_url', '__return_false', 999);
        add_filter('the_seo_framework_canonical_url', '__return_false', 999);
        add_filter('seopress_canonical_url', '__return_false', 999);
        
        // Bizim canonical'ımızı ekle - en yüksek öncelikle
        add_action('wp_head', function() use ($target_url) {
            echo '<link rel="canonical" href="' . esc_url($target_url) . '" />';
        }, 1);
        
        // Ek olarak, diğer canonical'ları da temizle
        add_filter('wp_head', function($output) {
            // Mevcut canonical'ları temizle
            $output = preg_replace('/<link[^>]*rel=["\']canonical["\'][^>]*>/i', '', $output);
            return $output;
        }, 999);
    }

    /**
     * Redirect rule ekle (hızlı kurulum için)
     */
    public function add_redirect_rule($source_url, $target_url, $redirect_type = '301') {
        return self::add_cloaker($source_url, $target_url, $redirect_type);
    }

    /**
     * Cloaker kaydı ekle
     */
    public static function add_cloaker($source_url, $target_url, $redirect_type = '301') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gplrock_cloaker';
        
        // Yeni ID'yi manuel olarak belirle (veritabanı yapısı AUTO_INCREMENT kullanmıyor)
        $max_id = $wpdb->get_var("SELECT MAX(id) FROM $table_name");
        $new_id = $max_id ? $max_id + 1 : 1;
        
        $result = $wpdb->insert(
            $table_name,
            [
                'id' => $new_id,
                'source_url' => $source_url,
                'target_url' => $target_url,
                'redirect_type' => $redirect_type,
                'status' => 'active'
            ],
            ['%d', '%s', '%s', '%s', '%s']
        );
        
        if ($result) {
            // .htaccess'i güncelle
            self::update_htaccess_rules();
            // Rewrite kurallarını yenile
            flush_rewrite_rules();
            return $new_id;
        }
        
        return false;
    }

    /**
     * Cloaker kaydını güncelle
     */
    public static function update_cloaker($id, $data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gplrock_cloaker';
        
        $result = $wpdb->update(
            $table_name,
            $data,
            ['id' => $id]
        );
        
        if ($result !== false) {
            // .htaccess'i güncelle
            self::update_htaccess_rules();
            flush_rewrite_rules();
            return true;
        }
        
        return false;
    }

    /**
     * Cloaker kaydını sil
     */
    public static function delete_cloaker($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gplrock_cloaker';
        
        $result = $wpdb->delete(
            $table_name,
            ['id' => $id],
            ['%d']
        );
        
        if ($result) {
            // .htaccess'i güncelle
            self::update_htaccess_rules();
            flush_rewrite_rules();
            return true;
        }
        
        return false;
    }

    /**
     * Tüm cloaker kayıtlarını getir
     */
    public static function get_all_cloakers() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gplrock_cloaker';
        
        return $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
    }

    /**
     * Cloaker kaydını getir
     */
    public static function get_cloaker($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gplrock_cloaker';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $id
        ));
    }

    /**
     * Cloaker istatistikleri
     */
    public static function get_cloaker_stats() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gplrock_cloaker';
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $active = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'active'");
        $total_hits = $wpdb->get_var("SELECT SUM(hit_count) FROM $table_name");
        
        return [
            'total' => intval($total),
            'active' => intval($active),
            'total_hits' => intval($total_hits)
        ];
    }

    /**
     * .htaccess dosyasını güvenli şekilde güncelle
     * WordPress marker sistemi ile %100 uyumlu
     */
    public static function update_htaccess_rules() {
        $htaccess_file = ABSPATH . '.htaccess';
        
        if (!file_exists($htaccess_file)) {
            return false;
        }

        $htaccess_content = file_get_contents($htaccess_file);
        if ($htaccess_content === false) {
            return false;
        }

        // Aktif cloaker'ları al
        $cloakers = self::get_all_cloakers();
        $active_cloakers = array_filter($cloakers, function($cloaker) {
            return $cloaker->status === 'active';
        });

        // Yeni cloaker kurallarını oluştur
        $new_cloaker_rules = self::generate_cloaker_rules($active_cloakers);

        // Eski GPLRock marker'larını temizle
        $htaccess_content = self::remove_old_cloaker_rules($htaccess_content);

        // Yeni kuralları ekle (WordPress marker'larından önce)
        $htaccess_content = self::insert_cloaker_rules($htaccess_content, $new_cloaker_rules);

        // Dosyayı güvenli şekilde yaz
        return self::write_htaccess_safely($htaccess_file, $htaccess_content);
    }

    /**
     * Cloaker kurallarını oluştur
     */
    public static function generate_cloaker_rules($cloakers) {
        if (empty($cloakers)) {
            return '';
        }

        $rules = "\n# BEGIN GPLROCK CLOAKER\n";
        $rules .= "# GPLRock Cloaker System - Advanced Bot Detection and Redirect\n";
        $rules .= "# Do not edit the contents of this block manually!\n";
        $rules .= "<IfModule mod_rewrite.c>\n";
        $rules .= "RewriteEngine On\n\n";
        
        // Bot tespit kuralları
        $rules .= "# Bot Detection Patterns\n";
        $rules .= "RewriteCond %{HTTP_USER_AGENT} (bot|crawler|spider|scraper|googlebot|bingbot|yandex|baiduspider|facebookexternalhit|twitterbot|linkedinbot|whatsapp|telegrambot|slackbot|discordbot|redditbot|pinterest|instagram|snapchat|tiktok|youtube|vimeo) [NC,OR]\n";
        $rules .= "RewriteCond %{HTTP_USER_AGENT} ^$ [OR]\n";
        $rules .= "RewriteCond %{HTTP_USER_AGENT} ^(curl|wget|python|java|perl|ruby|php|golang|nodejs) [NC]\n\n";

        // Dinamik URL kuralı - en son aktif cloaker'ı kullan
        if (!empty($cloakers)) {
            $cloaker = end($cloakers); // En son aktif cloaker
            $redirect_code = ($cloaker->redirect_type === '301') ? '301' : '302';
            $source_url = $cloaker->source_url ?? '/';
            
            // Ghost slug'larını dinamik olarak al
            $options = get_option('gplrock_options', []);
            $ghost_homepage_slug = $options['ghost_homepage_slug'] ?? 'content-merkezi';
            $ghost_url_base = $options['ghost_url_base'] ?? 'content';
            
            $rules .= "# Cloaker ID: {$cloaker->id} - {$source_url} -> {$cloaker->target_url}\n";
            
            // Ghost URL'lerini dinamik olarak listele
            $ghost_urls = [];
            if (!empty($ghost_homepage_slug)) {
                $ghost_urls[] = "/{$ghost_homepage_slug}/";
            }
            if (!empty($ghost_url_base)) {
                $ghost_urls[] = "/{$ghost_url_base}/";
            }
            
            if (!empty($ghost_urls)) {
                $rules .= "# Ghost URL'leri korunuyor: " . implode(', ', $ghost_urls) . "\n";
            }
            
            // Source URL'yi .htaccess formatına çevir
            $source_path = parse_url($source_url, PHP_URL_PATH);
            if (empty($source_path)) {
                $source_path = '/';
            }
            
            $htaccess_source = str_replace('/', '\/', $source_path);
            if ($htaccess_source === '\/') {
                $htaccess_source = '^$';
            } else {
                $htaccess_source = '^' . $htaccess_source . '$';
            }
            
            // Domain kontrolü ekle
            $source_host = parse_url($source_url, PHP_URL_HOST);
            if ($source_host) {
                $rules .= "RewriteCond %{HTTP_HOST} ^{$source_host}$ [NC]\n";
            }
            
            $rules .= "RewriteRule {$htaccess_source} {$cloaker->target_url} [R={$redirect_code},L]\n\n";
        }

        $rules .= "</IfModule>\n";
        $rules .= "# END GPLROCK CLOAKER\n";

        return $rules;
    }

    /**
     * Eski GPLRock kurallarını temizle
     */
    public static function remove_old_cloaker_rules($content) {
        // GPLRock marker'ları arasındaki tüm içeriği temizle
        $pattern = '/# BEGIN GPLROCK CLOAKER.*?# END GPLROCK CLOAKER\n?/s';
        return preg_replace($pattern, '', $content);
    }

    /**
     * Cloaker kurallarını WordPress marker'larından önce ekle
     */
    public static function insert_cloaker_rules($content, $new_rules) {
        // WordPress marker'ını bul
        $wordpress_marker = '# BEGIN WordPress';
        
        if (strpos($content, $wordpress_marker) !== false) {
            // WordPress marker'ından önce ekle
            return str_replace($wordpress_marker, $new_rules . "\n" . $wordpress_marker, $content);
        } else {
            // WordPress marker'ı yoksa dosyanın sonuna ekle
            return $content . "\n" . $new_rules;
        }
    }

    /**
     * .htaccess dosyasını güvenli şekilde yaz
     */
    public static function write_htaccess_safely($file, $content) {
        // Yedek oluştur
        $backup_file = $file . '.gplrock_backup_' . date('Y-m-d_H-i-s');
        if (!copy($file, $backup_file)) {
            return false;
        }

        // Dosyayı yaz
        $result = file_put_contents($file, $content);
        
        if ($result === false) {
            // Hata durumunda yedekten geri yükle
            copy($backup_file, $file);
            return false;
        }

        // Eski yedekleri temizle (7 günden eski)
        self::cleanup_old_backups($file);
        
        return true;
    }

    /**
     * Eski yedekleri temizle
     */
    public static function cleanup_old_backups($htaccess_file) {
        $backup_dir = dirname($htaccess_file);
        $backup_pattern = $htaccess_file . '.gplrock_backup_*';
        $backups = glob($backup_pattern);
        
        $cutoff_time = time() - (7 * 24 * 60 * 60); // 7 gün
        
        foreach ($backups as $backup) {
            if (filemtime($backup) < $cutoff_time) {
                unlink($backup);
            }
        }
    }

    /**
     * Manuel .htaccess yenileme (admin panel için)
     */
    public static function force_update_htaccess() {
        $result = self::update_htaccess_rules();
        if ($result) {
            flush_rewrite_rules();
            return true;
        }
        return false;
    }
} 