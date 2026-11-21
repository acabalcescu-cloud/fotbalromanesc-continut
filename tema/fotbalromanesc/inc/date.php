<?php
/**
 * Datele competițiilor: clasament, meciuri, formă și marcatori.
 *
 * Două surse, alese pe competiție (Articole › Competiții):
 *  - LiveScore (fluxul public folosit și de Poveștile Științei): „Sursa LiveScore” = romania/liga-1, romania/liga-ii…
 *  - un fișier JSON (pentru Liga 3, scris de rutina automată): „Fișier JSON cu date”.
 *
 * Paginile nu așteaptă după surse: un cron reîmprospătează datele la 10 minute, iar șabloanele citesc ultima
 * copie bună. Doar prima afișare, fără nicio copie, cere datele pe loc.
 *
 * Forma standard (și formatul fișierului JSON):
 * {
 *   "clasament": [ { "loc": 1, "echipa": "…", "meciuri": 13, "v": 10, "e": 0, "i": 3, "gm": 34, "gp": 15, "pct": 30 } ],
 *   "meciuri":   [ { "id": "…", "data": "2026-11-23T17:00:00+02:00", "runda": "14", "gazde": "…", "oaspeti": "…",
 *                    "scor": [4, 1] sau null, "stare": "terminat" | "programat" | "live" | "amanat" } ]
 * }
 *
 * @package fotbalromanesc
 */

defined( 'ABSPATH' ) || exit;

const FR_LIVESCORE = 'https://prod-public-api.livescore.com/v1/api/app';

add_filter( 'cron_schedules', 'fr_intervale_cron' );
function fr_intervale_cron( $s ) {
	$s['fr_10m'] = array( 'interval' => 10 * MINUTE_IN_SECONDS, 'display' => 'La 10 minute' );
	$s['fr_15m'] = array( 'interval' => 15 * MINUTE_IN_SECONDS, 'display' => 'La 15 minute' );
	return $s;
}

add_action( 'init', 'fr_programeaza_date' );
function fr_programeaza_date() {
	if ( ! wp_next_scheduled( 'fr_reimprospateaza_date' ) ) {
		wp_schedule_event( time() + 30, 'fr_10m', 'fr_reimprospateaza_date' );
	}
}

add_action( 'switch_theme', 'fr_opreste_cron' );
function fr_opreste_cron() {
	wp_clear_scheduled_hook( 'fr_reimprospateaza_date' );
	wp_clear_scheduled_hook( 'fr_import_continut' );
}

add_action( 'fr_reimprospateaza_date', 'fr_reimprospateaza_toate' );
function fr_reimprospateaza_toate() {
	fr_actualizeaza_lista_date_repo();
	fr_scaneaza_nationale();
	foreach ( fr_competitii_cu_date() as $t ) {
		fr_incarca_date( $t->term_id );
	}
}

/**
 * Naționalele joacă în competiții diferite (preliminarii, Liga Națiunilor, amicale), iar LiveScore nu are o
 * pagină pe echipă. Așa că citim zilele din calendar și păstrăm meciurile echipelor urmărite
 * („Echipa în LiveScore” pe competiție, ex. „Romania U21”).
 * La fiecare rulare: ieri, azi, mâine și încă 4 zile care se rotesc prin intervalul -60…+180 de zile,
 * deci tot sezonul e acoperit în aproximativ o zi, cu doar 7 cereri pe rulare.
 */
function fr_scaneaza_nationale() {
	$urmarite = array();
	$termeni  = get_terms( array( 'taxonomy' => 'competitie', 'hide_empty' => false, 'meta_key' => 'fr_echipa_livescore' ) );
	foreach ( is_wp_error( $termeni ) ? array() : $termeni as $t ) {
		$nume = trim( (string) get_term_meta( $t->term_id, 'fr_echipa_livescore', true ) );
		if ( $nume ) {
			$urmarite[ $nume ] = $t->term_id;
		}
	}
	if ( ! $urmarite ) {
		return;
	}
	$cursor = (int) get_option( 'fr_nat_cursor', -60 );
	$zile   = array( -1, 0, 1 );
	for ( $i = 0; $i < 4; $i++ ) {
		$zile[] = $cursor;
		$cursor = $cursor >= 180 ? -60 : $cursor + 1;
	}
	update_option( 'fr_nat_cursor', $cursor, false );

	$tz       = new DateTimeZone( 'Europe/Bucharest' );
	$colectie = array();
	foreach ( array_unique( $zile ) as $z ) {
		$data = ( new DateTime( 'now', $tz ) )->modify( ( $z >= 0 ? '+' : '' ) . $z . ' days' )->format( 'Ymd' );
		$d    = fr_cere_json( FR_LIVESCORE . '/date/soccer/' . $data . '/0?MD=1' );
		foreach ( $d['Stages'] ?? array() as $st ) {
			foreach ( $st['Events'] ?? array() as $e ) {
				$g = $e['T1'][0]['Nm'] ?? '';
				$o = $e['T2'][0]['Nm'] ?? '';
				foreach ( array( $g, $o ) as $nume ) {
					if ( isset( $urmarite[ $nume ] ) ) {
						$ts    = fr_ts_livescore( $e['Esd'] ?? '' );
						$stare = fr_stare_livescore( (string) ( $e['Eps'] ?? '' ) );
						$colectie[ $urmarite[ $nume ] ][ (string) $e['Eid'] ] = array(
							'id'      => (string) $e['Eid'],
							'data'    => gmdate( 'c', $ts ),
							'runda'   => (string) ( $st['Cnm'] ?? '' ),
							'gazde'   => $g,
							'oaspeti' => $o,
							'scor'    => ( in_array( $stare, array( 'terminat', 'live' ), true ) && isset( $e['Tr1'] ) && '' !== $e['Tr1'] ) ? array( (int) $e['Tr1'], (int) $e['Tr2'] ) : null,
							'stare'   => $stare,
							'minut'   => 'live' === $stare ? (string) $e['Eps'] : '',
						);
					}
				}
			}
		}
	}
	$limita = gmdate( 'c', time() - 365 * DAY_IN_SECONDS );
	foreach ( $colectie as $term_id => $noi ) {
		$toate = array_merge( (array) get_option( 'fr_nat_' . $term_id, array() ), $noi );
		$toate = array_filter( $toate, fn( $m ) => $m['data'] >= $limita );
		update_option( 'fr_nat_' . $term_id, $toate, false );
	}
}

/**
 * Competițiile care au o sursă de date.
 */
function fr_competitii_cu_date( $doar_prima = false ) {
	$termeni = get_terms( array( 'taxonomy' => 'competitie', 'hide_empty' => false ) );
	if ( is_wp_error( $termeni ) ) {
		return array();
	}
	$rez = array();
	foreach ( $termeni as $t ) {
		$are = get_term_meta( $t->term_id, 'fr_livescore', true ) || get_term_meta( $t->term_id, 'fr_echipa_livescore', true ) || get_term_meta( $t->term_id, 'fr_superscore', true ) || fr_sursa_json( $t );
		if ( $are && ( ! $doar_prima || get_term_meta( $t->term_id, 'fr_pe_prima', true ) ) ) {
			$rez[] = $t;
		}
	}
	usort( $rez, fn( $a, $b ) => fr_cheie_ordine( $a ) <=> fr_cheie_ordine( $b ) );
	return $rez;
}

/**
 * Ordinea în liste: întâi ordinea competiției-părinte, apoi a seriei sau a echipei din ea.
 */
function fr_cheie_ordine( $t ) {
	$proprie = (int) get_term_meta( $t->term_id, 'fr_ordine', true );
	if ( ! $t->parent ) {
		return $proprie * 100;
	}
	return (int) get_term_meta( $t->parent, 'fr_ordine', true ) * 100 + $proprie;
}

/**
 * Datele unei competiții, din ultima copie bună (sau încărcate acum, dacă nu există).
 */
function fr_date_competitie( $term_id ) {
	$date = get_option( 'fr_date_' . (int) $term_id );
	if ( ! is_array( $date ) ) {
		$date = fr_incarca_date( $term_id );
	}
	return is_array( $date ) ? $date : null;
}

function fr_goleste_cache_date( $term_id ) {
	delete_option( 'fr_date_' . (int) $term_id );
}

/**
 * Cere datele de la sursă și, dacă sunt valide, le păstrează ca ultimă copie bună.
 */
function fr_incarca_date( $term_id ) {
	$ls   = get_term_meta( $term_id, 'fr_livescore', true );
	$json = fr_sursa_json( get_term( $term_id, 'competitie' ) );
	$nat  = get_term_meta( $term_id, 'fr_echipa_livescore', true );
	$ss   = get_term_meta( $term_id, 'fr_superscore', true );
	$date = null;
	if ( $ss ) {
		// SuperScore: program, scoruri, clasament; marcatorii vin din fișierul nostru, dacă există.
		$din_json  = $json ? fr_date_din_json( $json ) : null;
		$preferate = array_values( array_unique( array_merge(
			array_column( (array) ( $din_json['clasament'] ?? array() ), 'echipa' ),
			array_map( fn( $c ) => $c->name, (array) get_terms( array( 'taxonomy' => 'club', 'hide_empty' => false, 'meta_key' => 'fr_competitie', 'meta_value' => $term_id ) ) )
		) ) );
		$date = fr_date_superscore( $ss, $preferate );
		if ( $date ) {
			$date = fr_ss_cu_marcatori( $date, $din_json );
		} elseif ( $din_json ) {
			$date = $din_json;
		}
	} elseif ( $ls ) {
		$date = fr_date_livescore( $ls );
	} elseif ( $nat ) {
		$meciuri = array_values( (array) get_option( 'fr_nat_' . (int) $term_id, array() ) );
		if ( ! $meciuri && ! get_option( 'fr_nat_initial_' . (int) $term_id ) ) {
			// Prima afișare: o scanare imediată, ca pagina să nu fie goală până trece cronul.
			update_option( 'fr_nat_initial_' . (int) $term_id, 1, false );
			fr_scaneaza_nationale();
			$meciuri = array_values( (array) get_option( 'fr_nat_' . (int) $term_id, array() ) );
		}
		usort( $meciuri, fn( $a, $b ) => strcmp( $a['data'], $b['data'] ) );
		$date = array( 'clasament' => array(), 'meciuri' => $meciuri, 'sursa' => 'LiveScore' );
	} elseif ( $json ) {
		$date = fr_date_din_json( $json );
	}
	if ( is_array( $date ) && ( $date['clasament'] || $date['meciuri'] ) ) {
		$date['neoficial']  = (bool) get_term_meta( $term_id, 'fr_neoficial', true );
		$date['actualizat'] = time();
		update_option( 'fr_date_' . (int) $term_id, $date, false );
		return $date;
	}
	$vechi = get_option( 'fr_date_' . (int) $term_id );
	return is_array( $vechi ) ? $vechi : null;
}

/**
 * Fișierele de date din depozitul de conținut: date/index.json = { "fisiere": [ "liga-3-seria-1", … ] }.
 * O competiție al cărei slug apare acolo își ia datele din date/<slug>.json, fără nicio setare în admin.
 */
function fr_actualizeaza_lista_date_repo() {
	if ( ! fr_setare( 'repo' ) ) {
		delete_option( 'fr_date_repo' );
		return;
	}
	$idx = fr_repo_json( 'date/index.json' );
	if ( ! is_wp_error( $idx ) && isset( $idx['fisiere'] ) ) {
		update_option( 'fr_date_repo', array_values( array_map( 'sanitize_title', (array) $idx['fisiere'] ) ), false );
	}
}

/**
 * Adresa fișierului JSON al unei competiții: câmpul din admin sau, dacă lipsește, fișierul din depozit.
 */
function fr_sursa_json( $term ) {
	if ( ! $term instanceof WP_Term ) {
		return '';
	}
	$url = get_term_meta( $term->term_id, 'fr_date_json', true );
	if ( $url ) {
		return $url;
	}
	if ( fr_setare( 'repo' ) && in_array( $term->slug, (array) get_option( 'fr_date_repo', array() ), true ) ) {
		return fr_repo_url( 'date/' . $term->slug . '.json' );
	}
	return '';
}

function fr_cere_json( $url ) {
	$r = wp_remote_get( $url, array(
		'timeout' => 12,
		'headers' => array( 'Accept' => 'application/json', 'User-Agent' => 'Mozilla/5.0 (compatible; fotbalromanesc.ro)' ),
	) );
	if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
		return null;
	}
	$d = json_decode( wp_remote_retrieve_body( $r ), true );
	return is_array( $d ) ? $d : null;
}

/**
 * LiveScore: „romania/liga-ii” → clasament și meciurile sezonului.
 */
function fr_date_livescore( $cale ) {
	$cale = trim( preg_replace( '~[^a-z0-9/\-]~', '', strtolower( $cale ) ), '/' );
	if ( ! $cale ) {
		return null;
	}
	$d = fr_cere_json( FR_LIVESCORE . '/stage/soccer/' . $cale . '/0?MD=1' );
	if ( ! $d || empty( $d['Stages'][0] ) ) {
		return null;
	}
	$st = $d['Stages'][0];

	$clasament = array();
	foreach ( $st['LeagueTable']['L'][0]['Tables'][0]['team'] ?? array() as $r ) {
		$clasament[] = array(
			'loc'     => (int) ( $r['rnk'] ?? 0 ),
			'echipa'  => (string) ( $r['Tnm'] ?? '' ),
			'meciuri' => (int) ( $r['pld'] ?? 0 ),
			'v'       => (int) ( $r['win'] ?? 0 ),
			'e'       => (int) ( $r['drw'] ?? 0 ),
			'i'       => (int) ( $r['lst'] ?? 0 ),
			'gm'      => (int) ( $r['gf'] ?? 0 ),
			'gp'      => (int) ( $r['ga'] ?? 0 ),
			'pct'     => (int) ( $r['pts'] ?? 0 ),
		);
	}

	$meciuri = array();
	$sigle   = array();
	foreach ( $st['Events'] ?? array() as $e ) {
		foreach ( array( 'T1', 'T2' ) as $k ) {
			if ( ! empty( $e[ $k ][0]['Nm'] ) && ! empty( $e[ $k ][0]['Img'] ) ) {
				$sigle[ (string) $e[ $k ][0]['Nm'] ] = (string) $e[ $k ][0]['Img'];
			}
		}
		$ts = fr_ts_livescore( $e['Esd'] ?? '' );
		if ( ! $ts || empty( $e['T1'][0]['Nm'] ) || empty( $e['T2'][0]['Nm'] ) ) {
			continue;
		}
		$stare = fr_stare_livescore( (string) ( $e['Eps'] ?? '' ) );
		$scor  = ( 'programat' !== $stare && 'amanat' !== $stare && isset( $e['Tr1'], $e['Tr2'] ) && '' !== $e['Tr1'] ) ? array( (int) $e['Tr1'], (int) $e['Tr2'] ) : null;
		$meciuri[] = array(
			'id'      => (string) ( $e['Eid'] ?? '' ),
			'data'    => gmdate( 'c', $ts ),
			'runda'   => (string) ( $e['ErnInf'] ?? '' ),
			'gazde'   => (string) $e['T1'][0]['Nm'],
			'oaspeti' => (string) $e['T2'][0]['Nm'],
			'scor'    => $scor,
			'stare'   => $stare,
			'minut'   => 'live' === $stare ? (string) $e['Eps'] : '',
		);
	}
	usort( $meciuri, fn( $a, $b ) => strcmp( $a['data'], $b['data'] ) );
	return array( 'clasament' => $clasament, 'meciuri' => $meciuri, 'sursa' => 'LiveScore', 'sigle' => $sigle );
}

function fr_ts_livescore( $esd ) {
	$esd = preg_replace( '/\D/', '', (string) $esd );
	if ( strlen( $esd ) < 12 ) {
		return 0;
	}
	$dt = DateTime::createFromFormat( 'YmdHis', str_pad( substr( $esd, 0, 14 ), 14, '0' ), new DateTimeZone( 'UTC' ) );
	return $dt ? $dt->getTimestamp() : 0;
}

function fr_stare_livescore( $eps ) {
	// FT final, AET după prelungiri, AP după penalty-uri, AW/Awarded rezultat acordat la masa verde.
	if ( in_array( $eps, array( 'FT', 'AET', 'AP', 'FT Pen', 'Pen', 'AW', 'Awarded', 'Awd' ), true ) ) {
		return 'terminat';
	}
	if ( in_array( $eps, array( 'NS', '' ), true ) ) {
		return 'programat';
	}
	if ( preg_match( '/postp|canc|abd|susp|del|int|tba/i', $eps ) ) {
		return 'amanat';
	}
	// În desfășurare: un minut („45'”, „90+2'”) sau pauza/prelungirile.
	if ( preg_match( "/^\\d+(\\+\\d+)?'?$|^(HT|ET|BT|Pen\\.? ?Live|LIVE)$/i", $eps ) ) {
		return 'live';
	}
	return 'amanat';
}

/**
 * Un fișier JSON în forma standard (vezi capul fișierului).
 */
function fr_date_din_json( $url ) {
	$d = fr_cere_json( $url );
	if ( ! $d ) {
		return null;
	}
	$clasament = array();
	foreach ( (array) ( $d['clasament'] ?? array() ) as $i => $r ) {
		if ( empty( $r['echipa'] ) ) {
			continue;
		}
		$clasament[] = array(
			'loc'     => (int) ( $r['loc'] ?? $i + 1 ),
			'echipa'  => sanitize_text_field( $r['echipa'] ),
			'meciuri' => (int) ( $r['meciuri'] ?? 0 ),
			// Lipsă din sursă = necunoscut (null), nu 0.
			'v'       => isset( $r['v'] ) ? (int) $r['v'] : null,
			'e'       => isset( $r['e'] ) ? (int) $r['e'] : null,
			'i'       => isset( $r['i'] ) ? (int) $r['i'] : null,
			'gm'      => isset( $r['gm'] ) ? (int) $r['gm'] : null,
			'gp'      => isset( $r['gp'] ) ? (int) $r['gp'] : null,
			'pct'     => (int) ( $r['pct'] ?? 0 ),
		);
	}
	$meciuri = array();
	foreach ( (array) ( $d['meciuri'] ?? array() ) as $m ) {
		$ts = isset( $m['data'] ) ? strtotime( $m['data'] ) : 0;
		if ( ! $ts || empty( $m['gazde'] ) || empty( $m['oaspeti'] ) ) {
			continue;
		}
		$scor      = isset( $m['scor'] ) && is_array( $m['scor'] ) && 2 === count( $m['scor'] ) ? array( (int) $m['scor'][0], (int) $m['scor'][1] ) : null;
		$meciuri[] = array(
			'id'      => sanitize_text_field( $m['id'] ?? md5( $m['data'] . $m['gazde'] ) ),
			'data'    => gmdate( 'c', $ts ),
			'runda'   => sanitize_text_field( (string) ( $m['runda'] ?? '' ) ),
			'gazde'   => sanitize_text_field( $m['gazde'] ),
			'oaspeti' => sanitize_text_field( $m['oaspeti'] ),
			'scor'    => $scor,
			'stare'   => fr_stare_json( $m['stare'] ?? '', $scor, $ts ),
			'minut'   => '',
			'marcatori' => isset( $m['marcatori'] ) && is_array( $m['marcatori'] ) ? $m['marcatori'] : null,
		);
	}
	usort( $meciuri, fn( $a, $b ) => strcmp( $a['data'], $b['data'] ) );
	return array( 'clasament' => $clasament, 'meciuri' => $meciuri, 'sursa' => 'JSON' );
}

/**
 * Forma ultimelor $n meciuri pentru fiecare echipă: [ 'Echipa' => ['V','E','Î',…] ], cel mai vechi primul.
 */
function fr_forma( $date, $n = 5 ) {
	$forma = array();
	foreach ( $date['meciuri'] ?? array() as $m ) {
		if ( 'terminat' !== $m['stare'] || ! $m['scor'] ) {
			continue;
		}
		list( $g, $o ) = $m['scor'];
		$forma[ $m['gazde'] ][]   = $g > $o ? 'V' : ( $g === $o ? 'E' : 'Î' );
		$forma[ $m['oaspeti'] ][] = $o > $g ? 'V' : ( $g === $o ? 'E' : 'Î' );
	}
	return array_map( fn( $l ) => array_slice( $l, -$n ), $forma );
}

/**
 * Meciurile unei echipe (după numele din date).
 */
function fr_meciurile_echipei( $date, $echipa ) {
	return array_values( array_filter( $date['meciuri'] ?? array(), fn( $m ) => $m['gazde'] === $echipa || $m['oaspeti'] === $echipa ) );
}

/**
 * Meciurile dintr-un interval (timestamp-uri), din toate competițiile date.
 */
function fr_meciuri_interval( $termeni, $de_la, $pana_la ) {
	$rez = array();
	foreach ( $termeni as $t ) {
		$date = fr_date_competitie( $t->term_id );
		foreach ( $date['meciuri'] ?? array() as $m ) {
			$ts = strtotime( $m['data'] );
			if ( $ts >= $de_la && $ts <= $pana_la ) {
				$m['competitie'] = $t;
				$rez[]           = $m;
			}
		}
	}
	usort( $rez, fn( $a, $b ) => strcmp( $a['data'], $b['data'] ) );
	return $rez;
}

/**
 * Marcatorii unui meci terminat (LiveScore). Păstrați 30 de zile: un meci terminat nu se mai schimbă.
 * Întoarce [ 'gazde' => [ ['nume','minut','tip'] ], 'oaspeti' => [...] ] sau null.
 */
function fr_marcatori_meci( $meci ) {
	if ( ! empty( $meci['marcatori'] ) ) {
		return $meci['marcatori'];
	}
	if ( 'terminat' !== ( $meci['stare'] ?? '' ) || empty( $meci['id'] ) || ! ctype_digit( (string) $meci['id'] ) ) {
		return null;
	}
	$cheie = 'fr_marc_' . $meci['id'];
	$rez   = get_transient( $cheie );
	if ( is_array( $rez ) ) {
		return $rez;
	}
	$d = fr_cere_json( FR_LIVESCORE . '/incidents/soccer/' . $meci['id'] . '?locale=en' );
	if ( ! $d ) {
		return null;
	}
	$rez = array( 'gazde' => array(), 'oaspeti' => array() );
	foreach ( (array) ( $d['Incs'] ?? array() ) as $repriza ) {
		foreach ( (array) $repriza as $inc ) {
			$parti = ! empty( $inc['Incs'] ) && is_array( $inc['Incs'] ) ? $inc['Incs'] : array( $inc );
			foreach ( $parti as $p ) {
				$it = (int) ( $p['IT'] ?? 0 );
				if ( ! in_array( $it, array( 36, 37, 39 ), true ) || empty( $p['Pn'] ) ) {
					continue;
				}
				$parte          = 2 === (int) ( $p['Nm'] ?? $inc['Nm'] ?? 1 ) ? 'oaspeti' : 'gazde';
				$rez[ $parte ][] = array(
					'nume'  => sanitize_text_field( $p['Pn'] ),
					'minut' => (int) ( $p['Min'] ?? $inc['Min'] ?? 0 ) . ( ! empty( $p['MinEx'] ) ? '+' . (int) $p['MinEx'] : '' ),
					'tip'   => 37 === $it ? 'penalty' : ( 39 === $it ? 'autogol' : '' ),
				);
			}
		}
	}
	set_transient( $cheie, $rez, 30 * DAY_IN_SECONDS );
	return $rez;
}

/**
 * Golgheterii unei echipe în competiție, din marcatorii meciurilor terminate (fără autogoluri).
 * Calculat cel mult o dată la 6 ore; primele cereri pot dura câteva secunde.
 */
function fr_golgheteri_echipa( $term_id, $echipa, $max = 6 ) {
	$cheie = 'fr_golg_' . md5( $term_id . '|' . $echipa );
	$rez   = get_transient( $cheie );
	if ( is_array( $rez ) ) {
		return $rez;
	}
	$date = fr_date_competitie( $term_id );
	if ( ! $date ) {
		return array();
	}
	$numar = array();
	foreach ( fr_meciurile_echipei( $date, $echipa ) as $m ) {
		$marc = fr_marcatori_meci( $m );
		if ( ! $marc ) {
			continue;
		}
		foreach ( $marc[ $m['gazde'] === $echipa ? 'gazde' : 'oaspeti' ] as $g ) {
			if ( 'autogol' !== $g['tip'] ) {
				$numar[ $g['nume'] ] = ( $numar[ $g['nume'] ] ?? 0 ) + 1;
			}
		}
	}
	arsort( $numar );
	$rez = array_slice( $numar, 0, $max, true );
	set_transient( $cheie, $rez, 6 * HOUR_IN_SECONDS );
	return $rez;
}

/**
 * Data unui meci, în ora României: „Lun 23 nov · 17:00”.
 */
function fr_data_meci( $iso, $format = 'scurt' ) {
	$ts = strtotime( $iso );
	if ( ! $ts ) {
		return '';
	}
	$zile = array( 'Dum', 'Lun', 'Mar', 'Mie', 'Joi', 'Vin', 'Sâm' );
	$luni = array( 'ian', 'feb', 'mar', 'apr', 'mai', 'iun', 'iul', 'aug', 'sep', 'oct', 'nov', 'dec' );
	$dt   = new DateTime( '@' . $ts );
	$dt->setTimezone( new DateTimeZone( 'Europe/Bucharest' ) );
	if ( 'ora' === $format ) {
		return $dt->format( 'H:i' );
	}
	if ( 'data' === $format ) {
		return $zile[ (int) $dt->format( 'w' ) ] . ' ' . (int) $dt->format( 'j' ) . ' ' . $luni[ (int) $dt->format( 'n' ) - 1 ];
	}
	if ( 'zi' === $format ) {
		$zileLungi = array( 'Duminică', 'Luni', 'Marți', 'Miercuri', 'Joi', 'Vineri', 'Sâmbătă' );
		$luniLungi = array( 'ianuarie', 'februarie', 'martie', 'aprilie', 'mai', 'iunie', 'iulie', 'august', 'septembrie', 'octombrie', 'noiembrie', 'decembrie' );
		return $zileLungi[ (int) $dt->format( 'w' ) ] . ', ' . (int) $dt->format( 'j' ) . ' ' . $luniLungi[ (int) $dt->format( 'n' ) - 1 ];
	}
	return $zile[ (int) $dt->format( 'w' ) ] . ' ' . (int) $dt->format( 'j' ) . ' ' . $luni[ (int) $dt->format( 'n' ) - 1 ] . ' · ' . $dt->format( 'H:i' );
}

/**
 * GET /wp-json/fr/v1/date/<id-competitie> – datele, pentru aplicații și verificări.
 * POST (administratori) – reîncarcă acum.
 */
add_action( 'rest_api_init', 'fr_rute_date' );
function fr_rute_date() {
	register_rest_route( 'fr/v1', '/date/(?P<id>\d+)', array(
		array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => function ( $req ) {
				$d = fr_date_competitie( (int) $req['id'] );
				return $d ? $d : new WP_Error( 'fr_fara_date', 'Competiția nu are date.', array( 'status' => 404 ) );
			},
		),
		array(
			'methods'             => 'POST',
			'permission_callback' => fn() => current_user_can( 'manage_options' ),
			'callback'            => fn( $req ) => fr_incarca_date( (int) $req['id'] ) ?: new WP_Error( 'fr_fara_date', 'Sursa nu a răspuns.', array( 'status' => 502 ) ),
		),
	) );
}

/**
 * Starea unui meci din datele JSON (Liga 3). Un meci fără scor al cărui început a trecut de peste două ore
 * e „asteptare” (rezultatul nu e încă în date): nu mai stă la program, apare la rezultate cu „în curând”.
 */
function fr_stare_json( $stare, $scor, $ts ) {
	if ( in_array( $stare, array( 'terminat', 'live', 'amanat' ), true ) ) {
		return $stare;
	}
	if ( $scor ) {
		return 'terminat';
	}
	return ( $ts && $ts + 2 * HOUR_IN_SECONDS < time() ) ? 'asteptare' : 'programat';
}
