import { beforeAll, describe, expect, it } from 'vitest';
import { loadConfig, loadGenerator } from './helpers.js';

let config;
let gen;

const valid = {
	name: 'Maria da Silva',
	sector: 'NTIC',
	office: 'DREMP',
	role: 'Professora',
	school: 'EMEF Joaquim José da Silva Xavier',
	email: 'Maria.Silva@sme.prefeitura.sp.gov.br',
	phone: '55 11 3397-1234',
	address: 'Av. Nordestina, 747',
	postal: '08021-000 | São Paulo | SP',
	link1: 'educacao.sme.prefeitura.sp.gov.br',
	link2: '',
};

beforeAll( () => {
	config = loadConfig();
	gen = loadGenerator();
} );

function model( id ) {
	return gen.findModel( config, id );
}

describe( 'escapeHtml', () => {
	it( 'escapes every character that can open markup or end an attribute', () => {
		expect( gen.escapeHtml( `<a href="x" onclick='y'>&</a>` ) ).toBe( '&lt;a href=&quot;x&quot; onclick=&#39;y&#39;&gt;&amp;&lt;/a&gt;' );
	} );

	it( 'turns null and undefined into an empty string', () => {
		expect( gen.escapeHtml( null ) ).toBe( '' );
		expect( gen.escapeHtml( undefined ) ).toBe( '' );
	} );
} );

describe( 'URLs', () => {
	it( 'prefixes a bare host with https', () => {
		expect( gen.normalizeUrl( 'example.org' ) ).toBe( 'https://example.org' );
		expect( gen.normalizeUrl( 'http://example.org' ) ).toBe( 'http://example.org' );
		expect( gen.normalizeUrl( '   ' ) ).toBe( '' );
	} );

	it( 'accepts http and https only', () => {
		expect( gen.isValidUrl( 'https://example.org/x' ) ).toBe( true );
		expect( gen.isValidUrl( 'javascript:alert(1)' ) ).toBe( false );
		expect( gen.isValidUrl( 'data:text/html,x' ) ).toBe( false );
		expect( gen.isValidUrl( 'https://' ) ).toBe( false );
	} );
} );

describe( 'validate', () => {
	it( 'passes a complete regional-office signature', () => {
		expect( gen.validate( model( 'staff_regional' ), valid, config ) ).toEqual( [] );
	} );

	it( 'names every missing required field', () => {
		const keys = gen.validate( model( 'staff_regional' ), {}, config ).map( ( e ) => e.key );
		expect( keys ).toEqual( [ 'name', 'sector', 'office', 'email' ] );
	} );

	it.each( [
		[ 'email', 'not-an-email' ],
		[ 'phone', '11 3397-1234' ],
		[ 'phone', '55 11 3397-XXXX' ],
		[ 'link1', 'javascript:alert(1)' ],
		[ 'sector', 'NTIC / DREMP' ],
	] )( 'refuses %s = %s', ( key, value ) => {
		const errors = gen.validate( model( 'staff_regional' ), { ...valid, [ key ]: value }, config );
		expect( errors.map( ( e ) => e.key ) ).toEqual( [ key ] );
	} );

	it( 'accepts two phone numbers joined by a bar', () => {
		const errors = gen.validate( model( 'staff_regional' ), { ...valid, phone: '55 11 3397-1234 | 55 11 93397-5678' }, config );
		expect( errors ).toEqual( [] );
	} );

	it( 'refuses a slash in a school role', () => {
		const errors = gen.validate( model( 'staff_school' ), { ...valid, role: 'Teacher / Coordinator' }, config );
		expect( errors.map( ( e ) => e.key ) ).toEqual( [ 'role' ] );
	} );

	it( 'refuses a slash in the school, where the office is added on its own', () => {
		const errors = gen.validate( model( 'staff_school' ), { ...valid, school: 'EMEF X / DREMP' }, config );
		expect( errors.map( ( e ) => e.key ) ).toEqual( [ 'school' ] );
		expect( errors[ 0 ].message ).toContain( 'School' );
	} );

	it( 'puts the field label into the required message', () => {
		const [ error ] = gen.validate( model( 'staff_regional' ), { ...valid, name: '' }, config );
		expect( error.message ).toContain( 'Full name' );
	} );
} );

describe( 'render', () => {
	it( 'prints typed markup as text, never as markup', () => {
		const html = gen.render( model( 'staff_regional' ), { ...valid, name: '<img src=x onerror=alert(1)>', address: '"><script>x</script>' }, config );
		expect( html ).not.toMatch( /<img src=x/i );
		expect( html ).not.toContain( '<script>' );
		expect( html ).toContain( '&lt;IMG SRC=X ONERROR=ALERT(1)&gt;' );
		expect( html ).toContain( '&quot;&gt;&lt;script&gt;' );
	} );

	it( 'upper-cases the name and lower-cases the email', () => {
		const html = gen.render( model( 'staff_regional' ), valid, config );
		expect( html ).toContain( 'MARIA DA SILVA' );
		expect( html ).toContain( 'mailto:maria.silva@sme.prefeitura.sp.gov.br' );
	} );

	it( 'keeps the name as typed when upper-casing is off', () => {
		const html = gen.render( model( 'staff_regional' ), valid, { ...config, uppercaseName: false } );
		expect( html ).toContain( 'Maria da Silva' );
	} );

	it( 'shows a link by its host name and never links a non-http address', () => {
		const html = gen.render( model( 'staff_regional' ), { ...valid, link2: 'javascript:alert(1)' }, config );
		expect( html ).toContain( 'href="https://educacao.sme.prefeitura.sp.gov.br"' );
		expect( html ).toContain( '>educacao.sme.prefeitura.sp.gov.br</a>' );
		expect( html ).not.toContain( 'javascript:' );
	} );

	it( 'appends the chosen office acronym to the department and to the school', () => {
		const regional = gen.render( model( 'staff_regional' ), valid, config );
		expect( regional ).toContain( '>NTIC / DREMP</div>' );

		const school = gen.render( model( 'staff_school' ), { ...valid, office: 'DREPE' }, config );
		expect( school ).toContain( '>Professora</div>' );
		expect( school ).toContain( 'EMEF Joaquim José da Silva Xavier / DREPE' );
		expect( school ).not.toContain( 'NTIC' );

		// Without a school there is nothing to qualify.
		expect( gen.render( model( 'staff_school' ), { ...valid, school: '' }, config ) ).not.toContain( '/ DREMP' );
	} );

	it( 'offers every office by its full name and keeps its acronym', () => {
		document.body.innerHTML = '<div id="c"></div>';
		gen.mount( document.getElementById( 'c' ), config );
		const options = [ ...document.querySelectorAll( 'select[name="office"] option' ) ];
		expect( options ).toHaveLength( config.offices.length );
		const miguel = options.find( ( o ) => o.value === 'DREMP' );
		expect( miguel.textContent ).toBe( 'Diretoria Regional de Educação São Miguel / DREMP' );
		expect( miguel.selected ).toBe( true );
	} );

	it( 'prints only the fields of the chosen model', () => {
		const html = gen.render( model( 'department' ), { ...valid, role: 'Hidden role', region: 'Diretoria Regional de Educação Penha / DREPE' }, config );
		expect( html ).not.toContain( 'Hidden role' );
		expect( html ).toContain( 'Diretoria Regional de Educação Penha / DREPE' );
	} );

	it( 'links the configured logo', () => {
		const html = gen.render( model( 'staff_regional' ), valid, config );
		expect( html ).toContain( `src="${ config.logo.url }"` );
		expect( html ).toContain( 'width="120"' );
	} );

	it( 'renders no image when no logo is configured', () => {
		const html = gen.render( model( 'staff_regional' ), valid, { ...config, logo: null } );
		expect( html ).not.toContain( '<img' );
	} );

	it( 'draws the divider beside the logo only, so it is as tall as the logo', () => {
		document.body.innerHTML = gen.render( model( 'staff_regional' ), valid, config );
		const img = document.querySelector( 'img' );
		const cell = img.parentElement;
		expect( cell.tagName ).toBe( 'TD' );
		expect( cell.getAttribute( 'style' ) ).toContain( 'border-right: 3px solid #000000' );
		// The cell holds the logo and nothing else.
		expect( cell.children ).toHaveLength( 1 );
		// No other cell carries a border, so nothing spans the text's height.
		const bordered = [ ...document.querySelectorAll( 'td' ) ].filter( ( td ) => td.getAttribute( 'style' ).includes( 'border' ) );
		expect( bordered ).toEqual( [ cell ] );
	} );

	it( 'adds the configured space above the signature as a spacer row', () => {
		expect( config.topSpace ).toBe( 4 );
		document.body.innerHTML = gen.render( model( 'staff_regional' ), valid, config );
		const first = document.querySelector( 'table > tbody > tr > td' );
		expect( first.getAttribute( 'height' ) ).toBe( '4' );
		expect( first.getAttribute( 'colspan' ) ).toBe( '2' );
		expect( first.getAttribute( 'style' ) ).toContain( 'height: 4px' );

		const none = gen.render( model( 'staff_regional' ), valid, { ...config, topSpace: 0 } );
		expect( none ).not.toContain( 'height="' );
		expect( gen.render( model( 'staff_regional' ), valid, { ...config, topSpace: 99 } ) ).toContain( 'height="20"' );
		expect( gen.render( model( 'staff_regional' ), valid, { ...config, logo: null } ) ).not.toContain( 'colspan' );
	} );

	it( 'draws no divider without a logo', () => {
		const html = gen.render( model( 'staff_regional' ), valid, { ...config, logo: null } );
		expect( html ).not.toContain( 'border-right' );
		expect( html ).not.toContain( 'border-left' );
	} );
} );

describe( 'fileName', () => {
	it( 'drops accents and anything a file system dislikes', () => {
		expect( gen.fileName( model( 'staff_school' ), { name: 'João   d’Ávila / X' } ) ).toBe( 'Joao_d_Avila_X_staff_school' );
		expect( gen.fileName( model( 'department' ), { name: '' } ) ).toBe( 'signature_department' );
	} );
} );

describe( 'mount', () => {
	it( 'draws the chosen model, shows errors and builds the preview', () => {
		document.body.innerHTML = '<div id="c"></div>';
		const ui = gen.mount( document.getElementById( 'c' ), config );

		expect( document.querySelectorAll( '.esg-model option' ) ).toHaveLength( config.models.length );
		expect( ui.generate() ).toBe( false );
		expect( document.querySelectorAll( '.esg-errors li' ).length ).toBeGreaterThan( 0 );
		expect( document.querySelector( '[name="name"]' ).getAttribute( 'aria-invalid' ) ).toBe( 'true' );

		Object.entries( valid ).forEach( ( [ key, value ] ) => {
			const input = document.querySelector( `[name="${ key }"]` );
			if ( input ) {
				input.value = value;
			}
		} );
		expect( ui.generate() ).toBe( true );
		expect( ui.html() ).toContain( 'MARIA DA SILVA' );
		expect( document.querySelectorAll( '.esg-errors li' ) ).toHaveLength( 0 );
	} );

	it( 'offers the download unless the site turned it off', () => {
		document.body.innerHTML = '<div id="c"></div>';
		gen.mount( document.getElementById( 'c' ), config );
		expect( document.querySelector( '.esg-download' ) ).not.toBeNull();

		document.body.innerHTML = '<div id="c"></div>';
		gen.mount( document.getElementById( 'c' ), { ...config, offerDownload: false } );
		expect( document.querySelector( '.esg-download' ) ).toBeNull();
		expect( document.querySelector( '.esg-copy' ) ).not.toBeNull();
	} );

	it.each( [ [ 'notice', true ], [ 'more', true ], [ 'plain', false ] ] )( 'after a download in the %s style, recommends Copy HTML: %s', async ( style, notes ) => {
		document.body.innerHTML = '<div id="c"></div>';
		const ui = gen.mount( document.getElementById( 'c' ), { ...config, downloadStyle: style } );
		Object.entries( valid ).forEach( ( [ key, value ] ) => {
			const input = document.querySelector( `[name="${ key }"]` );
			if ( input ) {
				input.value = value;
			}
		} );
		expect( ui.generate() ).toBe( true );
		window.html2canvas = () => Promise.resolve( { width: 0, toDataURL: () => 'data:image/png;base64,' } );
		const click = HTMLAnchorElement.prototype.click;
		HTMLAnchorElement.prototype.click = () => {};
		try {
			document.querySelector( '.esg-download' ).click();
			await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
			expect( document.querySelector( '.esg-status' ).textContent ).toBe( notes ? config.strings.downloaded : '' );
		} finally {
			HTMLAnchorElement.prototype.click = click;
			delete window.html2canvas;
		}
	} );

	it( 'shows Copy HTML and the download only after a signature is generated', () => {
		document.body.innerHTML = '<div id="c"></div>';
		const ui = gen.mount( document.getElementById( 'c' ), config );
		const output = document.querySelector( '.esg-output' );
		expect( output.hidden ).toBe( true );
		expect( output.contains( document.querySelector( '.esg-copy' ) ) ).toBe( true );
		expect( output.contains( document.querySelector( '.esg-download' ) ) ).toBe( true );
		expect( output.contains( document.querySelector( '.esg-hint' ) ) ).toBe( true );

		expect( ui.generate() ).toBe( false );
		expect( output.hidden ).toBe( true );

		Object.entries( valid ).forEach( ( [ key, value ] ) => {
			const input = document.querySelector( `[name="${ key }"]` );
			if ( input ) {
				input.value = value;
			}
		} );
		expect( ui.generate() ).toBe( true );
		expect( output.hidden ).toBe( false );

		// Editing a field makes the signature stale until it is generated again.
		document.querySelector( '[name="name"]' ).dispatchEvent( new Event( 'input', { bubbles: true } ) );
		expect( output.hidden ).toBe( true );
		expect( ui.generate() ).toBe( true );
		ui.select( 'department' );
		expect( output.hidden ).toBe( true );
	} );

	it( 'puts the download in a collapsed "More options" when the site asks', () => {
		document.body.innerHTML = '<div id="c"></div>';
		gen.mount( document.getElementById( 'c' ), { ...config, downloadStyle: 'more' } );
		const details = document.querySelector( 'details.esg-more' );
		expect( details ).not.toBeNull();
		expect( details.open ).toBe( false );
		expect( details.querySelector( 'summary' ).textContent ).toBe( config.strings.moreOptions );
		expect( details.contains( document.querySelector( '.esg-download' ) ) ).toBe( true );
	} );

	it( 'switches the fields with the model', () => {
		document.body.innerHTML = '<div id="c"></div>';
		const ui = gen.mount( document.getElementById( 'c' ), config );
		ui.select( 'department' );
		expect( document.querySelector( 'select[name="region"]' ) ).not.toBeNull();
		expect( document.querySelector( '[name="role"]' ) ).toBeNull();
	} );

	it( 'hides the model choice when only one model is offered', () => {
		document.body.innerHTML = '<div id="c"></div>';
		gen.mount( document.getElementById( 'c' ), { ...config, models: [ config.models[ 1 ] ] } );
		expect( document.querySelector( '.esg-model' ).closest( 'p' ).hidden ).toBe( true );
	} );

	it( 'mounts every container once', () => {
		document.body.innerHTML = `<div data-esg-config='${ JSON.stringify( config ).replace( /'/g, '&#39;' ) }'></div>`;
		gen.mountAll( document );
		gen.mountAll( document );
		expect( document.querySelectorAll( '.esg-form' ) ).toHaveLength( 1 );
	} );
} );

describe( 'settings in the configuration', () => {
	it( 'refuses an address outside the allowed domain', () => {
		const cfg = { ...config, emailDomain: 'sme.prefeitura.sp.gov.br' };
		expect( gen.validate( model( 'staff_regional' ), valid, cfg ) ).toEqual( [] );

		const errors = gen.validate( model( 'staff_regional' ), { ...valid, email: 'maria@gmail.com' }, cfg );
		expect( errors.map( ( e ) => e.key ) ).toEqual( [ 'email' ] );
		expect( errors[ 0 ].message ).toContain( 'sme.prefeitura.sp.gov.br' );
	} );

	it( 'does not accept a domain that merely ends the same way', () => {
		const cfg = { ...config, emailDomain: 'example.org' };
		const errors = gen.validate( model( 'staff_regional' ), { ...valid, email: 'a@evilexample.org' }, cfg );
		expect( errors.map( ( e ) => e.key ) ).toEqual( [ 'email' ] );
	} );

	it( 'shows a locked field read-only and always uses its default', () => {
		const locked = JSON.parse( JSON.stringify( config ) );
		const field = locked.models[ 0 ].fields.find( ( f ) => 'address' === f.key );
		field.default = 'Rua A, 1';
		field.locked = true;

		document.body.innerHTML = '<div id="c"></div>';
		const ui = gen.mount( document.getElementById( 'c' ), locked );
		const input = document.querySelector( '[name="address"]' );
		expect( input.hasAttribute( 'readonly' ) ).toBe( true );

		input.value = 'Changed in the inspector';
		Object.entries( valid ).forEach( ( [ key, value ] ) => {
			const el = document.querySelector( `[name="${ key }"]` );
			if ( el && 'address' !== key ) {
				el.value = value;
			}
		} );
		expect( ui.generate() ).toBe( true );
		expect( ui.html() ).toContain( 'Rua A, 1' );
		expect( ui.html() ).not.toContain( 'Changed in the inspector' );
	} );
} );

describe( 'imageOptions', () => {
	it( 'reads the configured density and quality', () => {
		expect( gen.imageOptions( config ) ).toEqual( { format: 'png', scale: 1, quality: 0.9 } );
		expect( gen.imageOptions( { image: { format: 'jpg', scale: 3, quality: 75 } } ) ).toEqual( { format: 'jpg', scale: 3, quality: 0.75 } );
		expect( gen.imageOptions( { image: { format: 'gif' } } ).format ).toBe( 'png' );
	} );

	it( 'falls back to 1× at 90% on a missing or out-of-range value', () => {
		expect( gen.imageOptions( {} ) ).toEqual( { format: 'png', scale: 1, quality: 0.9 } );
		expect( gen.imageOptions( null ) ).toEqual( { format: 'png', scale: 1, quality: 0.9 } );
		expect( gen.imageOptions( { image: { scale: 9, quality: 10 } } ) ).toEqual( { format: 'png', scale: 1, quality: 0.9 } );
		expect( gen.imageOptions( { image: { scale: 'x', quality: 'y' } } ) ).toEqual( { format: 'png', scale: 1, quality: 0.9 } );
	} );
} );

describe( 'exportSize', () => {
	it( 'keeps the signature size at 1× and draws it at 2× to smooth the text', () => {
		expect( gen.exportSize( 450, 160, 1 ) ).toEqual( { render: 2, width: 450, height: 160 } );
	} );

	it( 'multiplies the final size by the density and never draws below it', () => {
		expect( gen.exportSize( 450, 160, 2 ) ).toEqual( { render: 2, width: 900, height: 320 } );
		expect( gen.exportSize( 450, 160.4, 3 ) ).toEqual( { render: 3, width: 1350, height: 481 } );
	} );
} );
