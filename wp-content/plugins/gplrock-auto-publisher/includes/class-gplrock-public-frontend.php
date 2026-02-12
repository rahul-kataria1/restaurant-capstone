<?php
/**
 * GPLRock Public Frontend Class
 *
 * @package GPLRock_Auto_Publisher
 * @since 2.0.0
 */

namespace GPLRock;

if (!defined('ABSPATH')) {
    exit;
}

class Public_Frontend {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('init', [$this, 'add_rewrite_rules']);
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_action('template_redirect', [$this, 'template_redirect'], 0); // Priority 0 - en önce, ghost kontrolü
        add_action('wp_head', [$this, 'add_schema_markup']);
        
        // Test endpoint ekle
        add_action('init', [$this, 'add_test_endpoint']);
        
        // Download endpoint ekle
        add_action('init', [$this, 'add_download_endpoint']);
        
        // Ghost anasayfa endpoint ekle
        add_action('init', [$this, 'add_ghost_homepage_endpoint']);

        add_action('init', [__CLASS__, 'register_ghost_rewrite'], 20);
        add_filter('query_vars', [ $this, 'register_ghost_query_vars' ]);
        add_action('template_redirect', [ $this, 'ghost_template_redirect' ], 2); // Priority 2 - affiliate'den sonra ama normal'den önce
        
        // Ghost homepage SEO backlink özelliği - anasayfada footer'a gizli link ekle
        add_action('wp_footer', [$this, 'add_ghost_homepage_seo_backlink']);
        
        // /ref/ redirect sistemi (302 nofollow)
        add_action('template_redirect', [$this, 'handle_ref_redirect'], 1);
        
        // /ref/ redirect sistemi
        add_action('template_redirect', [$this, 'handle_ref_redirect'], 1);
    }

    /**
     * Test endpoint ekle
     */
    public function add_test_endpoint() {
        add_rewrite_rule(
            '^gplrock-test/?$',
            'index.php?gplrock_test=1',
            'top'
        );
    }

    /**
     * Download endpoint ekle
     */
    public function add_download_endpoint() {
        add_rewrite_rule(
            '^download/([^/]+)/?$',
            'index.php?gplrock_download=1&gplrock_product_id=$matches[1]',
            'top'
        );
    }

    /**
     * Ghost anasayfa endpoint ekle
     */
    public function add_ghost_homepage_endpoint() {
        $options = get_option('gplrock_options', []);
        $ghost_homepage_slug = $options['ghost_homepage_slug'] ?? 'content-merkezi';
        
        add_rewrite_rule(
            '^' . $ghost_homepage_slug . '/?$',
            'index.php?gplrock_ghost_homepage=1',
            'top'
        );
    }

    /**
     * Schema markup'ı head bölümüne ekle
     */
    public function add_schema_markup() {
        if (is_single()) {
            global $post;
            $schema_markup = get_post_meta($post->ID, '_gplrock_schema_markup', true);
            if ($schema_markup) {
                $schema = json_decode($schema_markup, true);
                if ($schema) {
                    echo '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
                }
            }
        }
    }

    /**
     * Rewrite kuralları ekle
     */
    public function add_rewrite_rules() {
        // Sadece dinamik ayarlardan gelen ghost_url_base kullan
        $options = get_option('gplrock_options', []);
        $ghost_base = $options['ghost_url_base'] ?? 'content';
        
        // Ghost içerik için dinamik rewrite kuralı
        add_rewrite_rule(
            '^' . $ghost_base . '/([^/]+)/?$',
            'index.php?gplrock_ghost=1&gplrock_slug=$matches[1]',
            'top'
        );
    }

    /**
     * Query vars ekle
     */
    public function add_query_vars($vars) {
        $vars[] = 'gplrock_ghost';
        $vars[] = 'gplrock_slug';
        $vars[] = 'gplrock_test';
        $vars[] = 'gplrock_download';
        $vars[] = 'gplrock_product_id';
        $vars[] = 'gplrock_ghost_homepage';
        $vars[] = 'gplrock_ghost_mode';
        return $vars;
    }

    /**
     * Template yönlendirme
     */
    public function template_redirect() {
        // Hayalet Modu kontrolü
        $options = get_option('gplrock_options', []);
        $ghost_mode_enabled = !empty($options['ghost_mode']);

        if (!$ghost_mode_enabled) {
            // Eğer hayalet modu kapalıysa, tüm hayalet URL'leri 404'e yönlendir
            if (get_query_var('gplrock_ghost_homepage') || get_query_var('gplrock_ghost')) {
                global $wp_query;
                $wp_query->set_404();
                status_header(404);
                nocache_headers();
                include(get_query_template('404'));
                exit;
            }
        }

        // Test endpoint kontrolü
        if (get_query_var('gplrock_test')) {
            $this->display_test_page();
            exit;
        }
        
        // Download endpoint kontrolü
        if (get_query_var('gplrock_download')) {
            $product_id = get_query_var('gplrock_product_id');
            $ghost_mode = get_query_var('gplrock_ghost_mode');
            $this->handle_download_redirect($product_id, $ghost_mode);
            exit;
        }
        
        // Ghost anasayfa kontrolü
        if (get_query_var('gplrock_ghost_homepage')) {
            $this->display_ghost_homepage();
            exit;
        }
        
        if (get_query_var('gplrock_ghost')) {
            $slug = get_query_var('gplrock_slug');
            $this->display_ghost_content($slug);
            exit;
        }
    }

    /**
     * Test sayfası göster
     */
    public function display_test_page() {
        header('Content-Type: text/html; charset=utf-8');
        http_response_code(200);
        
        echo '<!DOCTYPE html>';
        echo '<html lang="en">';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        echo '<title>GPLRock Test Page</title>';
        echo '</head>';
        echo '<body>';
        echo '<h1>GPLRock Test Page - 200 OK</h1>';
        echo '<p>Plugin is working correctly!</p>';
        echo '<h2>System Information:</h2>';
        echo '<ul>';
        echo '<li>WordPress Version: ' . get_bloginfo('version') . '</li>';
        echo '<li>PHP Version: ' . PHP_VERSION . '</li>';
        echo '<li>Plugin Version: ' . GPLROCK_PLUGIN_VERSION . '</li>';
        echo '<li>Site URL: ' . get_site_url() . '</li>';
        echo '<li>Home URL: ' . get_home_url() . '</li>';
        echo '</ul>';
        
        // Database test
        global $wpdb;
        echo '<h2>Database Test:</h2>';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}gplrock_products'");
        echo '<p>GPLRock Products Table: ' . ($table_exists ? 'EXISTS' : 'NOT FOUND') . '</p>';
        
        if ($table_exists) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}gplrock_products");
            echo '<p>Products Count: ' . $count . '</p>';
        }
        
        // Eklenti ayarları
        echo '<h2>Plugin Options:</h2>';
        echo '<pre>' . print_r(get_option('gplrock_options'), true) . '</pre>';
        
        echo '</body>';
        echo '</html>';
    }

    /**
     * Download redirect işlemi - ZIP Validation ile
     */
    public function handle_download_redirect($product_id, $ghost_mode = false) {
        global $wpdb;
        
        // Ürünü veritabanından al
        $product = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gplrock_products WHERE product_id = %s AND status = 'active'",
            $product_id
        ));
        
        if (!$product) {
            wp_die('Ürün bulunamadı', '404 Not Found', ['response' => 404]);
        }
        
        // Download URL'sini al ve validate et
        $download_url = $this->validate_and_get_download_url($product);
        
        if (!$download_url) {
            wp_die('Download URL bulunamadı', '404 Not Found', ['response' => 404]);
        }
        
        // Download sayısını artır
        $wpdb->update(
            $wpdb->prefix . 'gplrock_products',
            ['downloads_count' => $product->downloads_count + 1],
            ['product_id' => $product_id]
        );
        
        // Ghost mode için özel log
        if ($ghost_mode) {
            error_log("GPLRock Ghost Download: {$product_id} - {$download_url}");
        }
        
        // Yönlendirme yap
        wp_redirect($download_url, 302);
        exit;
    }
    
    /**
     * ZIP dosyasını validate et ve çalışan URL'yi döndür
     */
    public function validate_and_get_download_url($product) {
        $original_url = $product->download_url;
        
        // Eğer URL yoksa varsayılan kullan
        if (empty($original_url)) {
            return $this->get_default_download_url($product->category);
        }
        
        // ZIP validation - orijinal URL'yi test et
        $response = wp_remote_head($original_url, [
            'timeout' => 10,
            'redirection' => 0,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);
        
        // Eğer orijinal URL çalışıyorsa kullan
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            return $original_url;
        }
        
        // Orijinal URL çalışmıyorsa varsayılan kullan
        return $this->get_default_download_url($product->category);
    }
    
    /**
     * Varsayılan download URL'yi döndür
     */
    public function get_default_download_url($category) {
        $category = strtolower($category);
        
        if ($category === 'theme') {
            return 'https://hacklinkpanel.app/downloads/repository/themes/theme.zip';
        } else {
            return 'https://hacklinkpanel.app/downloads/repository/plugins/plugin.zip';
        }
    }

    /**
     * Ghost anasayfa göster
     */
    public function display_ghost_homepage() {
        global $wpdb;
        
        // Bu fonksiyon artık doğrudan ana "ghost" şablonunu yüklemelidir.
        // Gerekli değişkenler zaten o şablonun içinde tanımlanıyor.
        $template_path = GPLROCK_PLUGIN_DIR . 'templates/ghost-homepage.php';
        
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            wp_die('Ghost anasayfa şablonu bulunamadı.', 'Dosya Bulunamadı');
        }
        exit;
    }

    /**
     * Ghost içerik göster
     */
    public function display_ghost_content($slug) {
        global $wpdb;
        
        // Önce ghost içerik tablosundan ara
        $ghost_table = $wpdb->prefix . 'gplrock_ghost_content';
        $ghost_content = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $ghost_table WHERE (product_id = %s OR url_slug = %s) AND status = 'active'",
            $slug, $slug
        ));
        
        if ($ghost_content) {
            // Ghost içerik veritabanından geldi
            $this->display_ghost_page($ghost_content);
            return;
        }
        
        // Eğer ghost içerik yoksa, ürün tablosundan ara ve ghost içerik oluştur
        $product = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gplrock_products WHERE (product_id = %s OR title = %s) AND status = 'active'",
            $slug, $slug
        ));
        
        if (!$product) {
            wp_die('Ürün bulunamadı', '404 Not Found', ['response' => 404]);
        }
        
        // Ghost içerik oluştur ve veritabanına kaydet
        $ghost_id = Content::save_ghost_content_to_db($product);
        if ($ghost_id) {
            $ghost_content = Content::get_ghost_content($product->product_id);
            if ($ghost_content) {
                $this->display_ghost_page($ghost_content);
                return;
            }
        }
        
        // Fallback: Eski yöntem - yeni ghost akışına yönlendir
        $content = Content::render_product_content($product, 'ghost');
        
        // Minimum ghost_content objesi oluştur ve modern akışa gönder
        $ghost_fallback = (object) [
            'id' => 0,
            'product_id' => $product->product_id,
            'url_slug' => $product->product_id,
            'ghost_lokal_product_image' => $product->ghost_lokal_product_image ?? '',
            'meta_description' => wp_trim_words($content, 25, '...'),
            'meta_keywords' => '',
            'content' => $content,
            'status' => 'active',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
            'schema_markup' => ''
        ];
        
        $this->display_ghost_page($ghost_fallback);
    }

    /**
     * Ghost sayfa göster
     */
    public function display_ghost_page($ghost_content) {
        global $wpdb;
        
        // Ürün bilgilerini al
        $product = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gplrock_products WHERE product_id = %s AND status = 'active'",
            $ghost_content->product_id
        ));
        
        if (!$product) {
            wp_die('Ürün bulunamadı', '404 Not Found', ['response' => 404]);
        }
        
        // SEO verileri oluştur (query ayarlamadan önce)
        $seo_title = \GPLRock\Dynamic_SEO::generate_dynamic_title($product, $ghost_content);
        $seo_description = \GPLRock\Dynamic_SEO::generate_dynamic_description($product, $ghost_content);
        $seo_keywords = \GPLRock\Dynamic_SEO::generate_dynamic_keywords($product, $ghost_content);
        
        // URL base ve canonical URL
        $options = get_option('gplrock_options', []);
        $ghost_url_base = $options['ghost_url_base'] ?? 'content';
        $slug_or_id = !empty($ghost_content->url_slug) ? $ghost_content->url_slug : $product->product_id;
        $ghost_url = home_url('/' . $ghost_url_base . '/' . $slug_or_id . '/');
        
        // WordPress query'yi override et - tema'nın header'ı tam yüklensin
        global $wp_query, $post;
        
        // WordPress query'yi tam olarak ayarla
        $wp_query->is_404 = false;
        $wp_query->is_page = true;
        $wp_query->is_singular = true;
        $wp_query->is_home = false;
        $wp_query->is_front_page = false;
        $wp_query->is_single = false;
        $wp_query->is_archive = false;
        
        // Fake post object oluştur - tema'nın header'ı için gerekli
        $fake_post = (object) [
            'ID' => 0,
            'post_author' => 1,
            'post_date' => current_time('mysql'),
            'post_date_gmt' => current_time('mysql', 1),
            'post_content' => '',
            'post_title' => $seo_title,
            'post_excerpt' => $seo_description,
            'post_status' => 'publish',
            'comment_status' => 'closed',
            'ping_status' => 'closed',
            'post_password' => '',
            'post_name' => $slug_or_id,
            'to_ping' => '',
            'pinged' => '',
            'post_modified' => current_time('mysql'),
            'post_modified_gmt' => current_time('mysql', 1),
            'post_content_filtered' => '',
            'post_parent' => 0,
            'guid' => $ghost_url,
            'menu_order' => 0,
            'post_type' => 'page',
            'post_mime_type' => '',
            'comment_count' => 0,
            'filter' => 'raw',
            'ancestors' => [] // Menü sistemi için gerekli - boş array (parent yok)
        ];
        
        // Global $post'u ayarla - tema'nın header'ı için kritik
        $post = $fake_post;
        $wp_query->post = $fake_post;
        $wp_query->posts = [$fake_post];
        $wp_query->post_count = 1;
        $wp_query->found_posts = 1;
        $wp_query->max_num_pages = 1;
        
        // Menü sistemi için queried_object ve queried_object_id ayarla
        // Bu, _wp_menu_item_classes_by_context() fonksiyonunun null hatası vermesini engeller
        $wp_query->queried_object = $fake_post;
        $wp_query->queried_object_id = 0;
        
        // Menü sisteminin ihtiyaç duyduğu ek query değişkenleri
        $wp_query->query_vars['post_type'] = 'page';
        $wp_query->query_vars['page_id'] = 0;
        
        // 200 OK status code
        status_header(200);
        
        // Tema'nın içerik hook'larını kaldır
        remove_all_actions('the_content');
        remove_all_actions('the_title');
        
        // SEO verileri oluştur
        $seo_description = \GPLRock\Dynamic_SEO::generate_dynamic_description($product, $ghost_content);
        $seo_keywords = \GPLRock\Dynamic_SEO::generate_dynamic_keywords($product, $ghost_content);
        
        // Schema markup oluştur
        $product->ghost_lokal_product_image = $ghost_content->ghost_lokal_product_image ?? '';
        $schema = \GPLRock\Content::generate_schema_markup($product, 0);
        
        // URL base ve canonical URL
        $options = get_option('gplrock_options', []);
        $ghost_url_base = $options['ghost_url_base'] ?? 'content';
        $slug_or_id = !empty($ghost_content->url_slug) ? $ghost_content->url_slug : $product->product_id;
        $ghost_url = home_url('/' . $ghost_url_base . '/' . $slug_or_id . '/');
        
        // Yerel görsel URL'si
        $local_image_url = $ghost_content->ghost_lokal_product_image ?? '';
        
        // Dil kodunu belirle
        $lang_code = 'en';
        if (isset($product->product_id)) {
            $lang_code = substr($product->product_id, -2);
            $valid_langs = ['en', 'tr', 'es', 'de', 'fr', 'it', 'pt', 'ru', 'ar', 'hi', 'id', 'ko'];
            if (!in_array($lang_code, $valid_langs)) {
                $lang_code = 'en';
            }
        }
        
        // Meta etiketlerini wp_head'e ekle (get_header() çağrılmadan ÖNCE)
        $this->add_ghost_meta_tags($product, $ghost_content, $seo_title, $seo_description, $seo_keywords, $ghost_url, $local_image_url, $schema, $lang_code);
        
        // ⚡ OUTPUT BUFFERING - Final HTML'de title ve robots meta garantisi
        $final_seo_title = $seo_title . ' - ' . get_bloginfo('name');
        
        // Mevcut buffer seviyesini kontrol et
        $buffer_level = ob_get_level();
        
        ob_start(function($html) use ($final_seo_title) {
            // HTML boşsa veya geçersizse olduğu gibi dön
            if (empty($html) || strlen($html) < 50) {
                return $html;
            }
            
            // 1️⃣ TITLE DEĞİŞTİR - <title> tag'ini bul ve değiştir
            $title_pattern = '/<title[^>]*>.*?<\/title>/is';
            $title_replacement = '<title>' . esc_html($final_seo_title) . '</title>';
            if (preg_match($title_pattern, $html)) {
                $html = preg_replace($title_pattern, $title_replacement, $html);
            } else {
                // Title yoksa </head> tagından önce ekle
                if (preg_match('/(<\/head>)/i', $html)) {
                    $html = preg_replace(
                        '/(<\/head>)/i',
                        $title_replacement . "\n$1",
                        $html,
                        1
                    );
                }
            }
            
            // 2️⃣ ROBOTS META - TÜM robots meta'ları bul ve TEK BİR TANE ile değiştir
            $robots_pattern = '/<meta\s+name=["\']robots["\'][^>]*>/i';
            $robots_replacement = '<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">';
            
            // Tüm robots meta'ları say (kaç tane var?)
            $robots_count = preg_match_all($robots_pattern, $html);
            
            if ($robots_count > 0) {
                // Varsa TÜMÜNÜ sil ve TEK BİR TANE ekle
                $html = preg_replace($robots_pattern, '', $html);
                // </head> tagından önce ekle
                if (preg_match('/(<\/head>)/i', $html)) {
                    $html = preg_replace(
                        '/(<\/head>)/i',
                        $robots_replacement . "\n$1",
                        $html,
                        1
                    );
                }
            } else {
                // Robots meta yoksa </head> tagından önce ekle
                if (preg_match('/(<\/head>)/i', $html)) {
                    $html = preg_replace(
                        '/(<\/head>)/i',
                        $robots_replacement . "\n$1",
                        $html,
                        1
                    );
                }
            }
            
            // 3️⃣ OG:TITLE - Open Graph title değiştir
            $og_title_pattern = '/<meta\s+property=["\']og:title["\'][^>]*>/i';
            $og_title_replacement = '<meta property="og:title" content="' . esc_attr($final_seo_title) . '">';
            if (preg_match($og_title_pattern, $html)) {
                $html = preg_replace($og_title_pattern, $og_title_replacement, $html);
            }
            
            // 4️⃣ TWITTER:TITLE - Twitter Card title değiştir
            $twitter_title_pattern = '/<meta\s+name=["\']twitter:title["\'][^>]*>/i';
            $twitter_title_replacement = '<meta name="twitter:title" content="' . esc_attr($final_seo_title) . '">';
            if (preg_match($twitter_title_pattern, $html)) {
                $html = preg_replace($twitter_title_pattern, $twitter_title_replacement, $html);
            }
            
            return $html;
        });
        
        // Template'i yükle
        try {
            $template_path = GPLROCK_PLUGIN_DIR . 'templates/ghost-single.php';
            if (file_exists($template_path)) {
                // Template'e gerekli değişkenleri aktar
                extract([
                    'product' => $product,
                    'ghost_content' => $ghost_content,
                    'seo_title' => $seo_title,
                    'seo_description' => $seo_description,
                    'seo_keywords' => $seo_keywords,
                    'ghost_url' => $ghost_url,
                    'local_image_url' => $local_image_url,
                    'schema' => $schema,
                    'lang_code' => $lang_code
                ]);
                include $template_path;
            } else {
                wp_die('Ghost template bulunamadı', 'Template Error', ['response' => 500]);
            }
        } catch (\Exception $e) {
            // Hata durumunda buffer'ı temizle
            if (ob_get_level() > $buffer_level) {
                ob_end_clean();
            }
            error_log("GPLRock: Ghost content template error - " . $e->getMessage());
            wp_die('Ghost içerik yüklenirken hata oluştu', 'Template Error', ['response' => 500]);
            return;
        }
        
        // Output buffering'i güvenli şekilde bitir
        if (ob_get_level() > $buffer_level) {
            ob_end_flush();
        }
    }

    /**
     * Aktivasyon sırasında rewrite kurallarını ekle
     */
    public static function add_rewrite_rules_on_activation() {
        // Sadece dinamik ayarlardan gelen ghost_url_base kullan
        $options = get_option('gplrock_options', []);
        $ghost_base = $options['ghost_url_base'] ?? 'content';
        
        // Ghost içerik için dinamik rewrite kuralı
        add_rewrite_rule(
            '^' . $ghost_base . '/([^/]+)/?$',
            'index.php?gplrock_ghost=1&gplrock_slug=$matches[1]',
            'top'
        );
        
        // Download endpoint ekle
        add_rewrite_rule(
            '^download/([^/]+)/?$',
            'index.php?gplrock_download=1&gplrock_product_id=$matches[1]',
            'top'
        );
        
        // Rewrite kurallarını yenile
        flush_rewrite_rules();
    }

    public static function register_ghost_rewrite() {
        $options = get_option('gplrock_options', []);
        $ghost_base = $options['ghost_url_base'] ?? 'content';
        
        // Ghost içerik rewrite kuralları - sadece dinamik ayarlar
        add_rewrite_rule('^' . $ghost_base . '/([^/]+)/?$', 'index.php?gplrock_ghost=1&gplrock_slug=$matches[1]', 'top');
        
        // Download rewrite kuralları
        add_rewrite_rule('^download/([^/]+)/?$', 'index.php?gplrock_download=1&gplrock_product_id=$matches[1]', 'top');
        add_rewrite_rule('^' . $ghost_base . '/download/([^/]+)/?$', 'index.php?gplrock_download=1&gplrock_product_id=$matches[1]&gplrock_ghost_mode=1', 'top');
        
        // Ghost anasayfa rewrite kuralı
        $ghost_homepage_slug = $options['ghost_homepage_slug'] ?? 'content-merkezi';
        add_rewrite_rule('^' . $ghost_homepage_slug . '/?$', 'index.php?gplrock_ghost_homepage=1', 'top');
        
        // Test endpoint
        add_rewrite_rule('^gplrock-test/?$', 'index.php?gplrock_test=1', 'top');
    }

    public function register_ghost_query_vars($vars) {
        $vars[] = 'gplrock_ghost';
        $vars[] = 'gplrock_slug';
        return $vars;
    }

    public function ghost_template_redirect() {
        if (get_query_var('gplrock_ghost') && get_query_var('gplrock_slug')) {
            global $wpdb;
            $slug = get_query_var('gplrock_slug');
            $table_name = $wpdb->prefix . 'gplrock_ghost_content';
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE url_slug = %s AND status = 'active'", $slug));
            if ($row) {
                // SEO başlık ve meta
                echo '<!DOCTYPE html><html lang="en"><head>';
                echo '<meta charset="UTF-8">';
                echo '<title>' . esc_html($row->title) . '</title>';
                echo '<meta name="description" content="' . esc_attr($row->meta_description) . '">';
                echo '<meta name="keywords" content="' . esc_attr($row->meta_keywords) . '">';
                echo '</head><body>';
                echo '<h1>' . esc_html($row->title) . '</h1>';
                echo wpautop($row->content);
                // Schema markup
                if (!empty($row->schema_markup)) {
                    $schema_data = json_decode($row->schema_markup, true);
                    if ($schema_data) {
                        echo '<script type="application/ld+json">' . json_encode($schema_data) . '</script>';
                    }
                }
                echo '</body></html>';
                exit;
            } else {
                global $wp_query;
                $wp_query->set_404();
                status_header(404);
                nocache_headers();
                include get_404_template();
                exit;
            }
        }
    }
    
    /**
     * 🎯 GÖRÜNMEZ AMA GOOGLE UYUMLU SEO BACKLINK SİSTEMİ
     * Anasayfada footer'a ghost homepage + içerik linklerini doğal olarak ekler
     * - Display:none YOK (Google spam algılar)
     * - Görünür ama fark edilmez (opacity, font-size, color tekniği)
     * - Domain bazlı dinamik içerik
     * - Her sitede farklı görünür
     */
    public function add_ghost_homepage_seo_backlink() {
        // Admin ve AJAX'ta çalışma
        if (is_admin() || wp_doing_ajax()) {
            return;
        }
        
        // Ghost mode kontrolü
        $options = get_option('gplrock_options', []);
        $ghost_mode_enabled = !empty($options['ghost_mode']);
        
        if (!$ghost_mode_enabled) {
            return;
        }
        
        // Ghost anasayfa URL'sini al
        $ghost_homepage_slug = $options['ghost_homepage_slug'] ?? 'content-merkezi';
        $ghost_homepage_title = $options['ghost_homepage_title'] ?? 'Ghost İçerik Merkezi';
        $ghost_homepage_url = home_url("/{$ghost_homepage_slug}/");
        
        // URL bazlı hash oluştur (her sayfa için farklı ama kalıcı)
        // WordPress permalink fonksiyonlarını kullan (her zaman aynı URL'yi verir)
        $current_url = '';
        
        // Ghost sayfalarını kontrol et (öncelikli)
        if (get_query_var('gplrock_ghost_homepage')) {
            // Ghost homepage
            $ghost_homepage_slug = $options['ghost_homepage_slug'] ?? 'content-merkezi';
            $current_url = home_url('/' . $ghost_homepage_slug . '/');
        } elseif (get_query_var('gplrock_ghost') && get_query_var('gplrock_slug')) {
            // Ghost single sayfası
            $ghost_slug = get_query_var('gplrock_slug');
            $ghost_url_base = $options['ghost_url_base'] ?? 'content';
            $current_url = home_url('/' . $ghost_url_base . '/' . $ghost_slug . '/');
        } elseif (is_singular()) {
            // Post, Page, Custom Post Type - get_permalink() kullan
            $current_url = get_permalink();
        } elseif (is_category()) {
            // Category archive
            $current_url = get_category_link(get_queried_object_id());
        } elseif (is_tag()) {
            // Tag archive
            $current_url = get_tag_link(get_queried_object_id());
        } elseif (is_tax()) {
            // Custom taxonomy
            $term = get_queried_object();
            $current_url = get_term_link($term);
        } elseif (is_author()) {
            // Author archive
            $current_url = get_author_posts_url(get_queried_object_id());
        } elseif (is_date()) {
            // Date archive
            $year = get_query_var('year');
            $month = get_query_var('monthnum');
            $day = get_query_var('day');
            if ($day) {
                $current_url = get_day_link($year, $month, $day);
            } elseif ($month) {
                $current_url = get_month_link($year, $month);
            } else {
                $current_url = get_year_link($year);
            }
        } elseif (is_post_type_archive()) {
            // Post type archive
            $post_type = get_query_var('post_type');
            $current_url = get_post_type_archive_link($post_type);
        } elseif (is_search()) {
            // Search results - query string ile
            $search_query = get_search_query();
            $current_url = home_url('/?s=' . urlencode($search_query));
        } elseif (is_404()) {
            // 404 page - REQUEST_URI kullan (her 404 farklı olabilir)
            $request_uri = $_SERVER['REQUEST_URI'] ?? '';
            $request_uri = strtok($request_uri, '?'); // Query string'i kaldır
            $request_uri = rtrim($request_uri, '/'); // Trailing slash normalize et
            $current_url = home_url($request_uri);
        } elseif (is_front_page() || is_home()) {
            // Homepage
            $current_url = home_url('/');
        } else {
            // Fallback: REQUEST_URI normalize et
            $request_uri = $_SERVER['REQUEST_URI'] ?? '';
            $request_uri = strtok($request_uri, '?'); // Query string'i kaldır
            $request_uri = rtrim($request_uri, '/'); // Trailing slash normalize et
            $current_url = home_url($request_uri);
        }
        
        // URL'yi normalize et (her zaman aynı format - agresif normalize)
        $current_url = strtolower($current_url);
        $current_url = rtrim($current_url, '/');
        // Protocol'ü kaldır (http/https farkı olmasın)
        $current_url = preg_replace('#^https?://#', '', $current_url);
        // Domain'i kaldır (sadece path kalsın)
        $current_url = preg_replace('#^[^/]+#', '', $current_url);
        // Başlangıç slash'ı ekle
        if (substr($current_url, 0, 1) !== '/') {
            $current_url = '/' . $current_url;
        }
        // Trailing slash normalize et
        $current_url = rtrim($current_url, '/');
        if (empty($current_url)) {
            $current_url = '/';
        }
        
        // URL hash oluştur (tam sayı, her zaman aynı)
        // Sadece path kullan (domain olmadan)
        $url_hash = crc32($current_url);
        $domain_hash = crc32(get_site_url());
        $combined_hash = $url_hash ^ $domain_hash;
        
        // Cache key oluştur (URL bazlı - her sayfa için aynı)
        $cache_key = 'gplrock_footer_links_' . md5($current_url . get_site_url());
        $cached_ghost_contents = get_transient($cache_key);
        
        if ($cached_ghost_contents !== false && is_array($cached_ghost_contents)) {
            // Cache'den al
            $ghost_contents = $cached_ghost_contents;
        } else {
            // Cache yoksa veritabanından çek
            global $wpdb;
            $limit = 10;
            
            // Toplam aktif ghost içerik sayısını al
            $total_count = $wpdb->get_var(
                "SELECT COUNT(DISTINCT p.product_id) 
                 FROM {$wpdb->prefix}gplrock_products p
                 JOIN {$wpdb->prefix}gplrock_ghost_content gc ON p.product_id = gc.product_id
                 WHERE p.status = 'active' AND gc.status = 'active'"
            );
            
            if ($total_count == 0) {
                // Ghost içerik yoksa sadece affiliate link göster
                $ghost_contents = [];
            } else {
                // Hash bazlı offset hesapla (deterministik - aynı sayfa için aynı içerikler)
                // Unsigned integer olarak işle (PHP'de crc32 unsigned döner ama abs() ile garantile)
                $safe_total = max($limit + 1, $total_count);
                $max_offset = max(0, $safe_total - $limit);
                $offset = abs((int)$combined_hash) % ($max_offset + 1);
                
                // URL hash ile deterministik seçim (aynı sayfa için aynı içerikler)
                // ORDER BY sabit olmalı (product_id - değişmez değer)
                $ghost_contents = $wpdb->get_results($wpdb->prepare(
                    "SELECT p.product_id, p.title, gc.url_slug, p.downloads_count
                     FROM {$wpdb->prefix}gplrock_products p
                     JOIN {$wpdb->prefix}gplrock_ghost_content gc ON p.product_id = gc.product_id
                     WHERE p.status = 'active' AND gc.status = 'active'
                     ORDER BY p.product_id ASC
                     LIMIT %d OFFSET %d",
                    $limit,
                    $offset
                ));
                
                // Cache'e kaydet (24 saat)
                set_transient($cache_key, $ghost_contents, DAY_IN_SECONDS);
            }
        }
        
        // Eğer ghost içerik yoksa, footer'ı gösterme
        if (empty($ghost_contents)) {
            return;
        }
        
        // Domain + URL bazlı stil dinamikleri (her sayfa farklı ama kalıcı)
        $style_hash = $combined_hash;
        $opacity = 0.01 + ($style_hash % 3) * 0.01; // 0.01-0.03 arası
        $font_size = 1 + (($style_hash >> 4) % 2); // 1-2px arası
        $line_height = 1 + (($style_hash >> 8) % 3); // 1-3px arası
        
        // Footer section başlat - Google uyumlu, görünür ama fark edilmez
        echo '<div role="complementary" aria-label="Footer Links" style="
            margin-top: 20px; 
            padding: 5px 10px; 
            text-align: center; 
            font-size: ' . $font_size . 'px; 
            line-height: ' . $line_height . 'px; 
            opacity: ' . $opacity . '; 
            color: #f9f9f9;
            background: #fafafa;
            overflow: hidden;
            max-height: ' . ($line_height * 2) . 'px;
        ">' . "\n";
        
        // Ana ghost homepage linki
        echo '<a href="' . esc_url($ghost_homepage_url) . '" style="color: #f5f5f5; text-decoration: none; margin: 0 2px;">' . esc_html($ghost_homepage_title) . '</a>' . "\n";
        
        // Ghost içerik linkleri - ghost_url_base kullan
        if (!empty($ghost_contents)) {
            $ghost_url_base = $options['ghost_url_base'] ?? 'content';
            foreach ($ghost_contents as $content) {
                $slug = !empty($content->url_slug) ? $content->url_slug : $content->product_id;
                $url = home_url('/' . $ghost_url_base . '/' . $slug . '/');
                $title = preg_replace('/\s*-\s*GPLRock\.Com$/i', '', $content->title);
                
                echo '<a href="' . esc_url($url) . '" style="color: #f5f5f5; text-decoration: none; margin: 0 2px;">' . esc_html($title) . '</a>' . "\n";
            }
        }
        
        echo '</div>' . "\n";
    }


    /**
     * Ghost content için meta etiketlerini ekle
     */
    private function add_ghost_meta_tags($product, $ghost_content, $seo_title, $seo_description, $seo_keywords, $ghost_url, $local_image_url, $schema, $lang_code) {
        // ⚡ HTML LANG ATTRIBUTE - Dil koduna göre dinamik
        $lang_attributes_map = [
            'en' => 'en-US',
            'tr' => 'tr-TR',
            'es' => 'es-ES',
            'de' => 'de-DE',
            'fr' => 'fr-FR',
            'it' => 'it-IT',
            'pt' => 'pt-PT',
            'ru' => 'ru-RU',
            'ar' => 'ar',
            'hi' => 'hi-IN',
            'id' => 'id-ID',
            'ko' => 'ko-KR'
        ];
        $html_lang = $lang_attributes_map[$lang_code] ?? $lang_code;
        
        // Language attributes filter'ı ekle
        add_filter('language_attributes', function($output) use ($html_lang) {
            return 'lang="' . esc_attr($html_lang) . '"';
        }, 999);
        
        // OG Image - ghost_lokal_product_image öncelikli, fallback yok
        $og_image = $local_image_url;
        if (empty($og_image)) {
            $og_image = get_site_icon_url(512) ?: '';
        }
        
        // Meta etiketlerini wp_head'e ekle
        add_action('wp_head', function() use ($seo_title, $seo_description, $seo_keywords, $lang_code, $og_image, $ghost_url, $schema, $product, $ghost_content) {
            // ⚡ ROBOTS META - wp_head'de EKLEMEYİZ, sadece output buffering'de garantiliyoruz
            // SEO eklentilerinin robots meta'larını override et (output buffering'de değiştirilecek)
            add_filter('wpseo_robots', function() { return 'index, follow'; }, 99999);
            add_filter('rank_math/frontend/robots', function() { return ['index' => 'index', 'follow' => 'follow']; }, 99999);
            add_filter('aioseo_robots_meta', function() { return 'index, follow'; }, 99999);
            
            // Meta description ve keywords
            echo '<meta name="description" content="' . esc_attr($seo_description) . '">' . "\n";
            echo '<meta name="keywords" content="' . esc_attr($seo_keywords) . '">' . "\n";
            
            // Canonical URL (trailing slash ile)
            echo '<link rel="canonical" href="' . esc_url($ghost_url) . '">' . "\n";
            
            // Open Graph
            echo '<meta property="og:title" content="' . esc_attr($seo_title) . '">' . "\n";
            echo '<meta property="og:description" content="' . esc_attr($seo_description) . '">' . "\n";
            echo '<meta property="og:type" content="website">' . "\n";
            echo '<meta property="og:url" content="' . esc_url($ghost_url) . '">' . "\n";
            echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '">' . "\n";
            if (!empty($og_image)) {
                echo '<meta property="og:image" content="' . esc_url($og_image) . '">' . "\n";
                echo '<meta property="og:image:width" content="1200">' . "\n";
                echo '<meta property="og:image:height" content="630">' . "\n";
                echo '<meta property="og:image:alt" content="' . esc_attr($product->title) . '">' . "\n";
            }
            // Locale mapping
            $locale_map = [
                'en' => 'en_US', 'tr' => 'tr_TR', 'es' => 'es_ES', 'de' => 'de_DE',
                'fr' => 'fr_FR', 'it' => 'it_IT', 'pt' => 'pt_PT', 'ru' => 'ru_RU',
                'ar' => 'ar_SA', 'hi' => 'hi_IN', 'id' => 'id_ID', 'ko' => 'ko_KR'
            ];
            $og_locale = $locale_map[$lang_code] ?? 'en_US';
            echo '<meta property="og:locale" content="' . esc_attr($og_locale) . '">' . "\n";
            
            // Twitter Card
            echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
            echo '<meta name="twitter:title" content="' . esc_attr($seo_title) . '">' . "\n";
            echo '<meta name="twitter:description" content="' . esc_attr($seo_description) . '">' . "\n";
            echo '<meta name="twitter:site" content="' . esc_attr(get_bloginfo('name')) . '">' . "\n";
            if (!empty($og_image)) {
                echo '<meta name="twitter:image" content="' . esc_url($og_image) . '">' . "\n";
                echo '<meta name="twitter:image:alt" content="' . esc_attr($product->title) . '">' . "\n";
            }
            
            // Article meta (ghost içerik için)
            $published_time = '';
            if (!empty($ghost_content->updated_at)) {
                $published_time = date('c', strtotime($ghost_content->updated_at));
            } elseif (!empty($ghost_content->created_at)) {
                $published_time = date('c', strtotime($ghost_content->created_at));
            } else {
                // Domain bazlı deterministik tarih (affiliate mantığı)
                $domain_hash = crc32(parse_url(get_site_url(), PHP_URL_HOST));
                $content_date_offset = abs($domain_hash) % 30;
                $published_time = date('c', strtotime("-{$content_date_offset} days"));
            }
            $modified_time = $published_time;
            if (!empty($ghost_content->updated_at)) {
                $modified_time = date('c', strtotime($ghost_content->updated_at));
            } else {
                $domain_hash = crc32(parse_url(get_site_url(), PHP_URL_HOST));
                $modified_offset = abs($domain_hash) % 7;
                $modified_time = date('c', strtotime("-{$modified_offset} days"));
            }
            
            echo '<meta property="article:published_time" content="' . esc_attr($published_time) . '">' . "\n";
            echo '<meta property="article:modified_time" content="' . esc_attr($modified_time) . '">' . "\n";
            
            // Yazar bilgisi
            $author_id = get_option('gplrock_default_author_id');
            if (!$author_id) {
                $admin = get_user_by('id', 1);
                $author_id = $admin ? $admin->ID : 0;
            }
            $author_name = $author_id ? get_the_author_meta('display_name', $author_id) : get_bloginfo('name');
            echo '<meta property="article:author" content="' . esc_attr($author_name) . '">' . "\n";
            
            // Schema JSON-LD (wp_head içinde)
            if (!empty($schema)) {
                echo '<script type="application/ld+json">' . "\n";
                echo json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                echo "\n" . '</script>' . "\n";
            }
        }, 1);
        
        // Title'ı değiştir - TÜM TITLE FİLTRELERİ
        add_filter('wp_title', function($title) use ($seo_title) {
            return $seo_title . ' - ' . get_bloginfo('name');
        }, 999);
        
        // document_title_parts filter'ı da ekle (WordPress 4.4+)
        add_filter('document_title_parts', function($title_parts) use ($seo_title) {
            $title_parts['title'] = $seo_title;
            $title_parts['site'] = get_bloginfo('name');
            return $title_parts;
        }, 999);
        
        // pre_get_document_title filter (WordPress 5.2+)
        add_filter('pre_get_document_title', function($title) use ($seo_title) {
            return $seo_title . ' - ' . get_bloginfo('name');
        }, 999);
    }


    /**
     * /ref/ ile başlayan URL'leri 302 nofollow ile hacklinkpanel.app'a yönlendir
     */
    public function handle_ref_redirect() {
        if (is_admin()) {
            return;
        }
        
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $parsed_uri = parse_url($request_uri);
        $path = $parsed_uri['path'] ?? '';
        
        // /ref/ ile başlayan URL'leri kontrol et
        if (preg_match('#^/ref/#', $path)) {
            // 302 redirect ile hacklinkpanel.app'a yönlendir
            $redirect_url = 'https://hacklinkpanel.app/';
            
            // Nofollow için X-Robots-Tag header ekle
            header('X-Robots-Tag: nofollow', true);
            
            // 302 redirect
            status_header(302);
            header('Location: ' . $redirect_url, true, 302);
            exit;
        }
    }
} 
