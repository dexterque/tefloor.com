<?php
/**
 * Blog index template.
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
		<div class="weiyintex-shell-inner weiyintex-grid">
			<?php
			while ( have_posts() ) {
				the_post();
				$image = weiyintex_post_image_url( get_the_ID() );
				?>
				<article class="weiyintex-card">
					<a href="<?php the_permalink(); ?>">
						<img loading="lazy" src="<?php echo esc_url( $image ); ?>" alt="<?php the_title_attribute(); ?>">
						<h2><?php the_title(); ?></h2>
					</a>
				</article>
				<?php
			}
			?>
		</div>
	</section>
</main>
<?php weiyintex_simple_footer(); ?>
