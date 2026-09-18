/**
 * Focused browser-tracker tests using a simulated document and request queue.
 */

import assert from 'node:assert/strict';
import { Blob } from 'node:buffer';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(
	new URL( '../../../../assets/customer/js/shurloc-journey-tracker.js', import.meta.url ),
	'utf8'
);

/**
 * Run one isolated tracker with controlled visibility, time, and transport.
 *
 * @param {object} options Harness options.
 * @return {object} Harness controls and captured requests.
 */
function harness( options = {} ) {
	const config = {
		viewUrl: 'https://example.com/wp-json/shurloc-site-tools/v1/journey/view',
		durationUrl: 'https://example.com/wp-json/shurloc-site-tools/v1/journey/duration',
		nonce: options.nonce || '',
	};
	const requests = [];
	const beacons = [];
	const documentListeners = {};
	const windowListeners = {};
	const intervals = new Map();
	const timeouts = new Map();
	let clock = 0;
	let nextTimer = 1;
	let viewResponses = options.viewResponses || [];
	const document = {
		visibilityState: options.visibility || 'visible',
		referrer: 'https://search.example/path?private=secret',
		addEventListener( name, callback ) {
			documentListeners[ name ] = callback;
		},
	};
	const window = {
		shurlocJourneyBrowser: config,
		location: { pathname: '/shop', search: '?utm_source=email&private=secret' },
		performance: { now: () => clock },
		crypto: options.noCrypto ? null : {
			getRandomValues( bytes ) {
				bytes.fill( 0xab );
				return bytes;
			},
		},
		Blob,
		navigator: {
			sendBeacon( url, body ) {
				beacons.push( { url, body } );
				return options.beaconAccepted !== false;
			},
		},
		setInterval( callback, delay ) {
			const id = nextTimer++;
			intervals.set( id, { callback, delay } );
			return id;
		},
		clearInterval( id ) {
			intervals.delete( id );
		},
		setTimeout( callback, delay ) {
			const id = nextTimer++;
			timeouts.set( id, { callback, delay } );
			return id;
		},
		fetch( url, request ) {
			requests.push( { url, request } );
			if ( url === config.viewUrl ) {
				const response = viewResponses.shift();
				return response || Promise.resolve( {
					ok: true,
					status: 200,
					json: async () => ( { event_id: 42, created: true } ),
				} );
			}
			if ( options.durationThrows ) {
				throw new Error( 'Duration transport unavailable.' );
			}
			return Promise.resolve( { ok: true, status: 200 } );
		},
		addEventListener( name, callback ) {
			windowListeners[ name ] = callback;
		},
	};

	vm.runInNewContext( source, { window, document, URL, Uint8Array, Blob }, {
		filename: 'shurloc-journey-tracker.js',
	} );

	return {
		config,
		requests,
		beacons,
		documentListeners,
		windowListeners,
		intervals,
		timeouts,
		advance( milliseconds ) {
			clock += milliseconds;
		},
		visibility( state ) {
			document.visibilityState = state;
			documentListeners.visibilitychange();
		},
		pagehide() {
			windowListeners.pagehide();
		},
		pageshow() {
			windowListeners.pageshow();
		},
		checkpoint() {
			for ( const timer of intervals.values() ) {
				timer.callback();
			}
		},
		retry() {
			for ( const [ id, timer ] of timeouts ) {
				timeouts.delete( id );
				timer.callback();
			}
		},
	};
}

/** Let async fetch and JSON callbacks settle. */
async function settle() {
	await new Promise( ( resolve ) => setImmediate( resolve ) );
}

/** Find captured duration fetches. */
function durations( tracker ) {
	return tracker.requests.filter( ( item ) => item.url === tracker.config.durationUrl );
}

/** Run the focused behavior checks. */
export async function runTrackerTests() {
	let passed = 0;

	{
		const tracker = harness( { visibility: 'hidden' } );
		assert.equal( tracker.requests.length, 0 );
		tracker.advance( 50000 );
		tracker.visibility( 'visible' );
		await settle();
		assert.equal( tracker.requests.length, 1 );
		const view = JSON.parse( tracker.requests[ 0 ].request.body );
		assert.equal( view.page_uri, '/shop?utm_source=email&private=secret' );
		assert.equal( view.referrer_url, 'https://search.example' );
		assert.match( view.view_token, /^[0-9a-f]{32}$/ );
		tracker.advance( 360000 );
		tracker.checkpoint();
		await settle();
		assert.equal( JSON.parse( durations( tracker )[ 0 ].request.body ).total_active_ms, 360000 );
		assert.deepEqual( Object.keys( tracker.documentListeners ), [ 'visibilitychange' ] );
		passed++;
	}

	{
		const tracker = harness();
		await settle();
		tracker.advance( 1000 );
		tracker.visibility( 'hidden' );
		tracker.visibility( 'hidden' );
		tracker.pagehide();
		assert.equal( tracker.beacons.length, 1 );
		assert.equal( tracker.beacons[ 0 ].body.type, 'application/json' );
		assert.equal( JSON.parse( await tracker.beacons[ 0 ].body.text() ).total_active_ms, 1000 );
		tracker.advance( 10000 );
		tracker.visibility( 'visible' );
		tracker.pageshow();
		await settle();
		assert.equal( JSON.parse( durations( tracker )[ 0 ].request.body ).total_active_ms, 1000 );
		tracker.advance( 500 );
		tracker.visibility( 'hidden' );
		assert.equal( JSON.parse( await tracker.beacons[ 1 ].body.text() ).total_active_ms, 1500 );
		assert.equal( tracker.requests.filter( ( item ) => item.url === tracker.config.viewUrl ).length, 1 );
		passed++;
	}

	{
		const tracker = harness( { nonce: 'test-rest-nonce' } );
		await settle();
		assert.equal( tracker.requests[ 0 ].request.headers['X-WP-Nonce'], 'test-rest-nonce' );
		tracker.advance( 750 );
		tracker.visibility( 'hidden' );
		await settle();
		assert.equal( tracker.beacons.length, 0 );
		assert.equal( durations( tracker )[ 0 ].request.keepalive, true );
		assert.equal( durations( tracker )[ 0 ].request.headers['X-WP-Nonce'], 'test-rest-nonce' );
		assert.equal( JSON.parse( durations( tracker )[ 0 ].request.body ).total_active_ms, 750 );
		passed++;
	}

	{
		const tracker = harness( { beaconAccepted: false } );
		await settle();
		tracker.advance( 800 );
		tracker.pagehide();
		await settle();
		assert.equal( tracker.beacons.length, 1 );
		assert.equal( durations( tracker ).length, 1 );
		assert.equal( durations( tracker )[ 0 ].request.keepalive, true );
		assert.equal( durations( tracker )[ 0 ].request.headers['Content-Type'], 'application/json' );
		passed++;
	}

	{
		const tracker = harness( {
			viewResponses: [
				Promise.resolve( { ok: false, status: 503 } ),
				Promise.resolve( { ok: true, status: 200, json: async () => ( { event_id: 42 } ) } ),
			],
		} );
		await settle();
		assert.equal( tracker.timeouts.size, 1 );
		tracker.retry();
		await settle();
		const views = tracker.requests.filter( ( item ) => item.url === tracker.config.viewUrl );
		assert.equal( views.length, 2 );
		assert.equal( JSON.parse( views[ 0 ].request.body ).view_token, JSON.parse( views[ 1 ].request.body ).view_token );
		tracker.advance( 600 );
		tracker.visibility( 'hidden' );
		assert.equal( tracker.beacons.length, 1 );
		passed++;
	}

	{
		let releaseView;
		const pendingView = new Promise( ( resolve ) => { releaseView = resolve; } );
		const tracker = harness( { viewResponses: [ pendingView ] } );
		tracker.advance( 900 );
		tracker.pagehide();
		assert.equal( tracker.beacons.length, 0 );
		releaseView( { ok: true, status: 200, json: async () => ( { event_id: 42 } ) } );
		await settle();
		assert.equal( JSON.parse( await tracker.beacons[ 0 ].body.text() ).total_active_ms, 900 );
		passed++;
	}

	{
		const tracker = harness( { noCrypto: true } );
		assert.equal( tracker.requests.length, 0 );
		assert.deepEqual( tracker.documentListeners, {} );
		passed++;
	}

	{
		const tracker = harness( { nonce: 'test-rest-nonce', durationThrows: true } );
		await settle();
		tracker.advance( 500 );
		assert.doesNotThrow( () => tracker.visibility( 'hidden' ) );
		tracker.visibility( 'visible' );
		tracker.advance( 500 );
		assert.doesNotThrow( () => tracker.visibility( 'hidden' ) );
		assert.equal( durations( tracker ).length, 2 );
		passed++;
	}

	return `${ passed } tracker behavior tests passed`;
}

test( 'journey tracker browser behavior', async () => {
	assert.equal( await runTrackerTests(), '8 tracker behavior tests passed' );
} );
