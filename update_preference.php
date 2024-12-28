<?php
// Connect to the database
$mysqli = new mysqli('localhost', 'root', '', 'information_retreival');

// Check for connection errors
if ($mysqli->connect_error) {
    die('Database connection error: ' . $mysqli->connect_error);
}

// Get POST data
$document_id = (int)$_POST['document_id'];
$query_term = $mysqli->real_escape_string($_POST['query_term']);

// Check if the record already exists
$result = $mysqli->query("SELECT * FROM user_preference WHERE document_id = $document_id AND query_term = '$query_term'");

if ($result->num_rows > 0) {
    // If it exists, increment the preference_count
    $mysqli->query("UPDATE user_preference SET preference_count = preference_count + 1 
                    WHERE document_id = $document_id AND query_term = '$query_term'");
} else {
    // If it doesn't exist, insert a new record
    $mysqli->query("INSERT INTO user_preference (query_term, document_id, preference_count) 
                    VALUES ('$query_term', $document_id, 1)");
}

// Query the database for the document details
$document_result = $mysqli->query("SELECT * FROM documents WHERE id = $document_id");

// Check if the document exists
if ($document_result->num_rows > 0) {
    $document = $document_result->fetch_assoc();

    // Display the document details
    echo '
        <style>
            .document-details {
                background-color: #f8f9fa;
                border: 1px solid #dee2e6;
                padding: 20px;
                margin: 20px 0;
                border-radius: 5px;
                font-family: Arial, sans-serif;
            }
            .document-details h2 {
                margin: 0 0 10px;
                color: #343a40;
            }
            .document-details p {
                margin: 5px 0;
                color: #495057;
            }
        </style>
        <div class="document-details">
            <h2>' . htmlspecialchars($document['title']) . '</h2>
            <p><strong>Author:</strong> ' . htmlspecialchars($document['author']) . '</p>
            <p><strong>Publish Date:</strong> ' . htmlspecialchars($document['publish_date']) . '</p>
            <p><strong>Content:</strong></p>
            <p>' . nl2br(htmlspecialchars($document['content'])) . '</p>
        </div>
    ';
} else {
    // If the document is not found
    echo '<p>Document not found.</p>';
}

exit;
?>
