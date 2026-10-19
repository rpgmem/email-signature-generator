import { beforeEach, describe, expect, it } from 'vitest';
import { runScript } from './helpers.js';

function row( i, domain, client ) {
	return `<tr><td><input name="o[mail_clients][${ i }][domain]" value="${ domain }" required></td>`
		+ `<td><select name="o[mail_clients][${ i }][client]"><option value="none">None</option><option value="outlook"${ 'outlook' === client ? ' selected' : '' }>Outlook</option><option value="gmail"${ 'gmail' === client ? ' selected' : '' }>Gmail</option></select></td>`
		+ '<td><button type="button" class="esg-mail-clients-delete">x</button></td></tr>';
}

beforeEach( () => {
	window.esgAdmin = {};
	document.body.innerHTML = `<table><tbody class="esg-mail-clients-rows">${ row( 0, 'sme.prefeitura.sp.gov.br', 'outlook' ) }${ row( 1, 'edu.sme.prefeitura.sp.gov.br', 'gmail' ) }</tbody></table>`
		+ `<template class="esg-mail-clients-template">${ row( '__i__', '', 'none' ) }</template>`
		+ '<button type="button" class="esg-mail-clients-add">add</button>';
	runScript( 'assets/js/admin-settings.js' );
} );

describe( 'install-instructions domains table (#107)', () => {
	it( 'adds a row with the next index, set to None, and focuses it', () => {
		document.querySelector( '.esg-mail-clients-add' ).click();
		const rows = document.querySelectorAll( '.esg-mail-clients-rows tr' );
		expect( rows ).toHaveLength( 3 );
		expect( rows[ 2 ].querySelector( 'input' ).name ).toBe( 'o[mail_clients][2][domain]' );
		expect( rows[ 2 ].querySelector( 'select' ).value ).toBe( 'none' );
		expect( document.activeElement ).toBe( rows[ 2 ].querySelector( 'input' ) );
	} );

	it( 'removes a row', () => {
		document.querySelector( '.esg-mail-clients-delete' ).click();
		const rows = document.querySelectorAll( '.esg-mail-clients-rows tr' );
		expect( rows ).toHaveLength( 1 );
		expect( rows[ 0 ].querySelector( 'input' ).value ).toBe( 'edu.sme.prefeitura.sp.gov.br' );
	} );
} );
