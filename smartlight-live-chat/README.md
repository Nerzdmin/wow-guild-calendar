# Smartlight Live Chat – WordPress Plugin

A lightweight real-time chat plugin for WordPress that replaces the traditional contact form with an interactive live-chat widget.  
Built for **www.smartlight.sk** but fully portable to any WordPress site.

---

## Features

| Feature | Detail |
|---------|--------|
| Chat bubble | Fixed-position button (bottom-right or bottom-left), accent colour configurable |
| Pre-chat form | Visitor enters name + optional e-mail before starting |
| Real-time polling | New messages appear every 4 s (no WebSocket server required) |
| Admin inbox | Dedicated WP-Admin page lists all open sessions; operators type replies inline |
| E-mail notification | Admin receives an e-mail when a new chat starts (one click to open inbox) |
| Session persistence | Browser `sessionStorage` keeps the chat alive on page navigation |
| Offline mode | Toggle in settings – shows a configurable offline message instead of starting a chat |
| Uninstall clean-up | Deletes both DB tables and all options on plugin deletion |

---

## Installation

1. Upload the `smartlight-live-chat` folder to `/wp-content/plugins/`.
2. Activate the plugin through **Plugins → Installed Plugins**.
3. Navigate to **Live Chat → Settings** and configure operator name, colours, notification e-mail, etc.
4. The chat bubble appears automatically on every front-end page.  
   Alternatively, embed it in a specific page/post using the shortcode:  
   ```
   [slc_chat]
   ```

---

## Configuration (Settings page)

| Setting | Default | Description |
|---------|---------|-------------|
| Operator name | Site name | Shown in the chat header |
| Operator avatar URL | Built-in SVG | URL to a square image |
| Welcome message | "Hello! How can we help you today?" | First message sent automatically |
| Offline message | "(see default)" | Shown when Offline mode is enabled |
| Offline mode | Off | Disables live chat; shows offline message |
| Chat window title | "Chat with us" | Title shown in the pre-chat screen |
| Accent colour | `#e63c1e` | Used for the bubble, header, send buttons |
| Button position | Bottom-right | Can be set to Bottom-left |
| Notification e-mail | Admin e-mail | Receives new-chat notifications |

---

## REST API

All endpoints live under `{site}/wp-json/slc/v1/`.

### Public (visitor)

| Method | Path | Purpose |
|--------|------|---------|
| `POST` | `/start` | Begin a new session. Params: `name`, `email` |
| `POST` | `/message` | Send a visitor message. Params: `token`, `message` |
| `GET`  | `/poll` | Poll for new operator messages. Params: `token`, `after_id` |

### Admin (requires `manage_options`)

| Method | Path | Purpose |
|--------|------|---------|
| `GET`  | `/admin/sessions` | List all open sessions |
| `GET`  | `/admin/session/{id}` | Get all messages for a session |
| `POST` | `/admin/reply` | Send operator reply. Params: `session_id`, `message` |
| `POST` | `/admin/close` | Close a session. Params: `session_id` |

---

## Database Tables

Two tables are created on activation (prefixed with the WP table prefix):

**`{prefix}slc_sessions`**  
`id`, `session_token`, `visitor_name`, `visitor_email`, `status` (open/closed), `operator_id`, `created_at`, `updated_at`

**`{prefix}slc_messages`**  
`id`, `session_id`, `sender` (visitor/operator), `body`, `sent_at`, `is_read`

Both tables are removed on plugin uninstall.

---

## Requirements

- WordPress 5.8+
- PHP 7.4+
- MySQL 5.7+ / MariaDB 10.2+

---

## License

GPL-2.0+
