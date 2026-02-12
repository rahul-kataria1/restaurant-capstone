<?php
/**
 * GPLRock Sitemap Manager
 * Ghost içerikler için otomatik sitemap oluşturma
 * 
 * @package GPLRock
 * @version 1.0.0
 * @author Frida's Quantum Code
 */

namespace GPLRock;

if (!defined('ABSPATH')) {
    exit;
}

class Sitemap {
    
    private static $instance = null;
    
    /**
     * Singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor - Hook'ları kur
     */
    public function __construct() {
        // ⚡ CRITICAL: Query vars önce ekle (init'ten önce)
        add_filter('query_vars', [$this, 'add_query_vars'], 1);
        
        // ⚡ CRITICAL: Rewrite kuralları - ERKEN priority
        add_action('init', [$this, 'register_sitemap_rewrites'], 1);
        
        // ⚡ ULTRA CRITICAL: PARSE_REQUEST - WordPress'ten ÖNCE yakala
        add_action('parse_request', [$this, 'intercept_sitemap_request'], 1);
        
        // Fallback: template_redirect
        add_action('template_redirect', [$this, 'handle_sitemap_request'], 1);
        
        // WordPress native sitemap entegrasyonu
        add_filter('wp_sitemaps_post_types', [$this, 'add_ghost_to_wp_sitemap']);
        add_filter('wp_sitemaps_posts_query_args', [$this, 'modify_sitemap_query'], 10, 2);
        
        // ⚡ Robots.txt'e sitemap ekle - ÇİFTE GÜVENLİK
        add_filter('robots_txt', [$this, 'add_sitemap_to_robots'], 999, 2);
        add_action('do_robots', [$this, 'force_robots_sitemap'], 1);
        
        // ⚡ Physical robots.txt oluştur
        add_action('init', [$this, 'maybe_create_physical_robots'], 999);
    }
    
    /**
     * ⚡ INTERCEPT: WordPress parse_request'te yakala (en erken)
     */
    public function intercept_sitemap_request($wp) {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $sitemap_slug = $this->get_unique_sitemap_slug();
        
        // Sitemap URL'i mi? (hem numaralı hem numarasız)
        if (preg_match('#/' . preg_quote($sitemap_slug, '#') . '(-[0-9]+)?\.xml($|\?)#', $request_uri)) {
            // WordPress routing'i durdur
            $this->handle_sitemap_request();
            // Eğer handle_sitemap_request exit etmediyse zorla et
            exit;
        }
    }
    
    /**
     * ⚡ Query vars ekle - GLOBAL
     */
    public function add_query_vars($vars) {
        $vars[] = 'ghost_sitemap';
        return $vars;
    }
    
    /**
     * ⚡ Robots.txt'e sitemap ekle - GÜÇLÜ + Virtual robots.txt
     */
    public function add_sitemap_to_robots($output, $public) {
        if ($public) {
            $sitemap_url = self::get_sitemap_url();
            
            // ⚡ Eğer sitemap zaten varsa ekleme
            if (strpos($output, $sitemap_url) !== false) {
                return $output;
            }
            
            // ⚡ Sitemap'i ekle (en sona)
            $output = rtrim($output) . "\n\n";
            $output .= "Sitemap: " . $sitemap_url . "\n";
        }
        return $output;
    }
    
    /**
     * ⚡ FORCE: do_robots hook'unda sitemap ekle
     */
    public function force_robots_sitemap() {
        $sitemap_url = self::get_sitemap_url();
        echo "Sitemap: " . esc_url($sitemap_url) . "\n";
    }
    
    /**
     * ⚡ Physical robots.txt oluştur/güncelle
     */
    public function maybe_create_physical_robots() {
        // ⚡ Sadece admin ve tek seferlik
        if (!is_admin() || get_transient('gplrock_robots_updated')) {
            return;
        }
        
        $sitemap_url = self::get_sitemap_url();
        $robots_file = ABSPATH . 'robots.txt';
        
        // Robots.txt varsa kontrol et
        if (file_exists($robots_file)) {
            $content = file_get_contents($robots_file);
            
            // Sitemap zaten varsa skip
            if (strpos($content, $sitemap_url) !== false) {
                set_transient('gplrock_robots_updated', 1, DAY_IN_SECONDS);
                return;
            }
            
            // Sitemap ekle
            $content = rtrim($content) . "\n\n";
            $content .= "Sitemap: " . $sitemap_url . "\n";
            
            @file_put_contents($robots_file, $content);
        } else {
            // Robots.txt yoksa oluştur
            $content = "User-agent: *\n";
            $content .= "Disallow: /wp-admin/\n";
            $content .= "Allow: /wp-admin/admin-ajax.php\n\n";
            $content .= "Sitemap: " . $sitemap_url . "\n";
            
            @file_put_contents($robots_file, $content);
        }
        
        // 1 gün boyunca tekrar kontrol etme
        set_transient('gplrock_robots_updated', 1, DAY_IN_SECONDS);
    }
    
    /**
     * Sitemap rewrite kurallarını kaydet
     * ⚡ DOMAIN-BASED UNIQUE NAME + GÜÇLÜ REWRITE
     */
    public function register_sitemap_rewrites() {
        // ⚡ Domain bazlı özgün sitemap ismi
        $sitemap_slug = $this->get_unique_sitemap_slug();
        
        // ⚡ CRITICAL: Rewrite kuralları - TOP priority
        // Ana sitemap (index) → ghost_sitemap=index
        add_rewrite_rule('^' . preg_quote($sitemap_slug, '/') . '\.xml$', 'index.php?ghost_sitemap=index', 'top');
        // Sayfalı sitemap → ghost_sitemap=1, ghost_sitemap=2, etc
        add_rewrite_rule('^' . preg_quote($sitemap_slug, '/') . '-([0-9]+)\.xml$', 'index.php?ghost_sitemap=$matches[1]', 'top');
        
        // ⚡ DEBUG: Log sitemap slug
        error_log("GPLRock Sitemap: Registered with slug = " . $sitemap_slug);
    }
    
    /**
     * ⚡ Domain bazlı özgün sitemap slug oluştur
     */
    public function get_unique_sitemap_slug() {
        static $cached_slug = null;
        
        if ($cached_slug !== null) {
            return $cached_slug;
        }
        
        $slug = get_option('gplrock_sitemap_slug');
        
        if (!$slug) {
            // Domain hash'e göre özgün isim
            $domain = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field($_SERVER['HTTP_HOST']) : get_site_url();
            $domain = str_replace(['www.', 'http://', 'https://'], '', $domain);
            $hash = crc32($domain);
            
            // Özgün kelimeler
            $prefixes = ['content', 'archive', 'index', 'library', 'catalog', 'repository'];
            $suffixes = ['map', 'index', 'list', 'feed', 'data', 'registry'];
            
            $prefix = $prefixes[abs($hash) % count($prefixes)];
            $suffix = $suffixes[abs($hash >> 8) % count($suffixes)];
            
            $slug = $prefix . '-' . $suffix;
            
            // Kaydet
            update_option('gplrock_sitemap_slug', $slug);
        }
        
        $cached_slug = $slug;
        return $slug;
    }
    
    /**
     * Sitemap isteğini yakala ve işle
     * ⚡ ULTRA POWERFUL: Direct URL match + Query var fallback
     */
    public function handle_sitemap_request() {
        // ⚡ METHOD 1: Direct URL kontrolü (en güvenilir)
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $sitemap_slug = $this->get_unique_sitemap_slug();
        
        $is_sitemap = false;
        $page_num = 1;
        
        // Main sitemap INDEX: /slug.xml (numarasız)
        if (preg_match('#/' . preg_quote($sitemap_slug, '#') . '\.xml$#', $request_uri)) {
            $is_sitemap = true;
            $page_num = 0; // 0 = INDEX
        }
        // Paginated: /slug-1.xml, /slug-2.xml, etc (numaralı = içerik listesi)
        elseif (preg_match('#/' . preg_quote($sitemap_slug, '#') . '-([0-9]+)\.xml$#', $request_uri, $matches)) {
            $is_sitemap = true;
            $page_num = intval($matches[1]); // 1, 2, 3... = sayfa numarası
        }
        
        // ⚡ METHOD 2: Query var fallback (rewrite çalışırsa)
        if (!$is_sitemap) {
            $query_var = get_query_var('ghost_sitemap', false);
            if ($query_var !== false && $query_var !== '') {
                $is_sitemap = true;
                // 'index' veya 'main' → page_num = 0 (index)
                // Sayı → page_num = sayı (içerik listesi)
                if ($query_var === 'index' || $query_var === 'main') {
                    $page_num = 0;
                } else {
                    $page_num = intval($query_var);
                }
            }
        }
        
        // Sitemap değilse çık
        if (!$is_sitemap) {
            return;
        }
        
        // ⚡ PERFORMANCE: Static cache (24 saat)
        $cache_key = 'gplrock_ghost_sitemap_' . $page_num;
        $cached = get_transient($cache_key);
        
        if ($cached && !isset($_GET['nocache'])) {
            $this->output_sitemap($cached);
            exit;
        }
        
        // ⚡ Sitemap oluştur
        // page_num = 0 → INDEX (sayfa listesi)
        // page_num > 0 → İçerik listesi (1, 2, 3...)
        if ($page_num === 0) {
            $xml = $this->generate_ghost_sitemap_index();
        } else {
            $xml = $this->generate_ghost_sitemap_page($page_num);
        }
        
        // ⚡ Cache'e kaydet (24 saat - uzun cache)
        set_transient($cache_key, $xml, DAY_IN_SECONDS);
        
        // XML çıktı
        $this->output_sitemap($xml);
        exit;
    }
    
    /**
     * ⚡ OPTIMIZED: Sitemap çıktısı - Temiz XML output
     */
    private function output_sitemap($xml) {
        // ⚡ CRITICAL: Tüm output buffer'ları temizle
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // ⚡ Clean headers start
        if (!headers_sent()) {
            header('Content-Type: application/xml; charset=UTF-8');
            header('X-Robots-Tag: noindex, follow');
            
            // ⚡ Gzip compression (bandwidth tasarrufu)
            if (extension_loaded('zlib') && !ini_get('zlib.output_compression')) {
                if (strpos($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip') !== false) {
                    header('Content-Encoding: gzip');
                    $xml = gzencode($xml, 9);
                }
            }
            
            // Cache headers (1 gün)
            header('Cache-Control: public, max-age=86400');
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
            header('Content-Length: ' . strlen($xml));
        }
        
        // ⚡ Temiz XML output
        echo $xml;
        
        // ⚡ WordPress'i tamamen durdur
        die();
    }
    
    /**
     * Ghost sitemap index oluştur
     * ⚡ OPTIMIZED: Cached count + Faster query
     */
    private function generate_ghost_sitemap_index() {
        global $wpdb;
        
        $options = get_option('gplrock_options', []);
        $ghost_base = $options['ghost_url_base'] ?? 'content';
        
        // ⚡ PERFORMANCE: Cached total count (1 saat cache)
        $cache_key = 'gplrock_ghost_total_count';
        $total = get_transient($cache_key);
        
        if ($total === false) {
            // ⚡ CRITICAL: Ghost içerikler AYRI tabloda!
            $ghost_table = $wpdb->prefix . 'gplrock_ghost_content';
            $posts_table = $wpdb->prefix . 'posts';
            
            $total = 0;
            
            // METHOD 1: Ghost content tablosundan say (DOĞRU KOŞUL!)
            if ($wpdb->get_var("SHOW TABLES LIKE '$ghost_table'") === $ghost_table) {
                $ghost_count = $wpdb->get_var(
                    "SELECT COUNT(*) FROM {$ghost_table} 
                    WHERE status = 'active'"
                );
                $total += intval($ghost_count);
            }
            
            // METHOD 2: wp_posts'tan say (eski içerikler)
            $posts_count = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$posts_table} 
                WHERE post_status = 'publish' 
                AND post_type = 'post' 
                AND post_content LIKE '%<!-- ghost-content -->%'"
            );
            $total += intval($posts_count);
            
            // ⚡ 1 saat cache
            set_transient($cache_key, $total, HOUR_IN_SECONDS);
        }
        
        // Sayfalama (2000'er içerik - daha az dosya)
        $per_page = 2000;
        $pages = $total > 0 ? ceil($total / $per_page) : 1;
        
        // ⚡ Özgün sitemap slug al
        $sitemap_slug = $this->get_unique_sitemap_slug();
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        for ($i = 1; $i <= $pages; $i++) {
            $xml .= '  <sitemap>' . "\n";
            $xml .= '    <loc>' . esc_url(home_url('/' . $sitemap_slug . '-' . $i . '.xml')) . '</loc>' . "\n";
            $xml .= '    <lastmod>' . current_time('c') . '</lastmod>' . "\n";
            $xml .= '  </sitemap>' . "\n";
        }
        
        $xml .= '</sitemapindex>';
        
        return $xml;
    }
    
    /**
     * Ghost sitemap sayfa oluştur
     * ⚡ ULTRA-OPTIMIZED: Minimum query + Batch processing
     */
    private function generate_ghost_sitemap_page($page = 1) {
        global $wpdb;
        
        $options = get_option('gplrock_options', []);
        $ghost_base = $options['ghost_url_base'] ?? 'content';
        
        $per_page = 2000; // ⚡ Daha fazla içerik = daha az dosya
        $offset = ($page - 1) * $per_page;
        
        // ⚡ CRITICAL: Ghost içerikler AYRI tabloda!
        $ghost_table = $wpdb->prefix . 'gplrock_ghost_content';
        $posts_table = $wpdb->prefix . 'posts';
        
        // ⚡ İki tablodan da çek (hem ghost table hem wp_posts)
        $posts = [];
        
        // METHOD 1: Ghost content tablosundan (DOĞRU ALAN ADLARI!)
        if ($wpdb->get_var("SHOW TABLES LIKE '$ghost_table'") === $ghost_table) {
            $ghost_posts = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT url_slug as post_name, updated_at as post_modified 
                    FROM {$ghost_table} 
                    WHERE status = 'active'
                    ORDER BY id DESC 
                    LIMIT %d OFFSET %d",
                    $per_page,
                    $offset
                ),
                ARRAY_A
            );
            
            if ($ghost_posts) {
                $posts = array_merge($posts, $ghost_posts);
            }
        }
        
        // METHOD 2: wp_posts'tan (fallback - eski içerikler)
        if (empty($posts)) {
            $posts = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT post_name, post_modified 
                    FROM {$posts_table} 
                    WHERE post_status = 'publish' 
                    AND post_type = 'post' 
                    AND post_content LIKE '%<!-- ghost-content -->%'
                    ORDER BY ID DESC 
                    LIMIT %d OFFSET %d",
                    $per_page,
                    $offset
                ),
                ARRAY_A
            );
        }
        
        // ⚡ XML buffer - Tek seferde oluştur
        $urls = [];
        
        // Ghost homepage (sadece ilk sayfada)
        if ($page == 1) {
            $ghost_homepage_slug = $options['ghost_homepage_slug'] ?? 'content-home';
            $homepage_url = home_url('/' . $ghost_homepage_slug . '/');
            
            $urls[] = '  <url>
    <loc>' . esc_url($homepage_url) . '</loc>
    <lastmod>' . current_time('c') . '</lastmod>
    <changefreq>daily</changefreq>
    <priority>1.0</priority>
  </url>';
        }
        
        // ⚡ Ghost içerikleri - Batch processing
        if (!empty($posts)) {
            $base_url = home_url('/' . $ghost_base . '/');
            foreach ($posts as $post) {
                $urls[] = '  <url>
    <loc>' . esc_url($base_url . $post['post_name'] . '/') . '</loc>
    <lastmod>' . mysql2date('c', $post['post_modified'], false) . '</lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.8</priority>
  </url>';
            }
        }
        
        // ⚡ Tek seferde XML oluştur (implode = hızlı)
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $xml .= implode("\n", $urls);
        $xml .= "\n" . '</urlset>';
        
        return $xml;
    }
    
    /**
     * WordPress native sitemap'e ghost ekle
     */
    public function add_ghost_to_wp_sitemap($post_types) {
        // Ghost post type eklenmesi (opsiyonel)
        return $post_types;
    }
    
    /**
     * Sitemap sorgusu değiştir
     */
    public function modify_sitemap_query($args, $post_type) {
        // Ghost içerikleri dahil et
        return $args;
    }
    
    /**
     * Sitemap cache temizle
     * ⚡ OPTIMIZED: Batch delete + Count cache clear + Robots update
     */
    public static function clear_sitemap_cache() {
        global $wpdb;
        
        // ⚡ BATCH DELETE: Tek query'de tüm cache temizleme
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_gplrock_ghost_%' 
            OR option_name LIKE '_transient_timeout_gplrock_ghost_%'"
        );
        
        // ⚡ WordPress object cache temizle
        wp_cache_delete('gplrock_ghost_total_count', 'transient');
        
        // ⚡ Robots.txt'i yeniden güncelle
        delete_transient('gplrock_robots_updated');
        
        return true;
    }
    
    /**
     * Sitemap istatistikleri
     */
    public static function get_sitemap_stats() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'posts';
        
        $stats = [
            'total_ghost_posts' => 0,
            'total_pages' => 0,
            'last_updated' => current_time('mysql'),
            'sitemap_url' => self::get_sitemap_url() // ⚡ Doğal URL
        ];
        
        // ⚡ CRITICAL: Ghost içerikler AYRI tabloda!
        $ghost_table = $wpdb->prefix . 'gplrock_ghost_content';
        $posts_table = $wpdb->prefix . 'posts';
        
        $total = 0;
        
        // Ghost content tablosundan say (DOĞRU KOŞUL!)
        if ($wpdb->get_var("SHOW TABLES LIKE '$ghost_table'") === $ghost_table) {
            $ghost_count = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$ghost_table} WHERE status = 'active'"
            );
            $total += intval($ghost_count);
        }
        
        // wp_posts'tan say (eski içerikler)
        $posts_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$posts_table} 
            WHERE post_status = 'publish' 
            AND post_type = 'post' 
            AND post_content LIKE %s",
            '%<!-- ghost-content -->%'
        ));
        $total += intval($posts_count);
        
        $stats['total_ghost_posts'] = $total;
        $stats['total_pages'] = $total > 0 ? ceil($total / 2000) : 1;
        
        return $stats;
    }
    
    /**
     * Sitemap URL'ini al
     * ⚡ DOMAIN-BASED UNIQUE URL - Doğal ve özgün
     */
    public static function get_sitemap_url() {
        $slug = get_option('gplrock_sitemap_slug');
        
        if (!$slug) {
            // Domain bazlı doğal slug oluştur
            $domain = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field($_SERVER['HTTP_HOST']) : parse_url(get_site_url(), PHP_URL_HOST);
            $domain = str_replace(['www.', 'http://', 'https://'], '', $domain);
            $hash = crc32($domain . 'sitemap');
            
            // Doğal kelime kombinasyonları
            $words = [
                'content', 'archive', 'index', 'library', 'catalog', 'repository',
                'pages', 'posts', 'articles', 'items', 'resources', 'data',
                'map', 'list', 'feed', 'registry', 'collection', 'database'
            ];
            
            $word1 = $words[abs($hash) % count($words)];
            $word2 = $words[abs($hash >> 8) % count($words)];
            
            // Aynı kelime gelirse farklı al
            if ($word1 === $word2) {
                $word2 = $words[abs($hash >> 16) % count($words)];
            }
            
            $slug = $word1 . '-' . $word2;
            update_option('gplrock_sitemap_slug', $slug);
        }
        
        return home_url('/' . $slug . '.xml');
    }
    
    /**
     * SEO plugin'lerine sitemap bildir
     * ⚡ OPTIMIZED: Async ping + Rate limit
     */
    public static function notify_seo_plugins() {
        $sitemap_url = self::get_sitemap_url();
        
        // ⚡ Rate limit: Max 1 ping per hour
        $last_ping = get_transient('gplrock_last_sitemap_ping');
        if ($last_ping) {
            return false; // Too soon
        }
        
        // Yoast SEO
        if (function_exists('wpseo_submit_sitemap')) {
            wpseo_submit_sitemap($sitemap_url);
        }
        
        // Rank Math
        if (function_exists('rank_math_sitemap_ping')) {
            rank_math_sitemap_ping($sitemap_url);
        }
        
        // All in One SEO
        if (function_exists('aioseo_submit_sitemap')) {
            aioseo_submit_sitemap($sitemap_url);
        }
        
        // ⚡ ASYNC: Google + Bing ping (non-blocking)
        $engines = [
            'https://www.google.com/ping?sitemap=' . urlencode($sitemap_url),
            'https://www.bing.com/ping?sitemap=' . urlencode($sitemap_url),
            'https://www.google.com/webmasters/sitemaps/ping?sitemap=' . urlencode($sitemap_url)
        ];
        
        foreach ($engines as $ping_url) {
            wp_remote_get($ping_url, [
                'timeout' => 3,
                'blocking' => false,
                'sslverify' => false
            ]);
        }
        
        // ⚡ Set rate limit (1 saat)
        set_transient('gplrock_last_sitemap_ping', time(), HOUR_IN_SECONDS);
        
        return true;
    }
    
    /**
     * Otomatik sitemap güncelleme cron'u
     */
    public static function schedule_sitemap_update() {
        if (!wp_next_scheduled('gplrock_sitemap_update')) {
            wp_schedule_event(time(), 'daily', 'gplrock_sitemap_update');
        }
    }
    
    /**
     * Sitemap güncelleme cron fonksiyonu
     * ⚡ OPTIMIZED: Smart update - Only when needed
     */
    public static function sitemap_update_cron() {
        // ⚡ Smart check: Sadece yeni içerik varsa güncelle
        $last_update = get_option('gplrock_sitemap_last_update', 0);
        $last_publish = get_option('gplrock_last_ghost_publish', 0);
        
        if ($last_publish <= $last_update) {
            return false; // No new content, skip
        }
        
        // Cache temizle
        self::clear_sitemap_cache();
        
        // SEO plugin'lerine bildir
        self::notify_seo_plugins();
        
        // ⚡ Update timestamp
        update_option('gplrock_sitemap_last_update', time());
        
        return true;
    }
    
    /**
     * ⚡ QUICK INFO: Sitemap durumu ve URL
     */
    public static function get_sitemap_info() {
        $slug = get_option('gplrock_sitemap_slug', 'content-map');
        $url = home_url('/' . $slug . '.xml');
        $stats = self::get_sitemap_stats();
        
        return [
            'url' => $url,
            'slug' => $slug,
            'total_posts' => $stats['total_ghost_posts'],
            'total_pages' => $stats['total_pages'],
            'last_update' => get_option('gplrock_sitemap_last_update', 0),
            'cache_active' => get_transient('gplrock_ghost_sitemap_1') !== false,
            'robots_included' => true,
            'seo_notified' => get_transient('gplrock_last_sitemap_ping') !== false
        ];
    }
}


