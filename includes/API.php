<?php

declare(strict_types=1);

namespace RRZE\FAUbox;

defined('ABSPATH') || exit;

/**
 * Class API
 *
 * Dummy implementation that returns structured file data for a given folder.
 * Replace internals later with real FAUbox / PowerFolder HTTP calls.
 */
class API
{
    /**
     * Fetch files from FAUbox API.
     *
     * @param string $folderId   Base64-encoded FAUbox folder ID.
     * @param string $token      API access token.
     * @param string|null $subdir Optional subfolder inside the folder.
     *
     * @return array|null
     */
//    public static function fetchFiles(string $folderId, string $token, string $subdir = null): ?array
//    {
//        $baseUrl = 'https://faubox.fau.de'; // Anpassen, falls andere Instanz
//
//        $path = $subdir ? '/' . trim($subdir, '/') : '';
//        $url = sprintf('%s/api/files/%s%s?action=getAll', $baseUrl, $folderId, $path);
//
//        $response = wp_remote_get($url, [
//            'headers' => [
//                'Authorization' => 'Bearer ' . $token,
//            ],
//        ]);
//
//        if (is_wp_error($response)) {
//            return null;
//        }
//
//        $body = wp_remote_retrieve_body($response);
//        $data = json_decode($body, true);
//
//        return $data['ResultSet']['Result'] ?? null;
//    }

    public static function fetchFiles(string $folderId, string $token, string $subdir = null): ?array
    {
        // Normalisiere den Pfad
        $path = $subdir ? '/' . trim($subdir, '/') : '/';

        // Cache-Key bauen (einfacher Aufbau ohne extra Klasse)
        $key = 'rrze_faubox:v1:path=' . $path;

        // 3. Cache prüfen
        $cached = get_transient($key);
        if (is_array($cached)) {
            return $cached;
        }


        // Dummy data: structure with optional subdir filtering
        $dummyData = [
            '/' => [ // Root folder
                [
                    'fileName' => 'Willkommen.pdf',
                    'fileSize' => 234567,
                    'mimeType' => 'application/pdf',
                    'lastModified' => '2025-10-15T12:00:00Z',
                ],
                [
                    'fileName' => 'FAQ.txt',
                    'fileSize' => 1234,
                    'mimeType' => 'text/plain',
                    'lastModified' => '2025-10-12T09:30:00Z',
                ],
            ],
            '/ws25' => [
                [
                    'fileName' => 'Zeugnisse_SS25.docx',
                    'fileSize' => 34567,
                    'mimeType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'lastModified' => '2025-11-09T08:45:00Z',
                ],
                [
                    'fileName' => 'Aufgaben_SS25.pdf',
                    'fileSize' => 7012,
                    'mimeType' => 'application/pdf',
                    'lastModified' => '2025-10-11T14:20:00Z',
                ],
                [
                    'fileName' => 'Bachelor.txt',
                    'fileSize' => 1234,
                    'mimeType' => 'text/plain',
                    'lastModified' => '2025-08-12T09:30:00Z',
                ],
                [
                    'fileName' => 'Master.pdf',
                    'fileSize' => 68012,
                    'mimeType' => 'application/pdf',
                    'lastModified' => '2025-04-01T14:20:00Z',
                ],
            ],
            '/ss24' => [
                [
                    'fileName' => 'Skriptum_SS24.docx',
                    'fileSize' => 345678,
                    'mimeType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'lastModified' => '2025-10-10T08:45:00Z',
                ],
                [
                    'fileName' => 'Aufgaben_SS24.zip',
                    'fileSize' => 789012,
                    'mimeType' => 'application/zip',
                    'lastModified' => '2025-10-05T14:20:00Z',
                ],
            ],
            '/ws23' => [
                [
                    'fileName' => 'Skriptum_WS23.pdf',
                    'fileSize' => 456789,
                    'mimeType' => 'application/pdf',
                    'lastModified' => '2024-12-20T10:00:00Z',
                ],
            ],
            '/ss23' => [
                [
                    'fileName' =>  'https://www.wp.rrze.fau.de/files/2025/09/AdobeStock_1612537156-1.jpg',
                    'fileSize' => 456789,
                    'mimeType' => 'image/jpg',
                    'lastModified' => '2024-12-20T10:00:00Z',
                ],
                [
                    'fileName' =>  'https://www.wp.rrze.fau.de/files/2024/08/Werkzeuge-Umwandeln.png',
                    'fileSize' => 456789,
                    'mimeType' => 'image/png',
                    'lastModified' => '2024-12-20T10:00:00Z',
                ],
                [
                    'fileName' =>  'https://www.wp.rrze.fau.de/files/2025/05/Listenansicht-Spaltenblock.png',
                    'fileSize' => 456789,
                    'mimeType' => 'image/png',
                    'lastModified' => '2024-12-20T10:00:00Z',
                ],
                [
                    'fileName' =>  'https://faubox.rrze.uni-erlangen.de/getlink/fiHJEHaNYgLURqGAuTa8T1/Mittel%20%28FAU_Anna_Tiessen_131%29.jpeg',
                    'fileSize' => 456789,
                    'mimeType' => 'image/jpeg',
                    'lastModified' => '2025-08-20T10:00:00Z',
                ],
                [
                    'fileName' =>  'https://faubox.rrze.uni-erlangen.de/getlink/fiJWF8WYRGH8F5zYMq4on5/Mittel%20%28FAU_Anna_Tiessen_17%29.jpeg',
                    'fileSize' => 456789,
                    'mimeType' => 'image/jpeg',
                    'lastModified' => '2023-05-10T10:00:00Z',
                ],


            ],
        ];

        // Daten für aktuellen Pfad holen
        $result = $dummyData[$path] ?? [];

        //Cachen – 15 Minuten
        set_transient($key, $result, 10);

        return $result;
    }

/**
* Return a list of dummy subfolders.
*
* @return array
*/
    public static function getDummyFolders(): array
    {
        return [
//
            '/ws25' => 'Wintersemester 2025',
            '/ss24' => 'Sommersemester 2024',
            '/ws23' => 'Wintersemester 2023',
            '/ss23' => 'Bilder Sommerfest 2023',
        ];
    }

    public static function getFiles(array $args): array
    {


        // Normalisieren
        $index = '/' . ltrim(trim((string) ($args['index'] ?? '')), '/');
        $filetype = strtolower(trim((string) ($args['filetype'] ?? '')));
        $orderby = strtolower(trim((string) ($args['orderby'] ?? 'name')));
        $sort = strtolower(trim((string) ($args['sort'] ?? 'asc')));

        // Cache-Key erzeugen
        $key = CacheKey::forFiles([
            'index' => $index,
            'filetype' => $filetype,
            'orderby' => $orderby,
            'sort' => $sort,
        ]);

        // Cache lesen
        $cached = Cache::get($key);
        if ($cached !== null) {
            error_log('[FAUbox] ✅ Cache HIT: ' . $key);
            return $cached;
        }

        error_log('[FAUbox] 🔁 Cache MISS – store: ' . $key);


        // API-Daten laden
        $folderId = get_option('rrze_faubox_folder', 'dummy-folder');
        $token = get_option('rrze_faubox_token', 'dummy-token');

        $files = self::fetchFiles($folderId, $token, $index);

        if (is_array($files)) {
            Cache::set($key, $files, 900); // 15 Minuten
            return $files;
        }

        return [];
    }


}
