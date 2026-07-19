<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap">
    <h1><?php esc_html_e( 'Live Chat Inbox', 'smartlight-live-chat' ); ?></h1>

    <div id="slc-admin-wrap">

        <!-- ── Sidebar: session list ─────────────────────────────── -->
        <div id="slc-sessions-list">
            <h3><?php esc_html_e( 'Open Chats', 'smartlight-live-chat' ); ?></h3>
            <ul id="slc-sessions-list-ul" style="list-style:none;margin:0;padding:0;"></ul>
            <p id="slc-no-sessions" style="display:none">
                <?php esc_html_e( 'No open chats right now.', 'smartlight-live-chat' ); ?>
            </p>
        </div>

        <!-- ── Main: chat panel ──────────────────────────────────── -->
        <div id="slc-chat-panel" style="display:none;flex-direction:column;">

            <div id="slc-chat-header">
                <div class="slc-visitor-info">
                    <div class="slc-visitor-name" id="slc-v-name"></div>
                    <div class="slc-visitor-email" id="slc-v-email"></div>
                </div>
                <button id="slc-close-session-btn">
                    <?php esc_html_e( 'Close session', 'smartlight-live-chat' ); ?>
                </button>
            </div>

            <div id="slc-admin-messages"></div>

            <div id="slc-reply-area">
                <textarea
                    id="slc-reply-input"
                    placeholder="<?php esc_attr_e( 'Type your reply… (Enter to send)', 'smartlight-live-chat' ); ?>"
                    rows="2"
                ></textarea>
                <button id="slc-reply-btn">
                    <?php esc_html_e( 'Send', 'smartlight-live-chat' ); ?>
                </button>
            </div>

        </div><!-- /slc-chat-panel -->

        <!-- Empty state -->
        <div id="slc-empty-state">
            <?php esc_html_e( 'Select a chat from the left to start replying.', 'smartlight-live-chat' ); ?>
        </div>

    </div><!-- /slc-admin-wrap -->
</div>
