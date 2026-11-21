<?php
/**
 * Rezumatele video (YouTube) din depozitul de conținut: date/video.json.
 *
 * {
 *   "video": {
 *     "<id articol>": [ { "id": "Al6Bq68J06M", "titlu": "REZUMAT: Farul - U Cluj 2-3", "canal": "Prima Sport", "data": "2026-11-20" } ]
 *   }
 * }
 *
 * Doar canale oficiale (ligă, federație, televiziuni cu drepturi, cluburi). Videoclipul se încarcă abia la clic
 * (miniatură + buton), de pe youtube-nocookie.com, ca pagina să rămână rapidă.
 *
 * @package fotbalromanesc
 */

defined( 'ABSPATH' ) || exit;

/**
 * Harta articol → videoclipuri, reîmprospătată la fiecare import (15 minute) și păstrată ca ultimă copie bună.
 */
function fr_video_harta( $proaspat = false ) {
	$h = get_option( 'fr_video_harta' );
	if ( ! $proaspat && is_array( $h ) ) {
		return $h;
	}
	if ( ! fr_setare( 'repo' ) ) {
		return array();
	}
	$d = fr_repo_json( 'date/video.json' );
	if ( is_wp_error( $d ) ) {
		return is_array( $h ) ? $h : array();
	}
	$curat = array();
	foreach ( (array) ( $d['video'] ?? array() ) as $articol => $lista ) {
		foreach ( (array) $lista as $v ) {
			$id = (string) ( $v['id'] ?? '' );
			if ( ! preg_match( '/^[A-Za-z0-9_-]{11}$/', $id ) ) {
				continue;
			}
			$curat[ sanitize_text_field( $articol ) ][] = array(
				'id'    => $id,
				'titlu' => sanitize_text_field( $v['titlu'] ?? '' ),
				'canal' => sanitize_text_field( $v['canal'] ?? '' ),
				'data'  => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $v['data'] ?? '' ) ) ? $v['data'] : '',
			);
		}
	}
	update_option( 'fr_video_harta', $curat, true );
	return $curat;
}
add_action( 'fr_import_continut', function () {
	fr_video_harta( true );
}, 20 );

/**
 * Videoclipurile unui articol (după id-ul lui din depozit).
 */
function fr_video_articol( $post = null ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return array();
	}
	$sursa = (string) get_post_meta( $post->ID, '_fr_sursa', true );
	$h     = fr_video_harta();
	return ( '' !== $sursa && ! empty( $h[ $sursa ] ) ) ? $h[ $sursa ] : array();
}

function fr_video_html( $lista ) {
	ob_start();
	?>
	<section class="fr-video" aria-labelledby="fr-video-t">
		<h2 id="fr-video-t" class="fr-video__titlu">Rezumat video</h2>
		<?php foreach ( $lista as $v ) : ?>
			<figure class="fr-video__item">
				<div class="fr-video__cadru" data-yt="<?php echo esc_attr( $v['id'] ); ?>">
					<button type="button" class="fr-video__play" aria-label="<?php echo esc_attr( 'Pornește videoclipul: ' . ( $v['titlu'] ?: 'rezumat' ) ); ?>">
						<img src="<?php echo esc_url( 'https://i.ytimg.com/vi/' . $v['id'] . '/hqdefault.jpg' ); ?>" alt="" loading="lazy" width="480" height="360">
						<span class="fr-video__icon" aria-hidden="true"></span>
					</button>
				</div>
				<figcaption class="fr-video__sursa"><?php echo esc_html( trim( ( $v['titlu'] ? $v['titlu'] . ' · ' : '' ) . 'Video: ' . ( $v['canal'] ?: 'YouTube' ) ) ); ?></figcaption>
			</figure>
		<?php endforeach; ?>
	</section>
	<?php
	return ob_get_clean();
}

// În articol: după primul paragraf (deschiderea), ca rezumatul să se vadă fără derulare.
add_filter( 'the_content', function ( $html ) {
	if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
		return $html;
	}
	$lista = fr_video_articol();
	if ( ! $lista ) {
		return $html;
	}
	$bloc = fr_video_html( $lista );
	$poz  = stripos( $html, '</p>' );
	return false === $poz ? $bloc . $html : substr( $html, 0, $poz + 4 ) . $bloc . substr( $html, $poz + 4 );
}, 20 );

// Datele structurate VideoObject (Google poate arăta articolul cu eticheta video).
add_action( 'wp_head', function () {
	if ( ! is_singular( 'post' ) ) {
		return;
	}
	$lista = fr_video_articol();
	if ( ! $lista ) {
		return;
	}
	$obiecte = array();
	foreach ( $lista as $v ) {
		$o = array(
			'@type'        => 'VideoObject',
			'name'         => $v['titlu'] ?: get_the_title(),
			'description'  => wp_strip_all_tags( get_the_excerpt() ) ?: get_the_title(),
			'thumbnailUrl' => 'https://i.ytimg.com/vi/' . $v['id'] . '/hqdefault.jpg',
			'embedUrl'     => 'https://www.youtube.com/embed/' . $v['id'],
			'uploadDate'   => $v['data'] ?: get_the_date( 'Y-m-d' ),
		);
		$obiecte[] = $o;
	}
	echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $obiecte ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
}, 30 );

// Eticheta „Video” pe cardurile articolelor care au rezumat.
add_filter( 'fr_are_video', fn( $x, $post ) => (bool) fr_video_articol( $post ), 10, 2 );
