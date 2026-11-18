<?php

return [
    'credentials' => env('GOOGLE_APPLICATION_CREDENTIALS'),
    'sheets' => [
        'template_id' => env('GOOGLE_SHEETS_TEMPLATE_ID'),
        'folder_id' => env('GOOGLE_DRIVE_FOLDER_ID'),
        'mapping' => [
            'numero' => 'B2',
            'data_emissao' => 'B3',
            'situacao' => 'B4',
            'transportadora' => 'B5',
            'filial' => 'B6',
            'cliente' => 'B7',
            'motorista' => 'B8',
            'transportador' => 'B9',
            'veiculo' => 'B10',
            'implementos' => 'B11',
            'tipo_composicao' => 'B12',
            'tipo_carroceria' => 'B13',
            'produto' => 'B14',
            'quantidade' => 'B15',
            'unidade_medida' => 'B16',
            'origem' => 'B17',
            'local_carregamento' => 'B18',
            'data_carregamento' => 'B19',
            'destino' => 'B20',
            'local_descarga' => 'B21',
            'data_previsao_entrega' => 'B22',
            'observacoes' => 'B23',
        ],
    ],
    'timeout' => (int) env('GOOGLE_API_TIMEOUT', 15),
];
