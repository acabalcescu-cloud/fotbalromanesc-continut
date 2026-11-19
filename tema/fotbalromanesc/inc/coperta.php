<?php
/**
 * Coperțile generate pentru rețele: 1080×1350 (Instagram, 4:5) și 1200×630 (previzualizarea linkului pe Facebook).
 * Albastru-închis, eticheta competiției în galben, titlul mare (Barlow Condensed). Dacă articolul are imagine,
 * ea ocupă partea de sus. Fișierele: uploads/fr-social/<id>-ig.jpg și <id>-og.jpg, refăcute când articolul se schimbă.
 *
 * @package fotbalromanesc
 */

defined( 'ABSPATH' ) || exit;

const FR_FONT_BOLD = '/assets/fonturi/BarlowCondensed-Bold.ttf';
const FR_FONT_SEMI = '/assets/fonturi/BarlowCondensed-SemiBold.ttf';

/**
 * URL-ul coperții ($format: ig | og), generată dacă lipsește sau e mai veche decât articolul. '' dacă nu se poate.
 */
function fr_coperta_url( $post_id, $format = 'ig', $forteaza = false ) {
	$up   = wp_upload_dir();
	$dir  = trailingslashit( $up['basedir'] ) . 'fr-social';
	$file = $dir . '/' . (int) $post_id . '-' . $format . '.jpg';
	$url  = trailingslashit( $up['baseurl'] ) . 'fr-social/' . (int) $post_id . '-' . $format . '.jpg';
	$modificat = strtotime( get_post_field( 'post_modified_gmt', $post_id ) . ' UTC' );
	if ( ! $forteaza && file_exists( $file ) && filemtime( $file ) >= $modificat ) {
		return $url . '?v=' . filemtime( $file );
	}
	if ( ! function_exists( 'imagettftext' ) ) {
		return '';
	}
	$im = fr_deseneaza_coperta( $post_id, $format );
	if ( ! $im ) {
		return '';
	}
	wp_mkdir_p( $dir );
	imagejpeg( $im, $file, 90 );
	imagedestroy( $im );
	return $url . '?v=' . filemtime( $file );
}

function fr_culoare( $im, $hex, $alfa = 0 ) {
	$hex = ltrim( $hex, '#' );
	return imagecolorallocatealpha( $im, hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ), $alfa );
}

/**
 * Împarte textul în rânduri care încap în $latime, la mărimea $marime.
 */
function fr_randuri( $text, $font, $marime, $latime ) {
	$cuvinte = preg_split( '/\s+/u', trim( $text ) );
	$randuri = array();
	$rand    = '';
	foreach ( $cuvinte as $c ) {
		$incercare = '' === $rand ? $c : $rand . ' ' . $c;
		$b         = imagettfbbox( $marime, 0, $font, $incercare );
		if ( $b[2] - $b[0] > $latime && '' !== $rand ) {
			$randuri[] = $rand;
			$rand      = $c;
		} else {
			$rand = $incercare;
		}
	}
	if ( '' !== $rand ) {
		$randuri[] = $rand;
	}
	return $randuri;
}

/**
 * Cea mai mare mărime (între $max și $min) la care textul încape în $latime × $inaltime.
 */
function fr_potriveste( $text, $font, $max, $min, $latime, $inaltime, $interlinie = 1.14 ) {
	for ( $m = $max; $m >= $min; $m -= 2 ) {
		$r = fr_randuri( $text, $font, $m, $latime );
		if ( count( $r ) * $m * $interlinie * 1.33 <= $inaltime ) {
			return array( $m, $r );
		}
	}
	return array( $min, fr_randuri( $text, $font, $min, $latime ) );
}

/**
 * Imaginea articolului, decupată centrat în dreptunghiul dat.
 */
function fr_lipeste_imagine( $im, $post_id, $x, $y, $w, $h ) {
	$thumb = get_post_thumbnail_id( $post_id );
	$src   = $thumb ? get_attached_file( $thumb ) : '';
	if ( ! $src || ! file_exists( $src ) ) {
		return false;
	}
	$date = file_get_contents( $src ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$poza = $date ? @imagecreatefromstring( $date ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors
	if ( ! $poza ) {
		return false;
	}
	$pw = imagesx( $poza );
	$ph = imagesy( $poza );
	$r  = $w / $h;
	if ( $pw / $ph > $r ) {
		$sw = (int) round( $ph * $r ); $sh = $ph; $sx = (int) ( ( $pw - $sw ) / 2 ); $sy = 0;
	} else {
		$sw = $pw; $sh = (int) round( $pw / $r ); $sx = 0; $sy = (int) ( ( $ph - $sh ) / 2 );
	}
	imagecopyresampled( $im, $poza, $x, $y, $sx, $sy, $w, $h, $sw, $sh );
	imagedestroy( $poza );
	// trecere spre fundal, în partea de jos a imaginii
	for ( $i = 0; $i < 220; $i++ ) {
		$alfa = (int) round( 127 - 127 * ( $i / 220 ) );
		imagefilledrectangle( $im, $x, $y + $h - 220 + $i, $x + $w, $y + $h - 220 + $i, fr_culoare( $im, '0B1F3A', max( 0, $alfa ) ) );
	}
	return true;
}

function fr_deseneaza_coperta( $post_id, $format ) {
	$bold = get_template_directory() . FR_FONT_BOLD;
	$semi = get_template_directory() . FR_FONT_SEMI;
	if ( ! file_exists( $bold ) || ! file_exists( $semi ) ) {
		return null;
	}
	list( $W, $H ) = 'og' === $format ? array( 1200, 630 ) : array( 1080, 1350 );
	$im = imagecreatetruecolor( $W, $H );
	imagealphablending( $im, true );
	$navy   = fr_culoare( $im, '0B1F3A' );
	$navy2  = fr_culoare( $im, '13294A' );
	$galben = fr_culoare( $im, 'F5B700' );
	$alb    = fr_culoare( $im, 'FFFFFF' );
	$gri    = fr_culoare( $im, 'C9D3E3' );
	imagefill( $im, 0, 0, $navy );

	// Liniile unui teren, discrete, în colțul din dreapta-jos.
	imagesetthickness( $im, 4 );
	imagearc( $im, $W, $H, (int) round( 1.10 * $H ), (int) round( 1.10 * $H ), 180, 270, $navy2 );
	imagearc( $im, $W, $H, (int) round( 0.60 * $H ), (int) round( 0.60 * $H ), 180, 270, $navy2 );

	$m      = 'og' === $format ? 64 : 80; // marginea
	$titlu  = html_entity_decode( wp_strip_all_tags( get_the_title( $post_id ) ), ENT_QUOTES, 'UTF-8' );
	$comp   = fr_competitia_articolului( $post_id );
	$etich  = $comp ? mb_strtoupper( fr_eticheta_competitie( $comp ), 'UTF-8' ) : '';
	$cu_poza = 'ig' === $format && fr_lipeste_imagine( $im, $post_id, 0, 0, $W, 620 );

	// Sigla-text, sus (nu peste fotografie: atunci site-ul apare doar jos).
	if ( ! $cu_poza ) {
		$fs_s    = 'og' === $format ? 30 : 36;
		$y_sigla = 'og' === $format ? 92 : 118;
		$b       = imagettftext( $im, $fs_s, 0, $m, $y_sigla, $alb, $bold, 'fotbalromanesc' );
		imagettftext( $im, $fs_s, 0, $b[2] + 1, $y_sigla, $galben, $bold, '.ro' );
	}

	// Zona titlului.
	if ( 'og' === $format ) {
		$sus = 150; $jos = $H - 70;
	} else {
		$sus = $cu_poza ? 660 : 300; $jos = $H - 150;
	}
	// Eticheta competiției.
	if ( $etich ) {
		$fs = 'og' === $format ? 24 : 30;
		$bb = imagettfbbox( $fs, 0, $bold, $etich );
		$lw = $bb[2] - $bb[0];
		imagefilledrectangle( $im, $m, $sus, $m + $lw + 32, $sus + $fs + 26, $galben );
		imagettftext( $im, $fs, 0, $m + 16, $sus + $fs + 12, $navy, $bold, $etich );
		$sus += $fs + 26 + ( 'og' === $format ? 26 : 34 );
	}
	list( $marime, $randuri ) = fr_potriveste( $titlu, $bold, 'og' === $format ? 64 : ( $cu_poza ? 76 : 96 ), 34, $W - 2 * $m, $jos - $sus );
	$y = $sus + (int) round( $marime * 1.33 * 0.78 );
	foreach ( $randuri as $r ) {
		imagettftext( $im, $marime, 0, $m, $y, $alb, $bold, $r );
		$y += (int) round( $marime * 1.33 * 1.14 );
	}

	// Jos, pe Instagram: trimiterea la site.
	if ( 'ig' === $format ) {
		imagefilledrectangle( $im, $m, $H - 112, $m + 72, $H - 106, $galben );
		imagettftext( $im, 30, 0, $m, $H - 64, $gri, $semi, 'Citește tot pe fotbalromanesc.ro' );
	}
	return $im;
}
