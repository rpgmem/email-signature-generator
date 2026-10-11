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

describe( 'formatPhone', () => {
	it( 'writes a number the way the signature prints it', () => {
		expect( gen.formatPhone( '(11) 3397-1234' ) ).toBe( '55 11 3397-1234' );
		expect( gen.formatPhone( '11933971234' ) ).toBe( '55 11 93397-1234' );
		expect( gen.formatPhone( '+55 (11) 93397-1234' ) ).toBe( '55 11 93397-1234' );
		expect( gen.formatPhone( '55 11 3397-1234' ) ).toBe( '55 11 3397-1234' );
	} );

	it( 'joins several numbers with a bar', () => {
		expect( gen.formatPhone( '11 3397-1234 / 11 93397-5678' ) ).toBe( '55 11 3397-1234 | 55 11 93397-5678' );
		expect( gen.formatPhone( '1133971234;11933975678' ) ).toBe( '55 11 3397-1234 | 55 11 93397-5678' );
	} );

	it( 'leaves what is not a number with its area code as typed, for the check to name', () => {
		expect( gen.formatPhone( '3397-1234' ) ).toBe( '3397-1234' );
		expect( gen.formatPhone( '1 800 123 4567' ) ).toBe( '1 800 123 4567' );
		expect( gen.formatPhone( '' ) ).toBe( '' );
	} );
} );

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

	it( 'links every phone number so a tap dials it', () => {
		const html = gen.render( model( 'staff_regional' ), { ...valid, phone: '55 11 3397-1234 | 55 11 93397-5678' }, config );
		expect( html ).toContain( '<a href="tel:+551133971234" style="text-decoration: none; color: inherit;">55 11 3397-1234</a>' );
		expect( html ).toContain( '<a href="tel:+5511933975678" style="text-decoration: none; color: inherit;">55 11 93397-5678</a>' );
		expect( html ).toContain( '</a> | <a href="tel:+5511933975678"' );
	} );

	function withFields( id, changes ) {
		const m = JSON.parse( JSON.stringify( model( id ) ) );
		m.fields.forEach( ( f ) => Object.assign( f, changes[ f.key ] || {} ) );
		return m;
	}

	it( 'keeps a field as typed when its capitals are off, with no CSS forcing them', () => {
		const html = gen.render( withFields( 'staff_regional', { name: { uppercase: false }, sector: { uppercase: false } } ), { ...valid, sector: 'Ntic' }, config );
		expect( html ).toContain( 'Maria da Silva' );
		expect( html ).toContain( 'Ntic / DREMP' );
		expect( html ).not.toContain( 'text-transform' );
	} );

	it( 'prints the department and the role in capitals by default, accents included', () => {
		expect( gen.render( model( 'staff_regional' ), { ...valid, sector: 'gestão' }, config ) ).toContain( 'GESTÃO / DREMP' );
		expect( gen.render( model( 'staff_school' ), { ...valid, role: 'Secretária' }, config ) ).toContain( 'SECRETÁRIA' );
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
		expect( school ).toContain( '>PROFESSORA</div>' );
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
		const cfg = { ...config, emailDomains: [ 'sme.prefeitura.sp.gov.br' ] };
		expect( gen.validate( model( 'staff_regional' ), valid, cfg ) ).toEqual( [] );

		const errors = gen.validate( model( 'staff_regional' ), { ...valid, email: 'maria@gmail.com' }, cfg );
		expect( errors.map( ( e ) => e.key ) ).toEqual( [ 'email' ] );
		expect( errors[ 0 ].message ).toContain( 'sme.prefeitura.sp.gov.br' );
	} );

	it( 'does not accept a domain that merely ends the same way', () => {
		const cfg = { ...config, emailDomains: [ 'example.org' ] };
		const errors = gen.validate( model( 'staff_regional' ), { ...valid, email: 'a@evilexample.org' }, cfg );
		expect( errors.map( ( e ) => e.key ) ).toEqual( [ 'email' ] );
	} );

	it( 'accepts any of several domains and names them all when none matches', () => {
		const cfg = { ...config, emailDomains: [ 'sme.prefeitura.sp.gov.br', 'prefeitura.sp.gov.br' ] };
		expect( gen.validate( model( 'staff_regional' ), { ...valid, email: 'a@prefeitura.sp.gov.br' }, cfg ) ).toEqual( [] );

		const errors = gen.validate( model( 'staff_regional' ), { ...valid, email: 'a@gmail.com' }, cfg );
		expect( errors[ 0 ].message ).toContain( 'sme.prefeitura.sp.gov.br, prefeitura.sp.gov.br' );
	} );

	function fill( values ) {
		Object.entries( values ).forEach( ( [ key, value ] ) => {
			const input = document.querySelector( `[name="${ key }"]` );
			if ( input ) {
				input.value = value;
			}
		} );
	}

	it( 'refuses an "@" in the part typed beside the domain', () => {
		const cfg = { ...config, emailDomains: [ 'sme.prefeitura.sp.gov.br' ] };
		const errors = gen.validate( model( 'staff_regional' ), { ...valid, email: 'maria@gmail.com@sme.prefeitura.sp.gov.br' }, cfg );
		expect( errors ).toEqual( [ { key: 'email', message: config.strings.emailLocalOnly } ] );
	} );

	it( 'refuses a placeholder domain, and its subdomains, when any address is accepted', () => {
		const cfg = { ...config, emailDomains: [], blockedEmailDomains: [ 'test.com' ] };
		for ( const email of [ 'a@test.com', 'a@TEST.com', 'a@mail.test.com' ] ) {
			const errors = gen.validate( model( 'staff_regional' ), { ...valid, email }, cfg );
			expect( errors.map( ( e ) => e.message ) ).toEqual( [ config.strings.emailBlocked.replace( '%s', 'test.com' ) ] );
		}
		expect( gen.validate( model( 'staff_regional' ), { ...valid, email: 'a@latest.com' }, cfg ) ).toEqual( [] );
		expect( gen.validate( model( 'staff_regional' ), { ...valid, email: 'a@gmail.com' }, cfg ) ).toEqual( [] );
	} );

	it( 'ships a list of placeholder domains by default', () => {
		expect( config.blockedEmailDomains ).toContain( 'test.com' );
		expect( gen.validate( model( 'staff_regional' ), { ...valid, email: 'a@example.com' }, config ) ).toHaveLength( 1 );
	} );

	it( 'explains where to paste the signature, collapsed under the result', () => {
		document.body.innerHTML = '<div id="c"></div>';
		gen.mount( document.getElementById( 'c' ), config );
		const howto = document.querySelector( '.esg-output details.esg-howto' );
		expect( howto ).not.toBeNull();
		expect( howto.open ).toBe( false );
		expect( howto.querySelector( 'summary' ).textContent ).toBe( config.strings.howTo );
		expect( [ ...howto.querySelectorAll( 'li' ) ].map( ( li ) => li.textContent ) ).toEqual( [
			config.strings.howToOutlookWeb,
			config.strings.howToOutlook,
			config.strings.howToGmail,
			config.strings.howToPhone,
		] );
	} );

	it( 'formats the phone on leaving the field and when generating', () => {
		document.body.innerHTML = '<div id="c"></div>';
		const ui = gen.mount( document.getElementById( 'c' ), config );
		fill( { ...valid, phone: '(11) 3397-1234' } );
		const phone = document.querySelector( '[name="phone"]' );
		phone.dispatchEvent( new window.Event( 'blur' ) );
		expect( phone.value ).toBe( '55 11 3397-1234' );

		phone.value = '11933975678';
		expect( ui.generate() ).toBe( true );
		expect( ui.html() ).toContain( 'tel:+5511933975678' );
	} );

	it( 'remembers nothing unless the site allows it', () => {
		window.localStorage.clear();
		document.body.innerHTML = '<div id="c"></div>';
		const ui = gen.mount( document.getElementById( 'c' ), config );
		fill( valid );
		expect( ui.generate() ).toBe( true );
		expect( window.localStorage.length ).toBe( 0 );
		expect( document.querySelector( '.esg-forget' ) ).toBeNull();
	} );

	it( 'fills the form again from this browser, and forgets on request', () => {
		window.localStorage.clear();
		const cfg = { ...config, rememberValues: true };
		document.body.innerHTML = '<div id="c"></div>';
		let ui = gen.mount( document.getElementById( 'c' ), cfg );
		ui.select( 'staff_school' );
		fill( { ...valid, role: 'Professora' } );
		expect( ui.generate() ).toBe( true );

		document.body.innerHTML = '<div id="c"></div>';
		ui = gen.mount( document.getElementById( 'c' ), cfg );
		expect( document.querySelector( '.esg-model' ).value ).toBe( 'staff_school' );
		expect( document.querySelector( '[name="name"]' ).value ).toBe( 'Maria da Silva' );
		expect( document.querySelector( '[name="role"]' ).value ).toBe( 'Professora' );

		document.querySelector( '.esg-forget' ).click();
		expect( window.localStorage.length ).toBe( 0 );
		expect( document.querySelector( '[name="name"]' ).value ).toBe( '' );
		expect( document.querySelector( '.esg-status' ).textContent ).toBe( config.strings.forgotten );
	} );

	it( 'shows the preview in dark mode without touching what is copied', () => {
		document.body.innerHTML = '<div id="c"></div>';
		const ui = gen.mount( document.getElementById( 'c' ), config );
		fill( valid );
		ui.generate();
		const box = document.querySelector( '.esg-dark-toggle' );
		box.checked = true;
		box.dispatchEvent( new window.Event( 'change' ) );
		expect( document.querySelector( '.esg-preview' ).classList.contains( 'esg-preview--dark' ) ).toBe( true );
		expect( ui.html() ).not.toContain( '<style>' );
	} );

	it( 'leaves the email field free when no domain is set', () => {
		document.body.innerHTML = '<div id="c"></div>';
		gen.mount( document.getElementById( 'c' ), config );
		expect( document.querySelector( '.esg-email-group' ) ).toBeNull();
		expect( document.querySelector( '[name="email"]' ).getAttribute( 'type' ) ).toBe( 'email' );
	} );

	it( 'shows one domain beside the field and joins it to what is typed', () => {
		document.body.innerHTML = '<div id="c"></div>';
		const ui = gen.mount( document.getElementById( 'c' ), { ...config, emailDomains: [ 'sme.prefeitura.sp.gov.br' ] } );
		const domain = document.querySelector( '.esg-email-group .esg-email-domain' );
		expect( domain.tagName ).toBe( 'SPAN' );
		expect( domain.textContent ).toBe( '@sme.prefeitura.sp.gov.br' );
		expect( document.querySelector( '[name="email"]' ).getAttribute( 'aria-describedby' ) ).toBe( domain.id );

		fill( { ...valid, email: 'Maria.Silva' } );
		expect( ui.generate() ).toBe( true );
		expect( ui.html() ).toContain( 'mailto:maria.silva@sme.prefeitura.sp.gov.br' );
	} );

	it( 'offers several domains in a list, the first chosen', () => {
		document.body.innerHTML = '<div id="c"></div>';
		const ui = gen.mount( document.getElementById( 'c' ), { ...config, emailDomains: [ 'sme.prefeitura.sp.gov.br', 'prefeitura.sp.gov.br' ] } );
		const select = document.querySelector( 'select.esg-email-domain' );
		expect( [ ...select.options ].map( ( o ) => o.textContent ) ).toEqual( [ '@sme.prefeitura.sp.gov.br', '@prefeitura.sp.gov.br' ] );
		expect( select.value ).toBe( 'sme.prefeitura.sp.gov.br' );
		expect( select.getAttribute( 'aria-label' ) ).toBe( config.strings.emailDomain );

		fill( { ...valid, email: 'maria' } );
		select.value = 'prefeitura.sp.gov.br';
		expect( ui.generate() ).toBe( true );
		expect( ui.html() ).toContain( 'mailto:maria@prefeitura.sp.gov.br' );
	} );

	it( 'splits a pasted address at an accepted domain, and refuses another', () => {
		document.body.innerHTML = '<div id="c"></div>';
		const ui = gen.mount( document.getElementById( 'c' ), { ...config, emailDomains: [ 'sme.prefeitura.sp.gov.br', 'prefeitura.sp.gov.br' ] } );
		fill( valid );
		const local = document.querySelector( '[name="email"]' );
		local.value = 'Maria@Prefeitura.SP.gov.br';
		local.dispatchEvent( new window.Event( 'input', { bubbles: true } ) );
		expect( local.value ).toBe( 'Maria' );
		expect( document.querySelector( 'select.esg-email-domain' ).value ).toBe( 'prefeitura.sp.gov.br' );

		local.value = 'maria@gmail.com';
		local.dispatchEvent( new window.Event( 'input', { bubbles: true } ) );
		expect( local.value ).toBe( 'maria@gmail.com' );
		expect( ui.generate() ).toBe( false );
		expect( local.getAttribute( 'aria-invalid' ) ).toBe( 'true' );
		expect( document.querySelector( '.esg-errors' ).textContent ).toContain( config.strings.emailLocalOnly );
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
