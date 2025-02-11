<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    |
    | The base URL for all of your endpoints.
    |
    */

    'base_url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Collection Filename
    |--------------------------------------------------------------------------
    |
    | The name for the collection file to be saved.
    |
    */

    'filename' => '{timestamp}_{app}_{format}_collection.json',

    /*
    |--------------------------------------------------------------------------
    | Collection Name
    |--------------------------------------------------------------------------
    |
    | The name for the collection.
    |
    */

    'name' => 'Laravel API Collection',

    /*
    |--------------------------------------------------------------------------
    | Structured
    |--------------------------------------------------------------------------
    |
    | If you want folders to be generated based on namespace/group.
    |
    |
    */

    'structured' => false,

    /*
    |--------------------------------------------------------------------------
    | Auth Middleware
    |--------------------------------------------------------------------------
    |
    | The middleware which wraps your authenticated API routes.
    |
    | E.g. auth:api, auth:sanctum
    |
    */

    'auth_middleware' => 'auth:api',

    /*
    |--------------------------------------------------------------------------
    | Headers
    |--------------------------------------------------------------------------
    |
    | The headers applied to all routes within the collection.
    |
    */

    'headers' => [
        [
            'key' => 'Accept',
            'value' => 'application/json',
        ],
        [
            'key' => 'Content-Type',
            'value' => 'application/json',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Scripts
    |--------------------------------------------------------------------------
    |
    | If you want to configure the pre-request, test and post-response scripts for the collection,
    | then please provide paths to the JavaScript files.
    |
    */
    'scripts' => [
        'pre-request' => [
            'path' => '',
            'content' => '',
            'enabled' => false,
        ],
        'post-response' => [
            'path' => '',
            'content' => '',
            'enabled' => false,
        ],
        'test' => [
            'path' => '',
            'content' => '',
            'enabled' => false,
        ],
    ],


    /*
    |--------------------------------------------------------------------------
    | Body Mode
    |--------------------------------------------------------------------------
    |
    | The default body mode for requests. Available options are:
    | 'raw', 'urlencoded', 'formdata', 'file', 'graphql', 'none'
    |
    */
    'body_mode' => env('COLLECTION_BODY_MODE', 'raw'),

    /*
    |--------------------------------------------------------------------------
    | Body Options
    |--------------------------------------------------------------------------
    |
    | Additional options for request bodies.
    |
    */

    'body_options' => [
        'raw' => [
            'language' => 'json'
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Include Doc Comments
    |--------------------------------------------------------------------------
    |
    | Determines whether to set the PHP Doc comments to the description
    | in postman.
    |
    */

    'include_doc_comments' => false,

    /*
    |--------------------------------------------------------------------------
    | Enable Form Data
    |--------------------------------------------------------------------------
    |
    | Determines whether form data should be handled.
    |
    */

    'enable_formdata' => true,

    /*
    |--------------------------------------------------------------------------
    | Parse Form Request Rules
    |--------------------------------------------------------------------------
    |
    | If you want form requests to be printed in the field description field,
    | and if so, whether they will be in a human-readable form.
    |
    */

    'print_rules' => true, // @requires: 'enable_formdata' ===  true
    'rules_to_human_readable' => true, // @requires: 'parse_rules' ===  true

    /*
    |--------------------------------------------------------------------------
    | Form Data
    |--------------------------------------------------------------------------
    |
    | The key/values to requests for form data dummy information.
    |
    */

    'formdata' => [
        // 'email' => 'john@example.com',
        // 'password' => 'changeme',
    ],

    /*
    |--------------------------------------------------------------------------
    | Include Middleware
    |--------------------------------------------------------------------------
    |
    | The routes of the included middleware are included in the export.
    |
    */

    'include_middleware' => ['api'],

    /*
    |--------------------------------------------------------------------------
    | Disk Driver
    |--------------------------------------------------------------------------
    |
    | Specify the configured disk for storing the collection files.
    |
    */

    'disk' => 'local',

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | Specify the authentication to be used for the endpoints.
    |
    */

    'authentication' => [
        'method' => env('POSTMAN_EXPORT_AUTH_METHOD'),
        'token' => env('POSTMAN_EXPORT_AUTH_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Protocol Profile Behavior
    |--------------------------------------------------------------------------
    |
    | Set of configurations used to alter the usual behavior of sending the request.
    | These can be defined in a collection at Item or ItemGroup level which will be inherited if applicable.
    |
    */

    'protocol_profile_behavior' => [
        'disable_body_pruning' => false,
        'follow_redirects' => true,
        'strict_ssl' => true,
    ],

];
