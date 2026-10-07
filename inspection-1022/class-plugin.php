<?php
namespace H2O\Site_Migrator;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Plugin {
    public static function boot(): void {
        Updater::boot();
        Funnels::register();

        add_action( 'init', array( Sync::class, 'maybe_sync' ), 30 );
        add_action( 'admin_menu', array( Admin::class, 'register' ) );
        add_action( 'admin_post_h2osm_resync', array( Admin::class, 'handle_resync' ) );
        add_action( 'template_redirect', array( self::class, 'redirect_legacy_alias' ), 1 );
        add_filter( 'wp_robots', array( self::class, 'robots' ) );
        add_filter( 'body_class', array( self::class, 'body_classes' ) );
    }

    public static function redirect_legacy_alias(): void {
        if ( is_admin() || wp_doing_ajax() ) {
            return;
        }

        $profile = Registry::current_profile();
        if ( ! $profile ) {
            return;
        }

        $request_path = wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
        $request_slug = trim( is_string( $request_path ) ? $request_path : '', '/' );
        if ( '' === $request_slug || false !== strpos( $request_slug, '/' ) ) {
            return;
        }

        foreach ( (array) ( $profile['pages'] ?? array() ) as $page ) {
            $canonical = sanitize_title( (string) ( $page['slug'] ?? '' ) );
            foreach ( (array) ( $page['aliases'] ?? array() ) as $alias ) {
                if ( $request_slug === sanitize_title( (string) $alias ) && '' !== $canonical ) {
                    wp_safe_redirect( home_url( '/' . $canonical . '/' ), 301 );
                    exit;
                }
            }
        }
    }

    public static function robots( array $robots ): array {
        if ( Sync::is_managed_page() ) {
            $robots['noindex'] = true;
            $robots['follow']  = true;
        }
        return $robots;
    }

    public static function body_classes( array $classes ): array {
        if ( Sync::is_managed_page() ) {
            $classes[] = 'h2osm-managed-page';
            $classes[] = 'h2osm-templa';
        }
        return $classes;
    }
}
