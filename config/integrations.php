<?php

return [
    'max_polls' => 30,
    'poll_delay' => 10,
    'providers' => [
        'firecrawl' => [
            'api_key' => env('FIRECRAWL_API_KEY', ''),
            'base_url' => 'https://api.firecrawl.dev/v2',
        ],
        'browse-ai' => [
            'api_key' => env('BROWSE_AI_API_KEY', ''),
            'base_url' => 'https://api.browse.ai/v2',
            'robot_id' => env('BROWSE_AI_ROBOT_ID', ''),
            'url_parameter' => env('BROWSE_AI_URL_PARAMETER', 'originUrl'),
        ],
        'scrapegraphai' => [
            'api_key' => env('SCRAPEGRAPH_API_KEY', ''),
            'base_url' => 'https://v2-api.scrapegraphai.com/api',
        ],
    ],
];
