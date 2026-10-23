# SubTask Description

Modify base on [Subtaskdate](https://github.com/eSkiSo/Subtaskdate)

Requirements
------------

- Kanboard >= 1.0.34
- PHP >= 5.3.3

Installation
------------

1. Install the plugin from the Kanboard plugin manager in one click
2. Download the zip file and decompress everything under the directory `plugins/Subtaskdescription`
3. Clone this repository into the folder `plugins/Subtaskdescription`

Note: Plugin folder is case-sensitive.

API
---

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

`This repo will no longer be maintained, but PRs are welcome!`
