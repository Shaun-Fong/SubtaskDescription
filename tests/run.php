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
    }

    return 1;
}

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

check(
    'plugin schema version is recorded as 1',
    is_array($schemaRow) && isset($schemaRow['version']) && (int) $schemaRow['version'] === 1
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

$projectId = $container['projectModel']->create(array('name' => 'SubtaskDescription integration test'));
check('test project was created', is_int($projectId) && $projectId > 0);

$taskId = $db->table('tasks')->persist(array(
    'title' => 'SubtaskDescription integration test task',
    'project_id' => $projectId,
    'date_creation' => time(),
));
check('test task was created', is_int($taskId) && $taskId > 0);

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
