/**
 * Retake the screenshots in docs/screenshots from a WordPress site running
 * the plugin, with English as its language:
 *
 *   ESG_URL=http://localhost:8080 ESG_USER=admin ESG_PASS=admin \
 *     ESG_PAGE=/signature/ node bin/screenshots.mjs
 *
 * It needs Playwright (`npx playwright`), which is not a dependency of the
 * project. ESG_PAGE is a page holding [email_signature]. The form is filled with
 * fictitious details; give the site the email domains and the "Remember
 * what was typed" switch the README shows before running it.
 */
import { chromium } from 'playwright';

const base = process.env.ESG_URL || 'http://localhost:8080';
const out = new URL( '../docs/screenshots/', import.meta.url ).pathname;
const browser = await chromium.launch();
const page = await ( await browser.newContext( { viewport: { width: 1280, height: 900 } } ) ).newPage();

await page.goto( base + ( process.env.ESG_PAGE || '/signature/' ) );
await page.selectOption( '.esg-model', 'staff_school' );
const values = { name: 'Maria da Silva', role: 'Teacher', school: 'EMEF Example School', email: 'maria.silva', phone: '1133971234', address: 'Rua Exemplo, 100', postal: '05500-000' };
for ( const [ key, value ] of Object.entries( values ) ) {
	const field = page.locator( `[name="${ key }"]` );
	if ( await field.count() && await field.isEditable() ) {
		await field.fill( value );
	}
}
await page.click( '.esg-generate' );
await page.locator( '.esg-generator' ).screenshot( { path: out + '1-form.png' } );
await page.check( '.esg-dark-toggle' );
await page.locator( '.esg-preview' ).screenshot( { path: out + '2-preview-dark.png' } );

await page.goto( base + '/wp-login.php' );
await page.fill( '#user_login', process.env.ESG_USER || 'admin' );
await page.fill( '#user_pass', process.env.ESG_PASS || 'admin' );
await Promise.all( [ page.waitForNavigation(), page.click( '#wp-submit' ) ] );
const tabs = [ [ 'general', '3-general' ], [ 'layout', '4-layout' ], [ 'download', '5-download' ], [ 'regions', '6-units' ], [ 'fields', '7-fields' ] ];
for ( const [ tab, name ] of tabs ) {
	await page.goto( base + '/wp-admin/options-general.php?page=email-signature-generator&tab=' + tab );
	await page.locator( '.wrap' ).screenshot( { path: out + name + '.png' } );
}
await browser.close();
