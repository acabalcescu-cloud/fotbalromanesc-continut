<?php
/**
 * Listele de articole: blog, autor, etichetă, dată, căutare.
 *
 * @package fotbalromanesc
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( is_search() ) {
	/* translators: %s: termenul căutat */
	$fr_titlu = sprintf( 'Rezultate pentru „%s”', get_search_query() );
} elseif ( is_home() ) {
	$fr_titlu = get_option( 'page_for_posts' ) ? get_the_title( get_option( 'page_for_posts' ) ) : 'Ultimele articole';
} elseif ( is_author() ) {
	$fr_titlu = get_the_author_meta( 'display_name', get_queried_object_id() );
} else {
	$fr_titlu = wp_strip_all_tags( get_the_archive_title() );
}
?>

<section class="fr-pagina-cap">
	<div class="fr-container">
		<h1 class="fr-titlu-mare"><?php echo esc_html( $fr_titlu ); ?></h1>
		<?php if ( is_author() && get_the_author_meta( 'description', get_queried_object_id() ) ) : ?>
			<p class="fr-pagina-cap__desc"><?php echo esc_html( get_the_author_meta( 'description', get_queried_object_id() ) ); ?></p>
		<?php elseif ( get_the_archive_description() ) : ?>
			<div class="fr-pagina-cap__desc"><?php echo wp_kses_post( get_the_archive_description() ); ?></div>
		<?php endif; ?>
		<?php if ( is_search() ) : ?>
			<div class="fr-cautare-pagina"><?php get_search_form(); ?></div>
		<?php endif; ?>
	</div>
</section>

<main id="continut" class="fr-container fr-layout">
	<div class="fr-layout__principal">
		<?php if ( have_posts() ) : ?>
			<div class="fr-grila">
				<?php
				while ( have_posts() ) :
					the_post();
					echo fr_card_articol( get_post(), 'card' ); // phpcs:ignore WordPress.Security.EscapeOutput
				endwhile;
				?>
			</div>
			<?php the_posts_pagination( array( 'mid_size' => 1, 'prev_text' => '‹ Mai noi', 'next_text' => 'Mai vechi ›' ) ); ?>
		<?php else : ?>
			<div class="fr-card">
				<p class="fr-gol"><?php echo is_search() ? 'Nu am găsit nimic. Încearcă un nume de echipă sau de jucător.' : 'Încă nu există articole aici.'; ?></p>
			</div>
		<?php endif; ?>
	</div>
	<aside class="fr-layout__lateral">
		<?php get_template_part( 'template-parts/clasamente-tab' ); ?>
		<?php if ( is_active_sidebar( 'bara-laterala' ) ) : ?>
			<?php dynamic_sidebar( 'bara-laterala' ); ?>
		<?php endif; ?>
	</aside>
</main>

<?php
get_footer();
