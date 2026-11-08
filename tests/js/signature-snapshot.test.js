// The signature each model produces, frozen (#122).
//
// The other render tests check one property at a time: an escaped value, a
// link, a capital letter. None of them sees the table as a whole, and the
// table is what Outlook and Gmail parse: a moved cell, a dropped inline style
// or a changed width breaks the signature in the mail client with every
// property test still green. A snapshot makes any change to the markup a diff
// someone has to read and accept (`npx vitest run -u`), never a silent one.
//
// The logo URL is replaced by a fixed one: the standalone build embeds the
// image, and its bytes are not what this test is about.
import { beforeAll, describe, expect, it } from 'vitest';
import { loadConfig, loadGenerator } from './helpers.js';

const MODELS = [ 'staff_regional', 'staff_school', 'department' ];

const input = {
	name: 'Maria da Silva',
	sector: 'NTIC',
	office: 'DREMP',
	region: 'Diretoria Regional de Educação São Miguel / DREMP',
	role: 'Professora',
	school: 'EMEF Joaquim José da Silva Xavier',
	email: 'Maria.Silva@sme.prefeitura.sp.gov.br',
	phone: '55 11 3397-1234 | 55 11 93397-5678',
	address: 'Av. Nordestina, 747',
	postal: '08021-000 | São Paulo | SP',
	link1: 'educacao.sme.prefeitura.sp.gov.br',
	link2: 'https://www.example.org/agenda',
};

let config;
let gen;

beforeAll( () => {
	config = loadConfig();
	config.logo = { ...config.logo, url: 'https://example.org/logo.png' };
	gen = loadGenerator();
} );

/**
 * One tag per line, so a snapshot diff points at the cell that moved.
 *
 * @param {string} html Rendered signature.
 * @return {string} The same markup, broken between tags.
 */
function readable( html ) {
	return html.replace( />\s*</g, '>\n<' );
}

describe( 'signature markup per model', () => {
	it( 'covers every model the configuration offers', () => {
		expect( config.models.map( ( m ) => m.id ) ).toEqual( MODELS );
	} );

	it.each( MODELS )( '%s', ( id ) => {
		const html = gen.render( gen.findModel( config, id ), input, config );
		expect( readable( html ) ).toMatchSnapshot();
	} );
} );
