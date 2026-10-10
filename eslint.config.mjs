// ESLint flat config: catches real bugs (undefined names, duplicates,
// unreachable code, loose equality) and leaves style alone.
import globals from 'globals';

export default [
	{
		ignores: ['node_modules/**', 'vendor/**', 'build/**', '**/*.min.js', 'assets/vendor/**'],
	},
	{
		files: ['assets/js/**/*.js'],
		languageOptions: {
			ecmaVersion: 2022,
			sourceType: 'script',
			globals: {
				...globals.browser,
			},
		},
		rules: {
			'no-undef': 'error',
			'no-dupe-keys': 'error',
			'no-duplicate-case': 'error',
			'no-unreachable': 'error',
			'no-redeclare': 'error',
			eqeqeq: ['error', 'smart'],
			'no-unused-vars': ['warn', { args: 'none' }],
		},
	},
];
