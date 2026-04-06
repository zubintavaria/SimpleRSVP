/**
 * Jest setup — runs before each test file.
 *
 * Provides the globals that WordPress injects via wp_localize_script().
 */
global.SimpleRSVP = {
  ajax_url: 'http://example.com/wp-admin/admin-ajax.php',
  nonce:    'test-nonce-abc123',
};
