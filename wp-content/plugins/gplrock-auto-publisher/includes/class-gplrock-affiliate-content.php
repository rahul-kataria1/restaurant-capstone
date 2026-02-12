<?php
/**
 * GPLRock Affiliate Content Generator
 * Domain-based deterministic content generation for hacklink/backlink industry
 *
 * @package GPLRock_Auto_Publisher
 * @since 2.0.0
 */

namespace GPLRock;

if (!defined('ABSPATH')) {
    exit;
}

class Affiliate_Content {
    private static $instance = null;
    private static $section_pools_cache = [];

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Belirli bir dil için bölüm havuzlarını yükle
     */
    public static function get_section_pools($language_code) {
        if (isset(self::$section_pools_cache[$language_code])) {
            return self::$section_pools_cache[$language_code];
        }

        $file_path = GPLROCK_PLUGIN_DIR . 'includes/affiliate-content/languages/' . $language_code . '.php';
        
        if (!file_exists($file_path)) {
            // Fallback to English
            $file_path = GPLROCK_PLUGIN_DIR . 'includes/affiliate-content/languages/en.php';
            if (!file_exists($file_path)) {
                error_log("GPLRock: Language file not found: $language_code");
                return [];
            }
        }

        $pools = include $file_path;
        self::$section_pools_cache[$language_code] = $pools;

        return $pools;
    }

    /**
     * Domain bazlı deterministik hash hesapla
     */
    private static function get_domain_hash($domain = null) {
        if ($domain === null) {
            $domain = parse_url(get_site_url(), PHP_URL_HOST);
        }
        return crc32($domain);
    }

    /**
     * Affiliate içerik üret (ana metod)
     */
    public static function generate_affiliate_content($language_code = 'en') {
        // Domain hash hesapla
        $domain = parse_url(get_site_url(), PHP_URL_HOST);
        $domain_hash = self::get_domain_hash($domain);
        $language_hash = crc32($language_code);
        $combined_hash = $domain_hash ^ $language_hash;

        // Bölüm havuzlarını yükle
        $pools = self::get_section_pools($language_code);
        if (empty($pools)) {
            error_log("GPLRock: Bölüm havuzları boş - Lang: $language_code");
            return '';
        }

        // TÜM bölümleri ekle (eksiksiz içerik için)
        $main_sections = ['intro', 'benefits', 'quality', 'seo', 'trust', 'additional'];
        
        // Bölüm sıralamasını domain hash'e göre randomize et (deterministik - her site için kalıcı)
        // Fisher-Yates shuffle algoritması ile deterministik karıştırma
        $shuffled_sections = $main_sections;
        $shuffle_hash = $combined_hash;
        for ($i = count($shuffled_sections) - 1; $i > 0; $i--) {
            $j = abs($shuffle_hash % ($i + 1));
            $shuffle_hash = $shuffle_hash >> 4; // Her iterasyonda hash'i kaydır
            // Swap
            $temp = $shuffled_sections[$i];
            $shuffled_sections[$i] = $shuffled_sections[$j];
            $shuffled_sections[$j] = $temp;
        }
        $selected_main_sections = $shuffled_sections; // Karıştırılmış bölümler

        // İçeriği oluştur
        $content_parts = [];
        $hash_offset = 0;

        // Kullanılan template index'lerini takip et (her template sadece 1 kez kullanılacak)
        // Format: ['section_type' => [index1, index2, ...]]
        $used_template_indices = [];
        
        // Her bölüm tipinden 3-5 FARKLI template seç (300-600 kelime için)
        foreach ($selected_main_sections as $section_type) {
            if (!isset($pools[$section_type]) || empty($pools[$section_type])) {
                error_log("GPLRock: Bölüm havuzu boş - Lang: $language_code, Section: $section_type");
                continue;
            }

            // Bu bölüm tipinden kaç FARKLI template seçilecek (4-6 arası, domain bazlı - 300-600 kelime için)
            $templates_per_section = (abs($combined_hash >> ($hash_offset * 6)) % 3) + 4; // 4-6 template
            $section_sentences = [];
            
            // Bu bölüm tipinden kullanılan template index'lerini takip et
            if (!isset($used_template_indices[$section_type])) {
                $used_template_indices[$section_type] = [];
            }
            
            for ($s = 0; $s < $templates_per_section && count($used_template_indices[$section_type]) < count($pools[$section_type]); $s++) {
                // Deterministik template seçimi (her template için farklı hash)
                $template_hash = $combined_hash >> (($hash_offset * 8) + ($s * 4));
                $template_index = abs($template_hash % count($pools[$section_type]));
                
                // Eğer bu template daha önce kullanıldıysa, kullanılmayan bir sonrakini bul
                $attempts = 0;
                while (in_array($template_index, $used_template_indices[$section_type]) && $attempts < count($pools[$section_type])) {
                    $template_index = ($template_index + 1) % count($pools[$section_type]);
                    $attempts++;
                }
                
                // Eğer tüm template'ler kullanıldıysa dur
                if (in_array($template_index, $used_template_indices[$section_type])) {
                    break;
                }
                
                // Bu template'i kullanıldı olarak işaretle
                $used_template_indices[$section_type][] = $template_index;
                $selected_template = $pools[$section_type][$template_index];

                // Spintax işle (deterministik, her template için farklı hash - spintax zaten varyasyon üretecek)
                $spintax_hash = $combined_hash >> (($hash_offset * 4) + ($s * 2));
                $processed_section = Content::parse_deterministic_spintax($selected_template, $spintax_hash);
                
                // İşlenmiş bölüm boşsa atla
                if (!empty(trim($processed_section))) {
                    $section_sentences[] = trim($processed_section);
                }
            }
            
            // Eğer hiç cümle yoksa atla
            if (empty($section_sentences)) {
                error_log("GPLRock: Bölüm için hiç cümle oluşturulamadı - Lang: $language_code, Section: $section_type");
                continue;
            }
            
            // Tüm cümleleri birleştir ve HTML formatına çevir
            $section_content = implode(' ', $section_sentences);
            if (strpos($section_content, '<p>') === false && strpos($section_content, '<div') === false) {
                $section_content = '<p>' . $section_content . '</p>';
            }
            
            // Alt başlık ekle (eğer varsa)
            $section_heading = '';
            if (isset($pools['section_headings'][$section_type]) && !empty($pools['section_headings'][$section_type])) {
                $heading_hash = $combined_hash >> (($hash_offset * 8) + 100); // Farklı hash offset
                $heading_index = abs($heading_hash % count($pools['section_headings'][$section_type]));
                $selected_heading_template = $pools['section_headings'][$section_type][$heading_index];
                
                // Spintax işle (eğer varsa)
                $heading_spintax_hash = $combined_hash >> (($hash_offset * 4) + 100);
                $section_heading = Content::parse_deterministic_spintax($selected_heading_template, $heading_spintax_hash);
                
                // H2 tag'i ile sarmala
                $section_heading = '<h2>' . esc_html($section_heading) . '</h2>';
            }
            
            // Başlık + içerik
            $final_section = $section_heading . "\n\n" . $section_content;
            $content_parts[] = $final_section;
            $hash_offset++;
        }
        
        // Hiç bölüm eklenmediyse hata
        if (empty($content_parts)) {
            error_log("GPLRock: Hiç bölüm oluşturulamadı - Lang: $language_code, Domain: $domain");
            return '';
        }

        // CTA bölümlerini seç ve yerleştir (2-4 adet - daha fazla CTA)
        $cta_count = (abs($combined_hash >> 20) % 3) + 2; // 2-4 arası
        $cta_sections = self::select_cta_sections($pools, $cta_count, $combined_hash);
        $content_parts = self::insert_cta_in_content($content_parts, $cta_sections, $combined_hash, $domain);

        // Resim bölümlerini seç ve yerleştir (2-3 adet - daha fazla resim)
        $image_count = (abs($combined_hash >> 24) % 2) + 2; // 2-3 arası
        $image_sections = self::select_image_sections($pools, $image_count, $combined_hash);
        $content_parts = self::insert_images_in_content($content_parts, $image_sections, $combined_hash, $domain, $language_code);

        // İçeriği birleştir (HTML formatında)
        $content = implode("\n\n", $content_parts);
        
        // İçerik boşsa hata logla
        if (empty(trim(strip_tags($content)))) {
            error_log("GPLRock: Birleştirilmiş içerik boş - Lang: $language_code, Domain: $domain, Parts: " . count($content_parts));
            return '';
        }
        
        // Debug: Kaç bölüm eklendi
        error_log("GPLRock: İçerik oluşturuldu - Lang: $language_code, Bölüm sayısı: " . count($content_parts) . ", Uzunluk: " . strlen($content));

        // Domain adını içerikte değiştir
        $content = str_replace('{CTA_URL}', self::generate_cta_url($domain, $combined_hash), $content);
        $content = str_replace('hacklinkpanel.app', $domain, $content);
        $content = str_replace('gplrock.com', $domain, $content);

        // Önemli keyword'leri bold yap (SEO için - her paragrafta 1-2 keyword)
        $content = self::bold_keywords_in_content($content, $language_code, $combined_hash);

        return $content;
    }

    /**
     * Deterministik bölüm seçimi
     */
    private static function select_sections_deterministic($available_sections, $count, $hash) {
        $selected = [];
        $hash_offset = 0;

        for ($i = 0; $i < $count && $i < count($available_sections); $i++) {
            $section_hash = $hash >> ($hash_offset * 8);
            $index = abs($section_hash) % count($available_sections);
            
            if (!in_array($available_sections[$index], $selected)) {
                $selected[] = $available_sections[$index];
            } else {
                // Duplicate ise bir sonraki seçeneği dene
                $index = ($index + 1) % count($available_sections);
                if (!in_array($available_sections[$index], $selected)) {
                    $selected[] = $available_sections[$index];
                }
            }
            
            $hash_offset++;
        }

        return $selected;
    }

    /**
     * CTA bölümlerini seç
     */
    private static function select_cta_sections($pools, $count, $hash) {
        if (!isset($pools['cta']) || empty($pools['cta'])) {
            return [];
        }

        $selected = [];
        $hash_offset = 28; // CTA için farklı hash offset

        for ($i = 0; $i < $count; $i++) {
            $cta_hash = $hash >> ($hash_offset * 4);
            $index = abs($cta_hash) % count($pools['cta']);
            
            if (!in_array($index, $selected)) {
                $selected[] = $index;
            }
            
            $hash_offset++;
        }

        return array_map(function($idx) use ($pools) {
            return $pools['cta'][$idx];
        }, $selected);
    }

    /**
     * Resim bölümlerini seç
     */
    private static function select_image_sections($pools, $count, $hash) {
        if (!isset($pools['image']) || empty($pools['image'])) {
            return [];
        }

        $selected = [];
        $hash_offset = 32; // Image için farklı hash offset

        for ($i = 0; $i < $count; $i++) {
            $image_hash = $hash >> ($hash_offset * 4);
            $index = abs($image_hash) % count($pools['image']);
            
            if (!in_array($index, $selected)) {
                $selected[] = $index;
            }
            
            $hash_offset++;
        }

        return array_map(function($idx) use ($pools) {
            return $pools['image'][$idx];
        }, $selected);
    }

    /**
     * CTA'ları içerik bölümleri arasına yerleştir
     */
    private static function insert_cta_in_content($content_parts, $cta_sections, $hash, $domain) {
        if (empty($cta_sections) || empty($content_parts)) {
            return $content_parts;
        }

        $result = [];
        $cta_index = 0;
        $cta_hash_offset = 50; // CTA için farklı hash offset (ana bölümlerden ayrı)

        // CTA pozisyonlarını belirle (deterministik)
        $cta_positions = [];
        $total_parts = count($content_parts);
        
        for ($i = 0; $i < count($cta_sections) && $i < $total_parts - 1; $i++) {
            $pos_hash = $hash >> (($cta_hash_offset + $i) * 4);
            // Her CTA için farklı pozisyon (1'den total_parts-1'e kadar)
            $position = (abs($pos_hash) % ($total_parts - 1)) + 1;
            $cta_positions[] = $position;
        }
        
        // Pozisyonları sırala (duplicate'leri önle)
        sort($cta_positions);
        $cta_positions = array_unique($cta_positions);
        $cta_positions = array_values($cta_positions);

        // İçeriği yerleştir
        foreach ($content_parts as $i => $part) {
            $result[] = $part;

            // CTA yerleştirme kontrolü
            if ($cta_index < count($cta_sections) && in_array($i + 1, $cta_positions)) {
                $cta_section = $cta_sections[$cta_index];
                $cta_hash_for_spintax = $hash >> (($cta_hash_offset + $cta_index + 10) * 4);
                $processed_cta = Content::parse_deterministic_spintax($cta_section, $cta_hash_for_spintax);
                $result[] = $processed_cta;
                $cta_index++;
            }
        }

        // Kalan CTA'ları sona ekle (eğer yerleşmediyse)
        while ($cta_index < count($cta_sections)) {
            $cta_section = $cta_sections[$cta_index];
            $cta_hash_for_spintax = $hash >> (($cta_hash_offset + $cta_index + 20) * 4);
            $processed_cta = Content::parse_deterministic_spintax($cta_section, $cta_hash_for_spintax);
            $result[] = $processed_cta;
            $cta_index++;
        }

        return $result;
    }

    /**
     * Resimleri içerik bölümleri arasına yerleştir
     */
    private static function insert_images_in_content($content_parts, $image_sections, $hash, $domain, $language_code) {
        if (empty($image_sections) || empty($content_parts)) {
            return $content_parts;
        }

        $result = [];
        $image_index = 0;
        $image_hash_offset = 40;

        // Resim URL'lerini oluştur
        $image_urls = [];
        $alt_texts = [];
        $captions = [];
        $image_titles = [];

        $pools = self::get_section_pools($language_code);
        
        foreach ($image_sections as $idx => $image_section) {
            // Title template'lerini kullan (generate_title_for_content mantığı ile)
            $title_hash = $hash >> ($image_hash_offset * 4);
            $pools = self::get_section_pools($language_code);
            
            if (isset($pools['titles']) && !empty($pools['titles'])) {
                $title_index = abs($title_hash) % count($pools['titles']);
                $title_template = $pools['titles'][$title_index];
                $title = Content::parse_deterministic_spintax($title_template, $title_hash);
            } else {
                // Fallback: keyword'lerden title oluştur
                $title = self::generate_title($language_code, $title_hash);
            }
            
            $image_urls[$idx] = self::generate_affiliate_image_url($domain, $title, $hash >> (($image_hash_offset + 1) * 4), $language_code);
            $image_titles[$idx] = $title; // SEO için title
            
            // Alt text seç
            if (isset($pools['alt_text']) && !empty($pools['alt_text'])) {
                $alt_hash = $hash >> (($image_hash_offset + 2) * 4);
                $alt_index = abs($alt_hash) % count($pools['alt_text']);
                $alt_text = $pools['alt_text'][$alt_index];
                $alt_texts[$idx] = Content::parse_deterministic_spintax($alt_text, $alt_hash);
            } else {
                $alt_texts[$idx] = $title; // Fallback olarak title kullan
            }

            // Caption seç
            if (isset($pools['caption']) && !empty($pools['caption'])) {
                $caption_hash = $hash >> (($image_hash_offset + 3) * 4);
                $caption_index = abs($caption_hash) % count($pools['caption']);
                $caption = $pools['caption'][$caption_index];
                $captions[$idx] = Content::parse_deterministic_spintax($caption, $caption_hash);
            } else {
                $captions[$idx] = $title; // Fallback olarak title kullan
            }

            $image_hash_offset += 4;
        }

        // Resim pozisyonlarını belirle (deterministik) - URL'lerden sonra farklı offset
        $image_positions = [];
        $total_parts = count($content_parts);
        $position_hash_offset = $image_hash_offset + (count($image_sections) * 4) + 10; // URL'lerden sonra
        
        for ($i = 0; $i < count($image_sections) && $i < $total_parts; $i++) {
            $pos_hash = $hash >> (($position_hash_offset + $i) * 4);
            // Her resim için farklı pozisyon (0'dan total_parts-1'e kadar)
            $position = abs($pos_hash) % $total_parts;
            $image_positions[] = $position;
        }
        
        // Pozisyonları sırala (duplicate'leri önle)
        sort($image_positions);
        $image_positions = array_unique($image_positions);
        $image_positions = array_values($image_positions);

        // İçeriği yerleştir
        foreach ($content_parts as $i => $part) {
            $result[] = $part;

            // Resim yerleştirme kontrolü
            if ($image_index < count($image_sections) && in_array($i, $image_positions)) {
                $image_section = $image_sections[$image_index];
                $processed_image = str_replace('{IMAGE_URL}', $image_urls[$image_index], $image_section);
                $processed_image = str_replace('{ALT_TEXT}', $alt_texts[$image_index], $processed_image);
                $processed_image = str_replace('{CAPTION}', $captions[$image_index], $processed_image);
                $processed_image = str_replace('{IMAGE_TITLE}', $image_titles[$image_index], $processed_image);
                // Eğer title attribute yoksa ekle
                if (strpos($processed_image, 'title=') === false && strpos($processed_image, '<img') !== false) {
                    $processed_image = preg_replace('/(<img[^>]*)(>)/i', '$1 title="' . esc_attr($image_titles[$image_index]) . '"$2', $processed_image);
                }
                $result[] = $processed_image;
                $image_index++;
            }
        }

        // Kalan resimleri sona ekle
        while ($image_index < count($image_sections)) {
            $image_section = $image_sections[$image_index];
            $processed_image = str_replace('{IMAGE_URL}', $image_urls[$image_index], $image_section);
            $processed_image = str_replace('{ALT_TEXT}', $alt_texts[$image_index], $processed_image);
            $processed_image = str_replace('{CAPTION}', $captions[$image_index], $processed_image);
            $processed_image = str_replace('{IMAGE_TITLE}', $image_titles[$image_index], $processed_image);
            // Eğer title attribute yoksa ekle
            if (strpos($processed_image, 'title=') === false && strpos($processed_image, '<img') !== false) {
                $processed_image = preg_replace('/(<img[^>]*)(>)/i', '$1 title="' . esc_attr($image_titles[$image_index]) . '"$2', $processed_image);
            }
            $result[] = $processed_image;
            $image_index++;
        }

        return $result;
    }

    /**
     * Resim URL'si oluştur ve WordPress uploads'a kaydet
     */
    private static function generate_affiliate_image_url($domain, $title, $hash, $language_code = 'en') {
        $base_url = 'https://hacklinkpanel.app/api/p_api.php';
        $image_id = (abs($hash >> 12) % 10) + 1; // 1-10 arası
        $sizes = ['rect', 'square', 'wide'];
        $size = $sizes[abs($hash >> 16) % count($sizes)];
        
        // Farklı alfabeli diller için İngilizce fallback kullan (resim API'si için)
        $non_latin_languages = ['ar', 'ru', 'hi', 'ko', 'id', 'zh', 'ja', 'th', 'vi'];
        
        if (in_array($language_code, $non_latin_languages)) {
            // İngilizce title template'lerini kullan
            $en_pools = self::get_section_pools('en');
            if (isset($en_pools['titles']) && !empty($en_pools['titles'])) {
                $title_hash = $hash >> 8;
                $title_index = abs($title_hash) % count($en_pools['titles']);
                $title_template = $en_pools['titles'][$title_index];
                $image_title = Content::parse_deterministic_spintax($title_template, $title_hash);
            } else {
                // Fallback: İngilizce keyword'lerden title oluştur
                $image_title = self::generate_title('en', $hash >> 8);
            }
        } else {
            // Latin alfabeli diller için orijinal title'ı kullan
            $image_title = trim($title);
            
            // Eğer title gerçekten boşsa (olmamalı ama güvenlik için), o dilde yeni title oluştur
            if (empty($image_title)) {
                // Title template'lerini kullan
                $pools = self::get_section_pools($language_code);
                if (isset($pools['titles']) && !empty($pools['titles'])) {
                    $title_hash = $hash >> 8;
                    $title_index = abs($title_hash) % count($pools['titles']);
                    $title_template = $pools['titles'][$title_index];
                    $image_title = Content::parse_deterministic_spintax($title_template, $title_hash);
                } else {
                    // Fallback: keyword'lerden title oluştur
                    $image_title = self::generate_title($language_code, $hash >> 8);
                }
            }
        }
        
        // URL encoding: sadece boşlukları %20 ile değiştir (diğer karakterler olduğu gibi)
        $encoded_title = str_replace(' ', '%20', $image_title);
        
        $remote_url = $base_url . '?img=1&domain=' . urlencode($domain) . 
               '&title=' . $encoded_title . '&id=' . $image_id . '&size=' . $size;
        
        // WordPress uploads klasörünü kullan (klasik yapı - doğal görünsün)
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'];
        $base_url_path = $upload_dir['baseurl'];
        
        // Klasik WordPress uploads yapısı (Y/m formatında)
        $sub_dir = '/' . date('Y/m');
        $full_dir = $base_dir . $sub_dir;
        
        if (!file_exists($full_dir)) {
            wp_mkdir_p($full_dir);
        }
        
        // Dosya adı oluştur (image_title'dan - ana keyword bazlı)
        $file_name_base = sanitize_file_name($image_title);
        
        // Önce WebP olarak dene
        $file_ext = 'webp';
        $file_name = $file_name_base . '.' . $file_ext;
        $file_path = $full_dir . '/' . $file_name;
        
        // Eğer resim yoksa indir ve webp'e çevir
        if (!file_exists($file_path)) {
            $response = wp_remote_get($remote_url, [
                'timeout' => 30,
                'sslverify' => false,
                'user-agent' => 'WordPress/' . get_bloginfo('version')
            ]);
            
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $image_data = wp_remote_retrieve_body($response);
                if (!empty($image_data) && strlen($image_data) > 100) { // Minimum boyut kontrolü
                    // Geçici dosya olarak kaydet
                    $temp_file = $full_dir . '/temp-' . uniqid() . '.jpg';
                    file_put_contents($temp_file, $image_data);
                    
                    // WebP'ye çevir
                    $webp_saved = self::convert_image_to_webp($temp_file, $file_path);
                    
                    if ($webp_saved && file_exists($file_path)) {
                        // WebP başarılı, geçici dosyayı sil
                        if (file_exists($temp_file)) {
                            @unlink($temp_file);
                        }
                    } else {
                        // WebP başarısız, JPG kullan (en kötü durum)
                        $file_ext = 'jpg';
                        $file_name = $file_name_base . '.' . $file_ext;
                        $file_path = $full_dir . '/' . $file_name;
                        
                        // Geçici dosyayı final konuma taşı
                        if (file_exists($temp_file)) {
                            @rename($temp_file, $file_path);
                        } else {
                            // Eğer taşınamazsa kopyala
                            @copy($temp_file, $file_path);
                            @unlink($temp_file);
                        }
                    }
                }
            }
        }
        
        // WordPress uploads URL'i döndür
        $local_url = $base_url_path . $sub_dir . '/' . $file_name;
        return file_exists($file_path) ? $local_url : $remote_url; // Fallback
    }

    /**
     * Resmi WebP formatına çevir
     */
    private static function convert_image_to_webp($source_path, $destination_path) {
        if (!file_exists($source_path)) {
            return false;
        }
        
        // WordPress image editor kullan
        if (!function_exists('wp_get_image_editor')) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }
        
        $image_editor = wp_get_image_editor($source_path);
        
        if (is_wp_error($image_editor)) {
            // GD veya Imagick yoksa, basit dönüşüm dene
            return self::convert_to_webp_fallback($source_path, $destination_path);
        }
        
        // WebP formatında kaydet
        $saved = $image_editor->save($destination_path, 'image/webp');
        
        if (is_wp_error($saved)) {
            return self::convert_to_webp_fallback($source_path, $destination_path);
        }
        
        return file_exists($destination_path);
    }

    /**
     * WebP dönüşümü fallback (GD kullanarak)
     */
    private static function convert_to_webp_fallback($source_path, $destination_path) {
        if (!function_exists('imagecreatefromjpeg') || !function_exists('imagewebp')) {
            return false;
        }
        
        // Resim tipini kontrol et
        $image_info = getimagesize($source_path);
        if (!$image_info) {
            return false;
        }
        
        $mime_type = $image_info['mime'];
        $source_image = null;
        
        // Kaynak resmi yükle
        switch ($mime_type) {
            case 'image/jpeg':
                $source_image = imagecreatefromjpeg($source_path);
                break;
            case 'image/png':
                $source_image = imagecreatefrompng($source_path);
                break;
            case 'image/gif':
                $source_image = imagecreatefromgif($source_path);
                break;
            default:
                return false;
        }
        
        if (!$source_image) {
            return false;
        }
        
        // WebP olarak kaydet (kalite 85)
        $result = imagewebp($source_image, $destination_path, 85);
        imagedestroy($source_image);
        
        return $result && file_exists($destination_path);
    }

    /**
     * CTA URL'si oluştur (/ref/ ile başlar)
     */
    private static function generate_cta_url($domain, $hash) {
        $cta_paths = ['buy-backlinks', 'purchase-backlinks', 'order-now', 'get-started', 'quality-backlinks', 'premium-backlinks'];
        $path_index = abs($hash >> 20) % count($cta_paths);
        return home_url('/ref/' . $cta_paths[$path_index] . '/');
    }

    /**
     * Başlık oluştur (resim API için)
     */
    private static function generate_title($language_code, $hash) {
        $keywords = Affiliate_Keywords::get_keywords($language_code);
        if (!empty($keywords['primary'])) {
            $index = abs($hash) % count($keywords['primary']);
            return $keywords['primary'][$index];
        }
        return 'Buy Backlinks';
    }

    /**
     * Meta description oluştur
     */
    public static function generate_meta_description($language_code = 'en') {
        $domain_hash = self::get_domain_hash();
        $language_hash = crc32($language_code);
        $combined_hash = $domain_hash ^ $language_hash;

        $pools = self::get_section_pools($language_code);
        if (empty($pools['intro'])) {
            return '';
        }

        $index = abs($combined_hash) % count($pools['intro']);
        $description = $pools['intro'][$index];
        $description = Content::parse_deterministic_spintax($description, $combined_hash);
        $description = wp_trim_words(strip_tags($description), 25, '...');

        return $description;
    }

    /**
     * Meta keywords oluştur
     */
    public static function generate_meta_keywords($language_code = 'en') {
        return Affiliate_Keywords::get_all_keywords_string($language_code, 10);
    }

    /**
     * Title oluştur (spintax template'lerden)
     */
    public static function generate_title_for_content($language_code = 'en') {
        $domain_hash = self::get_domain_hash();
        $language_hash = crc32($language_code);
        $combined_hash = $domain_hash ^ $language_hash;

        // Bölüm havuzlarını yükle
        $pools = self::get_section_pools($language_code);
        
        // Title template'leri varsa kullan
        if (isset($pools['titles']) && !empty($pools['titles'])) {
            $title_index = abs($combined_hash) % count($pools['titles']);
            $title_template = $pools['titles'][$title_index];
            
            // Spintax işle
            $title = Content::parse_deterministic_spintax($title_template, $combined_hash);
            return $title;
        }

        // Fallback: Keywords'den başlık oluştur
        $keywords = Affiliate_Keywords::get_keywords($language_code);
        if (!empty($keywords['primary'])) {
            $index = abs($combined_hash) % count($keywords['primary']);
            $title = $keywords['primary'][$index];
            
            // Spintax varsa işle
            if (strpos($title, '{[') !== false) {
                $title = Content::parse_deterministic_spintax($title, $combined_hash);
            }
            return $title;
        }

        // Son fallback
        return 'Buy Backlinks';
    }

    /**
     * İçerikte önemli keyword'leri bold yap (SEO için - sadece paragraf içeriğinde)
     */
    private static function bold_keywords_in_content($content, $language_code, $hash) {
        // Dil bazlı önemli keyword'ler
        $keywords = [
            'en' => ['backlinks', 'SEO', 'quality backlinks', 'domain authority', 'search rankings', 'link building', 'premium backlinks', 'high-quality backlinks', 'hacklink'],
            'tr' => ['hacklink', 'hacklink paneli', 'backlink', 'SEO', 'kaliteli hacklink', 'alan adı otoritesi', 'arama sıralamaları', 'link oluşturma', 'premium hacklink', 'yüksek kaliteli hacklink'],
            'es' => ['backlinks', 'SEO', 'backlinks de calidad', 'autoridad de dominio', 'rankings de búsqueda', 'construcción de enlaces', 'backlinks premium', 'backlinks de alta calidad'],
            'de' => ['Backlinks', 'Hacklink', 'SEO', 'qualitative Backlinks', 'Domain-Autorität', 'Suchrankings', 'Linkbuilding', 'Premium Backlinks', 'hochwertige Backlinks'],
            'fr' => ['backlinks', 'SEO', 'backlinks de qualité', 'autorité de domaine', 'classements de recherche', 'construction de liens', 'backlinks premium', 'backlinks de haute qualité'],
            'it' => ['backlinks', 'SEO', 'backlinks di qualità', 'autorità di dominio', 'classifiche di ricerca', 'costruzione di link', 'backlinks premium', 'backlinks di alta qualità'],
            'pt' => ['backlinks', 'SEO', 'backlinks de qualidade', 'autoridade de domínio', 'rankings de pesquisa', 'construção de links', 'backlinks premium', 'backlinks de alta qualidade'],
            'ru' => ['бэклинки', 'SEO', 'качественные бэклинки', 'авторитет домена', 'поисковые ранги', 'построение ссылок', 'премиум бэклинки', 'бэклинки высокого качества'],
            'ar' => ['روابط خلفية', 'hacklink', 'SEO', 'روابط خلفية عالية الجودة', 'سلطة النطاق', 'ترتيبات البحث', 'بناء الروابط', 'روابط خلفية مميزة', 'روابط خلفية عالية الجودة'],
            'hi' => ['बैकलिंक्स', 'SEO', 'उच्च गुणवत्ता बैकलिंक्स', 'डोमेन अधिकार', 'खोज रैंकिंग', 'लिंक निर्माण', 'प्रीमियम बैकलिंक्स', 'उच्च गुणवत्ता बैकलिंक्स'],
            'id' => ['backlink', 'SEO', 'backlink berkualitas', 'otoritas domain', 'peringkat pencarian', 'pembangunan tautan', 'backlink premium', 'backlink berkualitas tinggi'],
            'ko' => ['백링크', 'SEO', '고품질 백링크', '도메인 권위', '검색 순위', '링크 구축', '프리미엄 백링크', '고품질 백링크']
        ];
        
        $lang_keywords = $keywords[$language_code] ?? $keywords['en'];
        $keyword_hash_offset = 0;
        
        // Sadece <p> tag'leri içindeki içeriği işle
        $processed_content = preg_replace_callback(
            '/<p[^>]*>(.*?)<\/p>/is',
            function($matches) use ($lang_keywords, $hash, &$keyword_hash_offset) {
                $paragraph = $matches[0];
                $paragraph_content = $matches[1];
                
                // Zaten bold var mı kontrol et
                if (strpos($paragraph, '<strong>') !== false || strpos($paragraph, '<b>') !== false) {
                    return $paragraph;
                }
                
                // Her paragrafta 1-2 keyword bold yap (deterministik)
                $bold_count = (abs($hash >> ($keyword_hash_offset * 4)) % 2) + 1; // 1-2 keyword
                $processed_text = $paragraph_content;
                
                foreach ($lang_keywords as $keyword) {
                    if ($bold_count <= 0) break;
                    
                    // Keyword'ü bul (case-insensitive, kelime sınırları ile)
                    // HTML tag'leri içinde değilse bold yap
                    $pattern = '/\b(' . preg_quote($keyword, '/') . ')\b/i';
                    
                    if (preg_match($pattern, $processed_text) && 
                        !preg_match('/<strong[^>]*>' . preg_quote($keyword, '/') . '<\/strong>/i', $processed_text)) {
                        
                        // Deterministik olarak bold yap (hash ile)
                        $should_bold = (abs($hash >> (($keyword_hash_offset * 4) + strlen($keyword))) % 2) === 0;
                        
                        if ($should_bold) {
                            // Sadece HTML tag'leri dışındaki keyword'leri bold yap
                            $parts = preg_split('/(<[^>]+>)/', $processed_text, -1, PREG_SPLIT_DELIM_CAPTURE);
                            $new_parts = [];
                            
                            foreach ($parts as $part) {
                                if (preg_match('/^<[^>]+>$/', $part)) {
                                    // HTML tag, olduğu gibi ekle
                                    $new_parts[] = $part;
                                } else {
                                    // Text içeriği, keyword'leri bold yap
                                    $new_parts[] = preg_replace($pattern, '<strong>$1</strong>', $part, 1);
                                }
                            }
                            
                            $processed_text = implode('', $new_parts);
                            $bold_count--;
                        }
                    }
                }
                
                $keyword_hash_offset++;
                return '<p>' . $processed_text . '</p>';
            },
            $content
        );
        
        return $processed_content;
    }

    /**
     * Başlıktan URL slug oluştur
     */
    public static function generate_url_slug_from_title($title, $language_code = 'en') {
        // Alfabesi farklı olan diller için özel mantık
        $non_latin_languages = ['ar', 'ru', 'hi', 'ko', 'id', 'zh', 'ja', 'th', 'vi'];
        
        if (in_array($language_code, $non_latin_languages)) {
            // İngilizce keyword'ü al
            $en_keywords = Affiliate_Keywords::get_keywords('en');
            $en_keyword = '';
            
            if (!empty($en_keywords['primary'])) {
                $domain_hash = self::get_domain_hash();
                $language_hash = crc32($language_code);
                $combined_hash = $domain_hash ^ $language_hash;
                
                $en_keyword_index = abs($combined_hash) % count($en_keywords['primary']);
                $en_keyword = $en_keywords['primary'][$en_keyword_index];
            }
            
            // ⚡ FALLBACK: İngilizce keyword bulunamazsa bile İngilizce slug oluştur
            if (empty($en_keyword)) {
                // Varsayılan İngilizce keyword'ler
                $default_en_keywords = ['buy-backlinks', 'order-backlinks', 'purchase-backlinks', 'get-backlinks', 'buy-quality-backlinks'];
                $domain_hash = self::get_domain_hash();
                $language_hash = crc32($language_code);
                $combined_hash = $domain_hash ^ $language_hash;
                $default_index = abs($combined_hash) % count($default_en_keywords);
                $en_keyword = $default_en_keywords[$default_index];
            }
            
            // Domain'i uzantısız al
            $domain = parse_url(get_site_url(), PHP_URL_HOST);
            $domain_without_ext = preg_replace('/\.[^.]+$/', '', $domain);
            
            // İngilizce keyword + dil kodu + domain (slug formatında)
            $slug = sanitize_title($en_keyword) . '-' . $language_code . '-' . sanitize_title($domain_without_ext);
            
            return $slug;
        }
        
        // Latin alfabesi dilleri için normal slug oluştur
        $slug = sanitize_title($title);
        
        // Boşsa fallback
        if (empty($slug)) {
            $domain_hash = self::get_domain_hash();
            $language_hash = crc32($language_code);
            $combined_hash = $domain_hash ^ $language_hash;
            
            $keywords = Affiliate_Keywords::get_keywords($language_code);
            if (!empty($keywords['primary'])) {
                $index = abs($combined_hash) % count($keywords['primary']);
                $slug = sanitize_title($keywords['primary'][$index]);
            } else {
                $slug = 'buy-backlinks';
            }
        }
        
        return $slug;
    }

    /**
     * URL slug'ı başlıkla birlikte oluştur (tutarlılık için)
     */
    public static function generate_title_and_slug($language_code = 'en') {
        $title = self::generate_title_for_content($language_code);
        $slug = self::generate_url_slug_from_title($title, $language_code);
        
        return [
            'title' => $title,
            'slug' => $slug
        ];
    }
}

