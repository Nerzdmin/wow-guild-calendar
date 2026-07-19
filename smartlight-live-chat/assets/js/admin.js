/**
 * Smartlight Live Chat – Admin Inbox Script
 * Depends on: SLC_ADMIN (localised via wp_localize_script)
 */
( function () {
    'use strict';

    var cfg            = window.SLC_ADMIN;
    var currentSession = null;
    var pollTimer      = null;
    var lastMsgId      = 0;

    // ── DOM refs ─────────────────────────────────────────────────────
    var sessionsList   = document.getElementById( 'slc-sessions-list-ul' );
    var noSessions     = document.getElementById( 'slc-no-sessions' );
    var chatPanel      = document.getElementById( 'slc-chat-panel' );
    var emptyState     = document.getElementById( 'slc-empty-state' );
    var chatHeader     = document.getElementById( 'slc-chat-header' );
    var adminMessages  = document.getElementById( 'slc-admin-messages' );
    var replyInput     = document.getElementById( 'slc-reply-input' );
    var replyBtn       = document.getElementById( 'slc-reply-btn' );
    var closeSessionBtn= document.getElementById( 'slc-close-session-btn' );
    var visitorName    = document.getElementById( 'slc-v-name' );
    var visitorEmail   = document.getElementById( 'slc-v-email' );

    // ── helpers ──────────────────────────────────────────────────────
    function esc( str ) {
        var d = document.createElement( 'div' );
        d.textContent = str;
        return d.innerHTML;
    }
    function fmt( dateStr ) {
        var d = new Date( dateStr.replace( ' ', 'T' ) );
        return d.toLocaleTimeString( [], { hour: '2-digit', minute: '2-digit' } )
             + ' ' + d.toLocaleDateString( [], { month: 'short', day: 'numeric' } );
    }

    function apiFetch( path, method, body ) {
        var url  = cfg.apiRoot + path;
        var opts = {
            method:  method || 'GET',
            headers: { 'X-WP-Nonce': cfg.nonce },
            credentials: 'same-origin',
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

    // ── load session list ─────────────────────────────────────────────
    function loadSessions() {
        apiFetch( '/admin/sessions', 'GET' ).then( function ( sessions ) {
            sessionsList.innerHTML = '';
            if ( ! sessions.length ) {
                noSessions.style.display = 'block';
                return;
            }
            noSessions.style.display = 'none';

            sessions.forEach( function ( s ) {
                var li = document.createElement( 'li' );
                li.className = 'slc-session-item' + ( currentSession && currentSession.id == s.id ? ' active' : '' );
                li.setAttribute( 'data-id', s.id );
                li.innerHTML =
                    '<span class="slc-s-name">' + esc( s.visitor_name ) + ( +s.unread > 0 ? '<span class="slc-unread-dot"></span>' : '' ) + '</span>'
                    + '<span class="slc-s-email">' + esc( s.visitor_email || '—' ) + '</span>'
                    + '<span class="slc-s-time">' + fmt( s.updated_at ) + '</span>';
                li.addEventListener( 'click', function () { openSession( s ); } );
                sessionsList.appendChild( li );
            } );
        } );
    }

    // ── open session ──────────────────────────────────────────────────
    function openSession( session ) {
        currentSession  = session;
        lastMsgId       = 0;
        clearInterval( pollTimer );

        // update header
        visitorName.textContent  = session.visitor_name;
        visitorEmail.textContent = session.visitor_email || '';
        adminMessages.innerHTML  = '';
        emptyState.style.display = 'none';
        chatPanel.style.display  = 'flex';

        // mark active
        document.querySelectorAll( '.slc-session-item' ).forEach( function ( li ) {
            li.classList.toggle( 'active', li.getAttribute( 'data-id' ) == session.id );
        } );

        // load messages
        loadMessages();
        pollTimer = setInterval( loadMessages, cfg.pollInterval );
    }

    function loadMessages() {
        if ( ! currentSession ) return;
        apiFetch( '/admin/session/' + currentSession.id, 'GET' ).then( function ( messages ) {
            messages.forEach( function ( m ) {
                if ( m.id <= lastMsgId ) return;
                appendMessage( m.sender, m.body, m.sent_at );
                lastMsgId = m.id;
            } );
        } );
    }

    function appendMessage( sender, text, time ) {
        var div = document.createElement( 'div' );
        div.className = 'slc-admin-msg ' + ( sender === 'operator' ? 'slc-op' : 'slc-vis' );
        div.innerHTML = esc( text ) + '<span class="slc-meta">' + fmt( time ) + ' · ' + esc( sender ) + '</span>';
        adminMessages.appendChild( div );
        adminMessages.scrollTop = adminMessages.scrollHeight;
    }

    // ── send reply ────────────────────────────────────────────────────
    replyBtn.addEventListener( 'click', sendReply );
    replyInput.addEventListener( 'keydown', function ( e ) {
        if ( e.key === 'Enter' && ! e.shiftKey ) { e.preventDefault(); sendReply(); }
    } );

    function sendReply() {
        var msg = replyInput.value.trim();
        if ( ! msg || ! currentSession ) return;
        replyBtn.disabled = true;
        replyInput.value  = '';

        apiFetch( '/admin/reply', 'POST', { session_id: currentSession.id, message: msg } )
            .then( function () {
                replyBtn.disabled = false;
                loadMessages();
            } )
            .catch( function () { replyBtn.disabled = false; } );
    }

    // ── close session ─────────────────────────────────────────────────
    closeSessionBtn.addEventListener( 'click', function () {
        if ( ! currentSession ) return;
        if ( ! confirm( 'Close this chat session?' ) ) return;

        apiFetch( '/admin/close', 'POST', { session_id: currentSession.id } )
            .then( function () {
                clearInterval( pollTimer );
                currentSession = null;
                chatPanel.style.display  = 'none';
                emptyState.style.display = 'flex';
                loadSessions();
            } );
    } );

    // ── auto-refresh session list ─────────────────────────────────────
    setInterval( loadSessions, cfg.pollInterval * 2 );

    // ── init ─────────────────────────────────────────────────────────
    loadSessions();

    // open a session from URL param (?session=<id>)
    var urlParams  = new URLSearchParams( window.location.search );
    var preselect  = urlParams.get( 'session' );
    if ( preselect ) {
        apiFetch( '/admin/sessions', 'GET' ).then( function ( sessions ) {
            var found = sessions.find( function ( s ) { return s.id == preselect; } );
            if ( found ) openSession( found );
        } );
    }
} )();
