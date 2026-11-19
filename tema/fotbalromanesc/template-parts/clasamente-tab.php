<?php
/**
 * Clasamentele competițiilor de pe prima pagină, cu file (primele 10 echipe).
 *
 * @package fotbalromanesc
 */

defined( 'ABSPATH' ) || exit;

$fr_comps = array_values( array_filter(
	fr_competitii_cu_date( true ),
	function ( $t ) {
		$d = fr_date_competitie( $t->term_id );
		return $d && $d['clasament'];
	}
) );
if ( ! $fr_comps ) {
	return;
}
$fr_uid = 'fr-cl-' . wp_unique_id();
?>
<section class="fr-card fr-clasamente" data-fr-file aria-label="Clasamente">
	<div class="fr-pastile" role="tablist" aria-label="Alege competiția">
		<?php foreach ( $fr_comps as $i => $t ) : ?>
			<button type="button" role="tab" class="fr-pastila" id="<?php echo esc_attr( $fr_uid . '-t' . $i ); ?>" aria-controls="<?php echo esc_attr( $fr_uid . '-p' . $i ); ?>" aria-selected="<?php echo 0 === $i ? 'true' : 'false'; ?>"<?php echo 0 === $i ? '' : ' tabindex="-1"'; ?>><?php echo esc_html( fr_eticheta_competitie( $t ) ); ?></button>
		<?php endforeach; ?>
	</div>
	<?php foreach ( $fr_comps as $i => $t ) : ?>
		<div role="tabpanel" id="<?php echo esc_attr( $fr_uid . '-p' . $i ); ?>" aria-labelledby="<?php echo esc_attr( $fr_uid . '-t' . $i ); ?>"<?php echo 0 === $i ? '' : ' hidden'; ?>>
			<?php echo fr_tabel_clasament( fr_date_competitie( $t->term_id ), array( 'max' => 10 ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<a class="fr-link-sectiune" href="<?php echo esc_url( fr_url_fila( 'clasament', $t ) ); ?>">Clasamentul complet</a>
		</div>
	<?php endforeach; ?>
</section>
