<?php
/**
 * SEO: adrese curate pentru file, titluri și descrieri pe tip de pagină, canonical, date structurate
 * (WebSite, Organization, SportsTeam, BreadcrumbList), sitemap pentru Google News, verificarea Search Console.
 * Când e activ un modul SEO (Yoast, Rank Math, SEOPress, AIOSEO), titlurile, descrierile și canonical-ul rămân ale lui.
 *
 * @package fotbalromanesc
 */

defined( 'ABSPATH' ) || exit;

/**
 * E activ un modul SEO care chiar scrie titlurile și meta-datele? (Nu doar o constantă definită de un modul înrudit.)
 */
function fr_modul_seo_activ() {
	return ( defined( 'WPSEO_VERSION' ) && class_exists( 'WPSEO_Options' ) && function_exists( 'YoastSEO' ) )
		// Rank Math scrie meta-datele prin acțiunea „rank_math/head”; fără ea (neconfigurat), nu scrie nimic.
		|| ( defined( 'RANK_MATH_VERSION' ) && has_action( 'rank_math/head' ) )
		|| ( defined( 'SEOPRESS_VERSION' ) && function_exists( 'seopress_init' ) )
		|| ( defined( 'AIOSEO_VERSION' ) && function_exists( 'aioseo' ) );
}

// /news-sitemap.xml fără „/” la final.
add_filter( 'redirect_canonical', function ( $redirect ) {
	return get_query_var( 'fr_news_sitemap' ) ? false : $redirect;
} );

/* ---------- Adrese curate: /competitie/liga-2/clasament/, /club/x/meciuri/ ---------- */

add_filter( 'query_vars', fn( $v ) => array_merge( $v, array( 'fila', 'fr_news_sitemap' ) ) );

add_action( 'init', 'fr_reguli_adrese', 20 );
function fr_reguli_adrese() {
	add_rewrite_rule( '^competitie/([^/]+)/(clasament|meciuri)/?$', 'index.php?competitie=$matches[1]&fila=$matches[2]', 'top' );
	add_rewrite_rule( '^club/([^/]+)/(meciuri|stiri)/?$', 'index.php?club=$matches[1]&fila=$matches[2]', 'top' );
	add_rewrite_rule( '^news-sitemap\.xml$', 'index.php?fr_news_sitemap=1', 'top' );
	if ( get_option( 'fr_reguli_versiune' ) !== FR_VERSIUNE ) {
		update_option( 'fr_reguli_versiune', FR_VERSIUNE, false );
		flush_rewrite_rules( false );
		// LiteSpeed Cache poate ține redirecționări vechi: golim paginile pe care le-am schimbat.
		do_action( 'litespeed_purge_url', home_url( '/news-sitemap.xml' ) );
		do_action( 'litespeed_purge_url', home_url( '/news-sitemap.xml/' ) );
		do_action( 'litespeed_purge_url', home_url( '/robots.txt' ) );
		// O versiune nouă a temei schimbă paginile: golim tot cache-ul LiteSpeed.
		do_action( 'litespeed_purge_all' );
	}
}

// Adresele vechi cu ?fila= se redirecționează (301) spre cele curate.
add_action( 'template_redirect', function () {
	if ( isset( $_GET['fila'] ) && ( is_tax( 'competitie' ) || is_tax( 'club' ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$f    = sanitize_key( $_GET['fila'] ); // phpcs:ignore WordPress.Security.NonceVerification
		$dest = fr_url_fila( $f );
		$rest = array_diff_key( $_GET, array( 'fila' => 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification
		wp_safe_redirect( $rest ? add_query_arg( array_map( 'sanitize_text_field', wp_unslash( $rest ) ), $dest ) : $dest, 301 );
		exit;
	}
} );

/* ---------- Titluri și descrieri ---------- */

/**
 * [titlu, descriere] pentru pagina curentă, sau null pentru paginile la care rămâne comportamentul WordPress.
 */
function fr_seo_pagina() {
	static $rez = false;
	if ( false !== $rez ) {
		return $rez;
	}
	$rez   = null;
	$sezon = fr_sezon();
	if ( is_front_page() ) {
		$rez = array(
			get_bloginfo( 'name' ) . ': știri, rezultate și clasamente din fotbalul românesc',
			'Tot fotbalul românesc: SuperLiga, Liga 2, Liga 3 pe serii și naționalele. Rezultate, clasamente la zi, program, cronici și știri.',
		);
	} elseif ( is_tax( 'competitie' ) ) {
		$t    = get_queried_object();
		$nume = fr_eticheta_competitie( $t );
		$fila = fr_fila_curenta( array( 'stiri', 'meciuri', 'clasament' ), 'stiri' );
		$na   = (bool) get_term_meta( $t->term_id, 'fr_echipa_livescore', true );
		if ( 'clasament' === $fila ) {
			$rez = array( "Clasament {$nume} {$sezon}", "Clasamentul {$nume} în sezonul {$sezon}, actualizat după fiecare meci: puncte, golaveraj și forma ultimelor 5 meciuri ale fiecărei echipe." );
		} elseif ( 'meciuri' === $fila ) {
			$rez = $na
				? array( "{$nume}: program și rezultate", "Programul și rezultatele echipei {$nume} a României: preliminarii, Liga Națiunilor și meciuri amicale, cu ora meciurilor." )
				: array( "{$nume}: program și rezultate {$sezon}", "Programul și rezultatele din {$nume}, sezonul {$sezon}, etapă cu etapă, cu ora meciurilor și scorurile finale." );
		} else {
			$rez = array( "{$nume}: știri, rezultate, clasament și program {$sezon}", wp_strip_all_tags( term_description() ) ?: "Știri, cronici, rezultate, clasamentul și programul din {$nume}, sezonul {$sezon}." );
		}
	} elseif ( is_tax( 'club' ) ) {
		$c    = get_queried_object();
		$fila = fr_fila_curenta( array( 'prezentare', 'meciuri', 'stiri' ), 'prezentare' );
		$cid  = (int) get_term_meta( $c->term_id, 'fr_competitie', true );
		$comp = $cid ? get_term( $cid, 'competitie' ) : null;
		$in   = $comp instanceof WP_Term ? ' în ' . fr_eticheta_competitie( $comp ) : '';
		if ( 'meciuri' === $fila ) {
			$rez = array( "{$c->name}: program și rezultate {$sezon}", "Toate meciurile {$c->name}{$in} în sezonul {$sezon}: rezultate, program, ora meciurilor și cronicile noastre." );
		} elseif ( 'stiri' === $fila ) {
			$rez = array( "{$c->name}: știri și cronici", "Ultimele știri și cronici despre {$c->name}, de la fotbalromanesc.ro." );
		} else {
			$rez = array( "{$c->name}: rezultate, clasament, program și lot {$sezon}", "{$c->name}{$in}, sezonul {$sezon}: forma echipei, ultimul rezultat, următorul meci, clasamentul la zi, programul, stadionul, antrenorul și lotul." );
		}
	}
	return $rez;
}

add_filter( 'pre_get_document_title', function ( $titlu ) {
	if ( fr_modul_seo_activ() ) {
		return $titlu;
	}
	$p = fr_seo_pagina();
	if ( ! $p ) {
		return $titlu;
	}
	$pag = (int) get_query_var( 'paged' );
	return $p[0] . ( $pag > 1 ? ' – pagina ' . $pag : '' ) . ( is_front_page() ? '' : ' | ' . get_bloginfo( 'name' ) );
} );

/**
 * Descrierea paginii curente (folosită de meta description și Open Graph).
 */
function fr_descriere_pagina() {
	$p = fr_seo_pagina();
	if ( $p ) {
		return $p[1];
	}
	if ( is_singular() ) {
		return wp_strip_all_tags( get_the_excerpt() );
	}
	return get_bloginfo( 'description' );
}

/* ---------- Canonical, robots, verificare ---------- */

add_action( 'wp_head', function () {
	if ( fr_setare( 'google_verificare' ) ) {
		printf( '<meta name="google-site-verification" content="%s">' . "\n", esc_attr( fr_setare( 'google_verificare' ) ) );
	}
	if ( fr_modul_seo_activ() ) {
		return;
	}
	// WordPress pune canonical doar pe articole și pagini; aici și pe ligi, cluburi și filele lor.
	if ( is_tax( 'competitie' ) || is_tax( 'club' ) ) {
		$url = fr_url_fila( fr_fila_curenta( array( 'stiri', 'prezentare', 'meciuri', 'clasament' ), is_tax( 'club' ) ? 'prezentare' : 'stiri' ) );
		$pag = (int) get_query_var( 'paged' );
		if ( $pag > 1 ) {
			$url = trailingslashit( $url ) . 'page/' . $pag . '/';
		}
		printf( '<link rel="canonical" href="%s">' . "\n", esc_url( $url ) );
	}
}, 3 );

// Paginile fără valoare pentru căutare nu se indexează.
add_filter( 'wp_robots', function ( $r ) {
	if ( is_search() || is_404() || ( is_author() && ! have_posts() ) ) {
		$r['noindex'] = true;
		$r['follow']  = true;
	}
	$r['max-image-preview'] = 'large'; // imagini mari în Google Discover
	return $r;
} );

/* ---------- Date structurate ---------- */

function fr_logo_url() {
	return FR_URI . '/assets/logo/logo-patrat-512.png';
}

function fr_organizatie() {
	$same = array_values( array_filter( array( fr_setare( 'facebook' ), fr_setare( 'instagram' ), fr_setare( 'youtube' ), fr_setare( 'tiktok' ) ) ) );
	return array(
		'@type'  => 'NewsMediaOrganization',
		'@id'    => home_url( '/#organizatie' ),
		'name'   => get_bloginfo( 'name' ),
		'url'    => home_url( '/' ),
		'logo'   => array( '@type' => 'ImageObject', 'url' => fr_logo_url(), 'width' => 512, 'height' => 512 ),
		'email'  => fr_setare( 'email' ),
		'sameAs' => $same,
	);
}

function fr_firimituri() {
	$l = array( array( 'Acasă', home_url( '/' ) ) );
	if ( is_singular( 'post' ) ) {
		$c = fr_competitia_articolului();
		if ( $c ) {
			if ( $c->parent ) {
				$p = get_term( $c->parent, 'competitie' );
				if ( $p instanceof WP_Term ) {
					$l[] = array( $p->name, get_term_link( $p ) );
				}
			}
			$l[] = array( $c->name, get_term_link( $c ) );
		}
		$l[] = array( get_the_title(), get_permalink() );
	} elseif ( is_tax( 'competitie' ) || is_tax( 'club' ) ) {
		$t = get_queried_object();
		if ( is_tax( 'club' ) ) {
			$cid = (int) get_term_meta( $t->term_id, 'fr_competitie', true );
			$c   = $cid ? get_term( $cid, 'competitie' ) : null;
			if ( $c instanceof WP_Term ) {
				$l[] = array( fr_eticheta_competitie( $c ), get_term_link( $c ) );
			}
		} elseif ( $t->parent ) {
			$p = get_term( $t->parent, 'competitie' );
			if ( $p instanceof WP_Term ) {
				$l[] = array( $p->name, get_term_link( $p ) );
			}
		}
		$l[] = array( $t->name, get_term_link( $t ) );
	} else {
		return null;
	}
	$items = array();
	foreach ( $l as $i => $x ) {
		$items[] = array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => wp_strip_all_tags( $x[0] ), 'item' => $x[1] );
	}
	return array( '@type' => 'BreadcrumbList', 'itemListElement' => $items );
}

add_action( 'wp_head', function () {
	$grafic = array();
	if ( is_front_page() ) {
		$grafic[] = fr_organizatie();
		$grafic[] = array(
			'@type'           => 'WebSite',
			'@id'             => home_url( '/#site' ),
			'url'             => home_url( '/' ),
			'name'            => get_bloginfo( 'name' ),
			'inLanguage'      => 'ro-RO',
			'publisher'       => array( '@id' => home_url( '/#organizatie' ) ),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => home_url( '/?s={search_term_string}' ),
				'query-input' => 'required name=search_term_string',
			),
		);
	}
	if ( is_tax( 'club' ) ) {
		$c      = get_queried_object();
		$profil = fr_profil_club( $c );
		$echipa = array(
			'@type' => 'SportsTeam',
			'name'  => $c->name,
			'sport' => 'Fotbal',
			'url'   => get_term_link( $c ),
		);
		$sigla = get_term_meta( $c->term_id, 'fr_sigla', true );
		if ( $sigla ) {
			$echipa['logo'] = $sigla;
		}
		$cid = (int) get_term_meta( $c->term_id, 'fr_competitie', true );
		$cmp = $cid ? get_term( $cid, 'competitie' ) : null;
		if ( $cmp instanceof WP_Term ) {
			$echipa['memberOf'] = array( '@type' => 'SportsOrganization', 'name' => fr_eticheta_competitie( $cmp ), 'url' => get_term_link( $cmp ) );
		}
		if ( ! empty( $profil['stadion']['nume'] ) ) {
			$echipa['location'] = array( '@type' => 'StadiumOrArena', 'name' => $profil['stadion']['nume'], 'address' => $profil['oras'] ?? '' );
		}
		if ( ! empty( $profil['antrenor']['nume'] ) ) {
			$echipa['coach'] = array( '@type' => 'Person', 'name' => $profil['antrenor']['nume'] );
		}
		if ( ! empty( $profil['lot'] ) ) {
			$echipa['athlete'] = array_map( fn( $j ) => array( '@type' => 'Person', 'name' => $j['nume'] ?? '' ), array_slice( (array) $profil['lot'], 0, 40 ) );
		}
		$grafic[] = $echipa;
	}
	$fir = fr_firimituri();
	if ( $fir ) {
		$grafic[] = $fir;
	}
	if ( $grafic ) {
		echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $grafic ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
	}
}, 6 );

/* ---------- Sitemap pentru Google News: articolele din ultimele 48 de ore ---------- */

add_action( 'template_redirect', function () {
	if ( ! get_query_var( 'fr_news_sitemap' ) ) {
		return;
	}
	$posts = get_posts( array( 'numberposts' => 1000, 'date_query' => array( array( 'after' => '48 hours ago' ) ) ) );
	// Google News vrea articolele noi repede: cache de cel mult 10 minute.
	do_action( 'litespeed_control_set_ttl', 600 );
	do_action( 'litespeed_tag_add', 'fr_news' );
	header( 'Content-Type: application/xml; charset=UTF-8' );
	echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">' . "\n";
	foreach ( $posts as $p ) {
		printf(
			"<url><loc>%s</loc><news:news><news:publication><news:name>%s</news:name><news:language>ro</news:language></news:publication><news:publication_date>%s</news:publication_date><news:title>%s</news:title></news:news></url>\n",
			esc_url( get_permalink( $p ) ),
			esc_xml( get_bloginfo( 'name' ) ),
			esc_xml( get_the_date( 'c', $p ) ),
			esc_xml( wp_strip_all_tags( get_the_title( $p ) ) )
		);
	}
	echo '</urlset>';
	exit;
} );

// Sitemap-ul obișnuit (wp-sitemap.xml) primește și filele „clasament” și „meciuri” ale competițiilor cu date.
add_action( 'init', function () {
	if ( ! function_exists( 'wp_register_sitemap_provider' ) || ! class_exists( 'WP_Sitemaps_Provider' ) ) {
		return;
	}
	require_once FR_DIR . '/inc/sitemap-file.php';
	wp_register_sitemap_provider( 'filefr', new FR_Sitemap_File() );
}, 30 );

add_filter( 'robots_txt', function ( $txt, $public ) {
	if ( $public ) {
		// WordPress scrie deja linia pentru wp-sitemap.xml; adăugăm doar ce lipsește.
		if ( false === strpos( $txt, '/wp-sitemap.xml' ) ) {
			$txt .= "\nSitemap: " . home_url( '/wp-sitemap.xml' );
		}
		$txt .= "\nSitemap: " . home_url( '/news-sitemap.xml' ) . "\n";
	}
	return $txt;
}, 10, 2 );

add_action( 'transition_post_status', function ( $nou, $vechi, $post ) {
	if ( 'publish' === $nou && 'post' === $post->post_type ) {
		do_action( 'litespeed_purge', 'fr_news' );
	}
}, 10, 3 );

// Sitemap curat: fără conturile de utilizator (ar arăta numele de autentificare) și fără categoriile WordPress nefolosite.
add_filter( 'wp_sitemaps_add_provider', fn( $p, $nume ) => 'users' === $nume ? false : $p, 10, 2 );
add_filter( 'wp_sitemaps_taxonomies', function ( $t ) {
	unset( $t['category'] );
	return $t;
} );
