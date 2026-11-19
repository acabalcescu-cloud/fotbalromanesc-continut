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
