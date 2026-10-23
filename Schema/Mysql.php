<?php

namespace Kanboard\Plugin\Subtaskdescription\Schema;

use PDO;

const VERSION = 2;

function version_1(PDO $pdo)
{
    $pdo->exec("ALTER TABLE `subtasks` ADD COLUMN `due_description` TEXT NOT NULL ");
}

function version_2(PDO $pdo)
{
    // TEXT columns cannot have a default value before MySQL 8.0.13, so allow NULL instead.
    // Otherwise inserts without due_description (e.g. API createSubtask) fail in strict mode (error 1364).
    $pdo->exec("ALTER TABLE `subtasks` MODIFY `due_description` TEXT NULL");
}
