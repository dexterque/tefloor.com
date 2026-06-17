<?php
/**
 * Product archive template.
 *
 * @package WeiyintexStatic
 */

weiyintex_simple_head( 'Products' );
weiyintex_simple_header();
?>
<main>
	<section class="weiyintex-page-hero">
		<div class="weiyintex-shell-inner">
			<h1>Products</h1>
		</div>
	</section>
	<section class="weiyintex-page-content">
		<div class="weiyintex-shell-inner weiyintex-grid">
			<?php
			while ( have_posts() ) {
				the_post();
				weiyintex_render_simple_product_card();
			}
			?>
		</div>
	</section>
</main>
<?php weiyintex_simple_footer(); ?>
