<?php

use App\Models\Triumph\Events;
use App\Models\Triumph\Games;
use App\Models\Triumph\PlatformGames;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Search Engine
    |--------------------------------------------------------------------------
    |
    | This option controls the default search connection that gets used while
    | using Laravel Scout. This connection is used when syncing all models
    | to the search service. You should adjust this based on your needs.
    |
    | Supported: "algolia", "meilisearch", "typesense",
    |            "database", "collection", "null"
    |
    */

    'driver' => env('SCOUT_DRIVER', 'typesense'),

    /*
    |--------------------------------------------------------------------------
    | Index Prefix
    |--------------------------------------------------------------------------
    |
    | Here you may specify a prefix that will be applied to all search index
    | names used by Scout. This prefix may be useful if you have multiple
    | "tenants" or applications sharing the same search infrastructure.
    |
    */

    'prefix' => env('SCOUT_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Queue Data Syncing
    |--------------------------------------------------------------------------
    |
    | This option allows you to control if the operations that sync your data
    | with your search engines are queued. When this is set to "true" then
    | all automatic data syncing will get queued for better performance.
    |
    */

    'queue' => env('SCOUT_QUEUE', false),

    /*
    |--------------------------------------------------------------------------
    | Database Transactions
    |--------------------------------------------------------------------------
    |
    | This configuration option determines if your data will only be synced
    | with your search indexes after every open database transaction has
    | been committed, thus preventing any discarded data from syncing.
    |
    */

    'after_commit' => false,

    /*
    |--------------------------------------------------------------------------
    | Chunk Sizes
    |--------------------------------------------------------------------------
    |
    | These options allow you to control the maximum chunk size when you are
    | mass importing data into the search engine. This allows you to fine
    | tune each of these chunk sizes based on the power of the servers.
    |
    */

    'chunk' => [
        'searchable' => 500,
        'unsearchable' => 500,
    ],

    /*
    |--------------------------------------------------------------------------
    | Soft Deletes
    |--------------------------------------------------------------------------
    |
    | This option allows to control whether to keep soft deleted records in
    | the search indexes. Maintaining soft deleted records can be useful
    | if your application still needs to search for the records later.
    |
    */

    'soft_delete' => false,

    /*
    |--------------------------------------------------------------------------
    | Identify User
    |--------------------------------------------------------------------------
    |
    | This option allows you to control whether to notify the search engine
    | of the user performing the search. This is sometimes useful if the
    | engine supports any analytics based on this application's users.
    |
    | Supported engines: "algolia"
    |
    */

    'identify' => env('SCOUT_IDENTIFY', false),

    /*
    |--------------------------------------------------------------------------
    | Algolia Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your Algolia settings. Algolia is a cloud hosted
    | search engine which works great with Scout out of the box. Just plug
    | in your application ID and admin API key to get started searching.
    |
    */

    'algolia' => [
        'id' => env('ALGOLIA_APP_ID', ''),
        'secret' => env('ALGOLIA_SECRET', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Meilisearch Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your Meilisearch settings. Meilisearch is an open
    | source search engine with minimal configuration. Below, you can state
    | the host and key information for your own Meilisearch installation.
    |
    | See: https://www.meilisearch.com/docs/learn/configuration/instance_options#all-instance-options
    |
    */

    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://localhost:7700'),
        'key' => env('MEILISEARCH_KEY'),
        'index-settings' => [
            // 'users' => [
            //     'filterableAttributes'=> ['id', 'name', 'email'],
            // ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Typesense Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your Typesense settings. Typesense is an open
    | source search engine using minimal configuration. Below, you will
    | state the host, key, and schema configuration for the instance.
    |
    */

    'typesense' => [
        'client-settings' => [
            'api_key' => env('TYPESENSE_API_KEY', 'xyz'),
            'nodes' => [
                [
                    'host' => env('TYPESENSE_HOST', 'localhost'),
                    'port' => env('TYPESENSE_PORT', '8108'),
                    'path' => env('TYPESENSE_PATH', ''),
                    'protocol' => env('TYPESENSE_PROTOCOL', 'http'),
                ],
            ],
            'nearest_node' => [
                'host' => env('TYPESENSE_HOST', 'localhost'),
                'port' => env('TYPESENSE_PORT', '8108'),
                'path' => env('TYPESENSE_PATH', ''),
                'protocol' => env('TYPESENSE_PROTOCOL', 'http'),
            ],
            'connection_timeout_seconds' => env('TYPESENSE_CONNECTION_TIMEOUT_SECONDS', 2),
            'healthcheck_interval_seconds' => env('TYPESENSE_HEALTHCHECK_INTERVAL_SECONDS', 30),
            'num_retries' => env('TYPESENSE_NUM_RETRIES', 3),
            'retry_interval_seconds' => env('TYPESENSE_RETRY_INTERVAL_SECONDS', 1),
        ],
        'model-settings' => [
            Events::class => [
                'collection-schema' => [
                    'fields' => [
                        [
                            'name' => 'id',
                            'type' => 'string'
                        ],
                        [
                            'name' => 'event_id',
                            'type' => 'int32',
                            'sortable' => true, // 정렬 옵션 추가, 숫자일경우 기본적으로 정렬기능 허용이 가능함
                        ],
                        [
                            'name' => 'title',
                            'type' => 'string',
                        ],
                        [
                            'name' => 'description',
                            'type' => 'string',
                        ],
                        [
                            'name' => 'format',
                            'type' => 'int32',
                            'facet' => true // 필터링 사용 설정
                        ],
                        [
                            'name' => 'team_size',
                            'type' => 'int32',
                            'facet' => true // 필터링 사용 설정
                        ],
                        [
                            'name' => 'status',
                            'type' => 'int32',
                            'facet' => true, // 필터링 사용 설정
                            'sortable' => true, // 정렬 옵션 추가, 숫자일경우 기본적으로 정렬기능 허용이 가능함
                        ],
                        [
                            'name' => 'event_start_dt',
                            'type' => 'int32',
                            'facet' => true, // 필터링 사용 설정
                            'sortable' => true, // 정렬 옵션 추가, 숫자일경우 기본적으로 정렬기능 허용이 가능함
                        ],
                        [
                            'name' => 'games_game_id',
                            'type' => 'int32',
                            'facet' => true // 필터링 사용 설정
                        ],
                        [
                            'name' => 'games_name',
                            'type' => 'string',
                            'facet' => true // 필터링 사용 설정
                        ],
                        [
                            'name' => 'embedding',
                            'type' => 'float[]',
                            'embed' => [
                                'from' => [
                                    'title',
                                    'description'
                                ],
                                'model_config' => [
                                    'model_name' => 'ts/distiluse-base-multilingual-cased-v2' // 다국어 및 경량화버전
                                ]
                            ]
                        ],
                    ],
                    'default_sorting_field' => 'event_start_dt', // 기존 정렬순서는 최신순으로 진행
                ],
                'search-parameters' => [
                    'query_by' => 'title,description'
                ],
            ],
            Games::class => [
                'collection-schema' => [
                    'fields' => [
                        [
                            'name' => 'id',
                            'type' => 'string'
                        ],
                        [
                            'name' => 'game_id',
                            'type' => 'int32',
                            'facet' => true // 필터링 사용 설정
                        ],
                        [
                            'name' => 'game_name',
                            'type' => 'string',
                        ],
                    ],
                    'default_sorting_field' => 'game_id', // 기존 정렬순서는 최신순으로 진행
                ],
                'search-parameters' => [
                    'query_by' => 'game_name'
                ],
            ],
        ],
    ],

];
