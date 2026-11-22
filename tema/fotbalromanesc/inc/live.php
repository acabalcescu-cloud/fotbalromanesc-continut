<?php
/**
 * Scorurile live și programul etapei.
 *
 * 1. Cache: paginile cu meciuri (prima pagină, ligi, cluburi, program, articole) primesc eticheta LiteSpeed
 *    „fr_date” și o durată scurtă (2 minute când sunt meciuri azi, 10 minute altfel). Când datele se schimbă,
 *    eticheta se golește, așa că nicio pagină nu mai arată un scor vechi.
 * 2. În pagină: scriptul întreabă GET /wp-json/fr/v1/live la 45 de secunde (doar cât pagina e vizibilă) și
 *    actualizează scorurile, minutul și starea fiecărui meci marcat cu data-meci.
 * 3. Endpointul reîmprospătează singur competițiile cu meciuri în desfășurare (cel mult o dată pe minut), deci
 *    scorul live nu depinde de cronul de 10 minute.
 * 4. Programul etapei curente, cu scoruri, în bara laterală (desktop), sub sponsor.
 *
 * @package fotbalromanesc
 */

defined( 'ABSPATH' ) || exit;

/**
 * Meciurile din fereastra „azi”: de ieri seara până mâine, din toate competițiile cu date.
 */
function fr_live_meciuri() {
	$acum = time();
	return fr_meciuri_interval( fr_competitii_cu_date(), $acum - 14 * HOUR_IN_SECONDS, $acum + 30 * HOUR_IN_SECONDS );
}

function fr_live_sunt_meciuri_azi() {
	$acum = time();
	foreach ( fr_live_meciuri() as $m ) {
		$ts = strtotime( $m['data'] );
		if ( 'live' === $m['stare'] || ( $ts > $acum - 3 * HOUR_IN_SECONDS && $ts < $acum + 12 * HOUR_IN_SECONDS ) ) {
			return true;
		}
	}
	return false;
}

/* ---------- 1. Cache ---------- */

add_action( 'template_redirect', function () {
	$cu_date = is_front_page() || is_home() || is_tax( 'competitie' ) || is_tax( 'club' ) || is_singular( 'post' )
		|| is_page_template( 'page-templates/program.php' );
	if ( ! $cu_date ) {
		return;
	}
	do_action( 'litespeed_tag_add', 'fr_date' );
	do_action( 'litespeed_control_set_ttl', fr_live_sunt_meciuri_azi() ? 120 : 600 );
}, 5 );

// După fiecare reîmprospătare: dacă s-a schimbat ceva (scor, stare, program), golim paginile cu date.
add_action( 'fr_reimprospateaza_date', 'fr_live_goleste_daca_s_a_schimbat', 99 );
function fr_live_goleste_daca_s_a_schimbat() {
	$semn = array();
	foreach ( fr_competitii_cu_date() as $t ) {
		$d = get_option( 'fr_date_' . $t->term_id );
		foreach ( (array) ( $d['meciuri'] ?? array() ) as $m ) {
			$semn[] = $m['id'] . ':' . wp_json_encode( $m['scor'] ) . ':' . $m['stare'] . ':' . ( $m['minut'] ?? '' ) . ':' . $m['data'];
		}
	}
	$h = md5( implode( '|', $semn ) );
	if ( get_option( 'fr_date_semn' ) !== $h ) {
		update_option( 'fr_date_semn', $h, false );
		do_action( 'litespeed_purge', 'fr_date' );
	}
}

/* ---------- 2–3. Endpointul live ---------- */

add_action( 'rest_api_init', function () {
	register_rest_route( 'fr/v1', '/live', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => 'fr_live_raspuns',
	) );
} );

function fr_live_raspuns() {
	do_action( 'litespeed_control_set_nocache', 'scoruri live' );
	$acum = time();
	// Competițiile cu meciuri în desfășurare (sau care trebuiau să înceapă) se reîmprospătează acum, cel mult o dată pe minut.
	if ( ! get_transient( 'fr_live_blocat' ) ) {
		set_transient( 'fr_live_blocat', 1, MINUTE_IN_SECONDS );
		$de_actualizat = array();
		foreach ( fr_live_meciuri() as $m ) {
			$ts = strtotime( $m['data'] );
			if ( 'live' === $m['stare'] || ( in_array( $m['stare'], array( 'programat', 'asteptare' ), true ) && $ts < $acum && $ts > $acum - 4 * HOUR_IN_SECONDS ) ) {
				$de_actualizat[ $m['competitie']->term_id ] = true;
			}
		}
		foreach ( array_keys( $de_actualizat ) as $tid ) {
			fr_incarca_date( $tid );
		}
		if ( $de_actualizat ) {
			fr_live_goleste_daca_s_a_schimbat();
		}
	}
	$out = array();
	foreach ( fr_live_meciuri() as $m ) {
		$out[ (string) $m['id'] ] = fr_live_stare_meci( $m );
	}
	$r = rest_ensure_response( array( 'meciuri' => $out, 'acum' => $acum ) );
	$r->header( 'Cache-Control', 'no-store, max-age=0' );
	return $r;
}

/**
 * Textele afișate pentru un meci (aceleași ca în cardul din pagină).
 */
function fr_live_stare_meci( $m ) {
	if ( 'live' === $m['stare'] ) {
		$sus = 'LIVE ' . $m['minut'];
	} elseif ( 'amanat' === $m['stare'] ) {
		$sus = 'Amânat';
	} elseif ( 'asteptare' === $m['stare'] ) {
		$sus = fr_data_meci( $m['data'], 'data' ) . ' · rezultat în curând';
	} elseif ( 'programat' === $m['stare'] ) {
		$sus = fr_data_meci( $m['data'], 'data' );
	} else {
		$sus = fr_data_meci( $m['data'] );
	}
	return array(
		'stare' => $m['stare'],
		'scor'  => $m['scor'] ? (int) $m['scor'][0] . '-' . (int) $m['scor'][1] : ( 'asteptare' === $m['stare'] ? '–' : fr_data_meci( $m['data'], 'ora' ) ),
		'sus'   => $sus,
	);
}

/* ---------- 4. Programul etapei în bara laterală ---------- */

/**
 * Etapa curentă a unei competiții: cea cu meciuri live; altfel a următorului meci, dacă e în cel mult 4 zile;
 * altfel ultima etapă jucată. Întoarce [ număr, meciuri ] sau null (competiții fără etape numerotate).
 */
function fr_etapa_curenta( $term ) {
	$d = fr_date_competitie( $term->term_id );
	$m = array_values( array_filter( (array) ( $d['meciuri'] ?? array() ), fn( $x ) => is_numeric( $x['runda'] ) ) );
	if ( ! $m ) {
		return null;
	}
	$acum  = time();
	$runda = null;
	foreach ( $m as $x ) {
		if ( 'live' === $x['stare'] ) {
			$runda = $x['runda'];
			break;
		}
	}
	if ( null === $runda ) {
		$viitoare = array_values( array_filter( $m, fn( $x ) => in_array( $x['stare'], array( 'programat', 'asteptare' ), true ) && strtotime( $x['data'] ) > $acum - 3 * HOUR_IN_SECONDS ) );
		$jucate   = array_values( array_filter( $m, fn( $x ) => 'terminat' === $x['stare'] ) );
		if ( $viitoare && strtotime( $viitoare[0]['data'] ) < $acum + 4 * DAY_IN_SECONDS ) {
			$runda = $viitoare[0]['runda'];
		} elseif ( $jucate ) {
			$runda = end( $jucate )['runda'];
		} elseif ( $viitoare ) {
			$runda = $viitoare[0]['runda'];
		}
	}
	if ( null === $runda ) {
		return null;
	}
	$etapa = array_values( array_filter( $m, fn( $x ) => (string) $x['runda'] === (string) $runda ) );
	// Meciurile fără număr de etapă în sursă („Regular Season” la LiveScore) intră în etapa care se joacă în aceleași zile.
	if ( $etapa ) {
		$de_la  = strtotime( $etapa[0]['data'] ) - DAY_IN_SECONDS;
		$pana   = strtotime( end( $etapa )['data'] ) + DAY_IN_SECONDS;
		foreach ( (array) ( $d['meciuri'] ?? array() ) as $x ) {
			$ts = strtotime( $x['data'] );
			if ( ! is_numeric( $x['runda'] ) && $ts >= $de_la && $ts <= $pana ) {
				$etapa[] = $x;
			}
		}
	}
	usort( $etapa, fn( $a, $b ) => strcmp( $a['data'], $b['data'] ) );
	return array( $runda, $etapa );
}

function fr_widget_etapa( $term ) {
	if ( ! $term instanceof WP_Term ) {
		return '';
	}
	$e = fr_etapa_curenta( $term );
	if ( ! $e ) {
		return '';
	}
	list( $runda, $meciuri ) = $e;
	ob_start();
	?>
	<section class="fr-card fr-etapa-lateral" aria-label="<?php echo esc_attr( 'Etapa ' . $runda . ', ' . fr_eticheta_competitie( $term ) ); ?>">
		<?php echo fr_titlu_sectiune( fr_eticheta_competitie( $term ) . ' · etapa ' . $runda, fr_url_fila( 'meciuri', $term ), 'Toate' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<?php $zi = ''; ?>
		<ul class="fr-etapa-lateral__lista">
			<?php foreach ( $meciuri as $m ) : ?>
				<?php $z = fr_data_meci( $m['data'], 'zi' ); ?>
				<?php if ( $z !== $zi ) : ?>
					<?php $zi = $z; ?>
					<li class="fr-etapa-lateral__zi"><?php echo esc_html( $z ); ?></li>
				<?php endif; ?>
				<?php $s = fr_live_stare_meci( $m ); ?>
				<li class="fr-etapa-lateral__meci fr-meci--<?php echo esc_attr( $m['stare'] ); ?>" data-meci="<?php echo esc_attr( $m['id'] ); ?>">
					<span class="fr-etapa-lateral__echipa"><?php echo esc_html( fr_nume_echipa( $m['gazde'] ) ); ?></span>
					<b class="fr-etapa-lateral__scor js-scor"><?php echo esc_html( $s['scor'] ); ?></b>
					<span class="fr-etapa-lateral__echipa fr-etapa-lateral__echipa--o"><?php echo esc_html( fr_nume_echipa( $m['oaspeti'] ) ); ?></span>
					<small class="fr-etapa-lateral__stare js-stare"<?php echo 'live' === $m['stare'] ? '' : ' hidden'; ?>><?php echo 'live' === $m['stare'] ? esc_html( $s['sus'] ) : ''; ?></small>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>
	<?php
	return ob_get_clean();
}

/**
 * Competiția pentru widgetul din bară: a articolului (sau a părintelui cu date); altfel SuperLiga.
 */
function fr_widget_etapa_pentru( $term = null ) {
	$t = $term instanceof WP_Term ? $term : null;
	while ( $t && ! fr_etapa_curenta( $t ) ) {
		$t = $t->parent ? get_term( $t->parent, 'competitie' ) : null;
		$t = $t instanceof WP_Term ? $t : null;
	}
	if ( ! $t ) {
		$t = get_term_by( 'slug', 'superliga', 'competitie' );
	}
	return $t ? fr_widget_etapa( $t ) : '';
}
