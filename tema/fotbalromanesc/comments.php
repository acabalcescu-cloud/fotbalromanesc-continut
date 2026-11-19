<?php
/**
 * Comentariile.
 *
 * @package fotbalromanesc
 */

defined( 'ABSPATH' ) || exit;

if ( post_password_required() ) {
	return;
}
?>
<section id="comentarii" class="fr-comentarii fr-card">
	<?php if ( have_comments() ) : ?>
		<h2 class="fr-titlu-sectiune">
			<?php
			$fr_n = get_comments_number();
			echo esc_html( 1 === (int) $fr_n ? 'Un comentariu' : $fr_n . ' comentarii' );
			?>
		</h2>
		<ol class="fr-comentarii__lista">
			<?php wp_list_comments( array( 'style' => 'ol', 'short_ping' => true, 'avatar_size' => 40 ) ); ?>
		</ol>
		<?php the_comments_navigation(); ?>
	<?php endif; ?>
	<?php
	comment_form( array(
		'title_reply'        => 'Spune-ți părerea',
		'label_submit'       => 'Trimite',
		'comment_notes_before' => '<p class="fr-nota">Adresa de email nu se publică. Comentariile jignitoare se șterg.</p>',
	) );
	?>
</section>
