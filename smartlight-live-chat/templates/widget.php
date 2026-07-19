<?php defined( 'ABSPATH' ) || exit; ?>
<!-- Smartlight Live Chat Widget -->
<div id="slc-root" aria-label="<?php esc_attr_e( 'Live Chat', 'smartlight-live-chat' ); ?>" role="complementary">

    <!-- Bubble toggle button -->
    <button id="slc-toggle" aria-expanded="false" aria-controls="slc-window" title="<?php esc_attr_e( 'Open chat', 'smartlight-live-chat' ); ?>">
        <svg id="slc-icon-open"  xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 2H4a2 2 0 0 0-2 2v18l4-4h14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z"/></svg>
        <svg id="slc-icon-close" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" style="display:none"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
        <span id="slc-badge" style="display:none" aria-label="<?php esc_attr_e( 'Unread messages', 'smartlight-live-chat' ); ?>"></span>
    </button>

    <!-- Chat window -->
    <div id="slc-window" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Chat window', 'smartlight-live-chat' ); ?>" style="display:none">

        <!-- Header -->
        <div id="slc-header">
            <img id="slc-avatar" src="" alt="" aria-hidden="true">
            <span id="slc-operator-name"></span>
            <button id="slc-close-btn" aria-label="<?php esc_attr_e( 'Close chat', 'smartlight-live-chat' ); ?>">&#x2715;</button>
        </div>

        <!-- Pre-chat form -->
        <div id="slc-prechat">
            <p id="slc-welcome-text"></p>
            <label for="slc-visitor-name"><?php esc_html_e( 'Your name', 'smartlight-live-chat' ); ?> <span aria-hidden="true">*</span></label>
            <input type="text" id="slc-visitor-name" autocomplete="name" required>
            <label for="slc-visitor-email"><?php esc_html_e( 'E-mail (optional)', 'smartlight-live-chat' ); ?></label>
            <input type="email" id="slc-visitor-email" autocomplete="email">
            <button id="slc-start-btn" type="button"></button>
        </div>

        <!-- Messages area -->
        <div id="slc-body" style="display:none" aria-live="polite" aria-relevant="additions">
            <ul id="slc-messages" role="log" aria-label="<?php esc_html_e( 'Chat messages', 'smartlight-live-chat' ); ?>"></ul>
        </div>

        <!-- Input area -->
        <div id="slc-footer" style="display:none">
            <textarea id="slc-input" rows="2" placeholder="" aria-label="<?php esc_attr_e( 'Type a message', 'smartlight-live-chat' ); ?>"></textarea>
            <button id="slc-send-btn" type="button"></button>
        </div>

        <!-- Closed notice -->
        <div id="slc-closed-notice" style="display:none"></div>

    </div><!-- /slc-window -->
</div><!-- /slc-root -->
