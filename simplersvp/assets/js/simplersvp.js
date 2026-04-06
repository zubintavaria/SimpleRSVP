/* SimpleRSVP — frontend logic
 *
 * Responsibilities:
 *  1. Generate / retrieve a persistent device UUID from localStorage.
 *  2. On page load, fetch existing response & counts from the server.
 *  3. Handle button clicks → submit RSVP via AJAX.
 *  4. Poll for count updates every 10 s so the page reflects other visitors.
 *  5. "Change my response" flow: re-show buttons without a page reload.
 *
 * No jQuery dependency.  SimpleRSVP (ajax_url + nonce) is injected by
 * wp_localize_script() in simplersvp.php.
 */
( function () {
	'use strict';

	// ── UUID v4 generator ──────────────────────────────────────────────────

	function generateUUID() {
		if ( window.crypto && crypto.randomUUID ) {
			return crypto.randomUUID();
		}
		// Fallback for older browsers.
		return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace( /[xy]/g, function ( c ) {
			var r = ( Math.random() * 16 ) | 0;
			var v = c === 'x' ? r : ( r & 0x3 ) | 0x8;
			return v.toString( 16 );
		} );
	}

	// ── Persistent device identity ─────────────────────────────────────────

	var DEVICE_KEY = 'simplersvp_device_id';
	var NAME_KEY   = 'simplersvp_name';

	function getDeviceId() {
		var id = localStorage.getItem( DEVICE_KEY );
		if ( ! id ) {
			id = generateUUID();
			localStorage.setItem( DEVICE_KEY, id );
		}
		return id;
	}

	function getSavedName() {
		return localStorage.getItem( NAME_KEY ) || '';
	}

	function persistName( name ) {
		if ( name ) {
			localStorage.setItem( NAME_KEY, name );
		}
	}

	// ── Per-widget initialisation ──────────────────────────────────────────

	function initWidget( widget ) {
		var postId   = widget.dataset.postId;
		var deviceId = getDeviceId();

		var labels = {
			yes:   widget.dataset.yes,
			no:    widget.dataset.no,
			maybe: widget.dataset.maybe,
		};

		// DOM refs
		var nameInput    = widget.querySelector( '.simplersvp-name-input' );
		var buttonsDiv   = widget.querySelector( '.simplersvp-buttons' );
		var nameRow      = widget.querySelector( '.simplersvp-name-row' );
		var buttons      = widget.querySelectorAll( '.simplersvp-btn' );
		var submittedDiv = widget.querySelector( '.simplersvp-submitted' );
		var respLabel    = widget.querySelector( '.simplersvp-response-label' );
		var changeBtn    = widget.querySelector( '.simplersvp-change-btn' );

		// Pre-fill name from localStorage.
		if ( nameInput ) {
			nameInput.value = getSavedName();
		}

		// ── UI state helpers ───────────────────────────────────────────────

		function updateCounts( counts, animateKey ) {
			widget.querySelectorAll( '.simplersvp-count-num' ).forEach( function ( el ) {
				var key      = el.dataset.key;
				var newCount = ( counts[ key ] !== undefined ) ? counts[ key ] : 0;
				if ( el.textContent !== String( newCount ) ) {
					el.textContent = newCount;
				}
				// Highlight the count item that the user just voted for.
				var item = el.closest( '.simplersvp-count-item' );
				if ( item ) {
					item.classList.toggle( 'simplersvp-count-active', key === animateKey );
				}
			} );
		}

		function showSubmitted( response ) {
			buttonsDiv.hidden = true;
			nameRow.hidden    = true;
			submittedDiv.hidden = false;
			if ( respLabel ) {
				respLabel.textContent = labels[ response ] || response;
			}
		}

		function showButtons( clearSelection ) {
			buttonsDiv.hidden   = false;
			nameRow.hidden      = false;
			submittedDiv.hidden = true;
			if ( clearSelection ) {
				buttons.forEach( function ( b ) {
					b.classList.remove( 'simplersvp-selected' );
				} );
			}
		}

		function setButtonsDisabled( disabled ) {
			buttons.forEach( function ( b ) {
				b.disabled = disabled;
				b.classList.toggle( 'simplersvp-loading', disabled );
			} );
		}

		// ── Network helpers ────────────────────────────────────────────────

		function buildGetUrl() {
			return (
				SimpleRSVP.ajax_url +
				'?action=simplersvp_get_counts' +
				'&nonce=' + encodeURIComponent( SimpleRSVP.nonce ) +
				'&post_id=' + encodeURIComponent( postId ) +
				'&device_id=' + encodeURIComponent( deviceId )
			);
		}

		function fetchState( onComplete ) {
			fetch( buildGetUrl() )
				.then( function ( r ) { return r.json(); } )
				.then( function ( data ) {
					if ( ! data.success ) { return; }
					var d = data.data;
					updateCounts( d.counts, d.response || null );
					if ( d.response ) {
						// Restore name from server if we don't have it locally.
						if ( d.name && nameInput && ! getSavedName() ) {
							nameInput.value = d.name;
							persistName( d.name );
						}
						showSubmitted( d.response );
					}
					if ( onComplete ) { onComplete(); }
				} )
				.catch( function () {
					if ( onComplete ) { onComplete(); }
				} );
		}

		function submitRsvp( response ) {
			var name = nameInput ? nameInput.value.trim() : '';
			persistName( name );

			setButtonsDisabled( true );

			var body = new URLSearchParams( {
				action:    'simplersvp_submit',
				nonce:     SimpleRSVP.nonce,
				post_id:   postId,
				device_id: deviceId,
				name:      name,
				response:  response,
			} );

			fetch( SimpleRSVP.ajax_url, {
				method:  'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body:    body.toString(),
			} )
				.then( function ( r ) { return r.json(); } )
				.then( function ( data ) {
					setButtonsDisabled( false );
					if ( ! data.success ) {
						// On error keep buttons visible so user can retry.
						return;
					}
					updateCounts( data.data.counts, response );
					showSubmitted( response );
				} )
				.catch( function () {
					setButtonsDisabled( false );
				} );
		}

		// ── Event listeners ────────────────────────────────────────────────

		buttons.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				// Highlight the clicked button immediately for responsiveness.
				buttons.forEach( function ( b ) {
					b.classList.remove( 'simplersvp-selected' );
				} );
				btn.classList.add( 'simplersvp-selected' );
				submitRsvp( btn.dataset.value );
			} );
		} );

		changeBtn.addEventListener( 'click', function () {
			showButtons( true );
		} );

		// ── Boot ───────────────────────────────────────────────────────────

		// Load initial state immediately.
		fetchState( null );

		// Poll for count updates every 10 s (passive — updates counts only,
		// never overwrites an in-progress user interaction).
		setInterval( function () {
			// If buttons are visible, the user may be mid-selection; only
			// update counts silently without changing UI state.
			fetch( buildGetUrl() )
				.then( function ( r ) { return r.json(); } )
				.then( function ( data ) {
					if ( data.success ) {
						// Preserve the current active highlight if submitted.
						var activeKey = null;
						var activeNum = widget.querySelector( '.simplersvp-count-item.simplersvp-count-active .simplersvp-count-num' );
						if ( activeNum ) { activeKey = activeNum.dataset.key; }
						updateCounts( data.data.counts, activeKey );
					}
				} )
				.catch( function () {} );
		}, 10000 );
	}

	// ── Bootstrap all widgets on the page ─────────────────────────────────

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.simplersvp-widget' ).forEach( initWidget );
	} );
} )();
