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
	 * The email domains the site accepts; empty when any address is.
	 *
	 * @param {Object} config The configuration.
	 * @return {string[]} Lower-case domains, without the "@".
	 */
	function domains( config ) {
		return Array.isArray( config.emailDomains ) ? config.emailDomains : [];
	}

	/**
	 * The lower-case domain of an address.
	 *
	 * @param {string} value Address.
	 * @return {string} What follows its last "@".
	 */
	function domainOf( value ) {
		return value.slice( value.lastIndexOf( '@' ) + 1 ).trim().toLowerCase();
	}

	/**
	 * The refused domain an address domain falls under, itself or a parent.
	 *
	 * @param {string} domain Lower-case domain.
	 * @param {Object} config The configuration.
	 * @return {string} The refused domain, or '' when it is accepted.
	 */
	function blockedBy( domain, config ) {
		var blocked = Array.isArray( config.blockedEmailDomains ) ? config.blockedEmailDomains : [];
		for ( var i = 0; i < blocked.length; i++ ) {
			if ( domain === blocked[ i ] || domain.slice( -( blocked[ i ].length + 1 ) ) === '.' + blocked[ i ] ) {
				return blocked[ i ];
			}
		}
		return '';
	}

	/**
	 * Split an address at its last "@" when the part after it is an
	 * accepted domain.
	 *
	 * @param {string}   value   Typed or pasted text.
	 * @param {string[]} allowed Accepted domains.
	 * @return {?{local: string, domain: string}} The two parts, or null.
	 */
	function splitAddress( value, allowed ) {
		var at = String( value || '' ).lastIndexOf( '@' );
		if ( -1 === at ) {
			return null;
		}
		var domain = value.slice( at + 1 ).trim().toLowerCase();
		return -1 === allowed.indexOf( domain ) ? null : { local: value.slice( 0, at ).trim(), domain: domain };
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
					if ( domains( config ).length && value.indexOf( '@' ) !== value.lastIndexOf( '@' ) ) {
						// The part typed beside the domain carried an "@".
						errors.push( { key: field.key, message: strings.emailLocalOnly } );
					} else if ( ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( value ) ) {
						errors.push( { key: field.key, message: strings.invalidEmail } );
					} else if ( domains( config ).length && -1 === domains( config ).indexOf( domainOf( value ) ) ) {
						errors.push( { key: field.key, message: format( strings.invalidEmailDomain, domains( config ).join( ', ' ) ) } );
					} else if ( ! domains( config ).length && blockedBy( domainOf( value ), config ) ) {
						errors.push( { key: field.key, message: format( strings.emailBlocked, blockedBy( domainOf( value ), config ) ) } );
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
				case 'noSlash':
					if ( value.indexOf( '/' ) !== -1 ) {
						errors.push( { key: field.key, message: format( strings.invalidNoSlash, field.label ) } );
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
			lines.push( '<div style="margin: 0; padding: 0; font-size: 16px; text-transform: uppercase; line-height: 1.2;"><strong style="font-size: inherit; font-weight: bold;">' + escapeHtml( name ) + '</strong></div>' );
		}
		// The department and the school carry the chosen office's acronym:
		// "NTIC / DREMP". Without the text there is nothing to qualify.
		var withOffice = function ( text ) {
			return text && get( 'office' ) ? text + ' / ' + get( 'office' ) : text;
		};
		var role = has.sector ? withOffice( get( 'sector' ) ) : get( 'role' );
		if ( role ) {
			lines.push( '<div style="margin: 0; padding: 0; font-size: 7px; text-transform: uppercase; line-height: 1.2;">' + escapeHtml( role ) + '</div>' );
		}
		// A blank line in the background colour: email clients collapse an
		// empty div, and the layout needs the gap.
		lines.push( '<div style="margin: 0; padding: 0; font-size: 7px; line-height: 1.2; color: #ffffff;">_</div>' );
		if ( email ) {
			lines.push( line( '<a href="mailto:' + escapeHtml( email ) + '" style="text-decoration: none; color: inherit;">' + escapeHtml( email ) + '</a>' ) );
		}
		if ( get( 'phone' ) ) {
			// Each number dials on a tap: "55 11 3397-1234" → tel:+551133971234.
			var numbers = get( 'phone' ).split( '|' ).map( function ( number ) {
				number = number.trim();
				return '<a href="tel:+' + number.replace( /\D/g, '' ) + '" style="text-decoration: none; color: inherit;">' + escapeHtml( number ) + '</a>';
			} );
			lines.push( line( escapeHtml( config.strings.phonePrefix ) + ' ' + numbers.join( ' | ' ) ) );
		}
		if ( has.school && get( 'school' ) ) {
			lines.push( line( escapeHtml( withOffice( get( 'school' ) ) ) ) );
		}
		[ 'region', 'address', 'postal' ].forEach( function ( key ) {
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

		// The divider is the right border of a cell that holds only the logo,
		// so it is exactly as tall as the logo whatever the text's height. A
		// border on the text's cell would run the whole height of the row.
		var hasLogo = !! ( config.logo && config.logo.url );
		var logoWidth = hasLogo ? parseInt( config.logo.width, 10 ) || 0 : 0;
		var logo = hasLogo
			? '<table cellpadding="0" cellspacing="0" border="0"><tr>'
				+ '<td valign="middle" style="padding: 0 10px 0 0; border-right: 3px solid #000000; vertical-align: middle;">'
				+ '<img src="' + escapeHtml( config.logo.url ) + '" width="' + logoWidth + '" alt="' + escapeHtml( config.logo.alt ) + '" style="display: block; border: 0;" border="0">'
				+ '</td></tr></table>'
			: '';

		// A spacer row rather than a margin or padding: email programs, Outlook
		// above all, keep a cell's height and drop the other two.
		var space = Math.min( 20, Math.max( 0, parseInt( config.topSpace, 10 ) || 0 ) );
		var spacer = space
			? '<tr><td' + ( hasLogo ? ' colspan="2"' : '' ) + ' height="' + space + '" style="height: ' + space + 'px; padding: 0; font-size: 0; line-height: 0;">&nbsp;</td></tr>'
			: '';

		return '<table cellpadding="0" cellspacing="0" border="0" style="background: #ffffff; width: 100%; max-width: ' + SIGNATURE_WIDTH + 'px; font-family: Arial, sans-serif;">'
			+ spacer
			+ '<tr>'
			+ ( hasLogo ? '<td valign="middle" style="padding: 10px 0 10px 10px; width: ' + ( logoWidth + 13 ) + 'px; vertical-align: middle;">' + logo + '</td>' : '' )
			+ '<td valign="middle" style="padding-left: ' + ( hasLogo ? 7 : 10 ) + 'px; vertical-align: middle; line-height: 1; font-family: Arial, sans-serif; color: #000000;">'
			+ lines.join( '' )
			+ '</td>'
			+ '</tr>'
			+ '</table>';
	}

	/**
	 * The downloaded image's density and JPEG quality. The server clamps both
	 * already; this keeps a hand-edited configuration from breaking the
	 * download.
	 *
	 * @param {Object} config The configuration.
	 * @return {{format: string, scale: number, quality: number}} 'png' or 'jpg', scale factor and JPEG quality 0–1.
	 */
	function imageOptions( config ) {
		var image = ( config && config.image ) || {};
		var scale = parseInt( image.scale, 10 );
		var quality = parseInt( image.quality, 10 );
		return {
			format: 'jpg' === image.format ? 'jpg' : 'png',
			scale: scale >= 1 && scale <= 3 ? scale : 1,
			quality: ( quality >= 50 && quality <= 100 ? quality : 90 ) / 100,
		};
	}

	/**
	 * How to draw the image: at least twice the final size, then reduced to
	 * it, which smooths the text the way the original page did. The final
	 * size is the signature's own times the configured density.
	 *
	 * @param {number} width  Width of the signature, in CSS pixels.
	 * @param {number} height Height of the signature, in CSS pixels.
	 * @param {number} scale  Configured density.
	 * @return {{render: number, width: number, height: number}} Drawing density and final size in pixels.
	 */
	function exportSize( width, height, scale ) {
		return {
			render: Math.max( 2, scale ),
			width: Math.round( width * scale ),
			height: Math.round( height * scale ),
		};
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
		// The site chooses whether the image is offered at all, and how: a
		// button (the default), the same inside a collapsed "More options",
		// or a button with no note recommending Copy HTML afterwards.
		var offerDownload = false !== config.offerDownload;
		var downloadStyle = config.downloadStyle || 'notice';
		var inline = offerDownload && 'more' !== downloadStyle;
		form.appendChild( el( doc, 'p', { class: 'esg-actions' }, [ generateBtn ] ) );

		// Copying and downloading appear only once a signature is generated,
		// and hide again when a field changes, so neither can deliver a
		// signature that no longer matches the form.
		var output = el( doc, 'div', { class: 'esg-output', hidden: true } );
		output.appendChild( el( doc, 'p', { class: 'esg-actions' }, inline ? [ copyBtn, downloadBtn ] : [ copyBtn ] ) );
		if ( offerDownload && 'more' === downloadStyle ) {
			output.appendChild( el( doc, 'details', { class: 'esg-more' }, [
				el( doc, 'summary', { text: strings.moreOptions } ),
				el( doc, 'p', { class: 'esg-actions' }, [ downloadBtn ] ),
			] ) );
		}
		form.appendChild( output );
		output.appendChild( el( doc, 'p', { class: 'esg-hint', text: strings.copyHint } ) );
		// Where to paste it: the step people most often get stuck on.
		output.appendChild( el( doc, 'details', { class: 'esg-howto' }, [
			el( doc, 'summary', { text: strings.howTo } ),
			el( doc, 'ul', {}, [ strings.howToOutlookWeb, strings.howToOutlook, strings.howToGmail, strings.howToPhone ].filter( Boolean ).map( function ( text ) {
				return el( doc, 'li', { text: text } );
			} ) ),
		] ) );

		var status = el( doc, 'p', { class: 'esg-status', role: 'status' } );
		var previewHost = el( doc, 'div', { class: 'esg-preview' } );
		var previewRoot = previewHost.attachShadow ? previewHost.attachShadow( { mode: 'open' } ) : previewHost;

		container.innerHTML = '';
		container.appendChild( form );
		container.appendChild( status );
		container.appendChild( el( doc, 'h3', { class: 'esg-preview-title', text: strings.preview } ) );
		container.appendChild( previewHost );

		/**
		 * The email field when the site accepts only some domains: the person
		 * types the part before the "@", and the domain sits beside it, fixed
		 * when there is one and chosen from a list when there are more. A
		 * pasted address at an accepted domain is split into the two.
		 *
		 * @param {Object} field The email field.
		 * @param {string} id    The input's id.
		 * @return {HTMLElement} The group.
		 */
		function emailGroup( field, id ) {
			var allowed = domains( config );
			var placeholder = String( field.placeholder || '' );
			var local = el( doc, 'input', {
				id: id,
				name: field.key,
				type: 'text',
				inputmode: 'email',
				autocomplete: 'off',
				placeholder: -1 === placeholder.indexOf( '@' ) ? placeholder : placeholder.slice( 0, placeholder.indexOf( '@' ) ),
				'aria-required': field.required ? 'true' : undefined,
				'aria-describedby': id + '-domain',
			} );
			var suffix;
			if ( 1 === allowed.length ) {
				suffix = el( doc, 'span', { id: id + '-domain', class: 'esg-email-domain', text: '@' + allowed[ 0 ] } );
			} else {
				suffix = el( doc, 'select', { id: id + '-domain', class: 'esg-email-domain', name: field.key + '__domain', 'aria-label': strings.emailDomain } );
				allowed.forEach( function ( domain ) {
					suffix.appendChild( el( doc, 'option', { value: domain, text: '@' + domain } ) );
				} );
			}

			function take( value ) {
				var parts = splitAddress( value, allowed );
				if ( ! parts ) {
					return false;
				}
				local.value = parts.local;
				if ( 'SELECT' === suffix.tagName ) {
					suffix.value = parts.domain;
				}
				return true;
			}

			if ( ! take( field.default || '' ) ) {
				local.value = field.default || '';
			}
			local.addEventListener( 'input', function () {
				take( local.value );
			} );
			return el( doc, 'span', { class: 'esg-email-group' }, [ local, suffix ] );
		}

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
				} else if ( 'office' === field.type ) {
					// Shows the office's full name, keeps only its acronym.
					input = el( doc, 'select', { id: id, name: field.key, disabled: !! field.locked } );
					( config.offices || [] ).forEach( function ( office ) {
						input.appendChild( el( doc, 'option', { value: office.acronym, text: office.name + ' / ' + office.acronym, selected: office.acronym === field.default } ) );
					} );
				} else if ( 'email' === field.type && ! field.locked && domains( config ).length ) {
					input = emailGroup( field, id );
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
				var domain = input && input.parentNode && input.parentNode.querySelector( '.esg-email-domain' );
				var local = values[ field.key ].trim();
				if ( domain && ! field.locked && '' !== local ) {
					// A part typed with an "@" of its own keeps it, and the
					// second "@" is what validate() refuses.
					values[ field.key ] = local + '@' + ( 'SELECT' === domain.tagName ? domain.value : domain.textContent.slice( 1 ) );
				}
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
				output.hidden = true;
				return false;
			}
			html = render( current, values, config );
			previewRoot.innerHTML = html;
			output.hidden = false;
			return true;
		}

		// A changed field makes the generated signature stale.
		fieldsBox.addEventListener( 'input', function () {
			if ( html ) {
				html = '';
				output.hidden = true;
				setStatus( '' );
			}
		} );

		modelSelect.addEventListener( 'change', function () {
			current = findModel( config, modelSelect.value );
			html = '';
			previewRoot.innerHTML = '';
			output.hidden = true;
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
			var image = imageOptions( config );
			var table = stage.firstChild;
			var size = exportSize( table.offsetWidth, table.offsetHeight, image.scale );
			win.html2canvas( table, { useCORS: true, backgroundColor: '#ffffff', scale: size.render } )
				.then( function ( drawn ) {
					var canvas = drawn;
					if ( drawn.width !== size.width ) {
						canvas = doc.createElement( 'canvas' );
						canvas.width = size.width;
						canvas.height = size.height;
						var ctx = canvas.getContext( '2d' );
						ctx.imageSmoothingQuality = 'high';
						ctx.drawImage( drawn, 0, 0, size.width, size.height );
					}
					var href = 'jpg' === image.format ? canvas.toDataURL( 'image/jpeg', image.quality ) : canvas.toDataURL( 'image/png' );
					var link = el( doc, 'a', { download: name + '.' + image.format, href: href } );
					doc.body.appendChild( link );
					link.click();
					doc.body.removeChild( link );
					if ( 'plain' !== downloadStyle ) {
						setStatus( strings.downloaded );
					}
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
		imageOptions: imageOptions,
		exportSize: exportSize,
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
