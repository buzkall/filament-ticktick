<?php

return [
    'navigation_label'   => 'Tasques de TickTick',
    'model_label'        => 'Tasca de TickTick',
    'plural_model_label' => 'Tasques de TickTick',
    'subtask_label'      => 'Subtasca',

    'fields' => [
        'title'       => 'Títol',
        'content'     => 'Contingut',
        'start_date'  => "Data d'inici",
        'due_date'    => 'Data de venciment',
        'priority'    => 'Prioritat',
        'status'      => 'Estat',
        'project_id'  => 'Projecte',
        'projects'    => 'Projectes',
        'inbox'       => "Safata d'entrada",
        'tags'        => 'Etiquetes',
        'ticktick_id' => 'ID de TickTick',
        'created_at'  => 'Creat el',
        'updated_at'  => 'Actualitzat el',
        'subtasks'    => 'Subtasques',
    ],

    'actions' => [
        'pull'        => 'Importa des de TickTick',
        'pull_submit' => 'Importa',
        'complete'    => 'Completa',
        'reopen'      => 'Reobre',
    ],

    'notifications' => [
        'pulled' => "S'han importat :count tasques des de TickTick",
        'failed' => 'Error en la petició a TickTick',

        'errors' => [
            'missing_token' => "No hi ha cap token d'accés de TickTick configurat. Defineix TICKTICK_ACCESS_TOKEN al fitxer .env.",
            'connection'    => "No s'ha pogut connectar amb TickTick. Torna-ho a provar més tard.",
            'unauthorized'  => "TickTick ha rebutjat el token d'accés. Pot ser que hagi caducat o que s'hagi revocat.",
            'not_found'     => 'La tasca o el projecte ja no existeix a TickTick.',
            'rate_limited'  => 'Massa peticions a TickTick. Espera un moment i torna-ho a provar.',
            'server_error'  => 'TickTick no respon correctament. Torna-ho a provar més tard.',
            'unexpected'    => "S'ha produït un error inesperat en comunicar-se amb TickTick.",
        ],
    ],

    'sections' => [
        'task_details' => 'Detalls de la tasca',
    ],
];
