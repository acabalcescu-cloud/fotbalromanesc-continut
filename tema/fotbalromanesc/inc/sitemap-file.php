<?php
/**
 * Sitemap cu filele „clasament” și „meciuri” ale competițiilor cu date (wp-sitemap-filefr-1.xml).
 *
 * @package fotbalromanesc
 */

defined( 'ABSPATH' ) || exit;

class FR_Sitemap_File extends WP_Sitemaps_Provider {
	public function __construct() {
		$this->name        = 'filefr';
		$this->object_type = 'filefr';
	}

	public function get_url_list( $page_num, $object_subtype = '' ) {
		$lista = array();
		foreach ( fr_competitii_cu_date() as $t ) {
			$d = fr_date_competitie( $t->term_id );
			if ( ! $d ) {
				continue;
			}
			$mod = ! empty( $d['actualizat'] ) ? gmdate( 'c', (int) $d['actualizat'] ) : null;
			foreach ( array( 'clasament' => ! empty( $d['clasament'] ), 'meciuri' => ! empty( $d['meciuri'] ) ) as $fila => $are ) {
				if ( $are ) {
					$lista[] = array_filter( array( 'loc' => fr_url_fila( $fila, $t ), 'lastmod' => $mod ) );
				}
			}
		}
		return $lista;
	}

	public function get_max_num_pages( $object_subtype = '' ) {
		return 1;
	}
}
