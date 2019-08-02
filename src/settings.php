<?php
return [
    'settings' => [
        'displayErrorDetails' => false, // set to false in production
        'addContentLengthHeader' => false, // Allow the web server to send the content-length header

        // Renderer settings
        'renderer' => [
            'template_path' => __DIR__ . '/../templates/',
        ],

        // Monolog settings
        'logger' => [
            'name' => 'littleking',
            'path' => isset($_ENV['docker']) ? 'php://stdout' : __DIR__ . '/../logs/app.log',
            'level' => \Monolog\Logger::INFO,
        ],

        // Database settings
        'db' => [
            'host'   => 'localhost',
            'user'   => 'littleking',
            'pass'   => 'V4xZ2UNC',
            'dbname' => 'littleking'
        ],
    ],
];
