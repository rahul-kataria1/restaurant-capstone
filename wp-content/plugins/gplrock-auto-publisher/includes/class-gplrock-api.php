<?php
/**
 * GPLRock API Class
 *
 * @package GPLRock_Auto_Publisher
 * @since 2.0.0
 */

namespace GPLRock;

if (!defined('ABSPATH')) {
    exit;
}

use PDO;
use Exception;

class API {
    private static $instance = null;
    private $api_url;
    private $api_token;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $opts = get_option('gplrock_options', []);
        $this->api_url = $opts['api_url'] ?? 'https://hacklinkpanel.app/api/ghost-api.php';
        $this->api_token = $opts['api_token'] ?? '';
    }

    /**
     * API'den ürünleri çek
     */
    public function fetch_products($limit = 5000, $offset = 0, $last_synced_id = 0) {
        // Memory limit kontrolü
        $memory_limit = ini_get('memory_limit');
        $memory_limit_bytes = $this->convert_memory_limit_to_bytes($memory_limit);
        $required_memory = $limit * 1024 * 30; // Her ürün için ~10KB
        
        // Memory limit -1 ise sınırsız
        if ($memory_limit_bytes > 0 && $required_memory > $memory_limit_bytes * 0.8) {
            throw new \Exception("Memory limit yetersiz. Gerekli: " . $this->format_bytes($required_memory) . ", Mevcut: $memory_limit");
        }
        
        $params = [
            'limit' => intval($limit),
            'offset' => intval($offset),
            'last_synced_id' => intval($last_synced_id)
        ];
        $url = add_query_arg($params, $this->api_url);
        $args = [
            'timeout' => 120, // 2 dakika timeout
            'user-agent' => 'GPLRock-Auto-Publisher/2.0',
        ];
        if ($this->api_token) {
            $args['headers'] = [ 'Authorization' => 'Bearer ' . $this->api_token ];
        }
        
        // Progress tracking
        if ($limit > 1000) {
            error_log("GPLRock: Büyük veri çekme başladı - Limit: $limit, Offset: $offset, Last ID: $last_synced_id");
        }
        
        $response = wp_remote_get($url, $args);
        if (is_wp_error($response)) {
            throw new \Exception('API bağlantı hatası: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('API JSON parse hatası: ' . json_last_error_msg());
        }
        if (empty($data['status']) || $data['status'] !== 'success') {
            throw new \Exception('API veri hatası: ' . ($data['error'] ?? 'Bilinmeyen hata'));
        }
        
        $products = $data['data'] ?? [];
        
        // Progress tracking
        if (count($products) > 1000) {
            error_log("GPLRock: " . count($products) . " ürün başarıyla çekildi");
        }
        
        return $products;
    }

    /**
     * Toplu ürün senkronizasyonu - Optimized for large datasets
     * LOCK: 1 dakika boyunca eş zamanlı çalışmayı engeller
     * LIMIT: Maximum 4700 ürün
     */
    public static function sync_products($batch_size = 500, $max_total = 4700) {
        // ÖNCELİKLE: Sync tamamlandı mı kontrol et
        $sync_completed = get_option('gplrock_sync_completed', false);
        $current_offset = get_option('gplrock_sync_offset', 0);
        
        if ($sync_completed && $current_offset >= $max_total) {
            error_log("GPLRock: Sync zaten tamamlandı ($current_offset/$max_total). Manuel reset gerekli.");
            throw new \Exception("API sync tamamlandı ($current_offset/$max_total ürün). Yeniden başlatmak için 'Sync Reset' butonuna tıklayın.");
        }
        
        // LOCK MEKANIZMASI - 1 dakika koruma
        $lock_key = 'gplrock_sync_lock';
        $is_locked = get_transient($lock_key);
        
        if ($is_locked) {
            error_log("GPLRock: Sync zaten çalışıyor, lock aktif. 1 dakika sonra tekrar deneyin.");
            throw new \Exception('API sync işlemi zaten çalışıyor. Lütfen 1 dakika bekleyin.');
        }
        
        // Lock'u aktive et - 60 saniye (1 dakika)
        set_transient($lock_key, time(), 60);
        
        try {
            $api = self::get_instance();
            $total_synced = 0;
            
            // Kaldığı yerden devam et - offset'i options'tan al
            $original_offset = $current_offset;
            
            // 4700 limit kontrolü
            if ($current_offset >= $max_total) {
                error_log("GPLRock: Maximum limit ($max_total) ulaşıldı, sync tamamlandı olarak işaretleniyor");
                update_option('gplrock_sync_completed', true);
                update_option('gplrock_sync_offset', $max_total);
                delete_transient($lock_key); // Lock'u kaldır
                return $total_synced;
            }
            
            set_time_limit(300);
            ini_set('memory_limit', '512M');
            error_log("GPLRock: Sync başladı - Batch: $batch_size, Max: $max_total, Offset: $current_offset");
            
            // Kalan ürün sayısını hesapla
            $remaining = $max_total - $current_offset;
            $max_batches = ceil($remaining / $batch_size);
            
            for ($batch = 1; $batch <= $max_batches; $batch++) {
                // 4700 kontrolü - her batch'te
                if ($total_synced >= $max_total || $current_offset >= $max_total) {
                    error_log("GPLRock: 4700 limit ulaşıldı, durduruluyor");
                    break;
                }
            try {
                error_log("GPLRock: Batch $batch/$max_batches işleniyor... Offset: $current_offset");
                $products = $api->fetch_products($batch_size, $current_offset);
                
                if (empty($products)) {
                    error_log("GPLRock: Daha fazla ürün bulunamadı, senkronizasyon tamamlandı");
                    // Tüm ürünler çekildi, offset'i sıfırla
                    update_option('gplrock_sync_offset', 0);
                    break;
                }
                
                $fetched_count = count($products);
                $saved = Content::save_products_to_db($products);
                $total_synced += $saved;
                
                // AKILLI DUPLICATE SKIP: Eğer %90+ duplicate varsa, bu bölge zaten işlenmiş
                $duplicate_ratio = 1 - ($saved / max(1, $fetched_count));
                if ($duplicate_ratio >= 0.90 && $saved < 50) {
                    error_log("GPLRock: Yüksek duplicate oranı (%". round($duplicate_ratio * 100) ."%) - Offset hızlı ilerletiliyor");
                    // Duplicate bölgeyi hızlı atla - 2x ileri
                    $current_offset += ($batch_size * 2);
                    update_option('gplrock_sync_offset', $current_offset);
                    
                    // Ama 4700 limit kontrolü
                    if ($current_offset >= $max_total) {
                        error_log("GPLRock: 4700 limit ulaşıldı, sync tamamlandı olarak işaretleniyor");
                        update_option('gplrock_sync_completed', true);
                        update_option('gplrock_sync_offset', $max_total);
                        update_option('gplrock_last_sync', current_time('mysql'));
                        break;
                    }
                    
                    error_log("GPLRock: Duplicate bölge atlandı - Yeni Offset: $current_offset/$max_total");
                    continue; // Bir sonraki batch'e geç
                }
                
                // 4700 limit - tam kontrol
                if ($current_offset + $batch_size >= $max_total) {
                    error_log("GPLRock: 4700 limit ulaşıldı ($current_offset + $batch_size), sync tamamlandı olarak işaretleniyor");
                    update_option('gplrock_sync_completed', true);
                    update_option('gplrock_sync_offset', $max_total);
                    update_option('gplrock_last_sync', current_time('mysql'));
                    break;
                }
                
                // ✨ OFFSET OPTİMİZASYONU: Gerçekten alınan veri kadar ilerle (API mantığı)
                // API offset, kaydedilen değil, çekilen veri sayısına göre ilerler
                // Örnek: 500 ürün çektik -> offset +500 (kaç tane kaydedildiği önemli değil)
                $current_offset += $fetched_count; // Batch size yerine gerçekten çekilen sayı
                update_option('gplrock_sync_offset', $current_offset);
                
                error_log("GPLRock: Batch $batch tamamlandı - Yeni: $saved/$fetched_count, Dup: %". round($duplicate_ratio * 100) .", Toplam: $total_synced, Offset: $current_offset/$max_total");
                
                if ($saved < $batch_size * 0.1) {
                    error_log("GPLRock: Çok az yeni ürün, senkronizasyon tamamlandı");
                    break;
                }
                
                unset($products);
                gc_collect_cycles();
                
            } catch (\Exception $e) {
                error_log("GPLRock: Batch $batch hatası - " . $e->getMessage());
                delete_transient($lock_key); // Hata durumunda lock'u kaldır
                throw $e;
            }
            }
            
            update_option('gplrock_last_sync', current_time('mysql'));
            
            // Eğer 4700'e ulaştıysak, tamamlandı işaretle
            if ($current_offset >= $max_total || $total_synced >= $max_total) {
                update_option('gplrock_sync_completed', true);
                error_log("GPLRock: ✅ Senkronizasyon TAMAMLANDI - $total_synced ürün, Offset: $original_offset -> $current_offset/$max_total");
            } else {
                error_log("GPLRock: Senkronizasyon durdu - $total_synced ürün, Offset: $original_offset -> $current_offset/$max_total");
            }
            
            // Lock'u kaldır - başarılı tamamlama
            delete_transient($lock_key);
            
            return $total_synced;
            
        } catch (\Exception $e) {
            // Hata durumunda lock'u temizle
            delete_transient($lock_key);
            throw $e;
        }
    }

    /**
     * Memory limit'i byte'a çevir
     */
    public function convert_memory_limit_to_bytes($memory_limit) {
        $unit = strtolower(substr($memory_limit, -1));
        $value = (int) substr($memory_limit, 0, -1);
        
        switch ($unit) {
            case 'k': return $value * 1024;
            case 'm': return $value * 1024 * 1024;
            case 'g': return $value * 1024 * 1024 * 1024;
            default: return $value;
        }
    }

    /**
     * Byte'ı okunabilir formata çevir
     */
    public function format_bytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * API bağlantı testi
     */
    public static function test_connection() {
        $api = self::get_instance();
        $products = $api->fetch_products(1, 0);
        return !empty($products);
    }

    /**
     * REST API route kaydı (ileride kullanılabilir)
     */
    public static function register_routes() {
        // REST API endpointleri burada tanımlanabilir
    }
} 