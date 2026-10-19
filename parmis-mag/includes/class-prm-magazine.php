<?php
defined( 'ABSPATH' ) || exit;

final class PRM_Magazine {
    private static $layout_cache = null;
    private static $rendered = false;

    public static function init() {
        add_shortcode( 'parmis_mag', array( __CLASS__, 'shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue' ) );
        add_action( 'rest_api_init', array( __CLASS__, 'rest_routes' ) );
        add_filter( 'theme_page_templates', array( __CLASS__, 'page_templates' ) );
        add_filter( 'template_include', array( __CLASS__, 'template' ), 50 );
        add_filter( 'posts_search', array( __CLASS__, 'persian_search' ), 20, 2 );
    }

    public static function defaults() {
        return array(
            'post_type' => 'post', 'taxonomy' => 'category',
            'section_buying' => array(), 'section_start' => array(),
            'section_skills' => array(), 'section_checklists' => array(),
            'featured' => 0, 'secondary' => array( 0, 0 ),
            'journey' => array( 0, 0, 0, 0 ), 'buying' => array( 0, 0, 0 ),
            'override_posts_page' => 0,
        );
    }

    public static function post_types() {
        $types = get_post_types( array( 'public' => true ), 'objects' );
        foreach ( $types as $name => $type ) {
            if ( 'attachment' === $name || ! is_post_type_viewable( $type ) ) {
                unset( $types[ $name ] );
            }
        }
        return $types;
    }

    public static function valid_post_type( $type ) {
        return is_string( $type ) && isset( self::post_types()[ $type ] );
    }

    public static function taxonomy_for_type( $type, $requested = '' ) {
        $taxonomies = get_object_taxonomies( $type, 'objects' );
        $available = array();
        foreach ( $taxonomies as $name => $taxonomy ) {
            if ( $taxonomy->public ) {
                $available[ $name ] = $taxonomy;
            }
        }
        if ( isset( $available[ $requested ] ) ) {
            return $requested;
        }
        if ( isset( $available['category'] ) ) {
            return 'category';
        }
        return $available ? (string) key( $available ) : '';
    }

    public static function get_settings() {
        $stored = get_option( 'prm_magazine_settings', array() );
        $settings = wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
        $settings['post_type'] = self::valid_post_type( $settings['post_type'] ) ? $settings['post_type'] : 'post';
        $settings['taxonomy'] = self::taxonomy_for_type( $settings['post_type'], $settings['taxonomy'] );
        foreach ( array( 'section_buying', 'section_start', 'section_skills', 'section_checklists', 'secondary', 'journey', 'buying' ) as $key ) {
            $settings[ $key ] = is_array( $settings[ $key ] ) ? array_map( 'absint', $settings[ $key ] ) : array();
        }
        $settings['featured'] = absint( $settings['featured'] );
        $settings['override_posts_page'] = empty( $settings['override_posts_page'] ) ? 0 : 1;
        return $settings;
    }

    public static function is_public_article( $id, $type = '' ) {
        $post = get_post( absint( $id ) );
        if ( ! $type ) {
            $type = self::get_settings()['post_type'];
        }
        return $post instanceof WP_Post && $post->post_type === $type
            && 'publish' === $post->post_status && '' === (string) $post->post_password
            && self::valid_post_type( $type );
    }

    public static function section_labels() {
        return array( 'all' => 'همهٔ مقاله‌ها', 'buying' => 'آموزش حسابداری', 'start' => 'شروع حسابداری', 'skills' => 'مدیریت مالی', 'checklists' => 'چک‌لیست حسابداری' );
    }

    public static function section_terms( $section ) {
        $settings = self::get_settings();
        $taxonomy = $settings['taxonomy'];
        if ( ! $taxonomy ) {
            return array();
        }
        $configured = isset( $settings[ 'section_' . $section ] ) ? $settings[ 'section_' . $section ] : array();
        $valid = array();
        foreach ( array_slice( $configured, 0, 30 ) as $id ) {
            $term = get_term( $id, $taxonomy );
            if ( $term && ! is_wp_error( $term ) ) {
                $valid[] = (int) $term->term_id;
            }
        }
        if ( $valid ) {
            return array_values( array_unique( $valid ) );
        }
        $slugs = array(
            'buying' => array( 'accounting' ),
            'skills' => array( 'financial-management', 'cash-flow' ),
            'checklists' => array( 'checklists', 'checklist' ),
            'start' => array( 'accounting-basics', 'basic-accounting' ),
        );
        foreach ( isset( $slugs[ $section ] ) ? $slugs[ $section ] : array() as $slug ) {
            $term = get_term_by( 'slug', $slug, $taxonomy );
            if ( $term ) {
                $valid[] = (int) $term->term_id;
            }
        }
        return array_values( array_unique( $valid ) );
    }

    /** Fixed public constraints are shared by lists, editorial slots and REST. */
    private static function base_query() {
        return array(
            'post_type' => self::get_settings()['post_type'], 'post_status' => 'publish',
            'has_password' => false, 'ignore_sticky_posts' => true,
            'orderby' => array( 'date' => 'DESC', 'ID' => 'DESC' ),
        );
    }

    public static function query_articles( $params = array() ) {
        $params = wp_parse_args( $params, array( 'section' => 'all', 'q' => '', 'page' => 1, 'per_page' => 6, 'exclude' => array() ) );
        $section = isset( self::section_labels()[ $params['section'] ] ) ? $params['section'] : 'all';
        $args = self::base_query();
        $args['posts_per_page'] = min( 12, max( 1, absint( $params['per_page'] ) ) );
        $args['paged'] = min( 1000, max( 1, absint( $params['page'] ) ) );
        $args['post__not_in'] = array_values( array_filter( array_map( 'absint', (array) $params['exclude'] ) ) );
        $search = self::clean_search( $params['q'] );
        if ( '' !== $search ) {
            $args['s'] = $search;
            $args['_prm_search'] = $search;
        }
        if ( 'all' !== $section ) {
            $terms = self::section_terms( $section );
            if ( $terms ) {
                $args['tax_query'] = array( array( 'taxonomy' => self::get_settings()['taxonomy'], 'field' => 'term_id', 'terms' => $terms, 'include_children' => true ) );
            } elseif ( 'start' === $section ) {
                $layout = self::layout();
                $ids = array_merge( $layout['featured'] ? array( $layout['featured']->ID ) : array(), wp_list_pluck( $layout['journey'], 'ID' ) );
                $args['post__in'] = $ids ? array_values( array_unique( $ids ) ) : array( 0 );
            } else {
                // An empty post__in means ALL in WP_Query; [0] deliberately means none.
                $args['post__in'] = array( 0 );
            }
        }
        return new WP_Query( $args );
    }

    public static function clean_search( $value ) {
        $value = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
        $value = str_replace( array( 'ي', 'ك', "\u{200C}" ), array( 'ی', 'ک', ' ' ), $value );
        $value = preg_replace( '/[\x{064B}-\x{065F}\x{0670}\x{0640}\x{200D}\x{200E}\x{200F}]/u', '', $value );
        $value = trim( preg_replace( '/\s+/u', ' ', $value ) );
        return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 120, 'UTF-8' ) : substr( $value, 0, 120 );
    }

    /** Apply only to this plugin's article queries, preserving core visibility constraints. */
    public static function persian_search( $search, $query ) {
        global $wpdb;
        $value = $query->get( '_prm_search' );
        if ( ! is_string( $value ) || '' === $value ) { return $search; }
        $terms = preg_split( '/\s+/u', self::clean_search( $value ), -1, PREG_SPLIT_NO_EMPTY );
        $columns = array();
        foreach ( array( 'post_title', 'post_excerpt', 'post_content' ) as $field ) {
            $column = "$wpdb->posts.$field";
            // Normalize both stored text and query; removing spaces also matches صورت‌حساب / صورتحساب.
            $column = "REPLACE(REPLACE($column, 'ي', 'ی'), 'ك', 'ک')";
            $remove = array( ' ', "\u{200C}", "\u{200D}", "\u{200E}", "\u{200F}", "\u{0640}", "\t", "\n", "\r" );
            for ( $code = 0x064B; $code <= 0x065F; $code++ ) { $remove[] = html_entity_decode( '&#' . $code . ';', ENT_NOQUOTES, 'UTF-8' ); }
            $remove[] = "\u{0670}";
            foreach ( $remove as $character ) { $column = $wpdb->prepare( "REPLACE($column, %s, '')", $character ); }
            $columns[] = $column;
        }
        $clauses = array();
        foreach ( $terms as $term ) {
            $like = '%' . $wpdb->esc_like( $term ) . '%';
            $parts = array();
            foreach ( $columns as $column ) { $parts[] = $wpdb->prepare( "$column LIKE %s", $like ); }
            $clauses[] = '(' . implode( ' OR ', $parts ) . ')';
        }
        return $clauses ? ' AND (' . implode( ' AND ', $clauses ) . ')' : $search;
    }

    private static function preferred_post( $id, $slug, $exclude = array() ) {
        $type = self::get_settings()['post_type'];
        if ( $id && self::is_public_article( $id, $type ) && ! in_array( (int) $id, $exclude, true ) ) {
            return get_post( $id );
        }
        if ( $slug ) {
            $args = self::base_query();
            $args['name'] = $slug;
            $args['posts_per_page'] = 1;
            $args['no_found_rows'] = true;
            $args['post__not_in'] = $exclude;
            $query = new WP_Query( $args );
            if ( $query->posts ) {
                return $query->posts[0];
            }
        }
        return null;
    }

    public static function layout() {
        if ( null !== self::$layout_cache ) {
            return self::$layout_cache;
        }
        $settings = self::get_settings();
        $latest = self::query_articles( array( 'per_page' => 12 ) )->posts;
        $featured = self::preferred_post( $settings['featured'], 'accounting-basics' );
        if ( ! $featured && $latest ) {
            $featured = $latest[0];
        }
        $secondary = array();
        $excluded = $featured ? array( (int) $featured->ID ) : array();
        foreach ( array( 'bookkeeping-guide', 'financial-statements' ) as $i => $slug ) {
            $post = self::preferred_post( isset( $settings['secondary'][ $i ] ) ? $settings['secondary'][ $i ] : 0, $slug, $excluded );
            if ( ! $post ) {
                foreach ( $latest as $candidate ) {
                    if ( ! in_array( (int) $candidate->ID, $excluded, true ) ) {
                        $post = $candidate;
                        break;
                    }
                }
            }
            if ( $post ) {
                $secondary[] = $post;
                $excluded[] = (int) $post->ID;
            }
        }
        $journey = array();
        $journey_ids = array();
        foreach ( array( 'accounting-basics', 'bookkeeping-guide', 'chart-of-accounts', 'month-end-checklist' ) as $i => $slug ) {
            $post = self::preferred_post( isset( $settings['journey'][ $i ] ) ? $settings['journey'][ $i ] : 0, $slug, $journey_ids );
            if ( $post ) {
                $journey[] = $post;
                $journey_ids[] = (int) $post->ID;
            }
        }
        $buying = array();
        $buying_ids = array();
        foreach ( array( 'bookkeeping-guide', 'financial-statements', 'accounting-software-guide' ) as $i => $slug ) {
            $post = self::preferred_post( isset( $settings['buying'][ $i ] ) ? $settings['buying'][ $i ] : 0, $slug, $buying_ids );
            if ( $post ) {
                $buying[] = $post;
                $buying_ids[] = (int) $post->ID;
            }
        }
        if ( count( $buying ) < 3 ) {
            $more = self::query_articles( array( 'section' => 'buying', 'per_page' => 3, 'exclude' => $buying_ids ) )->posts;
            $buying = array_slice( array_merge( $buying, $more ), 0, 3 );
        }
        self::$layout_cache = compact( 'featured', 'secondary', 'journey', 'buying' );
        return self::$layout_cache;
    }

    public static function article_data( $post ) {
        $settings = self::get_settings();
        $terms = $settings['taxonomy'] ? get_the_terms( $post, $settings['taxonomy'] ) : array();
        $category = 'مجلهٔ پارمیس';
        if ( $terms && ! is_wp_error( $terms ) ) {
            $category = $terms[0]->name;
            foreach ( array( 'buying', 'checklists', 'skills' ) as $section ) {
                if ( array_intersect( wp_list_pluck( $terms, 'term_id' ), self::section_terms( $section ) ) ) {
                    $category = self::section_labels()[ $section ];
                    break;
                }
            }
        }
        $plain = $post->post_excerpt ? $post->post_excerpt : $post->post_content;
        // Never execute article shortcodes, blocks, embeds or content filters here.
        $plain = wp_strip_all_tags( strip_shortcodes( $plain ), true );
        $plain = html_entity_decode( $plain, ENT_QUOTES, get_bloginfo( 'charset' ) ?: 'UTF-8' );
        $image = get_the_post_thumbnail_url( $post, 'large' );
        return array(
            'id' => (int) $post->ID, 'title' => wp_strip_all_tags( get_the_title( $post ) ),
            'url' => esc_url_raw( get_permalink( $post ) ),
            'image' => esc_url_raw( $image ?: PRM_MAG_URL . 'assets/fallback.svg' ),
            'category' => wp_strip_all_tags( $category ),
            'date' => get_the_date( get_option( 'date_format' ), $post ),
            'description' => wp_trim_words( $plain, 24, '…' ),
        );
    }

    public static function rest_routes() {
        register_rest_route( 'parmis-mag/v1', '/articles', array(
            'methods' => WP_REST_Server::READABLE,
            'permission_callback' => '__return_true',
            'callback' => array( __CLASS__, 'rest_articles' ),
            'args' => array(
                'section' => array( 'type' => 'string', 'enum' => array_keys( self::section_labels() ), 'default' => 'all' ),
                'q' => array( 'type' => 'string', 'maxLength' => 120, 'default' => '', 'sanitize_callback' => array( __CLASS__, 'clean_search' ) ),
                'page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 1000, 'default' => 1 ),
            ),
        ) );
    }

    public static function rest_articles( WP_REST_Request $request ) {
        $query = self::query_articles( array( 'section' => $request['section'], 'q' => $request['q'], 'page' => $request['page'] ) );
        return rest_ensure_response( array(
            'items' => array_map( array( __CLASS__, 'article_data' ), $query->posts ),
            'total' => (int) $query->found_posts, 'page' => (int) $request['page'],
            'pages' => (int) $query->max_num_pages,
            'has_more' => (int) $request['page'] < (int) $query->max_num_pages,
        ) );
    }

    private static function initial_request() {
        $section = isset( $_GET['prm_section'] ) && is_string( $_GET['prm_section'] ) ? sanitize_key( wp_unslash( $_GET['prm_section'] ) ) : 'all';
        $q = isset( $_GET['prm_q'] ) ? self::clean_search( wp_unslash( $_GET['prm_q'] ) ) : '';
        $page = isset( $_GET['prm_page'] ) && is_scalar( $_GET['prm_page'] ) ? absint( $_GET['prm_page'] ) : max( 1, absint( get_query_var( 'paged' ) ) );
        return array( 'section' => isset( self::section_labels()[ $section ] ) ? $section : 'all', 'q' => $q, 'page' => min( 1000, max( 1, $page ) ) );
    }

    public static function base_url() {
        if ( is_home() && get_option( 'page_for_posts' ) ) {
            $url = get_permalink( (int) get_option( 'page_for_posts' ) );
        } else {
            $post = get_queried_object();
            if ( $post instanceof WP_Post ) {
                $url = 'publish' !== $post->post_status && current_user_can( 'edit_post', $post->ID ) ? get_preview_post_link( $post ) : get_permalink( $post );
            } else {
                $url = home_url( '/' );
            }
        }
        return remove_query_arg( array( 'prm_q', 'prm_page', 'prm_section' ), $url );
    }

    public static function href( $base, $section = 'all', $q = '', $page = 1 ) {
        $args = array();
        if ( 'all' !== $section ) { $args['prm_section'] = $section; }
        if ( '' !== $q ) { $args['prm_q'] = $q; }
        if ( $page > 1 ) { $args['prm_page'] = $page; }
        return add_query_arg( $args, $base ) . '#prm-articles';
    }

    public static function icon( $name ) {
        return '<svg class="icon" aria-hidden="true"><use href="#prm-i-' . esc_attr( $name ) . '"></use></svg>';
    }

    public static function number( $value ) {
        return strtr( number_format_i18n( $value ), array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) );
    }

    public static function card( $post ) {
        $item = self::article_data( $post );
        ob_start();
        ?>
        <article class="article-card">
          <a class="article-image" href="<?php echo esc_url( $item['url'] ); ?>" aria-label="<?php echo esc_attr( $item['title'] ); ?>"><img src="<?php echo esc_url( $item['image'] ); ?>" alt="<?php echo esc_attr( $item['title'] ); ?>" width="800" height="520" loading="lazy" decoding="async"></a>
          <div class="article-content"><div class="article-meta"><span class="article-category"><?php echo esc_html( $item['category'] ); ?></span><time><?php echo esc_html( $item['date'] ); ?></time></div><h3><a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a></h3><p class="article-excerpt"><?php echo esc_html( $item['description'] ); ?></p><a class="story-link" href="<?php echo esc_url( $item['url'] ); ?>">خواندن مقاله<?php echo self::icon( 'arrow' ); ?></a></div>
        </article>
        <?php
        return ob_get_clean();
    }

    public static function page_templates( $templates ) {
        $templates['prm-magazine-canvas.php'] = 'مجلهٔ پارمیس (تمام‌صفحه)';
        return $templates;
    }

    private static function replacing_posts_page() {
        return ! is_admin() && ! is_feed() && ! is_embed() && is_home() && ! is_front_page()
            && 'page' === get_option( 'show_on_front' )
            && (int) get_option( 'page_for_posts' ) > 0 && self::get_settings()['override_posts_page'];
    }

    public static function template( $template ) {
        $canvas = is_singular( 'page' ) && 'prm-magazine-canvas.php' === get_page_template_slug( get_queried_object_id() );
        return $canvas || self::replacing_posts_page() ? PRM_MAG_DIR . 'templates/canvas.php' : $template;
    }

    public static function enqueue() {
        wp_enqueue_style( 'prm-magazine', PRM_MAG_URL . 'assets/magazine.css', array(), PRM_MAG_VERSION . '.' . filemtime( PRM_MAG_DIR . 'assets/magazine.css' ) );
        wp_enqueue_script( 'prm-magazine', PRM_MAG_URL . 'assets/magazine.js', array(), PRM_MAG_VERSION . '.' . filemtime( PRM_MAG_DIR . 'assets/magazine.js' ), true );
    }

    public static function maybe_enqueue() {
        $post = get_queried_object();
        $needed = self::replacing_posts_page();
        if ( $post instanceof WP_Post ) {
            $needed = $needed || 'prm-magazine-canvas.php' === get_page_template_slug( $post ) || has_shortcode( $post->post_content, 'parmis_mag' );
            if ( ! $needed && defined( 'ELEMENTOR_VERSION' ) ) {
                $elementor = get_post_meta( $post->ID, '_elementor_data', true );
                $needed = is_string( $elementor ) && false !== strpos( $elementor, '[parmis_mag' );
            }
        }
        if ( $needed ) { self::enqueue(); }
    }

    public static function shortcode( $attributes = array() ) {
        if ( self::$rendered ) { return '<!-- parmis_mag: one instance per page -->'; }
        self::$rendered = true;
        $attributes = shortcode_atts( array( 'chrome' => 'on' ), $attributes, 'parmis_mag' );
        $chrome = ! in_array( strtolower( (string) $attributes['chrome'] ), array( 'off', '0', 'no' ), true );
        self::enqueue();
        $initial = self::initial_request();
        $query = self::query_articles( $initial );
        $layout = self::layout();
        $base = self::base_url();
        $labels = self::section_labels();
        $filtered = 'all' !== $initial['section'] || '' !== $initial['q'];
        $config = array(
            'restUrl' => rest_url( 'parmis-mag/v1/articles' ), 'baseUrl' => $base,
            'fallbackImage' => PRM_MAG_URL . 'assets/fallback.svg',
            'pageSize' => 6, 'initial' => array_merge( $initial, array( 'total' => (int) $query->found_posts, 'pages' => (int) $query->max_num_pages, 'has_more' => $initial['page'] < $query->max_num_pages ) ),
        );
        ob_start();
        if ( did_action( 'wp_head' ) && ! wp_style_is( 'prm-magazine', 'done' ) ) {
            wp_print_styles( 'prm-magazine' );
        }
        include PRM_MAG_DIR . 'templates/magazine.php';
        return ob_get_clean();
    }
}
