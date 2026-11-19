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
	ob_start();
	?>
	<div class="fr-tabel-cutie">
	<table class="fr-clasament<?php echo $opt['complet'] ? ' fr-clasament--complet' : ''; ?>">
		<thead><tr>
			<th scope="col" class="fr-nr">#</th>
			<th scope="col" class="fr-echipa-col">Echipa</th>
			<?php if ( $opt['complet'] ) : ?>
				<th scope="col"><abbr title="Meciuri jucate">M</abbr></th>
				<th scope="col"><abbr title="Victorii">V</abbr></th>
				<th scope="col"><abbr title="Egaluri">E</abbr></th>
				<th scope="col"><abbr title="Înfrângeri">Î</abbr></th>
				<th scope="col">Goluri</th>
			<?php endif; ?>
			<th scope="col"><abbr title="Golaveraj">GD</abbr></th>
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
					<td><?php echo (int) $r['v']; ?></td>
					<td><?php echo (int) $r['e']; ?></td>
					<td><?php echo (int) $r['i']; ?></td>
					<td><?php echo null === $r['gm'] ? '—' : (int) $r['gm'] . '-' . (int) $r['gp']; ?></td>
				<?php endif; ?>
				<td><?php echo esc_html( fr_gd( $r ) ); ?></td>
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
				<p class="fr-articol__meta"><?php echo esc_html( fr_meta_articol( $post, 'j M Y' ) ); ?></p>
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
 * Filele unei pagini de ligă sau de club (?fila=…).
 */
function fr_file( $file, $activa ) {
	$baza = get_term_link( get_queried_object() );
	echo '<nav class="fr-file" aria-label="Secțiuni"><ul>';
	foreach ( $file as $cheie => $nume ) {
		$url = 'stiri' === $cheie || 'prezentare' === $cheie ? $baza : add_query_arg( 'fila', $cheie, $baza );
		printf(
			'<li><a href="%s"%s>%s</a></li>',
			esc_url( $url ),
			$cheie === $activa ? ' aria-current="page"' : '',
			esc_html( $nume )
		);
	}
	echo '</ul></nav>';
}

function fr_fila_curenta( $permise, $implicit ) {
	$f = isset( $_GET['fila'] ) ? sanitize_key( $_GET['fila'] ) : $implicit; // phpcs:ignore WordPress.Security.NonceVerification
	return in_array( $f, $permise, true ) ? $f : $implicit;
}

/**
 * Open Graph și NewsArticle, doar când nu e activ un modul SEO (Yoast, Rank Math, SEOPress, AIOSEO).
 */
add_action( 'wp_head', 'fr_meta_sociale', 5 );
function fr_meta_sociale() {
	if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || defined( 'AIOSEO_VERSION' ) ) {
		return;
	}
	$titlu = wp_get_document_title();
	$desc  = get_bloginfo( 'description' );
	$img   = '';
	$url   = home_url( add_query_arg( array() ) );
	if ( is_singular() ) {
		$desc = wp_strip_all_tags( get_the_excerpt() );
		$img  = get_the_post_thumbnail_url( null, 'fr-mare' );
		if ( ! $img && is_singular( 'post' ) && function_exists( 'fr_coperta_url' ) ) {
			$img = fr_coperta_url( get_the_ID(), 'og' ); // coperta generată, pentru previzualizarea pe Facebook
		}
		$url  = get_permalink();
	} elseif ( is_tax() ) {
		$desc = wp_strip_all_tags( term_description() ) ?: $desc;
	}
	printf( '<meta property="og:site_name" content="%s">' . "\n", esc_attr( get_bloginfo( 'name' ) ) );
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
			'publisher'     => array( '@type' => 'Organization', 'name' => get_bloginfo( 'name' ) ),
			'mainEntityOfPage' => get_permalink(),
		);
		if ( $img ) {
			$schema['image'] = array( $img );
		}
		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
	}
}
