const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		'assignment-editor': path.resolve(
			process.cwd(),
			'src',
			'assignment-editor',
			'index.js'
		),
		'grading-interface': path.resolve(
			process.cwd(),
			'src',
			'grading-interface',
			'index.js'
		),
		dashboard: path.resolve(
			process.cwd(),
			'src',
			'dashboard',
			'index.js'
		),
		reports: path.resolve( process.cwd(), 'src', 'reports', 'index.js' ),
		'settings-panel': path.resolve(
			process.cwd(),
			'src',
			'settings-panel',
			'index.js'
		),
		onboarding: path.resolve(
			process.cwd(),
			'src',
			'onboarding',
			'index.js'
		),
		'blocks/assignment/index': path.resolve(
			process.cwd(),
			'blocks',
			'assignment',
			'index.js'
		),
		'blocks/my-submissions/index': path.resolve(
			process.cwd(),
			'blocks',
			'my-submissions',
			'index.js'
		),
	},
	output: {
		path: path.resolve( process.cwd(), 'build' ),
		filename: ( pathData ) => {
			// Output blocks to their own directories
			if ( pathData.chunk.name.startsWith( 'blocks/' ) ) {
				return '[name].js';
			}
			return '[name].js';
		},
	},
};
