<?php
/**
 * SuperScore (partenerul oficial al Ligii 3): programul complet, scorurile (și live), clasamentele și stemele.
 *
 * O competiție de pe site se leagă de SuperScore prin câmpul „Sursa SuperScore” (fr_superscore):
 *   liga-3/i5ry2e11#4          Liga 3, grupa (seria) 4
 *   romania-cup/nm72ecss       Cupa României
 *   super-cup/pjxt3pn6         Supercupa
 *   women-superliga/gi7btryj   Superliga feminină
 * Sezonul curent se află singur din pagina competiției (o dată pe zi). Datele vin din fluxul public folosit
 * de superscore.live și se reîmprospătează la 10 minute, ca LiveScore.
 *
 * @package fotbalromanesc
 */

defined( 'ABSPATH' ) || exit;

const FR_SS_PAGINA  = 'https://superscore.live/ro-RO/fotbal/competitii/';
const FR_SS_FLUX    = 'https://scorealarm-stats.freetls.fastly.net/v2/soccer/';
const FR_SS_STEME   = 'https://superscore.live/bucket/media/v2/team/original/';

// Numele din SuperScore care nu se potrivesc singure cu cele de pe site.
const FR_SS_ALIAS = array(
	'U Cluj'             => 'Universitatea Cluj',
	'Cetate Suceava'     => 'Cetatea Suceava',
	'Omega Bucuresti'    => 'ACS Omega Sport',
	'ACS Unirea Sintana' => 'Unirea Sântana',
	'Poli Timisoara II'  => 'Politehnica Timișoara 2',
	'Nera Bogodinti'     => 'Nera Bogodinț',
	'Sepsi Sfantu Gheorghe' => 'Sepsi OSK',
);

/**
 * Competițiile legate implicit (create la activare sau la actualizarea temei, dacă lipsesc).
 */
function fr_ss_implicite() {
	$l = array();
	for ( $i = 1; $i <= 8; $i++ ) {
		$l[ 'liga-3-seria-' . $i ] = 'liga-3/i5ry2e11#' . $i;
	}
	$l['cupa-romaniei']      = 'romania-cup/nm72ecss';
	$l['supercupa']          = 'super-cup/pjxt3pn6';
	$l['superliga-feminina'] = 'women-superliga/gi7btryj';
	return $l;
}

add_action( 'init', function () {
	if ( get_option( 'fr_ss_legat' ) === FR_VERSIUNE ) {
		return;
	}
	$nume = array( 'cupa-romaniei' => 'Cupa României', 'supercupa' => 'Supercupa României', 'superliga-feminina' => 'Superliga feminină' );
	foreach ( fr_ss_implicite() as $slug => $sursa ) {
		$t = get_term_by( 'slug', $slug, 'competitie' );
		if ( ! $t && isset( $nume[ $slug ] ) ) {
			$r = wp_insert_term( $nume[ $slug ], 'competitie', array( 'slug' => $slug ) );
			$t = is_wp_error( $r ) ? null : get_term( $r['term_id'], 'competitie' );
			if ( $t ) {
				update_term_meta( $t->term_id, 'fr_ordine', array( 'cupa-romaniei' => 5, 'supercupa' => 6, 'superliga-feminina' => 7 )[ $slug ] );
			}
		}
		if ( $t instanceof WP_Term && ! get_term_meta( $t->term_id, 'fr_superscore', true ) ) {
			update_term_meta( $t->term_id, 'fr_superscore', $sursa );
		}
	}
	update_option( 'fr_ss_legat', FR_VERSIUNE, true );
}, 30 );

function fr_ss_cere( $url ) {
	$r = wp_remote_get( $url, array( 'timeout' => 25, 'headers' => array( 'User-Agent' => 'Mozilla/5.0 (compatible; fotbalromanesc.ro)' ) ) );
	if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
		return null;
	}
	return wp_remote_retrieve_body( $r );
}

/**
 * Sezonul curent al unei competiții („liga-3/i5ry2e11”), din pagina ei; păstrat o zi.
 */
function fr_ss_sezon( $cale ) {
	$cheie = 'fr_ss_sezon_' . md5( $cale );
	$s     = get_transient( $cheie );
	if ( $s ) {
		return $s;
	}
	$h = fr_ss_cere( FR_SS_PAGINA . $cale );
	if ( $h && preg_match( '/[?&]season=([A-Za-z0-9]{10,40})/', $h, $m ) ) {
		set_transient( $cheie, $m[1], DAY_IN_SECONDS );
		update_option( 'fr_ss_sezon_ultim_' . md5( $cale ), $m[1], false );
		return $m[1];
	}
	return get_option( 'fr_ss_sezon_ultim_' . md5( $cale ) ) ?: '';
}

/**
 * Fluxul unui sezon (meciuri și clasamente), păstrat 9 minute: cele 8 serii ale Ligii 3 folosesc aceeași cerere.
 */
function fr_ss_flux( $sezon, $ce ) {
	$cheie = 'fr_ss_' . $ce . '_' . $sezon;
	$d     = get_transient( $cheie );
	if ( is_array( $d ) ) {
		return $d;
	}
	$url = 'meciuri' === $ce
		? FR_SS_FLUX . 'fixtures/season/rosuperscore/ro-RO?season-id=' . rawurlencode( $sezon ) . '&with-upcoming=yes'
		: FR_SS_FLUX . 'competitions/standings/season/rosuperscore/ro-RO?season-id=' . rawurlencode( $sezon );
	$j = json_decode( (string) fr_ss_cere( $url ), true );
	if ( ! is_array( $j ) ) {
		return null;
	}
	set_transient( $cheie, $j, 9 * MINUTE_IN_SECONDS );
	return $j;
}

/**
 * Numele de pe site al unei echipe din SuperScore (fără diacritice, „II” pentru echipele secunde).
 * Caută întâi printre numele date (ex. cluburile seriei), apoi printre toate cluburile.
 */
function fr_ss_nume( $brut, $preferate = array(), $global = true ) {
	static $cache = array();
	$ck = ( $global ? 'g' : 'l' ) . $brut;
	if ( isset( $cache[ $ck ] ) ) {
		return $cache[ $ck ];
	}
	$n = FR_SS_ALIAS[ $brut ] ?? trim( preg_replace( '/\bII\b/', '2', $brut ) );
	if ( $preferate ) {
		$obiecte = array_map( fn( $x ) => (object) array( 'name' => $x, 'term_id' => 0 ), $preferate );
		$g       = fr_gaseste_club( $n, $obiecte );
		if ( $g ) {
			return $cache[ $ck ] = $g->name;
		}
	}
	$c = $global ? fr_gaseste_club( $n ) : null;
	return $cache[ $ck ] = $c ? $c->name : $n;
}

/**
 * Datele unei competiții SuperScore în forma standard (clasament, meciuri, sigle).
 *
 * @param string $sursa    „liga-3/i5ry2e11#4”
 * @param array  $preferate Numele echipelor deja cunoscute (pentru potrivirea numelor).
 */
function fr_date_superscore( $sursa, $preferate = array() ) {
	$parti = explode( '#', trim( (string) $sursa ) );
	$cale  = trim( $parti[0], '/' );
	$grupa = isset( $parti[1] ) ? (int) $parti[1] : 0;
	$sezon = $cale ? fr_ss_sezon( $cale ) : '';
	// Fotbalul feminin are aceleași nume de cluburi: nu le legăm de paginile cluburilor masculine.
	$global = false === strpos( $cale, 'women' );
	if ( ! $sezon ) {
		return null;
	}
	$fl = fr_ss_flux( $sezon, 'meciuri' );
	$cl = fr_ss_flux( $sezon, 'clasament' );
	if ( ! $fl || empty( $fl['events'] ) ) {
		return null;
	}

	// Grupa N = al N-lea grup din clasament („Group 4”); id-ul lui leagă meciurile (table_id).
	$tabel   = '';
	$randuri = array();
	$grupuri = (array) ( $cl['standings_groups'] ?? array() );
	if ( ! $grupa && 1 === count( $grupuri ) ) {
		$randuri = (array) ( $grupuri[0]['rows_total'] ?? array() ); // campionat cu un singur clasament
	}
	foreach ( $grupa ? $grupuri : array() as $g ) {
		if ( $grupa && ! preg_match( '/Group\s+' . $grupa . '\b/i', (string) ( $g['name'] ?? '' ) ) ) {
			continue;
		}
		$tabel   = (string) ( $g['id'] ?? '' );
		$randuri = (array) ( $g['rows_total'] ?? array() );
		break;
	}
	if ( $grupa && ! $tabel ) {
		return null;
	}

	$sigle     = array();
	$clasament = array();
	if ( $randuri ) {
		foreach ( $randuri as $r ) {
			$v   = array();
			foreach ( (array) ( $r['data'] ?? array() ) as $bloc ) {
				foreach ( (array) ( $bloc['values'] ?? array() ) as $x ) {
					$v[] = $x['value']['display_value'] ?? ( $x['value']['draw_value'] ?? '' );
				}
			}
			// [meciuri, „gm:gp”, pct, v, e, i, formă]
			$gol  = explode( ':', (string) ( $v[1] ?? '' ) );
			$nume = fr_ss_nume( (string) ( $r['competitor_name'] ?? '' ), $preferate, $global );
			$clasament[] = array(
				'loc'     => (int) ( $r['rank'] ?? 0 ),
				'echipa'  => $nume,
				'meciuri' => (int) ( $v[0] ?? 0 ),
				'v'       => isset( $v[3] ) ? (int) $v[3] : null,
				'e'       => isset( $v[4] ) ? (int) $v[4] : null,
				'i'       => isset( $v[5] ) ? (int) $v[5] : null,
				'gm'      => 2 === count( $gol ) ? (int) $gol[0] : null,
				'gp'      => 2 === count( $gol ) ? (int) $gol[1] : null,
				'pct'     => (int) ( $v[2] ?? 0 ),
			);
			if ( ! empty( $r['competitor_id'] ) ) {
				$sigle[ $nume ] = FR_SS_STEME . $r['competitor_id'] . '.webp';
			}
		}
	}

	$meciuri = array();
	foreach ( $fl['events'] as $e ) {
		if ( $tabel && ( $e['table_id']['value'] ?? '' ) !== $tabel ) {
			continue;
		}
		$ts = (int) ( $e['match_date']['seconds'] ?? 0 );
		if ( ! $ts || empty( $e['team1']['name'] ) || empty( $e['team2']['name'] ) ) {
			continue;
		}
		$g = fr_ss_nume( $e['team1']['name'], $preferate, $global );
		$o = fr_ss_nume( $e['team2']['name'], $preferate, $global );
		foreach ( array( array( $g, 'team1' ), array( $o, 'team2' ) ) as $p ) {
			if ( ! isset( $sigle[ $p[0] ] ) && ! empty( $e[ $p[1] ]['id'] ) ) {
				$sigle[ $p[0] ] = FR_SS_STEME . $e[ $p[1] ]['id'] . '.webp';
			}
		}
		$scor = null;
		foreach ( (array) ( $e['scores'] ?? array() ) as $s ) {
			if ( 0 === (int) ( $s['type'] ?? -1 ) ) {
				$scor = array( (int) ( $s['team1'] ?? 0 ), (int) ( $s['team2'] ?? 0 ) );
			}
		}
		$stare_ss = (int) ( $e['match_state'] ?? 0 );
		if ( 2 === $stare_ss && $scor ) {
			$stare = 'terminat';
		} elseif ( 1 === $stare_ss ) {
			$stare = 'live';
		} else {
			$stare = fr_stare_json( '', null, $ts );
			$scor  = null;
		}
		$runda = (string) ( $e['week']['value'] ?? ( $e['round']['value'] ?? '' ) );
		$meciuri[] = array(
			'id'      => 'ss-' . (string) ( $e['id'] ?? md5( $ts . $g . $o ) ),
			'data'    => gmdate( 'c', $ts ),
			'runda'   => $runda,
			'gazde'   => $g,
			'oaspeti' => $o,
			'scor'    => $scor,
			'stare'   => $stare,
			'minut'   => 'live' === $stare && ! empty( $e['live_minute']['value'] ) ? $e['live_minute']['value'] . "'" : '',
		);
	}
	usort( $meciuri, fn( $a, $b ) => strcmp( $a['data'], $b['data'] ) );
	return array( 'clasament' => $clasament, 'meciuri' => $meciuri, 'sursa' => 'SuperScore', 'sigle' => $sigle, 'fara_cluburi' => ! $global );
}

/**
 * Marcatorii din fișierul nostru (scriși de rutina de pe Mac) se adaugă la meciurile SuperScore.
 */
function fr_ss_cu_marcatori( $date, $json ) {
	if ( ! $json || empty( $json['meciuri'] ) ) {
		return $date;
	}
	$k   = fn( $m ) => substr( (string) $m['data'], 0, 10 ) . '|' . implode( ' ', fr_cuvinte_club( $m['gazde'] ) );
	$idx = array();
	foreach ( $json['meciuri'] as $m ) {
		if ( ! empty( $m['marcatori'] ) ) {
			$idx[ $k( $m ) ] = $m['marcatori'];
		}
	}
	foreach ( $date['meciuri'] as &$m ) {
		$m['data_ro'] = $m['data'];
		$cheie        = substr( wp_date( 'Y-m-d', strtotime( $m['data'] ) ), 0, 10 ) . '|' . implode( ' ', fr_cuvinte_club( $m['gazde'] ) );
		if ( isset( $idx[ $cheie ] ) ) {
			$m['marcatori'] = $idx[ $cheie ];
		}
		unset( $m['data_ro'] );
	}
	unset( $m );
	return $date;
}

/* ---------- Loturile echipelor (SuperScore) ---------- */

const FR_SS_LOTURI = array( 'superliga/15c6unv0', 'liga-2/m8gsqjyi', 'liga-3/i5ry2e11' );

/**
 * Harta nume club de pe site → id echipă SuperScore, din clasamentele SuperLigii, Ligii 2 și Ligii 3. Refăcută zilnic.
 */
function fr_ss_harta_echipe( $proaspat = false ) {
	$h = get_option( 'fr_ss_echipe' );
	if ( ! $proaspat && is_array( $h ) && $h ) {
		return $h;
	}
	$nou = is_array( $h ) ? $h : array();
	foreach ( FR_SS_LOTURI as $cale ) {
		$sezon = fr_ss_sezon( $cale );
		$cl    = $sezon ? fr_ss_flux( $sezon, 'clasament' ) : null;
		foreach ( (array) ( $cl['standings_groups'] ?? array() ) as $g ) {
			foreach ( (array) ( $g['rows_total'] ?? array() ) as $r ) {
				if ( empty( $r['competitor_id'] ) || empty( $r['competitor_name'] ) ) {
					continue;
				}
				$c = fr_gaseste_club( FR_SS_ALIAS[ $r['competitor_name'] ] ?? preg_replace( '/\bII\b/', '2', $r['competitor_name'] ) );
				if ( $c ) {
					$nou[ $c->name ] = $r['competitor_id'];
				}
			}
		}
	}
	if ( $nou ) {
		update_option( 'fr_ss_echipe', $nou, false );
	}
	return $nou;
}
add_action( 'fr_sincronizare_cluburi', function () {
	fr_ss_harta_echipe( true );
}, 20 );

function fr_ss_tara( $cod ) {
	$t = array(
		'ROU' => 'România', 'MDA' => 'Republica Moldova', 'CMR' => 'Camerun', 'POR' => 'Portugalia', 'BRA' => 'Brazilia',
		'ESP' => 'Spania', 'FRA' => 'Franța', 'ITA' => 'Italia', 'SRB' => 'Serbia', 'CRO' => 'Croația', 'BUL' => 'Bulgaria',
		'HUN' => 'Ungaria', 'NGA' => 'Nigeria', 'GHA' => 'Ghana', 'SEN' => 'Senegal', 'CIV' => 'Coasta de Fildeș',
		'ARG' => 'Argentina', 'COL' => 'Columbia', 'NED' => 'Olanda', 'BEL' => 'Belgia', 'GER' => 'Germania', 'ALB' => 'Albania',
		'MKD' => 'Macedonia de Nord', 'BIH' => 'Bosnia și Herțegovina', 'MNE' => 'Muntenegru', 'SVN' => 'Slovenia',
		'SVK' => 'Slovacia', 'CZE' => 'Cehia', 'POL' => 'Polonia', 'UKR' => 'Ucraina', 'GRE' => 'Grecia', 'CYP' => 'Cipru',
		'TUR' => 'Turcia', 'ISR' => 'Israel', 'GUI' => 'Guineea', 'MLI' => 'Mali', 'CGO' => 'Congo', 'COD' => 'RD Congo',
		'GAB' => 'Gabon', 'TOG' => 'Togo', 'BFA' => 'Burkina Faso', 'ANG' => 'Angola', 'CPV' => 'Capul Verde',
		'GNB' => 'Guineea-Bissau', 'ALG' => 'Algeria', 'MAR' => 'Maroc', 'TUN' => 'Tunisia', 'EGY' => 'Egipt',
		'URU' => 'Uruguay', 'VEN' => 'Venezuela', 'PAR' => 'Paraguay', 'CHI' => 'Chile', 'PER' => 'Peru', 'ECU' => 'Ecuador',
		'USA' => 'SUA', 'JPN' => 'Japonia', 'KOR' => 'Coreea de Sud', 'AUT' => 'Austria', 'SUI' => 'Elveția', 'ENG' => 'Anglia',
		'SCO' => 'Scoția', 'IRL' => 'Irlanda', 'KOS' => 'Kosovo', 'GEO' => 'Georgia', 'ARM' => 'Armenia', 'AZE' => 'Azerbaidjan',
		'LTU' => 'Lituania', 'LVA' => 'Letonia', 'EST' => 'Estonia', 'FIN' => 'Finlanda', 'SWE' => 'Suedia', 'NOR' => 'Norvegia',
		'DEN' => 'Danemarca', 'ISL' => 'Islanda', 'BLR' => 'Belarus', 'RUS' => 'Rusia', 'KAZ' => 'Kazahstan', 'MEX' => 'Mexic',
		'CAN' => 'Canada', 'AUS' => 'Australia', 'GAM' => 'Gambia', 'SLE' => 'Sierra Leone', 'LBR' => 'Liberia', 'BEN' => 'Benin',
		'NIG' => 'Niger', 'ZAM' => 'Zambia', 'ZIM' => 'Zimbabwe', 'RSA' => 'Africa de Sud', 'KEN' => 'Kenya', 'CUW' => 'Curaçao',
		'SUR' => 'Surinam', 'HAI' => 'Haiti', 'JAM' => 'Jamaica', 'CRC' => 'Costa Rica', 'PAN' => 'Panama', 'HON' => 'Honduras',
		'MTN' => 'Mauritania', 'COM' => 'Comore', 'MAD' => 'Madagascar', 'BDI' => 'Burundi', 'RWA' => 'Rwanda', 'UGA' => 'Uganda',
		'TAN' => 'Tanzania', 'ETH' => 'Etiopia', 'SDN' => 'Sudan', 'LBY' => 'Libia', 'IRN' => 'Iran', 'IRQ' => 'Irak',
		'SYR' => 'Siria', 'LBN' => 'Liban', 'JOR' => 'Iordania', 'KSA' => 'Arabia Saudită', 'QAT' => 'Qatar', 'UAE' => 'Emiratele Arabe Unite',
		'CHN' => 'China', 'NZL' => 'Noua Zeelandă', 'LUX' => 'Luxemburg', 'MLT' => 'Malta', 'AND' => 'Andorra', 'WAL' => 'Țara Galilor', 'NIR' => 'Irlanda de Nord',
	);
	return $t[ $cod ] ?? $cod;
}

/**
 * Lotul unui club din SuperScore, în forma profilului: [ 'lot' => [...], 'antrenor' => [...] ]. 24 de ore în cache.
 */
function fr_ss_lot( $club ) {
	$harta = fr_ss_harta_echipe();
	$id    = $harta[ $club->name ] ?? '';
	if ( ! $id ) {
		return null;
	}
	$cheie = 'fr_ss_lot_' . $id;
	$rez   = get_transient( $cheie );
	if ( is_array( $rez ) ) {
		return $rez ?: null;
	}
	$j = json_decode( (string) fr_ss_cere( FR_SS_FLUX . 'teams/squad/rosuperscore/ro-RO?team-id=' . rawurlencode( $id ) ), true );
	if ( ! is_array( $j ) ) {
		$vechi = get_option( 'fr_ss_lot_ultim_' . $id );
		set_transient( $cheie, is_array( $vechi ) ? $vechi : array(), 2 * HOUR_IN_SECONDS );
		return is_array( $vechi ) ? $vechi : null;
	}
	$nume = function ( $p ) {
		foreach ( (array) ( $p['names'] ?? array() ) as $n ) {
			if ( 1 === (int) ( $n['type'] ?? 0 ) && ! empty( $n['value']['string_value'] ) ) {
				return $n['value']['string_value'];
			}
		}
		$n = (string) ( $p['name'] ?? '' );
		return str_contains( $n, ', ' ) ? implode( ' ', array_reverse( explode( ', ', $n, 2 ) ) ) : $n;
	};
	$lot = array();
	foreach ( array( 'goalkeepers' => 'portar', 'defenders' => 'fundas', 'midfielders' => 'mijlocas', 'attackers' => 'atacant' ) as $k => $post ) {
		foreach ( (array) ( $j[ $k ] ?? array() ) as $x ) {
			$p = $x['player'] ?? array();
			$n = $nume( $p );
			if ( '' === $n ) {
				continue;
			}
			$jucator = array( 'nume' => $n, 'post' => $post );
			if ( is_numeric( $x['shirt_number'] ?? '' ) ) {
				$jucator['nr'] = (int) $x['shirt_number'];
			}
			if ( ! empty( $p['birth_date']['value'] ) ) {
				$jucator['varsta'] = ( new DateTime( $p['birth_date']['value'] ) )->diff( new DateTime() )->y;
			}
			if ( ! empty( $p['country_code']['value'] ) ) {
				$jucator['nationalitate'] = fr_ss_tara( $p['country_code']['value'] );
			}
			$lot[] = $jucator;
		}
	}
	$rez = array( 'lot' => $lot );
	if ( ! empty( $j['manager'] ) ) {
		$rez['antrenor'] = array_filter( array(
			'nume'          => $nume( $j['manager'] ),
			'nationalitate' => ! empty( $j['manager']['country_code']['value'] ) ? fr_ss_tara( $j['manager']['country_code']['value'] ) : '',
		) );
	}
	if ( ! $lot ) {
		$rez = array();
	}
	update_option( 'fr_ss_lot_ultim_' . $id, $rez, false );
	set_transient( $cheie, $rez, DAY_IN_SECONDS );
	return $rez ?: null;
}

/**
 * Profilul complet al clubului: lotul vine din SuperScore (la zi); antrenorul din profilul nostru verificat,
 * altfel din SuperScore; restul (stadion, oraș, fondare) din profilul nostru.
 */
add_filter( 'fr_profil_club', function ( $profil, $club ) {
	$ss = fr_ss_lot( $club );
	if ( ! $ss ) {
		return $profil;
	}
	$profil = is_array( $profil ) ? $profil : array();
	if ( ! empty( $ss['lot'] ) ) {
		$profil['lot']      = $ss['lot'];
		$profil['lot_sursa'] = 'SuperScore';
	}
	if ( empty( $profil['antrenor']['nume'] ) && ! empty( $ss['antrenor']['nume'] ) ) {
		$profil['antrenor'] = $ss['antrenor'];
	}
	return $profil;
}, 10, 2 );
