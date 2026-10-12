import { execFileSync } from 'node:child_process';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

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

/**
 * Run a browser script in the test's window, under its real file name, so
 * V8 attributes its coverage to it (new Function() would leave it at 0%).
 *
 * @param {string} file Path from the repository root.
 */
export function runScript( file ) {
	const full = path.join( root, file );
	vm.runInThisContext( readFileSync( full, 'utf8' ), { filename: full } );
}

export { root };
