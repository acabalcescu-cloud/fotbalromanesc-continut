<?php
/**
 * Aspect › fotbalromanesc: depozitul de articole, sponsorul general, rețelele sociale, starea datelor.
 *
 * @package fotbalromanesc
 */

defined( 'ABSPATH' ) || exit;

function fr_setari_implicite() {
	return array(
		'repo'         => '',
		'ramura'       => 'main',
		'sponsor'      => '',
		'sponsor_url'  => '',
		'facebook'     => 'https://www.facebook.com/FotbalRomanesc.ro',
		'instagram'    => 'https://www.instagram.com/fotbalromanesc.ro/',
		'youtube'      => 'https://www.youtube.com/@fotbalromanesc_ro',
		'tiktok'       => 'https://www.tiktok.com/@fotbal.romanesc.ro',
		'text_subsol'  => 'Tot fotbalul românesc, de la SuperLiga la Liga 3, de la naționale la românii din străinătate.',
	);
}

function fr_setare( $cheie ) {
	$s = wp_parse_args( (array) get_option( 'fr_setari', array() ), fr_setari_implicite() );
	if ( 'repo' === $cheie ) {
		return fr_curata_repo( $s['repo'] );
	}
	// Rețelele: un câmp lăsat gol primește adresa implicită a site-ului.
	if ( in_array( $cheie, array( 'facebook', 'instagram', 'youtube', 'tiktok' ), true ) && empty( $s[ $cheie ] ) ) {
		return fr_setari_implicite()[ $cheie ];
	}
	return $s[ $cheie ] ?? '';
}

/**
 * „proprietar/nume” din orice formă: proprietar/nume, https://github.com/proprietar/nume(.git), git@github.com:proprietar/nume.git.
 */
function fr_curata_repo( $v ) {
	$v = trim( (string) $v );
	$v = preg_replace( '~^(https?:?/+)?(www\.)?github\.com[/:]~i', '', $v );
	$v = preg_replace( '~^git@github\.com:~i', '', $v );
	$v = preg_replace( '~\.git$~i', '', trim( $v, '/ ' ) );
	$parti = array_slice( explode( '/', $v ), 0, 2 );
	$v = implode( '/', $parti );
	return preg_match( '~^[A-Za-z0-9_.\-]+/[A-Za-z0-9_.\-]+$~', $v ) ? $v : '';
}

/**
 * 1.1.1: textul vechi din subsol, dacă n-a fost schimbat de mână, devine textul nou (o singură dată).
 */
add_action( 'init', 'fr_migrare_text_subsol' );
function fr_migrare_text_subsol() {
	if ( get_option( 'fr_migrare_111' ) ) {
		return;
	}
	$s = get_option( 'fr_setari' );
	if ( is_array( $s ) && isset( $s['text_subsol'] ) && 'Tot fotbalul românesc, de la SuperLiga la Liga 3.' === $s['text_subsol'] ) {
		$s['text_subsol'] = fr_setari_implicite()['text_subsol'];
		update_option( 'fr_setari', $s );
	}
	update_option( 'fr_migrare_111', 1, false );
}

add_action( 'admin_menu', 'fr_meniu_setari' );
function fr_meniu_setari() {
	add_theme_page( 'fotbalromanesc', 'fotbalromanesc', 'manage_options', 'fotbalromanesc', 'fr_pagina_setari' );
}

add_action( 'admin_init', 'fr_inregistreaza_setari' );
function fr_inregistreaza_setari() {
	register_setting( 'fr_setari', 'fr_setari', array( 'sanitize_callback' => 'fr_curata_setari', 'default' => fr_setari_implicite() ) );
}

function fr_curata_setari( $in ) {
	$in  = (array) $in;
	$out = fr_setari_implicite();
	$out['repo']        = fr_curata_repo( $in['repo'] ?? '' );
	$out['ramura']      = preg_replace( '~[^A-Za-z0-9_.\-/]~', '', (string) ( $in['ramura'] ?? 'main' ) ) ?: 'main';
	$out['sponsor']     = sanitize_text_field( $in['sponsor'] ?? '' );
	$out['text_subsol'] = sanitize_text_field( $in['text_subsol'] ?? '' );
	foreach ( array( 'sponsor_url', 'facebook', 'instagram', 'youtube', 'tiktok' ) as $k ) {
		$out[ $k ] = esc_url_raw( $in[ $k ] ?? '' );
	}
	return $out;
}

add_action( 'admin_post_fr_actiune', 'fr_actiune_admin' );
function fr_actiune_admin() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Nu ai voie.' );
	}
	check_admin_referer( 'fr_actiune' );
	$ce = sanitize_key( $_POST['ce'] ?? '' );
	if ( 'import' === $ce ) {
		fr_import_continut();
		fr_reimprospateaza_toate();
	} elseif ( 'date' === $ce ) {
		fr_reimprospateaza_toate();
	} elseif ( 'social' === $ce ) {
		set_transient( 'fr_mesaj_social', fr_social_salveaza_formular(), 60 );
	} elseif ( 'cheie' === $ce ) {
		delete_option( 'fr_cheie_actualizare' );
		fr_cheie_actualizare();
	}
	wp_safe_redirect( admin_url( 'themes.php?page=fotbalromanesc&gata=' . $ce ) );
	exit;
}

function fr_pagina_setari() {
	$s      = wp_parse_args( (array) get_option( 'fr_setari', array() ), fr_setari_implicite() );
	$jurnal = (array) get_option( 'fr_import_jurnal', array() );
	$camp   = function ( $k, $eticheta, $ajutor = '', $tip = 'text' ) use ( $s ) {
		printf(
			'<tr><th scope="row"><label for="fr_%1$s">%2$s</label></th><td><input type="%5$s" class="regular-text" id="fr_%1$s" name="fr_setari[%1$s]" value="%3$s">%4$s</td></tr>',
			esc_attr( $k ),
			esc_html( $eticheta ),
			esc_attr( $s[ $k ] ),
			$ajutor ? '<p class="description">' . esc_html( $ajutor ) . '</p>' : '',
			esc_attr( $tip )
		);
	};
	?>
	<div class="wrap">
		<h1>fotbalromanesc</h1>
		<?php if ( isset( $_GET['gata'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="notice notice-success"><p>Gata.</p></div>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'fr_setari' ); ?>
			<h2>Articole din GitHub</h2>
			<table class="form-table" role="presentation">
				<?php
				$camp( 'repo', 'Depozitul', 'Forma proprietar/nume, de exemplu acabalcescu-cloud/fotbalromanesc-continut. Gol = importul e oprit.' );
				$camp( 'ramura', 'Ramura', 'De obicei main.' );
				?>
			</table>
			<h2>Sponsor general și rețele</h2>
			<table class="form-table" role="presentation">
				<?php
				$camp( 'sponsor', 'Sponsorul site-ului', 'Apare în bara laterală, unde nu există un sponsor de ligă sau de club.' );
				$camp( 'sponsor_url', 'Adresa sponsorului', '', 'url' );
				$camp( 'facebook', 'Pagina de Facebook', '', 'url' );
				$camp( 'instagram', 'Instagram', '', 'url' );
				$camp( 'youtube', 'YouTube', '', 'url' );
				$camp( 'tiktok', 'TikTok', '', 'url' );
				$camp( 'text_subsol', 'Textul din subsol' );
				?>
			</table>
			<?php submit_button( 'Salvează' ); ?>
		</form>

		<hr>
		<h2>Importul</h2>
		<p>
			Ultima verificare: <strong><?php echo ! empty( $jurnal['verificat'] ) ? esc_html( wp_date( 'j M Y, H:i', $jurnal['verificat'] ) ) : '—'; ?></strong>.
			Următoarea: <strong><?php echo wp_next_scheduled( 'fr_import_continut' ) ? esc_html( wp_date( 'H:i', wp_next_scheduled( 'fr_import_continut' ) ) ) : '—'; ?></strong>.
		</p>
		<?php foreach ( array( 'importate' => 'Importate', 'erori' => 'Erori' ) as $k => $titlu ) : ?>
			<?php if ( ! empty( $jurnal[ $k ] ) ) : ?>
				<p><strong><?php echo esc_html( $titlu ); ?>:</strong></p>
				<ul style="list-style: disc; padding-left: 20px">
					<?php foreach ( array_slice( $jurnal[ $k ], 0, 30 ) as $linie ) : ?>
						<li><?php echo esc_html( $linie ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		<?php endforeach; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline-block; margin-right: 8px">
			<?php wp_nonce_field( 'fr_actiune' ); ?>
			<input type="hidden" name="action" value="fr_actiune"><input type="hidden" name="ce" value="import">
			<?php submit_button( 'Importă acum', 'secondary', 'submit', false ); ?>
		</form>

		<?php $fr_mesaj = get_transient( 'fr_mesaj_social' ); ?>
		<?php if ( $fr_mesaj ) : delete_transient( 'fr_mesaj_social' ); ?>
			<div class="notice notice-info"><p><?php echo esc_html( $fr_mesaj ); ?></p></div>
		<?php endif; ?>
		<?php fr_social_sectiune(); ?>

		<h2>Actualizarea temei</h2>
		<?php $fr_info = fr_info_tema_depozit(); ?>
		<p>
			Versiunea instalată: <strong><?php echo esc_html( fr_versiune_instalata() ); ?></strong>.
			În depozit: <strong><?php echo esc_html( $fr_info['versiune'] ?? '—' ); ?></strong>.
			Versiunile noi apar la Panou › Actualizări și se instalează automat.
		</p>
		<p>
			Cheia pentru actualizarea imediată (antetul <code>X-Fr-Key</code> la <code>POST /wp-json/fr/v1/tema</code>):<br>
			<input type="text" class="regular-text code" readonly value="<?php echo esc_attr( fr_cheie_actualizare() ); ?>" onclick="this.select()">
		</p>
		<p class="description">O dai doar prin formularul securizat al lui Claude, nu în chat sau pe email.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'fr_actiune' ); ?>
			<input type="hidden" name="action" value="fr_actiune"><input type="hidden" name="ce" value="cheie">
			<?php submit_button( 'Generează o cheie nouă', 'secondary', 'submit', false ); ?>
		</form>

		<h2>Datele competițiilor</h2>
		<table class="widefat striped" style="max-width: 900px">
			<thead><tr><th>Competiția</th><th>Sursa</th><th>Echipe</th><th>Meciuri</th><th>Actualizat</th></tr></thead>
			<tbody>
			<?php foreach ( fr_competitii_cu_date() as $t ) : ?>
				<?php $d = get_option( 'fr_date_' . $t->term_id ); ?>
				<tr>
					<td><a href="<?php echo esc_url( get_edit_term_link( $t->term_id, 'competitie' ) ); ?>"><?php echo esc_html( fr_eticheta_competitie( $t ) ); ?></a></td>
					<td><?php echo esc_html( get_term_meta( $t->term_id, 'fr_livescore', true ) || get_term_meta( $t->term_id, 'fr_echipa_livescore', true ) ? 'LiveScore' : 'JSON' ); ?></td>
					<td><?php echo is_array( $d ) ? count( $d['clasament'] ) : '—'; ?></td>
					<td><?php echo is_array( $d ) ? count( $d['meciuri'] ) : '—'; ?></td>
					<td><?php echo is_array( $d ) && ! empty( $d['actualizat'] ) ? esc_html( wp_date( 'j M, H:i', $d['actualizat'] ) ) : '—'; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 12px">
			<?php wp_nonce_field( 'fr_actiune' ); ?>
			<input type="hidden" name="action" value="fr_actiune"><input type="hidden" name="ce" value="date">
			<?php submit_button( 'Reîncarcă datele acum', 'secondary', 'submit', false ); ?>
		</form>
	</div>
	<?php
}
