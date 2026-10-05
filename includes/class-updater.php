<?php
namespace H2O\Site_Migrator;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * GitHub release updater for h2o IA Site Migrator.
 *
 * The runtime consumes only GitHub's public release API and release assets.
 * No other H2O component, plugin internals or WordPress private data are read.
 */
final class Updater {
    private const REPOSITORY = 'h2ostudiochile/h2o-ia-site-migrator';
    private const UPDATE_URI = 'https://github.com/h2ostudiochile/h2o-ia-site-migrator';
    private const API_URL = 'https://api.github.com/repos/h2ostudiochile/h2o-ia-site-migrator/releases/latest';
    private const CACHE_KEY = 'h2osm_release_manifest';
    private const VERIFY_KEY = 'h2osm_update_verification';

    public static function boot(): void {
        add_filter( 'update_plugins_github.com', array( self::class, 'check' ), 10, 4 );
        add_filter( 'upgrader_pre_download', array( self::class, 'verify_download' ), 10, 4 );
        add_filter( 'plugin_row_meta', array( self::class, 'row_meta' ), 10, 2 );
    }

    public static function check( $update, array $plugin_data, string $plugin_file, array $locales ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
        if ( plugin_basename( H2OSM_FILE ) !== $plugin_file ) {
            return $update;
        }

        $manifest = self::manifest();
        if ( ! $manifest || empty( $manifest['version'] ) || version_compare( (string) $manifest['version'], H2OSM_VERSION, '<=' ) ) {
            return false;
        }

        set_site_transient(
            self::VERIFY_KEY,
            array(
                'package' => (string) $manifest['package_url'],
                'sha256'  => (string) $manifest['package_sha256'],
                'version' => (string) $manifest['version'],
            ),
            12 * HOUR_IN_SECONDS
        );

        return array(
            'id'           => self::UPDATE_URI,
            'slug'         => 'h2o-ia-site-migrator',
            'version'      => (string) $manifest['version'],
            'url'          => self::UPDATE_URI,
            'package'      => (string) $manifest['package_url'],
            'tested'       => isset( $manifest['tested'] ) ? sanitize_text_field( (string) $manifest['tested'] ) : '',
            'requires_php' => isset( $manifest['requires_php'] ) ? sanitize_text_field( (string) $manifest['requires_php'] ) : '8.0',
            'autoupdate'   => false,
        );
    }

    private static function manifest(): ?array {
        $cached = get_site_transient( self::CACHE_KEY );
        if ( is_array( $cached ) && self::valid_manifest( $cached ) ) {
            return $cached;
        }

        $release = wp_safe_remote_get(
            self::API_URL,
            array(
                'timeout'     => 10,
                'redirection' => 2,
                'headers'     => array(
                    'Accept'               => 'application/vnd.github+json',
                    'User-Agent'           => 'h2o-IA-Site-Migrator/' . H2OSM_VERSION,
                    'X-GitHub-Api-Version' => '2022-11-28',
                ),
            )
        );

        if ( is_wp_error( $release ) || 200 !== (int) wp_remote_retrieve_response_code( $release ) ) {
            return null;
        }

        $payload = json_decode( wp_remote_retrieve_body( $release ), true );
        if ( ! is_array( $payload ) || empty( $payload['assets'] ) || ! is_array( $payload['assets'] ) ) {
            return null;
        }

        $manifest_url = '';
        foreach ( $payload['assets'] as $asset ) {
            if ( ! is_array( $asset ) || 'release.json' !== ( $asset['name'] ?? '' ) ) {
                continue;
            }
            $manifest_url = isset( $asset['browser_download_url'] ) ? esc_url_raw( (string) $asset['browser_download_url'], array( 'https' ) ) : '';
            break;
        }

        if ( '' === $manifest_url ) {
            return null;
        }

        $response = wp_safe_remote_get(
            $manifest_url,
            array(
                'timeout'     => 10,
                'redirection' => 3,
                'headers'     => array(
                    'Accept'     => 'application/json',
                    'User-Agent' => 'h2o-IA-Site-Migrator/' . H2OSM_VERSION,
                ),
            )
        );

        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            return null;
        }

        $manifest = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $manifest ) || ! self::valid_manifest( $manifest ) ) {
            return null;
        }

        set_site_transient( self::CACHE_KEY, $manifest, 30 * MINUTE_IN_SECONDS );
        return $manifest;
    }

    private static function valid_manifest( array $manifest ): bool {
        $version = isset( $manifest['version'] ) ? sanitize_text_field( (string) $manifest['version'] ) : '';
        $package = isset( $manifest['package_url'] ) ? esc_url_raw( (string) $manifest['package_url'], array( 'https' ) ) : '';
        $sha256  = isset( $manifest['package_sha256'] ) ? strtolower( sanitize_text_field( (string) $manifest['package_sha256'] ) ) : '';

        if ( '' === $version || '' === $package || ! preg_match( '/^[a-f0-9]{64}$/', $sha256 ) ) {
            return false;
        }

        $host = wp_parse_url( $package, PHP_URL_HOST );
        return in_array( strtolower( (string) $host ), array( 'github.com', 'objects.githubusercontent.com' ), true );
    }

    public static function verify_download( $reply, string $package, $upgrader, array $hook_extra ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
        if ( false !== $reply ) {
            return $reply;
        }

        if ( empty( $hook_extra['plugin'] ) || plugin_basename( H2OSM_FILE ) !== $hook_extra['plugin'] ) {
            return false;
        }

        $verification = get_site_transient( self::VERIFY_KEY );
        if ( ! is_array( $verification ) || empty( $verification['package'] ) || empty( $verification['sha256'] ) ) {
            return new \WP_Error( 'h2osm_update_unverified', 'h2o IA Site Migrator: no hay datos de verificación para este paquete.' );
        }

        if ( ! hash_equals( (string) $verification['package'], $package ) ) {
            return new \WP_Error( 'h2osm_update_package_mismatch', 'h2o IA Site Migrator: la URL del paquete no coincide con el manifiesto verificado.' );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        $download = download_url( $package, 30 );
        if ( is_wp_error( $download ) ) {
            return $download;
        }

        $actual = hash_file( 'sha256', $download );
        if ( ! is_string( $actual ) || ! hash_equals( strtolower( (string) $verification['sha256'] ), strtolower( $actual ) ) ) {
            wp_delete_file( $download );
            return new \WP_Error( 'h2osm_update_checksum_mismatch', 'h2o IA Site Migrator: el SHA-256 del paquete no coincide.' );
        }

        return $download;
    }

    public static function row_meta( array $links, string $file ): array {
        if ( plugin_basename( H2OSM_FILE ) !== $file ) {
            return $links;
        }
        $links[] = '<a href="' . esc_url( self::UPDATE_URI ) . '" target="_blank" rel="noopener">GitHub</a>';
        return $links;
    }
}
