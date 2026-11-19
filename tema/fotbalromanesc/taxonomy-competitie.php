<?php
/**
 * Pagina unei competiții: Știri / Meciuri / Clasament.
 *
 * @package fotbalromanesc
 */

defined( 'ABSPATH' ) || exit;

get_header();

$fr_t     = get_queried_object();
$fr_date  = fr_date_competitie( $fr_t->term_id );
$fr_file  = array( 'stiri' => 'Știri' );
if ( $fr_date && $fr_date['meciuri'] ) {
	$fr_file['meciuri'] = 'Meciuri';
}
if ( $fr_date && $fr_date['clasament'] ) {
	$fr_file['clasament'] = 'Clasament';
}
$fr_fila  = fr_fila_curenta( array_keys( $fr_file ), 'stiri' );
$fr_copii = get_terms( array( 'taxonomy' => 'competitie', 'parent' => $fr_t->parent ?: $fr_t->term_id, 'hide_empty' => false, 'meta_key' => 'fr_ordine', 'orderby' => 'meta_value_num' ) );
$fr_par   = $fr_t->parent ? get_term( $fr_t->parent, 'competitie' ) : null;
?>

<section class="fr-pagina-cap">
	<div class="fr-container">
		<?php if ( $fr_par && ! is_wp_error( $fr_par ) ) : ?>
			<a class="fr-pagina-cap__sus" href="<?php echo esc_url( get_term_link( $fr_par ) ); ?>"><?php echo esc_html( $fr_par->name ); ?></a>
		<?php endif; ?>
		<div class="fr-pagina-cap__rand">
			<div>
				<h1 class="fr-titlu-mare"><?php echo esc_html( $fr_t->name ); ?></h1>
				<?php if ( $fr_t->description ) : ?>
					<div class="fr-pagina-cap__desc"><?php echo wp_kses_post( wpautop( $fr_t->description ) ); ?></div>
				<?php endif; ?>
			</div>
			<a class="fr-buton" href="<?php echo esc_url( get_term_feed_link( $fr_t->term_id, 'competitie' ) ); ?>"><?php echo fr_icon( 'rss' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>Urmărește</a>
		</div>
		<?php if ( $fr_copii && ! is_wp_error( $fr_copii ) && count( $fr_copii ) > 1 ) : ?>
			<nav class="fr-pastile fr-pastile--link" aria-label="Serii">
				<?php foreach ( $fr_copii as $c ) : ?>
					<a class="fr-pastila" href="<?php echo esc_url( get_term_link( $c ) ); ?>"<?php echo $c->term_id === $fr_t->term_id ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $c->name ); ?></a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>
		<?php if ( count( $fr_file ) > 1 ) : ?>
			<?php fr_file( $fr_file, $fr_fila ); ?>
		<?php endif; ?>
	</div>
</section>

<main id="continut" class="fr-container fr-layout">
	<div class="fr-layout__principal">
	<?php if ( 'meciuri' === $fr_fila && get_term_meta( $fr_t->term_id, 'fr_echipa_livescore', true ) ) : ?>
		<?php
		// Naționalele: meciurile vin din competiții diferite, deci le arătăm pe „Urmează” și „Rezultate”, nu pe etape.
		$fr_urm = array_values( array_filter( $fr_date['meciuri'], fn( $m ) => 'terminat' !== $m['stare'] ) );
		$fr_rez = array_reverse( array_values( array_filter( $fr_date['meciuri'], fn( $m ) => 'terminat' === $m['stare'] ) ) );
		?>
		<section class="fr-card">
			<?php echo fr_titlu_sectiune( 'Urmează' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php echo fr_lista_meciuri( $fr_urm ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</section>
		<section class="fr-card">
			<?php echo fr_titlu_sectiune( 'Rezultate' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php echo fr_lista_meciuri( array_slice( $fr_rez, 0, 20 ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</section>

	<?php elseif ( 'meciuri' === $fr_fila ) : ?>
		<?php
		$fr_runde = array();
		foreach ( $fr_date['meciuri'] as $m ) {
			$fr_runde[ $m['runda'] ][] = $m;
		}
		$fr_runda = isset( $_GET['runda'] ) ? sanitize_text_field( wp_unslash( $_GET['runda'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! isset( $fr_runde[ $fr_runda ] ) ) {
			// Etapa meciului care urmează (sau se joacă acum); după ultimul meci al sezonului, ultima etapă.
			$fr_runda = '';
			foreach ( $fr_date['meciuri'] as $m ) {
				if ( 'live' === $m['stare'] || ( 'programat' === $m['stare'] && strtotime( $m['data'] ) >= time() - 3 * HOUR_IN_SECONDS ) ) {
					$fr_runda = (string) $m['runda'];
					break;
				}
			}
			if ( '' === $fr_runda && $fr_runde ) {
				$fr_runda = (string) array_key_last( $fr_runde );
			}
		}
		$fr_chei = array_map( 'strval', array_keys( $fr_runde ) );
		$fr_poz  = array_search( $fr_runda, $fr_chei, true );
		$fr_baza = add_query_arg( 'fila', 'meciuri', get_term_link( $fr_t ) );
		?>
		<div class="fr-card">
			<div class="fr-runde">
				<?php if ( $fr_poz > 0 ) : ?>
					<a class="fr-buton fr-buton--contur" href="<?php echo esc_url( add_query_arg( 'runda', $fr_chei[ $fr_poz - 1 ], $fr_baza ) ); ?>">‹ Înapoi</a>
				<?php else : ?><span></span><?php endif; ?>
				<h2 class="fr-titlu-sectiune"><?php echo esc_html( is_numeric( $fr_runda ) ? 'Etapa ' . $fr_runda : ( $fr_runda ?: 'Meciuri' ) ); ?></h2>
				<?php if ( false !== $fr_poz && $fr_poz < count( $fr_chei ) - 1 ) : ?>
					<a class="fr-buton fr-buton--contur" href="<?php echo esc_url( add_query_arg( 'runda', $fr_chei[ $fr_poz + 1 ], $fr_baza ) ); ?>">Înainte ›</a>
				<?php else : ?><span></span><?php endif; ?>
			</div>
			<?php echo fr_lista_meciuri( $fr_runde[ $fr_runda ] ?? array() ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>

	<?php elseif ( 'clasament' === $fr_fila ) : ?>
		<div class="fr-card">
			<?php echo fr_titlu_sectiune( 'Clasament' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php echo fr_tabel_clasament( $fr_date, array( 'complet' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>

	<?php else : ?>
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
			<div class="fr-card"><p class="fr-gol">Încă nu există articole în <?php echo esc_html( $fr_t->name ); ?>.</p></div>
		<?php endif; ?>
	<?php endif; ?>
	</div>

	<aside class="fr-layout__lateral">
		<?php if ( 'clasament' !== $fr_fila && $fr_date && $fr_date['clasament'] ) : ?>
			<section class="fr-card">
				<?php echo fr_titlu_sectiune( 'Clasament', add_query_arg( 'fila', 'clasament', get_term_link( $fr_t ) ), 'Complet' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php echo fr_tabel_clasament( $fr_date, array( 'max' => 8 ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</section>
		<?php endif; ?>
		<?php echo fr_sponsor( $fr_t ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<?php if ( is_active_sidebar( 'bara-laterala' ) ) : ?>
			<?php dynamic_sidebar( 'bara-laterala' ); ?>
		<?php endif; ?>
	</aside>
</main>

<?php
get_footer();
