const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );
const fs = require( 'fs' );
const { sync: readPkgUp } = require( 'read-pkg-up' );

const { path: pkgPath } = readPkgUp();

const srcDir = path.resolve( path.dirname( pkgPath ), 'src' );

const getDynamicEntryPoints = ( basePath = '' ) => {
	const entryPoints = {};

	const filePath = path.resolve( srcDir, basePath );
	const folders = fs.readdirSync( filePath );
	const folderName = path.basename( filePath );

	folders.forEach( ( folder ) => {
		const folderPath = path.resolve( filePath, folder );

		if ( ! fs.lstatSync( folderPath ).isDirectory() ) return;

		// ignore admin folder, it's handled separately
		if ( 'admin' === folder ) return;

		const handle =
			'src' === folderName ? folder : `${ folderName }-${ folder }`;

		// check for js and jsx files
		const jsFiles = fs.readdirSync( folderPath ).filter( ( file ) =>
			/^index.jsx?$/.test( file )
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
		...getDynamicEntryPoints(),
		...getDynamicEntryPoints( 'admin' ),
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
