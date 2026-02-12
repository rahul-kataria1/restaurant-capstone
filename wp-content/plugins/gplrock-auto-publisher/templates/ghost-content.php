<div class="ghost-product">
    <h1><?php echo esc_html($title); ?></h1>
    <div class="meta">
        <span class="category"><?php echo esc_html($category); ?></span>
        <span class="version">v<?php echo esc_html($version); ?></span>
        <span class="rating">⭐ <?php echo esc_html($rating); ?></span>
        <span class="downloads">⬇️ <?php echo esc_html($downloads_count); ?></span>
    </div>
    <div class="product-image">
        <?php if ($image_url): ?>
            <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($title); ?>" />
        <?php endif; ?>
    </div>
    <div class="description">
        <?php echo wpautop($description); ?>
    </div>
    <?php if (!empty($features)): ?>
    <div class="features">
        <h3>Özellikler</h3>
        <ul>
            <?php foreach ($features as $feature): ?>
                <li><?php echo esc_html($feature); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    <div class="download">
        <?php if ($download_url): ?>
            <a href="<?php echo esc_url($download_url); ?>" class="download-btn" target="_blank">İndir</a>
        <?php endif; ?>
    </div>
    <div class="ghost-seo">
        <p><strong>Not:</strong> Bu içerik GPL lisansı altındadır ve ticari kullanıma uygundur.</p>
        <p><strong>Son Güncelleme:</strong> <?php echo esc_html($updated_at); ?></p>
    </div>
</div> 