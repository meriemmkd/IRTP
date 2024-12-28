<?php
$mysqli = new mysqli("localhost", "root", "", "information_retreival");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Handle form submission for adding a document
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_document'])) {
    $title = $_POST['title'];
    $content = $_POST['content'];
    $author = $_POST['author'];
    $publish_date = $_POST['publish_date'];

    $stmt = $mysqli->prepare("INSERT INTO documents (title, content, author, publish_date) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $title, $content, $author, $publish_date);
    if ($stmt->execute()) {
        echo "<div class='alert alert-success'>Document added successfully!</div>";
    } else {
        echo "<div class='alert alert-danger'>Error adding document: " . $stmt->error . "</div>";
    }
}

// Fetch all documents
$documents = $mysqli->query("SELECT * FROM documents");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
    <h1>Admin Dashboard</h1>

    <!-- Add Document Form -->
    <div class="card mb-4">
        <div class="card-header">Add New Document</div>
        <div class="card-body">
            <form method="POST">
                <div class="mb-3">
                    <label for="title" class="form-label">Title</label>
                    <input type="text" id="title" name="title" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="content" class="form-label">Content</label>
                    <textarea id="content" name="content" class="form-control" rows="5" required></textarea>
                </div>
                <div class="mb-3">
                    <label for="author" class="form-label">Author</label>
                    <input type="text" id="author" name="author" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="publish_date" class="form-label">Publish Date</label>
                    <input type="date" id="publish_date" name="publish_date" class="form-control" required>
                </div>
                <button type="submit" name="add_document" class="btn btn-primary">Add Document</button>
            </form>
        </div>
    </div>

    <!-- Document List -->
<div class="card mb-4">
    <div class="card-header">Existing Documents</div>
    <div class="card-body">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Author</th>
                    <th>Publish Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($doc = $documents->fetch_assoc()): ?>
                    <tr>
                        <td><?= $doc['id'] ?></td>
                        <td><?= $doc['title'] ?></td>
                        <td><?= $doc['author'] ?></td>
                        <td><?= $doc['publish_date'] ?></td>
                        <td>
                            <?php if ($doc['indexed'] == 1): ?>
                                <!-- Display "Indexed" if the document is already indexed -->
                                <span class="text-success">Indexed</span>
                                
                            <?php else: ?>
                                <!-- Show the "Index" button if the document is not indexed -->
                                <form method="POST" action="index_single_document.php" style="display:inline;">
                                    <input type="hidden" name="document_id" value="<?= $doc['id'] ?>">
                                    <button type="submit" name="index_single_document" class="btn btn-success btn-sm">Index</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>


    <!-- Button to index all documents -->
    <form method="POST" action="index_documents.php">
        <button type="submit" name="index_all_documents" class="btn btn-info">Index All Documents</button>
    </form>
</div>
</body>
</html>
