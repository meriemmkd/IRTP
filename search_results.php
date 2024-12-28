<?php
$mysqli = new mysqli("localhost", "root", "", "information_retreival");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Fetch stop words and synonyms
$stop_words = [];
$synonyms = [];

// Fetch stop words from the database
$result = $mysqli->query("SELECT word FROM stop_words");
while ($row = $result->fetch_assoc()) {
    $stop_words[] = $row['word'];
}

// Fetch synonyms from the database
$result = $mysqli->query("SELECT word, synonym FROM synonyms");
while ($row = $result->fetch_assoc()) {
    $synonyms[$row['word']] = $row['synonym'];
}
require_once 'Stemmer.php';
// Check if a query was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['query'])) {
    $query = strtolower($_POST['query']);  // Normalize to lowercase

    // Tokenize, remove stop words, and apply stemming/lemmatization
    $words = preg_split('/\W+/', $query, -1, PREG_SPLIT_NO_EMPTY);
    $processed_words = [];
    foreach ($words as $word) {
        if (!in_array($word, $stop_words)) {
            $word = $synonyms[$word] ?? $word; // Replace synonym
            $processed_words[] = (new Stemmer())->stem($word); // Apply stemming
        }
    }

    // Calculate TF-IDF for the query (same logic as for documents)
    $query_terms = array_count_values($processed_words);  // Term frequency for the query

    // Fetch total number of documents (for IDF calculation)
    $total_documents_query = $mysqli->query("SELECT COUNT(*) AS total FROM documents WHERE indexed = 1");
    $total_documents = $total_documents_query->fetch_assoc()['total'];

    // Calculate TF-IDF values for the query terms
    $query_tfidf = [];
    foreach ($query_terms as $term => $count) {
        // Calculate term frequency (TF)
        $tf = $count / max($query_terms);  // Normalize by the highest frequency in the query

        // Calculate IDF for each term
        $doc_count_query = $mysqli->prepare("SELECT COUNT(*) AS doc_count FROM indexed_documents WHERE term = ?");
        $doc_count_query->bind_param("s", $term);
        $doc_count_query->execute();
        $doc_count_result = $doc_count_query->get_result();
        $doc_count = $doc_count_result->fetch_assoc()['doc_count'];

        $idf = log($total_documents / ($doc_count ?: 1));  // IDF formula
        $query_tfidf[$term] = $tf * $idf;  // TF-IDF for the query term
    }

    // Calculate cosine similarity between the query and each document
    $results = [];
    $documents_query = $mysqli->query("SELECT * FROM documents WHERE indexed = 1");

    while ($doc = $documents_query->fetch_assoc()) {
        $document_id = $doc['id'];
        $document_title = $doc['title'];
        $document_author = $doc['author'];
        $document_content = $doc['content'];
        $document_publish_date = $doc['publish_date'];
        $document_tfidf = [];

        // Get the TF-IDF values for the document from the indexed_documents table
        $stmt = $mysqli->prepare("SELECT term, tfidf_value FROM indexed_documents WHERE document_id = ?");
        $stmt->bind_param("i", $document_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $document_tfidf[$row['term']] = $row['tfidf_value'];
        }

        // Calculate cosine similarity between the query and document TF-IDF vectors
        $dot_product = 0;
        $query_magnitude = 0;
        $doc_magnitude = 0;
        foreach ($query_tfidf as $term => $query_value) {
            if (isset($document_tfidf[$term])) {
                $dot_product += $query_value * $document_tfidf[$term];
            }
            $query_magnitude += $query_value * $query_value;
        }

        foreach ($document_tfidf as $doc_value) {
            $doc_magnitude += $doc_value * $doc_value;
        }

        $query_magnitude = sqrt($query_magnitude);
        $doc_magnitude = sqrt($doc_magnitude);

        if ($query_magnitude > 0 && $doc_magnitude > 0) {
            $cosine_similarity = $dot_product / ($query_magnitude * $doc_magnitude);
        } else {
            $cosine_similarity = 0;
        }

        // Store the result with its cosine similarity score
        if ($cosine_similarity > 0) {
            $results[] = [
                'document_id' => $doc['id'],
                'title' => $document_title,
                'author' => $document_author,
                'content' => $document_content,
                'similarity' => $cosine_similarity,
                'publish_date' => $document_publish_date
            ];
        }
    }

   // Fetch preferences from the database for the given query term
$query_term = $mysqli->real_escape_string($query); // Assuming $search_query contains the user's query
$preferences_query = $mysqli->prepare("
    SELECT document_id, preference_count
    FROM user_preference
    WHERE query_term = ?
    ORDER BY preference_count DESC
");

$preferred_documents = [];
$preference_counts = [];

$preferences_query->bind_param("s", $query_term);
$preferences_query->execute();
$preferences_result = $preferences_query->get_result();

while ($row = $preferences_result->fetch_assoc()) {
    $preferred_documents[] = $row['document_id'];
    $preference_counts[$row['document_id']] = $row['preference_count']; // Store preference count
}






// Add a 'preferred' flag to each result based on user preferences
foreach ($results as &$result) {
    $result['preferred'] = in_array($result['document_id'], $preferred_documents) ? 1 : 0;
}
unset($result); // Break reference to avoid accidental modification

// Sort results: prioritize preferred documents, then sort by similarity
// Sort results: prioritize based on preference_count first, then sort by similarity
usort($results, function ($a, $b) use ($preference_counts) {
    // Sort by preference_count (higher first)
    $a_pref_count = isset($preference_counts[$a['document_id']]) ? $preference_counts[$a['document_id']] : 0;
    $b_pref_count = isset($preference_counts[$b['document_id']]) ? $preference_counts[$b['document_id']] : 0;

    if ($b_pref_count !== $a_pref_count) {
        return $b_pref_count <=> $a_pref_count;
    }

    // If both have the same preference_count, sort by similarity
    return $b['similarity'] <=> $a['similarity'];
});


}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results</title>
    <link rel="stylesheet" href="css/searchResult.css">

</head>
<body>
    <h1>Search Results</h1>
    <table border="1">
    <thead>
    <tr>
        <th>Document ID</th>
        <th>Title</th>
        <th>Author</th>
        <th>Publish Date</th>
        <th>Action</th>
    </tr>
</thead>
<tbody>
    <?php if (!empty($results)): ?>
        <?php foreach ($results as $result): ?>
            <tr>
                <td><?= $result['document_id'] ?></td>
                <td><?= $result['title'] ?></td>
                <td><?= $result['author'] ?></td>
                <td><?= $result['publish_date'] ?></td>
                <td>
                    <form method="POST" action="update_preference.php">
                        <input type="hidden" name="document_id" value="<?= $result['document_id'] ?>">
                        <input type="hidden" name="query_term" value="<?= htmlspecialchars($query) ?>">
                        <button type="submit" class="btn btn-primary">Check</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="6">No results found for your query.</td>
        </tr>
    <?php endif; ?>
</tbody>

    </table>
    <br>
    <a href="User.php">Go back to search</a>
</body>
</html>
