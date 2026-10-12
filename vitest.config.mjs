import { defineConfig } from 'vitest/config';

export default defineConfig( {
	test: {
		environment: 'jsdom',
		include: [ 'tests/js/**/*.test.js' ],
		coverage: {
			provider: 'v8',
			// Every script, so one no test loads counts as uncovered rather
			// than vanishing from the figure. Vendored libraries live in
			// assets/vendor, outside this pattern.
			include: [ 'assets/js/**/*.js' ],
			reporter: [ 'text-summary' ],
			// Ratcheted up in the PR that delivers a gain, never lowered; at
			// most 5 points below the measured figure (85.73% when set).
			thresholds: { lines: 81 },
		},
	},
} );
