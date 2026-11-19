<?php
/**
 * Antetul: logo, meniul competițiilor, căutarea.
 *
 * @package fotbalromanesc
 */

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0B1F3A">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="fr-sari" href="#continut">Sari la conținut</a>

<header class="fr-antet">
	<div class="fr-container fr-antet__rand">
		<?php if ( has_custom_logo() ) : ?>
			<div class="fr-logo"><?php the_custom_logo(); ?></div>
		<?php else : ?>
			<a class="fr-logo fr-logo--text" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
				<?php
				$nume = get_bloginfo( 'name' );
				if ( preg_match( '/^(.*)(\.[a-z]{2,4})$/i', $nume, $m ) ) {
					echo esc_html( $m[1] ) . '<span>' . esc_html( $m[2] ) . '</span>';
				} else {
					echo esc_html( $nume );
				}
				?>
			</a>
		<?php endif; ?>

		<nav class="fr-nav" id="fr-nav" aria-label="Meniul principal">
			<?php
			wp_nav_menu( array(
				'theme_location' => 'principal',
				'container'      => false,
				'menu_class'     => 'fr-meniu',
				'depth'          => 1,
				'fallback_cb'    => 'fr_meniu_implicit',
			) );
			?>
		</nav>

		<button type="button" class="fr-buton-icon" data-fr-deschide="fr-cautare" aria-controls="fr-cautare" aria-expanded="false" aria-label="Caută">
			<?php echo fr_icon( 'cauta' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</button>
	</div>
	<div class="fr-cautare" id="fr-cautare" hidden>
		<div class="fr-container">
			<?php get_search_form(); ?>
		</div>
	</div>
</header>
