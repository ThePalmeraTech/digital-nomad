<?php
/**
 * Plugin Download Page Template
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$token  = get_query_var( 'download_token' );
$result = PalmeritaDownloadManager::process_download( $token );
get_header();
?>
<div class="palmerita-download-page">
    <div class="container">
        <?php if ( isset( $result['error'] ) ) : ?>
            <div class="download-error">
                <div class="error-icon">⚠️</div>
                <h1><?php _e( 'Download Not Available', 'palmerita-subscriptions' ); ?></h1>
                <p><?php echo esc_html( $result['error'] ); ?></p>
                <div class="error-actions">
                    <a href="<?php echo home_url(); ?>" class="btn btn-primary"><?php _e( 'Go to Homepage', 'palmerita-subscriptions' ); ?></a>
                </div>
            </div>
        <?php else : ?>
            <div class="download-success">
                <div class="success-icon">🛠️</div>
                <h1><?php _e( 'Plugin Download Ready!', 'palmerita-subscriptions' ); ?></h1>
                <p><?php _e( 'Thank you for trying Digital Nomad Subscriptions. Click below to download the ZIP package.', 'palmerita-subscriptions' ); ?></p>
                <a href="<?php echo esc_url( PALMERITA_SUBS_PLUGIN_URL . 'assets/zip/palmerita-subscriptions.zip' ); ?>" class="download-btn" download="palmerita-subscriptions.zip">⬇️ <?php _e( 'Download Plugin', 'palmerita-subscriptions' ); ?></a>
                <div class="download-stats">
                    <small>
                    <?php
                    $remaining = $result['download']->max_downloads - $result['download']->downloads;
                    printf( __( 'Downloads remaining: %d of %d', 'palmerita-subscriptions' ), $remaining, $result['download']->max_downloads );
                    ?>
                    </small>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<style>
.palmerita-download-page{min-height:70vh;padding:40px 20px;background:#f3f4f6}.container{max-width:640px;margin:0 auto}.download-success,.download-error{background:#fff;border-radius:12px;padding:40px;text-align:center;box-shadow:0 10px 30px rgba(0,0,0,.1)}.download-btn{display:inline-block;background:#111;color:#fff;padding:18px 36px;border-radius:8px;text-decoration:none;font-weight:700;transition:.3s all}.download-btn:hover{background:#000}.btn{display:inline-block;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:700}.btn-primary{background:#007bff;color:#fff}.btn-primary:hover{background:#0056b3;color:#fff}
</style>
<?php get_footer(); ?> 