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

	function checkQuiz( container ) {
		var items   = container.querySelectorAll( '.elwanito-quiz-item' );
		var correct = 0;

		items.forEach( function ( item ) {
			var correctIndex = item.getAttribute( 'data-correct' );
			var selected      = item.querySelector( 'input[type="radio"]:checked' );
			var feedback      = item.querySelector( '.elwanito-quiz-feedback' );
			var explanation   = item.querySelector( '.elwanito-quiz-explanation' );

			item.classList.remove( 'elwanito-correct', 'elwanito-incorrect', 'elwanito-unanswered' );

			if ( ! selected ) {
				item.classList.add( 'elwanito-unanswered' );
				feedback.textContent = 'Pick an answer first.';
				feedback.hidden = false;
				return;
			}

			var isCorrect = selected.value === correctIndex;
			item.classList.add( isCorrect ? 'elwanito-correct' : 'elwanito-incorrect' );
			feedback.textContent = isCorrect ? 'Correct!' : 'Not quite.';
			feedback.hidden = false;
			explanation.hidden = false;

			if ( isCorrect ) {
				correct++;
			}
		} );

		var scoreEl = container.querySelector( '.elwanito-quiz-score' );
		if ( scoreEl ) {
			scoreEl.textContent = 'Score: ' + correct + ' / ' + items.length;
			scoreEl.hidden = false;
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.elwanito-resume-code' ).forEach( function ( el ) {
			el.textContent = getStoredCode() || 'not generated yet';
		} );

		document.querySelectorAll( '.elwanito-check-answers' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				checkQuiz( button.closest( '.elwanito-lesson-footer' ) );
			} );
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
