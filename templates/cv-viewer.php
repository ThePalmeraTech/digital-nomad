<?php
/**
 * CV Viewer Page Template
 * Professional PDF viewer integrated with WordPress
 */

if (!defined('ABSPATH')) {
    exit;
}

$token = get_query_var('viewer_token');
$is_preview = ($token === 'preview');

if (!$is_preview) {
    $result = PalmeritaDownloadManager::process_download($token);
    if (isset($result['error'])) {
        // Redirect to error page or show error
        wp_redirect(home_url('/palmerita-download/' . $token));
        exit;
    }
}

// Check if CV file exists
$cv_file_name = get_option('palmerita_cv_custom_name', 'My-CV.pdf');
$cv_file_path = PALMERITA_SUBS_PLUGIN_DIR . 'assets/cv/' . $cv_file_name;
$cv_file_url = PALMERITA_SUBS_PLUGIN_URL . 'assets/cv/' . $cv_file_name;

if (!file_exists($cv_file_path)) {
    wp_die('CV file not found. Please contact the administrator.');
}

get_header();
?>

<div class="palmerita-cv-viewer">
    <div class="viewer-header">
        <div class="profile-section">
            <h1>Hanaley Palma - CV</h1>
            <p>Senior Full-Stack Developer & WordPress Specialist</p>
        </div>
        
        <div class="actions-section">
            <a href="<?php echo $cv_file_url; ?>" download="<?php echo esc_attr($cv_file_name); ?>" class="download-btn">
                📄 Download PDF
            </a>
        </div>
    </div>

    <div class="pdf-viewer-container">
        <iframe src="<?php echo $cv_file_url; ?>#toolbar=1&navpanes=0&scrollbar=1" 
                width="100%" 
                height="800px"
                frameborder="0">
            <p>Your browser does not support PDF viewing. 
               <a href="<?php echo $cv_file_url; ?>" download>Download the CV directly</a>.
            </p>
        </iframe>
    </div>

    <div class="contact-section">
        <h2>Let's Connect!</h2>
        <p>Interested in working together? I'd love to discuss your project.</p>
        
        <div class="contact-links">
                    <a href="mailto:hana@palmeratech.net">📧 hana@palmeratech.net</a>
        <a href="https://palmeratech.net" target="_blank">🌐 palmeratech.net</a>
        </div>
    </div>
</div>

<style>
.palmerita-cv-viewer {
    max-width: 1200px;
    margin: 0 auto;
    background: white;
    box-shadow: 0 0 20px rgba(0,0,0,0.1);
    border-radius: 10px;
    overflow: hidden;
}

.viewer-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.profile-section h1 {
    margin: 0 0 5px;
    font-size: 2rem;
}

.profile-section p {
    margin: 0;
    opacity: 0.9;
}

.download-btn {
    background: rgba(255,255,255,0.2);
    color: white;
    padding: 12px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: bold;
    transition: all 0.3s ease;
}

.download-btn:hover {
    background: rgba(255,255,255,0.3);
    color: white;
    transform: translateY(-2px);
}

.pdf-viewer-container {
    padding: 20px;
    background: #f8f9fa;
}

.pdf-viewer-container iframe {
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    background: white;
}

.contact-section {
    background: #f8f9fa;
    padding: 30px;
    text-align: center;
    border-top: 1px solid #dee2e6;
}

.contact-section h2 {
    margin: 0 0 10px;
    color: #1e293b;
}

.contact-links {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin-top: 20px;
    flex-wrap: wrap;
}

.contact-links a {
    background: white;
    padding: 12px 20px;
    border-radius: 8px;
    text-decoration: none;
    color: #495057;
    border: 1px solid #dee2e6;
    transition: all 0.3s ease;
}

.contact-links a:hover {
    background: #e9ecef;
    color: #495057;
    transform: translateY(-2px);
}

@media (max-width: 768px) {
    .viewer-header {
        flex-direction: column;
        text-align: center;
        gap: 20px;
    }
    
    .pdf-viewer-container {
        padding: 10px;
    }
    
    .pdf-viewer-container iframe {
        height: 600px;
    }
    
    .contact-links {
        flex-direction: column;
        align-items: center;
    }
}
</style>

<?php get_footer(); ?> 