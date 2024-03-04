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

		// look for index.js, fallback to index.scss
		if ( fs.existsSync( `${ folderPath }/index.js` ) ) {
			entryPoints[ handle ] = `${ folderPath }/index.js`;
		} else if ( fs.existsSync( `${ folderPath }/index.scss` ) ) {
			entryPoints[ handle ] = `${ folderPath }/index.scss`;
		}
	} );

	return entryPoints;
};

module.exports = {
	...defaultConfig,
	entry: {
		...getDynamicEntryPoints(),
		...getDynamicEntryPoints( 'admin' ),
	},
};
