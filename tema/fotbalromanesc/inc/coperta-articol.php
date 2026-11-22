<?php
/**
 * Coperta articolului (imaginea principală), generată pentru articolele fără fotografie, din stemele cluburilor:
 *  - cronică (linia „Gazde – Oaspeți 2-1” sau un scor în titlu): stema gazdelor, scorul, stema oaspeților;
 *  - știre despre un club: stema lui, mare;
 *  - rezumat de serie / mai multe cluburi: până la 4 steme;
 *  - fără steme (naționale, stranieri): logoul site-ului.
 * 1200×675 (16:9), albastru-închis, cu eticheta competiției și logoul. Atașată ca imagine principală
 * (meta _fr_coperta_auto), deci apare pe carduri, în articol, în Google și pe rețele. O fotografie reală
 * pusă de mână nu e înlocuită niciodată.
 *
 * @package fotbalromanesc
 */

defined( 'ABSPATH' ) || exit;

const FR_COPERTA_VERSIUNE = 2; // la schimbarea desenului, coperțile automate se refac

/**
 * Calea locală a stemei unui club (fișierul din uploads) sau ''.
 */
function fr_cale_sigla( $club ) {
	$url = $club instanceof WP_Term ? (string) get_term_meta( $club->term_id, 'fr_sigla', true ) : '';
	if ( ! $url ) {
		return '';
	}
	$id = attachment_url_to_postid( $url );
	$f  = $id ? get_attached_file( $id ) : '';
	return $f && file_exists( $f ) ? $f : '';
}

/**
 * Ce arată coperta: [ 'tip' => meci|club|cluburi|gol, 'cluburi' => WP_Term[], 'scor' => '2-1' ].
 */
function fr_coperta_continut( $post_id ) {
	$cluburi = wp_get_post_terms( $post_id, 'club' );
	$cluburi = is_wp_error( $cluburi ) ? array() : $cluburi;
	$cont    = (string) get_post_field( 'post_content', $post_id );
	// Linia de rezultat a cronicii: „Gazde – Oaspeți 2-1 (pauză…)”.
	if ( preg_match( '~<strong>(.+?)\s+–\s+(.+?)\s+(\d+)-(\d+)\b~u', $cont, $m ) ) {
		$g = fr_gaseste_club( wp_strip_all_tags( $m[1] ) );
		$o = fr_gaseste_club( wp_strip_all_tags( $m[2] ) );
		if ( $g && $o && fr_cale_sigla( $g ) && fr_cale_sigla( $o ) ) {
			return array( 'tip' => 'meci', 'cluburi' => array( $g, $o ), 'scor' => $m[3] . '-' . $m[4] );
		}
	}
	$cu_sigla = array_values( array_filter( $cluburi, 'fr_cale_sigla' ) );
	if ( 1 === count( $cu_sigla ) ) {
		return array( 'tip' => 'club', 'cluburi' => $cu_sigla, 'scor' => '' );
	}
	if ( 2 === count( $cu_sigla ) && 2 === count( $cluburi ) ) {
		return array( 'tip' => 'duel', 'cluburi' => $cu_sigla, 'scor' => '' );
	}
	if ( $cu_sigla ) {
		return array( 'tip' => 'cluburi', 'cluburi' => array_slice( $cu_sigla, 0, 4 ), 'scor' => '' );
	}
	return array( 'tip' => 'gol', 'cluburi' => array(), 'scor' => '' );
}

/**
 * Lipește o stemă „contain” în pătratul (cx, cy, latura), pe un cerc alb discret.
 */
function fr_lipeste_sigla( $im, $fisier, $cx, $cy, $latura ) {
	$date = file_get_contents( $fisier ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$s    = $date ? @imagecreatefromstring( $date ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors
	if ( ! $s ) {
		return false;
	}
	$r = (int) round( $latura * 0.62 );
	imagefilledellipse( $im, $cx, $cy, 2 * $r, 2 * $r, fr_culoare( $im, 'FFFFFF', 0 ) );
	$sw = imagesx( $s );
	$sh = imagesy( $s );
	$k  = min( $latura / $sw, $latura / $sh );
	$w  = (int) round( $sw * $k );
	$h  = (int) round( $sh * $k );
	imagealphablending( $im, true );
	imagecopyresampled( $im, $s, $cx - (int) ( $w / 2 ), $cy - (int) ( $h / 2 ), 0, 0, $w, $h, $sw, $sh );
	imagedestroy( $s );
	return true;
}

function fr_text_centrat( $im, $text, $font, $marime, $cx, $y, $culoare, $max_latime = 0 ) {
	if ( $max_latime ) {
		while ( $marime > 18 ) {
			$b = imagettfbbox( $marime, 0, $font, $text );
			if ( $b[2] - $b[0] <= $max_latime ) {
				break;
			}
			$marime -= 2;
		}
	}
	$b = imagettfbbox( $marime, 0, $font, $text );
	imagettftext( $im, $marime, 0, $cx - (int) ( ( $b[2] - $b[0] ) / 2 ), $y, $culoare, $font, $text );
}

function fr_deseneaza_coperta_articol( $post_id ) {
	$bold = get_template_directory() . FR_FONT_BOLD;
	$semi = get_template_directory() . FR_FONT_SEMI;
	if ( ! function_exists( 'imagettftext' ) || ! file_exists( $bold ) ) {
		return null;
	}
	$W  = 1200;
	$H  = 675;
	$im = imagecreatetruecolor( $W, $H );
	imagealphablending( $im, true );
	$navy   = fr_culoare( $im, '010E33' );
	$navy2  = fr_culoare( $im, '0B1F4D' );
	$galben = fr_culoare( $im, 'F5B700' );
	$alb    = fr_culoare( $im, 'FFFFFF' );
	$gri    = fr_culoare( $im, 'C9D3E3' );
	imagefill( $im, 0, 0, $navy );
	// Fâșii diagonale discrete și liniile unui teren.
	for ( $x = -$H; $x < $W; $x += 90 ) {
		imagefilledpolygon( $im, array( $x, $H, $x + 40, $H, $x + 40 + $H, 0, $x + $H, 0 ), $navy2 );
	}
	imagesetthickness( $im, 3 );
	imageline( $im, (int) ( $W / 2 ), 0, (int) ( $W / 2 ), $H, fr_culoare( $im, 'FFFFFF', 112 ) );
	imageellipse( $im, (int) ( $W / 2 ), (int) ( $H / 2 ), 300, 300, fr_culoare( $im, 'FFFFFF', 112 ) );

	$c = fr_coperta_continut( $post_id );
	$y = 340; // centrul vertical al stemelor
	if ( 'meci' === $c['tip'] || 'duel' === $c['tip'] ) {
		list( $g, $o ) = $c['cluburi'];
		fr_lipeste_sigla( $im, fr_cale_sigla( $g ), 270, $y, 230 );
		fr_lipeste_sigla( $im, fr_cale_sigla( $o ), $W - 270, $y, 230 );
		if ( $c['scor'] ) {
			$bb = imagettfbbox( 150, 0, $bold, $c['scor'] );
			$sw = $bb[2] - $bb[0];
			imagefilledrectangle( $im, (int) ( $W / 2 - $sw / 2 - 30 ), $y - 110, (int) ( $W / 2 + $sw / 2 + 30 ), $y + 100, $navy );
			fr_text_centrat( $im, $c['scor'], $bold, 150, (int) ( $W / 2 ), $y + 75, $alb );
		} else {
			fr_text_centrat( $im, 'VS', $bold, 90, (int) ( $W / 2 ), $y + 45, $galben );
		}
		fr_text_centrat( $im, $g->name, $semi, 34, 270, $y + 205, $gri, 420 );
		fr_text_centrat( $im, $o->name, $semi, 34, $W - 270, $y + 205, $gri, 420 );
	} elseif ( 'club' === $c['tip'] ) {
		fr_lipeste_sigla( $im, fr_cale_sigla( $c['cluburi'][0] ), (int) ( $W / 2 ), $y - 10, 300 );
		fr_text_centrat( $im, $c['cluburi'][0]->name, $bold, 46, (int) ( $W / 2 ), $y + 255, $alb, 900 );
	} elseif ( 'cluburi' === $c['tip'] ) {
		$n   = count( $c['cluburi'] );
		$pas = (int) ( $W / ( $n + 1 ) );
		foreach ( $c['cluburi'] as $i => $cl ) {
			fr_lipeste_sigla( $im, fr_cale_sigla( $cl ), $pas * ( $i + 1 ), $y, $n > 3 ? 170 : 200 );
			fr_text_centrat( $im, $cl->name, $semi, 28, $pas * ( $i + 1 ), $y + 175, $gri, $pas - 20 );
		}
	} else {
		fr_lipeste_logo( $im, 'logo-patrat-512.png', (int) ( $W / 2 - 190 ), $y - 205, 380, 380 );
	}

	// Sus: eticheta competiției; dreapta: logoul pătrat.
	$comp = fr_competitia_articolului( $post_id );
	if ( $comp ) {
		$et = mb_strtoupper( fr_eticheta_competitie( $comp ), 'UTF-8' );
		$bb = imagettfbbox( 28, 0, $bold, $et );
		imagefilledrectangle( $im, 56, 48, 56 + ( $bb[2] - $bb[0] ) + 36, 48 + 54, $galben );
		imagettftext( $im, 28, 0, 74, 48 + 41, $navy, $bold, $et );
	}
	if ( 'gol' !== $c['tip'] ) {
		fr_lipeste_logo( $im, 'logo-patrat-240.png', $W - 56 - 120 + 12, 22, 120, 120 );
	}
	imagefilledrectangle( $im, 0, $H - 10, $W, $H, $galben );
	return $im;
}

/**
 * Generează coperta și o pune ca imagine principală. Nu atinge fotografiile reale.
 */
function fr_coperta_articol( $post_id, $forteaza = false ) {
	$thumb = get_post_thumbnail_id( $post_id );
	$auto  = (int) get_post_meta( $post_id, '_fr_coperta_auto', true );
	if ( $thumb && ! $auto ) {
		return false; // fotografie reală
	}
	if ( $thumb && $auto >= FR_COPERTA_VERSIUNE && ! $forteaza ) {
		return false;
	}
	$im = fr_deseneaza_coperta_articol( $post_id );
	if ( ! $im ) {
		return false;
	}
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$tmp = wp_tempnam( 'coperta' );
	imagejpeg( $im, $tmp, 88 );
	imagedestroy( $im );
	$slug = get_post_field( 'post_name', $post_id ) ?: 'articol-' . $post_id;
	$id   = media_handle_sideload( array( 'name' => substr( $slug, 0, 80 ) . '-coperta.jpg', 'tmp_name' => $tmp ), $post_id, get_the_title( $post_id ) );
	if ( is_wp_error( $id ) ) {
		wp_delete_file( $tmp );
		return false;
	}
	update_post_meta( $id, '_wp_attachment_image_alt', wp_strip_all_tags( get_the_title( $post_id ) ) );
	set_post_thumbnail( $post_id, $id );
	update_post_meta( $post_id, '_fr_coperta_auto', FR_COPERTA_VERSIUNE );
	if ( $thumb && $auto ) {
		wp_delete_attachment( $thumb, true ); // coperta automată veche
	}
	return true;
}

/**
 * La fiecare import (15 minute): coperți pentru până la 25 de articole publicate fără imagine (cele noi întâi).
 */
add_action( 'fr_import_continut', 'fr_coperti_lipsa', 30 );
function fr_coperti_lipsa( $max = 25 ) {
	$ids = get_posts( array(
		'post_type'   => 'post',
		'post_status' => 'publish',
		'numberposts' => $max,
		'fields'      => 'ids',
		'meta_query'  => array(
			'relation' => 'OR',
			array( 'key' => '_thumbnail_id', 'compare' => 'NOT EXISTS' ),
			array( 'key' => '_fr_coperta_auto', 'value' => FR_COPERTA_VERSIUNE, 'compare' => '<', 'type' => 'NUMERIC' ),
		),
	) );
	$n = 0;
	foreach ( $ids as $id ) {
		$n += fr_coperta_articol( $id ) ? 1 : 0;
	}
	return $n;
}

// POST /wp-json/fr/v1/coperti — generează acum (cheia X-Fr-Key sau administrator); ?id= pentru un singur articol.
add_action( 'rest_api_init', function () {
	register_rest_route( 'fr/v1', '/coperti', array(
		'methods'             => 'POST',
		'permission_callback' => 'fr_permis_cheie',
		'callback'            => function ( $req ) {
			if ( $req['id'] ) {
				return array( 'facut' => fr_coperta_articol( (int) $req['id'], true ) );
			}
			$n     = fr_coperti_lipsa( 40 );
			$ramas = count( get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_thumbnail_id', 'compare' => 'NOT EXISTS' ) ) ) ) );
			return array( 'facute' => $n, 'fara_imagine' => $ramas );
		},
	) );
} );
