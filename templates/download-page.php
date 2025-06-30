<?php
/**
 * CV Download Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$token = get_query_var('download_token');
$result = PalmeritaDownloadManager::process_download($token);

get_header();
?>

<div class="palmerita-download-page">
    <div class="container">
        <?php if (isset($result['error'])): ?>
            <!-- Error State -->
            <div class="download-error">
                <div class="error-icon">⚠️</div>
                <h1><?php _e('Download Not Available', 'palmerita-subscriptions'); ?></h1>
                <p><?php echo esc_html($result['error']); ?></p>
                
                <div class="error-actions">
                    <a href="<?php echo home_url(); ?>" class="btn btn-primary">
                        <?php _e('Go to Homepage', 'palmerita-subscriptions'); ?>
                    </a>
                    <a href="<?php echo home_url('#contact'); ?>" class="btn btn-secondary">
                        <?php _e('Request New Link', 'palmerita-subscriptions'); ?>
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Success State -->
            <div class="download-success">
                <div class="success-icon">🎯</div>
                <h1><?php _e('CV Download Ready!', 'palmerita-subscriptions'); ?></h1>
                <p><?php _e('Thank you for your interest in my professional profile.', 'palmerita-subscriptions'); ?></p>
                
                <div class="download-info">
                    <div class="info-card">
                        <h3><?php _e('Hanaley Palma', 'palmerita-subscriptions'); ?></h3>
                        <p><?php _e('Senior Full-Stack Developer & WordPress Specialist', 'palmerita-subscriptions'); ?></p>
                        <p><?php _e('Specializing in WordPress, React, and Modern Web Technologies', 'palmerita-subscriptions'); ?></p>
                    </div>
                    
                    <div class="download-action">
                        <a href="<?php echo PALMERITA_SUBS_PLUGIN_URL . 'assets/cv/Hanaley-Palma-CV.pdf'; ?>" 
                           class="download-btn" 
                           download="Hanaley-Palma-CV.pdf">
                            📄 <?php _e('Download CV Now', 'palmerita-subscriptions'); ?>
                        </a>
                        
                        <div class="download-stats">
                            <small>
                                <?php 
                                $remaining = $result['download']->max_downloads - $result['download']->downloads;
                                printf(
                                    __('Downloads remaining: %d of %d', 'palmerita-subscriptions'), 
                                    $remaining, 
                                    $result['download']->max_downloads
                                ); 
                                ?>
                            </small>
                        </div>
                    </div>
                </div>
                
                <div class="contact-info">
                    <h3><?php _e('Let\'s Connect!', 'palmerita-subscriptions'); ?></h3>
                    <p><?php _e('I\'d love to discuss how I can help bring your project to life.', 'palmerita-subscriptions'); ?></p>
                    
                    <div class="contact-links">
                        <a href="mailto:hana@palmeratech.net" class="contact-link">
                            📧 hana@palmeratech.net
                        </a>
                        <a href="https://palmeratech.net" class="contact-link" target="_blank">
                            🌐 palmeratech.net
                        </a>
                        <a href="https://linkedin.com/in/hanaley-palma" class="contact-link" target="_blank">
                            💼 LinkedIn Profile
                        </a>
                    </div>
                </div>
                
                <div class="portfolio-preview">
                    <h3><?php _e('Recent Work Highlights', 'palmerita-subscriptions'); ?></h3>
                    <div class="work-grid">
                        <div class="work-item">
                            <h4>Custom WordPress Solutions</h4>
                            <p><?php _e('Full-Stack Developer - Custom plugin development and advanced WordPress integrations', 'palmerita-subscriptions'); ?></p>
                        </div>
                        <div class="work-item">
                            <h4>Embassy Digital Solutions</h4>
                            <p><?php _e('WordPress Developer & Product Designer - Government sector projects', 'palmerita-subscriptions'); ?></p>
                        </div>
                        <div class="work-item">
                            <h4>Live & Invest Overseas</h4>
                            <p><?php _e('Web Product Specialist - International real estate platform', 'palmerita-subscriptions'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.palmerita-download-page {
    min-height: 80vh;
    padding: 40px 20px;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
}

.container {
    max-width: 800px;
    margin: 0 auto;
}

.download-error, .download-success {
    background: white;
    border-radius: 15px;
    padding: 40px;
    text-align: center;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.error-icon, .success-icon {
    font-size: 4rem;
    margin-bottom: 20px;
}

.download-error h1 {
    color: #dc3545;
    margin-bottom: 20px;
}

.download-success h1 {
    color: #28a745;
    margin-bottom: 20px;
}

.error-actions, .download-action {
    margin: 30px 0;
}

.btn {
    display: inline-block;
    padding: 12px 24px;
    margin: 0 10px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: bold;
    transition: all 0.3s ease;
}

.btn-primary {
    background: #007bff;
    color: white;
}

.btn-primary:hover {
    background: #0056b3;
    color: white;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #545b62;
    color: white;
}

.download-btn {
    display: inline-block;
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
    padding: 20px 40px;
    border-radius: 10px;
    text-decoration: none;
    font-size: 1.2rem;
    font-weight: bold;
    transition: all 0.3s ease;
    box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
}

.download-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(40, 167, 69, 0.4);
    color: white;
}

.info-card {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 30px;
    margin: 30px 0;
    border-left: 4px solid #007bff;
}

.info-card h3 {
    margin: 0 0 10px;
    color: #1e293b;
}

.download-stats {
    margin-top: 15px;
}

.download-stats small {
    color: #6c757d;
    background: #f8f9fa;
    padding: 5px 10px;
    border-radius: 15px;
}

.contact-info {
    margin: 40px 0;
    text-align: left;
}

.contact-links {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-top: 20px;
}

.contact-link {
    display: inline-block;
    background: #f8f9fa;
    padding: 10px 15px;
    border-radius: 8px;
    text-decoration: none;
    color: #495057;
    border: 1px solid #dee2e6;
    transition: all 0.3s ease;
}

.contact-link:hover {
    background: #e9ecef;
    border-color: #adb5bd;
    color: #495057;
}

.portfolio-preview {
    margin: 40px 0;
    text-align: left;
}

.work-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.work-item {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    border-left: 3px solid #6366f1;
}

.work-item h4 {
    margin: 0 0 10px;
    color: #1e293b;
}

.work-item p {
    margin: 0;
    color: #64748b;
    font-size: 0.9rem;
}

@media (max-width: 768px) {
    .palmerita-download-page {
        padding: 20px 10px;
    }
    
    .download-error, .download-success {
        padding: 30px 20px;
    }
    
    .contact-links {
        flex-direction: column;
    }
    
    .work-grid {
        grid-template-columns: 1fr;
    }
    
    .download-btn {
        padding: 15px 30px;
        font-size: 1.1rem;
    }
}
</style>

<?php get_footer(); ?> 