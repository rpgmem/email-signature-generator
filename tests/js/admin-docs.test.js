import { beforeEach, describe, expect, it } from 'vitest';
import { runScript } from './helpers.js';

function card( id, title, text ) {
	return `<div class="card esg-doc"><h2 id="esg-doc-${ id }"><span>${ title }</span></h2><p>${ text }</p></div>`;
}

beforeEach( () => {
	window.esgAdmin = {};
	document.body.innerHTML = `<div class="esg-doc-layout">
		<nav class="card esg-doc-contents"><p class="esg-doc-search"><input type="search" id="esg-doc-search"></p><ul>
			<li><a href="#esg-doc-general">General</a></li>
			<li><a href="#esg-doc-logo">Logo</a></li>
			<li><a href="#esg-doc-gone">Gone</a></li>
		</ul><p class="esg-doc-search-empty" hidden>None</p></nav>
		<div class="esg-doc-topics">${ card( 'general', 'General', 'Which models are offered.' ) }${ card( 'logo', 'Logo', 'Alt text for screen readers.' ) }</div>
	</div>`;
	runScript( 'assets/js/admin-settings.js' );
} );

function search( text ) {
	const input = document.getElementById( 'esg-doc-search' );
	input.value = text;
	input.dispatchEvent( new Event( 'input' ) );
}

const rows = () => [ ...document.querySelectorAll( '.esg-doc-contents li' ) ].map( ( li ) => li.hidden );
const cards = () => [ ...document.querySelectorAll( '.esg-doc' ) ].map( ( c ) => c.hidden );

describe( 'documentation search', () => {
	it( 'keeps a topic whose card text matches, row and card alike', () => {
		search( 'SCREEN readers' );
		expect( cards() ).toEqual( [ true, false ] );
		expect( rows() ).toEqual( [ true, false, true ] );
		expect( document.querySelector( '.esg-doc-search-empty' ).hidden ).toBe( true );
	} );

	it( 'matches a row with no card by its own title', () => {
		search( 'gone' );
		expect( rows() ).toEqual( [ true, true, false ] );
	} );

	it( 'says so when nothing matches, and an empty search shows everything again', () => {
		search( 'nothing like this' );
		expect( rows() ).toEqual( [ true, true, true ] );
		expect( document.querySelector( '.esg-doc-search-empty' ).hidden ).toBe( false );

		search( '  ' );
		expect( rows() ).toEqual( [ false, false, false ] );
		expect( cards() ).toEqual( [ false, false ] );
		expect( document.querySelector( '.esg-doc-search-empty' ).hidden ).toBe( true );
	} );
} );
