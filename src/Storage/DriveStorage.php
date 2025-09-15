<?php
namespace AutoDBVault\Storage;

use Google_Client;
use Google_Service_Drive;
use Google_Service_Drive_DriveFile;

final class DriveStorage
{
    private Google_Service_Drive $drive;
    private string $topFolderName;
    private ?string $topFolderId = null;

    public function __construct(Google_Client $client, string $topFolderName)
    {
        $this->drive = new Google_Service_Drive($client);
        $this->topFolderName = $topFolderName;
    }

    private function findFolderIdByName(?string $parentId, string $name): ?string
    {
        $q = sprintf("mimeType = 'application/vnd.google-apps.folder' and name = '%s' and trashed = false", addslashes($name));
        if ($parentId) {
            $q .= sprintf(" and '%s' in parents", addslashes($parentId));
        }
        $res = $this->drive->files->listFiles(['q' => $q, 'fields' => 'files(id, name)', 'pageSize' => 10]);
        foreach ($res->getFiles() as $file) {
            if ($file->getName() === $name) {
                return $file->getId();
            }
        }
        return null;
    }

    private function ensureFolder(string $name, ?string $parentId = null): string
    {
        $existing = $this->findFolderIdByName($parentId, $name);
        if ($existing)
            return $existing;
        $meta = new Google_Service_Drive_DriveFile([
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
        ]);
        if ($parentId)
            $meta->setParents([$parentId]);
        $created = $this->drive->files->create($meta, ['fields' => 'id']);
        return $created->getId();
    }

    private function ensureTopFolder(): string
    {
        if ($this->topFolderId)
            return $this->topFolderId;
        $this->topFolderId = $this->ensureFolder($this->topFolderName, null);
        return $this->topFolderId;
    }

    public function ensureDbFolder(string $db): string
    {
        $top = $this->ensureTopFolder();
        return $this->ensureFolder($db, $top);
    }

    public function upload(string $db, string $localPath, string $filename): string
    {
        $folderId = $this->ensureDbFolder($db);
        $file = new Google_Service_Drive_DriveFile([
            'name' => $filename,
            'parents' => [$folderId]
        ]);
        $created = $this->drive->files->create($file, [
            'data' => file_get_contents($localPath),
            'mimeType' => 'application/gzip',
            'uploadType' => 'media',
            'fields' => 'id'
        ]);
        return $created->getId();
    }

    public function listFiles(string $db): array
    {
        $folderId = $this->ensureDbFolder($db);
        $files = [];
        $pageToken = null;
        do {
            $params = [
                'q' => sprintf("'%s' in parents and trashed = false", addslashes($folderId)),
                'fields' => 'nextPageToken, files(id, name, createdTime)',
                'orderBy' => 'name asc',
                'pageSize' => 1000,
            ];
            if ($pageToken)
                $params['pageToken'] = $pageToken;
            $res = $this->drive->files->listFiles($params);
            foreach ($res->getFiles() as $f) {
                $files[] = $f;
            }
            $pageToken = $res->getNextPageToken();
        } while ($pageToken);
        return $files;
    }

    public function deleteById(string $fileId): void
    {
        $this->drive->files->delete($fileId);
    }

    // to-do: get file content by ID
    public function downloadFile(string $fileId): string
    {
        // Download file content by ID
        $res = $this->drive->files->get($fileId, ['alt' => 'media']);
        return $res->getBody()->getContents();
    }
}