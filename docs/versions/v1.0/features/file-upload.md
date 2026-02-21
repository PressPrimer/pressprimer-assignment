# Feature: File Upload System

## Overview

The file upload system allows students to submit documents for assignments. It supports drag-and-drop, multi-file uploads, progress tracking, and robust security validation.

## User Stories

**As a student**, I want to drag and drop files into the submission area so I can quickly submit my work.

**As a student**, I want to see upload progress so I know my large files are being processed.

**As a student**, I want clear error messages if my file isn't accepted so I can fix the issue.

**As an administrator**, I want file types validated by content (not just extension) so malicious files can't be uploaded.

**As an administrator**, I want files stored securely so they can't be accessed without permission.

---

## Acceptance Criteria

### Upload Interface
- [ ] Drag-and-drop zone is clearly visible
- [ ] Click-to-browse fallback works
- [ ] Multiple files can be selected at once
- [ ] Files can be removed before final submission
- [ ] Upload progress shows for each file
- [ ] Upload can be cancelled mid-progress
- [ ] File list shows name, size, and status
- [ ] Mobile touch upload works

### Validation
- [ ] Extension validated against assignment's allowed types
- [ ] MIME type validated using finfo
- [ ] File content (magic bytes) validated
- [ ] Double extensions rejected (file.php.pdf)
- [ ] File size validated against assignment limit
- [ ] Total file count validated against assignment limit
- [ ] Clear error messages for each validation failure

### Security
- [ ] Files stored outside webroot
- [ ] Unique filenames generated (hash + timestamp)
- [ ] SHA-256 hash stored for integrity verification
- [ ] .htaccess denies direct access
- [ ] Files served only through PHP with permission check
- [ ] Upload directory has index.php silence file

### User Experience
- [ ] Works without JavaScript (basic fallback)
- [ ] Accessible via keyboard
- [ ] Screen reader announces upload status
- [ ] Error messages are descriptive and actionable

---

## Technical Implementation

### Frontend Components

#### Upload Zone Component

```javascript
// src/components/FileUploader.js
import { useState, useCallback } from 'react';
import { Upload, message } from 'antd';
import { InboxOutlined } from '@ant-design/icons';

const { Dragger } = Upload;

const FileUploader = ({ 
    assignmentId,
    allowedTypes,
    maxFileSize,
    maxFiles,
    onFilesChange 
}) => {
    const [fileList, setFileList] = useState([]);
    
    const beforeUpload = (file) => {
        // Client-side validation (server validates again)
        const extension = file.name.split('.').pop().toLowerCase();
        
        if (!allowedTypes.includes(extension)) {
            message.error(`${file.name}: File type not allowed`);
            return Upload.LIST_IGNORE;
        }
        
        if (file.size > maxFileSize) {
            message.error(`${file.name}: File too large`);
            return Upload.LIST_IGNORE;
        }
        
        if (fileList.length >= maxFiles) {
            message.error(`Maximum ${maxFiles} files allowed`);
            return Upload.LIST_IGNORE;
        }
        
        return true;
    };
    
    const customRequest = async ({ file, onProgress, onSuccess, onError }) => {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('assignment_id', assignmentId);
        formData.append('nonce', ppaData.uploadNonce);
        
        try {
            const response = await fetch(ppaData.ajaxUrl, {
                method: 'POST',
                body: formData,
            });
            
            const result = await response.json();
            
            if (result.success) {
                onSuccess(result.data, file);
            } else {
                onError(new Error(result.data.message));
            }
        } catch (error) {
            onError(error);
        }
    };
    
    return (
        <Dragger
            multiple
            fileList={fileList}
            beforeUpload={beforeUpload}
            customRequest={customRequest}
            onChange={({ fileList }) => {
                setFileList(fileList);
                onFilesChange(fileList);
            }}
        >
            <p className="ant-upload-drag-icon">
                <InboxOutlined />
            </p>
            <p className="ant-upload-text">
                {__('Click or drag files to upload', 'pressprimer-assignment')}
            </p>
            <p className="ant-upload-hint">
                {__('Allowed: ', 'pressprimer-assignment')} {allowedTypes.join(', ')}
            </p>
        </Dragger>
    );
};
```

#### Vanilla JS Fallback (Progressive Enhancement)

```javascript
// assets/js/submission.js
(function() {
    'use strict';
    
    const PPA = window.PPA || {};
    
    PPA.Upload = {
        init: function(container) {
            this.container = container;
            this.dropZone = container.querySelector('.ppa-upload-zone');
            this.fileInput = container.querySelector('.ppa-file-input');
            this.fileList = container.querySelector('.ppa-file-list');
            this.files = [];
            
            this.bindEvents();
        },
        
        bindEvents: function() {
            // Drag and drop
            this.dropZone.addEventListener('dragover', this.handleDragOver.bind(this));
            this.dropZone.addEventListener('dragleave', this.handleDragLeave.bind(this));
            this.dropZone.addEventListener('drop', this.handleDrop.bind(this));
            
            // Click to browse
            this.dropZone.addEventListener('click', () => this.fileInput.click());
            this.fileInput.addEventListener('change', this.handleFileSelect.bind(this));
            
            // Keyboard accessibility
            this.dropZone.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.fileInput.click();
                }
            });
        },
        
        handleDragOver: function(e) {
            e.preventDefault();
            this.dropZone.classList.add('is-dragging');
        },
        
        handleDragLeave: function(e) {
            e.preventDefault();
            this.dropZone.classList.remove('is-dragging');
        },
        
        handleDrop: function(e) {
            e.preventDefault();
            this.dropZone.classList.remove('is-dragging');
            
            const files = Array.from(e.dataTransfer.files);
            this.processFiles(files);
        },
        
        handleFileSelect: function(e) {
            const files = Array.from(e.target.files);
            this.processFiles(files);
        },
        
        processFiles: function(files) {
            files.forEach(file => {
                if (this.validateFile(file)) {
                    this.uploadFile(file);
                }
            });
        },
        
        validateFile: function(file) {
            const extension = file.name.split('.').pop().toLowerCase();
            const config = this.container.dataset;
            const allowedTypes = JSON.parse(config.allowedTypes);
            const maxSize = parseInt(config.maxSize, 10);
            const maxFiles = parseInt(config.maxFiles, 10);
            
            if (!allowedTypes.includes(extension)) {
                this.showError(file.name + ': ' + ppaData.i18n.typeNotAllowed);
                return false;
            }
            
            if (file.size > maxSize) {
                this.showError(file.name + ': ' + ppaData.i18n.fileTooLarge);
                return false;
            }
            
            if (this.files.length >= maxFiles) {
                this.showError(ppaData.i18n.maxFilesReached);
                return false;
            }
            
            return true;
        },
        
        uploadFile: function(file) {
            const formData = new FormData();
            formData.append('action', 'ppa_upload_file');
            formData.append('file', file);
            formData.append('assignment_id', this.container.dataset.assignmentId);
            formData.append('nonce', ppaData.uploadNonce);
            
            const item = this.createFileItem(file);
            
            const xhr = new XMLHttpRequest();
            
            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const percent = Math.round((e.loaded / e.total) * 100);
                    item.querySelector('.ppa-progress-bar').style.width = percent + '%';
                    item.querySelector('.ppa-progress-text').textContent = percent + '%';
                }
            });
            
            xhr.addEventListener('load', () => {
                if (xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        this.files.push(response.data);
                        item.classList.add('is-complete');
                        item.dataset.fileId = response.data.id;
                        this.announceToScreenReader(file.name + ' uploaded successfully');
                    } else {
                        item.classList.add('is-error');
                        this.showError(response.data.message);
                    }
                } else {
                    item.classList.add('is-error');
                    this.showError(ppaData.i18n.uploadFailed);
                }
            });
            
            xhr.open('POST', ppaData.ajaxUrl);
            xhr.send(formData);
        },
        
        createFileItem: function(file) {
            const item = document.createElement('div');
            item.className = 'ppa-file-item is-uploading';
            item.innerHTML = `
                <span class="ppa-file-name">${this.escapeHtml(file.name)}</span>
                <span class="ppa-file-size">${this.formatSize(file.size)}</span>
                <div class="ppa-progress">
                    <div class="ppa-progress-bar" style="width: 0%"></div>
                    <span class="ppa-progress-text">0%</span>
                </div>
                <button type="button" class="ppa-file-remove" aria-label="Remove file">×</button>
            `;
            
            item.querySelector('.ppa-file-remove').addEventListener('click', () => {
                this.removeFile(item);
            });
            
            this.fileList.appendChild(item);
            return item;
        },
        
        removeFile: function(item) {
            const fileId = item.dataset.fileId;
            if (fileId) {
                // Remove from server
                fetch(ppaData.ajaxUrl, {
                    method: 'POST',
                    body: new URLSearchParams({
                        action: 'ppa_remove_file',
                        file_id: fileId,
                        nonce: ppaData.uploadNonce,
                    }),
                });
                
                this.files = this.files.filter(f => f.id !== parseInt(fileId, 10));
            }
            
            item.remove();
        },
        
        formatSize: function(bytes) {
            const units = ['B', 'KB', 'MB', 'GB'];
            let i = 0;
            while (bytes >= 1024 && i < units.length - 1) {
                bytes /= 1024;
                i++;
            }
            return bytes.toFixed(1) + ' ' + units[i];
        },
        
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        
        showError: function(message) {
            // Show inline error
            const error = document.createElement('div');
            error.className = 'ppa-upload-error';
            error.setAttribute('role', 'alert');
            error.textContent = message;
            this.container.appendChild(error);
            
            setTimeout(() => error.remove(), 5000);
        },
        
        announceToScreenReader: function(message) {
            const announcement = document.createElement('div');
            announcement.className = 'screen-reader-text';
            announcement.setAttribute('aria-live', 'polite');
            announcement.textContent = message;
            document.body.appendChild(announcement);
            setTimeout(() => announcement.remove(), 1000);
        }
    };
    
    // Initialize
    document.querySelectorAll('.ppa-upload-container').forEach(container => {
        PPA.Upload.init(container);
    });
    
    window.PPA = PPA;
})();
```

### Backend Implementation

#### AJAX Upload Handler

```php
<?php
// In class-ppa-submission-handler.php

class PressPrimer_Assignment_Submission_Handler {
    
    public function __construct() {
        add_action( 'wp_ajax_ppa_upload_file', [ $this, 'handle_upload' ] );
    }
    
    public function handle_upload() {
        // Verify nonce
        check_ajax_referer( 'ppa_upload_file', 'nonce' );
        
        // Check user is logged in
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [
                'code'    => 'not_logged_in',
                'message' => __( 'You must be logged in to upload files.', 'pressprimer-assignment' ),
            ], 401 );
        }
        
        // Get assignment
        $assignment_id = isset( $_POST['assignment_id'] ) ? absint( $_POST['assignment_id'] ) : 0;
        $assignment = PressPrimer_Assignment_Assignment::get( $assignment_id );
        
        if ( ! $assignment || 'published' !== $assignment->status ) {
            wp_send_json_error( [
                'code'    => 'invalid_assignment',
                'message' => __( 'Invalid assignment.', 'pressprimer-assignment' ),
            ], 400 );
        }
        
        // Check if user can submit
        $user_id = get_current_user_id();
        $can_submit = apply_filters( 'pressprimer_assignment_can_submit', true, $user_id, $assignment_id );
        
        if ( ! $can_submit ) {
            wp_send_json_error( [
                'code'    => 'cannot_submit',
                'message' => __( 'You cannot submit to this assignment.', 'pressprimer-assignment' ),
            ], 403 );
        }
        
        // Check file was uploaded
        if ( empty( $_FILES['file'] ) ) {
            wp_send_json_error( [
                'code'    => 'no_file',
                'message' => __( 'No file uploaded.', 'pressprimer-assignment' ),
            ], 400 );
        }
        
        // Validate file
        $validation = PressPrimer_Assignment_File_Service::validate_file( $_FILES['file'], $assignment );
        
        if ( is_wp_error( $validation ) ) {
            wp_send_json_error( [
                'code'    => $validation->get_error_code(),
                'message' => $validation->get_error_message(),
            ], 400 );
        }
        
        // Get or create submission
        $submission = $this->get_or_create_submission( $user_id, $assignment_id );
        
        if ( is_wp_error( $submission ) ) {
            wp_send_json_error( [
                'code'    => $submission->get_error_code(),
                'message' => $submission->get_error_message(),
            ], 400 );
        }
        
        // Store file
        $file_id = PressPrimer_Assignment_File_Service::store_file( $_FILES['file'], $submission->id );
        
        if ( is_wp_error( $file_id ) ) {
            wp_send_json_error( [
                'code'    => $file_id->get_error_code(),
                'message' => $file_id->get_error_message(),
            ], 400 );
        }
        
        // Get file record
        $file = PressPrimer_Assignment_Submission_File::get( $file_id );
        
        // Fire action
        do_action( 'pressprimer_assignment_file_uploaded', $file_id, $submission->id );
        
        wp_send_json_success( [
            'id'       => $file->id,
            'name'     => $file->original_filename,
            'size'     => $file->file_size,
            'type'     => $file->mime_type,
        ] );
    }
    
    private function get_or_create_submission( $user_id, $assignment_id ) {
        // Check for existing draft submission
        global $wpdb;
        
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppa_submissions 
             WHERE assignment_id = %d AND user_id = %d AND status = 'draft'
             ORDER BY created_at DESC LIMIT 1",
            $assignment_id,
            $user_id
        ) );
        
        if ( $existing ) {
            $submission = new PressPrimer_Assignment_Submission();
            $submission->data = (array) $existing;
            return $submission;
        }
        
        // Create new draft submission
        $submission_id = PressPrimer_Assignment_Submission::create( [
            'assignment_id' => $assignment_id,
            'user_id'       => $user_id,
            'status'        => 'draft',
        ] );
        
        if ( is_wp_error( $submission_id ) ) {
            return $submission_id;
        }
        
        do_action( 'pressprimer_assignment_submission_created', $submission_id );
        
        return PressPrimer_Assignment_Submission::get( $submission_id );
    }
}
```

#### File Service

```php
<?php
// In class-ppa-file-service.php

class PressPrimer_Assignment_File_Service {
    
    /**
     * Validate uploaded file
     */
    public static function validate_file( $file, $assignment ) {
        // Check upload error
        if ( UPLOAD_ERR_OK !== $file['error'] ) {
            return new WP_Error( 'upload_error', self::get_upload_error_message( $file['error'] ) );
        }
        
        // Sanitize filename
        $filename = sanitize_file_name( $file['name'] );
        
        // Check extension
        $extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
        $allowed = $assignment->get_allowed_file_types();
        
        if ( ! in_array( $extension, $allowed, true ) ) {
            return new WP_Error( 
                'invalid_extension',
                sprintf(
                    __( 'File type .%s is not allowed.', 'pressprimer-assignment' ),
                    esc_html( $extension )
                )
            );
        }
        
        // Check for double extensions
        $name_parts = explode( '.', $filename );
        if ( count( $name_parts ) > 2 ) {
            $dangerous = [ 'php', 'phtml', 'php3', 'php4', 'php5', 'phps', 'phar', 'js', 'exe', 'sh', 'bat' ];
            for ( $i = 1; $i < count( $name_parts ) - 1; $i++ ) {
                if ( in_array( strtolower( $name_parts[ $i ] ), $dangerous, true ) ) {
                    return new WP_Error( 'dangerous_filename', __( 'Invalid filename.', 'pressprimer-assignment' ) );
                }
            }
        }
        
        // Verify MIME type
        $finfo = finfo_open( FILEINFO_MIME_TYPE );
        $mime = finfo_file( $finfo, $file['tmp_name'] );
        finfo_close( $finfo );
        
        $expected_mimes = self::get_expected_mimes( $extension );
        
        if ( ! in_array( $mime, $expected_mimes, true ) ) {
            return new WP_Error(
                'invalid_mime',
                __( 'File content does not match expected type.', 'pressprimer-assignment' )
            );
        }
        
        // Check file size
        $max_size = $assignment->max_file_size ?: 10485760;
        if ( $file['size'] > $max_size ) {
            return new WP_Error(
                'file_too_large',
                sprintf(
                    __( 'File exceeds maximum size of %s.', 'pressprimer-assignment' ),
                    size_format( $max_size )
                )
            );
        }
        
        return true;
    }
    
    /**
     * Store file securely
     */
    public static function store_file( $file, $submission_id ) {
        // Get upload directory
        $upload_dir = self::get_upload_directory();
        
        // Generate secure filename
        $original_name = sanitize_file_name( $file['name'] );
        $extension = strtolower( pathinfo( $original_name, PATHINFO_EXTENSION ) );
        $hash = wp_generate_password( 16, false );
        $timestamp = time();
        $secure_name = sprintf( '%s_%d.%s', $hash, $timestamp, $extension );
        
        // Create subdirectory by date
        $subdir = gmdate( 'Y/m' );
        $target_dir = $upload_dir . '/' . $subdir;
        
        if ( ! file_exists( $target_dir ) ) {
            wp_mkdir_p( $target_dir );
            
            // Add index.php to subdirectory
            file_put_contents( $target_dir . '/index.php', '<?php // Silence is golden.' );
        }
        
        $target_path = $target_dir . '/' . $secure_name;
        $relative_path = $subdir . '/' . $secure_name;
        
        // Move file
        if ( ! move_uploaded_file( $file['tmp_name'], $target_path ) ) {
            return new WP_Error( 'move_failed', __( 'Failed to save file.', 'pressprimer-assignment' ) );
        }
        
        // Calculate hash for integrity
        $file_hash = hash_file( 'sha256', $target_path );
        
        // Get MIME type
        $finfo = finfo_open( FILEINFO_MIME_TYPE );
        $mime_type = finfo_file( $finfo, $target_path );
        finfo_close( $finfo );
        
        // Create database record
        $file_id = PressPrimer_Assignment_Submission_File::create( [
            'submission_id'     => $submission_id,
            'original_filename' => $original_name,
            'stored_filename'   => $secure_name,
            'file_path'         => $relative_path,
            'file_size'         => filesize( $target_path ),
            'mime_type'         => $mime_type,
            'file_extension'    => $extension,
            'file_hash'         => $file_hash,
        ] );
        
        if ( is_wp_error( $file_id ) ) {
            // Clean up file if DB insert failed
            unlink( $target_path );
            return $file_id;
        }
        
        // Update submission file count
        self::update_submission_file_stats( $submission_id );
        
        return $file_id;
    }
    
    /**
     * Get secure upload directory
     */
    public static function get_upload_directory() {
        $upload_dir = wp_upload_dir();
        $ppa_dir = $upload_dir['basedir'] . '/ppa-submissions';
        
        $ppa_dir = apply_filters( 'pressprimer_assignment_upload_dir', $ppa_dir );
        
        if ( ! file_exists( $ppa_dir ) ) {
            wp_mkdir_p( $ppa_dir );
            
            // Create .htaccess
            $htaccess = $ppa_dir . '/.htaccess';
            file_put_contents( $htaccess, "Order deny,allow\nDeny from all" );
            
            // Create index.php
            $index = $ppa_dir . '/index.php';
            file_put_contents( $index, '<?php // Silence is golden.' );
        }
        
        return $ppa_dir;
    }
    
    /**
     * Get expected MIME types for extension
     */
    private static function get_expected_mimes( $extension ) {
        $mimes = [
            'pdf'  => [ 'application/pdf' ],
            'docx' => [ 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ],
            'txt'  => [ 'text/plain', 'text/x-c' ],
            'rtf'  => [ 'application/rtf', 'text/rtf' ],
            'jpg'  => [ 'image/jpeg' ],
            'jpeg' => [ 'image/jpeg' ],
            'png'  => [ 'image/png' ],
            'gif'  => [ 'image/gif' ],
        ];
        
        return $mimes[ $extension ] ?? [];
    }
    
    /**
     * Get upload error message
     */
    private static function get_upload_error_message( $error_code ) {
        $messages = [
            UPLOAD_ERR_INI_SIZE   => __( 'File exceeds server upload limit.', 'pressprimer-assignment' ),
            UPLOAD_ERR_FORM_SIZE  => __( 'File exceeds form upload limit.', 'pressprimer-assignment' ),
            UPLOAD_ERR_PARTIAL    => __( 'File was only partially uploaded.', 'pressprimer-assignment' ),
            UPLOAD_ERR_NO_FILE    => __( 'No file was uploaded.', 'pressprimer-assignment' ),
            UPLOAD_ERR_NO_TMP_DIR => __( 'Server misconfiguration: no temp directory.', 'pressprimer-assignment' ),
            UPLOAD_ERR_CANT_WRITE => __( 'Failed to write file to disk.', 'pressprimer-assignment' ),
            UPLOAD_ERR_EXTENSION  => __( 'Upload blocked by server extension.', 'pressprimer-assignment' ),
        ];
        
        return $messages[ $error_code ] ?? __( 'Unknown upload error.', 'pressprimer-assignment' );
    }
    
    /**
     * Update submission file statistics
     */
    private static function update_submission_file_stats( $submission_id ) {
        global $wpdb;
        
        $stats = $wpdb->get_row( $wpdb->prepare(
            "SELECT COUNT(*) as file_count, COALESCE(SUM(file_size), 0) as total_size
             FROM {$wpdb->prefix}ppa_submission_files
             WHERE submission_id = %d",
            $submission_id
        ) );
        
        $wpdb->update(
            $wpdb->prefix . 'ppa_submissions',
            [
                'file_count'      => $stats->file_count,
                'total_file_size' => $stats->total_size,
            ],
            [ 'id' => $submission_id ],
            [ '%d', '%d' ],
            [ '%d' ]
        );
    }
}
```

---

## Database Requirements

See `architecture/DATABASE.md` for `wp_ppa_submission_files` table schema.

---

## UI/UX Requirements

### Upload Zone States

| State | Appearance |
|-------|------------|
| Default | Dashed border, upload icon, "Drag files here" text |
| Hover/Focus | Border color change, subtle background |
| Dragging Over | Prominent border, "Drop files" text |
| Uploading | Progress bars visible |
| Error | Red border, error message |

### File Item States

| State | Visual Indicator |
|-------|-----------------|
| Uploading | Progress bar, percentage |
| Complete | Green checkmark |
| Error | Red X, error message |
| Removing | Fade out animation |

### Accessibility Requirements

- Upload zone has `role="button"` and `tabindex="0"`
- Keyboard: Enter/Space activates file browser
- Progress announced to screen readers via `aria-live`
- Remove button has `aria-label="Remove [filename]"`
- Error messages use `role="alert"`

---

## Edge Cases

1. **Upload interrupted** - Partial files should be cleaned up
2. **Duplicate files** - Allow (same name, different content)
3. **Zero-byte files** - Reject with clear message
4. **Network timeout** - Show retry option
5. **Browser crash** - Draft submissions preserve uploaded files
6. **Concurrent uploads** - Handle race conditions in file count
7. **Max files reached mid-upload** - Cancel queued uploads, show message

---

## Not In Scope (v1.0)

- Chunked uploads for very large files (>50MB)
- Resume interrupted uploads
- Cloud storage (S3, Google Drive)
- Virus scanning
- Image thumbnail generation
- File preview during upload
- Drag to reorder files
