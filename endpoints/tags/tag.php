<?php

require_once '../../includes/connect_endpoint.php';

// Check if tags table exists before doing anything
$tableQuery = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='tags'");
$tagsTableExists = $tableQuery->fetchArray(SQLITE3_ASSOC) !== false;

if (!$tagsTableExists) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Tags table does not exist. Please run migrations first.']);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $requestBody = file_get_contents('php://input');
    $data = json_decode($requestBody, true);
    
    $action = $data['action'] ?? '';
    $tagName = trim($data['name'] ?? '');
    $tagColor = $data['color'] ?? '#666666';
    $tagId = $data['id'] ?? 0;

    switch ($action) {
        case 'create':
            if (empty($tagName)) {
                http_response_code(400);
                echo json_encode(['error' => 'Tag name is required']);
                exit;
            }

            // Check if tag already exists for this user
            $checkQuery = "SELECT id FROM tags WHERE user_id = :userId AND name = :name";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
            $checkStmt->bindValue(':name', $tagName, SQLITE3_TEXT);
            $checkResult = $checkStmt->execute();

            if ($checkResult->fetchArray(SQLITE3_ASSOC)) {
                http_response_code(409);
                echo json_encode(['error' => 'Tag already exists']);
                exit;
            }

            $insertQuery = "INSERT INTO tags (user_id, name, color) VALUES (:userId, :name, :color)";
            $insertStmt = $db->prepare($insertQuery);
            $insertStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
            $insertStmt->bindValue(':name', $tagName, SQLITE3_TEXT);
            $insertStmt->bindValue(':color', $tagColor, SQLITE3_TEXT);
            
            if ($insertStmt->execute()) {
                $newTagId = $db->lastInsertRowID();
                echo json_encode(['success' => true, 'id' => $newTagId, 'name' => $tagName, 'color' => $tagColor]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to create tag']);
            }
            break;

        case 'update':
            if (empty($tagName) || $tagId <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Tag name and ID are required']);
                exit;
            }

            $updateQuery = "UPDATE tags SET name = :name, color = :color WHERE id = :id AND user_id = :userId";
            $updateStmt = $db->prepare($updateQuery);
            $updateStmt->bindValue(':name', $tagName, SQLITE3_TEXT);
            $updateStmt->bindValue(':color', $tagColor, SQLITE3_TEXT);
            $updateStmt->bindValue(':id', $tagId, SQLITE3_INTEGER);
            $updateStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
            
            if ($updateStmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update tag']);
            }
            break;

        case 'delete':
            if ($tagId <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Tag ID is required']);
                exit;
            }

            // Delete tag (CASCADE will remove subscription_tags associations)
            $deleteQuery = "DELETE FROM tags WHERE id = :id AND user_id = :userId";
            $deleteStmt = $db->prepare($deleteQuery);
            $deleteStmt->bindValue(':id', $tagId, SQLITE3_INTEGER);
            $deleteStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
            
            if ($deleteStmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to delete tag']);
            }
            break;

        case 'assign':
            $subscriptionId = $data['subscription_id'] ?? 0;
            if ($tagId <= 0 || $subscriptionId <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Tag ID and subscription ID are required']);
                exit;
            }

            // Verify subscription belongs to user
            $verifyQuery = "SELECT id FROM subscriptions WHERE id = :subscriptionId AND user_id = :userId";
            $verifyStmt = $db->prepare($verifyQuery);
            $verifyStmt->bindValue(':subscriptionId', $subscriptionId, SQLITE3_INTEGER);
            $verifyStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
            $verifyResult = $verifyStmt->execute();

            if (!$verifyResult->fetchArray(SQLITE3_ASSOC)) {
                http_response_code(403);
                echo json_encode(['error' => 'Subscription not found']);
                exit;
            }

            $assignQuery = "INSERT OR IGNORE INTO subscription_tags (subscription_id, tag_id) VALUES (:subscriptionId, :tagId)";
            $assignStmt = $db->prepare($assignQuery);
            $assignStmt->bindValue(':subscriptionId', $subscriptionId, SQLITE3_INTEGER);
            $assignStmt->bindValue(':tagId', $tagId, SQLITE3_INTEGER);
            
            if ($assignStmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to assign tag']);
            }
            break;

        case 'unassign':
            $subscriptionId = $data['subscription_id'] ?? 0;
            if ($tagId <= 0 || $subscriptionId <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Tag ID and subscription ID are required']);
                exit;
            }

            $unassignQuery = "DELETE FROM subscription_tags WHERE subscription_id = :subscriptionId AND tag_id = :tagId";
            $unassignStmt = $db->prepare($unassignQuery);
            $unassignStmt->bindValue(':subscriptionId', $subscriptionId, SQLITE3_INTEGER);
            $unassignStmt->bindValue(':tagId', $tagId, SQLITE3_INTEGER);
            
            if ($unassignStmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to unassign tag']);
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

?>