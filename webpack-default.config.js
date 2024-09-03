const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );
const fs = require( 'fs' );
const { sync: readPkgUp } = require( 'read-pkg-up' );

const { path: pkgPath } = readPkgUp();

const baseDir = path.dirname( pkgPath )

/**
 * Gets dynamic entry points for the given base path.
 *
 * @param {string} basePath 
 * @param {object} config
 * @param {string|string[]} [config.excludePaths]
 * @returns 
 */
const getDynamicEntryPoints = ( basePath = '', config = {} ) => {
	const entryPoints = {};

	const filePath   = path.resolve( baseDir, basePath );
	const folders    = fs.readdirSync( filePath );
	const folderName = path.basename( filePath );

	const excludePaths = config.excludePaths ? [].concat( config.excludePaths ) : [];

	folders.forEach( ( folder ) => {
		const folderPath = path.resolve( filePath, folder );

		if ( ! fs.lstatSync( folderPath ).isDirectory() ) return;

		if ( excludePaths.includes(folder) ) return;

		const handle =
			'src' === folderName ? folder : `${ folderName }-${ folder }`;

		// check for js/ts and jsx files
		const jsFiles = fs.readdirSync( folderPath ).filter( ( file ) =>
			/^index.[jt]sx?$/.test( file )
		);

		if ( jsFiles.length ) {
			entryPoints[ handle ] = `${ folderPath }/${ jsFiles[ 0 ] }`;
		} else if ( fs.readdirSync( folderPath ).includes( 'index.scss' ) ) { // scss
			entryPoints[ handle ] = `${ folderPath }/index.scss`;
		}
	} );

	return entryPoints;
};


const config = {
	...defaultConfig,
	entry: {
		...getDynamicEntryPoints( 'src', { excludePaths: [ 'admin' ] } ),
		...getDynamicEntryPoints( 'src/admin' ),
	},
	output: {
		filename: '[name].js',
		path: path.resolve( process.cwd(), 'build/src' ),
	},
};

if(defaultConfig.devServer) {
	config.devServer = {
		...defaultConfig.devServer,
		proxy: {
			'/build/src': {
				...defaultConfig.devServer.proxy['/build'],
			}
		}
	};
}

module.exports = config;
