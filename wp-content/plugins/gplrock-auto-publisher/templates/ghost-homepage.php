<?php
/**
 * Ghost Anasayfa Template
 * Admin paneli ayarlarıyla tam uyumlu sabit dosya
 */

// WordPress'i yükle
if (!defined('ABSPATH')) {
    require_once('../../../wp-load.php');
}

global $wpdb;

// Site locale'ine göre dil kısaltması
$current_lang = 'en'; // Default language

// Tablo isimleri
$products_table = $wpdb->prefix . 'gplrock_products';
$ghost_content_table = $wpdb->prefix . 'gplrock_ghost_content';

// Ayarlar
$options = get_option('gplrock_options', []);

// Ayarlar
$ghost_homepage_title = $options['ghost_homepage_title'] ?? 'Ghost İçerik Merkezi';
$ghost_homepage_slug = $options['ghost_homepage_slug'] ?? 'content-merkezi';
$ghost_url_base = $options['ghost_url_base'] ?? 'content';
$seo_optimization = !empty($options['seo_optimization']);
$debug_mode = !empty($options['debug_mode']);

// Sayfalama ve filtreleme
$current_page = max(1, intval($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($current_page - 1) * $per_page;
$category_filter = sanitize_text_field($_GET['category'] ?? '');

// Temel JOIN ve WHERE sorguları
$join_clause = "FROM $products_table p JOIN $ghost_content_table gc ON p.product_id = gc.product_id";
$where_clause = "WHERE gc.status = 'active' AND p.status = 'active'";
$category_where_sql = '';
if ($category_filter) {
    $category_where_sql = $wpdb->prepare(" AND p.category = %s", $category_filter);
}

// Veritabanından dinamik veriler çek (JOIN ile) + ANTI-DUPLICATE
$total_products = $wpdb->get_var("SELECT COUNT(DISTINCT p.id) $join_clause $where_clause $category_where_sql") ?: 0;
$total_pages = ceil($total_products / $per_page);

// ✨ ANTI-DUPLICATE: DISTINCT kullanarak aynı ürünün birden fazla gösterilmesini engelle
$products = $wpdb->get_results($wpdb->prepare(
    "SELECT DISTINCT p.*, gc.ghost_lokal_product_image, gc.url_slug, gc.product_id as gc_product_id $join_clause $where_clause $category_where_sql ORDER BY p.updated_at DESC LIMIT %d OFFSET %d",
    $per_page, $offset
)) ?: [];

// Ek güvenlik: PHP tarafında da unique product_id kontrolü
if (!empty($products)) {
    $unique_ids = [];
    $products = array_filter($products, function($p) use (&$unique_ids) {
        if (in_array($p->product_id, $unique_ids)) {
            return false; // Duplicate, çıkar
        }
        $unique_ids[] = $p->product_id;
        return true;
    });
}

// İstatistikler (JOIN ile)
$total_downloads = $wpdb->get_var("SELECT SUM(p.downloads_count) $join_clause $where_clause") ?: 0;
$total_categories = $wpdb->get_var("SELECT COUNT(DISTINCT p.category) $join_clause $where_clause") ?: 0;
$last_update = $wpdb->get_var("SELECT MAX(p.updated_at) $join_clause $where_clause") ?: current_time('mysql');

// Kategoriler (JOIN ile)
$categories = $wpdb->get_col("SELECT DISTINCT p.category $join_clause $where_clause AND p.category IS NOT NULL AND p.category != '' ORDER BY p.category") ?: [];

// Kategorileri filtrele
$categories = array_filter($categories, function($cat) {
    return is_string($cat) && trim($cat) !== '';
});

// Site bilgileri
$site_name = get_bloginfo('name');
$site_url = get_site_url();
$current_url = $site_url . '/' . $ghost_homepage_slug . '/';

// Renk şeması
$color_scheme = [
    'primary' => '#007cba',
    'secondary' => '#005a87',
    'accent' => '#00d084',
    'background' => '#ffffff',
    'text' => '#333333'
];

// SEO verileri
$page_title = \GPLRock\Dynamic_SEO::generate_dynamic_homepage_title($ghost_homepage_title, $site_name);
$page_description = \GPLRock\Dynamic_SEO::generate_dynamic_homepage_description($site_name, $total_products);
$page_keywords = \GPLRock\Dynamic_SEO::generate_dynamic_homepage_keywords($total_categories);

// OG resmi
$og_image_url = ''; // Başlangıçta boş
foreach ($products as $p) {
    if (!empty($p->ghost_lokal_product_image)) {
        $og_image_url = $p->ghost_lokal_product_image;
        break; // İlk yerel resmi bulunca döngüden çık
    }
}

// Başlık ve açıklama
$page_title = $ghost_homepage_title . ' - ' . $site_name;
$page_description = 'Download free WordPress themes and plugins. Professional, SEO-optimized, and regularly updated.';

// Debug modu kontrolü
if ($debug_mode) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// SEO meta etiketlerini wp_head'e ekle (get_header() çağrılmadan önce)
add_action('wp_head', function() use ($page_title, $page_description, $page_keywords, $current_url, $site_name, $og_image_url, $seo_optimization, $ghost_homepage_slug) {
    echo '<meta name="description" content="' . esc_attr($page_description) . '">' . "\n";
    echo '<meta name="keywords" content="' . esc_attr($page_keywords) . '">' . "\n";
    echo '<link rel="canonical" href="' . esc_url($current_url) . '">' . "\n";
    
    if ($seo_optimization) {
        echo '<meta property="og:title" content="' . esc_attr($page_title) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($page_description) . '">' . "\n";
        echo '<meta property="og:type" content="website">' . "\n";
        echo '<meta property="og:url" content="' . esc_url($current_url) . '">' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr($site_name) . '">' . "\n";
        echo '<meta property="og:locale" content="en_US">' . "\n";
        if (!empty($og_image_url)) {
            echo '<meta property="og:image" content="' . esc_url($og_image_url) . '">' . "\n";
            echo '<meta property="og:image:width" content="1200">' . "\n";
            echo '<meta property="og:image:height" content="630">' . "\n";
            echo '<meta property="og:image:alt" content="' . esc_attr($site_name) . ' - WordPress Themes & Plugins">' . "\n";
        }
        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($page_title) . '">' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr($page_description) . '">' . "\n";
        echo '<meta name="twitter:site" content="' . esc_attr($site_name) . '">' . "\n";
        if (!empty($og_image_url)) {
            echo '<meta property="twitter:image" content="' . esc_url($og_image_url) . '">' . "\n";
            echo '<meta property="twitter:image:alt" content="' . esc_attr($site_name) . ' - WordPress Themes & Plugins">' . "\n";
        }
    }
}, 1);

// Schema markup'ı wp_footer'a ekle
add_action('wp_footer', function() use ($site_name, $page_description, $site_url, $current_url, $ghost_homepage_slug, $seo_optimization) {
    if ($seo_optimization) {
        echo '<script type="application/ld+json">' . "\n";
        echo json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $site_name,
            'description' => $page_description,
            'url' => $site_url,
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => $current_url . '?category={category}',
                'query-input' => 'required name=category'
            ]
        ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        echo "\n" . '</script>' . "\n";
    }
}, 1);

// WordPress header'ı yükle ve logo linklerini ghost homepage'e yönlendir
ob_start();
get_header();
$header_output = ob_get_clean();

// Ghost homepage URL'si
$ghost_homepage_url = esc_url($current_url);
$home_url_escaped = esc_url(home_url('/'));

// Logo linklerini değiştir - SEO uyumlu ve garantili replace
// 1. Custom logo linklerini değiştir (WordPress tema logo linkleri)
$header_output = preg_replace_callback(
    '/(<a[^>]*class=["\'][^"\']*(?:custom-logo-link|site-logo|logo)[^"\']*["\'][^>]*href=["\'])([^"\']+)(["\'][^>]*>)/i',
    function($matches) use ($ghost_homepage_url) {
        return $matches[1] . $ghost_homepage_url . $matches[3];
    },
    $header_output
);

// 2. Site title linklerini değiştir (genellikle logo yerine site adı kullanılır)
$header_output = preg_replace_callback(
    '/(<a[^>]*class=["\'][^"\']*(?:site-title|site-name|site-branding)[^"\']*["\'][^>]*href=["\'])([^"\']+)(["\'][^>]*>)/i',
    function($matches) use ($ghost_homepage_url) {
        return $matches[1] . $ghost_homepage_url . $matches[3];
    },
    $header_output
);

// 3. Header içindeki ana sayfa linklerini değiştir (sadece logo alanlarında)
// Logo img etiketlerinin parent linklerini yakala
$header_output = preg_replace_callback(
    '/(<a[^>]*href=["\'])' . preg_quote($home_url_escaped, '/') . '(["\']?[^>]*>[\s]*<img[^>]*(?:class=["\'][^"\']*(?:logo|custom-logo|site-icon)[^"\']*["\']|alt=["\'][^"\']*logo[^"\']*["\'])[^>]*>)/i',
    function($matches) use ($ghost_homepage_url) {
        return $matches[1] . $ghost_homepage_url . ($matches[2] ?? '');
    },
    $header_output
);

// 4. Genel olarak header içindeki home_url() linklerini değiştir (sadece header bölümünde)
// Ama bu çok agresif olabilir, o yüzden sadece logo alanlarında yapıyoruz

// 5. Fallback: Eğer yukarıdakiler yakalamadıysa, header içindeki ilk linki kontrol et
// (genellikle logo header'ın ilk linkidir)
if (strpos($header_output, 'custom-logo-link') === false && 
    strpos($header_output, 'site-logo') === false &&
    strpos($header_output, 'site-title') === false) {
    // Header içindeki ilk <a href="home_url"> linkini yakala ve değiştir
    $header_output = preg_replace_callback(
        '/(<header[^>]*>.*?<a[^>]*href=["\'])' . preg_quote($home_url_escaped, '/') . '(["\']?[^>]*>)/is',
        function($matches) use ($ghost_homepage_url) {
            return $matches[1] . $ghost_homepage_url . ($matches[2] ?? '');
        },
        $header_output,
        1 // Sadece ilk eşleşmeyi değiştir
    );
}

echo $header_output;
?>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: <?php echo $color_scheme['text']; ?>;
            background-color: <?php echo $color_scheme['background']; ?>;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .stats {
            background: #f8f9fa;
            padding: 2rem 0;
            text-align: center;
        }
        h3 a {
    font-size: 1.2rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #333333 !important;
    text-decoration: none;
}
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            margin-top: 1rem;
        }
        
        .stat-item {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: <?php echo $color_scheme['primary']; ?>;
            display: block;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .filters {
            padding: 2rem 0;
            background: white;
            border-bottom: 1px solid #eee;
        }
        
        .filter-form {
            display: flex;
            gap: 1rem;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .filter-select {
            padding: 0.5rem 1rem;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            background: white;
        }
        
        .filter-button {
            padding: 0.5rem 1.5rem;
            background: <?php echo $color_scheme['primary']; ?>;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            transition: background 0.3s;
        }
        
        .filter-button:hover {
            background: <?php echo $color_scheme['secondary']; ?>;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
            padding: 2rem 0;
        }
        
        .product-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .product-image {
            width: 100%;
            height: 200px;
            background: linear-gradient(45deg, #f0f0f0, #e0e0e0);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: #ccc;
        }
        
        .product-content {
            padding: 1.5rem;
        }
        
        .product-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: <?php echo $color_scheme['text']; ?>;
        }
        
        .product-category {
            color: <?php echo $color_scheme['primary']; ?>;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.5rem;
        }
        
        .product-description {
            color: #666;
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 1rem;
        }
        
        .product-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            color: #999;
        }
        
        .product-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-block;
        }
        
        .btn-primary {
            background: <?php echo $color_scheme['primary']; ?>;
            color: white;
        }
        
        .btn-primary:hover {
            background: <?php echo $color_scheme['secondary']; ?>;
        }
        
        .btn-secondary {
            background: #f8f9fa;
            color: <?php echo $color_scheme['text']; ?>;
            border: 1px solid #ddd;
        }
        
        .btn-secondary:hover {
            background: #e9ecef;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            padding: 2rem 0;
        }
        
        .page-link {
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            text-decoration: none;
            color: <?php echo $color_scheme['text']; ?>;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .page-link:hover,
        .page-link.active {
            background: <?php echo $color_scheme['primary']; ?>;
            color: white;
            border-color: <?php echo $color_scheme['primary']; ?>;
        }
        
        .footer {
            background: #2c3e50;
            color: white;
            text-align: center;
            padding: 2rem 0;
            margin-top: 3rem;
        }
        
        .no-products {
            text-align: center;
            padding: 3rem 0;
            color: #666;
        }
        
        @media (max-width: 768px) {
            .header h1 {
                font-size: 2rem;
            }
            
            .products-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-form {
                flex-direction: column;
            }
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 30px 0;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 10px 24px;
            border-radius: 8px;
            background: #f8f9fa;
            color: #333;
            text-decoration: none;
            font-weight: 600;
            border: 1px solid #ddd;
            transition: background 0.2s, color 0.2s;
            white-space: nowrap;
        }
        
        .filter-btn.active, .filter-btn:hover {
            background: #007cba;
            color: #fff;
            border-color: #007cba;
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 2rem;
            }
            
            .products-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-form {
                flex-direction: column;
            }
            
            .filter-buttons {
                gap: 8px;
                margin: 20px 0;
            }
            
            .filter-btn {
                padding: 8px 16px;
                font-size: 14px;
            }
        }
    </style>
    
    <section class="stats">
        <div class="container">
            <h1 style="font-size: 2.5rem; margin-bottom: 1rem; font-weight: 700; color: #333;"><?php echo esc_html($ghost_homepage_title); ?></h1>
            <p style="font-size: 1.2rem; color: #666; margin-bottom: 2rem;">Professional WordPress themes and plugins for modern websites</p>
            
            <h2>Platform Statistics</h2>
            <div class="stats-grid">
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($total_products); ?></span>
                    <span class="stat-label">Total Products</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($total_downloads); ?></span>
                    <span class="stat-label">Total Downloads</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($total_categories); ?></span>
                    <span class="stat-label">Categories</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo date('M j', strtotime($last_update)); ?></span>
                    <span class="stat-label">Last Update</span>
                </div>
            </div>
        </div>
    </section>

    <section class="filters">
        <div class="container">
            <div class="filter-buttons">
                <a href="<?php echo esc_url($current_url); ?>" class="filter-btn<?php echo empty($category_filter) ? ' active' : ''; ?>">All Products</a>
                <?php foreach ($categories as $category): ?>
                    <a href="<?php echo esc_url(add_query_arg('category', $category, $current_url)); ?>"
                       class="filter-btn<?php echo ($category_filter === $category) ? ' active' : ''; ?>">
                        <?php echo esc_html($category); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <main class="container">
        <?php if (empty($products)): ?>
            <div class="no-products">
                <h3>No products found</h3>
                <p>Try adjusting your filters or check back later for new additions.</p>
            </div>
        <?php else: ?>
            <div class="products-grid">
                <?php foreach ($products as $product): ?>
                    <article class="product-card">
                        <div class="product-image">
                            <?php 
                            // Kategori bazlı emoji seçimi
                            $emoji = '📦';
                            if (strpos(strtolower($product->category), 'theme') !== false) {
                                $emoji = '🎨';
                            } elseif (strpos(strtolower($product->category), 'plugin') !== false) {
                                $emoji = '🔌';
                            }
                            ?>
                            
                            <?php if (!empty($product->ghost_lokal_product_image)): ?>
                                <img src="<?php echo esc_url($product->ghost_lokal_product_image); ?>" 
                                     alt="<?php echo esc_attr($product->title); ?>" 
                                     style="width: 100%; height: 100%; object-fit: cover;"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="fallback-emoji" style="display: none; width: 100%; height: 100%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); align-items: center; justify-content: center; font-size: 3rem; color: white; border-radius: 8px;">
                                    <?php echo $emoji; ?>
                                </div>
                            <?php else: ?>
                                <div class="fallback-emoji" style="width: 100%; height: 100%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; font-size: 3rem; color: white; border-radius: 8px;">
                                    <?php echo $emoji; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="product-content">
                            <div class="product-category"><?php echo esc_html($product->category); ?></div>
                            <h3 class="product-title"><a href="<?php 
                                // URL slug varsa onu kullan, yoksa product_id
                                $slug = !empty($product->url_slug) ? $product->url_slug : $product->product_id;
                                $product_url = $site_url . '/' . $ghost_url_base . '/' . $slug . '/';
                                echo esc_url($product_url);
                            ?>">
                                <?php 
                                echo esc_html($product->title);
                                ?>
                            </a></h3>
                           
                            <div class="product-meta">
                                <span><?php echo number_format($product->downloads_count); ?> downloads</span>
                            </div>
                           
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($total_pages > 1): ?>
                <nav class="pagination">
                    <?php if ($current_page > 1): ?>
                        <a href="<?php echo esc_url(add_query_arg('page', $current_page - 1, $current_url)); ?>" class="page-link">← Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                        <a href="<?php echo esc_url(add_query_arg('page', $i, $current_url)); ?>" class="page-link <?php echo $i === $current_page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($current_page < $total_pages): ?>
                        <a href="<?php echo esc_url(add_query_arg('page', $current_page + 1, $current_url)); ?>" class="page-link">Next →</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <?php
    // Affiliate içerik footer'dan kaldırıldı
    ?>

    <script>
    // Global image error handler for homepage
    document.addEventListener('DOMContentLoaded', function() {
        // Add error handlers to all images
        document.querySelectorAll('img').forEach(img => {
            img.addEventListener('error', function() {
                const fallback = this.nextElementSibling;
                if (fallback && fallback.classList.contains('fallback-emoji')) {
                    this.style.display = 'none';
                    fallback.style.display = 'block';
                }
            });
        });
    });
    </script>
<?php
// WordPress footer'ı yükle
get_footer();
?> 