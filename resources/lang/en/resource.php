<?php

return [
    'navigation_label'   => 'TickTick Tasks',
    'model_label'        => 'TickTick Task',
    'plural_model_label' => 'TickTick Tasks',
    'subtask_label'      => 'Sub-task',

    'fields' => [
        'title'       => 'Title',
        'content'     => 'Content',
        'start_date'  => 'Start Date',
        'due_date'    => 'Due Date',
        'priority'    => 'Priority',
        'status'      => 'Status',
        'project_id'  => 'Project',
        'projects'    => 'Projects',
        'inbox'       => 'Inbox',
        'tags'        => 'Tags',
        'ticktick_id' => 'TickTick ID',
        'created_at'  => 'Created At',
        'updated_at'  => 'Updated At',
        'subtasks'    => 'Sub-tasks',
    ],

    'actions' => [
        'pull'        => 'Import from TickTick',
        'pull_submit' => 'Import',
        'complete'    => 'Complete',
        'reopen'      => 'Reopen',
    ],

    'notifications' => [
        'pulled' => 'Imported :count tasks from TickTick',
        'failed' => 'TickTick request failed',

        'errors' => [
            'missing_token' => 'No TickTick access token is configured. Set TICKTICK_ACCESS_TOKEN in the .env file.',
            'connection'    => 'Could not connect to TickTick. Please try again later.',
            'unauthorized'  => 'TickTick rejected the access token. It may have expired or been revoked.',
            'not_found'     => 'The task or project no longer exists in TickTick.',
            'rate_limited'  => 'Too many requests to TickTick. Please wait a moment and try again.',
            'server_error'  => 'TickTick is not responding correctly. Please try again later.',
            'unexpected'    => 'An unexpected error occurred while talking to TickTick.',
        ],
    ],

    'sections' => [
        'task_details' => 'Task Details',
    ],
];
