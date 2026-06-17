<?php
/**
 * Seed Weiyintex demo content into WordPress.
 *
 * Run with:
 * php ../.tools/wp-cli.phar eval-file tools/seed-content.php --path=wp-backend
 */

$mock_file = __DIR__ . '/data/homepage-data.json';

if ( ! file_exists( $mock_file ) ) {
	WP_CLI::error( 'Missing mock data file: ' . $mock_file );
}

$data = json_decode( file_get_contents( $mock_file ), true );

if ( ! is_array( $data ) ) {
	WP_CLI::error( 'Invalid seed data JSON.' );
}

if ( function_exists( 'weiyintex_site_defaults' ) ) {
	$stored_site_content = get_option( 'weiyintex_site_content', array() );

	if ( ! is_array( $stored_site_content ) ) {
		$stored_site_content = array();
	}

	update_option( 'weiyintex_site_content', array_replace_recursive( weiyintex_site_defaults(), $stored_site_content ) );
}

$default_post = get_page_by_path( 'hello-world', OBJECT, 'post' );

if ( $default_post && 'Hello world!' === $default_post->post_title ) {
	wp_delete_post( $default_post->ID, true );
}

function weiyintex_seed_slug_from_path( $path ) {
	$path = trim( (string) $path, '/' );
	$path = preg_replace( '#^product/#', 'products/', $path );
	$base = basename( $path );

	return sanitize_title( $base ?: $path );
}

function weiyintex_seed_find_post( $post_type, $slug ) {
	$query = new WP_Query(
		array(
			'post_type'      => $post_type,
			'name'           => $slug,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		)
	);

	return $query->posts ? (int) $query->posts[0] : 0;
}

function weiyintex_seed_product_catalog_structure( $data ) {
	if ( ! empty( $data['productCategories'] ) && is_array( $data['productCategories'] ) ) {
		return $data['productCategories'];
	}

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

function weiyintex_seed_ensure_product_category( $item, $parent_id = 0 ) {
	$name = $item['title'] ?? '';
	$slug = sanitize_title( $item['slug'] ?? $name );

	if ( '' === $name || '' === $slug ) {
		return 0;
	}

	$term = get_term_by( 'slug', $slug, 'weiyintex_product_category' );

	if ( $term && ! is_wp_error( $term ) ) {
		$term_id = (int) $term->term_id;
		wp_update_term(
			$term_id,
			'weiyintex_product_category',
			array(
				'name'   => $name,
				'parent' => (int) $parent_id,
			)
		);
	} else {
		$created = wp_insert_term(
			$name,
			'weiyintex_product_category',
			array(
				'slug'   => $slug,
				'parent' => (int) $parent_id,
			)
		);

		if ( is_wp_error( $created ) ) {
			WP_CLI::warning( 'Could not create product category: ' . $name );
			return 0;
		}

		$term_id = (int) $created['term_id'];
	}

	foreach ( $item['children'] ?? array() as $child ) {
		weiyintex_seed_ensure_product_category( $child, $term_id );
	}

	return $term_id;
}

function weiyintex_seed_ensure_product_categories( $items, $parent_id = 0 ) {
	foreach ( $items as $item ) {
		weiyintex_seed_ensure_product_category( $item, $parent_id );
	}
}

function weiyintex_seed_collect_leaf_category_slugs( $items ) {
	$slugs = array();

	foreach ( $items as $item ) {
		if ( ! empty( $item['children'] ) ) {
			$slugs = array_merge( $slugs, weiyintex_seed_collect_leaf_category_slugs( $item['children'] ) );
			continue;
		}

		$slugs[] = sanitize_title( $item['slug'] ?? $item['title'] ?? '' );
	}

	return array_values( array_filter( $slugs ) );
}

function weiyintex_seed_collect_category_slugs( $items ) {
	$slugs = array();

	foreach ( $items as $item ) {
		$slug = sanitize_title( $item['slug'] ?? $item['title'] ?? '' );

		if ( $slug ) {
			$slugs[] = $slug;
		}

		if ( ! empty( $item['children'] ) ) {
			$slugs = array_merge( $slugs, weiyintex_seed_collect_category_slugs( $item['children'] ) );
		}
	}

	return array_values( array_unique( $slugs ) );
}

function weiyintex_seed_delete_legacy_product_categories( $protected_slugs ) {
	$legacy_slugs = array(
		'fleece-blanket',
		'sweatshirt-jersey-blanket',
		'travel-blanket',
		'pet-blanket',
		'beach-towel',
		'outdoor-blanket',
		'down-camping-blanket',
		'picnic-blanket-travel-blanket',
		'hoodie-blanket',
		'bath-series',
		'custom-shirt',
	);

	foreach ( $legacy_slugs as $slug ) {
		if ( in_array( $slug, $protected_slugs, true ) ) {
			continue;
		}

		$term = get_term_by( 'slug', $slug, 'weiyintex_product_category' );

		if ( $term && ! is_wp_error( $term ) ) {
			wp_delete_term( (int) $term->term_id, 'weiyintex_product_category' );
		}
	}
}

function weiyintex_seed_find_category_slug_by_name( $items, $name ) {
	foreach ( $items as $item ) {
		if ( isset( $item['title'] ) && $item['title'] === $name ) {
			return sanitize_title( $item['slug'] ?? $item['title'] );
		}

		if ( ! empty( $item['children'] ) ) {
			$slug = weiyintex_seed_find_category_slug_by_name( $item['children'], $name );

			if ( $slug ) {
				return $slug;
			}
		}
	}

	return '';
}

function weiyintex_seed_add_product_menu_items( $menu_id, $items, $parent_menu_id = 0 ) {
	foreach ( $items as $item ) {
		$slug = sanitize_title( $item['slug'] ?? $item['title'] ?? '' );
		$term = get_term_by( 'slug', $slug, 'weiyintex_product_category' );

		if ( ! $term || is_wp_error( $term ) ) {
			continue;
		}

		$menu_item_id = wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'     => $term->name,
				'menu-item-object-id' => $term->term_id,
				'menu-item-object'    => 'weiyintex_product_category',
				'menu-item-type'      => 'taxonomy',
				'menu-item-parent-id' => (int) $parent_menu_id,
				'menu-item-status'    => 'publish',
			)
		);

		if ( ! is_wp_error( $menu_item_id ) && ! empty( $item['children'] ) ) {
			weiyintex_seed_add_product_menu_items( $menu_id, $item['children'], (int) $menu_item_id );
		}
	}
}

function weiyintex_seed_page( $title, $slug, $content ) {
	$post_id = weiyintex_seed_find_post( 'page', $slug );

	$post_data = array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => $title,
		'post_name'    => $slug,
		'post_content' => $content,
	);

	if ( $post_id ) {
		$post_data['ID'] = $post_id;
		wp_update_post( $post_data );
		return $post_id;
	}

	return wp_insert_post( $post_data, true );
}

$about_page = weiyintex_seed_page(
	'About Us',
	'about-us',
	'<p>Shaoxing Weiyin Textile Co., Ltd. is a professional home textile manufacturer focused on custom blankets, towels, bath textiles, and outdoor textile programs.</p><p>Our team supports OEM and ODM production, sample development, quality control, and global delivery for brand owners and cross-border sellers.</p>'
);

$blog_page = weiyintex_seed_page(
	'Blog',
	'blog',
	'<p>Insights about custom textile development, material selection, sampling, production, and export programs.</p>'
);

$contact_page = weiyintex_seed_page(
	'Contact Us',
	'contact-us',
	'<p>Email: <a href="mailto:black.tan@weiyintex.com">black.tan@weiyintex.com</a></p><p>Phone / WhatsApp: <a href="tel:+8615267506726">+86 15267506726</a></p><p>Send your product brief, target quantity, fabric preference, and required delivery date so the team can prepare a proposal.</p>'
);

if ( ! is_wp_error( $blog_page ) ) {
	update_option( 'page_for_posts', (int) $blog_page );
}

$product_catalog = weiyintex_seed_product_catalog_structure( $data );
weiyintex_seed_ensure_product_categories( $product_catalog );
weiyintex_seed_delete_legacy_product_categories( weiyintex_seed_collect_category_slugs( $product_catalog ) );

$seeded_product_ids = array();

foreach ( $data['products'] ?? array() as $index => $product ) {
	$slug    = weiyintex_seed_slug_from_path( $product['permalink'] ?? $product['name'] );
	$post_id = weiyintex_seed_find_post( 'weiyintex_product', $slug );

	$post_data = array(
		'post_type'    => 'weiyintex_product',
		'post_status'  => 'publish',
		'post_title'   => $product['name'],
		'post_name'    => $slug,
		'post_content' => $product['excerpt'] ?? 'Custom textile product available for OEM and ODM programs.',
		'menu_order'   => $index + 1,
	);

	if ( $post_id ) {
		$post_data['ID'] = $post_id;
		wp_update_post( $post_data );
	} else {
		$post_id = wp_insert_post( $post_data, true );
	}

	if ( is_wp_error( $post_id ) ) {
		WP_CLI::warning( 'Could not save product: ' . $product['name'] );
		continue;
	}

	$seeded_product_ids[] = (int) $post_id;

	update_post_meta( $post_id, '_weiyintex_sku', $product['sku'] ?? '' );
	update_post_meta( $post_id, '_weiyintex_image', $product['image'] ?? '' );
	update_post_meta( $post_id, '_weiyintex_hover_image', $product['hoverImage'] ?? '' );

	if ( ! empty( $product['category'] ) ) {
		$category_slug = $product['categorySlug'] ?? weiyintex_seed_find_category_slug_by_name( $product_catalog, $product['category'] );
		$term          = $category_slug ? term_exists( $category_slug, 'weiyintex_product_category' ) : false;

		if ( ! $term ) {
			$term = wp_insert_term( $product['category'], 'weiyintex_product_category', array( 'slug' => sanitize_title( $product['category'] ) ) );
		}

		if ( ! is_wp_error( $term ) ) {
			wp_set_object_terms( $post_id, (int) $term['term_id'], 'weiyintex_product_category' );
		}
	}
}

if ( $seeded_product_ids ) {
	$old_product_ids = get_posts(
		array(
			'post_type'      => 'weiyintex_product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'post__not_in'   => $seeded_product_ids,
		)
	);

	foreach ( $old_product_ids as $old_product_id ) {
		wp_update_post(
			array(
				'ID'          => (int) $old_product_id,
				'post_status' => 'draft',
			)
		);
	}
}

foreach ( $data['posts'] ?? array() as $post ) {
	$slug    = weiyintex_seed_slug_from_path( $post['link'] ?? $post['title'] );
	$post_id = weiyintex_seed_find_post( 'post', $slug );

	$post_data = array(
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_title'   => $post['title'],
		'post_name'    => $slug,
		'post_content' => $post['excerpt'] ?? '',
		'post_excerpt' => $post['excerpt'] ?? '',
	);

	if ( $post_id ) {
		$post_data['ID'] = $post_id;
		wp_update_post( $post_data );
	} else {
		$post_id = wp_insert_post( $post_data, true );
	}

	if ( is_wp_error( $post_id ) ) {
		WP_CLI::warning( 'Could not save post: ' . $post['title'] );
		continue;
	}

	update_post_meta( $post_id, '_weiyintex_image', $post['image'] ?? '' );
}

$menu_name = 'Main Menu';
$menu      = wp_get_nav_menu_object( $menu_name );

if ( ! $menu ) {
	$menu_id = wp_create_nav_menu( $menu_name );
} else {
	$menu_id = $menu->term_id;
	foreach ( wp_get_nav_menu_items( $menu_id ) ?: array() as $item ) {
		wp_delete_post( $item->ID, true );
	}
}

if ( ! is_wp_error( $menu_id ) ) {
	$home_id = wp_update_nav_menu_item(
		$menu_id,
		0,
		array(
			'menu-item-title'  => 'Home',
			'menu-item-url'    => home_url( '/' ),
			'menu-item-status' => 'publish',
		)
	);

	$products_id = wp_update_nav_menu_item(
		$menu_id,
		0,
		array(
			'menu-item-title'  => 'Products',
			'menu-item-url'    => get_post_type_archive_link( 'weiyintex_product' ),
			'menu-item-status' => 'publish',
		)
	);

	if ( ! is_wp_error( $products_id ) ) {
		weiyintex_seed_add_product_menu_items( $menu_id, $product_catalog, (int) $products_id );
	}

	foreach (
		array(
			array( 'id' => $about_page, 'label' => 'About' ),
			array( 'id' => $blog_page, 'label' => 'Blog' ),
			array( 'id' => $contact_page, 'label' => 'Contact' ),
		) as $page_item
	) {
		$page_id = $page_item['id'];
		$label   = $page_item['label'];

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			wp_update_nav_menu_item(
				$menu_id,
				0,
				array(
					'menu-item-title'     => $label,
					'menu-item-object-id' => (int) $page_id,
					'menu-item-object'    => 'page',
					'menu-item-type'      => 'post_type',
					'menu-item-status'    => 'publish',
				)
			);
		}
	}

	$locations            = get_theme_mod( 'nav_menu_locations', array() );
	$locations['primary'] = $menu_id;
	set_theme_mod( 'nav_menu_locations', $locations );
}

$footer_company_menu = wp_get_nav_menu_object( 'Footer Company Menu' );

if ( ! $footer_company_menu ) {
	$footer_company_menu_id = wp_create_nav_menu( 'Footer Company Menu' );
} else {
	$footer_company_menu_id = $footer_company_menu->term_id;
	foreach ( wp_get_nav_menu_items( $footer_company_menu_id ) ?: array() as $item ) {
		wp_delete_post( $item->ID, true );
	}
}

if ( ! is_wp_error( $footer_company_menu_id ) ) {
	foreach (
		array(
			array( 'id' => $about_page, 'label' => 'About Us' ),
			array( 'id' => $blog_page, 'label' => 'Blog' ),
			array( 'id' => $contact_page, 'label' => 'Contact Us' ),
		) as $page_item
	) {
		if ( $page_item['id'] && ! is_wp_error( $page_item['id'] ) ) {
			wp_update_nav_menu_item(
				$footer_company_menu_id,
				0,
				array(
					'menu-item-title'     => $page_item['label'],
					'menu-item-object-id' => (int) $page_item['id'],
					'menu-item-object'    => 'page',
					'menu-item-type'      => 'post_type',
					'menu-item-status'    => 'publish',
				)
			);
		}
	}
}

$footer_products_menu = wp_get_nav_menu_object( 'Footer Product Menu' );

if ( ! $footer_products_menu ) {
	$footer_products_menu_id = wp_create_nav_menu( 'Footer Product Menu' );
} else {
	$footer_products_menu_id = $footer_products_menu->term_id;
	foreach ( wp_get_nav_menu_items( $footer_products_menu_id ) ?: array() as $item ) {
		wp_delete_post( $item->ID, true );
	}
}

if ( ! is_wp_error( $footer_products_menu_id ) ) {
	foreach ( weiyintex_seed_collect_leaf_category_slugs( $product_catalog ) as $term_slug ) {
		$term = get_term_by( 'slug', $term_slug, 'weiyintex_product_category' );

		if ( $term && ! is_wp_error( $term ) ) {
			wp_update_nav_menu_item(
				$footer_products_menu_id,
				0,
				array(
					'menu-item-title'     => $term->name,
					'menu-item-object-id' => $term->term_id,
					'menu-item-object'    => 'weiyintex_product_category',
					'menu-item-type'      => 'taxonomy',
					'menu-item-status'    => 'publish',
				)
			);
		}
	}
}

$locations = get_theme_mod( 'nav_menu_locations', array() );

if ( isset( $footer_company_menu_id ) && ! is_wp_error( $footer_company_menu_id ) ) {
	$locations['footer_company'] = $footer_company_menu_id;
}

if ( isset( $footer_products_menu_id ) && ! is_wp_error( $footer_products_menu_id ) ) {
	$locations['footer_products'] = $footer_products_menu_id;
}

set_theme_mod( 'nav_menu_locations', $locations );

flush_rewrite_rules();

WP_CLI::success( 'Seeded Weiyintex dynamic content.' );
