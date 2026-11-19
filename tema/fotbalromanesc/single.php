<?php
/**
 * Un articol.
 *
 * @package fotbalromanesc
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	$fr_comp    = fr_competitia_articolului();
	$fr_cluburi = get_the_terms( get_the_ID(), 'club' );
	?>
	<main id="continut" class="fr-container fr-layout">
		<article <?php post_class( 'fr-layout__principal fr-text' ); ?>>
			<header class="fr-text__cap">
				<?php echo fr_eticheta_articol(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<h1 class="fr-text__titlu"><?php the_title(); ?></h1>
				<?php if ( has_excerpt() ) : ?>
					<p class="fr-text__rezumat"><?php echo esc_html( get_the_excerpt() ); ?></p>
				<?php endif; ?>
				<p class="fr-text__meta">
					<?php if ( get_the_author() ) : ?>
						<a href="<?php echo esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ); ?>"><?php the_author(); ?></a> ·
					<?php endif; ?>
					<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date( 'j F Y, H:i' ) ); ?></time>
					<?php if ( get_the_modified_time( 'U' ) - get_the_time( 'U' ) > HOUR_IN_SECONDS ) : ?>
						· actualizat <?php echo esc_html( get_the_modified_date( 'j F, H:i' ) ); ?>
					<?php endif; ?>
				</p>
			</header>

			<?php if ( has_post_thumbnail() && ! has_post_format( 'video' ) ) : ?>
				<figure class="fr-text__coperta">
					<?php the_post_thumbnail( 'fr-mare', array( 'loading' => 'eager', 'fetchpriority' => 'high' ) ); ?>
					<?php if ( get_the_post_thumbnail_caption() ) : ?>
						<figcaption><?php the_post_thumbnail_caption(); ?></figcaption>
					<?php endif; ?>
				</figure>
			<?php endif; ?>

			<div class="fr-text__continut">
				<?php
				the_content();
				wp_link_pages( array( 'before' => '<nav class="fr-pagini">Pagini: ', 'after' => '</nav>' ) );
				?>
			</div>

			<footer class="fr-text__subsol">
				<?php if ( $fr_cluburi && ! is_wp_error( $fr_cluburi ) ) : ?>
					<p class="fr-text__cluburi">
						<?php foreach ( $fr_cluburi as $c ) : ?>
							<a class="fr-pastila" href="<?php echo esc_url( get_term_link( $c ) ); ?>"><?php echo esc_html( $c->name ); ?></a>
						<?php endforeach; ?>
					</p>
				<?php endif; ?>
				<?php the_tags( '<p class="fr-text__etichete">', ' ', '</p>' ); ?>

				<?php if ( get_the_author_meta( 'description' ) ) : ?>
					<div class="fr-autor">
						<?php echo get_avatar( get_the_author_meta( 'ID' ), 56 ); ?>
						<div>
							<p class="fr-autor__nume"><?php the_author(); ?></p>
							<p><?php echo esc_html( get_the_author_meta( 'description' ) ); ?></p>
						</div>
					</div>
				<?php endif; ?>
			</footer>

			<?php if ( is_active_sidebar( 'dupa-articol' ) ) : ?>
				<?php dynamic_sidebar( 'dupa-articol' ); ?>
			<?php endif; ?>

			<?php
			$fr_legate = $fr_comp ? get_posts( array(
				'numberposts'  => 3,
				'post__not_in' => array( get_the_ID() ),
				'tax_query'    => array( array( 'taxonomy' => 'competitie', 'terms' => $fr_comp->term_id ) ),
			) ) : array();
			if ( $fr_legate ) :
				?>
				<section class="fr-sectiune" aria-label="Citește și">
					<?php echo fr_titlu_sectiune( 'Mai mult din ' . fr_eticheta_competitie( $fr_comp ), get_term_link( $fr_comp ), 'Toate' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<div class="fr-grila fr-grila--3">
						<?php foreach ( $fr_legate as $p ) : ?>
							<?php echo fr_card_articol( $p, 'lista' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>

			<?php
			if ( comments_open() || get_comments_number() ) {
				comments_template();
			}
			?>
		</article>

		<aside class="fr-layout__lateral">
			<?php
			$fr_date = null;
			$fr_ct   = $fr_comp;
			while ( $fr_ct && ! $fr_date ) {
				$fr_date = fr_date_competitie( $fr_ct->term_id );
				$fr_ct   = ! $fr_date && $fr_ct->parent ? get_term( $fr_ct->parent, 'competitie' ) : null;
				$fr_ct   = $fr_ct instanceof WP_Term ? $fr_ct : null;
			}
			if ( $fr_date && $fr_date['clasament'] ) :
				?>
				<section class="fr-card">
					<?php echo fr_titlu_sectiune( 'Clasament ' . fr_eticheta_competitie( $fr_comp ), add_query_arg( 'fila', 'clasament', get_term_link( $fr_comp ) ), 'Complet' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php echo fr_tabel_clasament( $fr_date, array( 'max' => 10 ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</section>
			<?php endif; ?>
			<?php echo fr_sponsor( $fr_cluburi && ! is_wp_error( $fr_cluburi ) && 1 === count( $fr_cluburi ) ? $fr_cluburi[0] : $fr_comp ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php if ( is_active_sidebar( 'bara-laterala' ) ) : ?>
				<?php dynamic_sidebar( 'bara-laterala' ); ?>
			<?php endif; ?>
		</aside>
	</main>
	<?php
endwhile;

get_footer();
