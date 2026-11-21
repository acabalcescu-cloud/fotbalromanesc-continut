<?php
/**
 * Numele echipelor, cu diacritice. LiveScore le dă fără diacritice („Chindia Targoviste”).
 * Un club creat în admin (Articole › Cluburi) cu „Numele în LiveScore” completat are întâietate.
 *
 * @package fotbalromanesc
 */

defined( 'ABSPATH' ) || exit;

function fr_nume_implicite() {
	return array(
		// Naționalele
		'Romania'                   => 'România',
		'Romania U21'               => 'România U21',
		'Romania U19'               => 'România U19',
		'Romania U17'               => 'România U17',
		// SuperLiga
		'CS Universitatea Craiova'  => 'Universitatea Craiova',
		'Dinamo Bucuresti'          => 'Dinamo București',
		'FC Rapid 1923'             => 'Rapid',
		'Petrolul Ploiesti'         => 'Petrolul Ploiești',
		'Universitatea Cluj'        => 'Universitatea Cluj',
		'FCV Farul Constanta'       => 'Farul Constanța',
		'Corvinul Hunedoara'        => 'Corvinul Hunedoara',
		'FCSB'                      => 'FCSB',
		'CFR Cluj'                  => 'CFR Cluj',
		'UTA Arad'                  => 'UTA Arad',
		'Sepsi OSK'                 => 'Sepsi OSK',
		'Botosani'                  => 'FC Botoșani',
		'ACS Champions FC Arges'    => 'FC Argeș',
		'Otelul Galati'             => 'Oțelul Galați',
		'FC Voluntari'              => 'FC Voluntari',
		'Miercurea Ciuc'            => 'Csíkszereda Miercurea Ciuc',
		// Liga 2
		'Chindia Targoviste'        => 'Chindia Târgoviște',
		'ASU Politehnica Timisoara' => 'Poli Timișoara',
		'CSM Slatina'               => 'CSM Slatina',
		'Popesti Leordeni'          => 'Popești-Leordeni',
		'Concordia Chiajna'         => 'Concordia Chiajna',
		'CSM Resita'                => 'CSM Reșița',
		'Metalul Buzau'             => 'Metalul Buzău',
		'Cetatea Suceava'           => 'Cetatea Suceava',
		'FC Metaloglobus Bucuresti' => 'Metaloglobus București',
		'Ramnicu Valcea'            => 'CSM Râmnicu Vâlcea',
		'FC Bacau'                  => 'FC Bacău',
		'FC Bihor Oradea'           => 'FC Bihor Oradea',
		'CS Afumati'                => 'CS Afumați',
		'CS Gloria Bistrita'        => 'Gloria Bistrița',
		'FC Unirea 2004 Slobozia'   => 'Unirea Slobozia',
		'Steaua Bucuresti'          => 'Steaua București',
		'ASA Targu Mures'           => 'ASA Târgu Mureș',
		'CSM Olimpia Satu Mare'     => 'Olimpia Satu Mare',
		'Comunal Selimbar'          => 'Comunal Șelimbăr',
		'CSL Stefanesti'            => 'CSL Ștefănești',
		'CS Dinamo Bucuresti'       => 'CS Dinamo București',
		'CSC Dumbravita'            => 'CSC Dumbrăvița',
	);
}

/**
 * Numele afișat pentru un nume venit din date.
 */
function fr_nume_echipa( $nume ) {
	$club = fr_club_dupa_nume_date( $nume );
	if ( $club ) {
		return $club->name;
	}
	$harta = fr_nume_implicite();
	if ( isset( $harta[ $nume ] ) ) {
		return $harta[ $nume ];
	}
	// Naționale: „Sweden U21” → „Suedia U21”.
	if ( preg_match( '/^(.+?)((?: U\d{2})?(?: Women| \(W\))?)$/', $nume, $m ) ) {
		$tari = fr_tari();
		if ( isset( $tari[ $m[1] ] ) ) {
			return $tari[ $m[1] ] . str_replace( array( ' Women', ' (W)' ), ' (f)', $m[2] );
		}
	}
	return $nume;
}

/**
 * Numele țărilor în română, pentru adversarii naționalelor.
 */
function fr_tari() {
	return array(
		'Romania' => 'România', 'Moldova' => 'Republica Moldova', 'Albania' => 'Albania', 'Andorra' => 'Andorra',
		'Armenia' => 'Armenia', 'Austria' => 'Austria', 'Azerbaijan' => 'Azerbaidjan', 'Belarus' => 'Belarus',
		'Belgium' => 'Belgia', 'Bosnia and Herzegovina' => 'Bosnia și Herțegovina', 'Bosnia & Herzegovina' => 'Bosnia și Herțegovina',
		'Bulgaria' => 'Bulgaria', 'Croatia' => 'Croația', 'Cyprus' => 'Cipru', 'Czech Republic' => 'Cehia', 'Czechia' => 'Cehia',
		'Denmark' => 'Danemarca', 'England' => 'Anglia', 'Estonia' => 'Estonia', 'Faroe Islands' => 'Insulele Feroe',
		'Finland' => 'Finlanda', 'France' => 'Franța', 'Georgia' => 'Georgia', 'Germany' => 'Germania', 'Gibraltar' => 'Gibraltar',
		'Greece' => 'Grecia', 'Hungary' => 'Ungaria', 'Iceland' => 'Islanda', 'Israel' => 'Israel', 'Italy' => 'Italia',
		'Kazakhstan' => 'Kazahstan', 'Kosovo' => 'Kosovo', 'Latvia' => 'Letonia', 'Liechtenstein' => 'Liechtenstein',
		'Lithuania' => 'Lituania', 'Luxembourg' => 'Luxemburg', 'Malta' => 'Malta', 'Montenegro' => 'Muntenegru',
		'Netherlands' => 'Țările de Jos', 'North Macedonia' => 'Macedonia de Nord', 'Northern Ireland' => 'Irlanda de Nord',
		'Norway' => 'Norvegia', 'Poland' => 'Polonia', 'Portugal' => 'Portugalia', 'Republic of Ireland' => 'Irlanda',
		'Ireland' => 'Irlanda', 'Russia' => 'Rusia', 'San Marino' => 'San Marino', 'Scotland' => 'Scoția', 'Serbia' => 'Serbia',
		'Slovakia' => 'Slovacia', 'Slovenia' => 'Slovenia', 'Spain' => 'Spania', 'Sweden' => 'Suedia', 'Switzerland' => 'Elveția',
		'Turkey' => 'Turcia', 'Türkiye' => 'Turcia', 'Ukraine' => 'Ucraina', 'Wales' => 'Țara Galilor',
		'Argentina' => 'Argentina', 'Brazil' => 'Brazilia', 'Colombia' => 'Columbia', 'Mexico' => 'Mexic', 'USA' => 'SUA',
		'United States' => 'SUA', 'Canada' => 'Canada', 'Japan' => 'Japonia', 'South Korea' => 'Coreea de Sud',
		'Morocco' => 'Maroc', 'Egypt' => 'Egipt', 'Algeria' => 'Algeria', 'Tunisia' => 'Tunisia', 'Nigeria' => 'Nigeria',
		'Saudi Arabia' => 'Arabia Saudită', 'Qatar' => 'Qatar', 'Australia' => 'Australia', 'Chile' => 'Chile', 'Uruguay' => 'Uruguay',
	);
}

/**
 * Clubul (termenul) care corespunde unui nume din date, după „Numele în LiveScore” sau după numele afișat.
 */
function fr_club_dupa_nume_date( $nume ) {
	static $cache = null;
	if ( null === $cache ) {
		$cache   = array();
		$cluburi = get_terms( array( 'taxonomy' => 'club', 'hide_empty' => false ) );
		if ( ! is_wp_error( $cluburi ) ) {
			foreach ( $cluburi as $c ) {
				$ls = get_term_meta( $c->term_id, 'fr_livescore_nume', true );
				if ( $ls ) {
					$cache[ $ls ] = $c;
				}
				$cache[ $c->name ] = $cache[ $c->name ] ?? $c;
			}
		}
	}
	if ( isset( $cache[ $nume ] ) ) {
		return $cache[ $nume ];
	}
	$harta = fr_nume_implicite();
	return isset( $harta[ $nume ], $cache[ $harta[ $nume ] ] ) ? $cache[ $harta[ $nume ] ] : null;
}

/**
 * Cheia de comparare a unui nume de club: fără diacritice, fără prefixele juridice (CS, CSM, FC, ACS…) și fără anii din nume.
 * „CSC Șelimbăr” → [selimbar], „FC Unirea 2004 Slobozia” → [unirea, slobozia].
 */
function fr_cuvinte_club( $nume ) {
	$s = strtolower( remove_accents( (string) $nume ) );
	$t = preg_split( '/[^a-z0-9]+/', $s, -1, PREG_SPLIT_NO_EMPTY );
	$p = array( 'cs', 'csc', 'csm', 'csl', 'cso', 'csu', 'fc', 'acs', 'acso', 'asu', 'afc', 'sc', 'cf', 'fk', 'sk', 'as', 'club', 'sportiv' );
	return array_values( array_filter( $t, fn( $w ) => ! in_array( $w, $p, true ) && ! preg_match( '/^(18|19|20)\d\d$/', $w ) ) );
}

/**
 * Clubul existent pentru un nume scris oricum: întâi exact (sau numele din date), apoi după cuvinte.
 * Potrivire după cuvinte: aceleași cuvinte, sau cuvintele numelui dat se regăsesc toate în numele clubului
 * („CSC Șelimbăr” → „Comunal Șelimbăr”), dar niciodată invers („FCSB 2” nu devine „FCSB”). Doar dacă potrivirea e unică.
 *
 * @param WP_Term[]|null $candidati Cluburile între care se caută (implicit toate).
 */
function fr_gaseste_club( $nume, $candidati = null ) {
	if ( null === $candidati ) {
		$c = fr_club_dupa_nume_date( $nume );
		if ( $c ) {
			return $c;
		}
		$candidati = get_terms( array( 'taxonomy' => 'club', 'hide_empty' => false ) );
		if ( is_wp_error( $candidati ) ) {
			return null;
		}
	}
	$cuv = fr_cuvinte_club( $nume );
	if ( ! $cuv ) {
		return null;
	}
	$egale   = array();
	$incluse = array();
	$cuprind = array();
	foreach ( $candidati as $t ) {
		$ct = fr_cuvinte_club( $t->name );
		if ( $ct === $cuv ) {
			$egale[] = $t;
		} elseif ( ! array_diff( $cuv, $ct ) ) {
			$incluse[] = $t;
		} elseif ( $ct && ! array_diff( $ct, $cuv ) ) {
			// Numele dat are cuvinte în plus („Rapid București” → „Rapid”); nu și echipele secunde („FCSB 2”, „Dinamo II”, „… B”).
			$plus = array_diff( $cuv, $ct );
			if ( ! array_filter( $plus, fn( $w ) => preg_match( '/^(\d+|i+|b|u\d+)$/', $w ) ) ) {
				$cuprind[] = $t;
			}
		}
	}
	if ( 1 === count( $egale ) ) {
		return $egale[0];
	}
	if ( $egale ) {
		return null;
	}
	if ( 1 === count( $incluse ) ) {
		return $incluse[0];
	}
	return ( ! $incluse && 1 === count( $cuprind ) ) ? $cuprind[0] : null;
}

/**
 * Unește cluburile create din articole (fără competiție) cu clubul real echivalent: articolele trec la clubul real,
 * dublura se șterge. Întoarce câte dubluri au fost unite.
 */
function fr_uneste_dubluri_cluburi() {
	$toate = get_terms( array( 'taxonomy' => 'club', 'hide_empty' => false ) );
	if ( is_wp_error( $toate ) ) {
		return 0;
	}
	$reale  = array_filter( $toate, fn( $t ) => (bool) get_term_meta( $t->term_id, 'fr_competitie', true ) );
	$unite  = 0;
	foreach ( $toate as $t ) {
		if ( get_term_meta( $t->term_id, 'fr_competitie', true ) ) {
			continue;
		}
		$real = fr_gaseste_club( $t->name, $reale );
		if ( ! $real || $real->term_id === $t->term_id ) {
			continue;
		}
		$posturi = get_objects_in_term( $t->term_id, 'club' );
		foreach ( (array) $posturi as $pid ) {
			wp_add_object_terms( (int) $pid, (int) $real->term_id, 'club' );
		}
		wp_delete_term( $t->term_id, 'club' );
		$unite++;
	}
	return $unite;
}
