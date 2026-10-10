/**
 * Email Signature Generator: the form and the signature, built in the browser
 * from a configuration object.
 *
 * The same file runs inside WordPress (the shortcode puts the configuration on
 * the container's data-esg-config attribute) and in the standalone HTML (the
 * build embeds the same attribute). What a person types never leaves the
 * browser.
 *
 * Every value is escaped before it reaches the signature's HTML: the copied
 * signature is pasted into other programs, so it must carry text, never
 * markup someone typed into a field.
 */
( function ( root ) {
	'use strict';

	var SIGNATURE_WIDTH = 450;
	var BODY_LINE = 'margin: 0; padding: 0; font-size: 8px; line-height: 1.2;';

	/**
	 * Escape a value for an HTML text node or a double-quoted attribute.
	 *
	 * @param {*} value Any value.
	 * @return {string} The escaped text.
	 */
	function escapeHtml( value ) {
		return String( value === undefined || value === null ? '' : value )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#39;' );
	}

	/**
	 * Prefix a bare host with https://, so "example.org" becomes a link.
	 *
	 * @param {string} url What the person typed.
	 * @return {string} The URL, or '' when empty.
	 */
	function normalizeUrl( url ) {
		var trimmed = String( url || '' ).trim();
		if ( '' === trimmed ) {
			return '';
		}
		return /^[a-z][a-z0-9+.-]*:/i.test( trimmed ) ? trimmed : 'https://' + trimmed;
	}

	/**
	 * Whether a URL is an http(s) address. Other schemes, such as javascript:,
	 * are refused: the link ends up in other people's mailboxes.
	 *
	 * @param {string} url A normalized URL.
	 * @return {boolean} True when it can be linked.
	 */
	function isValidUrl( url ) {
		try {
			var parsed = new URL( url );
			return ( 'http:' === parsed.protocol || 'https:' === parsed.protocol ) && '' !== parsed.hostname;
		} catch {
			return false;
		}
	}

	/**
	 * Replace the single %s in a translated string.
	 *
	 * @param {string} text  A string with one %s.
	 * @param {string} value The replacement.
	 * @return {string} The formatted string.
	 */
	function format( text, value ) {
		return String( text ).replace( '%s', value );
	}

	/**
	 * Find a model by id, or the first one.
	 *
	 * @param {Object} config The configuration.
	 * @param {string} id     A model id.
	 * @return {Object} The model.
	 */
	function findModel( config, id ) {
		for ( var i = 0; i < config.models.length; i++ ) {
			if ( config.models[ i ].id === id ) {
				return config.models[ i ];
			}
		}
		return config.models[ 0 ];
	}

	/**
	 * Check the values of one model against its fields' rules.
	 *
	 * @param {Object} model  The model.
	 * @param {Object} values Field key to typed value.
	 * @param {Object} config The configuration.
	 * @return {Array<{key: string, message: string}>} One entry per problem.
	 */
	function validate( model, values, config ) {
		var strings = config.strings;
		var errors = [];
		var phone = new RegExp( config.phonePattern );

		model.fields.forEach( function ( field ) {
			var value = String( values[ field.key ] || '' ).trim();

			if ( '' === value ) {
				if ( field.required ) {
					errors.push( { key: field.key, message: format( strings.required, field.label ) } );
				}
				return;
			}

			switch ( field.rule ) {
				case 'email':
					if ( ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( value ) ) {
						errors.push( { key: field.key, message: strings.invalidEmail } );
					}
					break;
				case 'phone':
					if ( ! phone.test( value ) ) {
						errors.push( { key: field.key, message: strings.invalidPhone } );
					}
					break;
				case 'url':
					if ( ! isValidUrl( normalizeUrl( value ) ) ) {
						errors.push( { key: field.key, message: format( strings.invalidUrl, field.label ) } );
					}
					break;
				case 'roleSector':
					if ( value.indexOf( '/' ) !== -1 ) {
						var parts = value.split( '/' ).map( function ( part ) {
							return part.trim();
						} );
						if ( parts.some( function ( part ) {
							return '' === part;
						} ) ) {
							errors.push( { key: field.key, message: strings.invalidRoleSector } );
						}
					} else if ( /\s/.test( value ) ) {
						errors.push( { key: field.key, message: strings.invalidRoleSector } );
					}
					break;
				case 'singleRole':
					if ( value.indexOf( '/' ) !== -1 ) {
						errors.push( { key: field.key, message: strings.invalidSingleRole } );
					}
					break;
			}
		} );

		return errors;
	}

	/**
	 * One line of the signature's text column.
	 *
	 * @param {string} content Escaped HTML.
	 * @param {string} extra   Extra inline style.
	 * @return {string} The line.
	 */
	function line( content, extra ) {
		return '<div style="' + BODY_LINE + ( extra || '' ) + '">' + content + '</div>';
	}

	/**
	 * Build the signature's HTML. Table-based with inline styles, because
	 * that is what email clients keep.
	 *
	 * @param {Object} model  The model.
	 * @param {Object} values Field key to typed value.
	 * @param {Object} config The configuration.
	 * @return {string} The signature HTML.
	 */
	function render( model, values, config ) {
		var has = {};
		model.fields.forEach( function ( field ) {
			has[ field.key ] = true;
		} );
		var get = function ( key ) {
			return has[ key ] ? String( values[ key ] || '' ).trim() : '';
		};

		var name = get( 'name' );
		if ( config.uppercaseName ) {
			name = name.toUpperCase();
		}
		var email = get( 'email' ).toLowerCase();
		var lines = [];

		if ( name ) {
			lines.push( '<div style="margin: 0; padding: 0; font-size: 16px; text-transform: uppercase; line-height: 1.2;"><strong style="font-size: inherit;">' + escapeHtml( name ) + '</strong></div>' );
		}
		if ( get( 'role' ) ) {
			lines.push( '<div style="margin: 0; padding: 0; font-size: 7px; text-transform: uppercase; line-height: 1.2;">' + escapeHtml( get( 'role' ) ) + '</div>' );
		}
		// A blank line in the background colour: email clients collapse an
		// empty div, and the layout needs the gap.
		lines.push( '<div style="margin: 0; padding: 0; font-size: 7px; line-height: 1.2; color: #ffffff;">_</div>' );
		if ( email ) {
			lines.push( line( '<a href="mailto:' + escapeHtml( email ) + '" style="text-decoration: none; color: inherit;">' + escapeHtml( email ) + '</a>' ) );
		}
		if ( get( 'phone' ) ) {
			lines.push( line( escapeHtml( config.strings.phonePrefix + ' ' + get( 'phone' ) ) ) );
		}
		[ 'unit', 'region', 'address', 'postal' ].forEach( function ( key ) {
			if ( get( key ) ) {
				lines.push( line( escapeHtml( get( key ) ) ) );
			}
		} );
		[ 'link1', 'link2' ].forEach( function ( key ) {
			var url = normalizeUrl( get( key ) );
			if ( url && isValidUrl( url ) ) {
				lines.push( line( '<a href="' + escapeHtml( url ) + '" target="_blank" style="text-decoration: none; color: inherit;">' + escapeHtml( new URL( url ).hostname ) + '</a>' ) );
			}
		} );

		var logo = config.logo && config.logo.url
			? '<img src="' + escapeHtml( config.logo.url ) + '" width="' + escapeHtml( config.logo.width ) + '" alt="' + escapeHtml( config.logo.alt ) + '" style="display: block; border: 0;" border="0">'
			: '';

		return '<table cellpadding="0" cellspacing="0" border="0" style="background: #ffffff; width: 100%; max-width: ' + SIGNATURE_WIDTH + 'px; font-family: Arial, sans-serif;">'
			+ '<tr>'
			+ '<td valign="middle" style="padding: 10px; width: ' + escapeHtml( config.logo ? config.logo.width : 0 ) + 'px; vertical-align: middle;">' + logo + '</td>'
			+ '<td valign="middle" style="padding-left: 7px; border-left: 3px solid #000000; vertical-align: middle; line-height: 1; font-family: Arial, sans-serif; color: #000000;">'
			+ lines.join( '' )
			+ '</td>'
			+ '</tr>'
			+ '</table>';
	}

	/**
	 * A safe file name for the downloaded image.
	 *
	 * @param {Object} model  The model.
	 * @param {Object} values Field key to typed value.
	 * @return {string} The name, without extension.
	 */
	function fileName( model, values ) {
		var base = String( values.name || '' ).trim()
			.normalize( 'NFD' ).replace( /[̀-ͯ]/g, '' )
			.replace( /[^A-Za-z0-9]+/g, '_' ).replace( /^_+|_+$/g, '' );
		return ( base || 'signature' ) + '_' + model.id;
	}

	/**
	 * Copy the signature as rich text, with plain-text HTML as the fallback.
	 *
	 * @param {string}   html       The signature HTML.
	 * @param {Document} doc        The document.
	 * @param {Function} onDone     Called with true on success.
	 */
	function copySignature( html, doc, onDone ) {
		var nav = doc.defaultView && doc.defaultView.navigator;
		var ClipboardItemCtor = doc.defaultView && doc.defaultView.ClipboardItem;

		if ( nav && nav.clipboard && nav.clipboard.write && ClipboardItemCtor ) {
			var item = new ClipboardItemCtor( {
				'text/html': new Blob( [ html ], { type: 'text/html' } ),
				'text/plain': new Blob( [ html ], { type: 'text/plain' } ),
			} );
			nav.clipboard.write( [ item ] ).then(
				function () {
					onDone( true );
				},
				function () {
					onDone( legacyCopy( html, doc ) );
				}
			);
			return;
		}
		onDone( legacyCopy( html, doc ) );
	}

	/**
	 * Copy through a selection, for browsers without the async Clipboard API.
	 *
	 * @param {string}   html The signature HTML.
	 * @param {Document} doc  The document.
	 * @return {boolean} Whether the browser reported a copy.
	 */
	function legacyCopy( html, doc ) {
		var holder = doc.createElement( 'div' );
		holder.setAttribute( 'aria-hidden', 'true' );
		holder.style.position = 'fixed';
		holder.style.left = '-9999px';
		holder.innerHTML = html;
		doc.body.appendChild( holder );

		var selection = doc.getSelection();
		var range = doc.createRange();
		range.selectNodeContents( holder );
		selection.removeAllRanges();
		selection.addRange( range );

		var ok = false;
		try {
			ok = doc.execCommand( 'copy' );
		} catch {
			ok = false;
		}
		selection.removeAllRanges();
		doc.body.removeChild( holder );
		return ok;
	}

	/**
	 * Build an element with attributes and children.
	 *
	 * @param {Document} doc      The document.
	 * @param {string}   tag      Tag name.
	 * @param {Object}   attrs    Attributes; `text` sets textContent.
	 * @param {Array}    children Child nodes.
	 * @return {Element} The element.
	 */
	function el( doc, tag, attrs, children ) {
		var node = doc.createElement( tag );
		Object.keys( attrs || {} ).forEach( function ( key ) {
			if ( 'text' === key ) {
				node.textContent = attrs[ key ];
			} else if ( true === attrs[ key ] ) {
				node.setAttribute( key, '' );
			} else if ( false !== attrs[ key ] && undefined !== attrs[ key ] && null !== attrs[ key ] ) {
				node.setAttribute( key, attrs[ key ] );
			}
		} );
		( children || [] ).forEach( function ( child ) {
			node.appendChild( child );
		} );
		return node;
	}

	/**
	 * Draw the form and wire its buttons inside a container.
	 *
	 * @param {Element} container The element that holds the generator.
	 * @param {Object}  config    The configuration.
	 * @return {Object} Handles used by the tests.
	 */
	function mount( container, config ) {
		var doc = container.ownerDocument;
		var strings = config.strings;
		var uid = 'esg-' + Math.random().toString( 36 ).slice( 2, 8 );
		var current = config.models[ 0 ];
		var html = '';

		var form = el( doc, 'form', { class: 'esg-form', novalidate: true } );
		var modelSelect = el( doc, 'select', { id: uid + '-model', class: 'esg-model' } );
		config.models.forEach( function ( model ) {
			modelSelect.appendChild( el( doc, 'option', { value: model.id, text: model.label } ) );
		} );
		var modelRow = el( doc, 'p', { class: 'esg-field' }, [
			el( doc, 'label', { for: uid + '-model', text: strings.model } ),
			modelSelect,
		] );
		modelRow.hidden = config.models.length < 2;
		form.appendChild( modelRow );

		var fieldsBox = el( doc, 'div', { class: 'esg-fields' } );
		form.appendChild( fieldsBox );

		var errorsBox = el( doc, 'ul', { class: 'esg-errors', role: 'alert' } );
		form.appendChild( errorsBox );

		var generateBtn = el( doc, 'button', { type: 'submit', class: 'esg-button esg-generate', text: strings.generate } );
		var copyBtn = el( doc, 'button', { type: 'button', class: 'esg-button esg-copy', text: strings.copy } );
		var downloadBtn = el( doc, 'button', { type: 'button', class: 'esg-button esg-download', text: strings.download } );
		form.appendChild( el( doc, 'p', { class: 'esg-actions' }, [ generateBtn, copyBtn, downloadBtn ] ) );

		var status = el( doc, 'p', { class: 'esg-status', role: 'status' } );
		var previewHost = el( doc, 'div', { class: 'esg-preview' } );
		var previewRoot = previewHost.attachShadow ? previewHost.attachShadow( { mode: 'open' } ) : previewHost;

		container.innerHTML = '';
		container.appendChild( form );
		container.appendChild( status );
		container.appendChild( el( doc, 'h3', { class: 'esg-preview-title', text: strings.preview } ) );
		container.appendChild( previewHost );

		function drawFields() {
			fieldsBox.innerHTML = '';
			current.fields.forEach( function ( field ) {
				var id = uid + '-' + field.key;
				var input;
				if ( 'region' === field.type ) {
					input = el( doc, 'select', { id: id, name: field.key } );
					config.regions.forEach( function ( region ) {
						input.appendChild( el( doc, 'option', { value: region, text: region, selected: region === field.default } ) );
					} );
				} else {
					input = el( doc, 'input', {
						id: id,
						name: field.key,
						type: 'email' === field.type ? 'email' : 'text',
						inputmode: 'url' === field.type ? 'url' : undefined,
						placeholder: field.placeholder,
						readonly: !! field.locked,
						'aria-required': field.required ? 'true' : undefined,
					} );
					input.value = field.default || '';
				}
				fieldsBox.appendChild( el( doc, 'p', { class: 'esg-field' }, [
					el( doc, 'label', { for: id, text: field.label + ( field.required ? ' *' : '' ) } ),
					input,
				] ) );
			} );
		}

		function readValues() {
			var values = {};
			current.fields.forEach( function ( field ) {
				var input = fieldsBox.querySelector( '[name="' + field.key + '"]' );
				values[ field.key ] = field.locked ? ( field.default || '' ) : ( input ? input.value : '' );
			} );
			return values;
		}

		function showErrors( errors ) {
			errorsBox.innerHTML = '';
			fieldsBox.querySelectorAll( '[aria-invalid]' ).forEach( function ( input ) {
				input.removeAttribute( 'aria-invalid' );
			} );
			errors.forEach( function ( error ) {
				errorsBox.appendChild( el( doc, 'li', { text: error.message } ) );
				var input = fieldsBox.querySelector( '[name="' + error.key + '"]' );
				if ( input ) {
					input.setAttribute( 'aria-invalid', 'true' );
				}
			} );
		}

		function setStatus( text ) {
			status.textContent = text || '';
		}

		function generate() {
			var values = readValues();
			var errors = validate( current, values, config );
			showErrors( errors );
			setStatus( '' );
			if ( errors.length ) {
				html = '';
				previewRoot.innerHTML = '';
				return false;
			}
			html = render( current, values, config );
			previewRoot.innerHTML = html;
			return true;
		}

		modelSelect.addEventListener( 'change', function () {
			current = findModel( config, modelSelect.value );
			html = '';
			previewRoot.innerHTML = '';
			showErrors( [] );
			drawFields();
		} );

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			generate();
		} );

		copyBtn.addEventListener( 'click', function () {
			if ( ! html && ! generate() ) {
				return;
			}
			copySignature( html, doc, function ( ok ) {
				setStatus( ok ? strings.copied : strings.copyFailed );
			} );
		} );

		downloadBtn.addEventListener( 'click', function () {
			if ( ! html && ! generate() ) {
				return;
			}
			var win = doc.defaultView;
			if ( ! win || 'function' !== typeof win.html2canvas ) {
				setStatus( strings.downloadFailed );
				return;
			}
			// html2canvas cannot read inside a shadow root, so it draws a copy
			// placed off screen in the document.
			var stage = el( doc, 'div', { 'aria-hidden': 'true', class: 'esg-stage' } );
			stage.style.position = 'fixed';
			stage.style.left = '-9999px';
			stage.style.top = '0';
			stage.style.width = SIGNATURE_WIDTH + 'px';
			stage.style.background = '#ffffff';
			stage.innerHTML = html;
			doc.body.appendChild( stage );
			var name = fileName( current, readValues() );

			// toDataURL throws when the logo was served without CORS headers
			// (the canvas is tainted), so the catch covers both the drawing and
			// the export.
			win.html2canvas( stage.firstChild, { useCORS: true, backgroundColor: '#ffffff', scale: win.devicePixelRatio || 2 } )
				.then( function ( canvas ) {
					var link = el( doc, 'a', { download: name + '.jpg', href: canvas.toDataURL( 'image/jpeg', 0.9 ) } );
					doc.body.appendChild( link );
					link.click();
					doc.body.removeChild( link );
				} )
				.catch( function () {
					setStatus( strings.downloadFailed );
				} )
				.then( function () {
					if ( stage.parentNode ) {
						stage.parentNode.removeChild( stage );
					}
				} );
		} );

		drawFields();

		return {
			generate: generate,
			html: function () {
				return html;
			},
			select: function ( id ) {
				modelSelect.value = id;
				modelSelect.dispatchEvent( new doc.defaultView.Event( 'change' ) );
			},
		};
	}

	/**
	 * Mount every generator container on the page.
	 *
	 * @param {Document} doc The document.
	 */
	function mountAll( doc ) {
		doc.querySelectorAll( '[data-esg-config]' ).forEach( function ( container ) {
			if ( container.getAttribute( 'data-esg-mounted' ) ) {
				return;
			}
			container.setAttribute( 'data-esg-mounted', '1' );
			mount( container, JSON.parse( container.getAttribute( 'data-esg-config' ) ) );
		} );
	}

	var api = {
		escapeHtml: escapeHtml,
		normalizeUrl: normalizeUrl,
		isValidUrl: isValidUrl,
		validate: validate,
		render: render,
		fileName: fileName,
		findModel: findModel,
		mount: mount,
		mountAll: mountAll,
	};

	if ( 'object' === typeof module && module.exports ) {
		module.exports = api;
	} else {
		root.EmailSignatureGenerator = api;
		if ( 'loading' === root.document.readyState ) {
			root.document.addEventListener( 'DOMContentLoaded', function () {
				mountAll( root.document );
			} );
		} else {
			mountAll( root.document );
		}
	}
}( this ) );
