# SimpleRSVP

A lightweight WordPress plugin that adds a simple, embeddable RSVP widget to any post or page via a shortcode.

Visitors can respond with **Yes**, **No**, or **Maybe** (optional). Each device gets one response. Live counts update automatically as others respond, and anyone can change their answer at any time. An admin dashboard shows headcounts and named responses per event.

---

## Features

- **One shortcode** embeds a fully self-contained widget anywhere
- **Anonymous-friendly** — no login required; device identity is stored locally in the browser
- **Optional name field** — visitors can identify themselves (or stay anonymous)
- **Change response** — a "Change my response" link lets anyone revise their answer without losing the count history
- **Live counts** — the Yes / No / Maybe totals update automatically every 10 seconds without a page reload
- **Admin dashboard** — view headcounts and named responses per event from the WordPress back end
- **Theme-aware styling** — inherits your theme's fonts and text color; only the semantic accent colors (green / red / orange) are fixed
- **Maybe is optional** — hide it with a shortcode parameter for events where you only want Yes / No

---

## Requirements

- WordPress 6.0 or later
- PHP 7.4 or later

---

## Installation

1. **Download** this repository as a ZIP, or clone it:
   ```bash
   git clone https://github.com/zubintavaria/simplersvp.git
   ```

2. **Copy the plugin folder** into your WordPress plugins directory:
   ```
   wp-content/plugins/simplersvp/
   ```
   The folder structure should look like:
   ```
   wp-content/plugins/simplersvp/
   ├── simplersvp.php
   ├── includes/
   ├── assets/
   └── templates/
   ```

3. **Activate the plugin** in the WordPress admin under **Plugins → Installed Plugins**.

   The database table (`wp_simplersvp`) is created automatically on activation.

---

## Usage

Place the shortcode anywhere in a post or page body:

```
[simplersvp]
```

That's it. The widget renders with default text and theme styling.

### Shortcode parameters

All parameters are optional.

| Parameter | Default | Description |
|---|---|---|
| `question` | `Will you attend?` | The prompt shown above the buttons |
| `yes` | `Yes` | Label for the Yes button |
| `no` | `No` | Label for the No button |
| `maybe` | `Maybe` | Label for the Maybe button |
| `show_maybe` | `true` | Set to `false` to hide the Maybe option entirely |

### Examples

Default widget:
```
[simplersvp]
```

Custom labels:
```
[simplersvp question="Coming to the barbecue?" yes="Count me in" no="Can't make it"]
```

Yes / No only:
```
[simplersvp question="Will you be joining us?" show_maybe="false"]
```

Custom everything:
```
[simplersvp question="Dinner on Saturday?" yes="I'll be there" no="Sorry, can't" maybe="Not sure yet"]
```

---

## How it works

### Visitor flow

1. A visitor opens a post with the `[simplersvp]` shortcode.
2. The widget loads the current Yes / No / Maybe counts from the server.
3. If the visitor has responded before (on this device), their previous answer is shown and the buttons are hidden.
4. If not, the visitor can optionally enter their name and click a response button.
5. Their answer is saved. The counts update immediately.
6. A **Change my response** link appears — clicking it re-shows the buttons so they can revise.
7. Counts continue to refresh every 10 seconds in the background.

### Device identity

Responses are tied to a **UUID** generated in the visitor's browser and stored in `localStorage`. No cookies. No account required. No personally identifiable information is sent to the server unless the visitor enters their name.

---

## Admin dashboard

Navigate to **SimpleRSVP** in the WordPress admin sidebar.

**Event list** — all posts/pages that have received at least one RSVP, with a total count and a link to the detail view.

**Event detail** — a summary table showing the Yes / No / Maybe breakdown with percentage bars, followed by a full list of individual responses with names (or "anonymous") and timestamps.

---

## Running the tests

The test suite covers the PHP backend (PHPUnit) and the JavaScript frontend (Jest).

### PHP tests

```bash
composer install
vendor/bin/phpunit
```

Expected output: **81 tests, 128 assertions**

Covers:
- Input validation: UUID regex, response whitelist, rate limiting, post status checks, injection payloads
- Database logic: insert vs update paths, count aggregation, zero-fill, type safety
- Shortcode rendering: attribute defaults, `show_maybe` flag, data-attributes, XSS escaping

### JavaScript tests

```bash
npm install
npm test
```

Expected output: **26 tests passed**

Covers:
- UUID generation and localStorage persistence
- Name pre-fill from localStorage and server
- Initial render state
- RSVP submission: DOM transitions, POST payload shape
- "Change my response" flow
- Polling: fires after 10 s, correct endpoint, does not fire early

---

## Security

| Concern | Mitigation |
|---|---|
| CSRF | WordPress nonce on every AJAX request (`wp_create_nonce` / `check_ajax_referer`) |
| Input injection | `absint`, `sanitize_text_field`, `sanitize_key`, `wp_unslash` on all inputs |
| Fake device IDs | UUID v4 format validated server-side with a strict regex |
| Spam / flooding | Transient-based rate limit: max 10 submissions per device per minute |
| XSS (admin) | All output escaped with `esc_html`, `esc_attr`, `esc_url` |
| Privilege escalation | Admin dashboard gated behind `manage_options` capability |

---

## License

[GPL-2.0-or-later](LICENSE)
