<?php
/**
 * Subsolul și meniul de jos de pe telefon.
 *
 * @package fotbalromanesc
 */

defined( 'ABSPATH' ) || exit;

$fr_program = fr_url_program();
?>
<footer class="fr-subsol">
	<div class="fr-container fr-subsol__rand">
		<div>
			<p class="fr-subsol__nume"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
			<p><?php echo esc_html( fr_setare( 'text_subsol' ) ); ?></p>
			<p class="fr-subsol__mic">Clasamentele calculate de noi sunt neoficiale. Datele competițiilor: LiveScore și presa locală.</p>
		</div>
		<?php
		wp_nav_menu( array(
			'theme_location' => 'subsol',
			'container'      => 'nav',
			'container_attr' => array( 'aria-label' => 'Subsol' ),
			'menu_class'     => 'fr-meniu-subsol',
			'depth'          => 1,
			'fallback_cb'    => false,
		) );
		?>
		<ul class="fr-sociale">
			<?php foreach ( array( 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'tiktok' => 'TikTok' ) as $k => $n ) : ?>
				<?php if ( fr_setare( $k ) ) : ?>
					<li><a href="<?php echo esc_url( fr_setare( $k ) ); ?>" rel="noopener" target="_blank"><?php echo esc_html( $n ); ?></a></li>
				<?php endif; ?>
			<?php endforeach; ?>
			<li><a href="<?php echo esc_url( get_feed_link() ); ?>">RSS</a></li>
		</ul>
	</div>
</footer>

<nav class="fr-jos" aria-label="Navigare rapidă">
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>"<?php echo is_front_page() ? ' aria-current="page"' : ''; ?>><?php echo fr_icon( 'acasa' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>Acasă</a>
	<a href="<?php echo esc_url( $fr_program ); ?>"<?php echo is_page_template( 'page-templates/program.php' ) ? ' aria-current="page"' : ''; ?>><?php echo fr_icon( 'meciuri' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>Meciuri</a>
	<button type="button" data-fr-deschide="fr-ligi" aria-controls="fr-ligi" aria-expanded="false"><?php echo fr_icon( 'ligi' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>Ligi</button>
	<button type="button" data-fr-deschide="fr-cautare" aria-controls="fr-cautare" aria-expanded="false"><?php echo fr_icon( 'cauta' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>Caută</button>
</nav>

<div class="fr-ligi" id="fr-ligi" hidden>
	<div class="fr-ligi__panou" role="dialog" aria-modal="true" aria-labelledby="fr-ligi-titlu">
		<div class="fr-ligi__cap">
			<h2 id="fr-ligi-titlu" class="fr-titlu-sectiune">Ligi și competiții</h2>
			<button type="button" class="fr-buton-icon fr-buton-icon--deschis" data-fr-inchide="fr-ligi" aria-label="Închide"><?php echo fr_icon( 'inchide' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
		</div>
		<ul class="fr-ligi__lista">
		<?php
		wp_list_categories( array(
			'taxonomy'   => 'competitie',
			'title_li'   => '',
			'hide_empty' => false,
			'orderby'    => 'meta_value_num',
			'meta_key'   => 'fr_ordine',
			'style'      => 'list',
		) );
		?>
		</ul>
	</div>
</div>

<?php wp_footer(); ?>
</body>
</html>
