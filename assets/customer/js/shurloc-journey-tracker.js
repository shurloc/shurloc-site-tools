/**
 * Customer Journey browser view and visible-duration tracking.
 *
 * A page view starts only when the document is visible. Duration is an
 * estimate based on visibility, with no mouse or keyboard inactivity cutoff.
 *
 * @package ShurlocSiteTools
 */

( function () {
	'use strict';

	const config = window.shurlocJourneyBrowser;
	if (
		! config ||
		typeof config.viewUrl !== 'string' ||
		config.viewUrl === '' ||
		typeof config.durationUrl !== 'string' ||
		config.durationUrl === '' ||
		typeof config.nonce !== 'string' ||
		typeof window.fetch !== 'function' ||
		! window.crypto ||
		typeof window.crypto.getRandomValues !== 'function' ||
		! window.performance ||
		typeof window.performance.now !== 'function' ||
		! [ 'visible', 'hidden' ].includes( document.visibilityState )
	) {
		return;
	}

	const MAX_DURATION_MS = 2147483647;
	const CHECKPOINT_MS = 120000;
	const RETRY_MS = 2000;
	const tokenBytes = new Uint8Array( 16 );

	try {
		window.crypto.getRandomValues( tokenBytes );
	} catch ( error ) {
		return;
	}

	const viewToken = Array.from( tokenBytes, ( byte ) =>
		byte.toString( 16 ).padStart( 2, '0' )
	).join( '' );
	const viewPayload = {
		page_uri: window.location.pathname + window.location.search,
		view_token: viewToken,
	};

	if ( document.referrer ) {
		try {
			const referrer = new URL( document.referrer );
			if ( [ 'http:', 'https:' ].includes( referrer.protocol ) ) {
				viewPayload.referrer_url = referrer.origin;
			}
		} catch ( error ) {
			// An invalid referrer is omitted from the request.
		}
	}

	const headers = { 'Content-Type': 'application/json' };
	if ( config.nonce ) {
		headers['X-WP-Nonce'] = config.nonce;
	}

	let eventId = null;
	let viewAttempts = 0;
	let viewPending = false;
	let viewStopped = false;
	let retryTimer = null;
	let visibleSince = null;
	let accumulatedMs = 0;
	let lastRequestedMs = 0;
	let confirmedMs = 0;
	let beaconUnconfirmed = false;
	let checkpointTimer = null;
	let pageSuspended = false;

	/**
	 * Return the bounded cumulative visible time for this document.
	 *
	 * @return {number} Visible milliseconds.
	 */
	function visibleTotal() {
		const runningMs = visibleSince === null
			? 0
			: Math.max( 0, window.performance.now() - visibleSince );
		return Math.min( MAX_DURATION_MS, Math.floor( accumulatedMs + runningMs ) );
	}

	/**
	 * Try one view request using the same token for a bounded retry.
	 *
	 * @return {Promise<void>} Request completion.
	 */
	async function requestView() {
		if ( viewPending || viewStopped || eventId !== null || viewAttempts >= 2 ) {
			return;
		}

		viewPending = true;
		viewAttempts++;

		try {
			const response = await window.fetch( config.viewUrl, {
				method: 'POST',
				headers: headers,
				credentials: 'same-origin',
				keepalive: true,
				body: JSON.stringify( viewPayload ),
			} );

			if ( ! response.ok ) {
				if ( response.status >= 500 ) {
					scheduleViewRetry();
				} else {
					viewStopped = true;
				}
				return;
			}

			const result = await response.json();
			if ( ! Number.isSafeInteger( result.event_id ) || result.event_id <= 0 ) {
				viewStopped = true;
				return;
			}

			eventId = result.event_id;
			if ( document.visibilityState !== 'visible' || pageSuspended ) {
				flushDuration( true );
			}
		} catch ( error ) {
			scheduleViewRetry();
		} finally {
			viewPending = false;
		}
	}

	/**
	 * Retry a transient view failure once, preserving its idempotency token.
	 *
	 * @return {void}
	 */
	function scheduleViewRetry() {
		if ( viewAttempts >= 2 || retryTimer !== null || pageSuspended ) {
			return;
		}

		retryTimer = window.setTimeout( () => {
			retryTimer = null;
			requestView();
		}, RETRY_MS );
	}

	/**
	 * Send a cumulative duration. A repeated request never adds time twice.
	 *
	 * @param {boolean} final Whether the page is becoming hidden or unloading.
	 * @return {void}
	 */
	function flushDuration( final ) {
		const totalMs = visibleTotal();
		if ( eventId === null || totalMs <= 0 || totalMs <= lastRequestedMs ) {
			return;
		}

		lastRequestedMs = totalMs;
		const body = JSON.stringify( {
			event_id: eventId,
			total_active_ms: totalMs,
		} );

		if (
			final &&
			! config.nonce &&
			window.navigator &&
			typeof window.navigator.sendBeacon === 'function' &&
			typeof window.Blob === 'function'
		) {
			try {
				const blob = new window.Blob( [ body ], { type: 'application/json' } );
				if ( window.navigator.sendBeacon( config.durationUrl, blob ) ) {
					beaconUnconfirmed = true;
					return;
				}
			} catch ( error ) {
				// Fall back to a keepalive request.
			}
		}

		let request;
		try {
			request = window.fetch( config.durationUrl, {
				method: 'POST',
				headers: headers,
				credentials: 'same-origin',
				keepalive: final,
				body: body,
			} );
		} catch ( error ) {
			lastRequestedMs = confirmedMs;
			return;
		}

		request.then( ( response ) => {
			if ( response.ok ) {
				confirmedMs = Math.max( confirmedMs, totalMs );
			} else if ( lastRequestedMs === totalMs ) {
				lastRequestedMs = confirmedMs;
			}
		} ).catch( () => {
			if ( lastRequestedMs === totalMs ) {
				lastRequestedMs = confirmedMs;
			}
		} );
	}

	/**
	 * Begin or resume visible-time measurement.
	 *
	 * @return {void}
	 */
	function startVisible() {
		if ( pageSuspended || document.visibilityState !== 'visible' || visibleSince !== null ) {
			return;
		}

		visibleSince = window.performance.now();
		checkpointTimer = window.setInterval( () => flushDuration( false ), CHECKPOINT_MS );

		if ( beaconUnconfirmed ) {
			beaconUnconfirmed = false;
			lastRequestedMs = confirmedMs;
			flushDuration( false );
		}

		if ( viewAttempts === 0 || ( ! viewPending && retryTimer === null ) ) {
			requestView();
		}
	}

	/**
	 * Stop at the visibility boundary without counting hidden time.
	 *
	 * @return {void}
	 */
	function stopVisible() {
		if ( visibleSince !== null ) {
			accumulatedMs = visibleTotal();
			visibleSince = null;
		}

		if ( checkpointTimer !== null ) {
			window.clearInterval( checkpointTimer );
			checkpointTimer = null;
		}
	}

	document.addEventListener( 'visibilitychange', () => {
		if ( document.visibilityState === 'visible' ) {
			startVisible();
		} else {
			stopVisible();
			flushDuration( true );
		}
	} );

	window.addEventListener( 'pagehide', () => {
		stopVisible();
		pageSuspended = true;
		flushDuration( true );
	} );

	window.addEventListener( 'pageshow', () => {
		pageSuspended = false;
		startVisible();
	} );

	startVisible();
} )();
