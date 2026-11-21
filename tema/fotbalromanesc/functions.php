<?php
/**
 * fotbalromanesc – funcțiile temei.
 *
 * inc/taxonomii.php  – competiții și cluburi (cu câmpurile lor din admin)
 * inc/date.php       – clasamente, program, formă și marcatori (LiveScore sau un fișier JSON)
 * inc/import.php     – importul articolelor din depozitul GitHub, la 15 minute
 * inc/setari.php     – pagina de setări (Aspect › fotbalromanesc)
 * inc/sabloane.php   – bucăți de afișare folosite în șabloane
 * inc/actualizare.php – actualizarea temei din depozit (automată sau la cerere)
 * inc/coperta.php    – coperțile generate pentru Instagram și Facebook
 * inc/social.php     – publicarea automată pe Facebook și Instagram
 * inc/cluburi.php    – cluburile create din date, stemele și profilurile (stadion, antrenor, lot)
 * inc/seo.php        – adrese curate, titluri, descrieri, canonical, date structurate, sitemap Google News
 *
 * @package fotbalromanesc
 */

defined( 'ABSPATH' ) || exit;

define( 'FR_VERSIUNE', '1.6.0' );
define( 'FR_DIR', get_template_directory() );
define( 'FR_URI', get_template_directory_uri() );

require FR_DIR . '/inc/nume-echipe.php';
require FR_DIR . '/inc/taxonomii.php';
require FR_DIR . '/inc/date.php';
require FR_DIR . '/inc/import.php';
require FR_DIR . '/inc/setari.php';
require FR_DIR . '/inc/sabloane.php';
require FR_DIR . '/inc/actualizare.php';
require FR_DIR . '/inc/coperta.php';
require FR_DIR . '/inc/social.php';
require FR_DIR . '/inc/cluburi.php';
require FR_DIR . '/inc/seo.php';
require FR_DIR . '/inc/video.php';
require FR_DIR . '/inc/superscore.php';

add_action( 'after_setup_theme', 'fr_setup' );
function fr_setup() {
	load_theme_textdomain( 'fotbalromanesc', FR_DIR . '/languages' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support( 'custom-logo', array( 'height' => 48, 'width' => 240, 'flex-width' => true, 'flex-height' => true ) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/css/editor.css' );

	add_image_size( 'fr-mare', 1200, 675, true );
	add_image_size( 'fr-card', 640, 360, true );

	register_nav_menus( array(
		'principal' => __( 'Meniul principal', 'fotbalromanesc' ),
		'subsol'    => __( 'Meniul din subsol', 'fotbalromanesc' ),
	) );
}

add_action( 'widgets_init', 'fr_widgets' );
function fr_widgets() {
	register_sidebar( array(
		'name'          => __( 'Bara laterală', 'fotbalromanesc' ),
		'id'            => 'bara-laterala',
		'description'   => __( 'Apare sub clasamente, pe prima pagină și pe paginile de ligă.', 'fotbalromanesc' ),
		'before_widget' => '<section id="%1$s" class="fr-card fr-widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h2 class="fr-titlu-sectiune">',
		'after_title'   => '</h2>',
	) );
	register_sidebar( array(
		'name'          => __( 'Articol – după text', 'fotbalromanesc' ),
		'id'            => 'dupa-articol',
		'description'   => __( 'Apare la finalul fiecărui articol (de exemplu, abonarea la newsletter).', 'fotbalromanesc' ),
		'before_widget' => '<section id="%1$s" class="fr-card fr-widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h2 class="fr-titlu-sectiune">',
		'after_title'   => '</h2>',
	) );
}

add_action( 'wp_enqueue_scripts', 'fr_resurse' );
function fr_resurse() {
	wp_enqueue_style( 'fr-fonturi', 'https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700&family=Inter:wght@400;500;600;700&display=swap', array(), null );
	wp_enqueue_style( 'fr-tema', FR_URI . '/assets/css/tema.css', array( 'fr-fonturi' ), FR_VERSIUNE );
	wp_enqueue_script( 'fr-tema', FR_URI . '/assets/js/tema.js', array(), FR_VERSIUNE, array( 'strategy' => 'defer', 'in_footer' => true ) );
	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}

add_filter( 'wp_resource_hints', 'fr_preconnect', 10, 2 );
function fr_preconnect( $urls, $tip ) {
	if ( 'preconnect' === $tip ) {
		$urls[] = 'https://fonts.googleapis.com';
		$urls[] = array( 'href' => 'https://fonts.gstatic.com', 'crossorigin' );
	}
	return $urls;
}

// Cu logo încărcat (Aspect › Personalizează › Identitatea site-ului), antetul devine alb, ca logoul să se vadă.
add_filter( 'body_class', function ( $c ) {
	$c[] = 'fr-cu-logo'; // logoul (al temei sau cel încărcat) e gândit pe fundal alb
	return $c;
} );

// Iconița site-ului: logoul pătrat, dacă în Aspect › Personalizează nu e setată alta.
add_action( 'wp_head', function () {
	if ( has_site_icon() ) {
		return;
	}
	$u = FR_URI . '/assets/logo/';
	printf( '<link rel="icon" href="%sfavicon.ico" sizes="any">' . "\n", esc_url( $u ) );
	printf( '<link rel="icon" type="image/png" sizes="32x32" href="%1$sfavicon-32.png">' . "\n" . '<link rel="icon" type="image/png" sizes="192x192" href="%1$sicon-192.png">' . "\n" . '<link rel="apple-touch-icon" href="%1$sapple-touch-icon.png">' . "\n", esc_url( $u ) );
}, 2 );

/**
 * /favicon.ico la rădăcina site-ului (unele browsere, cititoare RSS și motoare de căutare îl cer direct).
 * Se copiază din temă o singură dată pe versiune și doar dacă la rădăcină nu există deja un favicon.ico pus de altcineva.
 */
add_action( 'init', function () {
	if ( get_option( 'fr_favicon_versiune' ) === FR_VERSIUNE ) {
		return;
	}
	update_option( 'fr_favicon_versiune', FR_VERSIUNE, false );
	$dest = ABSPATH . 'favicon.ico';
	$src  = FR_DIR . '/assets/logo/favicon.ico';
	if ( ( ! file_exists( $dest ) || get_option( 'fr_favicon_al_temei' ) ) && is_writable( ABSPATH ) && file_exists( $src ) ) {
		if ( copy( $src, $dest ) ) {
			update_option( 'fr_favicon_al_temei', 1, false );
		}
	}
} );

add_filter( 'excerpt_length', fn() => 28 );
add_filter( 'excerpt_more', fn() => '…' );

/**
 * Fără meniu definit în admin: competițiile principale, în ordinea lor.
 */
function fr_meniu_implicit() {
	$termeni = get_terms( array(
		'taxonomy'   => 'competitie',
		'parent'     => 0,
		'hide_empty' => false,
		'meta_key'   => 'fr_ordine',
		'orderby'    => 'meta_value_num',
		'order'      => 'ASC',
	) );
	if ( is_wp_error( $termeni ) || ! $termeni ) {
		return;
	}
	echo '<ul class="fr-meniu">';
	foreach ( $termeni as $t ) {
		$activ = is_tax( 'competitie', $t->term_id ) || ( is_singular( 'post' ) && has_term( $t->term_id, 'competitie' ) );
		printf(
			'<li%s><a href="%s"%s>%s</a></li>',
			$activ ? ' class="current-menu-item"' : '',
			esc_url( get_term_link( $t ) ),
			$activ ? ' aria-current="page"' : '',
			esc_html( $t->name )
		);
	}
	echo '</ul>';
}

/**
 * La activarea temei: competițiile de bază, ca site-ul să aibă din prima meniul și paginile de ligă.
 */
add_action( 'after_switch_theme', 'fr_competitii_initiale' );
function fr_competitii_initiale() {
	fr_inregistreaza_taxonomii();
	$baza = array(
		array( 'SuperLiga', 'superliga', 1, 'romania/liga-1' ),
		array( 'Liga 2', 'liga-2', 2, 'romania/liga-ii' ),
		array( 'Liga 3', 'liga-3', 3, '' ),
		array( 'Naționala', 'nationala', 4, '' ),
		array( 'Stranierii', 'stranierii', 5, '' ),
		array( 'Opinie', 'opinie', 6, '' ),
	);
	foreach ( $baza as $c ) {
		if ( term_exists( $c[1], 'competitie' ) ) {
			continue;
		}
		$t = wp_insert_term( $c[0], 'competitie', array( 'slug' => $c[1] ) );
		if ( is_wp_error( $t ) ) {
			continue;
		}
		update_term_meta( $t['term_id'], 'fr_ordine', $c[2] );
		if ( $c[3] ) {
			update_term_meta( $t['term_id'], 'fr_livescore', $c[3] );
			update_term_meta( $t['term_id'], 'fr_pe_prima', 1 );
		}
	}
	$liga3 = get_term_by( 'slug', 'liga-3', 'competitie' );
	if ( $liga3 ) {
		for ( $i = 1; $i <= 8; $i++ ) {
			if ( ! term_exists( 'liga-3-seria-' . $i, 'competitie' ) ) {
				$s = wp_insert_term( 'Seria ' . $i, 'competitie', array( 'slug' => 'liga-3-seria-' . $i, 'parent' => $liga3->term_id ) );
				if ( ! is_wp_error( $s ) ) {
					update_term_meta( $s['term_id'], 'fr_ordine', $i );
					update_term_meta( $s['term_id'], 'fr_neoficial', 1 );
				}
			}
		}
	}
	$nationala = get_term_by( 'slug', 'nationala', 'competitie' );
	if ( $nationala ) {
		$echipe = array(
			array( 'Naționala mare', 'nationala-mare', 'Romania', 1 ),
			array( 'U21', 'nationala-u21', 'Romania U21', 0 ),
			array( 'U19', 'nationala-u19', 'Romania U19', 0 ),
			array( 'U17', 'nationala-u17', 'Romania U17', 0 ),
			array( 'Feminin', 'nationala-feminin', '', 0 ),
		);
		foreach ( $echipe as $i => $e ) {
			if ( term_exists( $e[1], 'competitie' ) ) {
				continue;
			}
			$n = wp_insert_term( $e[0], 'competitie', array( 'slug' => $e[1], 'parent' => $nationala->term_id ) );
			if ( is_wp_error( $n ) ) {
				continue;
			}
			update_term_meta( $n['term_id'], 'fr_ordine', $i + 1 );
			if ( $e[2] ) {
				update_term_meta( $n['term_id'], 'fr_echipa_livescore', $e[2] );
			}
			if ( $e[3] ) {
				update_term_meta( $n['term_id'], 'fr_pe_prima', 1 );
			}
		}
	}

	if ( ! fr_pagina_program() ) {
		$id = wp_insert_post( array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => 'Program și rezultate',
			'post_name'   => 'program',
		) );
		if ( $id && ! is_wp_error( $id ) ) {
			update_post_meta( $id, '_wp_page_template', 'page-templates/program.php' );
		}
	}
	flush_rewrite_rules();
}

/**
 * Pagina „Program și rezultate” (șablonul page-templates/program.php), dacă există.
 */
function fr_pagina_program() {
	$p = get_posts( array(
		'post_type'   => 'page',
		'post_status' => 'publish',
		'meta_key'    => '_wp_page_template',
		'meta_value'  => 'page-templates/program.php',
		'numberposts' => 1,
		'fields'      => 'ids',
	) );
	return $p ? (int) $p[0] : 0;
}

function fr_url_program() {
	$id = fr_pagina_program();
	return $id ? get_permalink( $id ) : home_url( '/#meciuri' );
}
