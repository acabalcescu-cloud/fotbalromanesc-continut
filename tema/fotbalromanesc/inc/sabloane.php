<?php
/**
 * Bucăți de afișare folosite în șabloane.
 *
 * @package fotbalromanesc
 */

defined( 'ABSPATH' ) || exit;

function fr_chip_forma( $l ) {
	$clasa = array( 'V' => 'v', 'E' => 'e', 'Î' => 'i' )[ $l ] ?? 'e';
	$titlu = array( 'V' => 'victorie', 'E' => 'egal', 'Î' => 'înfrângere' )[ $l ] ?? '';
	return '<span class="fr-forma fr-forma--' . $clasa . '" title="' . esc_attr( $titlu ) . '">' . esc_html( $l ) . '</span>';
}

function fr_sir_forma( $litere ) {
	if ( ! $litere ) {
		return '';
	}
	$text = implode( ', ', array_map( fn( $l ) => array( 'V' => 'victorie', 'E' => 'egal', 'Î' => 'înfrângere' )[ $l ] ?? $l, $litere ) );
	return '<span class="fr-forme" role="img" aria-label="' . esc_attr( 'Ultimele meciuri: ' . $text ) . '">' . implode( '', array_map( 'fr_chip_forma', $litere ) ) . '</span>';
}

/**
 * Numele echipei, cu link spre pagina clubului dacă există.
 */
function fr_echipa( $nume_date, $link = true ) {
	$club = fr_club_dupa_nume_date( $nume_date );
	$nume = fr_nume_echipa( $nume_date );
	if ( $link && $club ) {
		return '<a href="' . esc_url( get_term_link( $club ) ) . '">' . esc_html( $nume ) . '</a>';
	}
	return esc_html( $nume );
}

function fr_gd( $r ) {
	if ( null === $r['gm'] || null === $r['gp'] ) {
		return '—';
	}
	$gd = $r['gm'] - $r['gp'];
	return $gd > 0 ? '+' . $gd : (string) $gd;
}

/**
 * Tabelul clasamentului. $opt: max (rânduri), evidentiaza (nume din date), complet (toate coloanele).
 */
function fr_tabel_clasament( $date, $opt = array() ) {
	$opt   = wp_parse_args( $opt, array( 'max' => 0, 'evidentiaza' => '', 'complet' => false, 'in_jur' => 0 ) );
	$randuri = $date['clasament'] ?? array();
	if ( ! $randuri ) {
		return '<p class="fr-gol">Clasamentul nu e disponibil încă.</p>';
	}
	if ( $opt['in_jur'] && $opt['evidentiaza'] ) {
		$poz = array_search( $opt['evidentiaza'], array_column( $randuri, 'echipa' ), true );
		if ( false !== $poz ) {
			$start   = max( 0, min( $poz - $opt['in_jur'], count( $randuri ) - ( 2 * $opt['in_jur'] + 1 ) ) );
			$randuri = array_slice( $randuri, $start, 2 * $opt['in_jur'] + 1 );
		}
	} elseif ( $opt['max'] ) {
		$randuri = array_slice( $randuri, 0, $opt['max'] );
	}
	$forma = fr_forma( $date );
	// Coloanele pe care sursa nu le are deloc (de exemplu V/E/Î în Liga 3) nu se afișează.
	$are_vei = (bool) array_filter( $randuri, fn( $r ) => null !== $r['v'] );
	$are_gol = (bool) array_filter( $randuri, fn( $r ) => null !== $r['gm'] );
	ob_start();
	?>
	<div class="fr-tabel-cutie">
	<table class="fr-clasament<?php echo $opt['complet'] ? ' fr-clasament--complet' : ''; ?>">
		<thead><tr>
			<th scope="col" class="fr-nr">#</th>
			<th scope="col" class="fr-echipa-col">Echipa</th>
			<?php if ( $opt['complet'] ) : ?>
				<th scope="col"><abbr title="Meciuri jucate">M</abbr></th>
				<?php if ( $are_vei ) : ?>
					<th scope="col"><abbr title="Victorii">V</abbr></th>
					<th scope="col"><abbr title="Egaluri">E</abbr></th>
					<th scope="col"><abbr title="Înfrângeri">Î</abbr></th>
				<?php endif; ?>
				<?php if ( $are_gol ) : ?>
					<th scope="col">Goluri</th>
				<?php endif; ?>
			<?php endif; ?>
			<?php if ( $are_gol ) : ?>
				<th scope="col"><abbr title="Golaveraj">GD</abbr></th>
			<?php endif; ?>
			<th scope="col"><abbr title="Puncte">Pct</abbr></th>
			<th scope="col" class="fr-forma-col">Forma</th>
		</tr></thead>
		<tbody>
		<?php foreach ( $randuri as $r ) : ?>
			<tr<?php echo $r['echipa'] === $opt['evidentiaza'] ? ' class="fr-evidentiat"' : ''; ?>>
				<td class="fr-nr"><?php echo (int) $r['loc']; ?></td>
				<td class="fr-echipa-col"><?php echo fr_echipa( $r['echipa'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
				<?php if ( $opt['complet'] ) : ?>
					<td><?php echo (int) $r['meciuri']; ?></td>
					<?php if ( $are_vei ) : ?>
						<td><?php echo null === $r['v'] ? '—' : (int) $r['v']; ?></td>
						<td><?php echo null === $r['e'] ? '—' : (int) $r['e']; ?></td>
						<td><?php echo null === $r['i'] ? '—' : (int) $r['i']; ?></td>
					<?php endif; ?>
					<?php if ( $are_gol ) : ?>
						<td><?php echo null === $r['gm'] ? '—' : (int) $r['gm'] . '-' . (int) $r['gp']; ?></td>
					<?php endif; ?>
				<?php endif; ?>
				<?php if ( $are_gol ) : ?>
					<td><?php echo esc_html( fr_gd( $r ) ); ?></td>
				<?php endif; ?>
				<td class="fr-pct"><?php echo (int) $r['pct']; ?></td>
				<td class="fr-forma-col"><?php echo fr_sir_forma( $forma[ $r['echipa'] ] ?? array() ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	</div>
	<p class="fr-nota">
		V victorie · E egal · Î înfrângere
		<?php if ( ! empty( $date['neoficial'] ) ) : ?> · <strong>date neoficiale</strong><?php endif; ?>
		<?php if ( ! empty( $date['actualizat'] ) ) : ?> · actualizat la <?php echo esc_html( wp_date( 'H:i', $date['actualizat'] ) ); ?><?php endif; ?>
	</p>
	<?php
	return ob_get_clean();
}

/**
 * Cardul unui meci (banda de pe prima pagină, paginile de ligă și de club).
 */
function fr_card_meci( $m, $cu_competitie = false ) {
	$scor = $m['scor'] ? (int) $m['scor'][0] . '-' . (int) $m['scor'][1] : fr_data_meci( $m['data'], 'ora' );
	$clasa = 'fr-meci fr-meci--' . $m['stare'];
	ob_start();
	?>
	<div class="<?php echo esc_attr( $clasa ); ?>">
		<div class="fr-meci__sus">
			<span><?php echo esc_html( $cu_competitie && ! empty( $m['competitie'] ) ? fr_eticheta_competitie( $m['competitie'] ) : ( is_numeric( $m['runda'] ) ? 'Etapa ' . $m['runda'] : $m['runda'] ) ); ?></span>
			<span>
				<?php
				if ( 'live' === $m['stare'] ) {
					echo '<b class="fr-live">LIVE ' . esc_html( $m['minut'] ) . '</b>';
				} elseif ( 'amanat' === $m['stare'] ) {
					echo 'Amânat';
				} elseif ( 'programat' === $m['stare'] ) {
					echo esc_html( fr_data_meci( $m['data'], 'data' ) );
				} else {
					echo esc_html( fr_data_meci( $m['data'] ) );
				}
				?>
			</span>
		</div>
		<div class="fr-meci__echipe">
			<span class="fr-meci__echipa"><?php echo fr_echipa( $m['gazde'], false ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			<span class="fr-meci__scor"><?php echo esc_html( $scor ); ?></span>
			<span class="fr-meci__echipa fr-meci__echipa--oaspeti"><?php echo fr_echipa( $m['oaspeti'], false ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Lista meciurilor grupate pe zile.
 */
function fr_lista_meciuri( $meciuri, $cu_competitie = false ) {
	if ( ! $meciuri ) {
		return '<p class="fr-gol">Niciun meci în această perioadă.</p>';
	}
	$out = '';
	$zi  = '';
	foreach ( $meciuri as $m ) {
		$z = fr_data_meci( $m['data'], 'zi' );
		if ( $z !== $zi ) {
			$out .= ( $zi ? '</div>' : '' ) . '<h3 class="fr-zi">' . esc_html( $z ) . '</h3><div class="fr-meciuri-zi">';
			$zi   = $z;
		}
		$out .= fr_card_meci( $m, $cu_competitie );
	}
	return $out . '</div>';
}

/**
 * Eticheta colorată a competiției unui articol.
 */
function fr_eticheta_articol( $post_id = 0 ) {
	$t = fr_competitia_articolului( $post_id );
	if ( ! $t ) {
		return '';
	}
	$radacina = $t->parent ? get_term( $t->parent, 'competitie' ) : $t;
	$opinie   = $radacina && ! is_wp_error( $radacina ) && 'opinie' === $radacina->slug;
	return '<a class="fr-eticheta' . ( $opinie ? ' fr-eticheta--opinie' : '' ) . '" href="' . esc_url( get_term_link( $t ) ) . '">' . esc_html( fr_eticheta_competitie( $t ) ) . '</a>';
}

/**
 * Cardul unui articol. $varianta: mare | card | lista.
 */
function fr_card_articol( $post = null, $varianta = 'card' ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	$link  = get_permalink( $post );
	$titlu = get_the_title( $post );
	$are_img = has_post_thumbnail( $post );
	ob_start();
	?>
	<article class="fr-articol fr-articol--<?php echo esc_attr( $varianta ); ?><?php echo $are_img ? '' : ' fr-articol--fara-imagine'; ?>">
		<?php if ( 'mare' === $varianta ) : ?>
			<a class="fr-articol__coperta" href="<?php echo esc_url( $link ); ?>" tabindex="-1" aria-hidden="true">
				<?php echo $are_img ? get_the_post_thumbnail( $post, 'fr-mare', array( 'loading' => 'eager', 'fetchpriority' => 'high' ) ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</a>
			<div class="fr-articol__suprapus">
				<?php echo fr_eticheta_articol( $post->ID ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<h2 class="fr-articol__titlu"><a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $titlu ); ?></a></h2>
			</div>
			<div class="fr-articol__corp">
				<p class="fr-articol__rezumat"><?php echo esc_html( get_the_excerpt( $post ) ); ?></p>
				<p class="fr-articol__meta"><?php echo esc_html( fr_meta_articol( $post, 'j F Y' ) ); ?></p>
			</div>
		<?php else : ?>
			<?php if ( $are_img && 'card' === $varianta ) : ?>
				<a class="fr-articol__imagine" href="<?php echo esc_url( $link ); ?>" tabindex="-1" aria-hidden="true"><?php echo get_the_post_thumbnail( $post, 'fr-card', array( 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
			<?php endif; ?>
			<div class="fr-articol__corp">
				<?php echo fr_eticheta_articol( $post->ID ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<h3 class="fr-articol__titlu"><a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $titlu ); ?></a></h3>
				<?php if ( 'card' === $varianta ) : ?>
					<p class="fr-articol__rezumat"><?php echo esc_html( get_the_excerpt( $post ) ); ?></p>
				<?php endif; ?>
				<p class="fr-articol__meta"><?php echo apply_filters( 'fr_are_video', false, $post ) ? '<span class="fr-eticheta-video">▶ Video</span> ' : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo esc_html( fr_meta_articol( $post, 'j M Y' ) ); ?></p>
			</div>
		<?php endif; ?>
	</article>
	<?php
	return ob_get_clean();
}

function fr_meta_articol( $post, $format ) {
	$autor = $post->post_author ? get_the_author_meta( 'display_name', $post->post_author ) : '';
	return implode( ' · ', array_filter( array( $autor, get_the_date( $format, $post ) ) ) );
}

/**
 * Spațiul sponsorului: al clubului sau al ligii, altfel sponsorul general, altfel spațiul liber.
 */
function fr_sponsor( $term = null ) {
	$nume = $term ? get_term_meta( $term->term_id, 'fr_sponsor', true ) : '';
	$url  = $term ? get_term_meta( $term->term_id, 'fr_sponsor_url', true ) : '';
	if ( ! $nume ) {
		$nume = fr_setare( 'sponsor' );
		$url  = fr_setare( 'sponsor_url' );
	}
	if ( ! $nume ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}
		return '<aside class="fr-sponsor fr-sponsor--liber" aria-label="Sponsor"><b>Spațiu pentru sponsor</b>Vizibil doar pentru administratori până îl completezi (pe club, pe ligă sau în Aspect › fotbalromanesc).</aside>';
	}
	$text = $term ? sprintf( 'Pagina %s, susținută de', $term->name ) : 'Susținut de';
	$nume_html = $url ? '<a href="' . esc_url( $url ) . '" rel="sponsored noopener" target="_blank">' . esc_html( $nume ) . '</a>' : esc_html( $nume );
	return '<aside class="fr-sponsor" aria-label="Sponsor"><span>' . esc_html( $text ) . '</span><b>' . $nume_html . '</b></aside>';
}

function fr_titlu_sectiune( $text, $link = '', $text_link = '' ) {
	$out = '<div class="fr-cap-sectiune"><h2 class="fr-titlu-sectiune">' . esc_html( $text ) . '</h2>';
	if ( $link ) {
		$out .= '<a class="fr-link-sectiune" href="' . esc_url( $link ) . '">' . esc_html( $text_link ?: 'Toate' ) . '</a>';
	}
	return $out . '</div>';
}

/**
 * Pictograme (contur), fără fișiere externe.
 */
function fr_icon( $nume ) {
	$p = array(
		'cauta'   => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>',
		'acasa'   => '<path d="M3 11l9-7 9 7v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
		'meciuri' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
		'ligi'    => '<path d="M8 4h8v5a4 4 0 0 1-8 0z"/><path d="M8 6H5a3 3 0 0 0 3 3M16 6h3a3 3 0 0 1-3 3M12 13v4M8 21h8"/>',
		'meniu'   => '<path d="M4 7h16M4 12h16M4 17h16"/>',
		'inchide' => '<path d="M6 6l12 12M18 6L6 18"/>',
		'rss'     => '<path d="M5 11a8 8 0 0 1 8 8M5 5a14 14 0 0 1 14 14"/><circle cx="6" cy="18" r="1"/>',
	);
	return '<svg class="fr-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ( $p[ $nume ] ?? '' ) . '</svg>';
}

/**
 * Adresa unei file a paginii curente de ligă sau de club: /competitie/liga-2/clasament/, /club/x/meciuri/.
 */
function fr_url_fila( $fila, $term = null ) {
	$term = $term ?: get_queried_object();
	$baza = get_term_link( $term );
	if ( is_wp_error( $baza ) ) {
		return home_url( '/' );
	}
	return in_array( $fila, array( 'stiri', 'prezentare', '' ), true ) && 'club' !== $term->taxonomy
		? $baza
		: ( 'prezentare' === $fila ? $baza : trailingslashit( $baza ) . $fila . '/' );
}

/**
 * Filele unei pagini de ligă sau de club.
 */
function fr_file( $file, $activa ) {
	echo '<nav class="fr-file" aria-label="Secțiuni"><ul>';
	foreach ( $file as $cheie => $nume ) {
		printf(
			'<li><a href="%s"%s>%s</a></li>',
			esc_url( fr_url_fila( $cheie ) ),
			$cheie === $activa ? ' aria-current="page"' : '',
			esc_html( $nume )
		);
	}
	echo '</ul></nav>';
}

function fr_fila_curenta( $permise, $implicit ) {
	$f = get_query_var( 'fila' ) ?: ( isset( $_GET['fila'] ) ? sanitize_key( $_GET['fila'] ) : $implicit ); // phpcs:ignore WordPress.Security.NonceVerification
	return in_array( $f, $permise, true ) ? $f : $implicit;
}

/**
 * Open Graph și NewsArticle, doar când nu e activ un modul SEO (Yoast, Rank Math, SEOPress, AIOSEO).
 */
add_action( 'wp_head', 'fr_meta_sociale', 5 );
function fr_meta_sociale() {
	if ( fr_modul_seo_activ() ) {
		return;
	}
	$titlu = wp_get_document_title();
	$desc  = fr_descriere_pagina();
	$img   = '';
	$url   = home_url( add_query_arg( array() ) );
	if ( is_singular() ) {
		$img  = get_the_post_thumbnail_url( null, 'fr-mare' );
		if ( ! $img && is_singular( 'post' ) && function_exists( 'fr_coperta_url' ) ) {
			$img = fr_coperta_url( get_the_ID(), 'og' ); // coperta generată, pentru previzualizarea pe Facebook
		}
		$url  = get_permalink();
	} elseif ( is_tax( 'club' ) ) {
		$img = get_term_meta( get_queried_object_id(), 'fr_sigla', true );
	}
	if ( ! $img ) {
		$img = fr_logo_url();
	}
	printf( '<meta property="og:site_name" content="%s">' . "\n" . '<meta property="og:locale" content="ro_RO">' . "\n", esc_attr( get_bloginfo( 'name' ) ) );
	printf( '<meta property="og:title" content="%s">' . "\n", esc_attr( $titlu ) );
	printf( '<meta property="og:type" content="%s">' . "\n", is_singular( 'post' ) ? 'article' : 'website' );
	printf( '<meta property="og:url" content="%s">' . "\n", esc_url( $url ) );
	if ( $desc ) {
		printf( '<meta name="description" content="%1$s">' . "\n" . '<meta property="og:description" content="%1$s">' . "\n", esc_attr( wp_trim_words( $desc, 40 ) ) );
	}
	if ( $img ) {
		printf( '<meta property="og:image" content="%s">' . "\n" . '<meta name="twitter:card" content="summary_large_image">' . "\n", esc_url( $img ) );
	}
	if ( is_singular( 'post' ) ) {
		$schema = array(
			'@context'      => 'https://schema.org',
			'@type'         => 'NewsArticle',
			'headline'      => get_the_title(),
			'datePublished' => get_the_date( 'c' ),
			'dateModified'  => get_the_modified_date( 'c' ),
			'author'        => array( '@type' => 'Person', 'name' => get_the_author_meta( 'display_name', get_post_field( 'post_author' ) ) ),
			'publisher'     => array( '@type' => 'NewsMediaOrganization', 'name' => get_bloginfo( 'name' ), 'logo' => array( '@type' => 'ImageObject', 'url' => fr_logo_url() ) ),
			'inLanguage'    => 'ro-RO',
			'mainEntityOfPage' => get_permalink(),
		);
		if ( $img ) {
			$schema['image'] = array( $img );
		}
		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
	}
}

/**
 * Pictogramele rețelelor (forme simplificate, într-o singură culoare).
 */
function fr_icon_retea( $retea ) {
	$p = array(
		'facebook'  => '<path fill="currentColor" d="M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.09 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.69.23 2.69.23v2.97h-1.51c-1.49 0-1.96.93-1.96 1.89v2.26h3.33l-.53 3.49h-2.8V24C19.61 23.09 24 18.1 24 12.07z"/>',
		'instagram' => '<rect x="2.5" y="2.5" width="19" height="19" rx="5.5" fill="none" stroke="currentColor" stroke-width="2.2"/><circle cx="12" cy="12" r="4.3" fill="none" stroke="currentColor" stroke-width="2.2"/><circle cx="17.6" cy="6.4" r="1.4" fill="currentColor"/>',
		'youtube'   => '<path fill="currentColor" d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.6 12 3.6 12 3.6s-7.5 0-9.4.5A3 3 0 0 0 .5 6.2 31 31 0 0 0 0 12a31 31 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.5 9.4.5 9.4.5s7.5 0 9.4-.5a3 3 0 0 0 2.1-2.1A31 31 0 0 0 24 12a31 31 0 0 0-.5-5.8zM9.6 15.6V8.4l6.3 3.6-6.3 3.6z"/>',
		'tiktok'    => '<path fill="currentColor" d="M12.53.02C13.84 0 15.14.01 16.44 0c.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/>',
	);
	return '<svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true">' . ( $p[ $retea ] ?? '' ) . '</svg>';
}

/**
 * Bara „Urmărește-ne” cu pictogramele rețelelor.
 */
function fr_bara_retele() {
	$retele = array( 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'tiktok' => 'TikTok' );
	$out    = '';
	foreach ( $retele as $k => $nume ) {
		if ( fr_setare( $k ) ) {
			$out .= '<li><a class="fr-retea fr-retea--' . $k . '" href="' . esc_url( fr_setare( $k ) ) . '" rel="noopener" target="_blank" aria-label="' . esc_attr( $nume ) . '">' . fr_icon_retea( $k ) . '</a></li>';
		}
	}
	return $out ? '<div class="fr-retele"><span class="fr-retele__text">Urmărește-ne</span><ul>' . $out . '</ul></div>' : '';
}
