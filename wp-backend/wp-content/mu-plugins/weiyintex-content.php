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
					'name'          => '产品',
					'singular_name' => '产品',
					'add_new'       => '新增产品',
					'add_new_item'  => '新增产品',
					'edit_item'     => '编辑产品',
					'new_item'      => '新产品',
					'view_item'     => '查看产品',
					'search_items'  => '搜索产品',
					'all_items'     => '全部产品',
					'menu_name'     => '产品',
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
					'name'          => '产品分类',
					'singular_name' => '产品分类',
					'search_items'  => '搜索产品分类',
					'all_items'     => '全部产品分类',
					'edit_item'     => '编辑产品分类',
					'update_item'   => '更新产品分类',
					'add_new_item'  => '新增产品分类',
					'new_item_name' => '新产品分类名称',
					'menu_name'     => '产品分类',
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

		if ( ! $screen || ! in_array( $screen->post_type, array( 'weiyintex_product', 'post' ), true ) ) {
			return;
		}

		wp_enqueue_media();
		wp_add_inline_script(
			'jquery-core',
			"(function($){
				$(document).on('click', '.weiyintex-product-select-media', function(e){
					e.preventDefault();
					var button = $(this);
					var frame = wp.media({ title: '选择产品图片', library: { type: 'image' }, multiple: false });
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
		add_meta_box( 'weiyintex_product_details', '产品详情', 'weiyintex_render_product_details_box', 'weiyintex_product', 'normal', 'high' );
		add_meta_box( 'weiyintex_post_image', '博客列表图片', 'weiyintex_render_post_image_box', 'post', 'normal', 'high' );
	}
);

function weiyintex_render_product_details_box( $post ) {
	wp_nonce_field( 'weiyintex_product_details', 'weiyintex_product_details_nonce' );

	$sku = get_post_meta( $post->ID, '_weiyintex_sku', true );
	?>
	<p>
		<label for="_weiyintex_sku"><strong>产品编号 SKU</strong></label>
		<input id="_weiyintex_sku" name="_weiyintex_sku" type="text" value="<?php echo esc_attr( $sku ); ?>" class="widefat" />
	</p>
	<?php
	weiyintex_render_product_media_field( $post->ID, '_weiyintex_image', '主图' );
	weiyintex_render_product_media_field( $post->ID, '_weiyintex_hover_image', '悬停图' );
	echo '<p class="description">图片建议从 WordPress 媒体库选择。路径字段仅作为旧数据或备用图片地址保留。</p>';
}

function weiyintex_render_post_image_box( $post ) {
	wp_nonce_field( 'weiyintex_post_image', 'weiyintex_post_image_nonce' );

	weiyintex_render_product_media_field( $post->ID, '_weiyintex_image', '博客列表图片' );
	echo '<p class="description">用于博客列表和首页 Latest from the Blog。未选择时会使用文章特色图片、正文首图或旧路径。</p>';
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
			<label for="<?php echo esc_attr( $field_id ); ?>_path">备用路径或图片 URL</label>
			<input id="<?php echo esc_attr( $field_id ); ?>_path" name="<?php echo esc_attr( $path_key ); ?>" type="text" value="<?php echo esc_attr( $path ); ?>" class="widefat" />
		</p>
		<p>
			<button type="button" class="button weiyintex-product-select-media" data-target-id="<?php echo esc_attr( $field_id ); ?>_id" data-target-path="<?php echo esc_attr( $field_id ); ?>_path" data-preview="<?php echo esc_attr( $field_id ); ?>_preview">从媒体库选择</button>
			<button type="button" class="button weiyintex-product-remove-media" data-target-id="<?php echo esc_attr( $field_id ); ?>_id" data-preview="<?php echo esc_attr( $field_id ); ?>_preview">移除媒体 ID</button>
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

add_action(
	'save_post_post',
	function ( $post_id ) {
		if ( ! isset( $_POST['weiyintex_post_image_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['weiyintex_post_image_nonce'] ) ), 'weiyintex_post_image' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['_weiyintex_image'] ) ) {
			update_post_meta( $post_id, '_weiyintex_image', sanitize_text_field( wp_unslash( $_POST['_weiyintex_image'] ) ) );
		}

		if ( isset( $_POST['_weiyintex_image_id'] ) ) {
			update_post_meta( $post_id, '_weiyintex_image_id', absint( $_POST['_weiyintex_image_id'] ) );
		}
	}
);
