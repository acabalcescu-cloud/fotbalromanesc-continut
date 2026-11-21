<?php
/**
 * Template Name: Program și rezultate
 *
 * Meciurile tuturor competițiilor cu date, pe zile: rezultatele din ultimele 3 zile și programul pe 7 zile.
 * Cu ?competitie=<id> se vede o singură competiție.
 *
 * @package fotbalromanesc
 */

defined( 'ABSPATH' ) || exit;

get_header();

$fr_toate = fr_competitii_cu_date();
$fr_ales  = isset( $_GET['competitie'] ) ? absint( $_GET['competitie'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
$fr_lista = $fr_ales ? array_values( array_filter( $fr_toate, fn( $t ) => $t->term_id === $fr_ales ) ) : $fr_toate;
$fr_azi   = ( new DateTime( 'today', new DateTimeZone( 'Europe/Bucharest' ) ) )->getTimestamp();
$fr_rez   = array_reverse( fr_meciuri_interval( $fr_lista, $fr_azi - 3 * DAY_IN_SECONDS, time() ) );
$fr_rez   = array_values( array_filter( $fr_rez, fn( $m ) => 'programat' !== $m['stare'] ) );
$fr_prog  = fr_meciuri_interval( $fr_lista, $fr_azi, $fr_azi + 8 * DAY_IN_SECONDS );
$fr_prog  = array_values( array_filter( $fr_prog, fn( $m ) => ! in_array( $m['stare'], array( 'terminat', 'asteptare' ), true ) ) );
?>

<section class="fr-pagina-cap">
	<div class="fr-container">
		<h1 class="fr-titlu-mare"><?php the_title(); ?></h1>
		<nav class="fr-pastile fr-pastile--link" aria-label="Competiții">
			<a class="fr-pastila" href="<?php echo esc_url( get_permalink() ); ?>"<?php echo $fr_ales ? '' : ' aria-current="page"'; ?>>Toate</a>
			<?php foreach ( $fr_toate as $t ) : ?>
				<a class="fr-pastila" href="<?php echo esc_url( add_query_arg( 'competitie', $t->term_id, get_permalink() ) ); ?>"<?php echo $t->term_id === $fr_ales ? ' aria-current="page"' : ''; ?>><?php echo esc_html( fr_eticheta_competitie( $t ) ); ?></a>
			<?php endforeach; ?>
		</nav>
	</div>
</section>

<main id="continut" class="fr-container fr-layout">
	<div class="fr-layout__principal">
		<section class="fr-card">
			<?php echo fr_titlu_sectiune( 'Programul' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php echo fr_lista_meciuri( $fr_prog, true ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</section>
		<section class="fr-card">
			<?php echo fr_titlu_sectiune( 'Rezultate recente' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php echo fr_lista_meciuri( $fr_rez, true ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</section>
	</div>
	<aside class="fr-layout__lateral">
		<?php get_template_part( 'template-parts/clasamente-tab' ); ?>
		<?php echo fr_sponsor(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	</aside>
</main>

<?php
get_footer();
