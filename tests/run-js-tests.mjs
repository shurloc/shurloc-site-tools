/** Run the JavaScript test files listed in js-tests.json. */

import { spawnSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const config = JSON.parse( readFileSync( new URL( './js-tests.json', import.meta.url ), 'utf8' ) );

if ( !Array.isArray( config.files ) || config.files.length === 0 ||
	!config.files.every( ( file ) => typeof file === 'string' && file.length > 0 ) ) {
	throw new Error( 'tests/js-tests.json must list at least one JavaScript test file.' );
}

const result = spawnSync( process.execPath, [ '--test', ...config.files ], {
	cwd: fileURLToPath( new URL( '../', import.meta.url ) ),
	stdio: 'inherit',
} );

if ( result.error ) {
	throw result.error;
}

process.exitCode = result.status ?? 1;
