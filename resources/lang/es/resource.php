<?php

return [
    'navigation_label'   => 'Tareas de TickTick',
    'model_label'        => 'Tarea de TickTick',
    'plural_model_label' => 'Tareas de TickTick',
    'subtask_label'      => 'Subtarea',

    'fields' => [
        'title'       => 'Título',
        'content'     => 'Contenido',
        'start_date'  => 'Fecha de Inicio',
        'due_date'    => 'Fecha de Vencimiento',
        'priority'    => 'Prioridad',
        'status'      => 'Estado',
        'project_id'  => 'Proyecto',
        'projects'    => 'Proyectos',
        'inbox'       => 'Bandeja de entrada',
        'tags'        => 'Etiquetas',
        'ticktick_id' => 'ID de TickTick',
        'created_at'  => 'Creado el',
        'updated_at'  => 'Actualizado el',
        'subtasks'    => 'Subtareas',
    ],

    'actions' => [
        'pull'        => 'Importar desde TickTick',
        'pull_submit' => 'Importar',
        'complete'    => 'Completar',
        'reopen'      => 'Reabrir',
    ],

    'notifications' => [
        'pulled' => 'Se han importado :count tareas desde TickTick',
        'failed' => 'Error en la petición a TickTick',

        'errors' => [
            'missing_token' => 'No hay ningún token de acceso de TickTick configurado. Define TICKTICK_ACCESS_TOKEN en el archivo .env.',
            'connection'    => 'No se ha podido conectar con TickTick. Inténtalo de nuevo más tarde.',
            'unauthorized'  => 'TickTick ha rechazado el token de acceso. Puede que haya caducado o se haya revocado.',
            'not_found'     => 'La tarea o el proyecto ya no existe en TickTick.',
            'rate_limited'  => 'Demasiadas peticiones a TickTick. Espera un momento y vuelve a intentarlo.',
            'server_error'  => 'TickTick no está respondiendo correctamente. Inténtalo de nuevo más tarde.',
            'unexpected'    => 'Se ha producido un error inesperado al comunicarse con TickTick.',
        ],
    ],

    'sections' => [
        'task_details' => 'Detalles de la Tarea',
    ],
];
