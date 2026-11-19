<?php
/**
 * Actualizarea temei din depozitul de conținut (tema/versiune.txt, tema/sha256.txt, tema/fotbalromanesc.zip).
 *
 * 1. WordPress vede versiunile noi ca pe orice actualizare de temă (Panou › Actualizări) și le instalează
 *    automat, de două ori pe zi, la verificarea lui obișnuită.
 * 2. POST /wp-json/fr/v1/tema instalează imediat versiunea nouă. Cere antetul X-Fr-Key cu cheia din
 *    Aspect › fotbalromanesc (sau un administrator autentificat).
 *
 * Arhiva se instalează doar dacă amprenta ei SHA-256 e cea din tema/sha256.txt.
 *
 * @package fotbalromanesc
 */

defined( 'ABSPATH' ) || exit;

const FR_SLUG_TEMA     = 'fotbalromanesc';
const FR_REPO_IMPLICIT = 'acabalcescu-cloud/fotbalromanesc-continut';

/**
 * Depozitul temei: cel din setări sau, dacă lipsește, depozitul implicit.
 */
function fr_repo_tema_url( $fisier ) {
	$repo = fr_setare( 'repo' ) ?: FR_REPO_IMPLICIT;
	$ram  = fr_setare( 'ramura' ) ?: 'main';
	$baza = apply_filters( 'fr_repo_baza', 'https://raw.githubusercontent.com/' . $repo . '/' . rawurlencode( $ram ) . '/' );
	return $baza . 'tema/' . $fisier;
}

/**
 * Versiunea din depozit: [ 'versiune', 'sha', 'pachet' ] sau null. Păstrată o oră (fără $proaspat).
 */
function fr_info_tema_depozit( $proaspat = false ) {
	$info = $proaspat ? false : get_transient( 'fr_info_tema' );
	if ( is_array( $info ) ) {
		return $info;
	}
	$citeste = function ( $f ) {
		$r = wp_remote_get( fr_repo_tema_url( $f ) . '?t=' . time(), array( 'timeout' => 15 ) );
		return ( ! is_wp_error( $r ) && 200 === (int) wp_remote_retrieve_response_code( $r ) ) ? trim( wp_remote_retrieve_body( $r ) ) : '';
	};
	$ver = $citeste( 'versiune.txt' );
	$sha = strtolower( $citeste( 'sha256.txt' ) );
	if ( ! preg_match( '/^\d+\.\d+\.\d+$/', $ver ) || ! preg_match( '/^[a-f0-9]{64}$/', $sha ) ) {
		return null;
	}
	$info = array( 'versiune' => $ver, 'sha' => $sha, 'pachet' => fr_repo_tema_url( 'fotbalromanesc.zip' ) );
	set_transient( 'fr_info_tema', $info, HOUR_IN_SECONDS );
	return $info;
}

function fr_versiune_instalata() {
	$d = get_file_data( get_template_directory() . '/style.css', array( 'v' => 'Version' ) );
	return $d['v'] ?: FR_VERSIUNE;
}

add_filter( 'pre_set_site_transient_update_themes', 'fr_anunta_actualizare' );
function fr_anunta_actualizare( $t ) {
	if ( ! is_object( $t ) || get_template() !== FR_SLUG_TEMA ) {
		return $t;
	}
	$info = fr_info_tema_depozit();
	if ( ! $info ) {
		return $t;
	}
	$item = array(
		'theme'        => FR_SLUG_TEMA,
		'new_version'  => $info['versiune'],
		'url'          => 'https://github.com/' . ( fr_setare( 'repo' ) ?: FR_REPO_IMPLICIT ),
		'package'      => $info['pachet'],
		'requires'     => '6.4',
		'requires_php' => '8.0',
	);
	if ( version_compare( $info['versiune'], fr_versiune_instalata(), '>' ) ) {
		$t->response[ FR_SLUG_TEMA ] = $item;
		unset( $t->no_update[ FR_SLUG_TEMA ] );
	} else {
		unset( $t->response[ FR_SLUG_TEMA ] );
		$t->no_update[ FR_SLUG_TEMA ] = $item;
	}
	return $t;
}

// Actualizările temei se instalează automat (se poate opri din Aspect › Teme).
add_filter( 'auto_update_theme', function ( $update, $item ) {
	return ( is_object( $item ) && FR_SLUG_TEMA === ( $item->theme ?? '' ) ) ? true : $update;
}, 10, 2 );

/**
 * Verificarea amprentei: arhiva temei se descarcă aici și se instalează doar dacă SHA-256 se potrivește.
 */
add_filter( 'upgrader_pre_download', 'fr_verifica_pachet', 10, 2 );
function fr_verifica_pachet( $raspuns, $pachet ) {
	if ( false !== $raspuns || ! is_string( $pachet ) || false === strpos( $pachet, 'tema/fotbalromanesc.zip' ) ) {
		return $raspuns;
	}
	$info = fr_info_tema_depozit( true );
	if ( ! $info ) {
		return new WP_Error( 'fr_fara_info', 'Nu am putut citi versiune.txt și sha256.txt din depozit.' );
	}
	if ( ! function_exists( 'download_url' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	$tmp = download_url( strtok( $pachet, '?' ) . '?t=' . time(), 120 );
	if ( is_wp_error( $tmp ) ) {
		return $tmp;
	}
	if ( ! hash_equals( $info['sha'], hash_file( 'sha256', $tmp ) ) ) {
		wp_delete_file( $tmp );
		return new WP_Error( 'fr_sha', 'Amprenta arhivei nu se potrivește cu tema/sha256.txt. Nu instalez.' );
	}
	return $tmp;
}

/**
 * Cheia pentru actualizarea imediată, generată o singură dată.
 */
function fr_cheie_actualizare() {
	$k = get_option( 'fr_cheie_actualizare' );
	if ( ! $k ) {
		$k = wp_generate_password( 40, false, false );
		update_option( 'fr_cheie_actualizare', $k, false );
	}
	return $k;
}

add_action( 'rest_api_init', 'fr_rute_tema' );
function fr_rute_tema() {
	register_rest_route( 'fr/v1', '/tema', array(
		array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => function () {
				$info = fr_info_tema_depozit( true );
				return array( 'instalata' => fr_versiune_instalata(), 'in_depozit' => $info['versiune'] ?? null );
			},
		),
		array(
			'methods'             => 'POST',
			'permission_callback' => function ( $req ) {
				$cheie = (string) $req->get_header( 'x_fr_key' );
				return current_user_can( 'update_themes' ) || ( '' !== $cheie && hash_equals( fr_cheie_actualizare(), $cheie ) );
			},
			'callback'            => 'fr_actualizeaza_acum',
		),
	) );
}

function fr_actualizeaza_acum() {
	if ( get_transient( 'fr_tema_in_lucru' ) ) {
		return new WP_Error( 'fr_ocupat', 'O actualizare e deja în curs. Mai încearcă peste un minut.', array( 'status' => 429 ) );
	}
	$inainte = fr_versiune_instalata();
	$info    = fr_info_tema_depozit( true );
	if ( ! $info ) {
		return new WP_Error( 'fr_fara_info', 'Nu am putut citi versiunea din depozit.', array( 'status' => 502 ) );
	}
	if ( ! version_compare( $info['versiune'], $inainte, '>' ) ) {
		return array( 'actualizat' => false, 'instalata' => $inainte, 'in_depozit' => $info['versiune'] );
	}
	set_transient( 'fr_tema_in_lucru', 1, 5 * MINUTE_IN_SECONDS );

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/misc.php';
	require_once ABSPATH . 'wp-admin/includes/theme.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	if ( ! WP_Filesystem() || 'direct' !== get_filesystem_method() ) {
		delete_transient( 'fr_tema_in_lucru' );
		return new WP_Error( 'fr_fs', 'Serverul nu permite scrierea directă a fișierelor; actualizează din Panou › Actualizări.', array( 'status' => 500 ) );
	}
	$skin = new WP_Ajax_Upgrader_Skin();
	$up   = new Theme_Upgrader( $skin );
	$rez  = $up->install( $info['pachet'], array( 'overwrite_package' => true, 'clear_update_cache' => true ) );
	delete_transient( 'fr_tema_in_lucru' );
	delete_transient( 'fr_info_tema' );
	if ( is_wp_error( $rez ) || ! $rez ) {
		$err = is_wp_error( $rez ) ? $rez : $skin->get_errors();
		return new WP_Error( 'fr_instalare', 'Instalarea a eșuat: ' . ( is_wp_error( $err ) ? $err->get_error_message() : 'motiv necunoscut' ), array( 'status' => 500 ) );
	}
	wp_clean_themes_cache();
	return array( 'actualizat' => true, 'inainte' => $inainte, 'acum' => fr_versiune_instalata() );
}
