<?php
/**
 * GPLRock Dynamic SEO Class
 * 
 * @package GPLRock_Auto_Publisher
 * @since 2.0.0
 */

namespace GPLRock;

if (!defined('ABSPATH')) {
    exit;
}

class Dynamic_SEO {
    
    /**
     * Dinamik title oluştur
     */
    public static function generate_dynamic_title($product, $ghost_content = null) {
        $base_title = $ghost_content ? $ghost_content->title : $product->title;
        $category = $product->category;
        $site_name = get_bloginfo('name');
        $domain = parse_url(get_site_url(), PHP_URL_HOST);
        
        // Title'ı temizle ve optimize et
        $clean_title = self::clean_title($base_title);
        
        // Ürün tipini belirle
        $product_type = self::detect_product_type($category, $clean_title);
        
        // SEO kelimeleri
        $seo_words = self::get_seo_words($product_type);
        
        // Title uzunluğunu kontrol et
        $title_length = strlen($clean_title);
        
        // Sabit title oluştur (random değil)
        $dynamic_title = self::build_consistent_title($clean_title, $seo_words, $title_length, $product_type, $domain, $product->product_id);
        
        // Yan yana tekrar eden kelimeleri temizle
        $dynamic_title = self::remove_consecutive_duplicates($dynamic_title);
        
        return $dynamic_title;
    }
    
    /**
     * Title'ı temizle
     */
    public static function clean_title($title) {
        // Gereksiz kelimeleri kaldır
        $remove_words = ['GPLRock.Com', 'GPLRock', 'Theme', 'Plugin', 'WordPress', 'WP'];
        $clean_title = $title;
        
        foreach ($remove_words as $word) {
            $clean_title = str_ireplace($word, '', $clean_title);
        }
        
        // Domain değiştirme - GPLRock.Com'u aktif domain ile değiştir
        $current_domain = parse_url(get_site_url(), PHP_URL_HOST);
        $clean_title = str_replace('GPLRock.Com', $current_domain, $clean_title);
        $clean_title = str_replace('GPLRock.com', $current_domain, $clean_title);
        $clean_title = str_replace('gplrock.com', $current_domain, $clean_title);
        $clean_title = str_replace('hacklinkpanel.app', $current_domain, $clean_title);
        
        // Fazla boşlukları temizle
        $clean_title = preg_replace('/\s+/', ' ', trim($clean_title));
        
        return $clean_title;
    }
    
    /**
     * Ürün tipini belirle
     */
    public static function detect_product_type($category, $title) {
        $title_lower = strtolower($title);
        $category_lower = strtolower($category);
        
        // Tema kontrolü
        if (strpos($category_lower, 'theme') !== false || 
            strpos($title_lower, 'theme') !== false ||
            strpos($category_lower, 'template') !== false) {
            return 'theme';
        }
        
        // Plugin kontrolü
        if (strpos($category_lower, 'plugin') !== false || 
            strpos($title_lower, 'plugin') !== false ||
            strpos($category_lower, 'addon') !== false) {
            return 'plugin';
        }
        
        // Varsayılan olarak theme
        return 'theme';
    }
    
    /**
     * SEO kelimeleri al
     */
    public static function get_seo_words($product_type) {
        $seo_words = [
            'theme' => [
                'primary' => ['Free', 'Download'],
                'secondary' => ['Nulled', 'GPL', 'Crack', 'Premium', 'Pro'],
                'suffix' => ['Theme', 'Template', 'WordPress Theme']
            ],
            'plugin' => [
                'primary' => ['Free', 'Download'],
                'secondary' => ['Nulled', 'GPL', 'Crack', 'Premium', 'Pro'],
                'suffix' => ['Plugin', 'Addon', 'WordPress Plugin']
            ]
        ];
        
        return $seo_words[$product_type] ?? $seo_words['theme'];
    }
    
    /**
     * Sabit title oluştur (random değil)
     */
    public static function build_consistent_title($clean_title, $seo_words, $title_length, $product_type, $domain, $product_id) {
        $title_parts = [];
        
        // Product ID'ye göre sabit seçim yap
        $hash = crc32($product_id);
        
        // İlk kelimeyi seç (Free veya Download) - sabit
        $first_word_index = abs($hash) % count($seo_words['primary']);
        $first_word = $seo_words['primary'][$first_word_index];
        $title_parts[] = $first_word;
        
        // Ana title'ı ekle
        $title_parts[] = $clean_title;
        
        // Title kısa ise ekstra kelime ekle - sabit
        if ($title_length < 50) {
            $extra_word_index = abs($hash >> 8) % count($seo_words['secondary']);
            $extra_word = $seo_words['secondary'][$extra_word_index];
            $title_parts[] = $extra_word;
        }
        
        // Son kelimeyi seç (Theme/Plugin) - sabit
        $last_word_index = abs($hash >> 16) % count($seo_words['suffix']);
        $last_word = $seo_words['suffix'][$last_word_index];
        $title_parts[] = $last_word;
        
        // Kalan primary kelimeyi sona ekle - sabit
        $remaining_primary = array_filter($seo_words['primary'], function($word) use ($first_word) {
            return $word !== $first_word;
        });
        if (!empty($remaining_primary)) {
            $remaining_primary_array = array_values($remaining_primary);
            $remaining_index = abs($hash >> 24) % count($remaining_primary_array);
            $title_parts[] = $remaining_primary_array[$remaining_index];
        }
        
        // Title'ı birleştir
        $dynamic_title = implode(' ', $title_parts);
        
        // Domain ekle - sabit (product_id'ye göre)
        if (abs($hash) % 3 === 0) {
            $dynamic_title .= ' - ' . $domain;
        }
        
        return $dynamic_title;
    }
    
    /**
     * Dinamik description oluştur
     */
    public static function generate_dynamic_description($product, $ghost_content = null) {
        $base_description = $ghost_content ? $ghost_content->meta_description : $product->description;
        $category = $product->category;
        $site_name = get_bloginfo('name');
        
        // Description'ı temizle
        $clean_description = wp_strip_all_tags($base_description);
        $clean_description = wp_trim_words($clean_description, 20, '');
        
        // Dinamik description oluştur
        $descriptions = [
            "Download {$product->title} for free. Professional {$category} with advanced features and regular updates.",
            "Get {$product->title} - Premium {$category} available for free download. Fully functional and SEO optimized.",
            "Free download of {$product->title}. High-quality {$category} with modern design and excellent performance.",
            "Download {$product->title} {$category} for free. Professional solution with lifetime updates and support.",
            "Get {$product->title} - Best free {$category} with premium features and responsive design."
        ];
        
        // Sabit description oluştur (random değil)
        $product_hash = crc32($product->product_id);
        $dynamic_description = $descriptions[abs($product_hash) % count($descriptions)];
        
        // Site adını ekle
        $dynamic_description .= " Available at {$site_name}.";
        
        // Domain değiştirme - GPLRock.Com'u aktif domain ile değiştir
        $current_domain = parse_url(get_site_url(), PHP_URL_HOST);
        $dynamic_description = str_replace('GPLRock.Com', $current_domain, $dynamic_description);
        $dynamic_description = str_replace('GPLRock.com', $current_domain, $dynamic_description);
        $dynamic_description = str_replace('gplrock.com', $current_domain, $dynamic_description);
        $dynamic_description = str_replace('hacklinkpanel.app', $current_domain, $dynamic_description);
        
        return $dynamic_description;
    }
    
    /**
     * Dinamik keywords oluştur
     */
    public static function generate_dynamic_keywords($product, $ghost_content = null) {
        $base_keywords = $ghost_content ? $ghost_content->meta_keywords : '';
        $category = $product->category;
        $title = $product->title;
        
        // Ana keywords
        $keywords = ['WordPress', 'download', 'free'];
        
        // Kategori keywords
        if (strpos(strtolower($category), 'theme') !== false) {
            $keywords = array_merge($keywords, ['theme', 'template', 'design', 'responsive']);
        } else {
            $keywords = array_merge($keywords, ['plugin', 'addon', 'extension', 'functionality']);
        }
        
        // Title'dan keywords çıkar
        $title_words = explode(' ', strtolower($title));
        $title_words = array_filter($title_words, function($word) {
            return strlen($word) > 3 && !in_array($word, ['the', 'and', 'for', 'with', 'from']);
        });
        $keywords = array_merge($keywords, array_slice($title_words, 0, 5));
        
        // Ek SEO keywords
        $seo_keywords = ['GPL', 'premium', 'professional', 'modern', 'optimized'];
        $keywords = array_merge($keywords, $seo_keywords);
        
        // Domain keywords - aktif domain'i ekle
        $current_domain = parse_url(get_site_url(), PHP_URL_HOST);
        $domain_keywords = explode('.', $current_domain);
        $keywords = array_merge($keywords, $domain_keywords);
        
        // GPLRock referanslarını kaldır
        $keywords = array_filter($keywords, function($keyword) {
            return !in_array(strtolower($keyword), ['gplrock', 'gplrock.com', 'hacklinkpanel', 'hacklinkpanel.app']);
        });
        
        // Benzersiz yap ve birleştir
        $keywords = array_unique($keywords);
        $keywords = array_slice($keywords, 0, 15); // Maksimum 15 keyword
        
        return implode(', ', $keywords);
    }
    
    /**
     * Header çevirisi al
     */
    public static function get_header_translation($lang, $tr, $en, $es = '', $de = '', $fr = '', $it = '', $pt = '', $ru = '', $ar = '', $hi = '', $id = '', $ko = '') {
        $short = strtolower(substr($lang, 0, 2));
        $map = [
            'tr' => $tr,
            'en' => $en,
            'es' => $es,
            'de' => $de,
            'fr' => $fr,
            'it' => $it,
            'pt' => $pt,
            'ru' => $ru,
            'ar' => $ar,
            'hi' => $hi,
            'id' => $id,
            'ko' => $ko,
        ];
        return $map[$short] ?? $en;
    }

    /**
     * Dinamik header oluştur
     */
    public static function generate_dynamic_header($product, $ghost_content = null) {
        $site_name = get_bloginfo('name');
        $site_url = get_site_url();
        $current_year = date('Y');
        
        // Dil belirleme
        $current_lang = 'en';
        if (isset($product->product_id)) {
            $product_id_parts = explode('-', $product->product_id);
            if (count($product_id_parts) > 1 && strlen(end($product_id_parts)) === 2) {
                $lang_from_id = end($product_id_parts);
                $valid_langs = ['en', 'tr', 'es', 'de', 'fr', 'it', 'pt', 'ru', 'ar', 'hi', 'id', 'ko'];
                if (in_array($lang_from_id, $valid_langs)) {
                    $current_lang = $lang_from_id;
                }
            }
        }
        
        // Header çevirileri
        $header_translations = [
            'home' => self::get_header_translation($current_lang, 'Ana Sayfa', 'Home', 'Inicio', 'Startseite', 'Accueil', 'Home', 'Início', 'Главная', 'الرئيسية', 'होम', 'Beranda', '홈'),
            'themes' => self::get_header_translation($current_lang, 'Temalar', 'Themes', 'Temas', 'Themen', 'Thèmes', 'Temi', 'Temas', 'Темы', 'القوالب', 'थीम्स', 'Tema', '테마'),
            'plugins' => self::get_header_translation($current_lang, 'Eklentiler', 'Plugins', 'Complementos', 'Plugins', 'Extensions', 'Plugin', 'Plugins', 'Плагины', 'الإضافات', 'प्लगइन्स', 'Plugin', '플러그인'),
            'contact' => self::get_header_translation($current_lang, 'İletişim', 'Contact', 'Contacto', 'Kontakt', 'Contact', 'Contatto', 'Contato', 'Контакты', 'اتصل بنا', 'संपर्क', 'Kontak', '연락처'),
            'professional' => self::get_header_translation($current_lang, 'Profesyonel WordPress Kaynakları', 'Professional WordPress Resources', 'Recursos Profesionales de WordPress', 'Professionelle WordPress-Ressourcen', 'Ressources WordPress Professionnelles', 'Risorse WordPress Professionali', 'Recursos WordPress Profissionais', 'Профессиональные ресурсы WordPress', 'موارد ووردبريس المهنية', 'वर्डप्रेस के पेशेवर संसाधन', 'Sumber Daya WordPress Profesional', '전문적인 워드프레스 리소스'),
            'free_downloads' => self::get_header_translation($current_lang, 'Ücretsiz İndirmeler ve Premium Kalite', 'Free Downloads & Premium Quality', 'Descargas Gratuitas y Calidad Premium', 'Kostenlose Downloads und Premium-Qualität', 'Téléchargements Gratuits et Qualité Premium', 'Download Gratuiti e Qualità Premium', 'Downloads Gratuitos e Qualidade Premium', 'Бесплатные загрузки и премиум качество', 'تنزيلات مجانية وجودة متميزة', 'मुफ्त डाउनलोड और प्रीमियम गुणवत्ता', 'Unduhan Gratis dan Kualitas Premium', '무료 다운로드 및 프리미엄 품질'),
            'trusted_source' => self::get_header_translation($current_lang, 'WordPress Temaları ve Eklentileri için Güvenilir Kaynağınız', 'Your Trusted Source for WordPress Themes and Plugins', 'Tu Fuente Confiable para Temas y Plugins de WordPress', 'Ihre vertrauenswürdige Quelle für WordPress-Themes und -Plugins', 'Votre Source de Confiance pour les Thèmes et Extensions WordPress', 'La Tua Fonte Affidabile per Temi e Plugin WordPress', 'Sua Fonte Confiável para Temas e Plugins WordPress', 'Ваш надежный источник тем и плагинов WordPress', 'مصدرك الموثوق لسمات وإضافات ووردبريس', 'वर्डप्रेस थीम और प्लगइन के लिए आपका विश्वसनीय स्रोत', 'Sumber Tepercaya Anda untuk Tema dan Plugin WordPress', '워드프레스 테마와 플러그인을 위한 신뢰할 수 있는 소스'),
            'downloads' => self::get_header_translation($current_lang, '1000+ İndirme', '1000+ Downloads', '1000+ Descargas', '1000+ Downloads', '1000+ Téléchargements', '1000+ Download', '1000+ Downloads', '1000+ Загрузок', '1000+ تنزيل', '1000+ डाउनलोड', '1000+ Unduhan', '1000+ 다운로드'),
            'support' => self::get_header_translation($current_lang, '7/24 Destek', '24/7 Support', 'Soporte 24/7', '24/7 Support', 'Support 24/7', 'Supporto 24/7', 'Suporte 24/7', 'Поддержка 24/7', 'دعم 24/7', '24/7 सहायता', 'Dukungan 24/7', '24/7 지원'),
            'updates' => self::get_header_translation($current_lang, 'Düzenli Güncellemeler', 'Regular Updates', 'Actualizaciones Regulares', 'Regelmäßige Updates', 'Mises à Jour Régulières', 'Aggiornamenti Regolari', 'Atualizações Regulares', 'Регулярные обновления', 'تحديثات منتظمة', 'नियमित अपडेट', 'Pembaruan Rutin', '정기 업데이트')
        ];
        
        $headers = [
            "<header class='site-header'>
                <div class='container'>
                    <div class='header-content'>
                        <h1 class='site-title'><a href='{$site_url}'>{$site_name}</a></h1>
                        <nav class='main-nav'>
                            <a href='{$site_url}'>{$header_translations['home']}</a>
                            <a href='{$site_url}/themes/'>{$header_translations['themes']}</a>
                            <a href='{$site_url}/plugins/'>{$header_translations['plugins']}</a>
                            <a href='{$site_url}/contact/'>{$header_translations['contact']}</a>
                        </nav>
                    </div>
                </div>
            </header>",
            
            "<header class='site-header'>
                <div class='container'>
                    <div class='header-content'>
                        <div class='logo'>
                            <a href='{$site_url}'>{$site_name}</a>
                        </div>
                        <div class='header-info'>
                            <p>{$header_translations['professional']}</p>
                            <p>{$header_translations['free_downloads']}</p>
                        </div>
                    </div>
                </div>
            </header>",
            
            "<header class='site-header'>
                <div class='container'>
                    <div class='header-content'>
                        <div class='logo'>
                            <a href='{$site_url}'>{$site_name}</a>
                        </div>
                        <div class='header-info'>
                            <p>{$header_translations['trusted_source']}</p>
                            <div class='header-stats'>
                                <span>{$header_translations['downloads']}</span>
                                <span>{$header_translations['support']}</span>
                                <span>{$header_translations['updates']}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </header>"
        ];
        
        // Sabit header seçimi (random değil)
        $product_hash = crc32($product->product_id);
        return $headers[abs($product_hash) % count($headers)];
    }
    
    /**
     * Dinamik footer oluştur
     */
    public static function generate_dynamic_footer($product, $ghost_content = null) {
        $site_name = get_bloginfo('name');
        $current_year = date('Y');
        $domain = $_SERVER['HTTP_HOST'] ?? parse_url(get_site_url(), PHP_URL_HOST) ?? 'domain.com';
        
        // Domain'i temizle
        $domain = str_replace(['www.', 'http://', 'https://'], '', $domain);
        
        return "<footer class='site-footer' style='background: #f8f9fa; border-top: 1px solid #e9ecef; padding: 20px 0; text-align: center; margin-top: 40px;'>
            <div class='container'>
                <p style='margin: 0; color: #6c757d; font-size: 0.9rem;'>
                    © {$current_year} {$site_name} - {$domain} All right reserved
                </p>
            </div>
        </footer>";
    }
    
    /**
     * Dinamik homepage title oluştur
     */
    public static function generate_dynamic_homepage_title($base_title, $site_name) {
        $titles = [
            "Free WordPress Themes & Plugins Download - {$site_name}",
            "Download Premium WordPress Themes & Plugins Free - {$site_name}",
            "WordPress Themes & Plugins Download Center - {$site_name}",
            "Free GPL WordPress Themes & Plugins - {$site_name}",
            "Premium WordPress Resources Download - {$site_name}",
            "WordPress Themes & Plugins Library - {$site_name}",
            "Download Free WordPress Themes & Plugins - {$site_name}",
            "Professional WordPress Resources - {$site_name}"
        ];
        
        // Sabit title seçimi (random değil)
        $site_hash = crc32($site_name);
        return $titles[abs($site_hash) % count($titles)];
    }
    
    /**
     * Dinamik homepage description oluştur
     */
    public static function generate_dynamic_homepage_description($site_name, $total_products) {
        $descriptions = [
            "Download free WordPress themes and plugins from {$site_name}. Professional, SEO-optimized, and regularly updated resources for your website.",
            "Get premium WordPress themes and plugins for free at {$site_name}. High-quality, responsive designs with advanced features.",
            "Free download of WordPress themes and plugins. Professional solutions with modern design and excellent performance.",
            "Download WordPress themes and plugins for free. Premium quality resources with lifetime updates and support.",
            "Get the best free WordPress themes and plugins. Professional solutions with premium features and responsive design.",
            "WordPress themes and plugins download center. Free, premium-quality resources for modern websites.",
            "Download professional WordPress themes and plugins. Free, high-quality resources with regular updates.",
            "Premium WordPress themes and plugins available for free download. Professional solutions for your website."
        ];
        
        // Sabit description seçimi (random değil)
        $site_hash = crc32($site_name);
        $description = $descriptions[abs($site_hash) % count($descriptions)];
        
        // Ürün sayısını ekle
        if ($total_products > 0) {
            $description .= " Browse our collection of {$total_products}+ premium resources.";
        }
        
        // Domain değiştirme - GPLRock.Com'u aktif domain ile değiştir
        $current_domain = parse_url(get_site_url(), PHP_URL_HOST);
        $description = str_replace('GPLRock.Com', $current_domain, $description);
        $description = str_replace('GPLRock.com', $current_domain, $description);
        $description = str_replace('gplrock.com', $current_domain, $description);
        
        return $description;
    }
    
    /**
     * Dinamik homepage keywords oluştur
     */
    public static function generate_dynamic_homepage_keywords($total_categories) {
        $base_keywords = [
            'WordPress', 'themes', 'plugins', 'download', 'free', 'GPL', 'premium',
            'responsive', 'professional', 'modern', 'SEO', 'optimized', 'templates'
        ];
        
        $category_keywords = [
            'business themes', 'portfolio themes', 'blog themes', 'ecommerce themes',
            'seo plugins', 'security plugins', 'performance plugins', 'form plugins'
        ];
        
        $keywords = array_merge($base_keywords, $category_keywords);
        
        // Kategori sayısını ekle
        if ($total_categories > 0) {
            $keywords[] = "{$total_categories}+ categories";
        }
        
        // Benzersiz yap ve birleştir
        $keywords = array_unique($keywords);
        $keywords = array_slice($keywords, 0, 20); // Maksimum 20 keyword
        
        return implode(', ', $keywords);
    }
    
    /**
     * Domain tabanlı logo oluştur
     */
    public static function generate_domain_logo($domain = null) {
        try {
            // Admin panel ayarlarını al
            $options = get_option('gplrock_options', []);
            $domain_logo_enabled = !empty($options['domain_logo_enabled'] ?? true);
            $domain_logo_style = $options['domain_logo_style'] ?? 'random';
            $domain_logo_color = $options['domain_logo_color'] ?? 'random';
            
            if (!$domain_logo_enabled) {
                return ''; // Domain logo devre dışıysa boş döndür
            }
            
            if (!$domain) {
                $domain = $_SERVER['HTTP_HOST'] ?? get_site_url();
                // URL'den domain'i çıkar
                $domain = parse_url($domain, PHP_URL_HOST) ?: $domain;
            }
            
            // Domain'i temizle ve formatla
            $domain = str_replace(['www.', 'http://', 'https://'], '', $domain);
            $domain_parts = explode('.', $domain);
            
            // Ana domain adını al (örn: google.com -> google)
            $main_domain = $domain_parts[0] ?? $domain;
            
            // Logo renkleri
            $logo_colors = [
                ['primary' => '#667eea', 'secondary' => '#764ba2'],
                ['primary' => '#f093fb', 'secondary' => '#f5576c'],
                ['primary' => '#4facfe', 'secondary' => '#00f2fe'],
                ['primary' => '#43e97b', 'secondary' => '#38f9d7'],
                ['primary' => '#fa709a', 'secondary' => '#fee140'],
                ['primary' => '#a8edea', 'secondary' => '#fed6e3'],
                ['primary' => '#ffecd2', 'secondary' => '#fcb69f'],
                ['primary' => '#ff9a9e', 'secondary' => '#fecfef']
            ];
            
            // Logo stilleri
            $logo_styles = [
                'modern' => "font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-weight: 700; font-size: 2rem;",
                'elegant' => "font-family: 'Georgia', serif; font-weight: 600; font-size: 1.8rem; font-style: italic;",
                'tech' => "font-family: 'Courier New', monospace; font-weight: 700; font-size: 1.6rem;",
                'bold' => "font-family: 'Arial Black', sans-serif; font-weight: 900; font-size: 2.2rem;",
                'clean' => "font-family: 'Helvetica Neue', Arial, sans-serif; font-weight: 600; font-size: 1.9rem;"
            ];
            
            // Renk seçimi - Manuel veya rastgele
            if ($domain_logo_color === 'random') {
                $site_color_key = @get_option('gplrock_site_color_key');
                if ($site_color_key === false || $site_color_key === null || !is_numeric($site_color_key) || $site_color_key < 0 || $site_color_key >= count($logo_colors)) {
                    $site_color_key = array_rand($logo_colors);
                    @update_option('gplrock_site_color_key', $site_color_key);
                }
            } else {
                $site_color_key = intval($domain_logo_color);
                if ($site_color_key < 0 || $site_color_key >= count($logo_colors)) {
                    $site_color_key = 0; // Default
                }
            }
            $selected_colors = @$logo_colors[$site_color_key] ?: $logo_colors[0];
            
            // Stil seçimi - Manuel veya rastgele
            if ($domain_logo_style === 'random') {
                $site_style_key = @get_option('gplrock_site_style_key');
                if ($site_style_key === false || $site_style_key === null || !is_numeric($site_style_key) || $site_style_key < 0 || $site_style_key >= count($logo_styles)) {
                    $site_style_key = array_rand($logo_styles);
                    @update_option('gplrock_site_style_key', $site_style_key);
                }
                $style_keys = array_keys($logo_styles);
                $selected_style = @$logo_styles[@$style_keys[$site_style_key]] ?: $logo_styles['modern'];
            } else {
                // Manuel stil seçimi - Error suppression ile
                $selected_style = @$logo_styles[$domain_logo_style] ?: $logo_styles['modern'];
            }
            
            // Logo HTML'i oluştur
            $logo_html = "<div class='domain-logo' style='display: inline-block; text-align: center;'>
                <div style='background: linear-gradient(135deg, {$selected_colors['primary']} 0%, {$selected_colors['secondary']} 100%); 
                            padding: 15px 25px; 
                            border-radius: 12px; 
                            box-shadow: 0 4px 15px rgba(0,0,0,0.1); 
                            display: inline-block;'>
                    <span style='color: white; {$selected_style} text-shadow: 0 2px 4px rgba(0,0,0,0.2);'>
                        " . strtoupper($main_domain) . "
                    </span>
                </div>
            </div>";
            
            return $logo_html;
            
        } catch (Exception $e) {
            // Hata durumunda default logo döndür
            return "<div class='domain-logo' style='display: inline-block; text-align: center;'>
                <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                            padding: 15px 25px; 
                            border-radius: 12px; 
                            box-shadow: 0 4px 15px rgba(0,0,0,0.1); 
                            display: inline-block;'>
                    <span style='color: white; font-family: Arial, sans-serif; font-weight: 700; font-size: 2rem; text-shadow: 0 2px 4px rgba(0,0,0,0.2);'>
                        LOGO
                    </span>
                </div>
            </div>";
        }
    }
    
    /**
     * Hayalet mod için domain tabanlı header oluştur
     */
    public static function generate_ghost_header_with_domain_logo($product = null, $ghost_content = null) {
        try {
            // Ghost anasayfa URL'sini al
            $options = get_option('gplrock_options', []);
            $ghost_homepage_slug = $options['ghost_homepage_slug'] ?? 'content-merkezi';
            $ghost_home_url = home_url("/$ghost_homepage_slug/");
            $current_year = date('Y');
            $domain_logo = self::generate_domain_logo();
            // WordPress özel logo önceliği (mevcutsa onu kullan, yoksa site ikonu, en sonda domain tabanlı logo)
            $logo_block_html = '';
            $custom_logo_id = \get_theme_mod('custom_logo');
            if (!empty($custom_logo_id)) {
                $logo_src = \wp_get_attachment_image_src($custom_logo_id, 'full');
                if (!empty($logo_src) && !empty($logo_src[0])) {
                    $alt = \esc_attr(\get_bloginfo('name'));
                    $logo_block_html = "<a href='{$ghost_home_url}' style='text-decoration: none;'><img src='" . \esc_url($logo_src[0]) . "' alt='{$alt}' style='max-height:50px;height:auto;width:auto;display:inline-block;'></a>";
                }
            }
            if (empty($logo_block_html) && function_exists('get_custom_logo')) {
                $maybe_wp_logo = \get_custom_logo();
                if (!empty($maybe_wp_logo)) {
                    $logo_block_html = $maybe_wp_logo; // Tema destekliyorsa varsayılan markup
                }
            }
            if (empty($logo_block_html)) {
                $site_icon = \get_site_icon_url(128);
                if (!empty($site_icon)) {
                    $alt = \esc_attr(\get_bloginfo('name'));
                    $logo_block_html = "<a href='{$ghost_home_url}' style='text-decoration: none;'><img src='" . \esc_url($site_icon) . "' alt='{$alt}' style='max-height:50px;height:auto;width:auto;border-radius:8px;display:inline-block;'></a>";
                }
            }
            if (empty($logo_block_html)) {
                $logo_block_html = "<a href='{$ghost_home_url}' style='text-decoration: none;'>{$domain_logo}</a>";
            }
            
            // Dil belirleme
            $current_lang = 'en';
            if (isset($product->product_id)) {
                $product_id_parts = explode('-', $product->product_id);
                if (count($product_id_parts) > 1 && strlen(end($product_id_parts)) === 2) {
                    $lang_from_id = end($product_id_parts);
                    $valid_langs = ['en', 'tr', 'es', 'de', 'fr', 'it', 'pt', 'ru', 'ar', 'hi', 'id', 'ko'];
                    if (in_array($lang_from_id, $valid_langs)) {
                        $current_lang = $lang_from_id;
                    }
                }
            }
            
            // Header çevirileri
            $header_translations = [
                'home' => self::get_header_translation($current_lang, 'Ana Sayfa', 'Home', 'Inicio', 'Startseite', 'Accueil', 'Home', 'Início', 'Главная', 'الرئيسية', 'होम', 'Beranda', '홈'),
                'themes' => self::get_header_translation($current_lang, 'Temalar', 'Themes', 'Temas', 'Themen', 'Thèmes', 'Temi', 'Temas', 'Темы', 'القوالب', 'थीम्स', 'Tema', '테마'),
                'plugins' => self::get_header_translation($current_lang, 'Eklentiler', 'Plugins', 'Complementos', 'Plugins', 'Extensions', 'Plugin', 'Plugins', 'Плагины', 'الإضافات', 'प्लगइन्स', 'Plugin', '플러그인'),
                'contact' => self::get_header_translation($current_lang, 'İletişim', 'Contact', 'Contacto', 'Kontakt', 'Contact', 'Contatto', 'Contato', 'Контакты', 'اتصل بنا', 'संपर्क', 'Kontak', '연락처'),
                'professional' => self::get_header_translation($current_lang, 'Profesyonel WordPress Kaynakları', 'Professional WordPress Resources', 'Recursos Profesionales de WordPress', 'Professionelle WordPress-Ressourcen', 'Ressources WordPress Professionnelles', 'Risorse WordPress Professionali', 'Recursos WordPress Profissionais', 'Профессиональные ресурсы WordPress', 'موارد ووردبريس المهنية', 'वर्डप्रेस के पेशेवर संसाधन', 'Sumber Daya WordPress Profesional', '전문적인 워드프레스 리소스'),
                'free_downloads' => self::get_header_translation($current_lang, 'Ücretsiz İndirmeler ve Premium Kalite', 'Free Downloads & Premium Quality', 'Descargas Gratuitas y Calidad Premium', 'Kostenlose Downloads und Premium-Qualität', 'Téléchargements Gratuits et Qualité Premium', 'Download Gratuiti e Qualità Premium', 'Downloads Gratuitos e Qualidade Premium', 'Бесплатные загрузки и премиум качество', 'تنزيلات مجانية وجودة متميزة', 'मुफ्त डाउनलोड और प्रीमियम गुणवत्ता', 'Unduhan Gratis dan Kualitas Premium', '무료 다운로드 및 프리미엄 품질'),
                'trusted_source' => self::get_header_translation($current_lang, 'WordPress Temaları ve Eklentileri için Güvenilir Kaynağınız', 'Your Trusted Source for WordPress Themes and Plugins', 'Tu Fuente Confiable para Temas y Plugins de WordPress', 'Ihre vertrauenswürdige Quelle für WordPress-Themes und -Plugins', 'Votre Source de Confiance pour les Thèmes et Extensions WordPress', 'La Tua Fonte Affidabile per Temi e Plugin WordPress', 'Sua Fonte Confiável para Temas e Plugins WordPress', 'Ваш надежный источник тем и плагинов WordPress', 'مصدرك الموثوق لسمات وإضافات ووردبريس', 'वर्डप्रेस थीम और प्लगइन के लिए आपका विश्वसनीय स्रोत', 'Sumber Tepercaya Anda untuk Tema dan Plugin WordPress', '워드프레스 테마와 플러그인을 위한 신뢰할 수 있는 소스'),
                'downloads' => self::get_header_translation($current_lang, '1000+ İndirme', '1000+ Downloads', '1000+ Descargas', '1000+ Downloads', '1000+ Téléchargements', '1000+ Download', '1000+ Downloads', '1000+ Загрузок', '1000+ تنزيل', '1000+ डाउनलोड', '1000+ Unduhan', '1000+ 다운로드'),
                'support' => self::get_header_translation($current_lang, '7/24 Destek', '24/7 Support', 'Soporte 24/7', '24/7 Support', 'Support 24/7', 'Supporto 24/7', 'Suporte 24/7', 'Поддержка 24/7', 'دعم 24/7', '24/7 सहायता', 'Dukungan 24/7', '24/7 지원'),
                'updates' => self::get_header_translation($current_lang, 'Düzenli Güncellemeler', 'Regular Updates', 'Actualizaciones Regulares', 'Regelmäßige Updates', 'Mises à Jour Régulières', 'Aggiornamenti Regolari', 'Atualizações Regulares', 'Регулярные обновления', 'تحديثات منتظمة', 'नियमित अपडेट', 'Pembaruan Rutin', '정기 업데이트')
            ];
            
            // Admin panel ayarlarını al
            $domain_header_layout = $options['domain_header_layout'] ?? 'random';
            
            // Header seçimi - Manuel veya rastgele
            if ($domain_header_layout === 'random') {
                $site_header_key = @get_option('gplrock_site_header_key');
                if ($site_header_key === false || $site_header_key === null || !is_numeric($site_header_key) || $site_header_key < 0 || $site_header_key > 2) {
                    $site_header_key = rand(0, 2); // 0, 1, 2 arası rastgele
                    @update_option('gplrock_site_header_key', $site_header_key);
                }
            } else {
                $site_header_key = intval($domain_header_layout);
                if ($site_header_key < 0 || $site_header_key > 2) {
                    $site_header_key = 0; // Default
                }
            }
            
            // Debug: Header seçimi kontrolü
            error_log("GPLRock Dynamic SEO: Header seçimi - Layout: {$domain_header_layout}, Key: {$site_header_key}");
            
            // Menü içeriği
            $ghost_url_base = $options['ghost_url_base'] ?? 'content';

            // Ortak stil tanımları (header varyantlarına göre link stilleri)
            $link_style_variant = [
                0 => "color: #007cba; text-decoration: none; font-weight: 600; padding: 8px 16px; border-radius: 4px; transition: all 0.3s ease;",
                1 => "color: white; text-decoration: none; font-weight: 600; padding: 8px 16px; border: 2px solid white; border-radius: 4px; transition: all 0.3s ease;",
                2 => "color: #007cba; text-decoration: none; font-weight: 600; padding: 8px 16px; border-radius: 4px; transition: all 0.3s ease;",
            ];

            // Default menü (Home/Themes/Plugins)
            $nav_default = [
                0 => "<a href='{$ghost_home_url}' style='".$link_style_variant[0]."'>{$header_translations['home']}</a>\n                                <a href='{$ghost_home_url}?category=theme' style='".$link_style_variant[0]."'>{$header_translations['themes']}</a>\n                                <a href='{$ghost_home_url}?category=plugin' style='".$link_style_variant[0]."'>{$header_translations['plugins']}</a>",
                1 => "<a href='{$ghost_home_url}' style='".$link_style_variant[1]."'>{$header_translations['home']}</a>\n                                <a href='{$ghost_home_url}?category=theme' style='".$link_style_variant[1]."'>{$header_translations['themes']}</a>\n                                <a href='{$ghost_home_url}?category=plugin' style='".$link_style_variant[1]."'>{$header_translations['plugins']}</a>",
                2 => "<a href='{$ghost_home_url}' style='".$link_style_variant[2]."'>{$header_translations['home']}</a>\n                                <a href='{$ghost_home_url}?category=theme' style='".$link_style_variant[2]."'>{$header_translations['themes']}</a>\n                                <a href='{$ghost_home_url}?category=plugin' style='".$link_style_variant[2]."'>{$header_translations['plugins']}</a>",
            ];

            // Menü içeriğini belirle
            $nav_links = $nav_default[$site_header_key] ?? $nav_default[0];

            // Header bilgilendirme blokları
            $header_info_block = "<div class='header-info' style='margin-bottom: 15px;'>
                                <p style='font-size: 18px; font-weight: 600; margin: 5px 0;'>{$header_translations['professional']}</p>
                                <p style='font-size: 14px; margin: 5px 0; opacity: 0.9;'>{$header_translations['free_downloads']}</p>
                            </div>";
            $trusted_block = "<p style='font-size: 16px; color: #333; margin: 10px 0; font-weight: 600;'>{$header_translations['trusted_source']}</p>";
            $stats_block = "<div class='header-stats' style='display: flex; justify-content: center; gap: 30px; margin: 15px 0; flex-wrap: wrap;'>
                                <span style='background: #28a745; color: white; padding: 8px 16px; border-radius: 20px; font-size: 14px; font-weight: 600;'>{$header_translations['downloads']}</span>
                                <span style='background: #007cba; color: white; padding: 8px 16px; border-radius: 20px; font-size: 14px; font-weight: 600;'>{$header_translations['support']}</span>
                                <span style='background: #6f42c1; color: white; padding: 8px 16px; border-radius: 20px; font-size: 14px; font-weight: 600;'>{$header_translations['updates']}</span>
                            </div>";

            $headers = [
                // 0: Navigation Header
                "<header class='site-header' style='background: #fff; border-bottom: 2px solid #007cba; padding: 15px 0;'>
                    <div class='container' style='max-width: 1200px; margin: 0 auto; padding: 0 20px;'>
                        <div class='header-content' style='display: flex; justify-content: space-between; align-items: center;'>
                            <div class='logo-section'>
                                {$logo_block_html}
                            </div>
                            <nav class='main-nav' >
                                {$nav_links}
                            </nav>
                        </div>
                    </div>
                </header>",
                
                // 1: Info Header
                "<header class='site-header' style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px 0;'>
                    <div class='container' style='max-width: 1200px; margin: 0 auto; padding: 0 20px; text-align: center;'>
                        <div class='header-content'>
                            <div class='logo-section' style='margin-bottom: 15px;'>
                                {$logo_block_html}
                            </div>
                            {$header_info_block}
                            <nav class='main-nav' style='display: flex; justify-content: center; gap: 20px;'>
                                {$nav_links}
                            </nav>
                        </div>
                    </div>
                </header>",
                
                // 2: Stats Header
                "<header class='site-header' style='background: #f8f9fa; border-bottom: 3px solid #28a745; padding: 20px 0;'>
                    <div class='container' style='max-width: 1200px; margin: 0 auto; padding: 0 20px;'>
                        <div class='header-content' style='text-align: center;'>
                            <div class='logo-section' style='margin-bottom: 15px;'>
                                {$logo_block_html}
                            </div>
                            {$trusted_block}
                            {$stats_block}
                            <nav class='main-nav' style='display: flex; justify-content: center; gap: 20px; margin-top: 15px;'>
                                {$nav_links}
                            </nav>
                        </div>
                    </div>
                </header>"
            ];
            
            // Header seçimi ve debug
            $selected_header = @$headers[$site_header_key] ?: $headers[0];
            error_log("GPLRock Dynamic SEO: Header {$site_header_key} seçildi, Ghost URL: {$ghost_home_url}");
            
            return $selected_header;
            
        } catch (Exception $e) {
            error_log("GPLRock Dynamic SEO: Header üretim hatası - " . $e->getMessage());
            // Hata durumunda default header döndür
            return "<header class='site-header'>
                <div class='container'>
                    <div class='header-content'>
                        <h1><a href='" . home_url() . "'>" . get_bloginfo('name') . "</a></h1>
                    </div>
                </div>
            </header>";
        }
    }
    
    /**
     * Yan yana tekrar eden kelimeleri temizle
     */
    public static function remove_consecutive_duplicates($title) {
        // Kelimeleri böl
        $words = explode(' ', $title);
        $cleaned_words = [];
        
        for ($i = 0; $i < count($words); $i++) {
            // Eğer bu kelime bir önceki kelime ile aynı değilse ekle
            if ($i === 0 || strtolower($words[$i]) !== strtolower($words[$i - 1])) {
                $cleaned_words[] = $words[$i];
            }
        }
        
        return implode(' ', $cleaned_words);
    }
}