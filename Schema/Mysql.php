<?php

namespace Kanboard\Plugin\Subtaskdescription\Schema;

use PDO;

const VERSION = 1;

function version_1(PDO $pdo)
{
    $pdo->exec("ALTER TABLE `subtasks` ADD COLUMN `due_description` TEXT NOT NULL ");
}
