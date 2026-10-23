# SubTask Description

Adds a Markdown description field to Kanboard subtasks.

[![Tests](https://github.com/Shaun-Fong/SubtaskDescription/actions/workflows/tests.yml/badge.svg)](https://github.com/Shaun-Fong/SubtaskDescription/actions/workflows/tests.yml)
[![Latest release](https://img.shields.io/github/v/release/Shaun-Fong/SubtaskDescription)](https://github.com/Shaun-Fong/SubtaskDescription/releases)

Based on [Subtaskdate](https://github.com/eSkiSo/Subtaskdate).

## Requirements

- Kanboard 1.2.x or newer (CI runs the plugin against the latest Kanboard release and against Kanboard `main`)
- PHP >= 8.1 (required by Kanboard 1.2)

Older Kanboard versions may still work, but they are not tested.

## Installation

1. Install the plugin from the Kanboard plugin manager (one click), or
2. Download `Subtaskdescription-<version>.zip` from the [latest release](https://github.com/Shaun-Fong/SubtaskDescription/releases)
   and extract it under the directory `plugins/` of your Kanboard installation, or
3. Clone this repository into `plugins/Subtaskdescription`:

   ```
   cd /path/to/kanboard/plugins
   git clone https://github.com/Shaun-Fong/SubtaskDescription.git Subtaskdescription
   ```

Note: the plugin folder is case-sensitive and must be named `Subtaskdescription`.

## Usage

- A **Description** field (Markdown editor) is added to the subtask creation and edition forms.
- The description is rendered as Markdown in the subtask table of the task details view and in the subtask tooltips on the board.
- On Kanboard versions up to 1.0.40 the description is also shown in the dashboard subtasks widget.

## API

The core `createSubtask` and `updateSubtask` methods ignore `due_description`, so the plugin adds two JSON-RPC methods.
They require the same project role as the core subtask methods (project member). `getSubtask` and `getAllSubtasks`
already return `due_description`.

### createSubtaskWithDescription

Parameters: `task_id` (required), `title` (required), `due_description`, `user_id`, `time_estimated`, `time_spent`, `status`

Result on success: subtask id. Result on failure: `false`

```json
{"jsonrpc":"2.0","id":1,"method":"createSubtaskWithDescription","params":{"task_id":2139,"title":"Lobby test","due_description":"Check with **4 players**","status":0}}
```

### updateSubtaskDescription

Parameters: `id` (required), `task_id` (required), `due_description` (required, `""` clears it)

Result on success: `true`. Result on failure: `false`

```json
{"jsonrpc":"2.0","id":1,"method":"updateSubtaskDescription","params":{"id":42,"task_id":2139,"due_description":"Updated text"}}
```

## Development

| Path | Purpose |
| --- | --- |
| `Plugin.php` | Plugin metadata, hooks and model callbacks |
| `Schema/` | Database migration for the `due_description` column |
| `Template/subtask/` | Form field and table cells for the description |
| `tests/run.php` | Standalone integration test suite |
| `scripts/check-archive.sh` | Validates the layout of the release archive |
| `Makefile` | Builds the installable archive with `git archive` |

### Tests

The test suite is a plain PHP script: it boots a real Kanboard instance, lets the plugin loader, the
schema migration and the hooks run, and checks metadata, schema, hook registration, template rendering
and subtask create/update round-trips. It supports SQLite, MySQL and PostgreSQL.

Tests are executed by GitHub Actions on every push and pull request — no local PHP installation and no
Kanboard copy in this repository is needed. The workflow:

- lints every PHP file on PHP 8.1, 8.2, 8.3 and 8.4;
- clones the latest Kanboard (`main`) and runs the suite against SQLite, MySQL and PostgreSQL;
- clones the latest Kanboard *release* and runs the suite against SQLite;
- builds the release archive and validates its layout;
- runs weekly on a schedule to catch upstream Kanboard breakage.

Running the suite locally is optional. It requires a Kanboard checkout and PHP >= 8.1:

```
git clone --depth 1 https://github.com/kanboard/kanboard.git /tmp/kanboard
cp -R Plugin.php Schema Template LICENSE /tmp/kanboard/plugins/Subtaskdescription/
KANBOARD_DIR=/tmp/kanboard php tests/run.php
```

Set `DB_DRIVER` (and the matching `DB_*` variables) to test another database:

```
DB_DRIVER=mysql DB_HOSTNAME=127.0.0.1 DB_USERNAME=root DB_PASSWORD=root DB_NAME=kanboard \
  KANBOARD_DIR=/tmp/kanboard php tests/run.php
```

### Release process

Releases are produced by the `Release` workflow, not by hand:

1. Bump `getPluginVersion()` in `Plugin.php` (for example `1.2.0`) and merge the change.
2. Create an annotated tag that matches the version and push it:

   ```
   git tag -a v1.2.0 -m "SubTask Description 1.2.0"
   git push origin v1.2.0
   ```

3. The workflow verifies that the tag matches `Plugin.php`, runs the test suite against the latest
   Kanboard, builds `Subtaskdescription-1.2.0.zip` with the Makefile, checks the archive layout and
   publishes the GitHub release with that archive.

To rebuild or re-upload the archive of an existing release, run the `Release` workflow manually
(Actions → Release → Run workflow) and pass the tag (for example `v1.2.0`).

## License

MIT — see [LICENSE](LICENSE).

`This repo will no longer be maintained, but PRs are welcome!`
