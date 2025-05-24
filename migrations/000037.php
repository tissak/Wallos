<?php
// This migration adds tags functionality to allow users to tag subscriptions for better organization and filtering

/** @noinspection PhpUndefinedVariableInspection */
$tableQuery = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='tags'");
$tagsTableRequired = $tableQuery->fetchArray(SQLITE3_ASSOC) === false;

if ($tagsTableRequired) {
    $db->exec('CREATE TABLE tags (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        color TEXT DEFAULT "#666666",
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(user_id, name)
    )');
}

/** @noinspection PhpUndefinedVariableInspection */
$junctionTableQuery = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='subscription_tags'");
$junctionTableRequired = $junctionTableQuery->fetchArray(SQLITE3_ASSOC) === false;

if ($junctionTableRequired) {
    $db->exec('CREATE TABLE subscription_tags (
        subscription_id INTEGER NOT NULL,
        tag_id INTEGER NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (subscription_id, tag_id),
        FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE,
        FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
    )');
}

?>