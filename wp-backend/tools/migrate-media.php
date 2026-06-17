<?php
/**
 * Register mirrored upload files as WordPress media attachments and bind content to attachment IDs.
 *
 * Run with:
 * php .tools/wp-cli.phar eval-file wp-backend/tools/migrate-media.php --path=wp-backend
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

function weiyintex_migrate_upload_relative_path( $value ) {
	$value = trim( (string) $value );

	if ( '' === $value ) {
		return '';
	}

	$value = html_entity_decode( $value, ENT_QUOTES );
	$value = rawurldecode( $value );

	if ( preg_match( '#^https?://#i', $value ) ) {
		$path = wp_parse_url( $value, PHP_URL_PATH );
		$value = is_string( $path ) ? $path : '';
	}

	$marker = 'wp-content/uploads/';
	$pos    = strpos( $value, $marker );

	if ( false !== $pos ) {
		return ltrim( substr( $value, $pos + strlen( $marker ) ), '/' );
	}

	return ltrim( $value, '/' );
}

function weiyintex_migrate_attachment_by_relative_path( $relative_path ) {
	$relative_path = weiyintex_migrate_upload_relative_path( $relative_path );

	if ( '' === $relative_path ) {
		return 0;
	}

	$attachments = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_wp_attached_file',
			'meta_value'     => $relative_path,
		)
	);

	return $attachments ? (int) $attachments[0] : 0;
}

function weiyintex_migrate_register_attachment( $file_path ) {
	$uploads = wp_upload_dir();
	$basedir = realpath( $uploads['basedir'] );
	$real    = realpath( $file_path );

	if ( ! $basedir || ! $real || 0 !== strpos( $real, $basedir ) ) {
		return 0;
	}

	$relative_path = ltrim( str_replace( DIRECTORY_SEPARATOR, '/', substr( $real, strlen( $basedir ) ) ), '/' );
	$existing_id   = weiyintex_migrate_attachment_by_relative_path( $relative_path );

	if ( $existing_id ) {
		return $existing_id;
	}

	$filetype = wp_check_filetype( basename( $real ), null );

	if ( empty( $filetype['type'] ) || 0 !== strpos( $filetype['type'], 'image/' ) ) {
		return 0;
	}

	$title = preg_replace( '/\.[^.]+$/', '', basename( $real ) );
	$guid  = trailingslashit( $uploads['baseurl'] ) . str_replace( '%2F', '/', rawurlencode( $relative_path ) );

	$attachment_id = wp_insert_attachment(
		array(
			'guid'           => $guid,
			'post_mime_type' => $filetype['type'],
			'post_title'     => sanitize_text_field( $title ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		),
		$real
	);

	if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
		return 0;
	}

	update_post_meta( $attachment_id, '_wp_attached_file', $relative_path );

	$metadata = wp_generate_attachment_metadata( $attachment_id, $real );

	if ( ! empty( $metadata ) && ! is_wp_error( $metadata ) ) {
		wp_update_attachment_metadata( $attachment_id, $metadata );
	}

	return (int) $attachment_id;
}

function weiyintex_migrate_register_all_upload_images() {
	$uploads = wp_upload_dir();
	$files   = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $uploads['basedir'], FilesystemIterator::SKIP_DOTS )
	);
	$count   = 0;

	foreach ( $files as $file ) {
		if ( ! $file->isFile() ) {
			continue;
		}

		$extension = strtolower( $file->getExtension() );

		if ( ! in_array( $extension, array( 'jpg', 'jpeg', 'png', 'webp', 'gif' ), true ) ) {
			continue;
		}

		if ( weiyintex_migrate_register_attachment( $file->getPathname() ) ) {
			++$count;
		}
	}

	return $count;
}

function weiyintex_migrate_get_nested_value( $data, $segments ) {
	foreach ( $segments as $segment ) {
		if ( ! is_array( $data ) || ! array_key_exists( $segment, $data ) ) {
			return '';
		}

		$data = $data[ $segment ];
	}

	return $data;
}

function weiyintex_migrate_set_nested_value( &$data, $segments, $value ) {
	$cursor = &$data;

	foreach ( $segments as $segment ) {
		if ( ! isset( $cursor[ $segment ] ) || ! is_array( $cursor[ $segment ] ) ) {
			$cursor[ $segment ] = array();
		}

		$cursor = &$cursor[ $segment ];
	}

	$cursor = $value;
}

function weiyintex_migrate_bind_site_image( &$content, $path ) {
	$segments = explode( '.', $path );
	$value    = weiyintex_migrate_get_nested_value( $content, $segments );
	$relative = weiyintex_migrate_upload_relative_path( $value );
	$id       = weiyintex_migrate_attachment_by_relative_path( $relative );

	if ( ! $id ) {
		return 0;
	}

	$id_segments = $segments;
	$last        = array_pop( $id_segments );
	$id_segments[] = $last . '_id';
	weiyintex_migrate_set_nested_value( $content, $id_segments, $id );

	return $id;
}

function weiyintex_migrate_bind_site_content() {
	if ( ! function_exists( 'weiyintex_site_defaults' ) ) {
		return 0;
	}

	$content = get_option( 'weiyintex_site_content', array() );

	if ( ! is_array( $content ) ) {
		$content = array();
	}

	$content = array_replace_recursive( weiyintex_site_defaults(), $content );
	$paths   = array( 'brand.logo', 'about.image' );

	for ( $i = 0; $i < 4; $i++ ) {
		$paths[] = "features.$i.image";
	}

	for ( $i = 0; $i < 5; $i++ ) {
		$paths[] = "why.items.$i.image";
	}

	$count = 0;

	foreach ( $paths as $path ) {
		if ( weiyintex_migrate_bind_site_image( $content, $path ) ) {
			++$count;
		}
	}

	update_option( 'weiyintex_site_content', $content );

	return $count;
}

function weiyintex_migrate_bind_post_image_meta( $post_id, $path_key, $id_key, $set_thumbnail = false ) {
	$path = get_post_meta( $post_id, $path_key, true );
	$id   = weiyintex_migrate_attachment_by_relative_path( $path );

	if ( ! $id ) {
		return 0;
	}

	update_post_meta( $post_id, $id_key, $id );

	if ( $set_thumbnail ) {
		set_post_thumbnail( $post_id, $id );
	}

	return $id;
}

function weiyintex_migrate_bind_products() {
	$query = new WP_Query(
		array(
			'post_type'      => 'weiyintex_product',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);
	$count = 0;

	foreach ( $query->posts as $post_id ) {
		if ( weiyintex_migrate_bind_post_image_meta( $post_id, '_weiyintex_image', '_weiyintex_image_id', true ) ) {
			++$count;
		}

		if ( weiyintex_migrate_bind_post_image_meta( $post_id, '_weiyintex_hover_image', '_weiyintex_hover_image_id', false ) ) {
			++$count;
		}
	}

	return $count;
}

function weiyintex_migrate_bind_posts() {
	$query = new WP_Query(
		array(
			'post_type'      => 'post',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => '_weiyintex_image',
		)
	);
	$count = 0;

	foreach ( $query->posts as $post_id ) {
		if ( weiyintex_migrate_bind_post_image_meta( $post_id, '_weiyintex_image', '_weiyintex_image_id', true ) ) {
			++$count;
		}
	}

	return $count;
}

$registered_count = weiyintex_migrate_register_all_upload_images();
$site_count       = weiyintex_migrate_bind_site_content();
$product_count    = weiyintex_migrate_bind_products();
$post_count       = weiyintex_migrate_bind_posts();

WP_CLI::success(
	sprintf(
		'Media migration complete. Registered/verified %d images, bound %d site images, %d product image refs, %d post image refs.',
		$registered_count,
		$site_count,
		$product_count,
		$post_count
	)
);
