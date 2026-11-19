<?php
/**
 * O pagină simplă (Despre noi, Contact, Colaboratori…).
 *
 * @package fotbalromanesc
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<main id="continut" class="fr-container fr-pagina-simpla">
		<article <?php post_class( 'fr-text' ); ?>>
			<header class="fr-text__cap">
				<h1 class="fr-text__titlu"><?php the_title(); ?></h1>
			</header>
			<div class="fr-text__continut">
				<?php
				the_content();
				wp_link_pages();
				?>
			</div>
		</article>
		<?php
		if ( comments_open() || get_comments_number() ) {
			comments_template();
		}
		?>
	</main>
	<?php
endwhile;

get_footer();
