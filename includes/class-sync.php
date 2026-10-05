<?php
namespace H2O\Site_Migrator;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Sync {
    private const STATE_OPTION = 'h2o_site_migrator_state';

    public static function activate(): void {
        self::sync( true );
    }

    public static function maybe_sync(): void {
        $profile = Registry::current_profile();
        if ( ! $profile ) {
            return;
        }

        $state = self::state();
        $key   = isset( $profile['key'] ) ? (string) $profile['key'] : '';
        $pv    = isset( $profile['version'] ) ? (string) $profile['version'] : '';

        if (
            ( $state['plugin_version'] ?? '' ) !== H2OSM_VERSION ||
            ( $state['profile_key'] ?? '' ) !== $key ||
            ( $state['profile_version'] ?? '' ) !== $pv ||
            ! self::profile_is_current( $profile )
        ) {
            self::sync( false );
        }
    }

    public static function sync( bool $force = false ): array {
        $profile = Registry::current_profile();
        if ( ! $profile ) {
            return array( 'ok' => false, 'reason' => 'no_profile', 'pages' => array() );
        }

        $pages   = isset( $profile['pages'] ) && is_array( $profile['pages'] ) ? $profile['pages'] : array();
        $managed = array();
        $results = array();

        foreach ( $pages as $page ) {
            if ( empty( $page['slug'] ) || empty( $page['title'] ) || ! isset( $page['content'] ) ) {
                continue;
            }

            $slug    = sanitize_title( (string) $page['slug'] );
            $title   = sanitize_text_field( (string) $page['title'] );
            $aliases = isset( $page['aliases'] ) && is_array( $page['aliases'] ) ? $page['aliases'] : array();
            $post    = self::find_page( $slug, $aliases, $title );

            $postarr = array(
                'post_type'      => 'page',
                'post_title'     => $title,
                'post_name'      => $slug,
                'post_content'   => (string) $page['content'],
                'post_status'    => 'publish',
                'comment_status' => 'closed',
                'ping_status'    => 'closed',
            );

            if ( $post instanceof \WP_Post ) {
                $postarr['ID'] = $post->ID;
                $post_id = wp_update_post( wp_slash( $postarr ), true );
                $action = 'updated';
            } else {
                $post_id = wp_insert_post( wp_slash( $postarr ), true );
                $action = 'created';
            }

            if ( is_wp_error( $post_id ) ) {
                $results[] = array(
                    'slug'   => $slug,
                    'ok'     => false,
                    'reason' => $post_id->get_error_message(),
                );
                continue;
            }

            $post_id = (int) $post_id;
            clean_post_cache( $post_id );
            $stored = get_post( $post_id );
            $stored_slug = $stored instanceof \WP_Post ? (string) $stored->post_name : $slug;

            $managed[ $slug ] = $post_id;
            $results[] = array(
                'slug'        => $slug,
                'stored_slug' => $stored_slug,
                'ok'          => true,
                'action'      => $action,
                'id'          => $post_id,
                'url'         => get_permalink( $post_id ),
            );
        }

        $state = array(
            'plugin_version'  => H2OSM_VERSION,
            'profile_key'     => (string) ( $profile['key'] ?? '' ),
            'profile_version' => (string) ( $profile['version'] ?? '' ),
            'managed_pages'   => $managed,
            'synced_at'       => gmdate( 'c' ),
        );
        update_option( self::STATE_OPTION, $state, false );

        return array( 'ok' => true, 'pages' => $results, 'state' => $state );
    }

    private static function profile_is_current( array $profile ): bool {
        $pages = isset( $profile['pages'] ) && is_array( $profile['pages'] ) ? $profile['pages'] : array();
        $key   = (string) ( $profile['key'] ?? '' );
        $pv    = (string) ( $profile['version'] ?? '' );
        $marker = 'h2o-site-migrator:' . $key . ':' . $pv;

        foreach ( $pages as $page ) {
            $slug = sanitize_title( (string) ( $page['slug'] ?? '' ) );
            if ( '' === $slug ) {
                continue;
            }

            $post = get_page_by_path( $slug, OBJECT, 'page' );
            if ( ! $post instanceof \WP_Post || 'publish' !== $post->post_status ) {
                return false;
            }

            if ( false === strpos( (string) $post->post_content, $marker ) ) {
                return false;
            }
        }

        return true;
    }

    private static function find_page( string $slug, array $aliases, string $title = '' ): ?\WP_Post {
        $page = get_page_by_path( $slug, OBJECT, 'page' );
        if ( $page instanceof \WP_Post ) {
            return $page;
        }

        foreach ( $aliases as $alias ) {
            $alias = sanitize_title( (string) $alias );
            if ( '' === $alias ) {
                continue;
            }
            $candidate = get_page_by_path( $alias, OBJECT, 'page' );
            if ( $candidate instanceof \WP_Post ) {
                return $candidate;
            }
        }

        if ( '' !== $title ) {
            $candidates = get_posts(
                array(
                    'post_type'              => 'page',
                    'post_status'            => array( 'publish', 'draft', 'private', 'pending', 'future' ),
                    'posts_per_page'         => 20,
                    'orderby'                => 'ID',
                    'order'                  => 'ASC',
                    'suppress_filters'       => true,
                    'no_found_rows'          => true,
                    'update_post_meta_cache' => false,
                    'update_post_term_cache' => false,
                )
            );

            foreach ( $candidates as $candidate ) {
                if ( $candidate instanceof \WP_Post && 0 === strcasecmp( trim( $candidate->post_title ), trim( $title ) ) ) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    public static function state(): array {
        $state = get_option( self::STATE_OPTION, array() );
        return is_array( $state ) ? $state : array();
    }

    public static function managed_page_ids(): array {
        $state = self::state();
        $ids = isset( $state['managed_pages'] ) && is_array( $state['managed_pages'] ) ? array_values( $state['managed_pages'] ) : array();
        $ids = array_values( array_filter( array_map( 'absint', $ids ) ) );

        if ( ! empty( $ids ) ) {
            return $ids;
        }

        $profile = Registry::current_profile();
        if ( ! $profile ) {
            return array();
        }

        foreach ( (array) ( $profile['pages'] ?? array() ) as $page ) {
            $slug = sanitize_title( (string) ( $page['slug'] ?? '' ) );
            if ( '' === $slug ) {
                continue;
            }
            $post = get_page_by_path( $slug, OBJECT, 'page' );
            if ( $post instanceof \WP_Post ) {
                $ids[] = (int) $post->ID;
            }
        }

        return array_values( array_unique( array_filter( $ids ) ) );
    }

    public static function is_managed_page( ?int $post_id = null ): bool {
        $post_id = $post_id ?: get_queried_object_id();
        if ( $post_id <= 0 ) {
            return false;
        }

        if ( in_array( $post_id, self::managed_page_ids(), true ) ) {
            return true;
        }

        $post = get_post( $post_id );
        if ( ! $post instanceof \WP_Post || 'page' !== $post->post_type ) {
            return false;
        }

        $profile = Registry::current_profile();
        if ( ! $profile ) {
            return false;
        }

        $key = (string) ( $profile['key'] ?? '' );
        if ( '' !== $key && false !== strpos( (string) $post->post_content, 'h2o-site-migrator:' . $key . ':' ) ) {
            return true;
        }

        $post_slug = sanitize_title( (string) $post->post_name );
        foreach ( (array) ( $profile['pages'] ?? array() ) as $page ) {
            $slug = sanitize_title( (string) ( $page['slug'] ?? '' ) );
            if ( $post_slug === $slug ) {
                return true;
            }
            foreach ( (array) ( $page['aliases'] ?? array() ) as $alias ) {
                if ( $post_slug === sanitize_title( (string) $alias ) ) {
                    return true;
                }
            }
        }

        return false;
    }
}
