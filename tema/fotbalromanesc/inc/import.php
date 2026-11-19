<?php
/**
 * Importul articolelor dintr-un depozit GitHub public, la 15 minute (același model ca la Poveștile Științei).
 *
 * index.json = { "articole": [ "articole/<id>.json", … ], "actualizate": [ … ] }   („cronici” e acceptat ca sinonim)
 * articole/<id>.json = {
 *   "id": "2026-11-15-liga3-seria1-runda13",            obligatoriu, unic
 *   "titlu": "…", "rezumat": "…", "continut": "<p>…</p>",  obligatorii (la video: "video_url" în loc de conținut)
 *   "competitie": "liga-3-seria-1" sau "Liga 3 › Seria 1",
 *   "cluburi": [ "Bradul Putna", "Ceahlăul Piatra Neamț" ],
 *   "etichete": [ … ],
 *   "imagine": "<id>.jpg" (lângă fișierul JSON) sau "imagine_url": "https://…",
 *   "autor": { "login": "…", "nume": "…" },
 *   "data_publicare": "2026-11-15 09:30" (ora României), "status": "draft" (implicit: publicat),
 *   "actualizat": "2026-11-15 18:00" (cu fișierul trecut și la „actualizate”: înlocuiește textul)
 * }
 *
 * @package fotbalromanesc
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'fr_programeaza_import' );
function fr_programeaza_import() {
	if ( fr_setare( 'repo' ) && ! wp_next_scheduled( 'fr_import_continut' ) ) {
		wp_schedule_event( time() + 60, 'fr_15m', 'fr_import_continut' );
	}
	if ( ! fr_setare( 'repo' ) && wp_next_scheduled( 'fr_import_continut' ) ) {
		wp_clear_scheduled_hook( 'fr_import_continut' );
	}
}

function fr_repo_url( $cale ) {
	$repo   = trim( (string) fr_setare( 'repo' ), '/' );
	$ramura = fr_setare( 'ramura' ) ?: 'main';
	$cale   = implode( '/', array_map( 'rawurlencode', explode( '/', ltrim( $cale, '/' ) ) ) );
	$baza = 'https://raw.githubusercontent.com/' . $repo . '/' . rawurlencode( $ramura ) . '/';
	/** Filtrul „fr_repo_baza” permite alt server pentru fișiere (o oglindă, un depozit găzduit altundeva). */
	return apply_filters( 'fr_repo_baza', $baza ) . $cale;
}

function fr_repo_json( $cale ) {
	$r = wp_remote_get( fr_repo_url( $cale ), array( 'timeout' => 20 ) );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	if ( 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
		return new WP_Error( 'fr_http', 'GitHub a răspuns ' . wp_remote_retrieve_response_code( $r ) . ' la ' . $cale );
	}
	$d = json_decode( wp_remote_retrieve_body( $r ), true );
	return is_array( $d ) ? $d : new WP_Error( 'fr_json', 'JSON invalid în ' . $cale );
}

/**
 * Competiția după slug, nume sau „Părinte › Copil”. O creează dacă lipsește.
 */
function fr_termen_competitie( $valoare ) {
	$valoare = trim( (string) $valoare );
	if ( '' === $valoare ) {
		return 0;
	}
	$t = get_term_by( 'slug', sanitize_title( $valoare ), 'competitie' );
	if ( $t ) {
		return (int) $t->term_id;
	}
	$parti   = array_map( 'trim', preg_split( '/\s*[›>\/]\s*/u', $valoare ) );
	$parinte = 0;
	foreach ( $parti as $nume ) {
		$gasit = get_terms( array( 'taxonomy' => 'competitie', 'name' => $nume, 'parent' => $parinte, 'hide_empty' => false, 'number' => 1 ) );
		if ( $gasit && ! is_wp_error( $gasit ) ) {
			$parinte = (int) $gasit[0]->term_id;
			continue;
		}
		$nou = wp_insert_term( $nume, 'competitie', array( 'parent' => $parinte ) );
		if ( is_wp_error( $nou ) ) {
			return $parinte;
		}
		$parinte = (int) $nou['term_id'];
	}
	return $parinte;
}

function fr_autor_import( $autor ) {
	$admini = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	$implicit = $admini ? (int) $admini[0] : 1;
	if ( empty( $autor['login'] ) ) {
		return $implicit;
	}
	$login = sanitize_user( $autor['login'], true );
	$user  = get_user_by( 'login', $login );
	if ( $user ) {
		return (int) $user->ID;
	}
	$nume  = sanitize_text_field( $autor['nume'] ?? $login );
	$parti = explode( ' ', $nume, 2 );
	$id    = wp_insert_user( array(
		'user_login'   => $login,
		'user_pass'    => wp_generate_password( 24 ),
		'display_name' => $nume,
		'nickname'     => $nume,
		'first_name'   => $parti[0],
		'last_name'    => $parti[1] ?? '',
		'role'         => 'author',
	) );
	return is_wp_error( $id ) ? $implicit : (int) $id;
}

function fr_ataseaza_imagine( $url, $post_id, $titlu ) {
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$att = media_sideload_image( $url, $post_id, $titlu, 'id' );
	if ( ! is_wp_error( $att ) ) {
		set_post_thumbnail( $post_id, $att );
	}
}

function fr_imagine_item( $item, $cale ) {
	if ( ! empty( $item['imagine_url'] ) ) {
		return esc_url_raw( $item['imagine_url'] );
	}
	if ( ! empty( $item['imagine'] ) ) {
		$dir = trim( dirname( $cale ), './' );
		return fr_repo_url( ( $dir ? $dir . '/' : '' ) . basename( $item['imagine'] ) );
	}
	return '';
}

/**
 * Importă (sau actualizează) un articol. Întoarce ID-ul nou, -ID la actualizare, 0 dacă exista deja.
 */
function fr_importa_articol( $item, $cale ) {
	$exista = get_posts( array( 'post_type' => 'post', 'post_status' => 'any', 'meta_key' => '_fr_sursa', 'meta_value' => $item['id'], 'numberposts' => 1, 'fields' => 'ids' ) );
	$video  = isset( $item['tip'] ) && 'video' === $item['tip'];

	if ( $exista ) {
		$act = ! empty( $item['actualizat'] ) ? strtotime( $item['actualizat'] ) : 0;
		if ( $act && $act > (int) get_post_meta( $exista[0], '_fr_actualizat', true ) && ! $video ) {
			wp_update_post( array(
				'ID'           => $exista[0],
				'post_title'   => wp_strip_all_tags( $item['titlu'] ),
				'post_excerpt' => wp_strip_all_tags( $item['rezumat'] ?? '' ),
				'post_content' => wp_kses_post( $item['continut'] ),
			) );
			update_post_meta( $exista[0], '_fr_actualizat', $act );
			if ( isset( $item['hashtaguri'] ) ) {
				update_post_meta( $exista[0], '_fr_hashtaguri', array_slice( array_map( 'sanitize_text_field', (array) $item['hashtaguri'] ), 0, 15 ) );
			}
			return -1 * (int) $exista[0];
		}
		return 0;
	}

	$postarr = array(
		'post_type'    => 'post',
		'post_status'  => 'draft',
		'post_author'  => fr_autor_import( $item['autor'] ?? array() ),
		'post_title'   => wp_strip_all_tags( $item['titlu'] ),
		'post_excerpt' => wp_strip_all_tags( $item['rezumat'] ?? '' ),
		'post_content' => $video ? esc_url_raw( $item['video_url'] ) : wp_kses_post( $item['continut'] ),
		'tags_input'   => array_map( 'sanitize_text_field', (array) ( $item['etichete'] ?? array() ) ),
		'meta_input'   => array(
			'_fr_sursa'      => sanitize_text_field( $item['id'] ),
			'_fr_hashtaguri' => array_slice( array_map( 'sanitize_text_field', (array) ( $item['hashtaguri'] ?? array() ) ), 0, 15 ),
		),
	);
	if ( ! empty( $item['data_publicare'] ) && strtotime( $item['data_publicare'] ) ) {
		// O dată din viitor ar programa articolul: o limităm la ora curentă.
		$postarr['post_date']     = gmdate( 'Y-m-d H:i:s', min( strtotime( $item['data_publicare'] ), strtotime( current_time( 'mysql' ) ) ) );
		$postarr['post_date_gmt'] = get_gmt_from_date( $postarr['post_date'] );
		$postarr['edit_date']     = true;
	}
	$post_id = wp_insert_post( $postarr, true );
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	$comp = fr_termen_competitie( $item['competitie'] ?? ( $item['categorie'] ?? '' ) );
	if ( $comp ) {
		wp_set_object_terms( $post_id, array( $comp ), 'competitie' );
	}
	if ( ! empty( $item['cluburi'] ) ) {
		wp_set_object_terms( $post_id, array_map( 'sanitize_text_field', (array) $item['cluburi'] ), 'club' );
	}
	if ( $video ) {
		set_post_format( $post_id, 'video' );
	}
	$img = fr_imagine_item( $item, $cale );
	if ( $img ) {
		fr_ataseaza_imagine( $img, $post_id, $item['titlu'] );
	}
	if ( ! ( isset( $item['status'] ) && 'draft' === $item['status'] ) ) {
		wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
	}
	return $post_id;
}

add_action( 'fr_import_continut', 'fr_import_continut' );
function fr_import_continut() {
	if ( ! fr_setare( 'repo' ) ) {
		return array( 'erori' => array( 'Depozitul nu e setat (Aspect › fotbalromanesc).' ) );
	}
	$jurnal = array( 'verificat' => time(), 'importate' => array(), 'erori' => array() );
	$index  = fr_repo_json( 'index.json' );
	if ( is_wp_error( $index ) ) {
		$jurnal['erori'][] = $index->get_error_message();
		update_option( 'fr_import_jurnal', $jurnal, false );
		return $jurnal;
	}
	$facute = (array) get_option( 'fr_import_facute', array() );
	$cai    = (array) ( $index['articole'] ?? $index['cronici'] ?? array() );
	foreach ( array_slice( $cai, 0, 200 ) as $cale ) {
		$cale = (string) $cale;
		if ( in_array( $cale, $facute, true ) ) {
			continue;
		}
		$item  = fr_repo_json( $cale );
		$video = is_array( $item ) && isset( $item['tip'] ) && 'video' === $item['tip'];
		if ( is_wp_error( $item ) || empty( $item['id'] ) || empty( $item['titlu'] ) || ( $video ? empty( $item['video_url'] ) : empty( $item['continut'] ) ) ) {
			$jurnal['erori'][] = is_wp_error( $item ) ? $item->get_error_message() : 'Articol incomplet: ' . $cale;
			continue;
		}
		$r = fr_importa_articol( $item, $cale );
		if ( is_wp_error( $r ) ) {
			$jurnal['erori'][] = $cale . ': ' . $r->get_error_message();
			continue;
		}
		$jurnal['importate'][] = $item['id'] . ( $r ? ' → ' . get_post_status( $r ) . ' ' . $r : ' (exista deja)' );
		$facute[]              = $cale;
	}
	foreach ( array_slice( (array) ( $index['actualizate'] ?? array() ), 0, 20 ) as $cale ) {
		$item = fr_repo_json( (string) $cale );
		if ( is_wp_error( $item ) || empty( $item['id'] ) || empty( $item['actualizat'] ) || empty( $item['continut'] ) ) {
			continue;
		}
		$r = fr_importa_articol( $item, (string) $cale );
		if ( is_int( $r ) && $r < 0 ) {
			$jurnal['importate'][] = $item['id'] . ' → actualizat ' . ( -1 * $r );
		}
	}
	update_option( 'fr_import_facute', array_slice( $facute, -2000 ), false );
	update_option( 'fr_import_jurnal', $jurnal, false );
	return $jurnal;
}

/**
 * GET  /wp-json/fr/v1/import – ultima verificare (administratori)
 * POST /wp-json/fr/v1/import – verifică acum (administratori)
 */
add_action( 'rest_api_init', 'fr_rute_import' );
function fr_rute_import() {
	$admin = fn() => current_user_can( 'manage_options' );
	register_rest_route( 'fr/v1', '/import', array(
		array( 'methods' => 'GET', 'permission_callback' => $admin, 'callback' => fn() => get_option( 'fr_import_jurnal', array() ) ),
		array( 'methods' => 'POST', 'permission_callback' => $admin, 'callback' => fn() => fr_import_continut() ),
	) );
}
