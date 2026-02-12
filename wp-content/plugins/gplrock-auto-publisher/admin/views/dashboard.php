<div class="wrap">
    <h1>GPLRock Auto Publisher - Dashboard</h1>
    
    <!-- Menü Öğeleri -->
    <div class="gplrock-admin-menu" style="margin: 20px 0; padding: 15px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px;">
        <h2 style="margin-top: 0; margin-bottom: 15px; color: #495057;">📋 Admin Menüsü</h2>
        <div class="gplrock-menu-buttons" style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="<?php echo admin_url('admin.php?page=gplrock-dashboard'); ?>" class="button button-primary" style="text-decoration: none;">
                🏠 Dashboard
            </a>
            <a href="<?php echo admin_url('admin.php?page=gplrock-settings'); ?>" class="button button-secondary" style="text-decoration: none;">
                ⚙️ Ayarlar
            </a>
            <a href="<?php echo admin_url('admin.php?page=gplrock-content'); ?>" class="button button-secondary" style="text-decoration: none;">
                📝 İçerik Yöneticisi
            </a>
            <a href="<?php echo admin_url('admin.php?page=gplrock-logs'); ?>" class="button button-secondary" style="text-decoration: none;">
                📋 Loglar
            </a>
        </div>
        <div style="margin-top: 10px; font-size: 12px; color: #6c757d;">
            💡 <strong>Hızlı Erişim:</strong> Bu menü öğeleri ile eklentinin tüm özelliklerine kolayca erişebilirsiniz.
        </div>
    </div>
    
    <div class="gplrock-stats-grid">
        <div class="stat-card">
            <h3>Toplam Ürün</h3>
            <div class="stat-number" id="total-products"><?php echo esc_html($stats['total_products']); ?></div>
        </div>
        <div class="stat-card">
            <h3>Yayımlanmış</h3>
            <div class="stat-number" id="published-products"><?php echo esc_html($stats['published_products']); ?></div>
        </div>
        <div class="stat-card">
            <h3>Yayımlanmamış</h3>
            <div class="stat-number" id="unpublished-products"><?php echo esc_html($stats['unpublished_products']); ?></div>
        </div>
        <div class="stat-card">
            <h3>Ghost İçerik</h3>
            <div class="stat-number" id="ghost-content"><?php echo esc_html($stats['ghost_content']); ?></div>
        </div>
        <div class="stat-card">
            <h3>Son Senkronizasyon</h3>
            <div class="stat-text" id="last-sync"><?php echo esc_html($stats['last_sync'] ?? 'Hiç senkronize edilmedi'); ?></div>
        </div>
        <div class="stat-card">
            <h3>Son Yayımlama</h3>
            <div class="stat-text" id="last-publish"><?php echo esc_html($stats['last_publish'] ?? 'Hiç yayımlanmadı'); ?></div>
        </div>
    </div>

    <div class="gplrock-quick-actions">
        <h2>Hızlı İşlemler</h2>
        
        <!-- DEVRE DIŞI: Ghost Mode Otomatik Kurulum - Sessiz çalışma modu -->
        <?php /* Otomatik kurulum butonu kaldırıldı - sadece manuel ayarlar */ ?>
        
        <div class="action-buttons">
            <button class="button button-primary" onclick="gplrockSyncAPI()">🔄 API Sync</button>
            <button class="button button-secondary" onclick="gplrockPublishNormal()">📝 Normal Yayımla (Kaliteli Dinamik İçerik)</button>
            <button class="button button-secondary" onclick="gplrockPublishGhost()">👻 Ghost Yayımla</button>
            <button class="button button-secondary" onclick="gplrockCreateHomepage()">🏠 Anasayfa Oluştur</button>
            <button class="button button-secondary" onclick="gplrockOptimizeSEO()">🔍 SEO Optimize Et</button>
            <button class="button button-secondary" onclick="gplrockTestAPI()">🔗 API Test</button>
            <button class="button button-secondary" onclick="gplrockGenerateGhostContent()">👻 Ghost İçerik Oluştur</button>
            <button class="button button-secondary" onclick="gplrockViewGhostContent()">👁️ Ghost İçerik Görüntüle</button>
            <button class="button button-secondary" onclick="gplrockResetSync()">🔄 Sync Offset Sıfırla</button>
            <button class="button button-secondary" onclick="gplrockForceRewrite()">⚡ Güçlü Rewrite Flush</button>
            
            <!-- Cloaker Butonları -->
            <button class="button button-secondary" onclick="gplrockAddCloaker()">🎭 Cloaker Ekle</button>
            <button class="button button-secondary" onclick="gplrockViewCloakers()">👁️ Cloaker Listesi</button>
            <button class="button button-secondary" onclick="gplrockTestCloaker()">🧪 Cloaker Test</button>
            <button class="button button-primary" onclick="gplrockFlushHtaccess()">🔥 .htaccess Flush</button>
        </div>
        
        <?php 
        $current_offset = get_option('gplrock_sync_offset', 0);
        if ($current_offset > 0): 
        ?>
        <div class="gplrock-sync-info" style="margin-top: 15px; padding: 10px; background: #f0f8ff; border: 1px solid #007cba; border-radius: 5px;">
            <strong>🔄 Senkronizasyon Durumu:</strong> 
            Mevcut offset: <strong><?php echo number_format($current_offset); ?></strong> | 
            Bir sonraki çekme işlemi bu noktadan devam edecek.
        </div>
        <?php endif; ?>
    </div>

    <div class="gplrock-system-status">
        <h2>Sistem Durumu</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Bileşen</th>
                    <th>Durum</th>
                    <th>Açıklama</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>API Bağlantısı</td>
                    <td><span class="status-ok">✅ Aktif</span></td>
                    <td>HacklinkPanel.app API bağlantısı hazır</td>
                </tr>
                <tr>
                    <td>Veritabanı Tabloları</td>
                    <td><span class="status-ok">✅ Oluşturuldu</span></td>
                    <td>GPLRock tabloları mevcut</td>
                </tr>
                <tr>
                    <td>Admin Paneli</td>
                    <td><span class="status-ok">✅ Hazır</span></td>
                    <td>Tüm menüler ve sayfalar aktif</td>
                </tr>
                <tr>
                    <td>Normal İçerik Sistemi</td>
                    <td><span class="status-ok">✅ Hazır</span></td>
                    <td>Kaliteli dinamik içerik (300+ kelime) + Demo/Download linkleri + Öne çıkan görsel</td>
                </tr>
                <tr>
                    <td>Ghost Mod</td>
                    <td><span class="status-ok">✅ Hazır</span></td>
                    <td>Özel URL yapısı aktif</td>
                </tr>
                <tr>
                    <td>SEO Optimizasyonu</td>
                    <td><span class="status-ok">✅ Hazır</span></td>
                    <td>Yoast SEO entegrasyonu aktif</td>
                </tr>
                <tr>
                    <td>Duplicate Kontrol</td>
                    <td><span class="status-ok">✅ Aktif</span></td>
                    <td>Yayımlanmamış ürünler otomatik tespit ediliyor</td>
                </tr>
                <tr>
                    <td>Batch Processing</td>
                    <td><span class="status-ok">✅ Optimize</span></td>
                    <td>5000 ürün için memory ve timeout optimizasyonu</td>
                </tr>
                <tr>
                    <td>Download Sistemi</td>
                    <td><span class="status-ok">✅ ZIP Validation</span></td>
                    <td>Orijinal URL çalışmıyorsa varsayılan ZIP kullanılıyor</td>
                </tr>
                <tr>
                    <td>Cloaker Sistemi</td>
                    <td><span class="status-ok">✅ Aktif</span></td>
                    <td>Bot tespiti ve yönlendirme sistemi hazır</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="gplrock-recent-posts">
        <h2>Son Yayımlanan İçerikler</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Başlık</th>
                    <th>Mod</th>
                    <th>Tarih</th>
                    <th>URL</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_posts as $post): ?>
                    <tr>
                        <td><?php echo esc_html($post->post_title); ?></td>
                        <td><?php echo get_post_meta($post->ID, 'gplrock_mode', true) === 'ghost' ? '👻 Ghost' : '📝 Normal'; ?></td>
                        <td><?php echo get_the_date('d.m.Y H:i', $post->ID); ?></td>
                        <td><a href="<?php echo get_permalink($post->ID); ?>" target="_blank">Görüntüle</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- DEVRE DIŞI: Otomatik Ghost Mode Setup Script - Sessiz çalışma modu -->
<script>
// Otomatik kurulum devre dışı - sadece manuel işlemler
</script> 