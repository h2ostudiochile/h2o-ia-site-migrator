<?php
namespace H2O\Site_Migrator;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Admin {
    public static function register(): void {
        add_management_page(
            'h2o Site Migrator',
            'h2o Site Migrator',
            'manage_options',
            'h2o-site-migrator',
            array( self::class, 'render' )
        );
    }

    public static function handle_resync(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No autorizado.', 'h2o-ia-site-migrator' ) );
        }
        check_admin_referer( 'h2osm_resync' );
        Sync::sync( true );
        wp_safe_redirect( admin_url( 'tools.php?page=h2o-site-migrator&synced=1' ) );
        exit;
    }

    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $profile = Registry::current_profile();
        $state   = Sync::state();
        ?>
        <div class="wrap">
            <h1>h2o IA Site Migrator</h1>
            <p><strong>Release:</strong> <?php echo esc_html( H2OSM_VERSION ); ?></p>
            <?php if ( ! $profile ) : ?>
                <div class="notice notice-warning"><p>No existe un perfil de migración para este dominio. No se ha modificado contenido.</p></div>
            <?php else : ?>
                <p><strong>Perfil:</strong> <?php echo esc_html( (string) ( $profile['label'] ?? $profile['key'] ?? '' ) ); ?> · versión <?php echo esc_html( (string) ( $profile['version'] ?? '' ) ); ?></p>
                <p>La portada configurada en WordPress no se modifica. Las páginas de migración se administran por slug público y se mantienen en <code>noindex</code> durante la etapa de prueba.</p>
                <table class="widefat striped" style="max-width:980px">
                    <thead><tr><th>Página</th><th>Slug</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ( (array) ( $profile['pages'] ?? array() ) as $page ) :
                        $slug = sanitize_title( (string) $page['slug'] );
                        $post = get_page_by_path( $slug, OBJECT, 'page' );
                        ?>
                        <tr>
                            <td><?php echo esc_html( (string) $page['title'] ); ?></td>
                            <td><code>/<?php echo esc_html( $slug ); ?>/</code></td>
                            <td><?php echo $post ? esc_html( $post->post_status ) : 'pendiente'; ?></td>
                            <td>
                                <?php if ( $post ) : ?>
                                    <a href="<?php echo esc_url( get_permalink( $post ) ); ?>" target="_blank" rel="noopener">Ver</a>
                                    &nbsp;·&nbsp;
                                    <a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>">Editar</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p style="margin-top:18px">
                    <a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=h2osm_resync' ), 'h2osm_resync' ) ); ?>">Aplicar release nuevamente</a>
                </p>
                <p><small>Última sincronización: <?php echo esc_html( (string) ( $state['synced_at'] ?? '—' ) ); ?></small></p>
            <?php endif; ?>
        </div>
        <?php
    }
}
