<div class="wrap">
    <h1>GPLRock Auto Publisher - İçerik Yöneticisi</h1>
    
    <!-- Menü Öğeleri -->
    <div class="gplrock-admin-menu" style="margin: 20px 0; padding: 15px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px;">
        <h2 style="margin-top: 0; margin-bottom: 15px; color: #495057;">📋 Admin Menüsü</h2>
        <div class="gplrock-menu-buttons" style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="<?php echo admin_url('admin.php?page=gplrock-dashboard'); ?>" class="button button-secondary" style="text-decoration: none;">
                🏠 Dashboard
            </a>
            <a href="<?php echo admin_url('admin.php?page=gplrock-settings'); ?>" class="button button-secondary" style="text-decoration: none;">
                ⚙️ Ayarlar
            </a>
            <a href="<?php echo admin_url('admin.php?page=gplrock-content'); ?>" class="button button-primary" style="text-decoration: none;">
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
    
    <div class="gplrock-content-actions">
        <button class="button button-primary" onclick="gplrockSyncAPI()">🔄 API'den Çek</button>
        <button class="button button-secondary" onclick="gplrockPublishNormal()">📝 Normal Yayımla</button>
        <button class="button button-secondary" onclick="gplrockPublishGhost()">👻 Ghost Yayımla</button>
    </div>

    <div class="gplrock-products-list">
        <h2>Veritabanındaki Ürünler</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Ürün ID</th>
                    <th>Başlık</th>
                    <th>Kategori</th>
                    <th>Versiyon</th>
                    <th>İndirme</th>
                    <th>Durum</th>
                    <th>Son Güncelleme</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?php echo esc_html($product->product_id); ?></td>
                        <td><?php echo esc_html($product->title); ?></td>
                        <td><?php echo esc_html($product->category); ?></td>
                        <td><?php echo esc_html($product->version); ?></td>
                        <td><?php echo esc_html($product->downloads_count); ?></td>
                        <td><?php echo esc_html($product->status); ?></td>
                        <td><?php echo esc_html($product->updated_at); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div> 