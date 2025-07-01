<?php
/**
 * Plugin File Download Page Template
 *
 * Displays the download button for the file uploaded via the File Manager.
 * The file URL is retrieved from the `palmerita_file_zip_url` option. If that
 * option is empty a built-in fallback ZIP is used so the page never breaks.
 *
 * @package Palmerita-Subscriptions
 */

// Security
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$token = get_query_var( 'download_token' );
$result = PalmeritaDownloadManager::process_download( $token );

// Get the final file URL stored by the File Manager (fallback to default ZIP)
$file_url  = get_option( 'palmerita_file_zip_url' );
$upload_dir = wp_upload_dir();
$files_url  = trailingslashit( $upload_dir['baseurl'] ) . 'files';

if ( ! $file_url ) {
    $file_url = PALMERITA_SUBS_PLUGIN_URL . 'assets/zip/palmerita-subscriptions.zip';
}

$file_name = basename( parse_url( $file_url, PHP_URL_PATH ) );

get_header();
?>

<div class="palmerita-download-page" style="min-height:80vh;padding:40px 20px;display:flex;align-items:center;justify-content:center;">
    <div style="max-width:600px;width:100%;text-align:center;background:#fff;border-radius:12px;padding:40px;box-shadow:0 10px 25px rgba(0,0,0,.08);">
        <?php if ( isset( $result['error'] ) ) : ?>
            <h1 style="color:#dc3545;margin-bottom:20px;">⚠️ <?php _e( 'Download Not Available', 'palmerita-subscriptions' ); ?></h1>
            <p><?php echo esc_html( $result['error'] ); ?></p>
            <a href="<?php echo esc_url( home_url() ); ?>" class="button" style="margin-top:20px;">← <?php _e( 'Back to home', 'palmerita-subscriptions' ); ?></a>
        <?php else : ?>
            <h1 style="color:#0069d9;margin-bottom:15px;">📦 <?php _e( 'Plugin Download Ready!', 'palmerita-subscriptions' ); ?></h1>
            <p><?php _e( 'Thank you for using Digital Nomad Subscriptions. Click the button below to download the ZIP package.', 'palmerita-subscriptions' ); ?></p>
            <a href="<?php echo esc_url( $file_url ); ?>" class="button button-primary" style="padding:14px 28px;font-size:18px;margin-top:25px;" download="<?php echo esc_attr( $file_name ); ?>">⬇️ <?php _e( 'Download Plugin', 'palmerita-subscriptions' ); ?></a>
            <p style="margin-top:15px;font-size:14px;color:#666;">
                <?php
                $remaining = $result['download']->max_downloads - $result['download']->downloads;
                printf( __( 'Downloads remaining: %d of %d', 'palmerita-subscriptions' ), $remaining, $result['download']->max_downloads );
                ?>
            </p>
        <?php endif; ?>
    </div>
</div>

<?php get_footer();