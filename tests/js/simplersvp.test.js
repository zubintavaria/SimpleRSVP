/**
 * Tests for simplersvp.js
 *
 * Strategy:
 *   1. Build the widget DOM from the same HTML structure as rsvp-widget.php.
 *   2. Load and eval the script so its DOMContentLoaded listener registers.
 *   3. Dispatch DOMContentLoaded to initialise the widget.
 *   4. Interact with the DOM and assert on state changes.
 *
 * `fetch` is mocked globally so no real HTTP calls are made.
 */

const fs   = require( 'fs' );
const path = require( 'path' );

/** Drain all pending microtasks (handles multi-level .then() chains). */
async function flushPromises() {
    for ( let i = 0; i < 10; i++ ) {
        await Promise.resolve();
    }
}

// ── Helpers ────────────────────────────────────────────────────────────────

const SCRIPT_PATH = path.join( __dirname, '../../simplersvp/assets/js/simplersvp.js' );
const scriptSrc   = fs.readFileSync( SCRIPT_PATH, 'utf8' );

/**
 * Build a minimal widget HTML string matching the PHP template's structure.
 */
function makeWidgetHtml( {
    postId    = '42',
    yes       = 'Yes',
    no        = 'No',
    maybe     = 'Maybe',
    showMaybe = 'true',
} = {} ) {
    return `
    <div class="simplersvp-widget"
         data-post-id="${ postId }"
         data-yes="${ yes }"
         data-no="${ no }"
         data-maybe="${ maybe }"
         data-show-maybe="${ showMaybe }">
      <div class="simplersvp-card">
        <p class="simplersvp-question">Will you attend?</p>
        <div class="simplersvp-name-row">
          <input type="text" class="simplersvp-name-input" />
        </div>
        <div class="simplersvp-buttons">
          <button class="simplersvp-btn simplersvp-btn-yes" data-value="yes" type="button">Yes</button>
          <button class="simplersvp-btn simplersvp-btn-no"  data-value="no"  type="button">No</button>
          ${ showMaybe === 'true' ? '<button class="simplersvp-btn simplersvp-btn-maybe" data-value="maybe" type="button">Maybe</button>' : '' }
        </div>
        <div class="simplersvp-submitted" hidden>
          <p class="simplersvp-current-response">
            Your response: <strong class="simplersvp-response-label"></strong>
          </p>
          <button class="simplersvp-change-btn" type="button">Change my response</button>
        </div>
        <div class="simplersvp-counts">
          <div class="simplersvp-count-item simplersvp-count-yes">
            <span class="simplersvp-count-num" data-key="yes">0</span>
            <span class="simplersvp-count-label">Yes</span>
          </div>
          <div class="simplersvp-count-item simplersvp-count-no">
            <span class="simplersvp-count-num" data-key="no">0</span>
            <span class="simplersvp-count-label">No</span>
          </div>
          ${ showMaybe === 'true' ? `
          <div class="simplersvp-count-item simplersvp-count-maybe">
            <span class="simplersvp-count-num" data-key="maybe">0</span>
            <span class="simplersvp-count-label">Maybe</span>
          </div>` : '' }
        </div>
      </div>
    </div>`;
}

/**
 * Build a fetch mock that returns the given data payload as JSON.
 */
function makeFetchMock( data ) {
    return jest.fn().mockResolvedValue( {
        json: () => Promise.resolve( data ),
    } );
}

const EMPTY_STATE = {
    success: true,
    data: {
        counts:   { yes: 0, no: 0, maybe: 0 },
        response: '',
        name:     '',
    },
};

/**
 * Inject the widget into the document, load the script, fire DOMContentLoaded.
 */
async function initWidget( widgetHtml = makeWidgetHtml(), fetchResponse = EMPTY_STATE ) {
    document.body.innerHTML = widgetHtml;
    global.fetch            = makeFetchMock( fetchResponse );

    // Eval re-executes the IIFE and registers a fresh DOMContentLoaded listener.
    // eslint-disable-next-line no-eval
    eval( scriptSrc );
    document.dispatchEvent( new Event( 'DOMContentLoaded' ) );

    // Drain all microtasks so fetch().then().then() chains complete.
    await flushPromises();

    return document.querySelector( '.simplersvp-widget' );
}

// ── localStorage ─────────────────────────────────────────────────────────────

describe( 'device identity', () => {
    beforeEach( () => localStorage.clear() );

    test( 'generates a UUID v4 and stores it in localStorage on first load', async () => {
        await initWidget();
        const stored = localStorage.getItem( 'simplersvp_device_id' );
        expect( stored ).toMatch(
            /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i
        );
    } );

    test( 'reuses the same UUID across re-loads', async () => {
        await initWidget();
        const first = localStorage.getItem( 'simplersvp_device_id' );
        await initWidget();
        const second = localStorage.getItem( 'simplersvp_device_id' );
        expect( first ).toBe( second );
    } );

    test( 'sends device_id in the GET request to fetch counts', async () => {
        localStorage.setItem( 'simplersvp_device_id', 'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa' );
        await initWidget();
        const calledUrl = global.fetch.mock.calls[0][0];
        expect( calledUrl ).toContain( 'device_id=aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa' );
    } );
} );

// ── Name persistence ──────────────────────────────────────────────────────────

describe( 'name persistence', () => {
    beforeEach( () => localStorage.clear() );

    test( 'pre-fills name input from localStorage', async () => {
        localStorage.setItem( 'simplersvp_name', 'Alice' );
        const widget = await initWidget();
        expect( widget.querySelector( '.simplersvp-name-input' ).value ).toBe( 'Alice' );
    } );

    test( 'pre-fills name from server response when missing locally', async () => {
        const serverState = {
            success: true,
            data: {
                counts:   { yes: 1, no: 0, maybe: 0 },
                response: 'yes',
                name:     'Bob',
            },
        };
        const widget = await initWidget( makeWidgetHtml(), serverState );
        expect( widget.querySelector( '.simplersvp-name-input' ).value ).toBe( 'Bob' );
    } );
} );

// ── Initial render state ─────────────────────────────────────────────────────

describe( 'initial render', () => {
    beforeEach( () => localStorage.clear() );

    test( 'buttons are visible before any response is recorded', async () => {
        const widget = await initWidget();
        expect( widget.querySelector( '.simplersvp-buttons' ).hidden ).toBe( false );
    } );

    test( 'submitted div is hidden before any response is recorded', async () => {
        const widget = await initWidget();
        expect( widget.querySelector( '.simplersvp-submitted' ).hidden ).toBe( true );
    } );

    test( 'count numbers start at 0', async () => {
        const widget = await initWidget();
        widget.querySelectorAll( '.simplersvp-count-num' ).forEach( el => {
            expect( el.textContent ).toBe( '0' );
        } );
    } );

    test( 'restores submitted state when server returns existing response', async () => {
        const serverState = {
            success: true,
            data: {
                counts:   { yes: 2, no: 0, maybe: 0 },
                response: 'yes',
                name:     '',
            },
        };
        const widget = await initWidget( makeWidgetHtml(), serverState );
        expect( widget.querySelector( '.simplersvp-submitted' ).hidden ).toBe( false );
        expect( widget.querySelector( '.simplersvp-buttons' ).hidden ).toBe( true );
    } );

    test( 'response label reflects server-returned response', async () => {
        const serverState = {
            success: true,
            data: { counts: { yes: 1, no: 0, maybe: 0 }, response: 'yes', name: '' },
        };
        const widget = await initWidget( makeWidgetHtml(), serverState );
        expect( widget.querySelector( '.simplersvp-response-label' ).textContent ).toBe( 'Yes' );
    } );
} );

// ── Count display ─────────────────────────────────────────────────────────────

describe( 'count display', () => {
    beforeEach( () => localStorage.clear() );

    test( 'updates count numbers from server response', async () => {
        const serverState = {
            success: true,
            data: { counts: { yes: 5, no: 2, maybe: 1 }, response: '', name: '' },
        };
        const widget = await initWidget( makeWidgetHtml(), serverState );
        expect( widget.querySelector( '[data-key="yes"]' ).textContent ).toBe( '5' );
        expect( widget.querySelector( '[data-key="no"]' ).textContent ).toBe( '2' );
        expect( widget.querySelector( '[data-key="maybe"]' ).textContent ).toBe( '1' );
    } );

    test( 'marks active count item when response is known', async () => {
        const serverState = {
            success: true,
            data: { counts: { yes: 3, no: 0, maybe: 0 }, response: 'yes', name: '' },
        };
        const widget = await initWidget( makeWidgetHtml(), serverState );
        expect(
            widget.querySelector( '.simplersvp-count-yes' ).classList.contains( 'simplersvp-count-active' )
        ).toBe( true );
        expect(
            widget.querySelector( '.simplersvp-count-no' ).classList.contains( 'simplersvp-count-active' )
        ).toBe( false );
    } );
} );

// ── Button clicks / submission flow ──────────────────────────────────────────

describe( 'RSVP submission', () => {
    beforeEach( () => localStorage.clear() );

    async function clickAndSubmit( widget, value, counts = { yes: 1, no: 0, maybe: 0 } ) {
        // After click, fetch will be called with a POST; mock the response.
        global.fetch = makeFetchMock( {
            success: true,
            data: { counts },
        } );

        const btn = widget.querySelector( `[data-value="${ value }"]` );
        btn.click();

        await flushPromises();
    }

    test( 'clicking a button adds simplersvp-selected class', async () => {
        const widget = await initWidget();
        const yesBtn = widget.querySelector( '.simplersvp-btn-yes' );
        global.fetch = makeFetchMock( { success: true, data: { counts: { yes: 1, no: 0, maybe: 0 } } } );
        yesBtn.click();
        expect( yesBtn.classList.contains( 'simplersvp-selected' ) ).toBe( true );
    } );

    test( 'only one button has simplersvp-selected at a time', async () => {
        const widget = await initWidget();
        global.fetch = makeFetchMock( { success: true, data: { counts: { yes: 0, no: 1, maybe: 0 } } } );
        widget.querySelector( '.simplersvp-btn-no' ).click();
        await Promise.resolve();
        const selected = widget.querySelectorAll( '.simplersvp-selected' );
        expect( selected.length ).toBe( 1 );
    } );

    test( 'submitted div becomes visible after successful submit', async () => {
        const widget = await initWidget();
        await clickAndSubmit( widget, 'yes' );
        expect( widget.querySelector( '.simplersvp-submitted' ).hidden ).toBe( false );
    } );

    test( 'buttons are hidden after successful submit', async () => {
        const widget = await initWidget();
        await clickAndSubmit( widget, 'no' );
        expect( widget.querySelector( '.simplersvp-buttons' ).hidden ).toBe( true );
    } );

    test( 'response label shows correct text after submit', async () => {
        const widget = await initWidget();
        await clickAndSubmit( widget, 'maybe', { yes: 0, no: 0, maybe: 1 } );
        expect( widget.querySelector( '.simplersvp-response-label' ).textContent ).toBe( 'Maybe' );
    } );

    test( 'count is updated after successful submit', async () => {
        const widget = await initWidget();
        await clickAndSubmit( widget, 'yes', { yes: 3, no: 0, maybe: 0 } );
        expect( widget.querySelector( '[data-key="yes"]' ).textContent ).toBe( '3' );
    } );

    test( 'POST body includes correct action and response value', async () => {
        const widget = await initWidget();
        const captureMock = jest.fn().mockResolvedValue( {
            json: () => Promise.resolve( { success: true, data: { counts: { yes: 1, no: 0, maybe: 0 } } } ),
        } );
        global.fetch = captureMock;

        widget.querySelector( '.simplersvp-btn-yes' ).click();
        await Promise.resolve();

        const [ url, options ] = captureMock.mock.calls[0];
        expect( url ).toBe( 'http://example.com/wp-admin/admin-ajax.php' );
        expect( options.method ).toBe( 'POST' );
        expect( options.body ).toContain( 'action=simplersvp_submit' );
        expect( options.body ).toContain( 'response=yes' );
        expect( options.body ).toContain( 'nonce=test-nonce-abc123' );
    } );

    test( 'POST body includes the post_id', async () => {
        const widget = await initWidget( makeWidgetHtml( { postId: '77' } ) );
        const captureMock = jest.fn().mockResolvedValue( {
            json: () => Promise.resolve( { success: true, data: { counts: { yes: 1, no: 0, maybe: 0 } } } ),
        } );
        global.fetch = captureMock;

        widget.querySelector( '.simplersvp-btn-yes' ).click();
        await Promise.resolve();

        expect( captureMock.mock.calls[0][1].body ).toContain( 'post_id=77' );
    } );
} );

// ── Change response flow ──────────────────────────────────────────────────────

describe( '"Change my response" flow', () => {
    beforeEach( () => localStorage.clear() );

    async function submitThenChange( widget ) {
        global.fetch = makeFetchMock( {
            success: true,
            data: { counts: { yes: 1, no: 0, maybe: 0 } },
        } );
        widget.querySelector( '.simplersvp-btn-yes' ).click();
        await Promise.resolve();
        await Promise.resolve();

        // Click "Change my response".
        widget.querySelector( '.simplersvp-change-btn' ).click();
    }

    test( 'buttons become visible after clicking change', async () => {
        const widget = await initWidget();
        await submitThenChange( widget );
        expect( widget.querySelector( '.simplersvp-buttons' ).hidden ).toBe( false );
    } );

    test( 'submitted div is hidden again after clicking change', async () => {
        const widget = await initWidget();
        await submitThenChange( widget );
        expect( widget.querySelector( '.simplersvp-submitted' ).hidden ).toBe( true );
    } );

    test( 'no selected button after clicking change', async () => {
        const widget = await initWidget();
        await submitThenChange( widget );
        const selected = widget.querySelectorAll( '.simplersvp-selected' );
        expect( selected.length ).toBe( 0 );
    } );
} );

// ── Polling ───────────────────────────────────────────────────────────────────

describe( 'polling', () => {
    beforeEach( () => {
        localStorage.clear();
        jest.useFakeTimers();
    } );

    afterEach( () => {
        jest.useRealTimers();
    } );

    test( 'polling fires a GET request after 10 seconds', async () => {
        await initWidget();
        const callsBefore = global.fetch.mock.calls.length;

        jest.advanceTimersByTime( 10000 );
        await Promise.resolve();

        expect( global.fetch.mock.calls.length ).toBeGreaterThan( callsBefore );
    } );

    test( 'polling request includes action=simplersvp_get_counts', async () => {
        await initWidget();

        jest.advanceTimersByTime( 10000 );
        await Promise.resolve();

        const lastUrl = global.fetch.mock.calls.at( -1 )[0];
        expect( lastUrl ).toContain( 'action=simplersvp_get_counts' );
    } );

    test( 'polling does not fire before 10 seconds', async () => {
        await initWidget();
        const callsBefore = global.fetch.mock.calls.length;

        jest.advanceTimersByTime( 9999 );
        await Promise.resolve();

        expect( global.fetch.mock.calls.length ).toBe( callsBefore );
    } );
} );
