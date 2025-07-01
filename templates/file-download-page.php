<?php
/**
 * File Download Page Template
 *
 * This template is displayed after a user successfully requests a download link
 * for a file and clicks the link in their email.
 *
 * @package Palmerita-Subscriptions
 */

// Basic security check
if (!defined('ABSPATH')) {
    exit;
}

// Get the token from the URL
$token = get_query_var('download_token');
if (empty($token)) {
    wp_die(__('Invalid download link.', 'palmerita-subscriptions'));
}

// It's assumed the click was already tracked and validated by the rewrite rule handler.
// This page is just for presenting the download button.

// Get the file URL from the File Manager settings
$file_url = get_option('palmerita_file_zip_url', PALMERITA_SUBS_PLUGIN_URL . 'assets/zip/palmerita-subscriptions.zip');
$file_name = basename(parse_url($file_url, PHP_URL_PATH));
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class('palmerita-download-page'); ?>>
    <div class="download-container">
        <h1><?php _e('Your Download is Ready!', 'palmerita-subscriptions'); ?></h1>
        <p><?php _e('Thank you for your interest. Click the button below to download the file.', 'palmerita-subscriptions'); ?></p>
        <a href="<?php echo esc_url($file_url); ?>" class="download-btn" download="<?php echo esc_attr($file_name); ?>">⬇️ <?php _e('Download File', 'palmerita-subscriptions'); ?></a>
    </div>
    <?php wp_footer(); ?>
</body>
</html> 