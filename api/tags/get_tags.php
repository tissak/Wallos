<?php

require_once '../../includes/connect_endpoint.php';

$tags = [];

// Check if tags table exists before querying
$tableQuery = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='tags'");
$tagsTableExists = $tableQuery->fetchArray(SQLITE3_ASSOC) !== false;

if ($_SERVER["REQUEST_METHOD"] === "GET" && $tagsTableExists) {
    $query = "SELECT t.*, COUNT(st.subscription_id) as count 
              FROM tags t 
              LEFT JOIN subscription_tags st ON t.id = st.tag_id 
              LEFT JOIN subscriptions s ON st.subscription_id = s.id AND s.user_id = :userId
              WHERE t.user_id = :userId 
              GROUP BY t.id, t.name 
              ORDER BY t.name ASC";
    
    $stmt = $db->prepare($query);
    $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();

    if ($result) {
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $tags[] = $row;
        }
    }
}

header('Content-Type: application/json');
echo json_encode($tags);

?>