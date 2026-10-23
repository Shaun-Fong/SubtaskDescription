<?php

/**
 * SubtaskDescription plugin integration tests.
 *
 * This suite boots a real Kanboard instance (the plugin must already be
 * installed as <kanboard>/plugins/Subtaskdescription), lets Kanboard's plugin
 * loader run, and checks that the plugin behaves like a working extension:
 * metadata, schema migration, hooks, template rendering and model round-trips.
 *
 * It intentionally does not use Composer or PHPUnit: Kanboard ships its runtime
 * dependencies in vendor/, so a plain PHP binary is enough.
 *
 * Usage:
 *     KANBOARD_DIR=/path/to/kanboard php tests/run.php
 *     php tests/run.php /path/to/kanboard
 *
 * Any DB_* / LOG_* environment variables are forwarded to Kanboard's
 * bootstrap, so the same script can test against SQLite, MySQL or PostgreSQL.
 *
 * Exit codes: 0 = success, 1 = assertion failure, 2 = Kanboard could not boot.
 */

declare(strict_types=1);

$kanboardDir = getenv('KANBOARD_DIR');

if ($kanboardDir === false || $kanboardDir === '') {
    $kanboardDir = $argv[1] ?? '';
}

if ($kanboardDir === '' || ! is_file($kanboardDir.'/app/common.php')) {
    fwrite(STDERR, "Kanboard was not found.\n\n");
    fwrite(STDERR, "Set KANBOARD_DIR to a Kanboard checkout, or pass the path as the first argument.\n");
    exit(2);
}

$kanboardDir = rtrim($kanboardDir, '/\\');

$passed = 0;
$failed = array();

/**
 * Record the result of a single assertion.
 *
 * @param string $description Human readable description of the assertion.
 * @param mixed  $condition   Truthy when the assertion is satisfied.
 * @return bool
 */
function check(string $description, $condition): bool
{
    global $passed, $failed;

    if ($condition) {
        $passed++;
        echo "  PASS  {$description}\n";
        return true;
    }

    $failed[] = $description;
    echo "  FAIL  {$description}\n";
    return false;
}

/**
 * Run an assertion that may throw, reporting the exception instead of crashing.
 *
 * @param string   $description Description of the assertion.
 * @param callable $callback    Callback returning true when the assertion holds.
 */
function attempt(string $description, callable $callback): void
{
    try {
        check($description, $callback() === true);
    } catch (Throwable $e) {
        check(
            $description.' (unexpected '.get_class($e).': '.$e->getMessage().')',
            false
        );
    }
}

/**
 * Print an informational, non-failing observation.
 *
 * @param string $message Message to print.
 */
function info(string $message): void
{
    echo "  INFO  {$message}\n";
}

/**
 * Print a section header.
 *
 * @param string $title Section title.
 */
function section(string $title): void
{
    echo "\n{$title}\n".str_repeat('-', strlen($title))."\n";
}

/**
 * Print the final report and return the process exit code.
 *
 * @return int
 */
function report(): int
{
    global $passed, $failed;

    $total = $passed + count($failed);

    echo "\n".str_repeat('=', 64)."\n";

    if (empty($failed)) {
        echo "OK: {$passed} assertions passed\n";
        return 0;
    }

    echo 'FAILED: '.$passed.' passed, '.count($failed)." failed (of {$total} assertions)\n\n";

    foreach ($failed as $message) {
        echo "  - {$message}\n";
        echo "::error title=Failed check::{$message}\n";
    }

    return 1;
}

// Surface crashes and failed assertions as GitHub annotations: the raw log of a
// workflow run is only visible to authenticated users, while annotations are
// rendered on the public job page.
set_exception_handler(static function (Throwable $e): void {
    $message = str_replace("\n", ' ', get_class($e).': '.$e->getMessage());

    fwrite(STDERR, $message."\n".$e->getTraceAsString()."\n");
    echo "::error title=Test suite crashed::{$message}\n";

    exit(255);
});

register_shutdown_function(static function (): void {
    $error = error_get_last();

    if ($error === null || ! in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
        return;
    }

    $message = str_replace("\n", ' ', $error['message'].' in '.$error['file'].':'.$error['line']);
    echo "::error title=Test suite crashed::{$message}\n";
});

section('Environment');

info('PHP '.PHP_VERSION.' on '.PHP_OS_FAMILY);
info('Kanboard: '.$kanboardDir);

check('PHP 8.1 or newer', version_compare(PHP_VERSION, '8.1.0', '>='));

section('Kanboard bootstrap');

try {
    require $kanboardDir.'/app/common.php';
} catch (Throwable $e) {
    fwrite(STDERR, 'Kanboard failed to boot: '.get_class($e).': '.$e->getMessage()."\n");
    exit(2);
}

info('APP_VERSION: '.APP_VERSION);
info('DB_DRIVER: '.DB_DRIVER.' / plugins: '.PLUGINS_DIR);

check('Kanboard container was created', isset($container) && $container instanceof \Pimple\Container);

section('Plugin discovery');

$pluginDir = PLUGINS_DIR.'/Subtaskdescription';

if (! is_dir($pluginDir)) {
    check('plugin directory exists at plugins/Subtaskdescription', false);
    fwrite(STDERR, "\nCopy the plugin to {$pluginDir} before running the tests.\n");
    exit(report());
}

check('plugin directory exists at plugins/Subtaskdescription', true);

attempt('plugin class is loadable', static function () use ($container) {
    $plugin = $container['pluginLoader']->loadPlugin('Subtaskdescription');
    return $plugin instanceof \Kanboard\Core\Plugin\Base;
});

$pluginLoader = $container['pluginLoader'];
$loadedPlugins = $pluginLoader->getPlugins();

check('plugin loader discovered the plugin', array_key_exists('Subtaskdescription', $loadedPlugins));

if (! array_key_exists('Subtaskdescription', $loadedPlugins)) {
    $incompatible = $pluginLoader->getIncompatiblePlugins();

    if (! empty($incompatible)) {
        info('incompatible plugins: '.implode(', ', array_keys($incompatible)));
    }

    // Kanboard logs plugin exceptions instead of failing, so surface the cause.
    attempt('plugin initialize() completes without exceptions', static function () use ($pluginLoader) {
        $plugin = $pluginLoader->loadPlugin('Subtaskdescription');
        $plugin->initialize();
        return true;
    });

    // Everything below depends on an initialized plugin instance.
    exit(report());
}

check('plugin is considered compatible with this Kanboard', empty($pluginLoader->getIncompatiblePlugins()));

$plugin = $loadedPlugins['Subtaskdescription'];

section('Plugin metadata');

check('plugin name is SubtaskDescription', $plugin->getPluginName() === 'SubtaskDescription');
check('plugin description is not empty', trim((string) $plugin->getPluginDescription()) !== '');
check('plugin author is not empty', trim((string) $plugin->getPluginAuthor()) !== '');
check(
    'plugin version is a release version (x.y.z)',
    preg_match('/^\d+\.\d+\.\d+$/', (string) $plugin->getPluginVersion()) === 1
);
info('plugin version: '.$plugin->getPluginVersion());

section('Database schema');

$db = $container['db'];

attempt('subtasks table has a due_description column', static function () use ($db) {
    $db->table('subtasks')->select('due_description')->limit(1)->findAll();
    return true;
});

$schemaRow = $db->table('plugin_schema_versions')
    ->eq('plugin', strtolower($plugin->getPluginName()))
    ->findOne();

// The MySQL schema needs an extra migration (TEXT columns cannot have a
// default value), so the expected version is read from the loaded driver file.
$schemaConstant = 'Kanboard\Plugin\Subtaskdescription\Schema\VERSION';
$expectedSchemaVersion = defined($schemaConstant) ? (int) constant($schemaConstant) : 1;

check(
    "plugin schema version matches the driver schema (expected {$expectedSchemaVersion})",
    is_array($schemaRow) && isset($schemaRow['version']) && (int) $schemaRow['version'] === $expectedSchemaVersion
);

section('Hooks');

$hook = $container['hook'];

check('model:subtask:creation:prepare hook is registered', $hook->exists('model:subtask:creation:prepare'));
check('model:subtask:modification:prepare hook is registered', $hook->exists('model:subtask:modification:prepare'));

$templateHooks = array(
    'template:subtask:form:create' => 'Subtaskdescription:subtask/form',
    'template:subtask:form:edit' => 'Subtaskdescription:subtask/form',
    'template:subtask:table:header:before-timetracking' => 'Subtaskdescription:subtask/table_header',
    'template:subtask:table:rows' => 'Subtaskdescription:subtask/table_rows',
    'template:board:tooltip:subtasks:header:before-assignee' => 'Subtaskdescription:subtask/table_header',
    'template:board:tooltip:subtasks:rows' => 'Subtaskdescription:subtask/table_rows',
);

foreach ($templateHooks as $hookName => $expectedTemplate) {
    $listeners = $hook->getListeners($hookName);
    $templates = array();

    foreach ($listeners as $listener) {
        if (isset($listener['template'])) {
            $templates[] = $listener['template'];
        }
    }

    check(
        "hook {$hookName} renders {$expectedTemplate}",
        in_array($expectedTemplate, $templates, true)
    );
}

section('API procedures');

$procedureClass = 'Kanboard\Plugin\Subtaskdescription\Api\Procedure\SubtaskDescriptionProcedure';

check('API procedure class is autoloadable', class_exists($procedureClass));
check(
    'API procedure extends the Kanboard base procedure',
    is_subclass_of($procedureClass, 'Kanboard\Api\Procedure\BaseProcedure')
);

$accessMap = $container['apiProjectAccessMap'];

foreach (array('createSubtaskWithDescription', 'updateSubtaskDescription') as $apiMethod) {
    $roles = $accessMap->getRoles('SubtaskDescriptionProcedure', $apiMethod);
    check(
        "API method {$apiMethod} requires project-member access",
        in_array('project-member', $roles, true)
    );
    info("roles for {$apiMethod}: ".implode(', ', $roles));
}

section('Template rendering');

$template = $container['template'];

$header = $template->hook->render('template:subtask:table:header:before-timetracking', array());
check('table header hook renders a <th> cell', strpos($header, '<th>') !== false);
check('table header hook labels the column "Description"', strpos($header, 'Description') !== false);

$rows = $template->hook->render('template:subtask:table:rows', array(
    'subtask' => array('due_description' => '**bold** and *italic*'),
));
check('table rows hook renders a <td> cell', strpos($rows, '<td>') !== false);
check('table rows hook renders Markdown', strpos($rows, '<strong>') !== false && strpos($rows, 'bold') !== false);

$emptyRows = $template->hook->render('template:subtask:table:rows', array(
    'subtask' => array('due_description' => ''),
));
check('table rows hook stays empty for an empty description', trim(strip_tags($emptyRows)) === '');

$form = $template->hook->render('template:subtask:form:create', array(
    'values' => array('task_id' => 1),
    'errors' => array(),
));
check('form hook renders the Markdown editor', strpos($form, 'js-text-editor') !== false);
check('form hook binds the editor to due_description', strpos($form, 'due_description') !== false);
check('form hook renders a Description label', strpos($form, 'Description') !== false);

$tooltipRows = $template->hook->render('template:board:tooltip:subtasks:rows', array(
    'subtask' => array('due_description' => 'tooltip **bold**'),
));
check('board tooltip hook renders the description', strpos($tooltipRows, '<strong>') !== false);

section('Model round-trip');

$projectId = 0;

try {
    $projectId = (int) $container['projectModel']->create(array('name' => 'SubtaskDescription integration test'));
} catch (Throwable $e) {
    info('test project creation failed: '.get_class($e).': '.$e->getMessage());
}

check('test project was created', $projectId > 0);

if ($projectId <= 0) {
    exit(report());
}

$columnId = (int) $container['columnModel']->getFirstColumnId($projectId);
check('test project has a board column', $columnId > 0);

// ProjectModel::create() creates the project's default swimlane as well. Using
// its real id keeps the fixture row valid under the foreign keys that MySQL and
// PostgreSQL enforce (tasks.swimlane_id => swimlanes.id).
$swimlaneId = (int) $db->table('swimlanes')
    ->eq('project_id', $projectId)
    ->asc('position')
    ->findOneColumn('id');
check('test project has a default swimlane', $swimlaneId > 0);

// tasks.column_id and tasks.swimlane_id are NOT NULL without a default on MySQL
// and PostgreSQL, and both are foreign keys, so the fixture row has to point at
// the rows the project creation produced.
$taskId = 0;

try {
    $taskId = (int) $db->table('tasks')->persist(array(
        'title' => 'SubtaskDescription integration test task',
        'project_id' => $projectId,
        'column_id' => $columnId,
        'swimlane_id' => $swimlaneId,
        'date_creation' => time(),
    ));
} catch (Throwable $e) {
    info('fixture task insert failed: '.get_class($e).': '.$e->getMessage());
}

check('test task was created', $taskId > 0);

if ($taskId <= 0) {
    exit(report());
}

$subtaskModel = $container['subtaskModel'];

$subtaskId = $subtaskModel->create(array(
    'task_id' => $taskId,
    'title' => 'Created with a description',
    'due_description' => '**markdown** description',
));
check('subtask with a description was created', is_int($subtaskId) && $subtaskId > 0);

$created = $db->table('subtasks')->eq('id', $subtaskId)->findOne();
check(
    'description is stored when a subtask is created',
    is_array($created) && $created['due_description'] === '**markdown** description'
);

$updated = $subtaskModel->update(array(
    'id' => $subtaskId,
    'task_id' => $taskId,
    'title' => 'Updated with a description',
    'due_description' => "first line\nsecond line",
));
check('subtask update succeeded', (bool) $updated);

$stored = $db->table('subtasks')->eq('id', $subtaskId)->findOne();
check(
    'description is stored when a subtask is updated',
    is_array($stored) && $stored['due_description'] === "first line\nsecond line"
);

// Core updateSubtask() and the web UI's status toggle update a subtask without
// sending due_description. That must not clear the stored description.
$toggled = $subtaskModel->update(array(
    'id' => $subtaskId,
    'task_id' => $taskId,
    'status' => 1,
));
check('subtask update without a description succeeds', (bool) $toggled);

$preserved = $db->table('subtasks')->eq('id', $subtaskId)->findOne();
check(
    'description survives an update that does not send it',
    is_array($preserved) && $preserved['due_description'] === "first line\nsecond line"
);

$withoutDescription = $subtaskModel->create(array(
    'task_id' => $taskId,
    'title' => 'Created without a description',
));
check('subtask without a description can still be created', is_int($withoutDescription) && $withoutDescription > 0);

if (is_int($withoutDescription) && $withoutDescription > 0) {
    $plain = $db->table('subtasks')->eq('id', $withoutDescription)->findOne();
    info('omitted description is stored as: '.var_export($plain['due_description'] ?? null, true));
}

exit(report());
