<?php
/**
 * Cluburile: create automat din datele competițiilor (SuperLiga, Liga 2, seriile Liga 3), cu stema,
 * plus profilul fiecăruia (stadion, antrenor, lot) din depozitul de conținut: date/cluburi/<slug>.json.
 *
 * Profilul (scris de rutina de pe Mac, din surse verificate):
 * {
 *   "nume": "Chindia Târgoviște", "oras": "Târgoviște", "fondat": 2010, "culori": "alb-roșu", "site": "https://…",
 *   "stadion": { "nume": "…", "capacitate": 8400, "inaugurat": 2021, "maps": "https://maps.google.com/?q=…" },
 *   "antrenor": { "nume": "…", "nationalitate": "România", "varsta": 45 },
 *   "lot": [ { "nr": 1, "nume": "…", "post": "portar|fundas|mijlocas|atacant", "varsta": 24, "nationalitate": "România" } ],
 *   "stema": "https://… .png (opțional, doar dacă clubul nu are stemă)",
 *   "actualizat": "2026-11-20", "surse": [ "https://…" ]
 * }
 *
 * @package fotbalromanesc
 */

defined( 'ABSPATH' ) || exit;

const FR_SIGLE_LIVESCORE = 'https://lsm-static-prod.livescore.com/high/';

add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'fr_sincronizare_cluburi' ) ) {
		// Prima dată la un minut după instalare, apoi zilnic.
		wp_schedule_event( time() + ( get_option( 'fr_cluburi_sincronizat' ) ? 300 : 60 ), 'daily', 'fr_sincronizare_cluburi' );
	}
} );
add_action( 'fr_sincronizare_cluburi', 'fr_sincronizeaza_cluburi' );

/**
 * Sezonul curent: „2026/2027” (din iulie până în iunie).
 */
function fr_sezon( $sep = '/' ) {
	$an = (int) wp_date( 'Y' );
	return (int) wp_date( 'n' ) >= 7 ? $an . $sep . ( $an + 1 ) : ( $an - 1 ) . $sep . $an;
}

/**
 * Creează sau actualizează câte un club pentru fiecare echipă din clasamentele competițiilor cu date.
 * Întoarce câte cluburi au fost create.
 */
function fr_sincronizeaza_cluburi( $max_sigle = 40 ) {
	$create = 0;
	$sigle  = 0;
	foreach ( fr_competitii_cu_date() as $t ) {
		if ( get_term_meta( $t->term_id, 'fr_echipa_livescore', true ) ) {
			continue; // naționalele nu au cluburi
		}
		$d = fr_date_competitie( $t->term_id );
		if ( is_array( $d ) && 'LiveScore' === ( $d['sursa'] ?? '' ) && ! isset( $d['sigle'] ) ) {
			$d = fr_incarca_date( $t->term_id ); // copie veche, fără steme
		}
		if ( ! $d || empty( $d['clasament'] ) ) {
			continue;
		}
		$din_livescore = 'LiveScore' === ( $d['sursa'] ?? '' );
		foreach ( $d['clasament'] as $r ) {
			$brut = $r['echipa'];
			$club = fr_club_dupa_nume_date( $brut );
			if ( ! $club ) {
				$nume = fr_nume_echipa( $brut );
				$nou  = wp_insert_term( $nume, 'club' );
				if ( is_wp_error( $nou ) ) {
					$exista = $nou->get_error_data( 'term_exists' );
					$club   = $exista ? get_term( (int) $exista, 'club' ) : null;
				} else {
					$club = get_term( $nou['term_id'], 'club' );
					$create++;
				}
				if ( ! $club instanceof WP_Term ) {
					continue;
				}
				if ( $din_livescore ) {
					update_term_meta( $club->term_id, 'fr_livescore_nume', $brut );
				}
			}
			// Competiția curentă (se schimbă la promovare sau retrogradare).
			update_term_meta( $club->term_id, 'fr_competitie', $t->term_id );
			// Stema, o singură dată, descărcată pe site.
			if ( $din_livescore && $sigle < $max_sigle && ! get_term_meta( $club->term_id, 'fr_sigla', true ) && ! empty( $d['sigle'][ $brut ] ) ) {
				$url = fr_descarca_sigla( FR_SIGLE_LIVESCORE . ltrim( $d['sigle'][ $brut ], '/' ), $club->name );
				$sigle++;
				if ( $url ) {
					update_term_meta( $club->term_id, 'fr_sigla', $url );
				} else {
					update_option( 'fr_sigla_eroare', $club->name . ': ' . get_option( 'fr_sigla_ultima_eroare', '' ), false );
				}
			}
		}
	}
	// Static cache din fr_club_dupa_nume_date nu mai e valabil după creări; nimic altceva de golit.
	update_option( 'fr_cluburi_sincronizat', time(), false );
	fr_uneste_dubluri_cluburi();
	if ( $sigle >= $max_sigle ) {
		// Au rămas steme de descărcat: încă o tură peste 2 minute.
		wp_schedule_single_event( time() + 120, 'fr_sincronizare_cluburi' );
	}
	return $create;
}

function fr_descarca_sigla( $url, $nume ) {
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$tmp = download_url( $url, 30 );
	if ( is_wp_error( $tmp ) ) {
		update_option( 'fr_sigla_ultima_eroare', $tmp->get_error_message(), false );
		return '';
	}
	$ext = strtolower( pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
	$ext = in_array( $ext, array( 'png', 'jpg', 'jpeg', 'webp' ), true ) ? $ext : 'png';
	$id  = media_handle_sideload( array( 'name' => sanitize_title( $nume ) . '-stema.' . $ext, 'tmp_name' => $tmp ), 0, 'Stema ' . $nume );
	if ( is_wp_error( $id ) ) {
		update_option( 'fr_sigla_ultima_eroare', $id->get_error_message(), false );
		wp_delete_file( $tmp );
		return '';
	}
	update_post_meta( $id, '_wp_attachment_image_alt', 'Stema ' . $nume );
	return (string) wp_get_attachment_url( $id );
}

/**
 * Profilul clubului din depozit (12 ore în cache; ultima copie bună rămâne dacă depozitul nu răspunde).
 */
function fr_profil_club( $club ) {
	if ( ! fr_setare( 'repo' ) ) {
		return null;
	}
	$cheie = 'fr_profil_' . $club->term_id;
	$p     = get_transient( $cheie );
	if ( is_array( $p ) ) {
		return $p ?: null;
	}
	$r = fr_repo_json( 'date/cluburi/' . $club->slug . '.json' );
	if ( is_wp_error( $r ) ) {
		$vechi = get_option( 'fr_profil_ultim_' . $club->term_id );
		set_transient( $cheie, is_array( $vechi ) ? $vechi : array(), 6 * HOUR_IN_SECONDS );
		return is_array( $vechi ) ? $vechi : null;
	}
	update_option( 'fr_profil_ultim_' . $club->term_id, $r, false );
	set_transient( $cheie, $r, 12 * HOUR_IN_SECONDS );
	// Stema din profil (pentru cluburile fără stemă în LiveScore, de exemplu cele din Liga 3), o singură încercare per adresă.
	$stema = isset( $r['stema'] ) ? (string) $r['stema'] : '';
	if ( $stema && ! get_term_meta( $club->term_id, 'fr_sigla', true ) && preg_match( '~^https://[^\s]+\.(png|jpe?g|webp)$~i', $stema )
		&& get_term_meta( $club->term_id, 'fr_stema_incercata', true ) !== $stema ) {
		update_term_meta( $club->term_id, 'fr_stema_incercata', $stema );
		$url = fr_descarca_sigla( $stema, $club->name );
		if ( $url ) {
			update_term_meta( $club->term_id, 'fr_sigla', $url );
		}
	}
	return $r;
}

/**
 * Articolul nostru despre un meci (cronica din Liga 2: id-ul AAAA-LL-ZZ-gazde-oaspeti, cu numele din LiveScore).
 */
function fr_articol_meci( $m ) {
	$ts = strtotime( $m['data'] );
	if ( ! $ts ) {
		return 0;
	}
	$id = wp_date( 'Y-m-d', $ts ) . '-' . sanitize_title( remove_accents( $m['gazde'] ) ) . '-' . sanitize_title( remove_accents( $m['oaspeti'] ) );
	$p  = get_posts( array( 'post_type' => 'post', 'meta_key' => '_fr_sursa', 'meta_value' => $id, 'numberposts' => 1, 'fields' => 'ids' ) );
	return $p ? (int) $p[0] : 0;
}

/**
 * Bilanțul echipei din clasament: meciuri, victorii, egaluri, înfrângeri, goluri.
 */
function fr_bilant( $date, $nume_date ) {
	foreach ( $date['clasament'] ?? array() as $r ) {
		if ( $r['echipa'] === $nume_date ) {
			return $r;
		}
	}
	return null;
}

/**
 * Textul de la finalul paginii (pentru cititori și pentru Google), din cifrele reale.
 */
function fr_text_club( $club, $comp, $bilant, $profil ) {
	$sezon = fr_sezon();
	$ce    = $comp ? fr_eticheta_competitie( $comp ) : 'campionat';
	$txt   = array();
	if ( $bilant && ( null === $bilant['v'] || null === $bilant['e'] || null === $bilant['i'] ) ) {
		$txt[] = sprintf(
			'În sezonul %1$s din %2$s, %3$s a jucat până acum %4$d %5$s%6$s și ocupă locul %7$d, cu %8$d %9$s.',
			$sezon,
			$ce,
			$club->name,
			$bilant['meciuri'],
			1 === $bilant['meciuri'] ? 'meci' : 'de meciuri',
			null !== $bilant['gm'] && null !== $bilant['gp'] ? sprintf( ', cu %d goluri marcate și %d primite,', $bilant['gm'], $bilant['gp'] ) : '',
			$bilant['loc'],
			$bilant['pct'],
			1 === $bilant['pct'] ? 'punct' : 'de puncte'
		);
	} elseif ( $bilant ) {
		$txt[] = sprintf(
			'În sezonul %1$s din %2$s, %3$s a jucat până acum %4$d %5$s: %6$d %7$s, %8$d %9$s și %10$d %11$s, cu %12$s goluri marcate și %13$s primite, și ocupă locul %14$d, cu %15$d %16$s.',
			$sezon,
			$ce,
			$club->name,
			$bilant['meciuri'],
			1 === $bilant['meciuri'] ? 'meci' : 'de meciuri',
			$bilant['v'],
			1 === $bilant['v'] ? 'victorie' : 'victorii',
			$bilant['e'],
			1 === $bilant['e'] ? 'egal' : 'egaluri',
			$bilant['i'],
			1 === $bilant['i'] ? 'înfrângere' : 'înfrângeri',
			null === $bilant['gm'] ? '—' : $bilant['gm'],
			null === $bilant['gp'] ? '—' : $bilant['gp'],
			$bilant['loc'],
			$bilant['pct'],
			1 === $bilant['pct'] ? 'punct' : 'de puncte'
		);
	}
	if ( ! empty( $profil['stadion']['nume'] ) ) {
		$txt[] = sprintf( 'Echipa joacă acasă pe %s%s.', $profil['stadion']['nume'], ! empty( $profil['oras'] ) ? ', în ' . $profil['oras'] : '' );
	}
	$txt[] = sprintf( 'Pe această pagină găsești rezultatele și programul meciurilor %1$s, clasamentul la zi, ultimele știri și cronici despre echipă%2$s.', $club->name, ! empty( $profil['lot'] ) ? ', antrenorul și lotul' : '' );
	return implode( ' ', $txt );
}

/**
 * GET /wp-json/fr/v1/cluburi – lista cluburilor (pentru rutina care le completează profilurile).
 */
add_action( 'rest_api_init', function () {
	register_rest_route( 'fr/v1', '/cluburi', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function () {
			$out = array();
			foreach ( get_terms( array( 'taxonomy' => 'club', 'hide_empty' => false ) ) as $c ) {
				$cid   = (int) get_term_meta( $c->term_id, 'fr_competitie', true );
				$cmp   = $cid ? get_term( $cid, 'competitie' ) : null;
				$out[] = array(
					'slug'       => $c->slug,
					'nume'       => $c->name,
					'competitie' => $cmp instanceof WP_Term ? $cmp->slug : '',
					'url'        => get_term_link( $c ),
				);
			}
			return $out;
		},
	) );
	// POST /wp-json/fr/v1/cluburi – sincronizarea acum (cheia X-Fr-Key, ca la actualizarea temei).
	register_rest_route( 'fr/v1', '/cluburi', array(
		'methods'             => 'POST',
		'permission_callback' => function ( $req ) {
			$cheie = (string) $req->get_header( 'x_fr_key' );
			return current_user_can( 'manage_options' ) || ( '' !== $cheie && hash_equals( fr_cheie_actualizare(), $cheie ) );
		},
		'callback'            => function () {
			// Profilurile se recitesc din depozit la următoarea afișare (de exemplu după un lot de profiluri noi).
			foreach ( get_terms( array( 'taxonomy' => 'club', 'hide_empty' => false, 'fields' => 'ids' ) ) as $cid ) {
				delete_transient( 'fr_profil_' . $cid );
			}
			$create = fr_sincronizeaza_cluburi( 25 );
			$total  = 0;
			$cu     = 0;
			foreach ( get_terms( array( 'taxonomy' => 'club', 'hide_empty' => false ) ) as $c ) {
				$total++;
				$cu += get_term_meta( $c->term_id, 'fr_sigla', true ) ? 1 : 0;
			}
			return array( 'create' => $create, 'cluburi' => $total, 'cu_stema' => $cu, 'ultima_eroare' => get_option( 'fr_sigla_eroare', '' ) );
		},
	) );
} );
