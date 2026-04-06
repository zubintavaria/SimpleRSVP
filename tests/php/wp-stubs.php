<?php
/**
 * Lightweight WordPress function / class stubs for unit testing.
 *
 * These replace the real WordPress environment so the plugin classes can be
 * loaded and exercised without a running WordPress installation.
 *
 * Each stub does the minimum needed to make the tests pass:
 *   - Pure-logic functions (absint, sanitize_key …) behave like the real thing.
 *   - I/O functions (wp_send_json_*, check_ajax_referer …) are no-ops or
 *     record their arguments for test inspection.
 */

// ── Constants ──────────────────────────────────────────────────────────────

if ( ! defined( 'ABSPATH' ) )          define( 'ABSPATH', '/tmp/wp/' );
if ( ! defined( 'SIMPLERSVP_VERSION' ) ) define( 'SIMPLERSVP_VERSION', '1.0.0' );
if ( ! defined( 'SIMPLERSVP_DIR' ) )   define( 'SIMPLERSVP_DIR', dirname( __DIR__, 2 ) . '/simplersvp/' );
if ( ! defined( 'SIMPLERSVP_URL' ) )   define( 'SIMPLERSVP_URL', 'http://example.com/wp-content/plugins/simplersvp/' );
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) define( 'MINUTE_IN_SECONDS', 60 );

// ── Global state used by stubs ─────────────────────────────────────────────

/** Captures the last wp_send_json_* call for assertions. */
$GLOBALS['_srsvp_last_json'] = null;

/** Captures the last wp_safe_redirect() URL for assertions. */
$GLOBALS['_srsvp_last_redirect'] = null;

/** In-memory transient store. */
$GLOBALS['_srsvp_transients'] = [];

/** In-memory post store (id → WP_Post stub). */
$GLOBALS['_srsvp_posts'] = [];

/** In-memory post title store (id → string). */
$GLOBALS['_srsvp_post_titles'] = [];

// ── Input-sanitising helpers ───────────────────────────────────────────────

if ( ! function_exists( 'absint' ) ) {
    function absint( $v ) { return abs( (int) $v ); }
}

if ( ! function_exists( 'wp_unslash' ) ) {
    function wp_unslash( $v ) {
        return is_array( $v ) ? array_map( 'wp_unslash', $v ) : stripslashes( $v );
    }
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $str ) {
        return trim( strip_tags( $str ) );
    }
}

if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( $key ) {
        return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
    }
}

// ── Nonce / AJAX response stubs ────────────────────────────────────────────

if ( ! function_exists( 'check_ajax_referer' ) ) {
    /** No-op: nonce checking is tested separately; here we always pass. */
    function check_ajax_referer( $action, $query_arg = false, $die = true ) {
        return true;
    }
}

if ( ! function_exists( 'wp_send_json_success' ) ) {
    function wp_send_json_success( $data = null, $status_code = 200 ) {
        $GLOBALS['_srsvp_last_json'] = [
            'success' => true,
            'data'    => $data,
            'status'  => $status_code,
        ];
        // Do NOT call die() so tests can continue.
    }
}

if ( ! function_exists( 'wp_send_json_error' ) ) {
    function wp_send_json_error( $data = null, $status_code = 200 ) {
        $GLOBALS['_srsvp_last_json'] = [
            'success' => false,
            'data'    => $data,
            'status'  => $status_code,
        ];
    }
}

// ── Transient stubs ────────────────────────────────────────────────────────

if ( ! function_exists( 'get_transient' ) ) {
    function get_transient( $key ) {
        return $GLOBALS['_srsvp_transients'][ $key ] ?? false;
    }
}

if ( ! function_exists( 'set_transient' ) ) {
    function set_transient( $key, $value, $expiration = 0 ) {
        $GLOBALS['_srsvp_transients'][ $key ] = $value;
        return true;
    }
}

// ── Post stubs ─────────────────────────────────────────────────────────────

if ( ! function_exists( 'get_post' ) ) {
    function get_post( $post_id, $output = OBJECT, $filter = 'raw' ) {
        return $GLOBALS['_srsvp_posts'][ $post_id ] ?? null;
    }
}

if ( ! defined( 'OBJECT' ) ) {
    define( 'OBJECT', 'OBJECT' );
}

if ( ! defined( 'ARRAY_A' ) ) {
    define( 'ARRAY_A', 'ARRAY_A' );
}

// ── Output-escaping helpers ────────────────────────────────────────────────

if ( ! function_exists( 'esc_html' ) ) {
    function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
}

if ( ! function_exists( 'esc_attr' ) ) {
    function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
}

if ( ! function_exists( 'esc_url' ) ) {
    function esc_url( $url ) { return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' ); }
}

if ( ! function_exists( 'esc_html_e' ) ) {
    function esc_html_e( $text, $domain = 'default' ) { echo esc_html( $text ); }
}

if ( ! function_exists( 'esc_attr_e' ) ) {
    function esc_attr_e( $text, $domain = 'default' ) { echo esc_attr( $text ); }
}

if ( ! function_exists( '__' ) ) {
    function __( $text, $domain = 'default' ) { return $text; }
}

if ( ! function_exists( 'esc_html__' ) ) {
    function esc_html__( $text, $domain = 'default' ) { return esc_html( $text ); }
}

if ( ! function_exists( 'esc_attr__' ) ) {
    function esc_attr__( $text, $domain = 'default' ) { return esc_attr( $text ); }
}

// ── Other WP helpers used by plugin classes ────────────────────────────────

if ( ! function_exists( 'add_shortcode' ) ) {
    function add_shortcode( $tag, $callback ) {}
}

if ( ! function_exists( 'shortcode_atts' ) ) {
    /**
     * Merges user-supplied $atts with $pairs defaults.
     * Matches WordPress behaviour: unknown keys in $atts are discarded.
     */
    function shortcode_atts( array $pairs, $atts, $shortcode = '' ) {
        $atts = (array) $atts;
        $out  = [];
        foreach ( $pairs as $name => $default ) {
            $out[ $name ] = array_key_exists( $name, $atts ) ? $atts[ $name ] : $default;
        }
        return $out;
    }
}

if ( ! function_exists( 'get_the_ID' ) ) {
    function get_the_ID() { return 1; }
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
    function wp_enqueue_style() {}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
    function wp_enqueue_script() {}
}

if ( ! function_exists( 'current_time' ) ) {
    function current_time( $type ) {
        return date( 'Y-m-d H:i:s' );
    }
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
    function plugin_dir_path( $file ) { return rtrim( dirname( $file ), '/' ) . '/'; }
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
    function plugin_dir_url( $file ) { return 'http://example.com/wp-content/plugins/simplersvp/'; }
}

if ( ! function_exists( 'admin_url' ) ) {
    function admin_url( $path = '' ) { return 'http://example.com/wp-admin/' . ltrim( $path, '/' ); }
}

if ( ! function_exists( 'register_activation_hook' ) ) {
    function register_activation_hook() {}
}

if ( ! function_exists( 'add_action' ) ) {
    function add_action() {}
}

if ( ! function_exists( 'add_menu_page' ) ) {
    function add_menu_page() {}
}

if ( ! function_exists( 'current_user_can' ) ) {
    function current_user_can( $capability ) { return true; }
}

if ( ! function_exists( 'wp_die' ) ) {
    function wp_die( $message = '', $title = '', $args = array() ) {
        throw new \RuntimeException( 'wp_die: ' . $message );
    }
}

if ( ! function_exists( 'check_admin_referer' ) ) {
    /** No-op: nonce validation always passes in tests. */
    function check_admin_referer( $action, $query_arg = '_wpnonce' ) { return true; }
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
    function wp_nonce_field( $action, $name = '_wpnonce', $referer = true, $echo = true ) {
        $html = '<input type="hidden" name="' . esc_attr( $name ) . '" value="test-nonce" />';
        if ( $echo ) { echo $html; }
        return $html;
    }
}

if ( ! function_exists( 'esc_js' ) ) {
    function esc_js( $text ) { return addslashes( (string) $text ); }
}

if ( ! function_exists( 'add_query_arg' ) ) {
    function add_query_arg( $args, $url = '' ) {
        $query = http_build_query( $args );
        return $url . ( strpos( $url, '?' ) === false ? '?' : '&' ) . $query;
    }
}

/**
 * Exception thrown by wp_safe_redirect() so tests can intercept and assert
 * on the redirect URL without executing a real HTTP redirect or exit().
 */
class SimpleRSVP_RedirectException extends \RuntimeException {}

if ( ! function_exists( 'wp_safe_redirect' ) ) {
    function wp_safe_redirect( $url, $status = 302, $x_redirect_by = 'WordPress' ) {
        $GLOBALS['_srsvp_last_redirect'] = $url;
        throw new SimpleRSVP_RedirectException( $url );
    }
}

if ( ! function_exists( 'get_permalink' ) ) {
    function get_permalink( $post_id = 0 ) {
        return 'http://example.com/?p=' . (int) $post_id;
    }
}

if ( ! function_exists( 'get_the_title' ) ) {
    function get_the_title( $post_id = 0 ) {
        return $GLOBALS['_srsvp_post_titles'][ $post_id ] ?? '';
    }
}

// ── Minimal $wpdb stub ─────────────────────────────────────────────────────

/**
 * Minimal wpdb stub.
 *
 * Tests that need specific return values should set properties directly:
 *   $wpdb->__next_get_var    = '42';
 *   $wpdb->__next_get_results = [...];
 *
 * Call history is recorded in $wpdb->__calls[].
 */
class WpdbStub {
    public string $prefix     = 'wp_';
    public string $last_error = '';

    /** Queue for get_var() return values (FIFO). */
    public array $__get_var_queue    = [];
    /** Queue for get_results() return values (FIFO). */
    public array $__get_results_queue = [];
    /** All calls recorded here for assertion. */
    public array $__calls            = [];

    public function get_charset_collate(): string {
        return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
    }

    /** Simulates sprintf-style query preparation (safe for tests). */
    public function prepare( string $query, ...$args ): string {
        // Replace %d/%s/%f with the supplied values (test-only approximation).
        $i = 0;
        return preg_replace_callback( '/%[dsf]/', function () use ( &$i, $args ) {
            return $args[ $i++ ] ?? '?';
        }, $query );
    }

    public function get_var( string $query ): mixed {
        $this->__calls[] = [ 'method' => 'get_var', 'query' => $query ];
        return array_shift( $this->__get_var_queue );
    }

    public function get_results( string $query, $output = OBJECT ): array {
        $this->__calls[] = [ 'method' => 'get_results', 'query' => $query ];
        return array_shift( $this->__get_results_queue ) ?? [];
    }

    public function insert( string $table, array $data, $format = null ): int|false {
        $this->__calls[] = [ 'method' => 'insert', 'table' => $table, 'data' => $data ];
        return 1;
    }

    public function update( string $table, array $data, array $where, $format = null, $where_format = null ): int|false {
        $this->__calls[] = [ 'method' => 'update', 'table' => $table, 'data' => $data, 'where' => $where ];
        return 1;
    }

    public function delete( string $table, array $where, $where_format = null ): int|false {
        $this->__calls[] = [ 'method' => 'delete', 'table' => $table, 'where' => $where ];
        return 1;
    }
}
