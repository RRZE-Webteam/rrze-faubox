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
        $baseUrl = plugins_url('', dirname(__DIR__) . '/rrze-faubox.php');


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
                    'fileName' =>  $baseUrl . '/testdata/pictures/Bild1.jpeg',
                    'fileSize' => 456789,
                    'mimeType' => 'image/jpeg',
                    'lastModified' => '2024-12-20T10:00:00Z',
                ],
                [
                    'fileName' =>  $baseUrl . '/testdata/pictures/Bild2.jpeg',
                    'fileSize' => 456789,
                    'mimeType' => 'image/jpeg',
                    'lastModified' => '2024-12-20T10:00:00Z',
                ],
            ],
        ];

        // Normalisieren
        $path = $subdir ? '/' . trim($subdir, '/') : '/';

        // Rückgabe: Daten für diesen Pfad oder leer
        return $dummyData[$path] ?? [];
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
}
