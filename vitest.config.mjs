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
			// The `.min.js` build outputs are the same code, minified.
			exclude: [ 'assets/js/**/*.min.js' ],
			reporter: [ 'text-summary' ],
			// The highest of 90%, the measured figure minus 5 points, and the
			// current value; never lowered (CLAUDE.md). Raised 81 -> 90 in #67,
			// measured 94.1% when set.
			thresholds: { lines: 90 },
		},
	},
} );
