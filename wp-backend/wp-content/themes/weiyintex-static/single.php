<?php
/**
 * Single post template.
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
				the_content();
			}
			?>
		</div>
	</section>
</main>
<?php weiyintex_simple_footer(); ?>
