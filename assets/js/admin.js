/**
 * WDOD Site Toolkit - admin behaviour (vanilla JS, no dependencies).
 *
 * - Debug log auto-refresh through admin-ajax (nonce + capability checked server-side).
 * - "Copy report" button on the Environment tab.
 * - Confirmation on destructive links.
 */
( function () {
	'use strict';

	var config = window.wdodSiteToolkit || {};
	var i18n = config.i18n || {};

	/**
	 * Small helper to set feedback text with an optional error state.
	 *
	 * @param {HTMLElement|null} el      Feedback element.
	 * @param {string}           message Message.
	 * @param {boolean}          isError Whether it is an error.
	 */
	function feedback( el, message, isError ) {
		if ( ! el ) {
			return;
		}

		el.textContent = message;
		el.classList.toggle( 'wdod-toolkit__feedback--error', !! isError );

		if ( message ) {
			window.clearTimeout( el._wdodTimer );
			el._wdodTimer = window.setTimeout( function () {
				el.textContent = '';
				el.classList.remove( 'wdod-toolkit__feedback--error' );
			}, 4000 );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Copy report                                                          */
	/* ------------------------------------------------------------------ */

	function initCopyReport() {
		var button = document.getElementById( 'wdod-toolkit-copy-report' );

		if ( ! button ) {
			return;
		}

		var target = document.getElementById( button.getAttribute( 'data-target' ) );
		var status = document.getElementById( 'wdod-toolkit-copy-feedback' );

		if ( ! target ) {
			return;
		}

		button.addEventListener( 'click', function () {
			var text = target.value;

			if ( navigator.clipboard && window.isSecureContext ) {
				navigator.clipboard.writeText( text ).then(
					function () {
						feedback( status, i18n.copied || 'Copied!' );
					},
					function () {
						fallbackCopy( target, status );
					}
				);
			} else {
				fallbackCopy( target, status );
			}
		} );
	}

	/**
	 * execCommand fallback for non-secure contexts (plain http local sites).
	 *
	 * @param {HTMLTextAreaElement} target Textarea.
	 * @param {HTMLElement|null}    status Feedback element.
	 */
	function fallbackCopy( target, status ) {
		try {
			target.focus();
			target.select();

			var ok = document.execCommand( 'copy' );

			feedback( status, ok ? ( i18n.copied || 'Copied!' ) : ( i18n.copyFailed || 'Copy failed' ), ! ok );
		} catch ( e ) {
			feedback( status, i18n.copyFailed || 'Copy failed', true );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Debug log auto-refresh                                               */
	/* ------------------------------------------------------------------ */

	function initLogRefresh() {
		var pre = document.getElementById( 'wdod-toolkit-log' );
		var toggle = document.getElementById( 'wdod-toolkit-log-autorefresh' );

		if ( ! pre || ! toggle || ! config.ajaxUrl ) {
			return;
		}

		var status = document.getElementById( 'wdod-toolkit-log-status' );
		var sizeEl = document.getElementById( 'wdod-toolkit-log-size' );
		var truncatedEl = document.getElementById( 'wdod-toolkit-log-truncated' );
		var interval = parseInt( config.refreshInterval, 10 ) || 10000;
		var timer = null;
		var lastSize = null;

		function isScrolledToBottom() {
			return pre.scrollHeight - pre.scrollTop - pre.clientHeight < 24;
		}

		function render( data ) {
			var stickToBottom = isScrolledToBottom();

			if ( ! data.exists ) {
				pre.textContent = i18n.missing || 'No debug.log file exists yet.';
			} else if ( ! data.content || ! data.content.trim() ) {
				pre.textContent = i18n.empty || 'The log file is empty.';
			} else if ( data.size !== lastSize ) {
				pre.textContent = data.content;
			}

			lastSize = data.size;

			if ( sizeEl && data.sizeHuman ) {
				sizeEl.textContent = data.sizeHuman;
			}

			if ( truncatedEl ) {
				truncatedEl.hidden = ! data.truncated;
			}

			if ( stickToBottom ) {
				pre.scrollTop = pre.scrollHeight;
			}

			feedback( status, ( i18n.updated || 'Updated' ) + ' ' + ( data.time || '' ) );
		}

		function refresh() {
			var body = new window.FormData();

			body.append( 'action', 'wdod_site_toolkit_log_tail' );
			body.append( 'nonce', config.nonce );

			window.fetch( config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: body
			} )
				.then( function ( response ) {
					if ( ! response.ok ) {
						throw new Error( response.statusText );
					}

					return response.json();
				} )
				.then( function ( json ) {
					if ( ! json || ! json.success ) {
						throw new Error( 'bad response' );
					}

					render( json.data );
				} )
				.catch( function () {
					feedback( status, i18n.error || 'Could not refresh the log.', true );
				} );
		}

		function start() {
			stop();
			timer = window.setInterval( refresh, interval );
		}

		function stop() {
			if ( timer ) {
				window.clearInterval( timer );
				timer = null;
			}
		}

		toggle.addEventListener( 'change', function () {
			if ( toggle.checked ) {
				refresh();
				start();
			} else {
				stop();
			}
		} );

		document.addEventListener( 'visibilitychange', function () {
			if ( document.hidden ) {
				stop();
			} else if ( toggle.checked ) {
				refresh();
				start();
			}
		} );

		// Start at the end of the log, like `tail -f`.
		pre.scrollTop = pre.scrollHeight;

		if ( toggle.checked ) {
			start();
		}
	}

	/* ------------------------------------------------------------------ */
	/* Confirmations                                                        */
	/* ------------------------------------------------------------------ */

	function initConfirmations() {
		var links = document.querySelectorAll( '[data-confirm]' );

		Array.prototype.forEach.call( links, function ( link ) {
			link.addEventListener( 'click', function ( event ) {
				if ( link.classList.contains( 'disabled' ) ) {
					event.preventDefault();
					return;
				}

				if ( ! window.confirm( link.getAttribute( 'data-confirm' ) ) ) {
					event.preventDefault();
				}
			} );
		} );

		Array.prototype.forEach.call( document.querySelectorAll( 'a.disabled' ), function ( link ) {
			link.addEventListener( 'click', function ( event ) {
				event.preventDefault();
			} );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	function init() {
		initCopyReport();
		initLogRefresh();
		initConfirmations();
	}
}() );
