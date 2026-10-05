<?php
namespace H2O\Site_Migrator;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * GitHub-backed updater for h2o IA Site Migrator.
 *
 * The updater reads a small public manifest and base64 package chunks from the
 * component's own GitHub repository. It never reads another plugin's internals.
 */
final class Updater {
    private const UPDATE_URI  = 'https://github.com/h2ostudiochile/h2o-ia-site-migrator';
    private const MANIFEST_URL = 'https://raw.githubusercontent.com/h2ostudiochile/h2o-ia-site-migrator/main/bootstrap/release.json';
    private const CACHE_KEY   = 'h2osm_release_manifest';
    private const VERIFY_KEY  = 'h2osm_update_verification';
    private const MAX_PACKAGE_BYTES = 20 * 1024 * 1024;

    public static function boot(): void {
        add_filter( 'update_plugins_github.com', array( self::class, 'check' ), 10, 4 );
        add_filter( 'upgrader_pre_download', array( self::class, 'prepare_package' ), 10, 4 );
        add_filter( 'plugin_row_meta', array( self::class, 'row_meta' ), 10, 2 );
    }

    public static function check( $update, array $plugin_data, string $plugin_file, array $locales ) {
        if ( plugin_basename( H2OSM_FILE ) !== $plugin_file ) {
            return $update;
        }

        $manifest = self::manifest();
        if ( ! $manifest || empty( $manifest['version'] ) || version_compare( (string) $manifest['version'], H2OSM_VERSION, '<=' ) ) {
            return false;
        }

        set_site_transient( self::VERIFY_KEY, $manifest, 12 * HOUR_IN_SECONDS );

        return array(
            'id'           => self::UPDATE_URI,
            'slug'         => 'h2o-ia-site-migrator',
            'version'      => (string) $manifest['version'],
            'url'          => self::UPDATE_URI,
            'package'      => (string) $manifest['package_id'],
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

        $response = wp_safe_remote_get(
            self::MANIFEST_URL,
            array(
                'timeout'     => 10,
                'redirection' => 2,
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

        set_site_transient( self::CACHE_KEY, $manifest, 15 * MINUTE_IN_SECONDS );
        return $manifest;
    }

    private static function valid_manifest( array $manifest ): bool {
        $version    = isset( $manifest['version'] ) ? sanitize_text_field( (string) $manifest['version'] ) : '';
        $package_id = isset( $manifest['package_id'] ) ? esc_url_raw( (string) $manifest['package_id'], array( 'https' ) ) : '';
        $sha256     = isset( $manifest['package_sha256'] ) ? strtolower( sanitize_text_field( (string) $manifest['package_sha256'] ) ) : '';
        $parts      = isset( $manifest['parts'] ) && is_array( $manifest['parts'] ) ? $manifest['parts'] : array();

        if ( '' === $version || '' === $package_id || ! preg_match( '/^[a-f0-9]{64}$/', $sha256 ) || empty( $parts ) || count( $parts ) > 64 ) {
            return false;
        }

        $package_host = strtolower( (string) wp_parse_url( $package_id, PHP_URL_HOST ) );
        if ( 'raw.githubusercontent.com' !== $package_host ) {
            return false;
        }

        foreach ( $parts as $part ) {
            if ( ! is_string( $part ) || '' === $part ) {
                return false;
            }
            $part_url = esc_url_raw( $part, array( 'https' ) );
            if ( '' === $part_url || 'raw.githubusercontent.com' !== strtolower( (string) wp_parse_url( $part_url, PHP_URL_HOST ) ) ) {
                return false;
            }
        }

        return true;
    }

    public static function prepare_package( $reply, string $package, $upgrader, array $hook_extra ) {
        if ( false !== $reply ) {
            return $reply;
        }

        if ( empty( $hook_extra['plugin'] ) || plugin_basename( H2OSM_FILE ) !== $hook_extra['plugin'] ) {
            return false;
        }

        $manifest = get_site_transient( self::VERIFY_KEY );
        if ( ! is_array( $manifest ) || ! self::valid_manifest( $manifest ) ) {
            $manifest = self::manifest();
        }

        if ( ! is_array( $manifest ) || ! self::valid_manifest( $manifest ) ) {
            return new \WP_Error( 'h2osm_update_unverified', 'h2o IA Site Migrator: no hay un manifiesto de actualización verificable.' );
        }

        if ( ! hash_equals( (string) $manifest['package_id'], $package ) ) {
            return new \WP_Error( 'h2osm_update_package_mismatch', 'h2o IA Site Migrator: el identificador del paquete no coincide con el manifiesto.' );
        }

        $encoded = '';
        foreach ( $manifest['parts'] as $part_url ) {
            $response = wp_safe_remote_get(
                (string) $part_url,
                array(
                    'timeout'     => 15,
                    'redirection' => 2,
                    'headers'     => array(
                        'Accept'     => 'text/plain',
                        'User-Agent' => 'h2o-IA-Site-Migrator/' . H2OSM_VERSION,
                    ),
                )
            );

            if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
                return new \WP_Error( 'h2osm_update_part_unavailable', 'h2o IA Site Migrator: no fue posible descargar una parte del paquete.' );
            }

            $encoded .= preg_replace( '/\s+/', '', (string) wp_remote_retrieve_body( $response ) );
            if ( strlen( $encoded ) > ( self::MAX_PACKAGE_BYTES * 2 ) ) {
                return new \WP_Error( 'h2osm_update_too_large', 'h2o IA Site Migrator: el paquete excede el tamaño permitido.' );
            }
        }

        $binary = base64_decode( $encoded, true );
        if ( false === $binary || strlen( $binary ) > self::MAX_PACKAGE_BYTES ) {
            return new \WP_Error( 'h2osm_update_decode_failed', 'h2o IA Site Migrator: el paquete publicado no pudo decodificarse.' );
        }

        $actual = hash( 'sha256', $binary );
        if ( ! hash_equals( strtolower( (string) $manifest['package_sha256'] ), strtolower( $actual ) ) ) {
            return new \WP_Error( 'h2osm_update_checksum_mismatch', 'h2o IA Site Migrator: el SHA-256 del paquete no coincide.' );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        $tmp = wp_tempnam( 'h2o-ia-site-migrator-' . sanitize_file_name( (string) $manifest['version'] ) . '.zip' );
        if ( ! $tmp ) {
            return new \WP_Error( 'h2osm_update_temp_failed', 'h2o IA Site Migrator: no fue posible crear el archivo temporal.' );
        }

        if ( false === file_put_contents( $tmp, $binary ) ) {
            wp_delete_file( $tmp );
            return new \WP_Error( 'h2osm_update_write_failed', 'h2o IA Site Migrator: no fue posible preparar el paquete temporal.' );
        }

        return $tmp;
    }

    public static function row_meta( array $links, string $file ): array {
        if ( plugin_basename( H2OSM_FILE ) !== $file ) {
            return $links;
        }
        $links[] = '<a href="' . esc_url( self::UPDATE_URI ) . '" target="_blank" rel="noopener">GitHub</a>';
        return $links;
    }
}
