<?php
/**
 * Single product template.
 *
 * @package WeiyintexStatic
 */

weiyintex_simple_head( get_the_title() );
weiyintex_simple_header();
?>
<main>
	<section class="weiyintex-page-hero">
		<div class="weiyintex-shell-inner">
			<h1><?php the_title(); ?></h1>
		</div>
	</section>
	<section class="weiyintex-page-content">
		<div class="weiyintex-shell-inner">
			<?php
			while ( have_posts() ) {
				the_post();
				$image = weiyintex_product_image_url( get_the_ID(), '_weiyintex_image', 'wp-content/uploads/2024/08/2-6-500x500.jpg' );
				?>
				<div class="weiyintex-grid" style="grid-template-columns: minmax(0, 440px) minmax(0, 1fr); align-items: start;">
					<img src="<?php echo esc_url( $image ); ?>" alt="<?php the_title_attribute(); ?>" style="width:100%; aspect-ratio:1/1; object-fit:cover;">
					<div>
						<?php the_content(); ?>
					</div>
				</div>
				<?php
			}
			?>
		</div>
	</section>
</main>
<?php weiyintex_simple_footer(); ?>
