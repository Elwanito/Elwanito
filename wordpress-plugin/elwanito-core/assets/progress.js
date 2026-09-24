( function () {
	'use strict';

	var STORAGE_KEY = 'elwanitoResumeCode';

	function getStoredCode() {
		try {
			return window.localStorage.getItem( STORAGE_KEY ) || '';
		} catch ( e ) {
			return '';
		}
	}

	function storeCode( code ) {
		try {
			window.localStorage.setItem( STORAGE_KEY, code );
		} catch ( e ) {
			// Private browsing / storage disabled: progress still works for
			// this page load, it just won't persist across visits.
		}
	}

	function saveProgress( lessonId ) {
		fetch( elwanitoProgress.restUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify( { code: getStoredCode(), lesson_id: lessonId, status: 'complete' } ),
		} )
			.then( function ( res ) { return res.json(); } )
			.then( function ( data ) {
				if ( data && data.code ) {
					storeCode( data.code );
					document.querySelectorAll( '.elwanito-resume-code' ).forEach( function ( el ) {
						el.textContent = data.code;
					} );
				}
			} )
			.catch( function () {
				// Network hiccup: the click still felt responsive to the
				// learner (button below flips immediately); nothing to
				// surface here beyond that.
			} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.elwanito-resume-code' ).forEach( function ( el ) {
			el.textContent = getStoredCode() || 'not generated yet';
		} );

		document.querySelectorAll( '.elwanito-mark-complete' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var lessonId = parseInt( button.getAttribute( 'data-lesson-id' ), 10 );
				if ( lessonId ) {
					saveProgress( lessonId );
					button.textContent = 'Completed ✓';
					button.disabled = true;
				}
			} );
		} );

		document.querySelectorAll( '.elwanito-resume-form' ).forEach( function ( form ) {
			form.addEventListener( 'submit', function ( evt ) {
				evt.preventDefault();
				var input = form.querySelector( '.elwanito-resume-input' );
				var code  = ( input.value || '' ).trim().toUpperCase();
				if ( code ) {
					storeCode( code );
					window.location.reload();
				}
			} );
		} );
	} );
} )();
