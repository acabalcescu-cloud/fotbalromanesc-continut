<?php
/**
 * Competiții (SuperLiga, Liga 2, Liga 3 › Seria 1…8, Naționala…) și cluburi.
 * Fiecare articol primește o competiție și, opțional, cluburile despre care e.
 *
 * @package fotbalromanesc
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'fr_inregistreaza_taxonomii', 5 );
function fr_inregistreaza_taxonomii() {
	register_taxonomy( 'competitie', array( 'post' ), array(
		'labels'            => array(
			'name'          => __( 'Competiții', 'fotbalromanesc' ),
			'singular_name' => __( 'Competiție', 'fotbalromanesc' ),
			'add_new_item'  => __( 'Adaugă o competiție', 'fotbalromanesc' ),
			'edit_item'     => __( 'Editează competiția', 'fotbalromanesc' ),
			'search_items'  => __( 'Caută competiții', 'fotbalromanesc' ),
			'parent_item'   => __( 'Competiția-părinte', 'fotbalromanesc' ),
		),
		'hierarchical'      => true,
		'public'            => true,
		'show_in_rest'      => true,
		'show_admin_column' => true,
		'rewrite'           => array( 'slug' => 'competitie', 'hierarchical' => false ),
	) );
	register_taxonomy( 'club', array( 'post' ), array(
		'labels'            => array(
			'name'          => __( 'Cluburi', 'fotbalromanesc' ),
			'singular_name' => __( 'Club', 'fotbalromanesc' ),
			'add_new_item'  => __( 'Adaugă un club', 'fotbalromanesc' ),
			'edit_item'     => __( 'Editează clubul', 'fotbalromanesc' ),
			'search_items'  => __( 'Caută cluburi', 'fotbalromanesc' ),
		),
		'hierarchical'      => false,
		'public'            => true,
		'show_in_rest'      => true,
		'show_admin_column' => true,
		'rewrite'           => array( 'slug' => 'club' ),
	) );
}

/**
 * Câmpurile din admin. Cheie => [etichetă, tip, ajutor].
 */
function fr_campuri_competitie() {
	return array(
		'fr_ordine'      => array( 'Ordinea în meniu', 'number', 'Numerele mici apar primele.' ),
		'fr_livescore'   => array( 'Sursa LiveScore', 'text', 'Calea competiției în LiveScore, de exemplu romania/liga-1 sau romania/liga-ii. Gol = fără date automate.' ),
		'fr_echipa_livescore' => array( 'Echipa în LiveScore (naționale)', 'text', 'Pentru naționale: numele exact al echipei, ex. Romania sau Romania U21. Tema adună meciurile ei din toate competițiile (preliminarii, Liga Națiunilor, amicale).' ),
		'fr_date_json'   => array( 'Fișier JSON cu date', 'url', 'Pentru competițiile fără LiveScore (Liga 3): adresa unui fișier cu clasamentul și meciurile. Formatul e descris în README.' ),
		'fr_neoficial'   => array( 'Date neoficiale', 'checkbox', 'Afișează mențiunea „date neoficiale” lângă clasament.' ),
		'fr_pe_prima'    => array( 'Pe prima pagină', 'checkbox', 'Meciurile apar în banda de sus, iar clasamentul în bara laterală.' ),
		'fr_sponsor'     => array( 'Sponsor', 'text', 'Numele sponsorului paginii.' ),
		'fr_sponsor_url' => array( 'Adresa sponsorului', 'url', '' ),
	);
}

function fr_campuri_club() {
	return array(
		'fr_livescore_nume' => array( 'Numele în LiveScore', 'text', 'Exact ca în date, de exemplu „Chindia Targoviste”. Leagă clubul de clasament și de meciuri.' ),
		'fr_competitie'     => array( 'Competiția', 'competitie', 'Liga în care joacă acum.' ),
		'fr_sigla'          => array( 'Sigla (adresă imagine)', 'url', 'Imagine pătrată, de preferință PNG transparent.' ),
		'fr_colaborator'    => array( 'Colaboratorul clubului', 'user', 'Autorul care scrie opinia despre club.' ),
		'fr_sponsor'        => array( 'Sponsor', 'text', 'Firma locală care susține pagina clubului.' ),
		'fr_sponsor_url'    => array( 'Adresa sponsorului', 'url', '' ),
	);
}

foreach ( array( 'competitie', 'club' ) as $fr_tax ) {
	add_action( $fr_tax . '_add_form_fields', 'fr_campuri_adauga' );
	add_action( $fr_tax . '_edit_form_fields', 'fr_campuri_editeaza' );
	add_action( 'created_' . $fr_tax, 'fr_campuri_salveaza' );
	add_action( 'edited_' . $fr_tax, 'fr_campuri_salveaza' );
}
unset( $fr_tax );

function fr_campuri_pentru( $taxonomie ) {
	return 'club' === $taxonomie ? fr_campuri_club() : fr_campuri_competitie();
}

function fr_camp_html( $cheie, $def, $valoare ) {
	list( , $tip ) = $def;
	$id = esc_attr( $cheie );
	switch ( $tip ) {
		case 'checkbox':
			return sprintf( '<input type="checkbox" id="%1$s" name="%1$s" value="1"%2$s>', $id, checked( $valoare, '1', false ) );
		case 'number':
			return sprintf( '<input type="number" id="%1$s" name="%1$s" value="%2$s" class="small-text">', $id, esc_attr( $valoare ) );
		case 'competitie':
			return wp_dropdown_categories( array(
				'taxonomy'         => 'competitie',
				'name'             => $cheie,
				'id'               => $cheie,
				'selected'         => (int) $valoare,
				'hide_empty'       => false,
				'hierarchical'     => true,
				'show_option_none' => '—',
				'option_none_value' => '0',
				'echo'             => false,
			) );
		case 'user':
			return wp_dropdown_users( array(
				'name'             => $cheie,
				'id'               => $cheie,
				'selected'         => (int) $valoare,
				'show_option_none' => '—',
				'capability'       => array( 'edit_posts' ),
				'echo'             => false,
			) );
		default:
			return sprintf( '<input type="%3$s" id="%1$s" name="%1$s" value="%2$s" class="regular-text">', $id, esc_attr( $valoare ), 'url' === $tip ? 'url' : 'text' );
	}
}

function fr_campuri_adauga( $taxonomie ) {
	wp_nonce_field( 'fr_campuri', 'fr_campuri_nonce' );
	foreach ( fr_campuri_pentru( $taxonomie ) as $cheie => $def ) {
		printf(
			'<div class="form-field"><label for="%s">%s</label>%s<p>%s</p></div>',
			esc_attr( $cheie ),
			esc_html( $def[0] ),
			fr_camp_html( $cheie, $def, '' ), // phpcs:ignore WordPress.Security.EscapeOutput
			esc_html( $def[2] )
		);
	}
}

function fr_campuri_editeaza( $term ) {
	wp_nonce_field( 'fr_campuri', 'fr_campuri_nonce' );
	foreach ( fr_campuri_pentru( $term->taxonomy ) as $cheie => $def ) {
		printf(
			'<tr class="form-field"><th scope="row"><label for="%s">%s</label></th><td>%s<p class="description">%s</p></td></tr>',
			esc_attr( $cheie ),
			esc_html( $def[0] ),
			fr_camp_html( $cheie, $def, get_term_meta( $term->term_id, $cheie, true ) ), // phpcs:ignore WordPress.Security.EscapeOutput
			esc_html( $def[2] )
		);
	}
}

function fr_campuri_salveaza( $term_id ) {
	if ( ! isset( $_POST['fr_campuri_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['fr_campuri_nonce'] ), 'fr_campuri' ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}
	$term = get_term( $term_id );
	if ( ! $term || is_wp_error( $term ) ) {
		return;
	}
	foreach ( fr_campuri_pentru( $term->taxonomy ) as $cheie => $def ) {
		$val = isset( $_POST[ $cheie ] ) ? wp_unslash( $_POST[ $cheie ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		switch ( $def[1] ) {
			case 'checkbox':
				$val = $val ? '1' : '';
				break;
			case 'number':
			case 'competitie':
			case 'user':
				$val = (string) max( 0, (int) $val );
				break;
			case 'url':
				$val = esc_url_raw( $val );
				break;
			default:
				$val = sanitize_text_field( $val );
		}
		if ( '' === $val || '0' === $val ) {
			delete_term_meta( $term_id, $cheie );
		} else {
			update_term_meta( $term_id, $cheie, $val );
		}
	}
	if ( 'competitie' === $term->taxonomy ) {
		fr_goleste_cache_date( $term_id );
	}
}

/**
 * Competiția principală a unui articol (prima, preferând un termen-copil, ex. „Liga 3 › Seria 1”).
 */
function fr_competitia_articolului( $post_id = 0 ) {
	$termeni = get_the_terms( $post_id ?: get_the_ID(), 'competitie' );
	if ( ! $termeni || is_wp_error( $termeni ) ) {
		return null;
	}
	usort( $termeni, fn( $a, $b ) => (int) ( 0 === $a->parent ) - (int) ( 0 === $b->parent ) );
	return $termeni[0];
}

/**
 * Eticheta competiției: „Liga 3 · Seria 1” pentru o serie, altfel numele.
 */
function fr_eticheta_competitie( $term ) {
	if ( ! $term ) {
		return '';
	}
	if ( $term->parent ) {
		$p = get_term( $term->parent, 'competitie' );
		if ( $p && ! is_wp_error( $p ) ) {
			return $p->name . ' · ' . $term->name;
		}
	}
	return $term->name;
}
