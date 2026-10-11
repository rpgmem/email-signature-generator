/**
 * Settings → Email Signature.
 *
 * Picks the logo from the media library: the chosen attachment's id goes
 * into the hidden field, and "Use the bundled logo" sets it back to 0.
 *
 * Saves each on/off toggle the moment it is flipped. Every toggle is also a
 * field of the form, so "Save Changes" posts its current state; text fields
 * still wait for that button.
 */
( function () {
	'use strict';

	var settings = window.esgAdmin || {};
	var LINGER = 1800;

	/**
	 * The value a toggle saves: '1' / '0', or every ticked model.
	 *
	 * @param {HTMLInputElement} input The flipped toggle.
	 * @return {string|string[]} Value to post.
	 */
	function valueOf( input ) {
		if ( 'models' !== input.getAttribute( 'data-esg-autosave' ) ) {
			return input.checked ? '1' : '0';
		}
		return Array.prototype.map.call(
			document.querySelectorAll( 'input[data-esg-autosave="models"]:checked' ),
			function ( box ) {
				return box.value;
			}
		);
	}

	/**
	 * Show the stored value again: one box, or the whole models group.
	 *
	 * @param {HTMLInputElement} input The flipped toggle.
	 * @param {boolean|string[]} value Stored value.
	 */
	function show( input, value ) {
		if ( Array.isArray( value ) ) {
			document.querySelectorAll( 'input[data-esg-autosave="models"]' ).forEach( function ( box ) {
				box.checked = -1 !== value.indexOf( box.value );
			} );
			return;
		}
		input.checked = !! value;
	}

	/**
	 * The status badge beside a toggle, created on first use.
	 *
	 * @param {HTMLInputElement} input Toggle.
	 * @return {HTMLElement} Badge.
	 */
	function badgeFor( input ) {
		var anchor = input.closest( '.esg-toggle' ) || input;
		var badge = anchor.nextElementSibling;
		if ( ! badge || ! badge.classList.contains( 'esg-autosave-badge' ) ) {
			badge = document.createElement( 'span' );
			badge.className = 'esg-autosave-badge';
			badge.setAttribute( 'role', 'status' );
			anchor.parentNode.insertBefore( badge, anchor.nextSibling );
		}
		return badge;
	}

	function setBadge( badge, state, text ) {
		badge.className = 'esg-autosave-badge esg-autosave-badge--' + state;
		badge.textContent = text;
		badge.hidden = false;
	}

	/**
	 * Post one toggle.
	 *
	 * @param {HTMLInputElement} input The flipped toggle.
	 */
	function save( input ) {
		var key = input.getAttribute( 'data-esg-autosave' );
		var before = ! input.checked;
		var value = valueOf( input );
		var badge = badgeFor( input );
		var body = new window.FormData();
		body.append( 'action', settings.action );
		body.append( 'nonce', settings.nonce );
		body.append( 'key', key );
		if ( Array.isArray( value ) ) {
			value.forEach( function ( v ) {
				body.append( 'value[]', v );
			} );
		} else {
			body.append( 'value', value );
		}

		window.clearTimeout( badge.esgTimer );
		setBadge( badge, 'saving', settings.saving );
		window.fetch( settings.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) {
				return response.json().catch( function () {
					return {};
				} );
			} )
			.then( function ( json ) {
				if ( ! json || ! json.success ) {
					throw new Error( json && json.data && json.data.message ? json.data.message : settings.failed );
				}
				show( input, json.data.value );
				setBadge( badge, 'saved', settings.saved );
				badge.esgTimer = window.setTimeout( function () {
					badge.hidden = true;
				}, LINGER );
			} )
			.catch( function ( error ) {
				input.checked = before;
				syncShown();
				setBadge( badge, 'error', error && error.message ? error.message : settings.failed );
			} );
	}

	/**
	 * Rows that only matter while a toggle is on (or off) follow it, at
	 * once and again if a failed save puts the toggle back.
	 */
	function syncShown() {
		function on( key ) {
			var toggle = document.querySelector( 'input[type="checkbox"][data-esg-autosave="' + key + '"]' );
			return !! ( toggle && toggle.checked );
		}
		document.querySelectorAll( '[data-esg-shown-by]' ).forEach( function ( row ) {
			row.hidden = ! on( row.getAttribute( 'data-esg-shown-by' ) );
		} );
		document.querySelectorAll( '[data-esg-hidden-by]' ).forEach( function ( row ) {
			row.hidden = on( row.getAttribute( 'data-esg-hidden-by' ) );
		} );
	}
	document.querySelectorAll( 'input[data-esg-autosave]' ).forEach( function ( input ) {
		input.addEventListener( 'change', syncShown );
	} );
	syncShown();

	if ( settings.action && settings.nonce && window.fetch ) {
		document.querySelectorAll( 'input[data-esg-autosave]' ).forEach( function ( input ) {
			input.addEventListener( 'change', function () {
				save( input );
			} );
		} );
	}

	/**
	 * Vertical tabs. Every panel stays in the one form, so switching never
	 * loses an edit; the tab only chooses what shows. The address and the
	 * page "Save Changes" returns to carry it, so a save lands on the same tab.
	 */
	var tabs = document.querySelector( '.esg-tabs' );
	if ( tabs ) {
		var activate = function ( id ) {
			var panel = document.getElementById( 'esg-panel-' + id );
			if ( ! panel ) {
				return;
			}
			tabs.querySelectorAll( '[data-esg-tab]' ).forEach( function ( tab ) {
				var on = tab.getAttribute( 'data-esg-tab' ) === id;
				tab.classList.toggle( 'is-active', on );
				tab.setAttribute( 'aria-selected', on ? 'true' : 'false' );
			} );
			tabs.querySelectorAll( '.esg-tabs__panel' ).forEach( function ( p ) {
				p.classList.toggle( 'is-active', p === panel );
			} );
			var url = new window.URL( window.location.href );
			url.searchParams.set( 'tab', id );
			if ( window.history && window.history.replaceState ) {
				window.history.replaceState( null, '', url.toString() );
			}
			var referer = document.querySelector( 'input[name="_wp_http_referer"]' );
			if ( referer ) {
				var back = new window.URL( referer.value, window.location.href );
				back.searchParams.set( 'tab', id );
				back.searchParams.delete( 'settings-updated' );
				referer.value = back.pathname + back.search;
			}
		};

		tabs.querySelectorAll( '[data-esg-tab]' ).forEach( function ( tab ) {
			tab.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				activate( tab.getAttribute( 'data-esg-tab' ) );
				tab.focus();
			} );
		} );

		// A required field in a hidden panel would block "Save Changes" with
		// no visible reason; show its panel so the browser can point at it.
		tabs.addEventListener( 'invalid', function ( event ) {
			var panel = event.target.closest( '.esg-tabs__panel' );
			if ( panel && ! panel.classList.contains( 'is-active' ) ) {
				activate( panel.id.replace( 'esg-panel-', '' ) );
			}
		}, true );

		tabs.classList.add( 'is-ready' );
	}

	/**
	 * Regional offices: add a row from the template, remove a row, and keep
	 * acronyms in capitals as they are typed.
	 */
	var rows = document.querySelector( '.esg-regions-rows' );
	var template = document.querySelector( '.esg-regions-template' );
	var addOffice = document.querySelector( '.esg-regions-add' );
	if ( rows && template && addOffice ) {
		var next = rows.children.length;
		addOffice.addEventListener( 'click', function () {
			var html = template.innerHTML.split( '__i__' ).join( String( next++ ) );
			rows.insertAdjacentHTML( 'beforeend', html );
			var added = rows.lastElementChild.querySelector( 'input' );
			if ( added ) {
				added.focus();
			}
		} );
		rows.addEventListener( 'click', function ( event ) {
			var remove = event.target.closest( '.esg-regions-delete' );
			if ( remove ) {
				remove.closest( 'tr' ).remove();
			}
		} );
		rows.addEventListener( 'input', function ( event ) {
			if ( event.target.classList.contains( 'esg-acronym' ) ) {
				var upper = event.target.value.toUpperCase().replace( /\s+/g, '' );
				if ( upper !== event.target.value ) {
					event.target.value = upper;
				}
			}
		} );
	}

	var preview = document.querySelector( '.esg-logo-preview' );
	var input = document.querySelector( '.esg-logo-id' );
	var choose = document.querySelector( '.esg-logo-choose' );
	var reset = document.querySelector( '.esg-logo-reset' );
	if ( ! preview || ! input || ! choose || ! reset || ! window.wp || ! window.wp.media ) {
		return;
	}

	var frame;

	choose.addEventListener( 'click', function () {
		if ( ! frame ) {
			frame = window.wp.media( {
				title: window.esgAdmin.choose,
				button: { text: window.esgAdmin.use },
				library: { type: 'image' },
				multiple: false,
			} );
			frame.on( 'select', function () {
				var image = frame.state().get( 'selection' ).first().toJSON();
				var size = image.sizes && image.sizes.medium ? image.sizes.medium : image;
				input.value = String( image.id );
				preview.src = size.url;
			} );
		}
		frame.open();
	} );

	reset.addEventListener( 'click', function () {
		input.value = '0';
		preview.src = window.esgAdmin.bundledLogo;
	} );
}() );
