/* fotbalromanesc – filele clasamentului, căutarea și panoul cu ligi. Fără dependențe. */
( function () {
	'use strict';

	// Butoane care deschid un panou: data-fr-deschide="<id>".
	function comuta( id, deschide ) {
		var panou = document.getElementById( id );
		if ( ! panou ) {
			return;
		}
		var acum = typeof deschide === 'boolean' ? deschide : panou.hidden;
		panou.hidden = ! acum;
		document.querySelectorAll( '[data-fr-deschide="' + id + '"]' ).forEach( function ( b ) {
			b.setAttribute( 'aria-expanded', acum ? 'true' : 'false' );
		} );
		if ( acum ) {
			var camp = panou.querySelector( 'input[type="search"], button, a' );
			if ( camp ) {
				camp.focus();
			}
		}
	}

	document.addEventListener( 'click', function ( e ) {
		var b = e.target.closest( '[data-fr-deschide]' );
		if ( b ) {
			comuta( b.getAttribute( 'data-fr-deschide' ) );
			return;
		}
		var inchide = e.target.closest( '[data-fr-inchide]' );
		if ( inchide ) {
			comuta( inchide.getAttribute( 'data-fr-inchide' ), false );
			return;
		}
		// Clic pe fundalul panoului cu ligi îl închide.
		if ( e.target.id === 'fr-ligi' ) {
			comuta( 'fr-ligi', false );
		}
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( e.key !== 'Escape' ) {
			return;
		}
		[ 'fr-ligi', 'fr-cautare' ].forEach( function ( id ) {
			var p = document.getElementById( id );
			if ( p && ! p.hidden ) {
				comuta( id, false );
				var b = document.querySelector( '[data-fr-deschide="' + id + '"]' );
				if ( b ) {
					b.focus();
				}
			}
		} );
	} );

	// File (role="tablist") în blocurile cu data-fr-file.
	document.querySelectorAll( '[data-fr-file]' ).forEach( function ( bloc ) {
		var file = Array.prototype.slice.call( bloc.querySelectorAll( '[role="tab"]' ) );
		function alege( fila, focus ) {
			file.forEach( function ( f ) {
				var activ = f === fila;
				f.setAttribute( 'aria-selected', activ ? 'true' : 'false' );
				f.tabIndex = activ ? 0 : -1;
				var p = document.getElementById( f.getAttribute( 'aria-controls' ) );
				if ( p ) {
					p.hidden = ! activ;
				}
			} );
			if ( focus ) {
				fila.focus();
			}
		}
		file.forEach( function ( f, i ) {
			f.addEventListener( 'click', function () {
				alege( f, false );
			} );
			f.addEventListener( 'keydown', function ( e ) {
				var j = e.key === 'ArrowRight' ? i + 1 : e.key === 'ArrowLeft' ? i - 1 : null;
				if ( j === null ) {
					return;
				}
				e.preventDefault();
				alege( file[ ( j + file.length ) % file.length ], true );
			} );
		} );
	} );
} )();
