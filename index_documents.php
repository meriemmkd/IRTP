<?php
$mysqli = new mysqli("localhost", "root", "", "information_retreival");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Fetch all documents
$documents = $mysqli->query("SELECT id, content FROM documents");

if ($documents->num_rows > 0) {
    require_once 'Stemmer.php';
    
    // Fetch stop words from the database
    $stop_words = [];
    $result = $mysqli->query("SELECT word FROM stop_words");

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $stop_words[] = $row['word'];
        }
    } else {
        die("Error fetching stop words: " . $mysqli->error);
    }

    // Fetch synonyms from the database
    $synonyms = [];
    $result = $mysqli->query("SELECT word, synonym FROM synonyms");

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $synonyms[$row['word']] = $row['synonym'];
        }
    } else {
        die("Error fetching synonyms: " . $mysqli->error);
    }

   // Fetch total number of documents (for IDF calculation)
$total_documents_query = $mysqli->query("SELECT COUNT(*) AS total FROM documents WHERE indexed = 1");
$total_documents = $total_documents_query->fetch_assoc()['total'];

// Create an array to store the document frequency (DF) for each term
$document_frequency = [];

while ($doc = $documents->fetch_assoc()) {
    $document_id = $doc['id'];
    $content = strtolower($doc['content']); // Normalize to lowercase

    // Tokenize, remove stop words, and apply stemming/lemmatization
    $words = preg_split('/\W+/', $content, -1, PREG_SPLIT_NO_EMPTY);
    $processed_words = [];
    foreach ($words as $word) {
        if (!in_array($word, $stop_words)) {
            $word = $synonyms[$word] ?? $word; // Replace synonym
            $processed_words[] = (new Stemmer())->stem($word); // Apply stemming
        }
    }

    // Calculate term frequency (TF) for the current document
    $term_counts = array_count_values($processed_words);
    $max_frequency = max($term_counts); // Normalization factor
    $tf = [];
    foreach ($term_counts as $term => $count) {
        $tf[$term] = $count / $max_frequency;
    }

    // Update document frequency (DF) for IDF calculation
    $unique_terms = array_keys($term_counts); // Get unique terms in the document
    foreach ($unique_terms as $term) {
        if (!isset($document_frequency[$term])) {
            $document_frequency[$term] = 0;
        }
        $document_frequency[$term]++;
    }

    // Insert TF-IDF values into indexed_documents table
    foreach ($tf as $term => $tf_value) {
        // Calculate IDF
        $doc_count = $document_frequency[$term]; // How many documents contain the term
        $idf = log(($total_documents + 1) / ($doc_count + 1)); // Smoothed IDF formula

        // Calculate TF-IDF value
        $tfidf_value = $tf_value * $idf;

        // Insert or update the TF-IDF value in the indexed_documents table
        $stmt = $mysqli->prepare("INSERT INTO indexed_documents (document_id, term, tfidf_value)
                                  VALUES (?, ?, ?)
                                  ON DUPLICATE KEY UPDATE tfidf_value = ?");
        $stmt->bind_param("isdd", $document_id, $term, $tfidf_value, $tfidf_value);
        $stmt->execute();
    }
}


    // Update document status to "indexed"
    $update_stmt = $mysqli->prepare("UPDATE documents SET indexed = 1 WHERE id = ?");
    $update_stmt->bind_param("i", $document_id);
    if ($update_stmt->execute()) {
        header("Location: admin.php");
        exit;
} else {
    echo "No documents found for indexing!";
}}
?>
