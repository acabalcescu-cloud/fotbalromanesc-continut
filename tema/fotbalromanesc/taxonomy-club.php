<?php
/**
 * Pagina unui club: Prezentare (forma, ultimul rezultat, următorul meci, clasament, rezultate, program,
 * stadion, noutăți, antrenor, lot) / Meciuri / Știri.
 *
 * @package fotbalromanesc
 */

defined( 'ABSPATH' ) || exit;

get_header();

$fr_club  = get_queried_object();
$fr_comp  = (int) get_term_meta( $fr_club->term_id, 'fr_competitie', true );
$fr_compt = $fr_comp ? get_term( $fr_comp, 'competitie' ) : null;
$fr_compt = $fr_compt instanceof WP_Term ? $fr_compt : null;
$fr_date  = $fr_comp ? fr_date_competitie( $fr_comp ) : null;
$fr_nume  = get_term_meta( $fr_club->term_id, 'fr_livescore_nume', true );
if ( ! $fr_nume ) {
	$fr_nume = array_search( $fr_club->name, fr_nume_implicite(), true ) ?: $fr_club->name;
}
$fr_meciuri = $fr_date ? fr_meciurile_echipei( $fr_date, $fr_nume ) : array();
$fr_bilant  = $fr_date ? fr_bilant( $fr_date, $fr_nume ) : null;
$fr_profil  = fr_profil_club( $fr_club );
$fr_file    = array( 'prezentare' => 'Prezentare' );
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

/**
 * Un rând de meci în listele de rezultate și program, cu butonul cronicii dacă avem articol.
 */
$fr_rand_meci = function ( $m ) use ( $fr_nume, $fr_rezultat ) {
	$art   = 'terminat' === $m['stare'] ? fr_articol_meci( $m ) : 0;
	$clasa = $m['scor'] && 'terminat' === $m['stare'] ? ' fr-rand-meci__scor--' . strtolower( strtr( $fr_rezultat( $m ), array( 'Î' => 'i' ) ) ) : '';
	ob_start();
	?>
	<li class="fr-rand-meci">
		<span class="fr-rand-meci__data"><?php echo esc_html( fr_data_meci( $m['data'], 'data' ) ); ?><small><?php echo esc_html( is_numeric( $m['runda'] ) ? 'etapa ' . $m['runda'] : $m['runda'] ); ?></small></span>
		<span class="fr-rand-meci__g<?php echo $m['gazde'] === $fr_nume ? ' fr-noi' : ''; ?>"><?php echo fr_echipa( $m['gazde'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
		<span class="fr-rand-meci__scor<?php echo esc_attr( $clasa ); ?>"><?php echo esc_html( $m['scor'] && 'programat' !== $m['stare'] ? $m['scor'][0] . '-' . $m['scor'][1] : ( 'asteptare' === $m['stare'] ? '–' : fr_data_meci( $m['data'], 'ora' ) ) ); ?></span>
		<span class="fr-rand-meci__o<?php echo $m['oaspeti'] === $fr_nume ? ' fr-noi' : ''; ?>"><?php echo fr_echipa( $m['oaspeti'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
		<span class="fr-rand-meci__link"><?php if ( $art ) : ?><a class="fr-buton fr-buton--mic" href="<?php echo esc_url( get_permalink( $art ) ); ?>">Cronica</a><?php endif; ?></span>
	</li>
	<?php
	return ob_get_clean();
};
?>

<section class="fr-club-erou">
	<div class="fr-container">
		<div class="fr-club-erou__rand">
			<?php if ( $fr_sigla ) : ?>
				<img class="fr-club-erou__stema" src="<?php echo esc_url( $fr_sigla ); ?>" alt="<?php echo esc_attr( 'Stema ' . $fr_club->name ); ?>" width="96" height="96">
			<?php else : ?>
				<span class="fr-club-erou__stema fr-sigla--gol" aria-hidden="true"><?php echo esc_html( mb_substr( $fr_club->name, 0, 1 ) ); ?></span>
			<?php endif; ?>
			<div>
				<h1 class="fr-club-erou__titlu"><?php echo esc_html( $fr_club->name ); ?><?php echo $fr_compt ? ' <span>în ' . esc_html( fr_eticheta_competitie( $fr_compt ) ) . '</span>' : ''; ?></h1>
				<p class="fr-club-erou__info">
					<?php
					$fr_info = array();
					if ( ! empty( $fr_profil['stadion']['nume'] ) ) {
						$fr_info[] = $fr_profil['stadion']['nume'] . ( ! empty( $fr_profil['oras'] ) ? ', ' . $fr_profil['oras'] : '' );
					}
					if ( $fr_bilant ) {
						$fr_info[] = 'locul ' . $fr_bilant['loc'] . ', ' . $fr_bilant['pct'] . ' de puncte';
					}
					$fr_info[] = 'sezonul ' . fr_sezon();
					echo esc_html( implode( ' · ', $fr_info ) );
					?>
				</p>
			</div>
			<a class="fr-buton fr-buton--galben" href="<?php echo esc_url( get_term_feed_link( $fr_club->term_id, 'club' ) ); ?>"><?php echo fr_icon( 'rss' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>Urmărește</a>
		</div>
		<?php fr_file( $fr_file, $fr_fila ); ?>
	</div>
</section>

<main id="continut" class="fr-container fr-club">
<?php if ( 'meciuri' === $fr_fila ) : ?>
	<section class="fr-card">
		<?php echo fr_titlu_sectiune( 'Program' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<?php if ( $fr_urmatoare ) : ?>
			<ul class="fr-randuri-meci"><?php foreach ( $fr_urmatoare as $m ) { echo $fr_rand_meci( $m ); } // phpcs:ignore WordPress.Security.EscapeOutput ?></ul>
		<?php else : ?><p class="fr-gol">Niciun meci programat.</p><?php endif; ?>
	</section>
	<section class="fr-card">
		<?php echo fr_titlu_sectiune( 'Rezultate' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<ul class="fr-randuri-meci"><?php foreach ( array_reverse( $fr_terminate ) as $m ) { echo $fr_rand_meci( $m ); } // phpcs:ignore WordPress.Security.EscapeOutput ?></ul>
	</section>

<?php elseif ( 'stiri' === $fr_fila ) : ?>
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

<?php else : ?>

	<?php if ( $fr_bilant ) : ?>
		<section aria-labelledby="fr-forma-t">
			<h2 class="fr-eticheta-mare" id="fr-forma-t">Forma echipei</h2>
			<div class="fr-cifre">
				<div class="fr-cifra"><b><?php echo (int) $fr_bilant['meciuri']; ?></b><span>Meciuri jucate</span></div>
				<?php if ( null !== $fr_bilant['v'] && null !== $fr_bilant['e'] && null !== $fr_bilant['i'] ) : ?>
					<div class="fr-cifra"><b><?php echo (int) $fr_bilant['v']; ?></b><span>Victorii</span></div>
					<div class="fr-cifra"><b><?php echo (int) $fr_bilant['e']; ?></b><span>Egaluri</span></div>
					<div class="fr-cifra"><b><?php echo (int) $fr_bilant['i']; ?></b><span>Înfrângeri</span></div>
				<?php else : ?>
					<div class="fr-cifra"><b><?php echo (int) $fr_bilant['loc']; ?></b><span>Locul</span></div>
					<div class="fr-cifra"><b><?php echo (int) $fr_bilant['pct']; ?></b><span>Puncte</span></div>
				<?php endif; ?>
				<?php if ( null !== $fr_bilant['gm'] && null !== $fr_bilant['gp'] ) : ?>
					<div class="fr-cifra"><b><?php echo (int) $fr_bilant['gm'] . '-' . (int) $fr_bilant['gp']; ?></b><span>Golaveraj</span></div>
				<?php endif; ?>
			</div>
			<?php if ( $fr_terminate ) : ?>
				<p class="fr-ultimele-meciuri">Ultimele meciuri: <?php echo fr_sir_forma( array_map( $fr_rezultat, array_slice( $fr_terminate, -5 ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<?php if ( $fr_terminate || $fr_urmatoare ) : ?>
		<div class="fr-doua">
			<?php if ( $fr_terminate ) : ?>
				<?php
				$m    = end( $fr_terminate );
				$marc = fr_marcatori_meci( $m );
				$art  = fr_articol_meci( $m );
				$fmt  = fn( $l ) => implode( '<br>', array_map( fn( $g ) => esc_html( $g['nume'] . ' ' . $g['minut'] . '′' . ( $g['tip'] ? ' (' . $g['tip'] . ')' : '' ) ), $l ) );
				?>
				<section aria-labelledby="fr-ultim-t">
					<h2 class="fr-eticheta-mare" id="fr-ultim-t">Ultimul rezultat</h2>
					<div class="fr-card fr-meci-mare">
						<p class="fr-meci-mare__sus"><?php echo esc_html( ( $fr_compt ? fr_eticheta_competitie( $fr_compt ) . ' · ' : '' ) . fr_data_meci( $m['data'], 'zi' ) ); ?></p>
						<div class="fr-meci-mare__rand">
							<div><b><?php echo fr_echipa( $m['gazde'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></b><?php if ( $marc ) : ?><p class="fr-marcatori"><?php echo $fmt( $marc['gazde'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></p><?php endif; ?></div>
							<span class="fr-meci-mare__scor"><?php echo (int) $m['scor'][0] . ':' . (int) $m['scor'][1]; ?></span>
							<div class="fr-dreapta"><b><?php echo fr_echipa( $m['oaspeti'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></b><?php if ( $marc ) : ?><p class="fr-marcatori"><?php echo $fmt( $marc['oaspeti'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></p><?php endif; ?></div>
						</div>
						<?php if ( $art ) : ?><p class="fr-centru"><a class="fr-buton fr-buton--mic" href="<?php echo esc_url( get_permalink( $art ) ); ?>">Cronica</a></p><?php endif; ?>
					</div>
				</section>
			<?php endif; ?>
			<?php if ( $fr_urmatoare ) : ?>
				<?php $m = $fr_urmatoare[0]; ?>
				<section aria-labelledby="fr-urm-t">
					<h2 class="fr-eticheta-mare" id="fr-urm-t"><?php echo 'live' === $m['stare'] ? 'Acum, în direct' : 'Următorul meci'; ?></h2>
					<div class="fr-card fr-meci-mare">
						<p class="fr-meci-mare__sus"><?php echo esc_html( ( $fr_compt ? fr_eticheta_competitie( $fr_compt ) . ' · ' : '' ) . fr_data_meci( $m['data'], 'zi' ) ); ?></p>
						<div class="fr-meci-mare__rand">
							<div><b><?php echo fr_echipa( $m['gazde'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></b></div>
							<span class="fr-meci-mare__scor fr-meci-mare__scor--ora"><?php echo esc_html( 'live' === $m['stare'] && $m['scor'] ? $m['scor'][0] . ':' . $m['scor'][1] : fr_data_meci( $m['data'], 'ora' ) ); ?></span>
							<div class="fr-dreapta"><b><?php echo fr_echipa( $m['oaspeti'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></b></div>
						</div>
					</div>
				</section>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( $fr_bilant ) : ?>
		<section aria-labelledby="fr-clas-t">
			<h2 class="fr-eticheta-mare" id="fr-clas-t">Clasament</h2>
			<div class="fr-card"><?php echo fr_tabel_clasament( $fr_date, array( 'evidentiaza' => $fr_nume, 'complet' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
		</section>
	<?php endif; ?>

	<?php if ( $fr_terminate ) : ?>
		<section aria-labelledby="fr-rez-t">
			<h2 class="fr-eticheta-mare" id="fr-rez-t">Rezultate</h2>
			<ul class="fr-randuri-meci fr-card"><?php foreach ( array_reverse( $fr_terminate ) as $m ) { echo $fr_rand_meci( $m ); } // phpcs:ignore WordPress.Security.EscapeOutput ?></ul>
		</section>
	<?php endif; ?>

	<?php if ( $fr_urmatoare ) : ?>
		<section aria-labelledby="fr-prog-t">
			<h2 class="fr-eticheta-mare" id="fr-prog-t">Program</h2>
			<ul class="fr-randuri-meci fr-card"><?php foreach ( array_slice( $fr_urmatoare, 0, 8 ) as $m ) { echo $fr_rand_meci( $m ); } // phpcs:ignore WordPress.Security.EscapeOutput ?></ul>
		</section>
	<?php endif; ?>

	<?php if ( ! empty( $fr_profil['stadion']['nume'] ) ) : ?>
		<?php $st = $fr_profil['stadion']; ?>
		<section aria-labelledby="fr-stad-t">
			<h2 class="fr-eticheta-mare" id="fr-stad-t">Stadion</h2>
			<div class="fr-card">
				<table class="fr-detalii">
					<tr><th scope="row">Nume</th><td><?php echo esc_html( $st['nume'] ); ?></td></tr>
					<?php if ( ! empty( $fr_profil['oras'] ) ) : ?><tr><th scope="row">Oraș</th><td><?php echo esc_html( $fr_profil['oras'] ); ?></td></tr><?php endif; ?>
					<?php if ( ! empty( $st['capacitate'] ) ) : ?><tr><th scope="row">Capacitate</th><td><?php echo esc_html( number_format_i18n( (int) $st['capacitate'] ) . ' de locuri' ); ?></td></tr><?php endif; ?>
					<?php if ( ! empty( $st['inaugurat'] ) ) : ?><tr><th scope="row">Inaugurat</th><td><?php echo (int) $st['inaugurat']; ?></td></tr><?php endif; ?>
					<?php if ( ! empty( $st['maps'] ) ) : ?><tr><th scope="row">Localizare</th><td><a href="<?php echo esc_url( $st['maps'] ); ?>" rel="noopener" target="_blank">Deschide în Google Maps</a></td></tr><?php endif; ?>
				</table>
			</div>
		</section>
	<?php endif; ?>

	<?php
	$fr_ultime = get_posts( array( 'numberposts' => 6, 'tax_query' => array( array( 'taxonomy' => 'club', 'terms' => $fr_club->term_id ) ) ) );
	if ( $fr_ultime ) :
		?>
		<section aria-labelledby="fr-nou-t">
			<div class="fr-cap-sectiune"><h2 class="fr-eticheta-mare" id="fr-nou-t">Noutăți despre echipă</h2><a class="fr-link-sectiune" href="<?php echo esc_url( fr_url_fila( 'stiri' ) ); ?>">Mai multe</a></div>
			<div class="fr-grila fr-grila--3">
				<?php foreach ( $fr_ultime as $p ) : ?>
					<?php echo fr_card_articol( $p, 'card' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php
	$fr_golg = ( $fr_comp && $fr_terminate && get_term_meta( $fr_comp, 'fr_livescore', true ) ) ? fr_golgheteri_echipa( $fr_comp, $fr_nume ) : array();
	if ( $fr_golg ) :
		$fr_max = max( $fr_golg );
		?>
		<section aria-labelledby="fr-golg-t">
			<h2 class="fr-eticheta-mare" id="fr-golg-t">Golgheterii echipei</h2>
			<div class="fr-card">
				<ul class="fr-bare">
					<?php foreach ( $fr_golg as $nume => $goluri ) : ?>
						<li><span><?php echo esc_html( $nume ); ?></span><span class="fr-bara" aria-hidden="true"><span style="width: <?php echo (int) round( $goluri / $fr_max * 100 ); ?>%"></span></span><b><?php echo (int) $goluri; ?></b></li>
					<?php endforeach; ?>
				</ul>
				<p class="fr-nota">Goluri în <?php echo esc_html( $fr_compt ? $fr_compt->name : 'campionat' ); ?>, sezonul curent, fără autogoluri.</p>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( ! empty( $fr_profil['antrenor']['nume'] ) ) : ?>
		<?php $an = $fr_profil['antrenor']; ?>
		<section aria-labelledby="fr-antr-t">
			<h2 class="fr-eticheta-mare" id="fr-antr-t">Antrenor</h2>
			<div class="fr-card">
				<table class="fr-lot"><thead><tr><th scope="col">Nume</th><th scope="col">Vârstă</th><th scope="col">Naționalitate</th></tr></thead>
				<tbody><tr><td><b><?php echo esc_html( $an['nume'] ); ?></b></td><td><?php echo ! empty( $an['varsta'] ) ? (int) $an['varsta'] : '—'; ?></td><td><?php echo esc_html( $an['nationalitate'] ?? '—' ); ?></td></tr></tbody></table>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( ! empty( $fr_profil['lot'] ) && is_array( $fr_profil['lot'] ) ) : ?>
		<section aria-labelledby="fr-lot-t">
			<h2 class="fr-eticheta-mare" id="fr-lot-t">Lot</h2>
			<div class="fr-card">
				<?php foreach ( array( 'portar' => 'Portari', 'fundas' => 'Fundași', 'mijlocas' => 'Mijlocași', 'atacant' => 'Atacanți' ) as $fr_post => $fr_titlu ) : ?>
					<?php $fr_j = array_values( array_filter( $fr_profil['lot'], fn( $j ) => ( $j['post'] ?? '' ) === $fr_post ) ); ?>
					<?php if ( $fr_j ) : ?>
						<h3 class="fr-lot__post"><?php echo esc_html( $fr_titlu ); ?></h3>
						<table class="fr-lot"><thead><tr><th scope="col">Nr.</th><th scope="col">Jucător</th><th scope="col">Vârstă</th><th scope="col">Naționalitate</th></tr></thead><tbody>
						<?php foreach ( $fr_j as $j ) : ?>
							<tr><td class="fr-lot__nr"><?php echo isset( $j['nr'] ) ? (int) $j['nr'] : ''; ?></td><td><b><?php echo esc_html( $j['nume'] ?? '' ); ?></b></td><td><?php echo ! empty( $j['varsta'] ) ? (int) $j['varsta'] : '—'; ?></td><td><?php echo esc_html( $j['nationalitate'] ?? '—' ); ?></td></tr>
						<?php endforeach; ?>
						</tbody></table>
					<?php endif; ?>
				<?php endforeach; ?>
				<?php if ( ! empty( $fr_profil['actualizat'] ) ) : ?><p class="fr-nota">Lot actualizat la <?php echo esc_html( $fr_profil['actualizat'] ); ?>.</p><?php endif; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php
	$fr_colab = (int) get_term_meta( $fr_club->term_id, 'fr_colaborator', true );
	if ( $fr_colab ) :
		?>
		<section class="fr-card">
			<span class="fr-eticheta fr-eticheta--opinie">Vocea din tribună</span>
			<h2 class="fr-titlu-card"><?php echo esc_html( get_the_author_meta( 'display_name', $fr_colab ) ); ?> scrie despre <?php echo esc_html( $fr_club->name ); ?></h2>
			<a class="fr-link-sectiune" href="<?php echo esc_url( get_author_posts_url( $fr_colab ) ); ?>">Toate textele</a>
		</section>
	<?php endif; ?>

	<?php echo fr_sponsor( $fr_club ); // phpcs:ignore WordPress.Security.EscapeOutput ?>

	<section class="fr-club-text">
		<h2><?php echo esc_html( 'Meciurile ' . $fr_club->name . ( $fr_compt ? ' în ' . fr_eticheta_competitie( $fr_compt ) : '' ) ); ?></h2>
		<p><?php echo esc_html( fr_text_club( $fr_club, $fr_compt, $fr_bilant, $fr_profil ) ); ?></p>
		<?php if ( $fr_club->description ) : ?><?php echo wp_kses_post( wpautop( $fr_club->description ) ); ?><?php endif; ?>
	</section>
<?php endif; ?>
</main>

<?php
get_footer();
