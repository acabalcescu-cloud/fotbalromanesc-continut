<?php
/**
 * Pagina care nu există.
 *
 * @package fotbalromanesc
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="continut" class="fr-container fr-pagina-simpla">
	<div class="fr-card fr-gol-mare">
		<p class="fr-scor-404" aria-hidden="true">0-4</p>
		<h1 class="fr-titlu-mare">Pagina nu există</h1>
		<p>Poate a fost mutată sau adresa e greșită. Caută ce te interesează:</p>
		<?php get_search_form(); ?>
		<p><a class="fr-buton" href="<?php echo esc_url( home_url( '/' ) ); ?>">Prima pagină</a></p>
	</div>
</main>
<?php
get_footer();
