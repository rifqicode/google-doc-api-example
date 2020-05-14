<?php
require __DIR__ . '../../vendor/autoload.php';

/**
 * @author Muhammad Rifqi <muhammadrifqi.tb@gmail.com>
 */
class Google_doc
{
    public $client;
    public $service;

    public function __construct($authCode)
    {
      $this->client = $this->getClient($authCode);
      $this->service = new Google_Service_Docs($this->client);
    }

    /**
     * Returns an authorized API client.
     * @return Google_Client the authorized client object
     */
    function getClient($authCode = null)
    {
        $client = new Google_Client();
        $client->setApplicationName('Google Docs API PHP Quickstart');
        $client->setScopes([
          'https://www.googleapis.com/auth/documents',
          'https://www.googleapis.com/auth/drive',
          'https://www.googleapis.com/auth/drive.file'
        ]);
        $client->setAuthConfig('credentials.json');
        $client->setAccessType('offline');


        // Load previously authorized credentials from a file.
        $credentialsPath = $this->expandHomeDirectory('token.json');
        if (file_exists($credentialsPath)) {
            $accessToken = json_decode(file_get_contents($credentialsPath), true);
        } else {
            if (!$authCode) {
              $authUrl = $client->createAuthUrl();
              echo "akses halaman ini : \n" . $authUrl;
              exit;
            }

            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

            // Store the credentials to disk.
            if (!file_exists(dirname($credentialsPath))) {
                mkdir(dirname($credentialsPath), 0700, true);
            }
            file_put_contents($credentialsPath, json_encode($accessToken));
            printf("Credentials saved to %s\n", $credentialsPath);
        }
        $client->setAccessToken($accessToken);

        // Refresh the token if it's expired.
        if ($client->isAccessTokenExpired()) {

            // save refresh token to some variable
            $refreshTokenSaved = $client->getRefreshToken();

            // update access token
            $client->fetchAccessTokenWithRefreshToken($refreshTokenSaved);

            // pass access token to some variable
            $accessTokenUpdated = $client->getAccessToken();

            // append refresh token
            $accessTokenUpdated['refresh_token'] = $refreshTokenSaved;

            //Set the new acces token
            $accessToken = $refreshTokenSaved;
            $client->setAccessToken($accessToken);

            // save to file
            file_put_contents($this->tokenFile,json_encode($accessTokenUpdated));
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


    public function getDocument(string $documentId)
    {
        return $this->service->documents->get($documentId);
    }

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

    public function exportDocumentToHtml($documentId)
    {
      $service = new Google_Service_Drive($this->client);
      $content = $service->files->export($documentId, 'text/html', array( 'alt' => 'media' ));

      return $content->getBody()->getContents();
    }
}
