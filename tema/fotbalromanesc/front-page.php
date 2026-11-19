<?php
/**
 * Prima pagină: meciurile săptămânii, articolul principal, ultimele articole, clasamentele.
 *
 * @package fotbalromanesc
 */

defined( 'ABSPATH' ) || exit;

get_header();

$fr_comp_prima = fr_competitii_cu_date( true );
$fr_meciuri    = fr_meciuri_interval( $fr_comp_prima, time() - DAY_IN_SECONDS, time() + 7 * DAY_IN_SECONDS );
// Întâi meciurile în desfășurare și cele care urmează, apoi rezultatele recente.
usort( $fr_meciuri, function ( $a, $b ) {
	$ra = array( 'live' => 0, 'programat' => 1, 'terminat' => 2, 'amanat' => 3 )[ $a['stare'] ] ?? 3;
	$rb = array( 'live' => 0, 'programat' => 1, 'terminat' => 2, 'amanat' => 3 )[ $b['stare'] ] ?? 3;
	return $ra <=> $rb ?: ( 2 === $ra ? strcmp( $b['data'], $a['data'] ) : strcmp( $a['data'], $b['data'] ) );
} );
$fr_meciuri = array_slice( $fr_meciuri, 0, 16 );

$fr_lipite = get_option( 'sticky_posts' );
$fr_lead   = null;
if ( $fr_lipite ) {
	$q = get_posts( array( 'post__in' => $fr_lipite, 'numberposts' => 1, 'ignore_sticky_posts' => true ) );
	$fr_lead = $q ? $q[0] : null;
}
$fr_articole = get_posts( array(
	'numberposts'         => $fr_lead ? 12 : 13,
	'post__not_in'        => $fr_lead ? array( $fr_lead->ID ) : array(),
	'ignore_sticky_posts' => true,
) );
if ( ! $fr_lead && $fr_articole ) {
	$fr_lead = array_shift( $fr_articole );
}
$fr_opinie = get_term_by( 'slug', 'opinie', 'competitie' );
?>

<?php if ( $fr_meciuri ) : ?>
<section class="fr-banda" id="meciuri" aria-labelledby="fr-banda-titlu">
	<div class="fr-container">
		<div class="fr-cap-sectiune">
			<h2 class="fr-titlu-sectiune" id="fr-banda-titlu">Meciurile săptămânii</h2>
			<a class="fr-link-sectiune" href="<?php echo esc_url( fr_url_program() ); ?>">Program și rezultate</a>
		</div>
		<div class="fr-banda__lista" tabindex="0" aria-label="Meciuri, derulează orizontal">
			<?php foreach ( $fr_meciuri as $m ) : ?>
				<?php echo fr_card_meci( $m, true ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<main id="continut" class="fr-container fr-layout">
	<div class="fr-layout__principal">
		<?php if ( $fr_lead ) : ?>
			<?php echo fr_card_articol( $fr_lead, 'mare' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<?php else : ?>
			<div class="fr-card fr-gol-mare">
				<h1 class="fr-titlu-mare">Bine ai venit pe <?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>
				<p>Încă nu există articole. Primul articol publicat apare aici.</p>
			</div>
		<?php endif; ?>

		<?php if ( $fr_articole ) : ?>
			<div class="fr-grila">
				<?php foreach ( array_slice( $fr_articole, 0, 6 ) as $p ) : ?>
					<?php echo fr_card_articol( $p, 'card' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php
		if ( $fr_opinie ) :
			$fr_aratate = array_merge( $fr_lead ? array( $fr_lead->ID ) : array(), wp_list_pluck( array_slice( $fr_articole, 0, 6 ), 'ID' ) );
			$fr_op      = get_posts( array( 'numberposts' => 3, 'post__not_in' => $fr_aratate, 'tax_query' => array( array( 'taxonomy' => 'competitie', 'terms' => $fr_opinie->term_id, 'include_children' => true ) ) ) );
			if ( $fr_op ) :
				?>
				<section class="fr-sectiune" aria-label="Opinie">
					<?php echo fr_titlu_sectiune( 'Opinie', get_term_link( $fr_opinie ), 'Toate editorialele' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<div class="fr-grila fr-grila--3">
						<?php foreach ( $fr_op as $p ) : ?>
							<?php echo fr_card_articol( $p, 'lista' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<?php endforeach; ?>
					</div>
				</section>
				<?php
			endif;
		endif;
		?>

		<?php if ( count( $fr_articole ) > 6 ) : ?>
			<section class="fr-sectiune" aria-label="Alte articole">
				<?php echo fr_titlu_sectiune( 'Alte articole' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<div class="fr-lista-articole">
					<?php foreach ( array_slice( $fr_articole, 6 ) as $p ) : ?>
						<?php echo fr_card_articol( $p, 'lista' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>
	</div>

	<aside class="fr-layout__lateral" aria-label="Clasamente">
		<?php get_template_part( 'template-parts/clasamente-tab' ); ?>
		<?php echo fr_sponsor(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<?php if ( is_active_sidebar( 'bara-laterala' ) ) : ?>
			<?php dynamic_sidebar( 'bara-laterala' ); ?>
		<?php endif; ?>
	</aside>
</main>

<?php
get_footer();
