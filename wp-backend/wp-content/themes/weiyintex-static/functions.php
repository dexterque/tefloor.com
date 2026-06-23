<?php
/**
 * Theme helpers for the static Tefloor homepage.
 *
 * @package WeiyintexStatic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		register_nav_menus(
			array(
				'primary' => 'Primary Menu',
				'footer_company' => 'Footer Company Menu',
				'footer_products' => 'Footer Product Menu',
			)
		);
	}
);

add_filter( 'document_title_parts', 'weiyintex_document_title_parts' );
add_action( 'wp', 'weiyintex_disable_core_canonical_when_theme_seo_is_active' );
add_action( 'wp_head', 'weiyintex_render_seo_meta', 1 );

add_action(
	'init',
	function () {
		foreach ( array( 'page', 'post', 'weiyintex_product' ) as $post_type ) {
			foreach (
				array(
					'_weiyintex_seo_title'       => 'sanitize_text_field',
					'_weiyintex_seo_description' => 'sanitize_textarea_field',
					'_weiyintex_seo_keywords'    => 'sanitize_text_field',
				) as $meta_key => $sanitize_callback
			) {
				register_post_meta(
					$post_type,
					$meta_key,
					array(
						'type'              => 'string',
						'single'            => true,
						'show_in_rest'      => true,
						'sanitize_callback' => $sanitize_callback,
						'auth_callback'     => function () {
							return current_user_can( 'edit_posts' );
						},
					)
				);
			}
		}
	}
);

add_action(
	'add_meta_boxes',
	function () {
		foreach ( array( 'page', 'post', 'weiyintex_product' ) as $post_type ) {
			add_meta_box( 'weiyintex_seo_settings', 'SEO 配置', 'weiyintex_render_seo_settings_box', $post_type, 'normal', 'default' );
		}
	}
);

add_action( 'save_post', 'weiyintex_save_seo_settings' );

add_action(
	'template_redirect',
	function () {
		if ( is_admin() || wp_doing_ajax() ) {
			return;
		}

		ob_start( 'weiyintex_rewrite_static_export_urls' );
	}
);

add_action(
	'pre_get_posts',
	function ( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( $query->is_post_type_archive( 'weiyintex_product' ) || $query->is_tax( 'weiyintex_product_category' ) ) {
			$query->set( 'orderby', array( 'menu_order' => 'ASC', 'title' => 'ASC' ) );
			$query->set( 'order', 'ASC' );
		}
	}
);

function weiyintex_rewrite_static_export_urls( $html ) {
	$home         = home_url();
	$home_escaped = str_replace( '/', '\\/', $home );
	$home_encoded = rawurlencode( home_url( '/' ) );

	$html = preg_replace( '/<!--\\s*Mirrored from .*?-->\\s*/i', '', $html );

	$replacements = array(
		'https://weiyintex.com'          => $home,
		'http://weiyintex.com'           => $home,
		'https:\\/\\/weiyintex.com'      => $home_escaped,
		'http:\\/\\/weiyintex.com'       => $home_escaped,
		'https%3A%2F%2Fweiyintex.com%2F' => $home_encoded,
		'http%3A%2F%2Fweiyintex.com%2F'  => $home_encoded,
		'www.weiyintex.com'              => 'Tefloor',
		'weiyintex.com'                  => wp_parse_url( $home, PHP_URL_HOST ),
		'href="wp-content/'    => 'href="' . home_url( '/wp-content/' ),
		"href='wp-content/"    => "href='" . home_url( '/wp-content/' ),
		'src="wp-content/'     => 'src="' . home_url( '/wp-content/' ),
		"src='wp-content/"     => "src='" . home_url( '/wp-content/' ),
		'content="wp-content/' => 'content="' . home_url( '/wp-content/' ),
		'href="wp-json/'       => 'href="' . home_url( '/wp-json/' ),
		'action="index.html"'  => 'action="' . home_url( '/' ) . '"',
		'href="index.html"'    => 'href="' . home_url( '/' ) . '"',
	);

	return strtr( $html, $replacements );
}

function weiyintex_page_url( $slug ) {
	$page = get_page_by_path( $slug );

	if ( $page ) {
		return get_permalink( $page );
	}

	return home_url( '/' . trim( $slug, '/' ) . '/' );
}

function weiyintex_product_category_url( $slug ) {
	$term = get_term_by( 'slug', $slug, 'weiyintex_product_category' );

	if ( $term && ! is_wp_error( $term ) ) {
		return get_term_link( $term );
	}

	return home_url( '/product-category/' . trim( $slug, '/' ) . '/' );
}

function weiyintex_product_catalog_structure() {
	return array(
		array(
			'title'    => 'Carpet brand',
			'slug'     => 'carpet-brand',
			'children' => array(
				array( 'title' => 'Mohawk Carpet', 'slug' => 'mohawk-carpet' ),
				array( 'title' => 'ShawContract Carpet', 'slug' => 'shawcontract-carpet' ),
				array( 'title' => 'TANDUS Carpet', 'slug' => 'tandus-carpet' ),
			),
		),
		array(
			'title'    => 'LVT/SPC Flooring',
			'slug'     => 'lvt-spc-flooring',
			'children' => array(
				array( 'title' => 'WALRUS', 'slug' => 'walrus' ),
				array( 'title' => 'Darde', 'slug' => 'darde' ),
			),
		),
	);
}

function weiyintex_catalog_item_to_menu_item( $item ) {
	$menu_item = array(
		'title'    => $item['title'],
		'url'      => weiyintex_product_category_url( $item['slug'] ),
		'children' => array(),
	);

	foreach ( $item['children'] ?? array() as $child ) {
		$menu_item['children'][] = weiyintex_catalog_item_to_menu_item( $child );
	}

	return $menu_item;
}

function weiyintex_product_catalog_menu_items() {
	return array_map( 'weiyintex_catalog_item_to_menu_item', weiyintex_product_catalog_structure() );
}

function weiyintex_product_catalog_leaf_links() {
	$links = array();

	foreach ( weiyintex_product_catalog_structure() as $parent ) {
		foreach ( $parent['children'] ?? array() as $child ) {
			$links[] = array(
				'title' => $child['title'],
				'url'   => weiyintex_product_category_url( $child['slug'] ),
			);
		}
	}

	return $links;
}

function weiyintex_default_menu_tree() {
	return array(
		array(
			'title' => 'Home',
			'url'   => home_url( '/' ),
		),
		array(
			'title'    => 'Products',
			'url'      => get_post_type_archive_link( 'weiyintex_product' ) ?: home_url( '/products/' ),
			'children' => weiyintex_product_catalog_menu_items(),
		),
		array(
			'title' => 'About',
			'url'   => weiyintex_page_url( 'about-us' ),
		),
		array(
			'title' => 'Blog',
			'url'   => weiyintex_page_url( 'blog' ),
		),
		array(
			'title' => 'Contact',
			'url'   => weiyintex_page_url( 'contact-us' ),
		),
	);
}

function weiyintex_menu_tree_from_location( $location ) {
	$locations = get_nav_menu_locations();

	if ( empty( $locations[ $location ] ) ) {
		return array();
	}

	$items = wp_get_nav_menu_items( $locations[ $location ] );

	if ( empty( $items ) || is_wp_error( $items ) ) {
		return array();
	}

	$indexed = array();
	$tree    = array();

	foreach ( $items as $item ) {
		$indexed[ $item->ID ] = array(
			'title'    => $item->title,
			'url'      => $item->url,
			'children' => array(),
			'parent'   => (int) $item->menu_item_parent,
		);
	}

	foreach ( $indexed as $id => &$item ) {
		if ( $item['parent'] && isset( $indexed[ $item['parent'] ] ) ) {
			$indexed[ $item['parent'] ]['children'][] = &$item;
		} else {
			$tree[] = &$item;
		}
	}

	return $tree;
}

function weiyintex_normalized_menu_path( $url ) {
	$path = wp_parse_url( $url, PHP_URL_PATH );
	$path = '/' . trim( (string) $path, '/' );

	return '/' === $path ? '/' : untrailingslashit( $path );
}

function weiyintex_current_request_path() {
	global $wp;

	$request = isset( $wp->request ) ? trim( (string) $wp->request, '/' ) : '';

	return '' === $request ? '/' : '/' . $request;
}

function weiyintex_is_current_menu_item( $item ) {
	$title_slug   = sanitize_title( $item['title'] ?? '' );
	$current_path = weiyintex_current_request_path();
	$item_path    = weiyintex_normalized_menu_path( $item['url'] ?? '' );

	if ( 'home' === $title_slug ) {
		return is_front_page() || '/' === $current_path;
	}

	if (
		'products' === $title_slug
		&& ( is_post_type_archive( 'weiyintex_product' ) || is_tax( 'weiyintex_product_category' ) || is_singular( 'weiyintex_product' ) )
	) {
		return true;
	}

	if ( 'blog' === $title_slug && ! is_front_page() && ( is_home() || is_singular( 'post' ) ) ) {
		return true;
	}

	return $item_path === $current_path;
}

function weiyintex_menu_item_has_current_descendant( $item ) {
	foreach ( $item['children'] ?? array() as $child ) {
		if ( weiyintex_is_current_menu_item( $child ) || weiyintex_menu_item_has_current_descendant( $child ) ) {
			return true;
		}
	}

	return false;
}

function weiyintex_render_menu_item_tree( $items, $variant = 'desktop', $depth = 0 ) {
	$li_prefix = 'mobile' === $variant ? 'menu-item' : 'menu-item menu-item-type-custom menu-item-object-custom';

	foreach ( $items as $item ) {
		$children          = ! empty( $item['children'] ) ? $item['children'] : array();
		$is_current        = weiyintex_is_current_menu_item( $item );
		$has_current_child = weiyintex_menu_item_has_current_descendant( $item );
		$classes           = $li_prefix . ' menu-item-' . sanitize_html_class( sanitize_title( $item['title'] ) );
		$classes          .= $children ? ' menu-item-has-children animated-submenu-block' : '';
		$classes          .= $is_current ? ' current-menu-item' : '';
		$classes          .= $has_current_child ? ' current-menu-parent current-menu-ancestor' : '';

		echo '<li class="' . esc_attr( $classes ) . '" role="none">';
		echo '<a href="' . esc_url( $item['url'] ) . '" class="ct-menu-link" role="menuitem"' . ( $is_current ? ' aria-current="page"' : '' ) . '>' . esc_html( $item['title'] );

		if ( $children && 'desktop' === $variant ) {
			echo '<span class="ct-toggle-dropdown-desktop"><svg class="ct-icon" width="8" height="8" viewBox="0 0 15 15"><path d="M2.1,3.2l5.4,5.4l5.4-5.4L15,4.3l-7.5,7.5L0,4.3L2.1,3.2z"/></svg></span>';
		}

		echo '</a>';

		if ( $children ) {
			if ( 'mobile' === $variant ) {
				echo '<button class="ct-toggle-dropdown-mobile" aria-label="Expand dropdown menu" aria-haspopup="true" aria-expanded="false" role="menuitem"><svg class="ct-icon toggle-icon-1" width="15" height="15" viewBox="0 0 15 15"><path d="M3.9,5.1l3.6,3.6l3.6-3.6l1.4,0.7l-5,5l-5-5L3.9,5.1z"/></svg></button>';
			} else {
				echo '<button class="ct-toggle-dropdown-desktop-ghost" aria-label="Expand dropdown menu" aria-haspopup="true" aria-expanded="false" role="menuitem"></button>';
			}

			echo '<ul class="sub-menu sub-menu-depth-' . esc_attr( (string) ( $depth + 1 ) ) . '" role="menu">';
			weiyintex_render_menu_item_tree( $children, $variant, $depth + 1 );
			echo '</ul>';
		}

		echo '</li>';
	}
}

function weiyintex_render_main_menu( $variant = 'desktop' ) {
	$items     = weiyintex_menu_tree_from_location( 'primary' ) ?: weiyintex_default_menu_tree();
	$ul_id     = 'mobile' === $variant ? 'menu-main-menu-1' : 'menu-main-menu';
	$ul_class  = 'mobile' === $variant ? '' : ' class="menu"';

	echo '<ul id="' . esc_attr( $ul_id ) . '"' . $ul_class . ' role="menubar">';
	weiyintex_render_menu_item_tree( $items, $variant );
	echo '</ul>';
}

function weiyintex_theme_asset( $path ) {
	return esc_url( home_url( '/' . ltrim( $path, '/' ) ) );
}

function weiyintex_simple_head( $title = '' ) {
	?>
	<!doctype html>
	<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, viewport-fit=cover">
		<link rel="stylesheet" href="<?php echo weiyintex_theme_asset( 'wp-content/uploads/blocksy/css/global3b36.css?ver=97531' ); ?>">
		<link rel="stylesheet" href="<?php echo weiyintex_theme_asset( 'wp-content/plugins/elementor/assets/css/frontend-lite.min11d9.css?ver=3.21.3' ); ?>">
		<link rel="stylesheet" href="<?php echo weiyintex_theme_asset( 'wp-content/themes/blocksy/static/bundle/main.min12fd.css?ver=2.0.42' ); ?>">
		<style>
			body.weiyintex-simple-page { --tefloor-blue: #273B92; --tefloor-blue-soft: rgba(39, 59, 146, 0.08); --tefloor-text: #141827; margin: 0; background: #fff; color: var(--tefloor-text); font-family: Poppins, Arial, sans-serif; }
			.weiyintex-shell-header { border-bottom: 1px solid rgba(39, 59, 146, 0.12); background: rgba(255, 255, 255, 0.98); position: sticky; top: 0; z-index: 1000; box-shadow: 0 8px 26px rgba(20, 24, 39, 0.05); }
			.weiyintex-shell-inner { max-width: 1180px; margin: 0 auto; padding: 0 24px; }
			.weiyintex-shell-nav { display: flex; align-items: center; justify-content: space-between; min-height: 88px; gap: 32px; }
			.weiyintex-shell-logo { display: inline-flex; align-items: center; flex: 0 0 auto; }
			.weiyintex-shell-logo img { width: 138px; max-width: min(38vw, 138px); height: auto; display: block; }
			.weiyintex-shell-menu { display: flex; justify-content: flex-end; min-width: 0; }
			.weiyintex-shell-menu ul#menu-main-menu { display: flex !important; align-items: center; gap: 8px; list-style: none; margin: 0; padding: 0; background: transparent !important; border: 0 !important; }
			.weiyintex-shell-menu li { position: relative; margin: 0 !important; padding: 0 !important; }
			.weiyintex-shell-menu a.ct-menu-link { display: inline-flex !important; align-items: center; justify-content: center; min-height: 42px; padding: 0 15px !important; border-radius: 999px; color: var(--tefloor-blue) !important; background: transparent !important; text-decoration: none; font-weight: 600; font-size: 15px !important; line-height: 1; letter-spacing: 0; transition: color 0.18s ease, background-color 0.18s ease, box-shadow 0.18s ease; }
			.weiyintex-shell-menu a.ct-menu-link:hover,
			.weiyintex-shell-menu a.ct-menu-link:focus-visible { color: var(--tefloor-blue) !important; background: var(--tefloor-blue-soft) !important; outline: none; }
			.weiyintex-shell-menu .current-menu-item > a.ct-menu-link,
			.weiyintex-shell-menu .current-menu-parent > a.ct-menu-link,
			.weiyintex-shell-menu .current-menu-ancestor > a.ct-menu-link { color: #fff !important; background: var(--tefloor-blue) !important; box-shadow: 0 8px 18px rgba(39, 59, 146, 0.18); }
			.weiyintex-shell-menu .ct-toggle-dropdown-desktop { display: inline-flex; align-items: center; margin-left: 8px; color: currentColor !important; }
			.weiyintex-shell-menu .ct-toggle-dropdown-desktop svg,
			.weiyintex-shell-menu .ct-toggle-dropdown-desktop svg *,
			.weiyintex-shell-menu .ct-toggle-dropdown-mobile svg,
			.weiyintex-shell-menu .ct-toggle-dropdown-mobile svg * { display: block; color: currentColor !important; fill: currentColor !important; stroke: currentColor !important; }
			.weiyintex-shell-menu .ct-toggle-dropdown-desktop-ghost { display: none !important; }
			.weiyintex-shell-menu .sub-menu { display: none !important; position: absolute; left: 0; top: calc(100% + 10px); z-index: 1010; min-width: 250px; overflow: visible !important; background: #fff; border: 1px solid rgba(39, 59, 146, 0.12); border-radius: 8px; padding: 10px; box-shadow: 0 18px 42px rgba(20, 24, 39, 0.16); }
			.weiyintex-shell-menu .sub-menu::before { content: ""; position: absolute; left: 0; right: 0; bottom: 100%; height: 10px; }
			.weiyintex-shell-menu li:hover > .sub-menu,
			.weiyintex-shell-menu li:focus-within > .sub-menu { display: grid !important; gap: 4px; visibility: visible !important; opacity: 1 !important; transform: none !important; pointer-events: auto !important; }
			.weiyintex-shell-menu li:hover > .sub-menu > li,
			.weiyintex-shell-menu li:focus-within > .sub-menu > li,
			.weiyintex-shell-menu li:hover > .sub-menu > li > a.ct-menu-link,
			.weiyintex-shell-menu li:focus-within > .sub-menu > li > a.ct-menu-link { visibility: visible !important; opacity: 1 !important; transform: none !important; }
			.weiyintex-shell-menu .sub-menu .sub-menu { left: calc(100% + 10px); top: -10px; }
			.weiyintex-shell-menu .sub-menu .menu-item-has-children > a.ct-menu-link { justify-content: space-between; gap: 16px; }
			.weiyintex-shell-menu .sub-menu a.ct-menu-link { justify-content: flex-start; width: 100%; min-height: 36px; padding: 0 12px !important; border-radius: 6px; font-size: 13px !important; white-space: nowrap; box-shadow: none !important; }
			.weiyintex-page-hero { background: #f7f7f9; padding: 72px 0 50px; }
			.weiyintex-page-hero h1 { margin: 0; font-size: 44px; line-height: 1.08; letter-spacing: 0; }
			.weiyintex-page-content { padding: 54px 0 72px; }
			.weiyintex-page-content p { font-size: 16px; line-height: 1.78; }
			.weiyintex-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 28px; }
			.weiyintex-card img { width: 100%; aspect-ratio: 1 / 1; object-fit: cover; display: block; background: #f3f3f5; }
			.weiyintex-card h2, .weiyintex-card h3 { font-size: 17px; line-height: 1.35; margin: 14px 0 0; }
			.weiyintex-card a { color: #16141a; text-decoration: none; }
			.weiyintex-product-detail { display: grid; grid-template-columns: minmax(0, 440px) minmax(0, 1fr); align-items: start; gap: 28px; }
			.weiyintex-product-detail-image { width: 100%; aspect-ratio: 1 / 1; object-fit: cover; display: block; background: #f3f3f5; }
			.weiyintex-product-detail-content { min-width: 0; overflow-wrap: anywhere; }
			.weiyintex-product-detail-content > :first-child { margin-top: 0; }
			.weiyintex-blog-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 30px; }
			.weiyintex-blog-card { display: flex; flex-direction: column; min-width: 0; overflow: hidden; border: 1px solid rgba(39, 59, 146, 0.12); border-radius: 8px; background: #fff; box-shadow: 0 12px 30px rgba(20, 24, 39, 0.06); }
			.weiyintex-blog-card img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; display: block; background: #f3f3f5; }
			.weiyintex-blog-card-body { display: flex; flex: 1; flex-direction: column; padding: 20px; }
			.weiyintex-blog-card time { margin-bottom: 10px; color: #6e7280; font-size: 13px; font-weight: 500; }
			.weiyintex-blog-card h2 { margin: 0 0 12px; color: #141827; font-size: 21px; line-height: 1.3; letter-spacing: 0; }
			.weiyintex-blog-card p { margin: 0 0 18px; color: #4f5668; font-size: 15px; line-height: 1.65; }
			.weiyintex-blog-card a { color: inherit; text-decoration: none; }
			.weiyintex-blog-more { margin-top: auto; color: var(--tefloor-blue) !important; font-weight: 700; }
			.weiyintex-blog-empty { margin: 0; color: #4f5668; }
			.weiyintex-shell-footer { border-top: 1px solid #ececf0; padding: 28px 0; color: #696773; font-size: 13px; }
			@media (max-width: 700px) { .weiyintex-product-detail { grid-template-columns: 1fr; justify-items: center; gap: 22px; } .weiyintex-product-detail-image { max-width: 420px; margin: 0 auto; } .weiyintex-product-detail-content { width: 100%; } }
			@media (max-width: 900px) { .weiyintex-grid, .weiyintex-blog-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .weiyintex-shell-nav { align-items: flex-start; flex-direction: column; min-height: 0; gap: 14px; padding: 18px 24px 14px; } .weiyintex-shell-logo img { width: 128px; } .weiyintex-shell-menu { width: 100%; justify-content: flex-start; overflow-x: auto; overflow-y: visible; -webkit-overflow-scrolling: touch; scrollbar-width: none; } .weiyintex-shell-menu::-webkit-scrollbar { display: none; } .weiyintex-shell-menu ul#menu-main-menu { flex-wrap: nowrap; min-width: max-content; gap: 4px; } .weiyintex-shell-menu a.ct-menu-link { min-height: 36px; padding: 0 12px !important; font-size: 14px !important; } }
			@media (max-width: 560px) { .weiyintex-grid, .weiyintex-blog-grid { grid-template-columns: 1fr; } .weiyintex-shell-nav { padding-right: 18px; padding-left: 18px; } .weiyintex-page-hero h1 { font-size: 34px; } }
		</style>
		<?php wp_head(); ?>
	</head>
	<body <?php body_class( 'weiyintex-simple-page' ); ?>>
	<?php
}

function weiyintex_simple_header() {
	?>
	<header class="weiyintex-shell-header">
		<div class="weiyintex-shell-inner weiyintex-shell-nav">
			<a class="weiyintex-shell-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
				<img src="<?php echo esc_url( weiyintex_site_image_url( 'brand.logo' ) ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
			</a>
			<nav class="weiyintex-shell-menu" aria-label="Primary">
				<?php weiyintex_render_main_menu( 'desktop' ); ?>
			</nav>
		</div>
	</header>
	<?php
}

function weiyintex_simple_footer() {
	?>
	<footer class="weiyintex-shell-footer">
		<div class="weiyintex-shell-inner"><?php echo esc_html( weiyintex_site_text( 'footer.copyright' ) ); ?></div>
	</footer>
	<?php wp_footer(); ?>
	</body>
	</html>
	<?php
}

function weiyintex_render_simple_product_card( $post_id = null ) {
	$post_id = $post_id ?: get_the_ID();
	$title   = get_the_title( $post_id );
	$image   = weiyintex_product_image_url( $post_id, '_weiyintex_image', 'wp-content/uploads/2024/08/2-6-500x500.jpg' );
	?>
	<article class="weiyintex-card">
		<a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>">
			<img loading="lazy" src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $title ); ?>">
			<h2><?php echo esc_html( $title ); ?></h2>
		</a>
	</article>
	<?php
}

function weiyintex_home_url( $path ) {
	if ( preg_match( '#^(https?:)?//#', $path ) || str_starts_with( $path, '#' ) || str_starts_with( $path, 'mailto:' ) || str_starts_with( $path, 'tel:' ) ) {
		return $path;
	}

	return home_url( '/' . ltrim( $path, '/' ) );
}

function weiyintex_site_defaults() {
	return array(
		'seo'             => array(
			'site_name'            => 'Tefloor',
			'title'                => 'Tefloor Flooring Solutions',
			'description'          => 'Tefloor supplies carpet, LVT/SPC flooring, and custom flooring solutions for commercial and residential projects, with reliable sourcing and product support.',
			'products_title'       => 'Flooring Products',
			'products_description' => 'Explore Tefloor carpet, LVT/SPC flooring, and commercial flooring products for reliable project sourcing and specification support.',
			'keywords'             => 'carpet, commercial carpet, LVT flooring, SPC flooring, flooring supplier',
			'og_image'             => '',
			'og_image_id'          => 0,
		),
		'brand'           => array(
			'logo'    => 'wp-content/uploads/2024/08/%e5%85%ac%e5%8f%b8logo.png',
			'logo_id' => 0,
		),
		'hero'            => array(
			'background'    => '',
			'background_id' => 0,
			'title'       => 'Custom Textile Manufacturer',
			'subtitle'    => 'Partner with us to create customized hometextile products that perfectly align with your specifications and vision',
			'button_text' => 'Get in touch with us',
			'button_url'  => 'contact-us',
		),
		'features'        => array(
			array(
				'image'       => 'wp-content/uploads/2024/08/file_01652249363893.webp',
				'image_id'    => 0,
				'title'       => 'Factory Strength',
				'description' => 'Many years of industry experience strength factory is trustworthy.',
			),
			array(
				'image'       => 'wp-content/uploads/2024/08/file_01652249466143.webp',
				'image_id'    => 0,
				'title'       => 'Quality Assurance',
				'description' => 'Strictly control every piece product quality',
			),
			array(
				'image'       => 'wp-content/uploads/2024/08/file_01652249378037.webp',
				'image_id'    => 0,
				'title'       => 'Large Inventory',
				'description' => 'There are many kinds ofproducts, large stock',
			),
			array(
				'image'       => 'wp-content/uploads/2024/08/file_01652249384121.webp',
				'image_id'    => 0,
				'title'       => 'Close Service',
				'description' => 'Intimate service, 24 / 7 for you the lawfel',
			),
		),
		'products'        => array(
			'title'       => 'Featured Products',
			'description' => 'The company operates a wide range of products, spot products, and fast delivery. One-stop purchase of home products.',
			'button_text' => 'View All',
			'button_url'  => 'products',
		),
		'intro'           => array(
			'background'    => '',
			'background_id' => 0,
			'headline_html' => 'A Professional <span style="color: #ffcc00;">Home Textile Manufacturer</span> For <span style="color: #ffcc00;">20 Years</span>. We Are Looking Forward To Serving You!',
			'body_html'     => '<div><div data-zone-id="0" data-line-index="0" data-line="true">Weiyintex specializes in custom home textile products, including blankets, beach towels, shower curtains, and more. We offer tailored solutions to help Amazon sellers and independent brands create unique, high-quality products that stand out.</div><div data-zone-id="0" data-line-index="1" data-line="true">With fast production times, excellent customer support, and attention to detail, Weiyintex is dedicated to delivering durable and stylish items that align with your brand’s vision. Trust us to bring your custom designs to life with precision and care.</div></div>',
		),
		'stats'           => array(
			array(
				'title'       => '20+ Years',
				'description' => 'A professional home textile manufacturer',
			),
			array(
				'title'       => '1800+ Employees',
				'description' => 'Has 1800+ employees and a strong production',
			),
			array(
				'title'       => 'One-stop',
				'description' => 'One-stop service for Amazon merchants',
			),
			array(
				'title'       => 'ODM & OEM',
				'description' => 'Support ODM And OEM service',
			),
		),
		'about'           => array(
			'image'       => 'wp-content/uploads/2026/04/2.jpg',
			'image_id'    => 0,
			'title'       => 'About Us',
			'body_html'   => '<div><div data-zone-id="0" data-line-index="0" data-line="true">Weiyintex to produce home textiles like blankets, towels, etc. For over a decade, our team has been providing solutions for Amazon sellers and Brand Owners. Whether you want custom designs, sizes, and logos, or environmentally-friendly materials and rapid global delivery, we are here to provide products that make your brand shine. We Follow Quality, Customer Satisfaction and Authenticity as our driving forces and aim to be your go to company in the home textile demand.</div><div data-zone-id="0" data-line-index="1" data-line="true">Tailored designs to match your brand’s unique identity.</div><div data-zone-id="0" data-line-index="2" data-line="true">Premium, eco-friendly materials for durable and vibrant products.</div><div data-zone-id="0" data-line-index="3" data-line="true">Fast production and reliable global shipping to meet your needs.</div></div>',
			'button_text' => 'Learn More',
			'button_url'  => 'about-us',
		),
		'why'             => array(
			'title'       => 'Why Choose Us',
			'subtitle'    => 'Brand OEM, cross-border foreign trade, home decoration customization, distribution, wholesale engineering, wholesale',
			'items'       => array(
				array(
					'image'       => 'wp-content/uploads/2026/04/4.jpg',
					'image_id'    => 0,
					'title'       => 'Factory strength',
					'description' => 'Three major factory areas, covering approximately 150000 square meters, with over 1800 employees and nearly 30 years of production experience.',
				),
				array(
					'image'       => 'wp-content/uploads/2026/04/7.jpg',
					'image_id'    => 0,
					'title'       => 'production capacity',
					'description' => 'Three major factory areas, covering approximately 150000 square meters, with over 1800 employees and nearly 30 years of production experience.',
				),
				array(
					'image'       => 'wp-content/uploads/2026/04/8.jpg',
					'image_id'    => 0,
					'title'       => 'Export capability',
					'description' => '20 years of cross-border experience, products exported to 57 countries, regions, and services worldwide',
				),
				array(
					'image'       => 'wp-content/uploads/2026/04/9.jpg',
					'image_id'    => 0,
					'title'       => 'Research and development capabilities',
					'description' => '35 developers, developing over 50 new products annually',
				),
				array(
					'image'       => 'wp-content/uploads/2024/08/5.jpg',
					'image_id'    => 0,
					'title'       => 'Professional personnel',
					'description' => 'More than 800 production personnel with over 10 years of experience',
				),
			),
		),
		'blog'            => array(
			'title' => 'Latest from the Blog',
		),
		'contact'         => array(
			'heading'          => 'CONTACT',
			'email_label'      => 'Email Address',
			'email'            => 'black.tan@weiyintex.com',
			'phone_label'      => 'Phone Numbers',
			'phone'            => '+8615267506726',
			'wechat_label'     => 'Wechat',
			'wechat'           => '+8615267506726',
			'whatsapp_label'   => 'WhatsApp',
			'whatsapp'         => '+8615267506726',
			'whatsapp_message' => 'Hello',
			'inquiry_text'     => 'Inquiry',
			'mobile_home'      => 'Home',
			'mobile_about'     => 'About',
			'mobile_product'   => 'Product',
			'mobile_email'     => 'Email',
			'mobile_phone'     => 'Phone',
		),
		'footer'          => array(
			'company_title' => 'COMPANY',
			'product_title' => 'PRODUCT',
			'description'   => 'Shaoxing Weiyin Textile Co., Ltd. is a professional manufacturer of home textile blankets with more than ten years of business experience. It has multiple channels for online and offline sales. Its product categories are rich, including woolen blankets, waterproof pet blankets, travel blankets, hoodies, outdoor blankets, beach towels, hoodies, bathrobes, shirts and other home textiles.',
			'copyright'     => 'Copyright © 2024 Shaoxing Weiyin Textile Co., Ltd. All rights reserved.',
		),
		'form'            => array(
			'title'          => 'Send us a message',
			'name_label'     => 'Name',
			'email_label'    => 'Email',
			'phone_label'    => 'Phone/WhatsApp',
			'message_label'  => 'Message',
			'submit_label'   => 'Send',
		),
	);
}

function weiyintex_site_options( $force_refresh = false ) {
	static $options = null;

	if ( null === $options || $force_refresh ) {
		$stored = get_option( 'weiyintex_site_content', array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$options = weiyintex_merge_site_options( weiyintex_site_defaults(), $stored );
	}

	return $options;
}

function weiyintex_merge_site_options( $defaults, $stored ) {
	if ( ! is_array( $defaults ) ) {
		return ( is_scalar( $stored ) && '' !== trim( (string) $stored ) ) ? $stored : $defaults;
	}

	if ( ! is_array( $stored ) ) {
		return $defaults;
	}

	foreach ( $defaults as $key => $default ) {
		if ( ! array_key_exists( $key, $stored ) ) {
			continue;
		}

		$defaults[ $key ] = weiyintex_merge_site_options( $default, $stored[ $key ] );
	}

	return $defaults;
}

function weiyintex_site_option( $path, $default = '' ) {
	$value = weiyintex_site_options();

	foreach ( explode( '.', $path ) as $segment ) {
		if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
			return $default;
		}

		$value = $value[ $segment ];
	}

	return $value;
}

function weiyintex_site_text( $path, $default = '' ) {
	$value = weiyintex_site_option( $path, $default );

	return is_scalar( $value ) ? (string) $value : $default;
}

function weiyintex_site_html( $path, $default = '' ) {
	return wp_kses_post( weiyintex_site_text( $path, $default ) );
}

function weiyintex_site_asset_url( $path ) {
	return weiyintex_home_url( weiyintex_site_text( $path ) );
}

function weiyintex_site_image_id_path( $path ) {
	if ( str_ends_with( $path, '.image' ) ) {
		return substr( $path, 0, -6 ) . '.image_id';
	}

	if ( str_ends_with( $path, '.logo' ) ) {
		return substr( $path, 0, -5 ) . '.logo_id';
	}

	return $path . '_id';
}

function weiyintex_site_image_id( $path ) {
	return absint( weiyintex_site_option( weiyintex_site_image_id_path( $path ), 0 ) );
}

function weiyintex_site_image_url( $path, $size = 'full' ) {
	$attachment_id = weiyintex_site_image_id( $path );

	if ( $attachment_id ) {
		$url = wp_get_attachment_image_url( $attachment_id, $size );

		if ( $url ) {
			return $url;
		}
	}

	return weiyintex_site_asset_url( $path );
}

function weiyintex_site_link( $path ) {
	$value = weiyintex_site_text( $path );

	if ( $value && ! preg_match( '#^(https?:)?//#', $value ) && ! str_starts_with( $value, '#' ) && ! str_starts_with( $value, 'mailto:' ) && ! str_starts_with( $value, 'tel:' ) && ! str_contains( $value, '?' ) && ! str_ends_with( $value, '/' ) ) {
		$value .= '/';
	}

	return weiyintex_home_url( $value );
}

function weiyintex_whatsapp_url() {
	$phone   = preg_replace( '/\s+/', '', weiyintex_site_text( 'contact.whatsapp' ) );
	$message = rawurlencode( weiyintex_site_text( 'contact.whatsapp_message' ) );

	return 'https://api.whatsapp.com/send?phone=' . $phone . '&text=' . $message;
}

function weiyintex_phone_url( $path = 'contact.phone' ) {
	return 'tel:' . preg_replace( '/\s+/', '', weiyintex_site_text( $path ) );
}

function weiyintex_email_url() {
	return 'mailto:' . sanitize_email( weiyintex_site_text( 'contact.email' ) );
}

function weiyintex_render_site_image_box( $path, $width = 80, $height = 80 ) {
	$title       = weiyintex_site_text( $path . '.title' );
	$description = weiyintex_site_text( $path . '.description' );
	$image       = weiyintex_site_image_url( $path . '.image' );

	?>
	<div class="elementor-image-box-wrapper">
		<figure class="elementor-image-box-img">
			<img loading="lazy" decoding="async" width="<?php echo esc_attr( (string) $width ); ?>" height="<?php echo esc_attr( (string) $height ); ?>" src="<?php echo esc_url( $image ); ?>" class="attachment-full size-full wp-post-image" alt="<?php echo esc_attr( $title ); ?>" />
		</figure>
		<div class="elementor-image-box-content">
			<h3 class="elementor-image-box-title"><?php echo esc_html( $title ); ?></h3>
			<p class="elementor-image-box-description"><?php echo esc_html( $description ); ?></p>
		</div>
	</div>
	<?php
}

function weiyintex_footer_company_links() {
	return array(
		array( 'title' => 'About Us', 'url' => weiyintex_page_url( 'about-us' ) ),
		array( 'title' => 'Blog', 'url' => weiyintex_page_url( 'blog' ) ),
		array( 'title' => 'Contact Us', 'url' => weiyintex_page_url( 'contact-us' ) ),
	);
}

function weiyintex_footer_product_links() {
	return weiyintex_product_catalog_leaf_links();
}

function weiyintex_render_footer_link_list( $location, $fallback_items ) {
	$items = weiyintex_menu_tree_from_location( $location ) ?: $fallback_items;

	echo '<ul class="elementor-icon-list-items">';
	foreach ( $items as $item ) {
		echo '<li class="elementor-icon-list-item"><a href="' . esc_url( $item['url'] ) . '"><span class="elementor-icon-list-text">' . esc_html( $item['title'] ) . '</span></a></li>';
	}
	echo '</ul>';
}

function weiyintex_known_seo_plugin_active() {
	return defined( 'WPSEO_VERSION' )
		|| defined( 'RANK_MATH_VERSION' )
		|| defined( 'AIOSEO_VERSION' )
		|| defined( 'SEOPRESS_VERSION' )
		|| class_exists( 'WPSEO_Frontend' )
		|| function_exists( 'rank_math' )
		|| function_exists( 'aioseo' );
}

function weiyintex_theme_seo_enabled() {
	return ! weiyintex_known_seo_plugin_active();
}

function weiyintex_disable_core_canonical_when_theme_seo_is_active() {
	if ( weiyintex_theme_seo_enabled() ) {
		remove_action( 'wp_head', 'rel_canonical' );
	}
}

function weiyintex_get_seo_post_meta( $key ) {
	if ( ! is_singular() ) {
		return '';
	}

	return trim( (string) get_post_meta( get_queried_object_id(), '_weiyintex_seo_' . $key, true ) );
}

function weiyintex_get_queried_excerpt_text() {
	if ( ! is_singular() ) {
		return '';
	}

	$post_id = get_queried_object_id();
	$text    = has_excerpt( $post_id ) ? get_the_excerpt( $post_id ) : get_post_field( 'post_content', $post_id );
	$text    = strip_shortcodes( $text );
	$text    = wp_strip_all_tags( $text, true );
	$text    = preg_replace( '/\s+/', ' ', $text );

	return trim( (string) $text );
}

function weiyintex_trim_meta_text( $text, $limit = 160 ) {
	$text = preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $text, true ) );
	$text = trim( (string) $text );

	if ( '' === $text ) {
		return $text;
	}

	$length = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );

	if ( $length <= $limit ) {
		return $text;
	}

	if ( function_exists( 'mb_substr' ) ) {
		return rtrim( mb_substr( $text, 0, $limit - 1 ) ) . '...';
	}

	return rtrim( substr( $text, 0, $limit - 1 ) ) . '...';
}

function weiyintex_get_seo_title() {
	$post_title = weiyintex_get_seo_post_meta( 'title' );

	if ( $post_title ) {
		return $post_title;
	}

	if ( is_front_page() ) {
		return weiyintex_site_text( 'seo.title', get_bloginfo( 'name' ) );
	}

	if ( is_post_type_archive( 'weiyintex_product' ) ) {
		return weiyintex_site_text( 'seo.products_title', weiyintex_site_text( 'products.title', 'Products' ) );
	}

	if ( is_tax( 'weiyintex_product_category' ) ) {
		$term = get_queried_object();
		return $term && ! is_wp_error( $term ) ? $term->name : 'Product Category';
	}

	return '';
}

function weiyintex_get_seo_description() {
	$post_description = weiyintex_get_seo_post_meta( 'description' );

	if ( $post_description ) {
		return weiyintex_trim_meta_text( $post_description, 180 );
	}

	if ( is_front_page() ) {
		return weiyintex_trim_meta_text( weiyintex_site_text( 'seo.description' ), 180 );
	}

	if ( is_post_type_archive( 'weiyintex_product' ) ) {
		return weiyintex_trim_meta_text( weiyintex_site_text( 'seo.products_description', weiyintex_site_text( 'seo.description' ) ), 180 );
	}

	if ( is_tax( 'weiyintex_product_category' ) ) {
		$term_description = term_description();

		if ( $term_description ) {
			return weiyintex_trim_meta_text( $term_description, 180 );
		}
	}

	$excerpt = weiyintex_get_queried_excerpt_text();

	if ( $excerpt ) {
		return weiyintex_trim_meta_text( $excerpt, 180 );
	}

	return weiyintex_trim_meta_text( get_bloginfo( 'description' ) ?: weiyintex_site_text( 'seo.description' ), 180 );
}

function weiyintex_get_seo_keywords() {
	return weiyintex_get_seo_post_meta( 'keywords' ) ?: weiyintex_site_text( 'seo.keywords' );
}

function weiyintex_get_canonical_url() {
	if ( is_front_page() ) {
		return home_url( '/' );
	}

	if ( is_singular() ) {
		return get_permalink();
	}

	if ( is_post_type_archive( 'weiyintex_product' ) ) {
		return get_post_type_archive_link( 'weiyintex_product' ) ?: home_url( '/products/' );
	}

	if ( is_tax() || is_category() || is_tag() ) {
		$term_link = get_term_link( get_queried_object() );
		return is_wp_error( $term_link ) ? '' : $term_link;
	}

	if ( is_home() && get_option( 'page_for_posts' ) ) {
		return get_permalink( (int) get_option( 'page_for_posts' ) );
	}

	global $wp;
	$request_path = isset( $wp->request ) ? trim( (string) $wp->request, '/' ) : '';

	return user_trailingslashit( home_url( '/' . $request_path ) );
}

function weiyintex_get_seo_image_url() {
	if ( is_singular() ) {
		$post_id = get_queried_object_id();

		if ( 'weiyintex_product' === get_post_type( $post_id ) ) {
			$image = weiyintex_product_image_url( $post_id, '_weiyintex_image', '', 'large' );

			if ( $image && home_url( '/' ) !== $image ) {
				return $image;
			}
		}

		if ( 'post' === get_post_type( $post_id ) ) {
			return weiyintex_post_image_url( $post_id );
		}

		if ( has_post_thumbnail( $post_id ) ) {
			return get_the_post_thumbnail_url( $post_id, 'large' );
		}
	}

	$seo_image = weiyintex_site_text( 'seo.og_image' );

	if ( $seo_image || weiyintex_site_image_id( 'seo.og_image' ) ) {
		return weiyintex_site_image_url( 'seo.og_image', 'large' );
	}

	return '';
}

function weiyintex_document_title_parts( $parts ) {
	if ( ! weiyintex_theme_seo_enabled() ) {
		return $parts;
	}

	$title     = weiyintex_get_seo_title();
	$site_name = weiyintex_site_text( 'seo.site_name' );

	if ( $title ) {
		$parts['title'] = $title;
	}

	if ( $site_name ) {
		$parts['site'] = $site_name;
	}

	unset( $parts['tagline'] );

	return $parts;
}

function weiyintex_render_seo_meta() {
	if ( ! weiyintex_theme_seo_enabled() ) {
		return;
	}

	$title       = wp_get_document_title();
	$description = weiyintex_get_seo_description();
	$keywords    = weiyintex_get_seo_keywords();
	$canonical   = weiyintex_get_canonical_url();
	$image       = weiyintex_get_seo_image_url();

	if ( $description ) {
		echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
	}

	if ( $keywords ) {
		echo '<meta name="keywords" content="' . esc_attr( $keywords ) . '">' . "\n";
	}

	if ( $canonical ) {
		echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
	}

	echo '<meta property="og:type" content="' . ( is_singular() ? 'article' : 'website' ) . '">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
	echo '<meta property="og:site_name" content="' . esc_attr( weiyintex_site_text( 'seo.site_name', get_bloginfo( 'name' ) ) ) . '">' . "\n";

	if ( $description ) {
		echo '<meta property="og:description" content="' . esc_attr( $description ) . '">' . "\n";
	}

	if ( $canonical ) {
		echo '<meta property="og:url" content="' . esc_url( $canonical ) . '">' . "\n";
	}

	if ( $image ) {
		echo '<meta property="og:image" content="' . esc_url( $image ) . '">' . "\n";
	}

	echo '<meta name="twitter:card" content="' . ( $image ? 'summary_large_image' : 'summary' ) . '">' . "\n";
}

function weiyintex_render_seo_settings_box( $post ) {
	wp_nonce_field( 'weiyintex_seo_settings', 'weiyintex_seo_settings_nonce' );

	$fields = array(
		'_weiyintex_seo_title'       => array( 'SEO 标题', '不填则自动使用页面标题。' ),
		'_weiyintex_seo_description' => array( 'SEO 描述', '建议 120-180 个字符；不填则自动取摘要或正文。' ),
		'_weiyintex_seo_keywords'    => array( 'SEO 关键词', '可选；Google 不用于排名，但保留给其他搜索引擎或内部管理。' ),
	);

	foreach ( $fields as $key => $field ) {
		$value = get_post_meta( $post->ID, $key, true );
		?>
		<p>
			<label for="<?php echo esc_attr( $key ); ?>"><strong><?php echo esc_html( $field[0] ); ?></strong></label>
			<?php if ( '_weiyintex_seo_description' === $key ) : ?>
				<textarea id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" class="widefat" rows="3"><?php echo esc_textarea( $value ); ?></textarea>
			<?php else : ?>
				<input id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" type="text" value="<?php echo esc_attr( $value ); ?>" class="widefat">
			<?php endif; ?>
			<span class="description"><?php echo esc_html( $field[1] ); ?></span>
		</p>
		<?php
	}
}

function weiyintex_save_seo_settings( $post_id ) {
	if ( ! isset( $_POST['weiyintex_seo_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['weiyintex_seo_settings_nonce'] ) ), 'weiyintex_seo_settings' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( ! in_array( get_post_type( $post_id ), array( 'page', 'post', 'weiyintex_product' ), true ) ) {
		return;
	}

	$fields = array(
		'_weiyintex_seo_title'       => 'sanitize_text_field',
		'_weiyintex_seo_description' => 'sanitize_textarea_field',
		'_weiyintex_seo_keywords'    => 'sanitize_text_field',
	);

	foreach ( $fields as $key => $sanitize_callback ) {
		if ( ! isset( $_POST[ $key ] ) ) {
			continue;
		}

		$value = call_user_func( $sanitize_callback, wp_unslash( $_POST[ $key ] ) );

		if ( '' === trim( (string) $value ) ) {
			delete_post_meta( $post_id, $key );
		} else {
			update_post_meta( $post_id, $key, $value );
		}
	}
}

function weiyintex_admin_html_paths() {
	return array(
		'intro.headline_html',
		'intro.body_html',
		'about.body_html',
		'footer.description',
	);
}

function weiyintex_sanitize_site_content( $raw, $defaults = null, $prefix = '' ) {
	$defaults = $defaults ?: weiyintex_site_defaults();
	$clean    = array();

	foreach ( $defaults as $key => $default ) {
		$path  = $prefix ? $prefix . '.' . $key : (string) $key;
		$value = is_array( $raw ) && array_key_exists( $key, $raw ) ? $raw[ $key ] : $default;

		if ( is_array( $default ) ) {
			$clean[ $key ] = weiyintex_sanitize_site_content( is_array( $value ) ? $value : array(), $default, $path );
			continue;
		}

		$value = is_scalar( $value ) ? (string) $value : '';

		if ( str_ends_with( $path, '_id' ) ) {
			$clean[ $key ] = absint( $value );
		} elseif ( in_array( $path, weiyintex_admin_html_paths(), true ) ) {
			$clean[ $key ] = wp_kses_post( $value );
		} else {
			$clean[ $key ] = sanitize_text_field( $value );
		}
	}

	return $clean;
}

function weiyintex_admin_field_name( $path ) {
	$name = 'weiyintex_site_content';

	foreach ( explode( '.', $path ) as $segment ) {
		$name .= '[' . esc_attr( $segment ) . ']';
	}

	return $name;
}

function weiyintex_admin_text_field( $path, $label, $description = '' ) {
	?>
	<p>
		<label>
			<strong><?php echo esc_html( $label ); ?></strong>
			<input class="widefat" type="text" name="<?php echo weiyintex_admin_field_name( $path ); ?>" value="<?php echo esc_attr( weiyintex_site_text( $path ) ); ?>">
		</label>
		<?php if ( $description ) : ?>
			<span class="description"><?php echo esc_html( $description ); ?></span>
		<?php endif; ?>
	</p>
	<?php
}

function weiyintex_admin_textarea_field( $path, $label, $description = '' ) {
	?>
	<p>
		<label>
			<strong><?php echo esc_html( $label ); ?></strong>
			<textarea class="widefat" rows="4" name="<?php echo weiyintex_admin_field_name( $path ); ?>"><?php echo esc_textarea( weiyintex_site_text( $path ) ); ?></textarea>
		</label>
		<?php if ( $description ) : ?>
			<span class="description"><?php echo esc_html( $description ); ?></span>
		<?php endif; ?>
	</p>
	<?php
}

function weiyintex_admin_media_field( $path, $label, $description = '' ) {
	$id_path       = weiyintex_site_image_id_path( $path );
	$attachment_id = weiyintex_site_image_id( $path );
	$image_url     = weiyintex_site_image_url( $path, 'medium' );
	$field_id      = 'weiyintex_' . sanitize_html_class( str_replace( '.', '_', $path ) );
	$id_field_id   = $field_id . '_id';
	$path_field_id = $field_id . '_path';
	$preview_id    = $field_id . '_preview';
	?>
	<div class="weiyintex-media-field">
		<p><strong><?php echo esc_html( $label ); ?></strong></p>
		<div id="<?php echo esc_attr( $preview_id ); ?>" class="weiyintex-media-preview">
			<?php if ( $image_url ) : ?>
				<img src="<?php echo esc_url( $image_url ); ?>" alt="">
			<?php endif; ?>
		</div>
		<input id="<?php echo esc_attr( $id_field_id ); ?>" type="hidden" name="<?php echo weiyintex_admin_field_name( $id_path ); ?>" value="<?php echo esc_attr( (string) $attachment_id ); ?>">
		<p>
			<label for="<?php echo esc_attr( $path_field_id ); ?>">备用路径或图片 URL</label>
			<input id="<?php echo esc_attr( $path_field_id ); ?>" class="widefat weiyintex-media-path" type="text" name="<?php echo weiyintex_admin_field_name( $path ); ?>" value="<?php echo esc_attr( weiyintex_site_text( $path ) ); ?>">
		</p>
		<p>
			<button type="button" class="button weiyintex-select-media" data-target-id="<?php echo esc_attr( $id_field_id ); ?>" data-target-path="<?php echo esc_attr( $path_field_id ); ?>" data-preview="<?php echo esc_attr( $preview_id ); ?>">从媒体库选择</button>
			<button type="button" class="button weiyintex-remove-media" data-target-id="<?php echo esc_attr( $id_field_id ); ?>" data-preview="<?php echo esc_attr( $preview_id ); ?>">移除媒体 ID</button>
			<span class="description">媒体 ID：<span class="weiyintex-current-id"><?php echo esc_html( (string) $attachment_id ); ?></span></span>
		</p>
		<?php if ( $description ) : ?>
			<p class="description"><?php echo esc_html( $description ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

add_action(
	'admin_menu',
	function () {
		add_theme_page( '站点内容配置', '站点内容配置', 'manage_options', 'weiyintex-site-content', 'weiyintex_render_site_content_admin' );
	}
);

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( 'appearance_page_weiyintex-site-content' !== $hook ) {
			return;
		}

		wp_enqueue_media();
		wp_add_inline_style(
			'wp-admin',
			'.weiyintex-media-field{margin:18px 0 24px;padding:14px 16px;border:1px solid #dcdcde;background:#fff}.weiyintex-media-preview{min-height:44px;margin:8px 0}.weiyintex-media-preview img{max-width:220px;max-height:140px;height:auto;display:block;border:1px solid #dcdcde;background:#f6f7f7}.weiyintex-current-id{font-family:monospace}'
		);
		wp_add_inline_script(
			'jquery-core',
			"(function($){
				$(document).on('click', '.weiyintex-select-media', function(e){
					e.preventDefault();
					var button = $(this);
					var frame = wp.media({ title: '选择图片', library: { type: 'image' }, multiple: false });
					frame.on('select', function(){
						var attachment = frame.state().get('selection').first().toJSON();
						$('#' + button.data('target-id')).val(attachment.id);
						$('#' + button.data('target-path')).val(attachment.url);
						$('#' + button.data('preview')).html('<img src=\"' + attachment.url + '\" alt=\"\">');
						button.siblings('.description').find('.weiyintex-current-id').text(attachment.id);
					});
					frame.open();
				});
				$(document).on('click', '.weiyintex-remove-media', function(e){
					e.preventDefault();
					var button = $(this);
					$('#' + button.data('target-id')).val('0');
					$('#' + button.data('preview')).empty();
					button.siblings('.description').find('.weiyintex-current-id').text('0');
				});
			})(jQuery);"
		);
	}
);

function weiyintex_render_site_content_admin() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( isset( $_POST['weiyintex_site_content_nonce'], $_POST['weiyintex_site_content'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['weiyintex_site_content_nonce'] ) ), 'weiyintex_site_content' ) ) {
		$raw = wp_unslash( $_POST['weiyintex_site_content'] );
		update_option( 'weiyintex_site_content', weiyintex_sanitize_site_content( $raw ) );
		weiyintex_site_options( true );
		echo '<div class="notice notice-success is-dismissible"><p>站点内容已保存。</p></div>';
	}

	?>
	<div class="wrap weiyintex-content-admin">
		<h1>站点内容配置</h1>
		<p>编辑当前主题使用的首页、联系信息和页脚内容。</p>
		<form method="post">
			<?php wp_nonce_field( 'weiyintex_site_content', 'weiyintex_site_content_nonce' ); ?>

			<h2>SEO 基础配置</h2>
			<?php
			weiyintex_admin_text_field( 'seo.site_name', 'SEO 站点名', '用于搜索标题后缀和社交分享站点名。' );
			weiyintex_admin_text_field( 'seo.title', '默认 SEO 标题', '主要用于首页；内页可在编辑页面里单独覆盖。' );
			weiyintex_admin_textarea_field( 'seo.description', '默认 SEO 描述', '首页默认描述，也作为其他页面缺少摘要时的兜底。' );
			weiyintex_admin_text_field( 'seo.products_title', '产品列表 SEO 标题', '用于 /products/ 产品列表页。' );
			weiyintex_admin_textarea_field( 'seo.products_description', '产品列表 SEO 描述', '用于 /products/ 产品列表页。' );
			weiyintex_admin_text_field( 'seo.keywords', '默认 SEO 关键词', '可选。Google 不用于排名，但可以保留给其他搜索引擎或内部管理。' );
			weiyintex_admin_media_field( 'seo.og_image', '默认分享图', '用于社交分享预览；建议使用横向图片。' );
			?>

			<h2>品牌</h2>
			<?php weiyintex_admin_media_field( 'brand.logo', 'Logo 图片', '从媒体库选择。文本路径仅作为备用。' ); ?>

			<h2>首页首屏</h2>
			<?php
			weiyintex_admin_media_field( 'hero.background', '背景图', '用于首页首屏标题背后的背景。' );
			weiyintex_admin_text_field( 'hero.title', '标题' );
			weiyintex_admin_textarea_field( 'hero.subtitle', '副标题' );
			weiyintex_admin_text_field( 'hero.button_text', '按钮文字' );
			weiyintex_admin_text_field( 'hero.button_url', '按钮链接', '示例：contact-us、products、#section-id、tel:+861234567890。' );
			?>

			<h2>优势卡片</h2>
			<?php for ( $i = 0; $i < 4; $i++ ) : ?>
				<h3>优势 <?php echo esc_html( (string) ( $i + 1 ) ); ?></h3>
				<?php
				weiyintex_admin_media_field( "features.$i.image", '图片' );
				weiyintex_admin_text_field( "features.$i.title", '标题' );
				weiyintex_admin_textarea_field( "features.$i.description", '描述' );
				?>
			<?php endfor; ?>

			<h2>产品区块</h2>
			<?php
			weiyintex_admin_text_field( 'products.title', '标题' );
			weiyintex_admin_textarea_field( 'products.description', '描述' );
			weiyintex_admin_text_field( 'products.button_text', '按钮文字' );
			weiyintex_admin_text_field( 'products.button_url', '按钮链接' );
			?>

			<h2>介绍与数据</h2>
			<?php
			weiyintex_admin_media_field( 'intro.background', '介绍区背景图', '用于介绍与数据区块标题背后的背景。' );
			weiyintex_admin_textarea_field( 'intro.headline_html', '介绍标题 HTML' );
			weiyintex_admin_textarea_field( 'intro.body_html', '介绍正文 HTML' );
			?>
			<?php for ( $i = 0; $i < 4; $i++ ) : ?>
				<h3>数据 <?php echo esc_html( (string) ( $i + 1 ) ); ?></h3>
				<?php
				weiyintex_admin_text_field( "stats.$i.title", '标题' );
				weiyintex_admin_textarea_field( "stats.$i.description", '描述' );
				?>
			<?php endfor; ?>

			<h2>关于我们区块</h2>
			<?php
			weiyintex_admin_media_field( 'about.image', '图片' );
			weiyintex_admin_text_field( 'about.title', '标题' );
			weiyintex_admin_textarea_field( 'about.body_html', '正文 HTML' );
			weiyintex_admin_text_field( 'about.button_text', '按钮文字' );
			weiyintex_admin_text_field( 'about.button_url', '按钮链接' );
			?>

			<h2>为什么选择我们</h2>
			<?php
			weiyintex_admin_text_field( 'why.title', '标题' );
			weiyintex_admin_textarea_field( 'why.subtitle', '副标题' );
			?>
			<?php for ( $i = 0; $i < 5; $i++ ) : ?>
				<h3>理由 <?php echo esc_html( (string) ( $i + 1 ) ); ?></h3>
				<?php
				weiyintex_admin_media_field( "why.items.$i.image", '图片' );
				weiyintex_admin_text_field( "why.items.$i.title", '标题' );
				weiyintex_admin_textarea_field( "why.items.$i.description", '描述' );
				?>
			<?php endfor; ?>

			<h2>博客、联系信息、页脚</h2>
			<?php
			weiyintex_admin_text_field( 'blog.title', '博客标题' );
			weiyintex_admin_text_field( 'contact.heading', '联系区标题' );
			weiyintex_admin_text_field( 'contact.email', '邮箱' );
			weiyintex_admin_text_field( 'contact.phone', '电话' );
			weiyintex_admin_text_field( 'contact.wechat', '微信' );
			weiyintex_admin_text_field( 'contact.whatsapp', 'WhatsApp' );
			weiyintex_admin_text_field( 'contact.whatsapp_message', 'WhatsApp 默认消息' );
			weiyintex_admin_text_field( 'footer.company_title', '页脚公司标题' );
			weiyintex_admin_text_field( 'footer.product_title', '页脚产品标题' );
			weiyintex_admin_textarea_field( 'footer.description', '页脚描述' );
			weiyintex_admin_text_field( 'footer.copyright', '版权信息' );
			?>

			<h2>弹窗表单标签</h2>
			<?php
			weiyintex_admin_text_field( 'form.title', '表单标题' );
			weiyintex_admin_text_field( 'form.name_label', '姓名标签' );
			weiyintex_admin_text_field( 'form.email_label', '邮箱标签' );
			weiyintex_admin_text_field( 'form.phone_label', '电话标签' );
			weiyintex_admin_text_field( 'form.message_label', '留言标签' );
			weiyintex_admin_text_field( 'form.submit_label', '提交按钮文字' );
			?>

			<?php submit_button( '保存站点内容' ); ?>
		</form>
	</div>
	<?php
}

function weiyintex_product_image_url( $post_id, $meta_key, $fallback = '', $size = 'large' ) {
	$attachment_id = absint( get_post_meta( $post_id, $meta_key . '_id', true ) );

	if ( $attachment_id ) {
		$url = wp_get_attachment_image_url( $attachment_id, $size );

		if ( $url ) {
			return $url;
		}
	}

	$path = get_post_meta( $post_id, $meta_key, true );

	if ( $path ) {
		return weiyintex_home_url( $path );
	}

	if ( '_weiyintex_image' === $meta_key && has_post_thumbnail( $post_id ) ) {
		return get_the_post_thumbnail_url( $post_id, $size );
	}

	return weiyintex_home_url( $fallback );
}

function weiyintex_product_category_slug( $post_id ) {
	$terms = get_the_terms( $post_id, 'weiyintex_product_category' );

	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return '';
	}

	return sanitize_html_class( 'product_cat-' . $terms[0]->slug );
}

function weiyintex_render_home_products( $limit = 8 ) {
	$products = new WP_Query(
		array(
			'post_type'      => 'weiyintex_product',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => array(
				'menu_order' => 'ASC',
				'date'       => 'DESC',
			),
		)
	);

	if ( ! $products->have_posts() ) {
		echo '<li class="product type-product instock"><h2 class="woocommerce-loop-product__title">No products published yet.</h2></li>';
		return;
	}

	$index = 0;

	while ( $products->have_posts() ) {
		$products->the_post();

		$post_id      = get_the_ID();
		$title        = get_the_title();
		$permalink    = get_permalink();
		$sku          = get_post_meta( $post_id, '_weiyintex_sku', true );
		$image        = weiyintex_product_image_url( $post_id, '_weiyintex_image', 'wp-content/uploads/2024/08/2-6-500x500.jpg' );
		$hover_image  = weiyintex_product_image_url( $post_id, '_weiyintex_hover_image', 'wp-content/uploads/2024/08/1-3-500x500.jpg' );

		if ( $hover_image === $image ) {
			$hover_image = '';
		}

		$position     = 0 === $index % 4 ? ' first' : ( 3 === $index % 4 ? ' last' : '' );
		$category_css = weiyintex_product_category_slug( $post_id );
		$class_name   = trim( "product type-product post-{$post_id} status-publish{$position} instock {$category_css} has-post-thumbnail shipping-taxable product-type-simple" );

		?>
		<li <?php post_class( $class_name, $post_id ); ?>>
			<figure>
				<a class="ct-media-container has-hover-effect" href="<?php echo esc_url( $permalink ); ?>" aria-label="<?php echo esc_attr( $title ); ?>">
					<?php if ( $hover_image && $hover_image !== $image ) : ?>
						<img loading="lazy" decoding="async" width="500" height="500" src="<?php echo esc_url( $hover_image ); ?>" alt="<?php echo esc_attr( $title ); ?>" class="ct-swap" style="aspect-ratio: 1/1;" />
					<?php endif; ?>
					<img loading="lazy" decoding="async" width="500" height="500" src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $title ); ?>" itemprop="image" class="wp-post-image" style="aspect-ratio: 1/1;" />
				</a>
			</figure>
			<h2 class="woocommerce-loop-product__title">
				<a class="woocommerce-LoopProduct-link woocommerce-loop-product__link" href="<?php echo esc_url( $permalink ); ?>" target="_self"><?php echo esc_html( $title ); ?></a>
			</h2>
			<div class="ct-woo-card-actions" data-add-to-cart="auto-hide">
				<a href="<?php echo esc_url( $permalink ); ?>" data-quantity="1" class="button product_type_simple" data-product_id="<?php echo esc_attr( $post_id ); ?>" data-product_sku="<?php echo esc_attr( $sku ); ?>" aria-label="<?php echo esc_attr( 'Read more about ' . $title ); ?>" rel="nofollow">Read more</a>
			</div>
		</li>
		<?php

		++$index;
	}

	wp_reset_postdata();
}

function weiyintex_post_image_url( $post_id ) {
	$attachment_id = absint( get_post_meta( $post_id, '_weiyintex_image_id', true ) );

	if ( $attachment_id ) {
		$url = wp_get_attachment_image_url( $attachment_id, 'medium' );

		if ( $url ) {
			return $url;
		}
	}

	if ( has_post_thumbnail( $post_id ) ) {
		return get_the_post_thumbnail_url( $post_id, 'medium' );
	}

	$content_image = weiyintex_post_content_image_url( $post_id );

	if ( $content_image ) {
		return $content_image;
	}

	$path = get_post_meta( $post_id, '_weiyintex_image', true );

	if ( $path ) {
		return weiyintex_home_url( $path );
	}

	return weiyintex_home_url( 'wp-content/uploads/2021/04/55415166-300x164.jpg' );
}

function weiyintex_post_content_image_url( $post_id ) {
	$content = get_post_field( 'post_content', $post_id );

	if ( ! $content ) {
		return '';
	}

	if ( preg_match( '/wp-image-(\d+)/', $content, $matches ) ) {
		$url = wp_get_attachment_image_url( absint( $matches[1] ), 'medium' );

		if ( $url ) {
			return $url;
		}
	}

	if ( preg_match( '/<img[^>]+src=[\'"]([^\'"]+)[\'"]/i', $content, $matches ) ) {
		return esc_url_raw( $matches[1] );
	}

	return '';
}

function weiyintex_render_home_posts( $limit = 3 ) {
	$posts = new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => $limit,
			'ignore_sticky_posts' => true,
		)
	);

	if ( ! $posts->have_posts() ) {
		echo '<div class="bdt-gallery-item bdt-width-1-1"><h4 class="bdt-gallery-item-title bdt-margin-remove">No posts published yet.</h4></div>';
		return;
	}

	while ( $posts->have_posts() ) {
		$posts->the_post();

		$post_id   = get_the_ID();
		$title     = get_the_title();
		$permalink = get_permalink();
		$image     = weiyintex_post_image_url( $post_id );

		?>
		<div class="bdt-gallery-item bdt-transition-toggle bdt-width-1-1 bdt-width-1-2@s bdt-width-1-3@m">
			<div class="bdt-post-gallery-inner">
				<div class="bdt-gallery-thumbnail">
					<img loading="lazy" decoding="async" width="300" height="200" src="<?php echo esc_url( $image ); ?>" class="attachment-medium size-medium wp-post-image" alt="<?php echo esc_attr( $title ); ?>" />
				</div>
				<div class="bdt-position-top-right bdt-margin bdt-margin-right">
					<div class="bdt-post-gallery-content">
						<div class="bdt-gallery-content-inner bdt-transition-fade">
							<div class="bdt-flex-inline bdt-gallery-item-link-wrapper">
								<a class="bdt-gallery-item-link bdt-link-icon" href="<?php echo esc_url( $permalink ); ?>" target="_blank" rel="noopener">
									<i class="ep-icon-link" aria-hidden="true"></i>
								</a>
							</div>
						</div>
					</div>
				</div>
				<div class="bdt-post-gallery-desc bdt-text-left bdt-position-z-index bdt-position-bottom">
					<a href="<?php echo esc_url( $permalink ); ?>" target="_blank" rel="noopener">
						<h4 class="bdt-gallery-item-title bdt-margin-remove"><?php echo esc_html( $title ); ?></h4>
					</a>
				</div>
				<div class="bdt-position-top-left"></div>
			</div>
		</div>
		<?php
	}

	wp_reset_postdata();
}

function weiyintex_render_blog_posts( $limit = 12 ) {
	$posts = new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => $limit,
			'ignore_sticky_posts' => true,
		)
	);

	if ( ! $posts->have_posts() ) {
		echo '<p class="weiyintex-blog-empty">No posts published yet.</p>';
		return;
	}

	echo '<div class="weiyintex-blog-grid">';

	while ( $posts->have_posts() ) {
		$posts->the_post();

		$post_id   = get_the_ID();
		$title     = get_the_title();
		$permalink = get_permalink();
		$image     = weiyintex_post_image_url( $post_id );
		$excerpt   = get_the_excerpt();

		if ( ! $excerpt ) {
			$excerpt = wp_trim_words( wp_strip_all_tags( get_the_content() ), 24 );
		}

		?>
		<article class="weiyintex-blog-card">
			<a href="<?php echo esc_url( $permalink ); ?>">
				<img loading="lazy" decoding="async" src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $title ); ?>">
			</a>
			<div class="weiyintex-blog-card-body">
				<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
				<h2><a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a></h2>
				<p><?php echo esc_html( $excerpt ); ?></p>
				<a class="weiyintex-blog-more" href="<?php echo esc_url( $permalink ); ?>">Read more</a>
			</div>
		</article>
		<?php
	}

	echo '</div>';

	wp_reset_postdata();
}
