<?php
/**
 * Site content model for Weiyintex.
 *
 * @package WeiyintexContent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	function () {
		register_post_type(
			'weiyintex_product',
			array(
				'labels'       => array(
					'name'          => 'Products',
					'singular_name' => 'Product',
					'add_new_item'  => 'Add New Product',
					'edit_item'     => 'Edit Product',
				),
				'public'       => true,
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-products',
				'has_archive'  => 'products',
				'rewrite'      => array( 'slug' => 'products' ),
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes', 'custom-fields' ),
			)
		);

		register_taxonomy(
			'weiyintex_product_category',
			array( 'weiyintex_product' ),
			array(
				'labels'       => array(
					'name'          => 'Product Categories',
					'singular_name' => 'Product Category',
				),
				'hierarchical' => true,
				'public'       => true,
				'show_in_rest' => true,
				'rewrite'      => array( 'slug' => 'product-category' ),
			)
		);

		foreach ( array( '_weiyintex_sku', '_weiyintex_image', '_weiyintex_hover_image' ) as $meta_key ) {
			register_post_meta(
				'weiyintex_product',
				$meta_key,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'sanitize_text_field',
					'auth_callback'     => function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}

		foreach ( array( '_weiyintex_image_id', '_weiyintex_hover_image_id' ) as $meta_key ) {
			register_post_meta(
				'weiyintex_product',
				$meta_key,
				array(
					'type'              => 'integer',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'absint',
					'auth_callback'     => function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}
);

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || 'weiyintex_product' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();
		wp_add_inline_script(
			'jquery-core',
			"(function($){
				$(document).on('click', '.weiyintex-product-select-media', function(e){
					e.preventDefault();
					var button = $(this);
					var frame = wp.media({ title: 'Select product image', library: { type: 'image' }, multiple: false });
					frame.on('select', function(){
						var attachment = frame.state().get('selection').first().toJSON();
						$('#' + button.data('target-id')).val(attachment.id);
						$('#' + button.data('target-path')).val(attachment.url);
						$('#' + button.data('preview')).html('<img src=\"' + attachment.url + '\" alt=\"\" style=\"max-width:180px;max-height:120px;height:auto;border:1px solid #dcdcde;\">');
					});
					frame.open();
				});
				$(document).on('click', '.weiyintex-product-remove-media', function(e){
					e.preventDefault();
					var button = $(this);
					$('#' + button.data('target-id')).val('0');
					$('#' + button.data('preview')).empty();
				});
			})(jQuery);"
		);
	}
);

add_action(
	'add_meta_boxes',
	function () {
		add_meta_box( 'weiyintex_product_details', 'Product Details', 'weiyintex_render_product_details_box', 'weiyintex_product', 'normal', 'high' );
	}
);

function weiyintex_render_product_details_box( $post ) {
	wp_nonce_field( 'weiyintex_product_details', 'weiyintex_product_details_nonce' );

	$sku = get_post_meta( $post->ID, '_weiyintex_sku', true );
	?>
	<p>
		<label for="_weiyintex_sku"><strong>SKU</strong></label>
		<input id="_weiyintex_sku" name="_weiyintex_sku" type="text" value="<?php echo esc_attr( $sku ); ?>" class="widefat" />
	</p>
	<?php
	weiyintex_render_product_media_field( $post->ID, '_weiyintex_image', 'Primary image' );
	weiyintex_render_product_media_field( $post->ID, '_weiyintex_hover_image', 'Hover image' );
	echo '<p class="description">Images should be selected from the WordPress Media Library. The path fields are kept as fallbacks for legacy mirrored assets.</p>';
}

function weiyintex_render_product_media_field( $post_id, $path_key, $label ) {
	$id_key        = $path_key . '_id';
	$attachment_id = absint( get_post_meta( $post_id, $id_key, true ) );
	$path          = get_post_meta( $post_id, $path_key, true );
	$preview       = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'medium' ) : '';
	$field_id      = sanitize_html_class( $path_key );
	?>
	<div style="margin:18px 0;padding:12px;border:1px solid #dcdcde;background:#fff;">
		<p><strong><?php echo esc_html( $label ); ?></strong></p>
		<div id="<?php echo esc_attr( $field_id ); ?>_preview" style="min-height:36px;margin-bottom:8px;">
			<?php if ( $preview ) : ?>
				<img src="<?php echo esc_url( $preview ); ?>" alt="" style="max-width:180px;max-height:120px;height:auto;border:1px solid #dcdcde;">
			<?php endif; ?>
		</div>
		<input id="<?php echo esc_attr( $field_id ); ?>_id" name="<?php echo esc_attr( $id_key ); ?>" type="hidden" value="<?php echo esc_attr( (string) $attachment_id ); ?>">
		<p>
			<label for="<?php echo esc_attr( $field_id ); ?>_path">Fallback path or URL</label>
			<input id="<?php echo esc_attr( $field_id ); ?>_path" name="<?php echo esc_attr( $path_key ); ?>" type="text" value="<?php echo esc_attr( $path ); ?>" class="widefat" />
		</p>
		<p>
			<button type="button" class="button weiyintex-product-select-media" data-target-id="<?php echo esc_attr( $field_id ); ?>_id" data-target-path="<?php echo esc_attr( $field_id ); ?>_path" data-preview="<?php echo esc_attr( $field_id ); ?>_preview">Choose from Media Library</button>
			<button type="button" class="button weiyintex-product-remove-media" data-target-id="<?php echo esc_attr( $field_id ); ?>_id" data-preview="<?php echo esc_attr( $field_id ); ?>_preview">Remove media ID</button>
		</p>
	</div>
	<?php
}

add_action(
	'save_post_weiyintex_product',
	function ( $post_id ) {
		if ( ! isset( $_POST['weiyintex_product_details_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['weiyintex_product_details_nonce'] ) ), 'weiyintex_product_details' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		foreach ( array( '_weiyintex_sku', '_weiyintex_image', '_weiyintex_hover_image' ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_post_meta( $post_id, $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
			}
		}

		foreach ( array( '_weiyintex_image_id', '_weiyintex_hover_image_id' ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_post_meta( $post_id, $key, absint( $_POST[ $key ] ) );
			}
		}
	}
);
