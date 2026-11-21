<?php
/**
 * Prima pagină, hibridă: meciurile săptămânii, deschiderea (articolul principal + ultimele știri),
 * rezultatele etapei, câte o secțiune pe competiție (articole + clasament scurt), Liga 3 serie cu serie, Opinie.
 * Secțiunile fără conținut nu apar.
 *
 * @package fotbalromanesc
 */

defined( 'ABSPATH' ) || exit;

get_header();

$fr_comp_prima = fr_competitii_cu_date( true );
$fr_meciuri    = fr_meciuri_interval( $fr_comp_prima, time() - DAY_IN_SECONDS, time() + 7 * DAY_IN_SECONDS );
// Întâi meciurile în desfășurare și cele care urmează, apoi rezultatele recente.
usort( $fr_meciuri, function ( $a, $b ) {
	$ra = array( 'live' => 0, 'programat' => 1, 'asteptare' => 2, 'terminat' => 2, 'amanat' => 3 )[ $a['stare'] ] ?? 3;
	$rb = array( 'live' => 0, 'programat' => 1, 'asteptare' => 2, 'terminat' => 2, 'amanat' => 3 )[ $b['stare'] ] ?? 3;
	return $ra <=> $rb ?: ( 2 === $ra ? strcmp( $b['data'], $a['data'] ) : strcmp( $a['data'], $b['data'] ) );
} );
$fr_meciuri = array_slice( $fr_meciuri, 0, 16 );

/**
 * Cel mai nou articol dintr-una din competițiile date (cu seriile și echipele lor), cu filtre opționale.
 */
$fr_cel_mai_nou = function ( $sluguri, $exclus = array(), $extra = array() ) {
	$ids = array();
	foreach ( (array) $sluguri as $s ) {
		$t = get_term_by( 'slug', $s, 'competitie' );
		if ( $t ) {
			$ids[] = $t->term_id;
		}
	}
	if ( ! $ids ) {
		return null;
	}
	$q = get_posts( array_merge( array(
		'numberposts'         => 1,
		'post__not_in'        => $exclus,
		'ignore_sticky_posts' => true,
		'tax_query'           => array( array( 'taxonomy' => 'competitie', 'terms' => $ids, 'include_children' => true ) ),
	), $extra ) );
	return $q ? $q[0] : null;
};

// Știrea zilei: articolul fixat; altfel cel mai important subiect din Naționale sau SuperLiga din ultimele
// 36 de ore (câmpul „importanta” din articol, apoi cel mai nou); altfel cel mai nou din ele (7 zile); altfel cel mai nou.
$fr_lipite = get_option( 'sticky_posts' );
$fr_lead   = null;
if ( $fr_lipite ) {
	$q       = get_posts( array( 'post__in' => $fr_lipite, 'numberposts' => 1, 'ignore_sticky_posts' => true ) );
	$fr_lead = $q ? $q[0] : null;
}
$fr_mari = array( 'nationala', 'superliga' );
if ( ! $fr_lead ) {
	$fr_lead = $fr_cel_mai_nou( $fr_mari, array(), array(
		'date_query' => array( array( 'after' => '36 hours ago' ) ),
		'meta_query' => array( 'relation' => 'OR', 'imp' => array( 'key' => '_fr_importanta', 'type' => 'NUMERIC' ), array( 'key' => '_fr_importanta', 'compare' => 'NOT EXISTS' ) ),
		'orderby'    => array( 'imp' => 'DESC', 'date' => 'DESC' ),
	) ) ?: $fr_cel_mai_nou( $fr_mari, array(), array( 'date_query' => array( array( 'after' => '7 days ago' ) ) ) );
}
if ( ! $fr_lead ) {
	$q       = get_posts( array( 'numberposts' => 1, 'ignore_sticky_posts' => true ) );
	$fr_lead = $q ? $q[0] : null;
}
$fr_exclus = $fr_lead ? array( $fr_lead->ID ) : array();

// Sub ea, două știri secundare: cea mai nouă din Liga 2 și cea mai nouă din Liga 3.
$fr_secundare = array();
foreach ( array( 'liga-2', 'liga-3' ) as $s ) {
	$p = $fr_cel_mai_nou( $s, $fr_exclus );
	if ( $p ) {
		$fr_secundare[] = $p;
		$fr_exclus[]    = $p->ID;
	}
}

$fr_ultimele = get_posts( array( 'numberposts' => 5, 'post__not_in' => $fr_exclus, 'ignore_sticky_posts' => true ) );

/**
 * Ultimele articole dintr-o competiție (cu seriile sau echipele ei).
 */
$fr_din = function ( $slug, $n ) use ( $fr_exclus ) {
	$t = get_term_by( 'slug', $slug, 'competitie' );
	if ( ! $t ) {
		return array( null, array() );
	}
	return array( $t, get_posts( array(
		'numberposts'  => $n,
		'post__not_in' => $fr_exclus,
		'tax_query'    => array( array( 'taxonomy' => 'competitie', 'terms' => $t->term_id, 'include_children' => true ) ),
	) ) );
};

/**
 * Rezultatele ultimei etape jucate dintr-o competiție cu date (cel mult $n meciuri, cele mai noi primele).
 */
$fr_rezultate = function ( $t, $n = 8 ) {
	$d = fr_date_competitie( $t->term_id );
	$terminate = array_values( array_filter( $d['meciuri'] ?? array(), fn( $m ) => 'terminat' === $m['stare'] && $m['scor'] ) );
	if ( ! $terminate ) {
		return array();
	}
	// Etapa = meciurile din ultimele 4 zile dinaintea ultimului meci jucat (etichetele etapelor nu sunt mereu sigure).
	$ultim = strtotime( end( $terminate )['data'] );
	$ale   = array_filter( $terminate, fn( $m ) => strtotime( $m['data'] ) >= $ultim - 4 * DAY_IN_SECONDS );
	return array_slice( array_reverse( array_values( $ale ) ), 0, $n );
};
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

<main id="continut" class="fr-container fr-prima">

	<?php if ( ! $fr_lead ) : ?>
		<div class="fr-card fr-gol-mare">
			<h1 class="fr-titlu-mare">Bine ai venit pe <?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>
			<p>Încă nu există articole. Primul articol publicat apare aici.</p>
		</div>
	<?php else : ?>

	<!-- Deschiderea -->
	<section class="fr-deschidere" aria-label="Deschiderea">
		<div class="fr-deschidere__lead">
			<?php echo fr_card_articol( $fr_lead, 'mare' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php if ( $fr_secundare ) : ?>
				<div class="fr-secundare">
					<?php foreach ( $fr_secundare as $p ) : ?>
						<?php echo fr_card_articol( $p, 'card' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php if ( $fr_ultimele ) : ?>
			<aside class="fr-card fr-ultimele" aria-labelledby="fr-ultimele-titlu">
				<h2 class="fr-titlu-sectiune" id="fr-ultimele-titlu">Ultimele știri</h2>
				<ol>
					<?php foreach ( $fr_ultimele as $p ) : ?>
						<li>
							<?php echo fr_eticheta_articol( $p->ID ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<a class="fr-ultimele__titlu" href="<?php echo esc_url( get_permalink( $p ) ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a>
							<time datetime="<?php echo esc_attr( get_the_date( 'c', $p ) ); ?>"><?php echo esc_html( human_time_diff( get_post_time( 'U', true, $p ) ) . ' în urmă' ); ?></time>
						</li>
					<?php endforeach; ?>
				</ol>
			</aside>
		<?php endif; ?>
	</section>
	<?php endif; ?>

	<!-- Rezultatele etapei -->
	<?php
	$fr_blocuri = array();
	foreach ( $fr_comp_prima as $t ) {
		$r = $fr_rezultate( $t );
		if ( $r ) {
			$fr_blocuri[] = array( $t, $r );
		}
	}
	if ( $fr_blocuri ) :
		?>
		<section class="fr-sectiune" aria-labelledby="fr-rez-titlu">
			<div class="fr-cap-sectiune">
				<h2 class="fr-titlu-sectiune" id="fr-rez-titlu">Rezultatele etapei</h2>
				<a class="fr-link-sectiune" href="<?php echo esc_url( fr_url_program() ); ?>">Toate rezultatele</a>
			</div>
			<div class="fr-rezultate">
				<?php foreach ( $fr_blocuri as list( $t, $rez ) ) : ?>
					<div class="fr-card fr-rezultate__bloc">
						<h3 class="fr-rezultate__comp"><a href="<?php echo esc_url( fr_url_fila( 'meciuri', $t ) ); ?>"><?php echo esc_html( fr_eticheta_competitie( $t ) ); ?></a><?php $fr_et = array_values( array_filter( wp_list_pluck( $rez, 'runda' ), 'is_numeric' ) ); ?><span><?php echo esc_html( $fr_et ? 'etapa ' . max( $fr_et ) : $rez[0]['runda'] ); ?></span></h3>
						<ul>
							<?php foreach ( $rez as $m ) : ?>
								<li>
									<span class="fr-rezultat__g"><?php echo esc_html( fr_nume_echipa( $m['gazde'] ) ); ?></span>
									<b class="fr-rezultat__s"><?php echo (int) $m['scor'][0] . '-' . (int) $m['scor'][1]; ?></b>
									<span class="fr-rezultat__o"><?php echo esc_html( fr_nume_echipa( $m['oaspeti'] ) ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<!-- Secțiunile pe competiții: articole + clasament scurt -->
	<?php
	foreach ( array( 'superliga', 'liga-2', 'nationala', 'stranierii' ) as $fr_slug ) :
		list( $fr_t, $fr_art ) = $fr_din( $fr_slug, 3 );
		if ( ! $fr_t || ! $fr_art ) {
			continue;
		}
		$fr_d = fr_date_competitie( $fr_t->term_id );
		?>
		<section class="fr-sectiune fr-sectiune-comp" aria-label="<?php echo esc_attr( $fr_t->name ); ?>">
			<?php echo fr_titlu_sectiune( $fr_t->name, get_term_link( $fr_t ), 'Tot din ' . $fr_t->name ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<div class="fr-sectiune-comp__rand<?php echo ( $fr_d && $fr_d['clasament'] ) ? '' : ' fr-sectiune-comp__rand--plin'; ?>">
				<div class="fr-grila fr-grila--3">
					<?php foreach ( $fr_art as $p ) : ?>
						<?php echo fr_card_articol( $p, 'card' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php endforeach; ?>
				</div>
				<?php if ( $fr_d && $fr_d['clasament'] ) : ?>
					<aside class="fr-card fr-clasament-scurt">
						<?php echo fr_tabel_clasament( $fr_d, array( 'max' => 6 ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<a class="fr-link-sectiune" href="<?php echo esc_url( fr_url_fila( 'clasament', $fr_t ) ); ?>">Clasamentul complet</a>
					</aside>
				<?php endif; ?>
			</div>
		</section>
	<?php endforeach; ?>

	<!-- Liga 3, serie cu serie -->
	<?php
	$fr_l3 = get_term_by( 'slug', 'liga-3', 'competitie' );
	$fr_serii = $fr_l3 ? get_terms( array( 'taxonomy' => 'competitie', 'parent' => $fr_l3->term_id, 'hide_empty' => true, 'meta_key' => 'fr_ordine', 'orderby' => 'meta_value_num' ) ) : array();
	$fr_carduri = array();
	foreach ( is_wp_error( $fr_serii ) ? array() : $fr_serii as $s ) {
		$p = get_posts( array( 'numberposts' => 1, 'tax_query' => array( array( 'taxonomy' => 'competitie', 'terms' => $s->term_id ) ) ) );
		if ( $p ) {
			$fr_carduri[] = array( $s, $p[0] );
		}
	}
	if ( $fr_carduri ) :
		?>
		<section class="fr-sectiune" aria-label="Liga 3">
			<?php echo fr_titlu_sectiune( 'Liga 3, serie cu serie', get_term_link( $fr_l3 ), 'Tot din Liga 3' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<div class="fr-serii">
				<?php foreach ( $fr_carduri as list( $s, $p ) ) : ?>
					<article class="fr-card fr-serie">
						<a class="fr-serie__nume" href="<?php echo esc_url( get_term_link( $s ) ); ?>"><?php echo esc_html( $s->name ); ?></a>
						<h3 class="fr-serie__titlu"><a href="<?php echo esc_url( get_permalink( $p ) ); ?>"><?php echo esc_html( preg_replace( '/^Liga 3, Seria \d+, runda \d+:\s*/u', '', get_the_title( $p ) ) ); ?></a></h3>
						<p class="fr-articol__meta"><?php echo esc_html( get_the_date( 'j M', $p ) ); ?> · <a href="<?php echo esc_url( fr_url_fila( 'clasament', $s ) ); ?>">clasament</a></p>
					</article>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<!-- Opinie -->
	<?php
	list( $fr_op_t, $fr_op ) = $fr_din( 'opinie', 3 );
	if ( $fr_op ) :
		?>
		<section class="fr-sectiune" aria-label="Opinie">
			<?php echo fr_titlu_sectiune( 'Opinie', get_term_link( $fr_op_t ), 'Toate editorialele' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<div class="fr-grila fr-grila--3">
				<?php foreach ( $fr_op as $p ) : ?>
					<?php echo fr_card_articol( $p, 'card' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php echo fr_sponsor(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<?php if ( is_active_sidebar( 'bara-laterala' ) ) : ?>
		<div class="fr-grila fr-grila--3"><?php dynamic_sidebar( 'bara-laterala' ); ?></div>
	<?php endif; ?>
</main>

<?php
get_footer();
