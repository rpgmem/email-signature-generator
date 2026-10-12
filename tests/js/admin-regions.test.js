import { beforeEach, describe, expect, it } from 'vitest';
import { runScript } from './helpers.js';

function row( i, name, acronym ) {
	return `<tr><td><input name="o[regions][${ i }][name]" value="${ name }" required></td>`
		+ `<td><input class="esg-acronym" name="o[regions][${ i }][acronym]" value="${ acronym }" required></td>`
		+ '<td><button type="button" class="esg-regions-delete">x</button></td></tr>';
}

beforeEach( () => {
	window.esgAdmin = {};
	document.body.innerHTML = `<table><tbody class="esg-regions-rows">${ row( 0, 'Norte', 'DREN' ) }${ row( 1, 'Sul', 'DRES' ) }</tbody></table>`
		+ `<template class="esg-regions-template">${ row( '__i__', '', '' ) }</template>`
		+ '<button type="button" class="esg-regions-add">add</button>';
	runScript( 'assets/js/admin-settings.js' );
} );

describe( 'regional offices table', () => {
	it( 'adds a row with the next index and focuses it', () => {
		document.querySelector( '.esg-regions-add' ).click();
		const rows = document.querySelectorAll( '.esg-regions-rows tr' );
		expect( rows ).toHaveLength( 3 );
		expect( rows[ 2 ].querySelector( 'input' ).name ).toBe( 'o[regions][2][name]' );
		expect( document.activeElement ).toBe( rows[ 2 ].querySelector( 'input' ) );

		document.querySelector( '.esg-regions-add' ).click();
		expect( document.querySelectorAll( '.esg-regions-rows tr' )[ 3 ].querySelector( 'input' ).name ).toBe( 'o[regions][3][name]' );
	} );

	it( 'removes a row', () => {
		document.querySelector( '.esg-regions-delete' ).click();
		const rows = document.querySelectorAll( '.esg-regions-rows tr' );
		expect( rows ).toHaveLength( 1 );
		expect( rows[ 0 ].querySelector( 'input' ).value ).toBe( 'Sul' );
	} );

	it( 'writes the acronym in capitals without spaces', () => {
		const acronym = document.querySelector( '.esg-acronym' );
		acronym.value = 'dre n';
		acronym.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		expect( acronym.value ).toBe( 'DREN' );
	} );
} );
