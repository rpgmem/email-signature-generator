import { execFileSync } from 'node:child_process';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const root = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '../..' );
const require = createRequire( import.meta.url );

/**
 * The real configuration, printed by the same PHP the plugin and the
 * standalone build use, so the tests never test a copy that drifted.
 *
 * @param {string} locale Catalogue to translate with.
 * @return {Object} The configuration.
 */
export function loadConfig( locale = 'en_US' ) {
	const out = execFileSync( 'php', [ path.join( root, 'bin/build-standalone.php' ), '0.0.0', '-', locale ] );
	return JSON.parse( out.toString() );
}

/**
 * The generator module, loaded through its CommonJS export.
 *
 * @return {Object} The public API.
 */
export function loadGenerator() {
	return require( path.join( root, 'assets/js/signature-generator.js' ) );
}

export { root };
