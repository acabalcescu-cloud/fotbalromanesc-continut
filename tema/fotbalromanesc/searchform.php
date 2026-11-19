<?php
/**
 * Formularul de căutare.
 *
 * @package fotbalromanesc
 */

defined( 'ABSPATH' ) || exit;

$fr_id = 'fr-s-' . wp_unique_id();
?>
<form role="search" method="get" class="fr-form-cautare" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="fr-ascuns" for="<?php echo esc_attr( $fr_id ); ?>">Caută pe site</label>
	<input type="search" id="<?php echo esc_attr( $fr_id ); ?>" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="Echipă, jucător, competiție…">
	<button type="submit" class="fr-buton">Caută</button>
</form>
