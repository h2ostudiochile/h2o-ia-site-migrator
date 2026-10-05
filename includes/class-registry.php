<?php
namespace H2O\Site_Migrator;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Registry {
    public static function current_profile(): ?array {
        $host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
        $host = is_string( $host ) ? strtolower( preg_replace( '/^www\./', '', $host ) ) : '';

        $profiles = array(
            'templadesign.cl' => H2OSM_DIR . 'profiles/templa-design.php',
        );

        if ( ! isset( $profiles[ $host ] ) || ! file_exists( $profiles[ $host ] ) ) {
            return null;
        }

        $profile = require $profiles[ $host ];
        return is_array( $profile ) ? $profile : null;
    }
}
