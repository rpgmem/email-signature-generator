import { beforeEach, describe, expect, it, vi } from 'vitest';
import { runScript } from './helpers.js';

function flush() {
	return new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
}

function boot( answer ) {
	window.fetch = vi.fn( () => Promise.resolve( { json: () => Promise.resolve( answer ) } ) );
	runScript( 'assets/js/admin-settings.js' );
}

beforeEach( () => {
	window.esgAdmin = { ajaxUrl: '/ajax', action: 'esg_autosave', nonce: 'n', saving: 'Saving', saved: 'Saved', failed: 'Not saved' };
	document.body.innerHTML = `<p><select id="m" data-esg-autosave="dark_mode"><option value="off" selected>Off</option><option value="on">On</option><option value="auto">Auto</option></select></p>`;
} );

describe( 'dark mode select', () => {
	it( 'saves the chosen value and announces it, so the page repaints', async () => {
		boot( { success: true, data: { value: 'on' } } );
		const heard = vi.fn();
		document.addEventListener( 'esg:setting-saved', heard );
		const select = document.getElementById( 'm' );
		select.value = 'on';
		select.dispatchEvent( new Event( 'change' ) );

		const body = window.fetch.mock.calls[ 0 ][ 1 ].body;
		expect( body.get( 'key' ) ).toBe( 'dark_mode' );
		expect( body.get( 'value' ) ).toBe( 'on' );
		await flush();
		expect( heard ).toHaveBeenCalledTimes( 1 );
		expect( heard.mock.calls[ 0 ][ 0 ].detail ).toEqual( { key: 'dark_mode', value: 'on' } );
		expect( document.querySelector( '.esg-autosave-badge' ).className ).toContain( '--saved' );
		document.removeEventListener( 'esg:setting-saved', heard );
	} );

	it( 'puts the saved value back when the save fails', async () => {
		boot( { success: false, data: { message: 'No' } } );
		const select = document.getElementById( 'm' );
		select.value = 'auto';
		select.dispatchEvent( new Event( 'change' ) );
		await flush();
		expect( select.value ).toBe( 'off' );
		expect( document.querySelector( '.esg-autosave-badge' ).textContent ).toBe( 'No' );
	} );
} );
