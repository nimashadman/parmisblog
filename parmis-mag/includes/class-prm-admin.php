<?php
/**
 * Magazine settings and an explicitly requested draft preview.
 *
 * @package Parmis_Mag
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PRM_Magazine_Admin {

	const OPTION = 'prm_magazine_settings';
	const PAGE   = 'prm-magazine';
	const PREVIEW_META = '_prm_magazine_preview_page';

	/** Register hooks without changing site settings or content. */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_prm_create_preview', array( __CLASS__, 'create_preview' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( PRM_MAG_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function admin_menu() {
		add_options_page(
			'مجله پارمیس',
			'مجله پارمیس',
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register_settings() {
		register_setting(
			'prm_magazine',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => PRM_Magazine::defaults(),
			)
		);
	}

	/** Return only public taxonomies attached to the configured post type. */
	private static function taxonomies( $post_type ) {
		$taxonomies = get_object_taxonomies( $post_type, 'objects' );
		foreach ( $taxonomies as $slug => $taxonomy ) {
			if ( empty( $taxonomy->public ) || ! in_array( $post_type, (array) $taxonomy->object_type, true ) ) {
				unset( $taxonomies[ $slug ] );
			}
		}
		return $taxonomies;
	}

	private static function article_id( $value, $post_type ) {
		$id = is_scalar( $value ) ? absint( $value ) : 0;
		return $id && PRM_Magazine::is_public_article( $id, $post_type ) ? $id : 0;
	}

	private static function article_slots( $values, $count, $post_type ) {
		$values = is_array( $values ) ? array_values( $values ) : array();
		$result = array();
		for ( $index = 0; $index < $count; $index++ ) {
			$result[] = self::article_id( isset( $values[ $index ] ) ? $values[ $index ] : 0, $post_type );
		}
		return $result;
	}

	private static function term_ids( $values, $taxonomy ) {
		if ( ! $taxonomy || ! is_array( $values ) ) {
			return array();
		}
		$ids = array();
		foreach ( array_slice( $values, 0, 500 ) as $value ) {
			$id = is_scalar( $value ) ? absint( $value ) : 0;
			if ( $id ) {
				$ids[ $id ] = $id;
			}
		}
		if ( ! $ids ) {
			return array();
		}
		$valid = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'include'    => array_values( $ids ),
				'hide_empty' => false,
				'fields'     => 'ids',
				'number'     => 30,
			)
		);
		return is_wp_error( $valid ) ? array() : array_map( 'absint', $valid );
	}

	/** Keep a typed, bounded settings structure; never accept arbitrary query keys. */
	public static function sanitize_settings( $input ) {
		$input  = is_array( $input ) ? $input : array();
		$result = PRM_Magazine::defaults();
		$type   = isset( $input['post_type'] ) && is_string( $input['post_type'] ) ? sanitize_key( $input['post_type'] ) : $result['post_type'];
		$result['post_type'] = PRM_Magazine::valid_post_type( $type ) ? $type : $result['post_type'];
		$type = $result['post_type'];

		$requested  = isset( $input['taxonomy'] ) && is_string( $input['taxonomy'] ) ? sanitize_key( $input['taxonomy'] ) : '';
		$taxonomies = self::taxonomies( $type );
		$taxonomy   = isset( $taxonomies[ $requested ] ) ? $requested : PRM_Magazine::taxonomy_for_type( $type, '' );
		$taxonomy   = is_string( $taxonomy ) && isset( $taxonomies[ $taxonomy ] ) ? $taxonomy : '';
		$result['taxonomy'] = $taxonomy;

		foreach ( array( 'section_buying', 'section_start', 'section_skills', 'section_checklists' ) as $key ) {
			$result[ $key ] = self::term_ids( isset( $input[ $key ] ) ? $input[ $key ] : array(), $taxonomy );
		}
		$result['featured']  = self::article_id( isset( $input['featured'] ) ? $input['featured'] : 0, $type );
		$result['secondary'] = self::article_slots( isset( $input['secondary'] ) ? $input['secondary'] : array(), 2, $type );
		$result['journey']   = self::article_slots( isset( $input['journey'] ) ? $input['journey'] : array(), 4, $type );
		$result['buying']    = self::article_slots( isset( $input['buying'] ) ? $input['buying'] : array(), 3, $type );
		$result['override_posts_page'] = isset( $input['override_posts_page'] ) && is_scalar( $input['override_posts_page'] ) && '1' === (string) $input['override_posts_page'] && 'page' === get_option( 'show_on_front' ) && absint( get_option( 'page_for_posts' ) ) > 0 ? 1 : 0;
		return $result;
	}

	/** Latest 200 eligible articles, plus any still-valid manual selections. */
	private static function editorial_posts( $settings ) {
		$query = new WP_Query(
			array(
				'post_type'              => $settings['post_type'],
				'post_status'            => 'publish',
				'has_password'           => false,
				'posts_per_page'         => 200,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'ignore_sticky_posts'    => true,
				'no_found_rows'           => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		$posts = array();
		foreach ( $query->posts as $post ) {
			$posts[ $post->ID ] = $post;
		}
		$selected = array_merge( array( $settings['featured'] ), (array) $settings['secondary'], (array) $settings['journey'], (array) $settings['buying'] );
		foreach ( array_unique( $selected ) as $id ) {
			$id = self::article_id( $id, $settings['post_type'] );
			if ( $id && ! isset( $posts[ $id ] ) ) {
				$posts[ $id ] = get_post( $id );
			}
		}
		return $posts;
	}

	/** At most 500 displayed terms, keeping saved selections visible. */
	private static function section_terms( $taxonomy, $settings ) {
		if ( ! $taxonomy ) {
			return array();
		}
		$selected = array();
		foreach ( array( 'section_buying', 'section_start', 'section_skills', 'section_checklists' ) as $key ) {
			$selected = array_merge( $selected, (array) $settings[ $key ] );
		}
		$selected = array_slice( array_unique( array_map( 'absint', $selected ) ), 0, 120 );
		$terms    = array();
		if ( $selected ) {
			$saved = get_terms( array( 'taxonomy' => $taxonomy, 'include' => $selected, 'hide_empty' => false, 'number' => 120 ) );
			if ( ! is_wp_error( $saved ) ) {
				foreach ( $saved as $term ) {
					$terms[ $term->term_id ] = $term;
				}
			}
		}
		$available = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'number' => 500, 'orderby' => 'name', 'order' => 'ASC' ) );
		if ( ! is_wp_error( $available ) ) {
			foreach ( $available as $term ) {
				if ( count( $terms ) >= 500 ) {
					break;
				}
				$terms[ $term->term_id ] = $term;
			}
		}
		return $terms;
	}

	private static function article_select( $key, $value, $posts, $index = null ) {
		$name = self::OPTION . '[' . $key . ']' . ( null !== $index ? '[' . absint( $index ) . ']' : '' );
		$id   = 'prm-' . $key . ( null !== $index ? '-' . absint( $index ) : '' );
		echo '<select class="regular-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
		echo '<option value="0">انتخاب خودکار</option>';
		foreach ( $posts as $post ) {
			$title = get_the_title( $post );
			$title = $title ? $title : '(بدون عنوان)';
			echo '<option value="' . esc_attr( $post->ID ) . '"' . selected( absint( $value ), $post->ID, false ) . '>' . esc_html( $title . ' — #' . $post->ID ) . '</option>';
		}
		echo '</select>';
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( 'دسترسی به تنظیمات مجله مجاز نیست.' ), '', array( 'response' => 403 ) );
		}
		$settings   = PRM_Magazine::get_settings();
		$types      = PRM_Magazine::post_types();
		$taxonomies = self::taxonomies( $settings['post_type'] );
		$taxonomy   = PRM_Magazine::taxonomy_for_type( $settings['post_type'], $settings['taxonomy'] );
		$terms      = self::section_terms( $taxonomy, $settings );
		$posts      = self::editorial_posts( $settings );
		$posts_page = 'page' === get_option( 'show_on_front' ) ? absint( get_option( 'page_for_posts' ) ) : 0;
		?>
		<div class="wrap" dir="rtl">
			<h1>مجله پارمیس</h1>
			<?php settings_errors(); ?>
			<p>برای نمایش مجله، شورت‌کد <code dir="ltr">[parmis_mag]</code> را در یک برگه قرار دهید. قالب برگه را روی «مجلهٔ پارمیس (تمام‌صفحه)» یا «Elementor Canvas» تنظیم کنید.</p>
			<p>دکمهٔ پیش‌نمایش فقط یک برگهٔ پیش‌نویس می‌سازد؛ صفحهٔ فعلی مجله و تنظیمات خواندن وردپرس تغییر نمی‌کنند.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
				<?php settings_fields( 'prm_magazine' ); ?>
				<h2>منبع مقاله‌ها</h2>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="prm-post-type">نوع نوشته</label></th><td>
						<select id="prm-post-type" name="<?php echo esc_attr( self::OPTION ); ?>[post_type]">
						<?php foreach ( $types as $slug => $type ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $settings['post_type'], $slug ); ?>><?php echo esc_html( $type->labels->name . ' (' . $slug . ')' ); ?></option>
						<?php endforeach; ?>
						</select>
						<p class="description">در سایت پارمیس، مقاله‌ها از نوع «نوشته‌ها (post)» هستند. پس از تغییر نوع نوشته، ابتدا ذخیره کنید تا فهرست دسته‌بندی و مقاله‌ها به‌روز شود.</p>
					</td></tr>
					<tr><th scope="row"><label for="prm-taxonomy">طبقه‌بندی مقاله‌ها</label></th><td>
						<select id="prm-taxonomy" name="<?php echo esc_attr( self::OPTION ); ?>[taxonomy]">
							<option value="">انتخاب خودکار</option>
							<?php foreach ( $taxonomies as $slug => $tax ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $settings['taxonomy'], $slug ); ?>><?php echo esc_html( $tax->labels->name . ' (' . $slug . ')' ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description">فقط طبقه‌بندی‌های عمومی متصل به نوع نوشتهٔ انتخاب‌شده نمایش داده می‌شوند. دسته‌های فعلی سایت از نوع category هستند.</p>
					</td></tr>
				</table>

				<h2>دسته‌های بخش‌های مجله</h2>
				<p>انتخاب هر بخش اختیاری است. خالی‌گذاشتن یعنی تشخیص خودکار بر اساس نامک دسته‌های موجود. در هر بخش حداکثر ۳۰ دسته ذخیره می‌شود؛ برای انتخاب چند مورد، کلید Ctrl یا Command را نگه دارید.</p>
				<table class="form-table" role="presentation">
					<?php foreach ( array( 'section_buying' => 'آموزش حسابداری', 'section_start' => 'شروع حسابداری', 'section_skills' => 'مدیریت مالی', 'section_checklists' => 'چک‌لیست حسابداری' ) as $key => $label ) : ?>
					<tr><th scope="row"><label for="prm-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td>
						<select multiple size="7" class="regular-text" id="prm-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( self::OPTION . '[' . $key . '][]' ); ?>" <?php disabled( empty( $terms ) ); ?>>
							<?php foreach ( $terms as $term ) : ?>
							<option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( in_array( (int) $term->term_id, array_map( 'intval', (array) $settings[ $key ] ), true ) ); ?>><?php echo esc_html( $term->name . ' (' . $term->slug . ')' ); ?></option>
							<?php endforeach; ?>
						</select>
						<?php if ( ! $terms ) : ?><p class="description">در طبقه‌بندی انتخاب‌شده دسته‌ای یافت نشد.</p><?php endif; ?>
					</td></tr>
					<?php endforeach; ?>
				</table>

				<h2>انتخاب‌های سردبیری</h2>
				<p>همهٔ انتخاب‌ها اختیاری‌اند؛ «انتخاب خودکار» محتوا را از مقاله‌های منتشرشده تعیین می‌کند. فهرست شامل ۲۰۰ مقالهٔ تازه و انتخاب‌های قبلی معتبر است. مقاله‌های پیش‌نویس، خصوصی و رمزدار نمایش داده نمی‌شوند.</p>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="prm-featured">مقالهٔ اصلی</label></th><td><?php self::article_select( 'featured', $settings['featured'], $posts ); ?></td></tr>
					<?php
					$groups = array(
						'secondary' => array( 'title' => 'دو مقالهٔ همراه', 'labels' => array( 'مقالهٔ همراه اول', 'مقالهٔ همراه دوم' ) ),
						'journey'   => array( 'title' => 'مسیر یادگیری حسابداری', 'labels' => array( 'گام اول', 'گام دوم', 'گام سوم', 'گام چهارم' ) ),
						'buying'    => array( 'title' => 'آموزش‌های حسابداری', 'labels' => array( 'آموزش حسابداری اول', 'آموزش حسابداری دوم', 'آموزش حسابداری سوم' ) ),
					);
					foreach ( $groups as $key => $group ) :
					?>
					<tr><th scope="row"><?php echo esc_html( $group['title'] ); ?></th><td><fieldset><legend class="screen-reader-text"><?php echo esc_html( $group['title'] ); ?></legend>
						<?php foreach ( $group['labels'] as $index => $label ) : ?>
						<p><label for="<?php echo esc_attr( 'prm-' . $key . '-' . $index ); ?>"><?php echo esc_html( $label ); ?></label><br><?php self::article_select( $key, isset( $settings[ $key ][ $index ] ) ? $settings[ $key ][ $index ] : 0, $posts, $index ); ?></p>
						<?php endforeach; ?>
					</fieldset></td></tr>
					<?php endforeach; ?>
				</table>

				<h2>نمایش در صفحهٔ فعلی نوشته‌ها</h2>
				<fieldset><legend class="screen-reader-text">جایگزینی نمای صفحهٔ نوشته‌ها</legend>
					<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[override_posts_page]" value="1" <?php checked( ! empty( $settings['override_posts_page'] ) ); ?> <?php disabled( ! $posts_page ); ?>> فعال‌کردن نمای مجله فقط برای برگهٔ فعلی نوشته‌ها در تنظیمات خواندن وردپرس</label>
					<?php if ( $posts_page ) : ?>
					<p class="description">برگهٔ نوشته‌های فعلی: <?php echo esc_html( get_the_title( $posts_page ) . ' (#' . $posts_page . ')' ); ?>. این گزینه به‌صورت پیش‌فرض خاموش است و آدرس نوشته‌ها یا تنظیم انتخاب برگهٔ نوشته‌ها را تغییر نمی‌دهد.</p>
					<?php else : ?>
					<p class="description">اکنون برگه‌ای برای نوشته‌ها تعیین نشده است؛ این گزینه غیرفعال است. ابتدا پیش‌نمایش پیش‌نویس را بررسی کنید.</p>
					<?php endif; ?>
				</fieldset>
				<?php submit_button( 'ذخیرهٔ تنظیمات مجله' ); ?>
			</form>

			<hr>
			<h2>پیش‌نمایش امن در یک برگهٔ پیش‌نویس</h2>
			<p>برای دیدن نتیجه، تنظیمات بالا را ذخیره کنید و سپس دکمهٔ زیر را بزنید. پیش‌نویس قبلی این دکمه فقط زمانی دوباره استفاده می‌شود که همچنان متعلق به شما باشد و محتوا و قالب آن تغییر نکرده باشد.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="prm_create_preview">
				<?php wp_nonce_field( 'prm_create_preview' ); ?>
				<?php submit_button( 'ساخت یا بازکردن پیش‌نمایش پیش‌نویس', 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/** Create only a draft requested through this administrator-only action. */
	public static function create_preview() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( 'اجازهٔ ساخت پیش‌نمایش مجله را ندارید.' ), '', array( 'response' => 403 ) );
		}
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			wp_die( esc_html( 'پیش‌نمایش را با دکمهٔ ساخت پیش‌نویس در تنظیمات مجله باز کنید.' ), '', array( 'response' => 405 ) );
		}
		check_admin_referer( 'prm_create_preview' );
		$author  = get_current_user_id();
		$page_id = absint( get_user_meta( $author, self::PREVIEW_META, true ) );
		$page    = $page_id ? get_post( $page_id ) : null;
		$reuse   = $page && 'page' === $page->post_type && 'draft' === $page->post_status && $author === (int) $page->post_author && '[parmis_mag]' === trim( $page->post_content ) && '' === $page->post_password && 'prm-magazine-canvas.php' === get_post_meta( $page_id, '_wp_page_template', true );
		if ( ! $reuse ) {
			$page_id = wp_insert_post(
				array(
					'post_type'      => 'page',
					'post_status'    => 'draft',
					'post_title'     => 'پیش‌نمایش مجلهٔ پارمیس',
					'post_content'   => '[parmis_mag]',
					'post_author'    => $author,
					'comment_status' => 'closed',
					'ping_status'    => 'closed',
					'meta_input'     => array( '_wp_page_template' => 'prm-magazine-canvas.php' ),
				),
				true
			);
			if ( is_wp_error( $page_id ) || ! $page_id ) {
				$message = is_wp_error( $page_id ) ? $page_id->get_error_message() : 'برگهٔ پیش‌نمایش ساخته نشد.';
				wp_die( esc_html( $message ), esc_html( 'خطای ساخت پیش‌نمایش' ), array( 'response' => 500 ) );
			}
			update_user_meta( $author, self::PREVIEW_META, $page_id );
		}
		$url = get_preview_post_link( $page_id );
		if ( ! $url ) {
			$url = get_edit_post_link( $page_id, 'raw' );
		}
		if ( $url && wp_safe_redirect( $url ) ) {
			exit;
		}
		$edit_url = get_edit_post_link( $page_id, 'raw' );
		if ( $edit_url && wp_safe_redirect( $edit_url ) ) {
			exit;
		}
		wp_die( esc_html( 'برگه ساخته شد، اما بازکردن پیش‌نمایش ممکن نشد. آن را از فهرست برگه‌های پیش‌نویس باز کنید.' ), '', array( 'response' => 500 ) );
	}

	public static function action_links( $links ) {
		$link = '<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ) . '">تنظیمات مجله</a>';
		array_unshift( $links, $link );
		return $links;
	}
}
