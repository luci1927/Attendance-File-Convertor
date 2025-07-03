<?php
// Define fixed master file path
define('MASTER_FILE_1', 'F:/Daily Att. Text File.txt');

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
                // Process each line
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

                    $output_content = implode("\n", $output_lines) . "\n";
                    
                    // Append to master file
                    $result = file_put_contents(MASTER_FILE_1, $output_content, FILE_APPEND);
                    
                    if ($result === false) {
                        $response = [
                            'status' => 'error', 
                            'message' => 'Failed to append to: ' . MASTER_FILE_1
                        ];
                    } else {
                        $response = [
                            'status' => 'success', 
                            'message' => 'File converted and appended to master file successfully!'
                        ];
                    }
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
        
        <div class="upload-section">
            <h2>Upload Attendance File</h2>
            
            <div id="message" class="message">
                <span class="icon">ℹ️</span>
                <span id="messageText">Select a file to begin conversion</span>
            </div>
            
            <div class="upload-box">
                <h3>Choose a file to upload</h3>
                <p>Only .txt files are accepted</p>
                
                <div class="file-input-wrapper">
                    <label class="file-label">
                        <span class="icon">📎</span> Select File
                        <input type="file" name="file" id="fileInput" accept=".txt" required>
                    </label>
                </div>
                
                <div id="fileName">No file chosen</div>
            </div>
            
            <button id="submitBtn" class="submit-btn">Upload and Convert</button>
        </div>

        <div class="master-files">
            <h2>Master File</h2>
            <div class="file-list">
                <div class="file-item">
                    <h3><span class="icon">📁</span> Master File</h3>
                    <div class="file-path"><?php echo MASTER_FILE_1; ?></div>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>Attendance File Converter System | Secure Processing</p>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('fileInput');
            const fileNameDisplay = document.getElementById('fileName');
            const submitBtn = document.getElementById('submitBtn');
            const messageDiv = document.getElementById('message');
            const messageText = document.getElementById('messageText');
            const messageIcon = messageDiv.querySelector('.icon');
            
            // Show initial message
            messageDiv.classList.add('info-message', 'show');
            
            // Update file name display
            fileInput.addEventListener('change', function() {
                const fileName = this.files[0] ? this.files[0].name : 'No file chosen';
                fileNameDisplay.textContent = fileName;
                
                // Validate file type
                if (this.files.length && !fileName.toLowerCase().endsWith('.txt')) {
                    showMessage('error', 'Only .txt files are allowed.');
                } else {
                    showMessage('info', 'File selected. Click "Upload and Convert" to proceed.');
                }
            });
            
            // Handle form submission
            submitBtn.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Check if file is selected
                if (!fileInput.files.length) {
                    showMessage('error', 'Please select a file first.');
                    return;
                }
                
                const file = fileInput.files[0];
                const fileName = file.name.toLowerCase();
                
                // Validate file type
                if (!fileName.endsWith('.txt')) {
                    showMessage('error', 'Only .txt files are allowed.');
                    return;
                }
                
                // Show processing message
                showMessage('info', 'Processing your file...');
                
                // Create FormData object and send via AJAX
                const formData = new FormData();
                formData.append('file', file);
                
                fetch('index.php?action=process', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        showMessage('success', data.message);
                        // Reset file input
                        fileInput.value = '';
                        fileNameDisplay.textContent = 'No file chosen';
                    } else {
                        showMessage('error', data.message);
                    }
                })
                .catch(error => {
                    showMessage('error', 'Network error: ' + error.message);
                });
            });
            
            // Function to show messages
            function showMessage(type, text) {
                messageText.textContent = text;
                
                // Clear previous classes
                messageDiv.className = 'message';
                
                // Set new classes and icon
                if (type === 'success') {
                    messageDiv.classList.add('success-message', 'show');
                    messageIcon.textContent = '✅';
                } else if (type === 'error') {
                    messageDiv.classList.add('error-message', 'show');
                    messageIcon.textContent = '❌';
                } else if (type === 'info') {
                    messageDiv.classList.add('info-message', 'show');
                    messageIcon.textContent = 'ℹ️';
                }
            }
        });
    </script>
</body>
</html>
