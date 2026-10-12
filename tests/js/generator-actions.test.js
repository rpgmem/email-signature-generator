import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { loadConfig, loadGenerator, runScript } from './helpers.js';

let config;
let gen;

const valid = {
	name: 'Maria da Silva',
	sector: 'NTIC',
	office: 'DREMP',
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

function mount( cfg = config ) {
	document.body.innerHTML = '<div id="c"></div>';
	return gen.mount( document.getElementById( 'c' ), cfg );
}

function fill( values ) {
	Object.entries( values ).forEach( ( [ key, value ] ) => {
		const input = document.querySelector( `[name="${ key }"]` );
		if ( input ) {
			input.value = value;
		}
	} );
}

function status() {
	return document.querySelector( '.esg-status' ).textContent;
}

function setClipboard( value ) {
	Object.defineProperty( navigator, 'clipboard', { configurable: true, value } );
}

const tick = () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

describe( 'Copy HTML', () => {
	let execCommand;

	beforeEach( () => {
		execCommand = document.execCommand;
	} );

	afterEach( () => {
		document.execCommand = execCommand;
		delete window.ClipboardItem;
		delete navigator.clipboard;
		vi.restoreAllMocks();
	} );

	it( 'writes rich text and plain text through the Clipboard API', async () => {
		const items = [];
		window.ClipboardItem = class {
			constructor( parts ) {
				this.types = Object.keys( parts );
			}
		};
		const write = vi.fn( ( list ) => {
			items.push( ...list );
			return Promise.resolve();
		} );
		setClipboard( { write } );

		mount();
		fill( valid );
		document.querySelector( '.esg-copy' ).click();
		await tick();

		expect( write ).toHaveBeenCalledTimes( 1 );
		expect( items[ 0 ].types ).toEqual( [ 'text/html', 'text/plain' ] );
		expect( status() ).toBe( config.strings.copied );
	} );

	it( 'falls back to a selection copy when the Clipboard API refuses', async () => {
		window.ClipboardItem = class {};
		setClipboard( { write: () => Promise.reject( new Error( 'denied' ) ) } );
		document.execCommand = vi.fn( () => true );

		mount();
		fill( valid );
		document.querySelector( '.esg-copy' ).click();
		await tick();

		expect( document.execCommand ).toHaveBeenCalledWith( 'copy' );
		expect( status() ).toBe( config.strings.copied );
	} );

	it( 'says the copy failed when the browser cannot copy, and leaves nothing behind', async () => {
		setClipboard( undefined );
		document.execCommand = vi.fn( () => {
			throw new Error( 'not allowed' );
		} );

		mount();
		fill( valid );
		const before = document.body.children.length;
		document.querySelector( '.esg-copy' ).click();
		await tick();

		expect( status() ).toBe( config.strings.copyFailed );
		expect( document.body.children.length ).toBe( before );
		expect( document.getSelection().rangeCount ).toBe( 0 );
	} );

	it( 'reports a selection copy the browser declined', async () => {
		setClipboard( undefined );
		document.execCommand = vi.fn( () => false );

		mount();
		fill( valid );
		document.querySelector( '.esg-copy' ).click();
		await tick();

		expect( status() ).toBe( config.strings.copyFailed );
	} );

	it( 'copies nothing while the form has errors', async () => {
		document.execCommand = vi.fn( () => true );

		mount();
		document.querySelector( '.esg-copy' ).click();
		await tick();

		expect( document.execCommand ).not.toHaveBeenCalled();
		expect( status() ).toBe( '' );
		expect( document.querySelector( '[name="name"]' ).getAttribute( 'aria-invalid' ) ).toBe( 'true' );
	} );
} );

describe( 'Download image', () => {
	let click;

	beforeEach( () => {
		click = HTMLAnchorElement.prototype.click;
		HTMLAnchorElement.prototype.click = vi.fn();
	} );

	afterEach( () => {
		HTMLAnchorElement.prototype.click = click;
		delete window.html2canvas;
		vi.restoreAllMocks();
	} );

	it( 'downloads nothing while the form has errors', () => {
		window.html2canvas = vi.fn();

		mount();
		document.querySelector( '.esg-download' ).click();

		expect( window.html2canvas ).not.toHaveBeenCalled();
		expect( status() ).toBe( '' );
	} );

	it( 'says the download failed when the image library did not load', () => {
		mount();
		fill( valid );
		document.querySelector( '.esg-download' ).click();

		expect( status() ).toBe( config.strings.downloadFailed );
		expect( document.querySelector( '.esg-stage' ) ).toBeNull();
	} );

	it( 'says the download failed when drawing fails, and removes the stage', async () => {
		window.html2canvas = vi.fn( () => Promise.reject( new Error( 'tainted canvas' ) ) );

		mount();
		fill( valid );
		document.querySelector( '.esg-download' ).click();
		await tick();
		await tick();

		expect( status() ).toBe( config.strings.downloadFailed );
		expect( document.querySelector( '.esg-stage' ) ).toBeNull();
		expect( HTMLAnchorElement.prototype.click ).not.toHaveBeenCalled();
	} );

	it( 'reduces a larger drawing to the final size and saves a JPG at the configured quality', async () => {
		const drawImage = vi.fn();
		vi.spyOn( HTMLCanvasElement.prototype, 'getContext' ).mockReturnValue( { drawImage } );
		const toDataURL = vi.spyOn( HTMLCanvasElement.prototype, 'toDataURL' ).mockReturnValue( 'data:image/jpeg;base64,' );
		window.html2canvas = vi.fn( () => Promise.resolve( { width: 9999, height: 9999 } ) );

		mount( { ...config, image: { format: 'jpg', quality: 80, scale: 2 } } );
		fill( valid );
		document.querySelector( '.esg-download' ).click();
		await tick();
		await tick();

		expect( window.html2canvas.mock.calls[ 0 ][ 1 ] ).toMatchObject( { useCORS: true, scale: 2 } );
		expect( drawImage ).toHaveBeenCalledTimes( 1 );
		expect( toDataURL ).toHaveBeenCalledWith( 'image/jpeg', 0.8 );
		const link = HTMLAnchorElement.prototype.click.mock.contexts[ 0 ];
		expect( link.getAttribute( 'download' ) ).toBe( 'Maria_da_Silva_' + config.models[ 0 ].id + '.jpg' );
		expect( document.querySelector( '.esg-stage' ) ).toBeNull();
	} );
} );

describe( 'remembered values', () => {
	it( 'falls back to the default when a remembered office is no longer offered', () => {
		window.localStorage.clear();
		const cfg = { ...config, rememberValues: true };
		const ui = mount( cfg );
		fill( valid );
		expect( ui.generate() ).toBe( true );

		mount( { ...cfg, offices: cfg.offices.filter( ( office ) => 'DREMP' !== office.acronym ) } );

		expect( document.querySelector( '[name="name"]' ).value ).toBe( valid.name );
		expect( document.querySelector( '[name="office"]' ).value ).not.toBe( 'DREMP' );
		window.localStorage.clear();
	} );
} );

describe( 'loading in a browser', () => {
	afterEach( () => {
		delete window.EmailSignatureGenerator;
	} );

	it( 'mounts every container once the page has loaded', () => {
		document.body.innerHTML = '<div class="a"></div>';
		document.querySelector( '.a' ).setAttribute( 'data-esg-config', JSON.stringify( config ) );

		runScript( 'assets/js/signature-generator.js' );

		expect( typeof window.EmailSignatureGenerator.mount ).toBe( 'function' );
		expect( document.querySelector( '.a' ).getAttribute( 'data-esg-mounted' ) ).toBe( '1' );
		expect( document.querySelector( '.a .esg-model' ) ).not.toBeNull();
	} );

	it( 'waits for the document when the script runs while it is still loading', () => {
		document.body.innerHTML = '<div class="b"></div>';
		document.querySelector( '.b' ).setAttribute( 'data-esg-config', JSON.stringify( config ) );
		const state = vi.spyOn( document, 'readyState', 'get' ).mockReturnValue( 'loading' );

		runScript( 'assets/js/signature-generator.js' );
		expect( document.querySelector( '.b' ).getAttribute( 'data-esg-mounted' ) ).toBeNull();

		state.mockRestore();
		document.dispatchEvent( new Event( 'DOMContentLoaded' ) );
		expect( document.querySelector( '.b' ).getAttribute( 'data-esg-mounted' ) ).toBe( '1' );
	} );
} );
