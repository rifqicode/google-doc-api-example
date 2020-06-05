<?php
require __DIR__ . '../../vendor/autoload.php';

/**
 * @author Muhammad Rifqi <muhammadrifqi.tb@gmail.com>
 * github : https://github.com/rifqicode/google-doc
 */
class Google_Docs
{
    private $client;
    private $service;
    private $scope;
    private $path = APPPATH;

    public function __construct()
    {
      $this->client = $this->getClient();
      $this->service = new Google_Service_Docs($this->client);
      $this->scope = [
        'https://www.googleapis.com/auth/documents',
        'https://www.googleapis.com/auth/drive',
        'https://www.googleapis.com/auth/drive.file'
      ];
    }

    /**
     * Returns an authorized API client.
     * @return Google_Client the authorized client object
     */
    function getClient()
    {
        $client = new Google_Client();
        $client->setApplicationName('Google Docs API PHP Quickstart');
        $client->setScopes($this->scope);
        $client->setAuthConfig($this->path . 'libraries/google_config/credentials.json');
        $client->setAccessType('offline');
        $client->setApprovalPrompt('force');

        $credentialsPath = $this->expandHomeDirectory($this->path . 'libraries/google_config/token.json');
        if (!file_exists($credentialsPath)) {
          return 'token tidak ditemukan';
        }

        $accessToken = json_decode(file_get_contents($credentialsPath), true);
        $client->setAccessToken($accessToken);

        if ($client->isAccessTokenExpired()) {
          $refreshToken = $client->getRefreshToken();
          $freshToken = $client->fetchAccessTokenWithRefreshToken($refreshToken);
          // insert refresh token again
          $freshToken['refresh_token'] = $refreshToken;
          file_put_contents($credentialsPath, json_encode($freshToken));
        }
        return $client;
    }

    /**
     * Expands the home directory alias '~' to the full path.
     * @param string $path the path to expand.
     * @return string the expanded path.
     */
    function expandHomeDirectory($path)
    {
        $homeDirectory = getenv('HOME');
        if (empty($homeDirectory)) {
            $homeDirectory = getenv('HOMEDRIVE') . getenv('HOMEPATH');
        }
        return str_replace('~', realpath($homeDirectory), $path);
    }


    /**
     * [getDocument description]
     * @param  string $documentId [description]
     * @return [type]             [description]
     */
    public function getDocument(string $documentId)
    {
        return $this->service->documents->get($documentId);
    }

    /**
     * [createDocument description]
     * @param  [type] $title [description]
     * @return [type]        [description]
     */
    public function createDocument($title)
    {
        $document = new Google_Service_Docs_Document(array(
            'title' => $title
        ));
        $document = $this->service->documents->create($document);

        // update permission
        $googleDriveService = new Google_Service_Drive($this->client);
        $permission = new Google_Service_Drive_Permission();
        $permission->setRole( 'writer' );
        $permission->setType( 'anyone' );
        $googleDriveService->permissions->create($document->documentId , $permission);

        return $document;
    }

    /**
     * [batchUpdate description]
     * batch update
     * @param  string $documentId [description]
     * @param  array  $update     [description]
     * @return [type]             [description]
     */
    public function batchUpdate(string $documentId , array $update = [])
    {
        $requests = array();
        $requests[] = new Google_Service_Docs_Request($update);

        $batchUpdateRequest = new Google_Service_Docs_BatchUpdateDocumentRequest(array(
            'requests' => $requests
        ));

        $response = $this->service->documents->batchUpdate($documentId, $batchUpdateRequest);
        return $response;
    }

    public function mergeDocument()
    {
        // https://stackoverflow.com/questions/61302108/merge-two-google-documents-together-using-node-googleapis
    }

    /**
     * [exportDocumentToHtml description]
     * export document to html
     *
     * @param  [type] $documentId [description]
     * @return [type]             [description]
     */
    public function exportDocumentToHtml($documentId)
    {
        $service = new Google_Service_Drive($this->client);
        $content = $service->files->export($documentId, 'text/html', array( 'alt' => 'media' ));

        return $content->getBody()->getContents();
    }

    /**
     * [exportDocument description]
     * export document to fleksible type
     *
     * @param  [type] $documentId [description]
     * @param  [type] $type       [description]
     * @return [type]             [description]
     */
    public function exportDocument($documentId , $type)
    {
      return "https://docs.google.com/document/d/${documentId}/export?format=${type}";
    }

    /**
     * [publish description]
     * publish revision
     *
     * @param  [type] $documentId [description]
     * @return [type]             [description]
     */
    public function publish($documentId)
    {
        $service = new Google_Service_Drive($this->client);

        $revision = new Google_Service_Drive_Revision();
        $revision->setPublished(true);
        $revision->setKeepForever(true);
        $revision->setPublishAuto(true);
        $revision->setPublishedOutsideDomain(true);

        $update = $service->revisions->update($documentId, 1, $revision);

        return $update;
    }

    /**
     * [getRevision GetListRevision]
     * Get list all revision on document
     *
     * @param  [type] $documentId [description]
     * @return [type]             [description]
     */
    public function getRevision($documentId)
    {
        $service = new Google_Service_Drive($this->client);
        return $service->revisions->listRevisions($documentId);
    }
}
