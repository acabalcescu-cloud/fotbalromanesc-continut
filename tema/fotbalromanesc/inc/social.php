<?php
/**
 * Publicarea automată pe Facebook (pagina) și Instagram (contul profesional legat de pagină), prin API-ul Meta.
 *
 * - Facebook: o postare cu linkul articolului, titlul, rezumatul și hashtag-urile; previzualizarea folosește
 *   coperta 1200×630 (og:image).
 * - Instagram: coperta 1080×1350 și descrierea (titlu, rezumat, „link în bio”, hashtag-uri).
 * Articolul nou se postează la ~2 minute după publicarea pe site. Articolele mai vechi de 2 zile nu se postează.
 *
 * Tokenul (de utilizator sau de pagină) se lipește în Aspect › fotbalromanesc; tema găsește singură pagina,
 * tokenul paginii și contul de Instagram. Tokenul nu stă în cod și nu apare în pagină.
 *
 * @package fotbalromanesc
 */

defined( 'ABSPATH' ) || exit;

const FR_GRAPH = 'https://graph.facebook.com/v21.0/';

function fr_social_cont() {
	return array(
		'token'    => (string) get_option( 'fr_meta_token', '' ),
		'pagina'   => (string) get_option( 'fr_fb_pagina_id', '' ),
		'nume'     => (string) get_option( 'fr_fb_pagina_nume', '' ),
		'ig'       => (string) get_option( 'fr_ig_id', '' ),
		'ig_nume'  => (string) get_option( 'fr_ig_nume', '' ),
		'fb_activ' => (bool) get_option( 'fr_fb_activ' ),
		'ig_activ' => (bool) get_option( 'fr_ig_activ' ),
	);
}

function fr_graph( $metoda, $cale, $param = array() ) {
	$url = FR_GRAPH . ltrim( $cale, '/' );
	$r   = 'GET' === $metoda
		? wp_remote_get( add_query_arg( array_map( 'rawurlencode', $param ), $url ), array( 'timeout' => 30 ) )
		: wp_remote_post( $url, array( 'timeout' => 45, 'body' => $param ) );
	if ( is_wp_error( $r ) ) {
		return array( 'error' => array( 'message' => $r->get_error_message() ) );
	}
	return (array) json_decode( wp_remote_retrieve_body( $r ), true );
}

/**
 * Din tokenul lipit: pagina, tokenul paginii și contul de Instagram legat.
 * Cu un token de utilizator, alege pagina al cărei nume conține „fotbal” (sau singura pagină).
 */
function fr_social_descopera( $t ) {
	$eu = fr_graph( 'GET', 'me', array( 'fields' => 'id,name,instagram_business_account{id,username}', 'access_token' => $t ) );
	if ( ! empty( $eu['error'] ) ) {
		return array( 'mesaj' => 'Facebook a răspuns: ' . wp_html_excerpt( (string) $eu['error']['message'], 200 ) );
	}
	// Token de pagină: /me e chiar pagina.
	$conturi = fr_graph( 'GET', 'me/accounts', array( 'fields' => 'id,name,access_token,instagram_business_account{id,username}', 'limit' => '50', 'access_token' => $t ) );
	if ( empty( $conturi['data'] ) ) {
		return array(
			'token'   => $t,
			'pagina'  => $eu['id'],
			'nume'    => $eu['name'] ?? '',
			'ig'      => $eu['instagram_business_account']['id'] ?? '',
			'ig_nume' => $eu['instagram_business_account']['username'] ?? '',
		);
	}
	$pagini = $conturi['data'];
	$aleasa = count( $pagini ) === 1 ? $pagini[0] : null;
	foreach ( $pagini as $p ) {
		if ( ! $aleasa && false !== stripos( remove_accents( $p['name'] ?? '' ), 'fotbal' ) ) {
			$aleasa = $p;
		}
	}
	if ( ! $aleasa || empty( $aleasa['access_token'] ) ) {
		return array( 'mesaj' => 'Tokenul vede mai multe pagini (' . implode( ', ', wp_list_pluck( $pagini, 'name' ) ) . '), dar niciuna nu conține „fotbal” în nume. Generează tokenul doar pentru pagina site-ului.' );
	}
	return array(
		'token'   => $aleasa['access_token'],
		'pagina'  => $aleasa['id'],
		'nume'    => $aleasa['name'] ?? '',
		'ig'      => $aleasa['instagram_business_account']['id'] ?? '',
		'ig_nume' => $aleasa['instagram_business_account']['username'] ?? '',
	);
}

/**
 * Hashtag-urile: cele fixe, competiția, cluburile și etichetele articolului.
 */
function fr_hashtag( $s ) {
	$s = preg_replace( '/[^a-z0-9]+/', '', strtolower( remove_accents( (string) $s ) ) );
	return strlen( $s ) >= 3 ? '#' . $s : '';
}

function fr_hashtaguri( $post_id, $max = 15 ) {
	$out = array( '#fotbalromanesc', '#fotbal' );
	$add = function ( $t ) use ( &$out ) {
		$h = fr_hashtag( $t );
		foreach ( $out as $x ) { // fără variante incluse în altele (#seria1 lângă #liga3seria1)
			if ( $h && false !== strpos( $x, substr( $h, 1 ) ) ) {
				return;
			}
		}
		if ( $h && ! in_array( $h, $out, true ) ) {
			$out[] = $h;
		}
	};
	foreach ( (array) get_the_terms( $post_id, 'competitie' ) as $t ) {
		if ( $t instanceof WP_Term ) {
			if ( $t->parent ) {
				$p = get_term( $t->parent, 'competitie' );
				if ( $p instanceof WP_Term ) {
					$add( $p->name );
				}
			}
			$add( 0 === strpos( $t->slug, 'liga-3-seria-' ) ? 'Liga 3 ' . $t->name : $t->name );
		}
	}
	foreach ( (array) get_the_terms( $post_id, 'club' ) as $t ) {
		if ( $t instanceof WP_Term ) {
			$add( $t->name );
		}
	}
	foreach ( wp_get_post_tags( $post_id, array( 'fields' => 'names' ) ) as $t ) {
		if ( ! preg_match( '/^\d{4}\/\d{4}$/', $t ) ) {
			$add( $t );
		}
	}
	return array_slice( $out, 0, $max );
}

function fr_text_social( $post_id, $retea ) {
	$titlu = html_entity_decode( wp_strip_all_tags( get_the_title( $post_id ) ), ENT_QUOTES, 'UTF-8' );
	$rez   = html_entity_decode( trim( wp_strip_all_tags( get_the_excerpt( $post_id ) ) ), ENT_QUOTES, 'UTF-8' );
	$tags  = implode( ' ', fr_hashtaguri( $post_id, 'ig' === $retea ? 20 : 8 ) );
	if ( 'ig' === $retea ) {
		return $titlu . "\n\n" . $rez . "\n\n📲 Tot articolul pe fotbalromanesc.ro (link în bio).\n\n" . $tags;
	}
	return $titlu . "\n\n" . $rez . "\n\n" . $tags;
}

function fr_publica_facebook( $post_id ) {
	$c = fr_social_cont();
	if ( ! $c['token'] || ! $c['pagina'] ) {
		return new WP_Error( 'fr_fb', 'Facebook neconfigurat.' );
	}
	fr_coperta_url( $post_id, 'og' ); // previzualizarea linkului o va folosi (og:image)
	$r = fr_graph( 'POST', rawurlencode( $c['pagina'] ) . '/feed', array(
		'message'      => fr_text_social( $post_id, 'fb' ),
		'link'         => get_permalink( $post_id ),
		'access_token' => $c['token'],
	) );
	return ! empty( $r['id'] ) ? $r['id'] : new WP_Error( 'fr_fb', (string) ( $r['error']['message'] ?? 'răspuns necunoscut' ) );
}

function fr_publica_instagram( $post_id ) {
	$c = fr_social_cont();
	if ( ! $c['token'] || ! $c['ig'] ) {
		return new WP_Error( 'fr_ig', 'Instagram neconfigurat (niciun cont profesional legat de pagină).' );
	}
	$lim = fr_graph( 'GET', rawurlencode( $c['ig'] ) . '/content_publishing_limit', array( 'fields' => 'quota_usage', 'access_token' => $c['token'] ) );
	if ( (int) ( $lim['data'][0]['quota_usage'] ?? 0 ) >= 45 ) {
		return new WP_Error( 'fr_ig', 'Sărit: aproape de limita Instagram de 50 de postări pe zi.' );
	}
	$img = fr_coperta_url( $post_id, 'ig', true );
	if ( ! $img ) {
		return new WP_Error( 'fr_ig', 'Nu am putut genera coperta.' );
	}
	$cont = fr_graph( 'POST', rawurlencode( $c['ig'] ) . '/media', array( 'image_url' => $img, 'caption' => fr_text_social( $post_id, 'ig' ), 'access_token' => $c['token'] ) );
	if ( empty( $cont['id'] ) ) {
		return new WP_Error( 'fr_ig', 'Container: ' . (string) ( $cont['error']['message'] ?? 'răspuns necunoscut' ) );
	}
	for ( $i = 0; $i < 10; $i++ ) {
		$s = fr_graph( 'GET', rawurlencode( $cont['id'] ), array( 'fields' => 'status_code', 'access_token' => $c['token'] ) );
		if ( 'FINISHED' === ( $s['status_code'] ?? '' ) ) {
			break;
		}
		if ( 'ERROR' === ( $s['status_code'] ?? '' ) ) {
			return new WP_Error( 'fr_ig', 'Instagram nu a putut procesa imaginea.' );
		}
		sleep( 3 );
	}
	$p = fr_graph( 'POST', rawurlencode( $c['ig'] ) . '/media_publish', array( 'creation_id' => $cont['id'], 'access_token' => $c['token'] ) );
	return ! empty( $p['id'] ) ? $p['id'] : new WP_Error( 'fr_ig', 'Publicare: ' . (string) ( $p['error']['message'] ?? 'răspuns necunoscut' ) );
}

/**
 * Postează articolul pe rețelele active (sau doar pe $retea). Rezultatul rămâne în meta: _fr_fb_id / _fr_ig_id / _fr_<retea>_eroare.
 */
function fr_social_ruleaza( $post_id, $retea = '', $din_nou = false ) {
	if ( 'publish' !== get_post_status( $post_id ) ) {
		return;
	}
	$c = fr_social_cont();
	foreach ( array( 'fb' => 'fr_publica_facebook', 'ig' => 'fr_publica_instagram' ) as $r => $fn ) {
		if ( ( $retea && $retea !== $r ) || ( ! $retea && ! $c[ $r . '_activ' ] ) ) {
			continue;
		}
		if ( ! $din_nou && get_post_meta( $post_id, '_fr_' . $r . '_id', true ) ) {
			continue;
		}
		$rez = $fn( $post_id );
		if ( is_wp_error( $rez ) ) {
			update_post_meta( $post_id, '_fr_' . $r . '_eroare', current_time( 'mysql' ) . ' · ' . $rez->get_error_message() );
		} else {
			update_post_meta( $post_id, '_fr_' . $r . '_id', $rez );
			update_post_meta( $post_id, '_fr_' . $r . '_data', current_time( 'mysql' ) );
			delete_post_meta( $post_id, '_fr_' . $r . '_eroare' );
		}
	}
}
add_action( 'fr_social_publica', 'fr_social_ruleaza' );

add_action( 'transition_post_status', function ( $nou, $vechi, $post ) {
	if ( 'publish' !== $nou || 'publish' === $vechi || 'post' !== $post->post_type ) {
		return;
	}
	$c = fr_social_cont();
	if ( ! $c['fb_activ'] && ! $c['ig_activ'] ) {
		return;
	}
	if ( strtotime( $post->post_date_gmt . ' UTC' ) < time() - 2 * DAY_IN_SECONDS ) {
		return; // articolele vechi nu se postează
	}
	if ( ! wp_next_scheduled( 'fr_social_publica', array( $post->ID ) ) ) {
		wp_schedule_single_event( time() + 120, 'fr_social_publica', array( $post->ID ) );
	}
}, 20, 3 );

/**
 * Cine poate folosi rutele de administrare: un administrator sau cererea cu cheia X-Fr-Key.
 */
function fr_permis_cheie( $req ) {
	$cheie = (string) $req->get_header( 'x_fr_key' );
	return current_user_can( 'manage_options' ) || ( '' !== $cheie && hash_equals( fr_cheie_actualizare(), $cheie ) );
}

add_action( 'rest_api_init', function () {
	// Starea conexiunii (fără token): pagina, contul de Instagram, valabilitatea, ultimele postări.
	register_rest_route( 'fr/v1', '/social', array(
		'methods'             => 'GET',
		'permission_callback' => 'fr_permis_cheie',
		'callback'            => function () {
			$c   = fr_social_cont();
			$dbg = $c['token'] ? fr_graph( 'GET', 'debug_token', array( 'input_token' => $c['token'], 'access_token' => $c['token'] ) ) : array();
			$d   = (array) ( $dbg['data'] ?? array() );
			$ult = array();
			foreach ( get_posts( array( 'numberposts' => 8 ) ) as $p ) {
				$ult[] = array(
					'id'        => $p->ID,
					'titlu'     => get_the_title( $p ),
					'fb'        => get_post_meta( $p->ID, '_fr_fb_id', true ) ?: get_post_meta( $p->ID, '_fr_fb_eroare', true ),
					'ig'        => get_post_meta( $p->ID, '_fr_ig_id', true ) ?: get_post_meta( $p->ID, '_fr_ig_eroare', true ),
				);
			}
			return array(
				'pagina'     => $c['nume'],
				'instagram'  => $c['ig_nume'],
				'fb_activ'   => $c['fb_activ'],
				'ig_activ'   => $c['ig_activ'],
				'token_valid' => $d['is_valid'] ?? null,
				'expira'     => isset( $d['expires_at'] ) ? ( 0 === (int) $d['expires_at'] ? 'niciodată' : wp_date( 'Y-m-d H:i', (int) $d['expires_at'] ) ) : null,
				'permisiuni' => $d['scopes'] ?? null,
				'articole'   => $ult,
			);
		},
	) );
	// Postează (din nou) un articol: /social/<id>?retea=fb|ig
	register_rest_route( 'fr/v1', '/social/(?P<id>\d+)', array(
		'methods'             => 'POST',
		'permission_callback' => 'fr_permis_cheie',
		'callback'            => function ( $req ) {
			$id    = (int) $req['id'];
			$retea = in_array( $req->get_param( 'retea' ), array( 'fb', 'ig' ), true ) ? $req->get_param( 'retea' ) : '';
			fr_social_ruleaza( $id, $retea, true );
			return array(
				'fb' => get_post_meta( $id, '_fr_fb_id', true ) ?: get_post_meta( $id, '_fr_fb_eroare', true ),
				'ig' => get_post_meta( $id, '_fr_ig_id', true ) ?: get_post_meta( $id, '_fr_ig_eroare', true ),
			);
		},
	) );
	// Previzualizarea, fără publicare: imaginile și textele.
	register_rest_route( 'fr/v1', '/social/(?P<id>\d+)/previzualizare', array(
		'methods'             => 'GET',
		'permission_callback' => 'fr_permis_cheie',
		'callback'            => function ( $req ) {
			$id = (int) $req['id'];
			return array(
				'ig_imagine' => fr_coperta_url( $id, 'ig', true ),
				'og_imagine' => fr_coperta_url( $id, 'og', true ),
				'ig_text'    => fr_text_social( $id, 'ig' ),
				'fb_text'    => fr_text_social( $id, 'fb' ),
			);
		},
	) );
} );

/**
 * Secțiunea din Aspect › fotbalromanesc.
 */
function fr_social_salveaza_formular() {
	$t = trim( sanitize_text_field( wp_unslash( $_POST['fr_meta_token'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$mesaj = '';
	if ( '' !== $t ) {
		$rez = fr_social_descopera( $t );
		if ( ! empty( $rez['pagina'] ) ) {
			update_option( 'fr_meta_token', $rez['token'], false );
			update_option( 'fr_fb_pagina_id', $rez['pagina'], false );
			update_option( 'fr_fb_pagina_nume', $rez['nume'], false );
			update_option( 'fr_ig_id', $rez['ig'], false );
			update_option( 'fr_ig_nume', $rez['ig_nume'], false );
			$mesaj = 'Pagina găsită: ' . $rez['nume'] . ( $rez['ig_nume'] ? '; Instagram: @' . $rez['ig_nume'] : '; niciun cont de Instagram legat de pagină' ) . '.';
		} else {
			$mesaj = 'Nu am putut folosi tokenul. ' . ( $rez['mesaj'] ?? '' );
		}
	}
	update_option( 'fr_fb_activ', ! empty( $_POST['fr_fb_activ'] ) ? 1 : 0, false ); // phpcs:ignore WordPress.Security.NonceVerification
	update_option( 'fr_ig_activ', ! empty( $_POST['fr_ig_activ'] ) ? 1 : 0, false ); // phpcs:ignore WordPress.Security.NonceVerification
	return $mesaj;
}

function fr_social_sectiune() {
	$c = fr_social_cont();
	?>
	<h2>Facebook și Instagram</h2>
	<p>
		Pagina: <strong><?php echo esc_html( $c['nume'] ?: 'neconectată' ); ?></strong> ·
		Instagram: <strong><?php echo esc_html( $c['ig_nume'] ? '@' . $c['ig_nume'] : 'neconectat' ); ?></strong>
	</p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'fr_actiune' ); ?>
		<input type="hidden" name="action" value="fr_actiune"><input type="hidden" name="ce" value="social">
		<p><input type="password" name="fr_meta_token" class="regular-text" autocomplete="off" placeholder="<?php echo esc_attr( $c['token'] ? 'token salvat (lipește altul ca să-l înlocuiești)' : 'tokenul Meta (EAA…)' ); ?>"></p>
		<p><label><input type="checkbox" name="fr_fb_activ" value="1" <?php checked( $c['fb_activ'] ); ?>> Postează automat articolele noi pe Facebook</label></p>
		<p><label><input type="checkbox" name="fr_ig_activ" value="1" <?php checked( $c['ig_activ'] ); ?>> Postează automat articolele noi pe Instagram</label></p>
		<?php submit_button( 'Salvează', 'secondary', 'submit', false ); ?>
	</form>
	<?php
	$ultimele = get_posts( array( 'numberposts' => 6 ) );
	if ( ! $ultimele ) {
		return;
	}
	echo '<table class="widefat striped" style="max-width: 900px; margin-top: 12px"><thead><tr><th style="width:130px">Imagine</th><th>Articol</th><th>Facebook</th><th>Instagram</th></tr></thead><tbody>';
	foreach ( $ultimele as $p ) {
		$img = fr_coperta_url( $p->ID, 'ig' );
		$st  = function ( $r ) use ( $p ) {
			$id  = get_post_meta( $p->ID, '_fr_' . $r . '_id', true );
			$err = get_post_meta( $p->ID, '_fr_' . $r . '_eroare', true );
			$txt = $id ? 'postat ' . get_post_meta( $p->ID, '_fr_' . $r . '_data', true ) : ( $err ? $err : '—' );
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=fr_social_acum&retea=' . $r . '&post=' . $p->ID ), 'fr_social_acum_' . $p->ID );
			return esc_html( $txt ) . '<br><a class="button button-small" href="' . esc_url( $url ) . '">' . ( $id ? 'Postează din nou' : 'Postează acum' ) . '</a>';
		};
		printf(
			'<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
			$img ? '<a href="' . esc_url( $img ) . '" target="_blank"><img src="' . esc_url( $img ) . '" width="108" height="135" alt=""></a>' : '—',
			esc_html( get_the_title( $p ) ),
			$st( 'fb' ), // phpcs:ignore WordPress.Security.EscapeOutput
			$st( 'ig' ) // phpcs:ignore WordPress.Security.EscapeOutput
		);
	}
	echo '</tbody></table>';
}

add_action( 'admin_post_fr_social_acum', function () {
	$id = (int) ( $_GET['post'] ?? 0 );
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'fr_social_acum_' . $id ) ) {
		wp_die( 'Nu ai voie.' );
	}
	$retea = in_array( $_GET['retea'] ?? '', array( 'fb', 'ig' ), true ) ? sanitize_key( $_GET['retea'] ) : '';
	fr_social_ruleaza( $id, $retea, true );
	wp_safe_redirect( admin_url( 'themes.php?page=fotbalromanesc&gata=social' ) );
	exit;
} );
