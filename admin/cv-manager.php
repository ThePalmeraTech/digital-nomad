<?php
/**
 * CV Manager Page
 * Upload and manage CV files
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Handle file upload
if (isset($_POST['upload_cv']) && wp_verify_nonce($_POST['_wpnonce'], 'upload_cv')) {
    if (!empty($_FILES['cv_file']['name'])) {
        $upload_dir = PALMERITA_SUBS_PLUGIN_DIR . 'assets/cv/';
        $file_name = 'Hanaley-Palma-CV.pdf';
        $upload_path = $upload_dir . $file_name;
        
        // Validate file type
        $file_type = $_FILES['cv_file']['type'];
        if ($file_type !== 'application/pdf') {
            echo '<div class="notice notice-error"><p>Please upload a PDF file only.</p></div>';
        } else if ($_FILES['cv_file']['size'] > 10 * 1024 * 1024) {
            echo '<div class="notice notice-error"><p>File size must be less than 10MB.</p></div>';
        } else {
            if (file_exists($upload_path)) {
                unlink($upload_path);
            }
            
            if (move_uploaded_file($_FILES['cv_file']['tmp_name'], $upload_path)) {
                update_option('palmerita_cv_uploaded', current_time('mysql'));
                update_option('palmerita_cv_filename', $_FILES['cv_file']['name']);
                echo '<div class="notice notice-success"><p>CV uploaded successfully!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Error uploading file.</p></div>';
            }
        }
    }
}

// Handle file deletion
if (isset($_POST['delete_cv']) && wp_verify_nonce($_POST['_wpnonce'], 'delete_cv')) {
    $upload_path = PALMERITA_SUBS_PLUGIN_DIR . 'assets/cv/Hanaley-Palma-CV.pdf';
    
    if (file_exists($upload_path)) {
        unlink($upload_path);
        delete_option('palmerita_cv_uploaded');
        delete_option('palmerita_cv_filename');
        delete_option('palmerita_cv_filesize');
        
        echo '<div class="notice notice-success"><p>' . __('CV deleted successfully!', 'palmerita-subscriptions') . '</p></div>';
    }
}

// Check if CV exists
$cv_path = PALMERITA_SUBS_PLUGIN_DIR . 'assets/cv/Hanaley-Palma-CV.pdf';
$cv_exists = file_exists($cv_path);
$cv_uploaded = get_option('palmerita_cv_uploaded', '');
$cv_filename = get_option('palmerita_cv_filename', '');
$cv_filesize = get_option('palmerita_cv_filesize', 0);
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-media-document"></span>
        <?php _e('CV Manager', 'palmerita-subscriptions'); ?>
    </h1>
    
    <a href="<?php echo admin_url('admin.php?page=palmerita-subscriptions'); ?>" class="page-title-action">
        <?php _e('← Back to Dashboard', 'palmerita-subscriptions'); ?>
    </a>
    
    <hr class="wp-header-end">

    <div class="palmerita-cv-manager">
        <?php if ($cv_exists): ?>
            <!-- CV Exists - Show Info and Actions -->
            <div class="cv-status-card success">
                <div class="status-icon">✅</div>
                <div class="status-content">
                    <h2><?php _e('CV Ready for Download', 'palmerita-subscriptions'); ?></h2>
                    <p><?php _e('Your CV is uploaded and ready to be shared with visitors.', 'palmerita-subscriptions'); ?></p>
                    
                    <div class="cv-details">
                        <div class="detail-item">
                            <strong><?php _e('Original Filename:', 'palmerita-subscriptions'); ?></strong>
                            <span><?php echo esc_html($cv_filename); ?></span>
                        </div>
                        <div class="detail-item">
                            <strong><?php _e('File Size:', 'palmerita-subscriptions'); ?></strong>
                            <span><?php echo size_format($cv_filesize); ?></span>
                        </div>
                        <div class="detail-item">
                            <strong><?php _e('Uploaded:', 'palmerita-subscriptions'); ?></strong>
                            <span><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($cv_uploaded)); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="cv-actions">
                <div class="action-group">
                    <h3><?php _e('Preview & Test', 'palmerita-subscriptions'); ?></h3>
                    <div class="action-buttons">
                        <a href="<?php echo PALMERITA_SUBS_PLUGIN_URL . 'assets/cv/Hanaley-Palma-CV.pdf'; ?>" 
                           target="_blank" 
                           class="button button-primary">
                            <span class="dashicons dashicons-visibility"></span>
                            <?php _e('Preview CV', 'palmerita-subscriptions'); ?>
                        </a>
                        
                        <a href="<?php echo site_url('/palmerita-cv-viewer/preview'); ?>" 
                           target="_blank" 
                           class="button button-secondary">
                            <span class="dashicons dashicons-laptop"></span>
                            <?php _e('Test Viewer Page', 'palmerita-subscriptions'); ?>
                        </a>
                    </div>
                </div>
                
                <div class="action-group">
                    <h3><?php _e('Download Links', 'palmerita-subscriptions'); ?></h3>
                    <div class="link-examples">
                        <div class="link-item">
                            <strong><?php _e('Direct Download:', 'palmerita-subscriptions'); ?></strong>
                            <code><?php echo PALMERITA_SUBS_PLUGIN_URL . 'assets/cv/Hanaley-Palma-CV.pdf'; ?></code>
                        </div>
                        <div class="link-item">
                            <strong><?php _e('Viewer Page:', 'palmerita-subscriptions'); ?></strong>
                            <code><?php echo site_url('/palmerita-cv-viewer/{token}'); ?></code>
                        </div>
                    </div>
                </div>
                
                <div class="action-group danger">
                    <h3><?php _e('Replace or Delete', 'palmerita-subscriptions'); ?></h3>
                    <p class="description"><?php _e('Upload a new CV to replace the current one, or delete it completely.', 'palmerita-subscriptions'); ?></p>
                </div>
            </div>
            
        <?php else: ?>
            <!-- No CV - Show Upload Form -->
            <div class="cv-status-card warning">
                <div class="status-icon">⚠️</div>
                <div class="status-content">
                    <h2><?php _e('No CV Uploaded', 'palmerita-subscriptions'); ?></h2>
                    <p><?php _e('Upload your CV to start sharing it with visitors who request it.', 'palmerita-subscriptions'); ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Upload Form -->
        <div class="cv-upload-section">
            <h2><?php echo $cv_exists ? __('Replace CV', 'palmerita-subscriptions') : __('Upload CV', 'palmerita-subscriptions'); ?></h2>
            
            <form method="post" enctype="multipart/form-data" class="cv-upload-form">
                <?php wp_nonce_field('upload_cv'); ?>
                
                <div class="upload-area">
                    <div class="upload-icon">📄</div>
                    <div class="upload-content">
                        <h3><?php _e('Select your CV file', 'palmerita-subscriptions'); ?></h3>
                        <p><?php _e('Choose a PDF file (max 10MB)', 'palmerita-subscriptions'); ?></p>
                        
                        <input type="file" 
                               name="cv_file" 
                               id="cv_file" 
                               accept=".pdf" 
                               required 
                               class="cv-file-input">
                        
                        <label for="cv_file" class="cv-file-label">
                            <span class="dashicons dashicons-upload"></span>
                            <?php _e('Choose File', 'palmerita-subscriptions'); ?>
                        </label>
                        
                        <div class="file-info" style="display: none;">
                            <span class="file-name"></span>
                            <span class="file-size"></span>
                        </div>
                    </div>
                </div>
                
                <div class="upload-actions">
                    <input type="submit" 
                           name="upload_cv" 
                           class="button button-primary button-large" 
                           value="<?php echo $cv_exists ? __('Replace CV', 'palmerita-subscriptions') : __('Upload CV', 'palmerita-subscriptions'); ?>">
                    
                    <?php if ($cv_exists): ?>
                        <input type="submit" 
                               name="delete_cv" 
                               class="button button-link-delete" 
                               value="<?php _e('Delete Current CV', 'palmerita-subscriptions'); ?>"
                               onclick="return confirm('<?php _e('Are you sure you want to delete the current CV?', 'palmerita-subscriptions'); ?>')">
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Usage Instructions -->
        <div class="cv-instructions">
            <h2><?php _e('How it Works', 'palmerita-subscriptions'); ?></h2>
            
            <div class="instruction-grid">
                <div class="instruction-item">
                    <div class="instruction-number">1</div>
                    <h3><?php _e('Upload Your CV', 'palmerita-subscriptions'); ?></h3>
                    <p><?php _e('Upload your PDF CV file using the form above. It will be stored securely.', 'palmerita-subscriptions'); ?></p>
                </div>
                
                <div class="instruction-item">
                    <div class="instruction-number">2</div>
                    <h3><?php _e('Visitors Request CV', 'palmerita-subscriptions'); ?></h3>
                    <p><?php _e('When visitors click "Get my CV", they enter their email and receive a secure link.', 'palmerita-subscriptions'); ?></p>
                </div>
                
                <div class="instruction-item">
                    <div class="instruction-number">3</div>
                    <h3><?php _e('Secure Viewing', 'palmerita-subscriptions'); ?></h3>
                    <p><?php _e('The link opens a professional viewer page on your website with download option.', 'palmerita-subscriptions'); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.palmerita-cv-manager {
    max-width: 1000px;
}

.cv-status-card {
    display: flex;
    align-items: center;
    background: white;
    border-radius: 10px;
    padding: 30px;
    margin: 20px 0;
    border-left: 5px solid #ddd;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.cv-status-card.success {
    border-left-color: #28a745;
    background: #f8fff9;
}

.cv-status-card.warning {
    border-left-color: #ffc107;
    background: #fffdf0;
}

.status-icon {
    font-size: 3rem;
    margin-right: 20px;
}

.status-content h2 {
    margin: 0 0 10px;
    color: #1e293b;
}

.cv-details {
    margin-top: 20px;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.detail-item {
    background: rgba(255,255,255,0.8);
    padding: 10px 15px;
    border-radius: 6px;
}

.detail-item strong {
    display: block;
    color: #6c757d;
    font-size: 0.9rem;
    margin-bottom: 5px;
}

.cv-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin: 30px 0;
}

.action-group {
    background: white;
    border-radius: 10px;
    padding: 20px;
    border: 1px solid #ddd;
}

.action-group.danger {
    border-color: #dc3545;
    background: #fff5f5;
}

.action-group h3 {
    margin: 0 0 15px;
    color: #1e293b;
}

.action-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.link-examples {
    space-y: 10px;
}

.link-item {
    margin-bottom: 15px;
}

.link-item strong {
    display: block;
    margin-bottom: 5px;
    color: #495057;
}

.link-item code {
    background: #f8f9fa;
    padding: 8px 12px;
    border-radius: 4px;
    display: block;
    font-size: 0.9rem;
    word-break: break-all;
}

.cv-upload-section {
    background: white;
    border-radius: 10px;
    padding: 30px;
    margin: 30px 0;
    border: 1px solid #ddd;
}

.cv-upload-form {
    margin-top: 20px;
}

.upload-area {
    border: 2px dashed #cbd5e0;
    border-radius: 10px;
    padding: 40px 20px;
    text-align: center;
    transition: all 0.3s ease;
    margin-bottom: 20px;
}

.upload-area:hover {
    border-color: #007bff;
    background: #f8f9ff;
}

.upload-icon {
    font-size: 3rem;
    margin-bottom: 15px;
}

.upload-content h3 {
    margin: 0 0 10px;
    color: #1e293b;
}

.cv-file-input {
    display: none;
}

.cv-file-label {
    display: inline-block;
    background: #007bff;
    color: white;
    padding: 12px 24px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: bold;
    transition: all 0.3s ease;
    margin-top: 15px;
}

.cv-file-label:hover {
    background: #0056b3;
}

.cv-file-label .dashicons {
    margin-right: 8px;
}

.file-info {
    margin-top: 15px;
    padding: 10px;
    background: #e7f3ff;
    border-radius: 6px;
}

.file-name {
    font-weight: bold;
    display: block;
}

.file-size {
    color: #6c757d;
    font-size: 0.9rem;
}

.upload-actions {
    text-align: center;
}

.upload-actions .button {
    margin: 0 10px;
}

.cv-instructions {
    background: white;
    border-radius: 10px;
    padding: 30px;
    margin: 30px 0;
    border: 1px solid #ddd;
}

.instruction-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.instruction-item {
    text-align: center;
    padding: 20px;
}

.instruction-number {
    width: 50px;
    height: 50px;
    background: #007bff;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: bold;
    margin: 0 auto 15px;
}

.instruction-item h3 {
    margin: 0 0 10px;
    color: #1e293b;
}

@media (max-width: 768px) {
    .cv-status-card {
        flex-direction: column;
        text-align: center;
    }
    
    .status-icon {
        margin: 0 0 20px;
    }
    
    .cv-actions {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        justify-content: center;
    }
    
    .instruction-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('cv_file');
    const fileInfo = document.querySelector('.file-info');
    const fileName = document.querySelector('.file-name');
    const fileSize = document.querySelector('.file-size');
    
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                fileName.textContent = file.name;
                fileSize.textContent = formatFileSize(file.size);
                fileInfo.style.display = 'block';
            } else {
                fileInfo.style.display = 'none';
            }
        });
    }
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
});
</script> 