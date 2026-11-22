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

// Rezumatul video: playerul YouTube se încarcă abia la clic.
( function () {
	document.querySelectorAll( '.fr-video__cadru[data-yt]' ).forEach( function ( c ) {
		var b = c.querySelector( '.fr-video__play' );
		if ( ! b ) {
			return;
		}
		b.addEventListener( 'click', function () {
			var f = document.createElement( 'iframe' );
			f.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent( c.getAttribute( 'data-yt' ) ) + '?autoplay=1&rel=0';
			f.title = b.getAttribute( 'aria-label' ) || 'Rezumat video';
			f.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
			f.allowFullscreen = true;
			f.referrerPolicy = 'strict-origin-when-cross-origin';
			c.innerHTML = '';
			c.appendChild( f );
			f.focus();
		} );
	} );
} )();

// Scorurile live: la 45 de secunde, cât pagina e vizibilă, actualizăm meciurile marcate cu data-meci.
( function () {
	var elemente = document.querySelectorAll( '[data-meci]' );
	if ( ! elemente.length || ! window.fetch ) {
		return;
	}
	var url = ( window.frLive && window.frLive.url ) || '/wp-json/fr/v1/live';
	function aplica( date ) {
		var m = ( date && date.meciuri ) || {};
		document.querySelectorAll( '[data-meci]' ).forEach( function ( el ) {
			var d = m[ el.getAttribute( 'data-meci' ) ];
			if ( ! d ) {
				return;
			}
			var s = el.querySelector( '.js-scor' );
			if ( s && s.textContent !== d.scor ) {
				s.textContent = d.scor;
			}
			var t = el.querySelector( '.js-stare' );
			if ( t ) {
				if ( t.tagName === 'SMALL' ) {
					t.hidden = d.stare !== 'live';
					t.textContent = d.stare === 'live' ? d.sus : '';
				} else {
					t.innerHTML = '';
					if ( d.stare === 'live' ) {
						var b = document.createElement( 'b' );
						b.className = 'fr-live';
						b.textContent = d.sus;
						t.appendChild( b );
					} else {
						t.textContent = d.sus;
					}
				}
			}
			[ 'live', 'programat', 'terminat', 'amanat', 'asteptare' ].forEach( function ( c ) {
				el.classList.toggle( 'fr-meci--' + c, c === d.stare );
			} );
		} );
	}
	function cere() {
		if ( document.hidden ) {
			return;
		}
		fetch( url + '?t=' + Date.now(), { credentials: 'omit' } ).then( function ( r ) {
			return r.ok ? r.json() : null;
		} ).then( aplica ).catch( function () {} );
	}
	cere();
	setInterval( cere, 45000 );
	document.addEventListener( 'visibilitychange', function () {
		if ( ! document.hidden ) {
			cere();
		}
	} );
} )();
