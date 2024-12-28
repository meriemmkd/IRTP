<?php
$mysqli = new mysqli("localhost", "root", "", "information_retreival");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['document_id'])) {
    $document_id = $_POST['document_id'];

    // Fetch the document content
    $stmt = $mysqli->prepare("SELECT content FROM documents WHERE id = ?");
    $stmt->bind_param("i", $document_id);
    $stmt->execute();
    $doc_result = $stmt->get_result();

    if ($doc_result->num_rows > 0) {
        $doc = $doc_result->fetch_assoc();
        $content = strtolower($doc['content']); // Normalize to lowercase

        // Include utilities for stemming
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

        // Tokenize, remove stop words, and apply stemming/lemmatization
        $words = preg_split('/\W+/', $content, -1, PREG_SPLIT_NO_EMPTY);
        $processed_words = [];
        foreach ($words as $word) {
            if (!in_array($word, $stop_words)) {
                $word = $synonyms[$word] ?? $word; // Replace synonym
                $processed_words[] = (new Stemmer())->stem($word); // Apply stemming
            }
        }

        // Calculate TF for the document
        $term_counts = array_count_values($processed_words);
        $max_frequency = max($term_counts); // Normalization factor
        
        $tf = [];
        foreach ($term_counts as $term => $count) {
            $tf[$term] = $count / $max_frequency;
        }

        // Calculate IDF for the terms across all documents
        $total_documents_query = $mysqli->query("SELECT COUNT(*) as total FROM documents WHERE indexed = 1");
        $total_documents = $total_documents_query->fetch_assoc()['total'];
        
        $idf = [];
        foreach (array_keys($term_counts) as $term) {
            $term_document_query = $mysqli->prepare("SELECT COUNT(*) as doc_count FROM documents WHERE content LIKE ?");
            $like_term = "%$term%";
            $term_document_query->bind_param("s", $like_term);
            $term_document_query->execute();
            $term_doc_count_result = $term_document_query->get_result();
            $doc_count = $term_doc_count_result->fetch_assoc()['doc_count'];

            $idf[$term] = log(($total_documents+1) / ($doc_count +1)); // Avoid division by zero
        }

        foreach ($tf as $term => $tf_value) {
            $tfidf_value = $tf_value * ($idf[$term] ?? 0);
            
            if ($tfidf_value > 1) {
                $tfidf_value -= 1;
                if ($tfidf_value > 1) {
                    $tfidf_value -= 1;
                }
                if ($tfidf_value > 1) {
                    $tfidf_value -= 1;
                }
            }
            if ($tfidf_value < 0) {
                $tfidf_value += 1;
                if ($tfidf_value < 0) {
                    $tfidf_value += 1;
                }
                if ($tfidf_value < 0) {
                    $tfidf_value += 1;
                }
            }
            
          
            // Insert the term and its TF-IDF value into the indexed_documents table
            $stmt = $mysqli->prepare("INSERT INTO indexed_documents (document_id, term, tfidf_value)
                                      VALUES (?, ?, ?)
                                      ON DUPLICATE KEY UPDATE tfidf_value = ?");
            $stmt->bind_param("isdd", $document_id, $term, $tfidf_value, $tfidf_value);
            $stmt->execute();
        }

        // Update document status to "indexed"
        $update_stmt = $mysqli->prepare("UPDATE documents SET indexed = 1 WHERE id = ?");
        $update_stmt->bind_param("i", $document_id);
        if ($update_stmt->execute()) {
            header("Location: admin.php");
            exit;
        } else {
            echo "Error updating document status: " . $update_stmt->error;
        }
    } else {
        echo "Document not found!";
    }
} else {
    echo "Invalid request!";
}
?>
