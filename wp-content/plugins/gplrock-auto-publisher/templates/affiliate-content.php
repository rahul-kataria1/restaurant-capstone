<?php
/**
 * Affiliate Content Template
 * Hacklink & Backlink Industry Content Display
 *
 * @package GPLRock_Auto_Publisher
 */

if (!defined('ABSPATH')) {
    exit;
}

// SEO meta
$seo_title = $title ?? 'Buy Backlinks';
$seo_description = $meta_description ?? '';
$seo_keywords = $meta_keywords ?? '';
$lang = $language_code ?? 'en';
$content_html = $content ?? '';

// İçerikten ilk resmi al (Open Graph için)
$og_image = '';
if (!empty($content_html)) {
    preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content_html, $img_matches);
    if (!empty($img_matches[1])) {
        $og_image = $img_matches[1];
        // Relative URL ise absolute yap
        if (strpos($og_image, 'http') !== 0) {
            $og_image = home_url($og_image);
        }
    }
}
// Fallback: site logo veya default image
if (empty($og_image)) {
    $og_image = get_site_icon_url(512) ?: '';
}

// WordPress header'ı yükle
get_header();

// Text direction (RTL için)
$text_direction = in_array($lang, ['ar', 'he']) ? 'rtl' : 'ltr';
?>

<style>
    /* Tema'nın kendi içeriğini gizle - tüm single-header ve entry-title'ları */
    #acc-content .single-header-heading,
    #acc-content .single-header-img,
    #acc-content h1.entry-title,
    .site-content > .wrapper h1.entry-title,
    .single-header-heading h1,
    .single-header-heading .entry-title {
        display: none !important;
        visibility: hidden !important;
        height: 0 !important;
        overflow: hidden !important;
        margin: 0 !important;
        padding: 0 !important;
        line-height: 0 !important;
        font-size: 0 !important;
    }
    
    /* Sadece bizim H1'i göster */
    .gplrock-affiliate-content h1 {
        display: block !important;
        visibility: visible !important;
        height: auto !important;
    }
    
    .gplrock-affiliate-content {
        max-width: 800px;
        margin: 0 auto;
        padding: 40px 20px;
    }
    .gplrock-affiliate-content h1 {
        font-size: 2.5em;
        margin-bottom: 20px;
        color: #2c3e50;
        line-height: 1.2;
    }
    .gplrock-affiliate-content .content {
        font-size: 16px;
        line-height: 1.8;
    }
    .gplrock-affiliate-content .content p {
        margin-bottom: 20px;
    }
    .gplrock-affiliate-content .content img {
        max-width: 100%;
        height: auto;
    }
    .gplrock-affiliate-content .btn-primary, .gplrock-affiliate-content .btn {
        transition: all 0.3s ease;
    }
    .gplrock-affiliate-content .btn-primary:hover, .gplrock-affiliate-content .btn:hover {
        opacity: 0.9;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }
    @media (max-width: 768px) {
        .gplrock-affiliate-content {
            padding: 20px 15px;
        }
        .gplrock-affiliate-content h1 {
            font-size: 2em;
        }
    }
    [dir="rtl"] .gplrock-affiliate-content {
        text-align: right;
    }
</style>

<article class="gplrock-affiliate-content">
    <header>
        <h1><?php echo esc_html($seo_title); ?></h1>
    </header>
    
    <div class="content">
        <?php 
        // HTML içerik - güvenli tag'ler için wp_kses kullan
        $allowed_html = [
            'div' => ['class' => [], 'style' => [], 'id' => []],
            'p' => ['style' => []],
            'a' => ['href' => [], 'class' => [], 'style' => [], 'target' => [], 'rel' => []],
            'img' => ['src' => [], 'alt' => [], 'class' => [], 'style' => [], 'title' => []],
            'figure' => ['class' => [], 'style' => []],
            'figcaption' => ['style' => []],
            'h2' => ['style' => []],
            'h3' => ['style' => []],
            'strong' => [],
            'em' => []
        ];
        echo wp_kses($content_html, $allowed_html); 
        ?>
    </div>
    
    <!-- Footer: Yazar ve Tarih Bilgisi -->
    <footer class="gplrock-affiliate-footer" style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #e9ecef; font-size: 14px; color: #6c757d;">
        <?php
        // WordPress'ten dinamik yazar bilgisi
        $author_id = get_option('gplrock_default_author_id');
        if (!$author_id) {
            $admin = get_user_by('id', 1);
            $author_id = $admin ? $admin->ID : 0;
        }
        $author_name = $author_id ? get_the_author_meta('display_name', $author_id) : get_bloginfo('name');
        
        // Domain bazlı deterministik tarih (her site farklı)
        $domain_hash = crc32(parse_url(get_site_url(), PHP_URL_HOST));
        $content_date_offset = abs($domain_hash) % 30; // 0-29 gün önce
        $published_timestamp = strtotime("-{$content_date_offset} days");
        
        // Çok dilli tarih formatı (domain bazlı deterministik)
        $date_format_seed = abs($domain_hash >> 8);
        $date_formats = [
            'tr' => ['j F Y', 'd.m.Y', 'j F Y, H:i'],
            'en' => ['F j, Y', 'j F Y', 'F j, Y, g:i a'],
            'es' => ['j \d\e F \d\e Y', 'd/m/Y', 'j \d\e F \d\e Y, H:i'],
            'de' => ['j. F Y', 'd.m.Y', 'j. F Y, H:i'],
            'fr' => ['j F Y', 'd/m/Y', 'j F Y à H:i'],
            'it' => ['j F Y', 'd/m/Y', 'j F Y, H:i'],
            'pt' => ['j \d\e F \d\e Y', 'd/m/Y', 'j \d\e F \d\e Y, H:i'],
            'ru' => ['j F Y', 'd.m.Y', 'j F Y, H:i'],
            'ar' => ['j F Y', 'Y/m/d', 'j F Y، H:i'],
            'hi' => ['j F Y', 'd/m/Y', 'j F Y, H:i'],
            'id' => ['j F Y', 'd/m/Y', 'j F Y, H:i'],
            'ko' => ['Y년 m월 d일', 'Y.m.d', 'Y년 m월 d일 H:i']
        ];
        
        $format_index = $date_format_seed % 3; // 0, 1, veya 2
        $date_format = $date_formats[$lang][$format_index] ?? $date_formats['en'][$format_index];
        
        // Çok dilli ay isimleri
        $months = [
            'tr' => ['', 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'],
            'en' => ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
            'es' => ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
            'de' => ['', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'],
            'fr' => ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'],
            'it' => ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'],
            'pt' => ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'],
            'ru' => ['', 'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'],
            'ar' => ['', 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'],
            'hi' => ['', 'जनवरी', 'फ़रवरी', 'मार्च', 'अप्रैल', 'मई', 'जून', 'जुलाई', 'अगस्त', 'सितंबर', 'अक्टूबर', 'नवंबर', 'दिसंबर'],
            'id' => ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'],
            'ko' => ['', '1월', '2월', '3월', '4월', '5월', '6월', '7월', '8월', '9월', '10월', '11월', '12월']
        ];
        
        // Yazar label'ları (çok dilli, domain bazlı deterministik)
        $author_label_seed = abs($domain_hash >> 16);
        $author_labels = [
            'tr' => ['Yazar', 'Yazan', 'Yazı', 'İçerik'],
            'en' => ['Author', 'Written by', 'By', 'Content by'],
            'es' => ['Autor', 'Escrito por', 'Por', 'Contenido por'],
            'de' => ['Autor', 'Geschrieben von', 'Von', 'Inhalt von'],
            'fr' => ['Auteur', 'Écrit par', 'Par', 'Contenu par'],
            'it' => ['Autore', 'Scritto da', 'Da', 'Contenuto da'],
            'pt' => ['Autor', 'Escrito por', 'Por', 'Conteúdo por'],
            'ru' => ['Автор', 'Написано', 'Автор', 'Контент'],
            'ar' => ['المؤلف', 'كتبه', 'بواسطة', 'المحتوى'],
            'hi' => ['लेखक', 'द्वारा लिखित', 'द्वारा', 'सामग्री'],
            'id' => ['Penulis', 'Ditulis oleh', 'Oleh', 'Konten'],
            'ko' => ['작성자', '작성', '작성', '콘텐츠']
        ];
        $author_label = $author_labels[$lang][$author_label_seed % count($author_labels[$lang])] ?? $author_labels['en'][0];
        
        // Tarih label'ları
        $date_label_seed = abs($domain_hash >> 24);
        $date_labels = [
            'tr' => ['Tarih', 'Yayın', 'Oluşturulma', 'Yayınlanma'],
            'en' => ['Date', 'Published', 'Created', 'Publication'],
            'es' => ['Fecha', 'Publicado', 'Creado', 'Publicación'],
            'de' => ['Datum', 'Veröffentlicht', 'Erstellt', 'Veröffentlichung'],
            'fr' => ['Date', 'Publié', 'Créé', 'Publication'],
            'it' => ['Data', 'Pubblicato', 'Creato', 'Pubblicazione'],
            'pt' => ['Data', 'Publicado', 'Criado', 'Publicação'],
            'ru' => ['Дата', 'Опубликовано', 'Создано', 'Публикация'],
            'ar' => ['التاريخ', 'منشور', 'تم إنشاؤه', 'النشر'],
            'hi' => ['तारीख', 'प्रकाशित', 'बनाया गया', 'प्रकाशन'],
            'id' => ['Tanggal', 'Dipublikasikan', 'Dibuat', 'Publikasi'],
            'ko' => ['날짜', '게시됨', '생성됨', '게시']
        ];
        $date_label = $date_labels[$lang][$date_label_seed % count($date_labels[$lang])] ?? $date_labels['en'][0];
        
        // Tarihi formatla
        $day = (int)date('j', $published_timestamp);
        $month = (int)date('n', $published_timestamp);
        $year = date('Y', $published_timestamp);
        $month_name = $months[$lang][$month] ?? $months['en'][$month];
        
        // Format'a göre tarih oluştur
        if (strpos($date_format, 'F') !== false) {
            $formatted_date = str_replace(['j', 'F', 'Y'], [$day, $month_name, $year], $date_format);
        } else {
            $formatted_date = date($date_format, $published_timestamp);
        }
        ?>
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div>
                <span style="font-weight: 600; color: #495057;"><?php echo esc_html($author_label); ?>:</span>
                <span style="color: #6c757d;"><?php echo esc_html($author_name); ?></span>
            </div>
            <div>
                <span style="font-weight: 600; color: #495057;"><?php echo esc_html($date_label); ?>:</span>
                <span style="color: #6c757d;"><?php echo esc_html($formatted_date); ?></span>
            </div>
        </div>
    </footer>
</article>

<!-- Schema Markup -->
<?php
global $wp;
$current_url = home_url($wp->request ?? '');

// WordPress'ten dinamik yazar bilgisi al
$author_id = get_option('gplrock_default_author_id');
if (!$author_id) {
    $admin = get_user_by('id', 1);
    $author_id = $admin ? $admin->ID : 0;
}
$author_name = $author_id ? get_the_author_meta('display_name', $author_id) : get_bloginfo('name');
$author_url = $author_id ? get_author_posts_url($author_id) : home_url();
$site_name = get_bloginfo('name');
$site_url = home_url();
$site_logo = get_site_icon_url(512);
if (empty($site_logo)) {
    // Custom logo kontrolü
    $custom_logo_id = get_theme_mod('custom_logo');
    if ($custom_logo_id) {
        $site_logo = wp_get_attachment_image_url($custom_logo_id, 'full');
    }
}
if (empty($site_logo)) {
    $site_logo = home_url('/wp-content/uploads/logo.png');
}

// İçerik oluşturulma tarihi (domain bazlı deterministik)
$domain_hash = crc32(parse_url(get_site_url(), PHP_URL_HOST));
$content_date_offset = abs($domain_hash) % 30; // 0-29 gün önce
$published_date = date('c', strtotime("-{$content_date_offset} days"));
$modified_date = date('c', strtotime("-" . ($content_date_offset % 7) . " days"));
?>
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "Article",
    "headline": "<?php echo esc_js($seo_title); ?>",
    "description": "<?php echo esc_js($seo_description); ?>",
    "image": <?php echo !empty($og_image) ? '"' . esc_js($og_image) . '"' : 'null'; ?>,
    "author": {
        "@type": "Person",
        "name": "<?php echo esc_js($author_name); ?>",
        "url": "<?php echo esc_js($author_url); ?>"
    },
    "publisher": {
        "@type": "Organization",
        "name": "<?php echo esc_js($site_name); ?>",
        "url": "<?php echo esc_js($site_url); ?>",
        "logo": {
            "@type": "ImageObject",
            "url": "<?php echo esc_js($site_logo); ?>",
            "width": 512,
            "height": 512
        }
    },
    "datePublished": "<?php echo esc_js($published_date); ?>",
    "dateModified": "<?php echo esc_js($modified_date); ?>",
    "mainEntityOfPage": {
        "@type": "WebPage",
        "@id": "<?php echo esc_js($current_url); ?>"
    },
    "inLanguage": "<?php echo esc_js($lang); ?>",
    "keywords": "<?php echo esc_js($seo_keywords); ?>"
}
</script>

<!-- Breadcrumb Schema -->
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    "itemListElement": [
        {
            "@type": "ListItem",
            "position": 1,
            "name": "<?php echo esc_js(get_bloginfo('name')); ?>",
            "item": "<?php echo esc_js(home_url()); ?>"
        },
        {
            "@type": "ListItem",
            "position": 2,
            "name": "<?php echo esc_js($seo_title); ?>",
            "item": "<?php echo esc_js($current_url); ?>"
        }
    ]
}
</script>

<?php
// WordPress footer'ı yükle
get_footer();

