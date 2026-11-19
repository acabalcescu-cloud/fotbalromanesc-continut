<?php
/**
 * Pagina unui club: Prezentare / Meciuri / Știri.
 *
 * @package fotbalromanesc
 */

defined( 'ABSPATH' ) || exit;

get_header();

$fr_club  = get_queried_object();
$fr_comp  = (int) get_term_meta( $fr_club->term_id, 'fr_competitie', true );
$fr_compt = $fr_comp ? get_term( $fr_comp, 'competitie' ) : null;
$fr_date  = $fr_comp ? fr_date_competitie( $fr_comp ) : null;
$fr_nume  = get_term_meta( $fr_club->term_id, 'fr_livescore_nume', true );
if ( ! $fr_nume ) {
	$fr_nume = array_search( $fr_club->name, fr_nume_implicite(), true ) ?: $fr_club->name;
}
$fr_meciuri = $fr_date ? fr_meciurile_echipei( $fr_date, $fr_nume ) : array();
$fr_rand    = null;
foreach ( $fr_date['clasament'] ?? array() as $r ) {
	if ( $r['echipa'] === $fr_nume ) {
		$fr_rand = $r;
	}
}
$fr_file = array( 'prezentare' => 'Prezentare' );
if ( $fr_meciuri ) {
	$fr_file['meciuri'] = 'Meciuri';
}
$fr_file['stiri'] = 'Știri';
$fr_fila  = fr_fila_curenta( array_keys( $fr_file ), 'prezentare' );
$fr_sigla = get_term_meta( $fr_club->term_id, 'fr_sigla', true );

$fr_terminate = array_values( array_filter( $fr_meciuri, fn( $m ) => 'terminat' === $m['stare'] && $m['scor'] ) );
$fr_urmatoare = array_values( array_filter( $fr_meciuri, fn( $m ) => in_array( $m['stare'], array( 'programat', 'live' ), true ) ) );
$fr_rezultat  = function ( $m ) use ( $fr_nume ) {
	list( $g, $o ) = $m['scor'];
	$noi = $m['gazde'] === $fr_nume ? $g : $o;
	$ei  = $m['gazde'] === $fr_nume ? $o : $g;
	return $noi > $ei ? 'V' : ( $noi === $ei ? 'E' : 'Î' );
};
?>

<section class="fr-pagina-cap">
	<div class="fr-container">
		<div class="fr-pagina-cap__rand">
			<div class="fr-club-cap">
				<?php if ( $fr_sigla ) : ?>
					<img class="fr-sigla" src="<?php echo esc_url( $fr_sigla ); ?>" alt="" width="72" height="72">
				<?php else : ?>
					<span class="fr-sigla fr-sigla--gol" aria-hidden="true"><?php echo esc_html( mb_substr( $fr_club->name, 0, 1 ) ); ?></span>
				<?php endif; ?>
				<div>
					<h1 class="fr-titlu-mare"><?php echo esc_html( $fr_club->name ); ?></h1>
					<?php if ( $fr_compt && ! is_wp_error( $fr_compt ) ) : ?>
						<p class="fr-pagina-cap__info">
							<a href="<?php echo esc_url( get_term_link( $fr_compt ) ); ?>"><?php echo esc_html( fr_eticheta_competitie( $fr_compt ) ); ?></a>
							<?php if ( $fr_rand ) : ?>
								· locul <?php echo (int) $fr_rand['loc']; ?> · <?php echo (int) $fr_rand['pct']; ?> de puncte după <?php echo (int) $fr_rand['meciuri']; ?> de meciuri
							<?php endif; ?>
						</p>
					<?php endif; ?>
				</div>
			</div>
			<a class="fr-buton" href="<?php echo esc_url( get_term_feed_link( $fr_club->term_id, 'club' ) ); ?>"><?php echo fr_icon( 'rss' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>Urmărește</a>
		</div>
		<?php fr_file( $fr_file, $fr_fila ); ?>
	</div>
</section>

<main id="continut" class="fr-container">
<?php if ( 'meciuri' === $fr_fila ) : ?>
	<div class="fr-layout">
		<div class="fr-layout__principal">
			<section class="fr-card">
				<?php echo fr_titlu_sectiune( 'Urmează' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php echo fr_lista_meciuri( $fr_urmatoare ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</section>
			<section class="fr-card">
				<?php echo fr_titlu_sectiune( 'Rezultate' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php echo fr_lista_meciuri( array_reverse( $fr_terminate ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</section>
		</div>
		<aside class="fr-layout__lateral"><?php echo fr_sponsor( $fr_club ); // phpcs:ignore WordPress.Security.EscapeOutput ?></aside>
	</div>

<?php elseif ( 'stiri' === $fr_fila ) : ?>
	<div class="fr-layout">
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
				<div class="fr-card"><p class="fr-gol">Încă nu există articole despre <?php echo esc_html( $fr_club->name ); ?>.</p></div>
			<?php endif; ?>
		</div>
		<aside class="fr-layout__lateral"><?php echo fr_sponsor( $fr_club ); // phpcs:ignore WordPress.Security.EscapeOutput ?></aside>
	</div>

<?php else : ?>
	<div class="fr-panouri">

		<?php if ( $fr_terminate ) : ?>
			<?php
			$fr_ult5 = array_slice( $fr_terminate, -5 );
			$fr_lit  = array_map( $fr_rezultat, $fr_ult5 );
			$fr_vict = 0;
			for ( $i = count( $fr_lit ) - 1; $i >= 0 && 'V' === $fr_lit[ $i ]; $i-- ) {
				$fr_vict++;
			}
			?>
			<section class="fr-card">
				<h2 class="fr-titlu-sectiune"><?php echo esc_html( $fr_vict >= 3 ? 'Forma: ' . $fr_vict . ' victorii la rând' : 'Forma' ); ?></h2>
				<ul class="fr-forma-lista">
					<?php foreach ( array_reverse( $fr_ult5 ) as $m ) : ?>
						<li>
							<?php echo fr_chip_forma( $fr_rezultat( $m ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<span><?php echo esc_html( fr_nume_echipa( $m['gazde'] ) . ' – ' . fr_nume_echipa( $m['oaspeti'] ) ); ?></span>
							<b><?php echo (int) $m['scor'][0] . '-' . (int) $m['scor'][1]; ?></b>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>

		<?php if ( $fr_urmatoare ) : ?>
			<?php $m = $fr_urmatoare[0]; ?>
			<section class="fr-card fr-card--inchis">
				<h2 class="fr-titlu-sectiune"><?php echo 'live' === $m['stare'] ? 'Acum, în direct' : 'Următorul meci'; ?></h2>
				<p class="fr-card__mic"><?php echo esc_html( ( $fr_compt && ! is_wp_error( $fr_compt ) ? fr_eticheta_competitie( $fr_compt ) . ' · ' : '' ) . ( is_numeric( $m['runda'] ) ? 'etapa ' . $m['runda'] . ' · ' : '' ) . fr_data_meci( $m['data'], 'zi' ) ); ?></p>
				<div class="fr-duel">
					<b><?php echo fr_echipa( $m['gazde'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></b>
					<span class="fr-duel__ora"><?php echo esc_html( 'live' === $m['stare'] && $m['scor'] ? $m['scor'][0] . '-' . $m['scor'][1] : fr_data_meci( $m['data'], 'ora' ) ); ?></span>
					<b><?php echo fr_echipa( $m['oaspeti'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></b>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( $fr_terminate ) : ?>
			<?php
			$m    = end( $fr_terminate );
			$marc = fr_marcatori_meci( $m );
			$fmt  = fn( $l ) => implode( '<br>', array_map( fn( $g ) => esc_html( $g['nume'] . ' ' . $g['minut'] . '′' . ( $g['tip'] ? ' (' . $g['tip'] . ')' : '' ) ), $l ) );
			?>
			<section class="fr-card">
				<h2 class="fr-titlu-sectiune">Ultimul meci</h2>
				<p class="fr-card__mic"><?php echo esc_html( ( is_numeric( $m['runda'] ) ? 'Etapa ' . $m['runda'] . ' · ' : '' ) . fr_data_meci( $m['data'], 'zi' ) ); ?></p>
				<div class="fr-duel fr-duel--deschis">
					<div><b><?php echo esc_html( fr_nume_echipa( $m['gazde'] ) ); ?></b><?php if ( $marc ) : ?><p class="fr-marcatori"><?php echo $fmt( $marc['gazde'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></p><?php endif; ?></div>
					<span class="fr-duel__ora"><?php echo (int) $m['scor'][0] . '-' . (int) $m['scor'][1]; ?></span>
					<div class="fr-dreapta"><b><?php echo esc_html( fr_nume_echipa( $m['oaspeti'] ) ); ?></b><?php if ( $marc ) : ?><p class="fr-marcatori"><?php echo $fmt( $marc['oaspeti'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></p><?php endif; ?></div>
				</div>
			</section>
		<?php endif; ?>

		<?php
		$fr_golg = ( $fr_comp && $fr_terminate && get_term_meta( $fr_comp, 'fr_livescore', true ) ) ? fr_golgheteri_echipa( $fr_comp, $fr_nume ) : array();
		if ( $fr_golg ) :
			$fr_max = max( $fr_golg );
			?>
			<section class="fr-card">
				<h2 class="fr-titlu-sectiune">Golgheterii echipei</h2>
				<ul class="fr-bare">
					<?php foreach ( $fr_golg as $nume => $goluri ) : ?>
						<li>
							<span><?php echo esc_html( $nume ); ?></span>
							<span class="fr-bara" aria-hidden="true"><span style="width: <?php echo (int) round( $goluri / $fr_max * 100 ); ?>%"></span></span>
							<b><?php echo (int) $goluri; ?></b>
						</li>
					<?php endforeach; ?>
				</ul>
				<p class="fr-nota">Goluri în <?php echo esc_html( $fr_compt && ! is_wp_error( $fr_compt ) ? $fr_compt->name : 'campionat' ); ?>, sezonul curent, fără autogoluri.</p>
			</section>
		<?php endif; ?>

		<?php if ( count( $fr_urmatoare ) > 1 ) : ?>
			<section class="fr-card">
				<?php echo fr_titlu_sectiune( 'Programul', add_query_arg( 'fila', 'meciuri', get_term_link( $fr_club ) ), 'Toate meciurile' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<ul class="fr-program">
					<?php foreach ( array_slice( $fr_urmatoare, 1, 4 ) as $m ) : ?>
						<li><span><?php echo esc_html( fr_data_meci( $m['data'] ) ); ?></span><span><?php echo esc_html( fr_nume_echipa( $m['gazde'] ) . ' – ' . fr_nume_echipa( $m['oaspeti'] ) ); ?></span></li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>

		<?php if ( $fr_rand ) : ?>
			<section class="fr-card">
				<?php echo fr_titlu_sectiune( 'Clasament', add_query_arg( 'fila', 'clasament', get_term_link( $fr_compt ) ), 'Complet' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php echo fr_tabel_clasament( $fr_date, array( 'evidentiaza' => $fr_nume, 'in_jur' => 2 ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</section>
		<?php endif; ?>

		<?php
		$fr_colab = (int) get_term_meta( $fr_club->term_id, 'fr_colaborator', true );
		$fr_op    = $fr_colab ? get_posts( array( 'numberposts' => 1, 'author' => $fr_colab, 'tax_query' => array( array( 'taxonomy' => 'club', 'terms' => $fr_club->term_id ) ) ) ) : array();
		if ( $fr_colab ) :
			?>
			<section class="fr-card">
				<span class="fr-eticheta fr-eticheta--opinie">Vocea din tribună</span>
				<h2 class="fr-titlu-card"><?php echo esc_html( get_the_author_meta( 'display_name', $fr_colab ) ); ?> scrie despre <?php echo esc_html( $fr_club->name ); ?></h2>
				<?php if ( $fr_op ) : ?>
					<p><a href="<?php echo esc_url( get_permalink( $fr_op[0] ) ); ?>"><?php echo esc_html( get_the_title( $fr_op[0] ) ); ?></a></p>
				<?php else : ?>
					<p><?php echo esc_html( get_the_author_meta( 'description', $fr_colab ) ); ?></p>
				<?php endif; ?>
				<a class="fr-link-sectiune" href="<?php echo esc_url( get_author_posts_url( $fr_colab ) ); ?>">Toate textele</a>
			</section>
		<?php endif; ?>

		<?php
		$fr_ultime = get_posts( array( 'numberposts' => 4, 'tax_query' => array( array( 'taxonomy' => 'club', 'terms' => $fr_club->term_id ) ) ) );
		if ( $fr_ultime ) :
			?>
			<section class="fr-card fr-panouri__lat">
				<?php echo fr_titlu_sectiune( 'Ultimele articole', add_query_arg( 'fila', 'stiri', get_term_link( $fr_club ) ), 'Toate' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<div class="fr-lista-articole">
					<?php foreach ( $fr_ultime as $p ) : ?>
						<?php echo fr_card_articol( $p, 'lista' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<?php echo fr_sponsor( $fr_club ); // phpcs:ignore WordPress.Security.EscapeOutput ?>

		<?php if ( ! $fr_meciuri && ! $fr_ultime ) : ?>
			<div class="fr-card"><p class="fr-gol">Pagina se completează pe măsură ce apar meciuri și articole despre <?php echo esc_html( $fr_club->name ); ?>.</p></div>
		<?php endif; ?>
	</div>
<?php endif; ?>
</main>

<?php
get_footer();
