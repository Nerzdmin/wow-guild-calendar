/**
 * Smartlight Live Chat – Front-end Widget Script
 * Depends on: SLC (localised via wp_localize_script)
 */
( function () {
    'use strict';

    // ── helpers ─────────────────────────────────────────────────────
    function el( id ) { return document.getElementById( id ); }
    function esc( str ) {
        var d = document.createElement( 'div' );
        d.textContent = str;
        return d.innerHTML;
    }
    function fmt( dateStr ) {
        var d = new Date( dateStr.replace( ' ', 'T' ) );
        return d.toLocaleTimeString( [], { hour: '2-digit', minute: '2-digit' } );
    }

    // ── state ────────────────────────────────────────────────────────
    var state = {
        open:       false,
        started:    false,
        sessionId:  null,
        token:      null,
        lastId:     0,
        pollTimer:  null,
        closed:     false,
        unread:     0,
    };

    // ── DOM refs ─────────────────────────────────────────────────────
    var root        = el( 'slc-root' );
    var toggleBtn   = el( 'slc-toggle' );
    var iconOpen    = el( 'slc-icon-open' );
    var iconClose   = el( 'slc-icon-close' );
    var badge       = el( 'slc-badge' );
    var chatWindow  = el( 'slc-window' );
    var prechat     = el( 'slc-prechat' );
    var body        = el( 'slc-body' );
    var footer      = el( 'slc-footer' );
    var msgs        = el( 'slc-messages' );
    var closedNote  = el( 'slc-closed-notice' );
    var nameInput   = el( 'slc-visitor-name' );
    var emailInput  = el( 'slc-visitor-email' );
    var startBtn    = el( 'slc-start-btn' );
    var textInput   = el( 'slc-input' );
    var sendBtn     = el( 'slc-send-btn' );
    var closeBtn    = el( 'slc-close-btn' );
    var avatarImg   = el( 'slc-avatar' );
    var opNameEl    = el( 'slc-operator-name' );
    var welcomeText = el( 'slc-welcome-text' );

    // ── initialise DOM from config ───────────────────────────────────
    function boot() {
        var cfg = window.SLC;
        // apply accent colour as CSS var
        root.style.setProperty( '--slc-accent', cfg.accentColor );

        // position
        if ( cfg.position === 'left' ) { root.classList.add( 'slc-left' ); }

        // header
        avatarImg.src          = cfg.operatorAvatar;
        avatarImg.alt          = cfg.operatorName;
        opNameEl.textContent   = cfg.operatorName;
        welcomeText.textContent = cfg.i18n.title;

        // buttons / placeholders
        startBtn.textContent       = cfg.i18n.startChat;
        nameInput.placeholder      = cfg.i18n.namePlaceholder;
        emailInput.placeholder     = cfg.i18n.emailPlaceholder;
        textInput.placeholder      = cfg.i18n.typePlaceholder;
        sendBtn.textContent        = cfg.i18n.send;
        closedNote.textContent     = cfg.i18n.closed;

        // restore session from sessionStorage
        var saved = sessionStorage.getItem( 'slc_session' );
        if ( saved ) {
            try {
                var s = JSON.parse( saved );
                state.sessionId = s.sessionId;
                state.token     = s.token;
                state.lastId    = s.lastId || 0;
                state.started   = true;
                showChatArea();
                loadHistory();
                startPolling();
            } catch ( e ) {
                sessionStorage.removeItem( 'slc_session' );
            }
        }
    }

    // ── toggle open / close ─────────────────────────────────────────
    toggleBtn.addEventListener( 'click', function () {
        state.open = ! state.open;
        chatWindow.style.display  = state.open ? 'flex' : 'none';
        iconOpen.style.display    = state.open ? 'none' : 'block';
        iconClose.style.display   = state.open ? 'block' : 'none';
        toggleBtn.setAttribute( 'aria-expanded', state.open );
        if ( state.open ) {
            state.unread = 0;
            badge.style.display = 'none';
            scrollBottom();
        }
    } );
    closeBtn.addEventListener( 'click', function () {
        toggleBtn.click();
    } );

    // ── start chat ──────────────────────────────────────────────────
    startBtn.addEventListener( 'click', startChat );
    nameInput.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Enter' ) startChat(); } );

    function startChat() {
        var name = nameInput.value.trim();
        if ( ! name ) { nameInput.focus(); return; }
        startBtn.disabled = true;

        apiFetch( '/start', 'POST', { name: name, email: emailInput.value.trim() } )
            .then( function ( data ) {
                state.sessionId = data.session_id;
                state.token     = data.token;
                state.started   = true;
                saveSession();
                showChatArea();
                startPolling();
                // show welcome message immediately
                poll();
            } )
            .catch( function () { startBtn.disabled = false; } );
    }

    // ── send message ────────────────────────────────────────────────
    sendBtn.addEventListener( 'click', sendMessage );
    textInput.addEventListener( 'keydown', function ( e ) {
        if ( e.key === 'Enter' && ! e.shiftKey ) { e.preventDefault(); sendMessage(); }
    } );

    function sendMessage() {
        var text = textInput.value.trim();
        if ( ! text || state.closed ) return;
        sendBtn.disabled = true;
        textInput.value  = '';

        appendMessage( 'visitor', text, new Date().toISOString() );

        apiFetch( '/message', 'POST', { token: state.token, message: text } )
            .then( function () { sendBtn.disabled = false; } )
            .catch( function () {
                showError( window.SLC.i18n.errorSend );
                sendBtn.disabled = false;
            } );
    }

    // ── polling ──────────────────────────────────────────────────────
    function startPolling() {
        clearInterval( state.pollTimer );
        state.pollTimer = setInterval( poll, window.SLC.pollInterval );
    }

    function poll() {
        if ( ! state.token ) return;
        apiFetch( '/poll?token=' + encodeURIComponent( state.token ) + '&after_id=' + state.lastId, 'GET' )
            .then( function ( data ) {
                data.messages.forEach( function ( m ) {
                    appendMessage( 'operator', m.body, m.sent_at );
                    state.lastId = Math.max( state.lastId, m.id );
                    if ( ! state.open ) {
                        state.unread++;
                        badge.textContent   = state.unread;
                        badge.style.display = 'block';
                    }
                } );
                saveSession();

                if ( data.status === 'closed' && ! state.closed ) {
                    state.closed = true;
                    clearInterval( state.pollTimer );
                    footer.style.display      = 'none';
                    closedNote.style.display  = 'block';
                }
            } );
    }

    // ── history reload ────────────────────────────────────────────────
    function loadHistory() {
        apiFetch( '/poll?token=' + encodeURIComponent( state.token ) + '&after_id=0', 'GET' )
            .then( function ( data ) {
                data.messages.forEach( function ( m ) {
                    appendMessage( 'operator', m.body, m.sent_at );
                    state.lastId = Math.max( state.lastId, m.id );
                } );
                scrollBottom();
            } );
    }

    // ── DOM helpers ──────────────────────────────────────────────────
    function showChatArea() {
        prechat.style.display = 'none';
        body.style.display    = 'block';
        footer.style.display  = 'flex';
    }

    function appendMessage( sender, text, time ) {
        var li  = document.createElement( 'li' );
        li.className = sender === 'operator' ? 'slc-op' : 'slc-vis';
        li.innerHTML = esc( text ) + '<span class="slc-time">' + fmt( time ) + '</span>';
        msgs.appendChild( li );
        scrollBottom();
    }

    function scrollBottom() {
        var b = el( 'slc-body' );
        if ( b ) b.scrollTop = b.scrollHeight;
    }

    function showError( msg ) {
        var li = document.createElement( 'li' );
        li.className = 'slc-op';
        li.style.color = '#c0331a';
        li.textContent = msg;
        msgs.appendChild( li );
        scrollBottom();
    }

    // ── sessionStorage persistence ────────────────────────────────────
    function saveSession() {
        sessionStorage.setItem( 'slc_session', JSON.stringify( {
            sessionId: state.sessionId,
            token:     state.token,
            lastId:    state.lastId,
        } ) );
    }

    // ── fetch wrapper ─────────────────────────────────────────────────
    function apiFetch( path, method, body ) {
        var cfg  = window.SLC;
        var url  = cfg.apiRoot + path;
        var opts = {
            method:  method || 'GET',
            headers: {
                'X-WP-Nonce': cfg.nonce,
            },
        };
        if ( body ) {
            opts.headers[ 'Content-Type' ] = 'application/json';
            opts.body = JSON.stringify( body );
        }
        return fetch( url, opts ).then( function ( r ) {
            if ( ! r.ok ) {
                return Promise.reject( new Error( 'HTTP ' + r.status ) );
            }
            return r.json();
        } );
    }

    // ── boot ──────────────────────────────────────────────────────────
    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', boot );
    } else {
        boot();
    }
} )();
