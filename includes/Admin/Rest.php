<?php

namespace RRZE\FAUbox\Admin;

use RRZE\FAUbox\API;

defined('ABSPATH') || exit;

/**
 *Register and handle REST endpoints for the FAUbox plugin .

This class exposes a REST endpoint for retrieving available FAUbox folders .
Currently returns dummy data from API::getDummyFolders() .
Replace this later with real FAUbox folder data from the API .

Example:
 GET / wp - json / rrze - faubox / v1 / folders

 @package RRZE\FAUbox\Admin

 */

class Rest
{
public static function register(): void
{
add_action('rest_api_init', [self::class, 'registerRoutes']);
}

public static function registerRoutes(): void
{
register_rest_route('rrze-faubox/v1', '/folders', [
'methods' => 'GET',
'callback' => [self::class, 'getFolders'],
'permission_callback' => '__return_true',
]);
}

public static function getFolders(\WP_REST_Request $request): \WP_REST_Response
{
$folders = API::getDummyFolders();

// convert to array of objects for SelectControl
$result = [];
foreach ($folders as $value => $label) {
$result[] = ['value' => $value, 'label' => $label];
}

return new \WP_REST_Response($result);
}
}
