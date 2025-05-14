<?php
session_start();

date_default_timezone_set('Asia/Colombo');

// Handle AJAX file processing
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_GET['action']) && $_GET['action'] === 'process') {
    header('Content-Type: application/json');
    $response = [];

    if (isset($_FILES['file'])) {
        $tmp_file = $_FILES['file']['tmp_name'];
        $file_name = $_FILES['file']['name'];
        $file_type = mime_content_type($tmp_file);
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        // Validate file type and extension
        if ($file_ext !== 'txt' || !in_array($file_type, ['text/plain'])) {
            $response = ['status' => 'error', 'message' => 'Only .txt files are allowed.'];
        } elseif (is_uploaded_file($tmp_file)) {
            // Read the uploaded file
            $lines = file($tmp_file, FILE_IGNORE_NEW_LINES);
            if (empty($lines)) {
                $response = ['status' => 'error', 'message' => 'The uploaded file is empty.'];
            } else {
                $data = [];
                // Process each line, assuming format: emp_num date time (e.g., "123 15/10/2023 12:30:45")
                for ($i = 1; $i < count($lines); $i++) { // Skip header
                    $line = trim($lines[$i]);
                    if ($line == '') continue;

                    $parts = preg_split('/\s+/', $line);
                    if (count($parts) != 3) continue;

                    $emp_num = $parts[0];
                    $date_time_str = $parts[1] . ' ' . $parts[2];
                    $date_time = DateTime::createFromFormat('d/m/Y H:i:s', $date_time_str);
                    if ($date_time === false) continue;

                    $timestamp = $date_time->getTimestamp();
                    $data[] = [
                        'emp_num' => $emp_num,
                        'date' => $parts[1],
                        'time' => $parts[2],
                        'timestamp' => $timestamp
                    ];
                }

                if (empty($data)) {
                    $response = ['status' => 'error', 'message' => 'No valid data found in the uploaded file.'];
                } else {
                    // Sort by timestamp
                    usort($data, function($a, $b) {
                        return $a['timestamp'] <=> $b['timestamp'];
                    });

                    // Format output lines
                    $output_lines = [];
                    foreach ($data as $entry) {
                        $date_parts = explode('/', $entry['date']);
                        $time_parts = explode(':', $entry['time']);
                        $dd = $date_parts[0];
                        $mm = $date_parts[1];
                        $yyyy = $date_parts[2];
                        $hh = $time_parts[0];
                        $min = $time_parts[1];
                        $transformed = $entry['emp_num'] . $dd . $mm . $yyyy . $hh . $min;
                        $output_lines[] = $transformed;
                    }

                    $output_content = implode("\n", $output_lines);

                    // Store file content in session for download
                    $_SESSION['file_content'] = $output_content;
                    $_SESSION['file_time'] = date('YmdHis');

                    $response = ['status' => 'success', 'message' => 'File converted successfully! Downloading now...'];
                }
            }
        } else {
            $response = ['status' => 'error', 'message' => 'File upload failed.'];
        }
    } else {
        $response = ['status' => 'error', 'message' => 'No file provided.'];
    }

    echo json_encode($response);
    exit;
}

// Handle file download
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['action']) && $_GET['action'] === 'download' && isset($_SESSION['file_content'])) {
    $output_content = $_SESSION['file_content'];
    $current_time = $_SESSION['file_time'];
    $filename = "Office_Attendance_" . $current_time . ".txt";

    // Send file for download
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $output_content;

    unset($_SESSION['file_content']);
    unset($_SESSION['file_time']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance File Converter</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <h1>Attendance File Converter</h1>
        <div class="message" id="message" style="display: none;"></div>
        <form id="uploadForm" enctype="multipart/form-data">
            <div class="file-upload">
                <input type="file" name="file" id="fileInput" accept=".txt" required>
                <label for="fileInput" class="file-label">Choose .txt File</label>
                <span id="fileName">No file chosen</span>
            </div>
            <button type="submit" class="submit-btn">Upload and Convert</button>
        </form>
    </div>
    <script>
        document.getElementById('fileInput').addEventListener('change', function() {
            const fileName = this.files[0] ? this.files[0].name : 'No file chosen';
            document.getElementById('fileName').textContent = fileName;
        });

        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const fileInput = document.getElementById('fileInput');
            const messageDiv = document.getElementById('message');

            // Client-side file type validation
            if (fileInput.files.length > 0) {
                const fileName = fileInput.files[0].name;
                if (!fileName.toLowerCase().endsWith('.txt')) {
                    messageDiv.style.display = 'block';
                    messageDiv.className = 'message error-message';
                    messageDiv.innerHTML = '<span class="error-icon">⚠️</span>Only .txt files are allowed.';
                    setTimeout(() => {
                        messageDiv.style.display = 'none';
                    }, 3000);
                    return;
                }
            }

            const formData = new FormData(this);

            fetch('index.php?action=process', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                messageDiv.style.display = 'block';
                messageDiv.className = 'message ' + (data.status === 'success' ? 'success-message' : 'error-message');
                messageDiv.innerHTML = (data.status === 'success' ? '<span class="success-icon">✅</span>' : '<span class="error-icon">⚠️</span>') + data.message;

                if (data.status === 'success') {
                    // Trigger file download
                    window.location.href = 'index.php?action=download';
                }

                // Clear message after 3 seconds
                setTimeout(() => {
                    messageDiv.style.display = 'none';
                }, 3000);
            })
            .catch(error => {
                messageDiv.style.display = 'block';
                messageDiv.className = 'message error-message';
                messageDiv.innerHTML = '<span class="error-icon">⚠️</span>Upload failed due to a network error.';
                setTimeout(() => {
                    messageDiv.style.display = 'none';
                }, 3000);
            });
        });
    </script>
</body>
</html>
