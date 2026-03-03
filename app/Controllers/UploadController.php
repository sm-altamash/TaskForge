<?php
/**
 * Upload Controller
 *
 * Handles file uploads for task image attachments.
 */

class UploadController {

    /**
     * POST /api/v1/uploads
     */
    public function handleUpload() {
        if (empty($_FILES['file'])) {
            $this->sendError('No file uploaded.');
            return;
        }

        $file = $_FILES['file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->sendError('Upload failed with error code: ' . $file['error']);
            return;
        }

        // Security: check MIME type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->file($file['tmp_name']);

        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mime_type, $allowed_mimes)) {
            $this->sendError('Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.');
            return;
        }

        // Generate safe filename
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $safe_filename  = uniqid('task-img-') . '.' . $file_extension;

        // Upload to storage/uploads (outside public)
        $upload_dir  = __DIR__ . '/../../storage/uploads/';
        $upload_path = $upload_dir . $safe_filename;

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            $public_url = '/uploads/' . $safe_filename;

            http_response_code(200);
            echo json_encode(['location' => $public_url]);
        } else {
            $this->sendError('Failed to move uploaded file.');
        }
    }

    private function sendError($message) {
        http_response_code(400);
        echo json_encode(['error' => $message]);
    }
}
