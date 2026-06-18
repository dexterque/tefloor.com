<?php
/**
 * Blog page template.
 *
 * @package WeiyintexStatic
 */

weiyintex_simple_head( 'Blog' );
weiyintex_simple_header();
?>
<main>
	<section class="weiyintex-page-hero">
		<div class="weiyintex-shell-inner">
			<h1>Blog</h1>
		</div>
	</section>
	<section class="weiyintex-page-content">
		<div class="weiyintex-shell-inner">
			<?php weiyintex_render_blog_posts(); ?>
		</div>
	</section>
</main>
<?php weiyintex_simple_footer(); ?>
