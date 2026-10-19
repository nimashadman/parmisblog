<?php
defined( 'ABSPATH' ) || exit;
$featured = $layout['featured'] ? self::article_data( $layout['featured'] ) : null;
$form_query = array();
wp_parse_str( (string) wp_parse_url( $base, PHP_URL_QUERY ), $form_query );
$form_action = preg_replace( '/[?#].*$/', '', $base );
$total = self::number( $query->found_posts );
$filter_text = array();
if ( 'all' !== $initial['section'] ) { $filter_text[] = 'موضوع: ' . $labels[ $initial['section'] ]; }
if ( '' !== $initial['q'] ) { $filter_text[] = 'جست‌وجو: «' . $initial['q'] . '»'; }
?>
<div id="prm-mag" class="prm-magazine" dir="rtl" lang="fa">
  <a class="skip-link" href="#prm-main">رفتن به محتوای مجله</a>
  <?php include PRM_MAG_DIR . 'templates/symbols.php'; ?>
  <?php if ( $chrome ) : ?>
  <header class="site-header"><div class="header-inner container">
    <a class="brand" href="#prm-mag" aria-label="مجلهٔ پارمیس؛ ابتدای صفحه"><span class="brand-symbol"><img class="brand-logo" src="<?php echo esc_url( PRM_MAG_URL . 'assets/parmis-logo.png' ); ?>" alt="" width="48" height="48"></span><span class="brand-name">پارمیس<small>مجلهٔ حسابداری و کسب‌وکار</small></span></a>
    <nav class="primary-nav" id="prm-primary-nav" aria-label="پیمایش مجله"><a href="#prm-discover">شروع مطالعه</a><a href="#prm-buying">آموزش حسابداری</a><a href="<?php echo esc_url( self::href( $base, 'skills' ) ); ?>" data-section="skills">مدیریت مالی</a><a href="#prm-checklist">چک‌لیست حسابداری</a></nav>
    <div class="header-actions"><a class="store-link" href="<?php echo esc_url( home_url( '/' ) ); ?>">وب‌سایت پارمیس<?php echo self::icon( 'external' ); ?></a><button class="menu-toggle icon-button" type="button" aria-controls="prm-primary-nav" aria-expanded="false" aria-label="باز کردن منوی مجله"><?php echo self::icon( 'menu' ); ?></button></div>
  </div></header>
  <?php endif; ?>

  <div id="prm-main" role="<?php echo $chrome ? 'main' : 'region'; ?>" aria-label="مجلهٔ پارمیس">
    <section class="mag-intro container" aria-labelledby="prm-mag-title"><div class="intro-copy"><p class="eyebrow"><span></span>حساب‌ها روشن، تصمیم‌ها مطمئن</p><h1 id="prm-mag-title">مجلهٔ پارمیس</h1><p class="intro-description">از اولین ثبت حسابداری تا تحلیل گزارش‌های مالی؛<br class="desktop-break"> دانش کاربردی برای مدیریت بهتر کسب‌وکار شما.</p></div>
      <form class="mag-search" id="prm-top-search" role="search" method="get" action="<?php echo esc_url( $form_action ); ?>">
        <?php foreach ( $form_query as $key => $value ) : if ( ! is_scalar( $value ) ) { continue; } ?>
        <input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>">
        <?php endforeach; ?>
        <input type="hidden" name="prm_section" value="<?php echo esc_attr( $initial['section'] ); ?>">
        <label for="prm-search-input">دنبال چه راهنمایی می‌گردید؟</label><div class="search-field"><?php echo self::icon( 'search' ); ?><input id="prm-search-input" name="prm_q" type="search" value="<?php echo esc_attr( $initial['q'] ); ?>" placeholder="مثلاً ترازنامه، ثبت سند یا جریان نقدی…" maxlength="120" autocomplete="off"><button type="submit" aria-label="جست‌وجو در مقاله‌ها"><?php echo self::icon( 'arrow' ); ?></button></div><span class="search-hint">جست‌وجو در مقاله‌های مجله</span>
      </form>
    </section>
    <div class="container"><nav class="topic-nav" aria-label="موضوع‌های مجله">
      <?php foreach ( $labels as $key => $label ) : ?>
      <a class="topic-button<?php echo $initial['section'] === $key ? ' is-active' : ''; ?>" data-section="<?php echo esc_attr( $key ); ?>" href="<?php echo esc_url( self::href( $base, $key, $initial['q'] ) ); ?>"<?php if ( $initial['section'] === $key ) { echo ' aria-current="true"'; } ?>><?php echo esc_html( $label ); ?></a>
      <?php endforeach; ?>
    </nav></div>

    <?php if ( $featured ) : ?>
    <section class="featured container" aria-label="پیشنهادهای مجله"><article class="feature-main"><div class="feature-copy"><span class="feature-label">از اینجا شروع کنید</span><h2><a href="<?php echo esc_url( $featured['url'] ); ?>"><?php echo esc_html( $featured['title'] ); ?></a></h2><p><?php echo esc_html( $featured['description'] ); ?></p><a class="feature-cta" href="<?php echo esc_url( $featured['url'] ); ?>">خواندن راهنما<?php echo self::icon( 'arrow' ); ?></a><span class="feature-note"><?php echo esc_html( $featured['category'] ); ?> · انتخاب تحریریه</span></div><a class="feature-image" href="<?php echo esc_url( $featured['url'] ); ?>" aria-label="<?php echo esc_attr( $featured['title'] ); ?>"><img src="<?php echo esc_url( $featured['image'] ); ?>" alt="<?php echo esc_attr( $featured['title'] ); ?>" width="650" height="750" fetchpriority="high" decoding="async"><span class="image-caption">یک قدم به مدیریت مالی بهتر</span></a></article>
      <div class="feature-side"><?php foreach ( $layout['secondary'] as $post ) : $item = self::article_data( $post ); ?>
        <article class="side-story"><a class="side-image" href="<?php echo esc_url( $item['url'] ); ?>" aria-label="<?php echo esc_attr( $item['title'] ); ?>"><img src="<?php echo esc_url( $item['image'] ); ?>" alt="<?php echo esc_attr( $item['title'] ); ?>" width="400" height="300" loading="lazy" decoding="async"></a><div class="side-copy"><span class="article-category"><?php echo esc_html( $item['category'] ); ?></span><h3><a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a></h3><a class="story-link" href="<?php echo esc_url( $item['url'] ); ?>">خواندن راهنما<?php echo self::icon( 'arrow' ); ?></a></div></article>
      <?php endforeach; ?></div>
    </section>
    <?php endif; ?>

    <section class="discover container section-space" id="prm-discover" aria-labelledby="prm-discover-title"><div class="section-heading"><div><p class="eyebrow">راهنما را از نیازتان پیدا کنید</p><h2 id="prm-discover-title">برای حساب‌های کسب‌وکارتان، از کجا شروع کنیم؟</h2></div></div><div class="need-grid">
      <a class="need-item" href="<?php echo esc_url( self::href( $base, 'start' ) ); ?>" data-section="start"><span class="need-icon"><?php echo self::icon( 'tent' ); ?></span><span><strong>تازه وارد حسابداری شده‌ام</strong><small>مفاهیم پایه و اولین ثبت‌های حسابداری</small></span><?php echo self::icon( 'arrow' ); ?></a>
      <a class="need-item" href="<?php echo esc_url( self::href( $base, 'buying' ) ); ?>" data-section="buying"><span class="need-icon"><?php echo self::icon( 'backpack' ); ?></span><span><strong>ثبت و گزارش حسابداری</strong><small>آموزش‌های حسابداری برای تصمیم بهتر</small></span><?php echo self::icon( 'arrow' ); ?></a>
      <a class="need-item" href="<?php echo esc_url( self::href( $base, 'skills' ) ); ?>" data-section="skills"><span class="need-icon"><?php echo self::icon( 'shield' ); ?></span><span><strong>مدیریت مالی بهتر</strong><small>بودجه‌بندی، نقدینگی و تحلیل عملکرد</small></span><?php echo self::icon( 'arrow' ); ?></a>
    </div></section>

    <?php if ( $layout['journey'] ) : ?>
    <section class="journey-wrap section-space" aria-labelledby="prm-journey-title"><div class="container journey-section"><div class="journey-intro"><p class="eyebrow">یک مسیر برای شروع</p><h2 id="prm-journey-title">حسابداری، از پایه،<br> قدم‌به‌قدم.</h2><p>از مفاهیم پایه شروع کنید و قدم‌به‌قدم به گزارش‌های قابل اتکا برسید.</p></div><ol class="journey-list">
      <?php foreach ( $layout['journey'] as $index => $post ) : ?>
      <li class="journey-step"><span class="step-number"><?php echo esc_html( self::number( $index + 1 ) ); ?></span><div><span class="step-label">قدم <?php echo esc_html( self::number( $index + 1 ) ); ?></span><h3><a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a></h3></div></li>
      <?php endforeach; ?>
    </ol></div></section>
    <?php endif; ?>

    <?php if ( $layout['buying'] ) : ?>
    <section class="buying container section-space" id="prm-buying" aria-labelledby="prm-buying-title"><div class="section-heading"><div><p class="eyebrow">با شناخت بیشتر انتخاب کنید</p><h2 id="prm-buying-title">حسابداری را کاربردی یاد بگیرید</h2></div><a class="text-link" data-section="buying" href="<?php echo esc_url( self::href( $base, 'buying' ) ); ?>">همهٔ آموزش‌های حسابداری<?php echo self::icon( 'arrow' ); ?></a></div><div class="buying-grid"><?php foreach ( $layout['buying'] as $post ) { echo self::card( $post ); } ?></div></section>
    <?php endif; ?>

    <section class="container section-space" id="prm-checklist" aria-labelledby="prm-checklist-title"><div class="checklist-banner"><div class="checklist-art"><?php echo self::icon( 'backpack' ); ?><span class="checklist-stamp">حساب‌ها آماده‌اند؟</span></div><div class="checklist-copy"><p class="eyebrow">پیش از پایان ماه</p><h2 id="prm-checklist-title">چیزی از قلم نیفتد.</h2><p>ثبت‌ها، مانده‌ها و مدارک را یک‌بار مرور کنید؛<br class="desktop-break"> تیک بزنید، یادداشت کنید و با خیال راحت جمع‌بندی کنید.</p></div><button class="button button-light" id="prm-open-checklist" type="button">چک‌لیست بستن حساب‌های ماهانه<?php echo self::icon( 'arrow' ); ?></button></div></section>

    <section class="articles container section-space" id="prm-articles" aria-labelledby="prm-articles-title"><div class="section-heading"><div><p class="eyebrow">بیشتر بدانید، دقیق‌تر تصمیم بگیرید</p><h2 id="prm-articles-title"><?php echo $filtered ? 'راهنماهای مرتبط' : 'تازه‌های مجله'; ?></h2></div><span class="result-status" id="prm-result-status" role="status" aria-live="polite"><?php echo esc_html( $total . ' مقاله' ); ?></span></div>
      <div class="results-toolbar" id="prm-results-toolbar"<?php echo $filtered ? '' : ' hidden'; ?>><p id="prm-filter-description"><?php echo esc_html( implode( ' · ', $filter_text ) ); ?></p><a class="text-link" id="prm-reset-filters" href="<?php echo esc_url( self::href( $base ) ); ?>">نمایش همهٔ مقاله‌ها<?php echo self::icon( 'close' ); ?></a></div>
      <div class="article-grid" id="prm-article-grid"><?php foreach ( $query->posts as $post ) { echo self::card( $post ); } ?></div>
      <div class="empty-state" id="prm-empty-state"<?php echo $query->posts ? ' hidden' : ''; ?>><?php echo self::icon( 'search' ); ?><h3>راهنمایی با این عبارت پیدا نشد.</h3><p>عبارت کوتاه‌تری بنویسید یا موضوع دیگری انتخاب کنید.</p><a class="button" id="prm-empty-reset" href="<?php echo esc_url( self::href( $base ) ); ?>">همهٔ مقاله‌ها</a></div>
      <div class="more-wrap"><a class="button button-outline" id="prm-load-more" href="<?php echo esc_url( self::href( $base, $initial['section'], $initial['q'], $initial['page'] + 1 ) ); ?>"<?php echo $config['initial']['has_more'] ? '' : ' hidden'; ?>>نمایش مقاله‌های بیشتر<?php echo self::icon( 'arrow' ); ?></a></div>
      <noscript><p class="search-hint">جست‌وجو و دسته‌بندی‌ها بدون جاوااسکریپت هم کار می‌کنند؛ چک‌لیست تعاملی به فعال‌بودن جاوااسکریپت نیاز دارد.</p></noscript>
    </section>
  </div>

  <?php if ( $chrome ) : ?>
  <footer class="site-footer"><div class="container footer-main"><div class="footer-brand"><a class="brand" href="#prm-mag"><span class="brand-symbol"><img class="brand-logo" src="<?php echo esc_url( PRM_MAG_URL . 'assets/parmis-logo.png' ); ?>" alt="" width="48" height="48"></span><span class="brand-name">پارمیس<small>حساب‌ها روشن، تصمیم‌ها مطمئن</small></span></a><p>آموزش حسابداری و مدیریت مالی؛<br>برای رشد آگاهانهٔ کسب‌وکار شما.</p></div><div class="footer-links"><span>مسیرهای مطالعه</span><a href="#prm-discover">از کجا شروع کنم؟</a><a href="<?php echo esc_url( self::href( $base, 'buying' ) ); ?>" data-section="buying">آموزش‌های حسابداری</a><a href="#prm-checklist">چک‌لیست حسابداری</a></div><div class="footer-links"><span>پارمیس</span><a href="<?php echo esc_url( home_url( '/' ) ); ?>">وب‌سایت پارمیس</a><a href="<?php echo esc_url( home_url( '/about/' ) ); ?>">دربارهٔ ما</a><a href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>">تماس با ما</a></div><div class="footer-signoff"><?php echo self::icon( 'mountain' ); ?><p>پشت هر تصمیم خوب،<br>حسابی روشن است.</p><a href="#prm-mag">بازگشت به بالا ↑</a></div></div><div class="container footer-bottom"><span>مجلهٔ پارمیس</span><a href="#prm-mag">حساب‌ها روشن، تصمیم‌ها مطمئن<?php echo self::icon( 'mountain' ); ?></a></div></footer>
  <?php endif; ?>
  <?php include PRM_MAG_DIR . 'templates/checklist.php'; ?>
  <script type="application/json" id="prm-config"><?php echo wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script>
</div>
