<?php
// require __DIR__ . '../../vendor/autoload.php';
require_once 'vendor/autoload.php';

/**
 * @author Muhammad Rifqi <muhammadrifqi.tb@gmail.com>
 * github : https://github.com/rifqicode/google-doc
 */
class Google_Docs
{
    private $client;
    private $service;
    private $path = APPPATH;

    public function __construct()
    {
      $this->client = $this->getClient();
      $this->service = new Google_Service_Docs($this->client);
    }

    /**
     * Returns an authorized API client.
     * @return Google_Client the authorized client object
     */
    function getClient()
    {
        $client = new Google_Client();
        $client->setApplicationName('Google Docs API PHP Quickstart');
        $client->setScopes([
          'https://www.googleapis.com/auth/documents',
          'https://www.googleapis.com/auth/drive',
          'https://www.googleapis.com/auth/drive.file'
        ]);
        $client->setAuthConfig($this->path . 'libraries/google_config/credentials.json');
        $client->setAccessType('offline');
        $client->setApprovalPrompt('force');


        $credentialsPath = $this->expandHomeDirectory($this->path . 'libraries/google_config/token.json');
        if (!file_exists($credentialsPath)) {
          return 'token tidak ditemukan';
        }

        $authUrl = $client->createAuthUrl();

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


    public function getDocument(string $documentId)
    {
        return $this->service->documents->get($documentId);
    }

    public function readParaghraphElement($element)
    {
        if (isset($element['textRun'])) {
          $format = [
            'startIndex' => $element['startIndex'],
            'endIndex' => $element['endIndex'],
            'content' => $element['textRun']['content']
          ];

          return $format;
        }

        return [];
    }

    public function getDocumentAllText($content)
    {
        $text = [];
        foreach ($content as $key => $value) {
          if (isset($value['paragraph'])) {
            $elements = $value['paragraph']['elements'];
            foreach ($elements as $key => $e) {
              $text[] = $this->readParaghraphElement($e);
            }
          } elseif (isset($value['table'])) {
            $table = $value['table'];
            foreach ($table['tableRows'] as $key => $row) {
              foreach ($row['tableCells'] as $key => $cell) {
                  $text[] = $this->getDocumentAllText($cell['content']);
              }
            }
          } elseif (isset($value['tableOfContents'])) {
            $toc = $value['tableOfContents'];
            $text[] = $this->getDocumentAllText($toc['content']);
          }
        }

        return $text;
    }

    public function findTextOnDocument($documentId, $findText)
    {
        $getDocumentContent = $this->getDocument($documentId)['body']['content'];
        $getAllListText = $this->getDocumentAllText($getDocumentContent);
        $find = [];
        foreach ($getAllListText as $key => $value) {
          if (isset($value[0])) {
            foreach ($value as $key => $v) {
              if (isset($v['content']) && trim($v['content']) == $findText) {
                $find = $v;
              }
            }
          } else {
            if (trim($value['content']) == $findText) {
              $find = $v;
            }
          }
        }

        return $find;
    }

    public function replaceTextWithImage($documentId, $findText, $img, $imgOption = []) : bool
    {
        if (!$findCordinateText = $this->findTextOnDocument($documentId, $findText)) {
          return false;
        }

        $replaceText =
          [
              'replaceAllText' => array(
                  'containsText' => array(
                      'text' => $findText
                  ),
                  'replaceText' => ''
              )
          ];
          $img = [
            'insertInlineImage' => array(
                'uri' => $img,
                'location' => array(
                    'index' => ($findCordinateText) ? $findCordinateText['startIndex'] : 0
                ),
                'objectSize' => array(
                    'height' => array(
                        'magnitude' => (isset($imgOption['height'])) ? $imgOption['height'] : 150,
                        'unit' => 'PT',
                    ),
                    'width' => array(
                        'magnitude' => (isset($imgOption['width'])) ? $imgOption['width'] : 150,
                        'unit' => 'PT',
                    ),
                )
            )
          ];

          $replace = $this->batchUpdate($documentId, $replaceText);
          $replaceWithImage = $this->batchUpdate($documentId, $img);

          return true;
    }

    public function documentReplaceTextWithImage($body, $findText, $imgUrl, $imgOption = [])
    {
        $startIndex = 0;
        $debug = [];
        foreach ($body as $key => $value) {
          dd($value);
          // if (!isset($value['paragraph'])) {
          //   continue;
          // }
          //
          // $paragraph = $value['paragraph'];
          $elements = $paragraph['elements'];

          foreach ($elements as $key => $e) {
            // if (!isset($e['textRun'])) {
            //   continue;
            // }

            // $text = $e['textRun']['content'];

            $debug[] = $e;
            // if ($text == $findText) {
            //   $startIndex = $e['startIndex'];
            // } else {
            //   continue;
            // }
          }

          return $debug;
        }
    }

    public function createDocument($title, $parent = null)
    {
        $document = new Google_Service_Docs_Document(array(
            'title' => $title
        ));
        $document = $this->service->documents->create($document);

        if ($parent) {
          $service = new \Google_Service_Drive($this->getClient());
          $emptyFileMetadata = new Google_Service_Drive_DriveFile();
          $file = $service->files->get($document->documentId, array('fields' => 'parents'));
          $file = $service->files->update($document->documentId, $emptyFileMetadata, array(
              'addParents' => $parent,
              'fields' => 'id, parents'));
        }

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
        if (!$documentId) return '';

        $service = new Google_Service_Drive($this->client);
        $content = $service->files->export($documentId, 'text/html', array( 'alt' => 'media' ));

        return $content->getBody()->getContents();
    }

    public function getHtmlContent(string $html)
    {
        preg_match("/<body[^>]*>(.*?)<\/body>/is", $html, $body);
        return $body ? $body[0] : '';
    }

    public function mergeFile(array $documentId)
    {
        $content = '';
        foreach ($documentId as $key => $value) {
            $getHtml = $this->exportDocumentToHtml($value);
            $content .= $getHtml . PHP_EOL;
        }

        return $content;
    }

    public function upload(string $filename, string $html, $parent = null)
    {
        $service = new Google_Service_Drive($this->getClient());
        $file = new Google_Service_Drive_DriveFile([
          'name' => $filename,
          'mimeType' => 'application/vnd.google-apps.document',
          'addParents' => $parent
        ]);

        $create = $service->files->create($file, array(
            'data' =>  $html,
            'mimeType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'uploadType' => 'multipart',
            'fields' => 'id'
          )
        );

        if ($parent)
        {
            $emptyFileMetadata = new Google_Service_Drive_DriveFile();
            $file->setParents($parent);
            $service->files->update($create->id, $emptyFileMetadata, array(
                'addParents' => $parent,
                'fields' => 'id, parents'));
        }

        $permission = new Google_Service_Drive_Permission([
          'role' => 'writer',
          'type' => 'anyone',
          'allowFileDiscovery' => 'true'
        ]);
        $service->permissions->create($create->id , $permission);

        return $create;
    }

    public function exportDocument($documentId , $type)
    {
      return "https://docs.google.com/document/d/${documentId}/export?format=${type}";
    }

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

    public function getRevision($documentId)
    {
        $service = new Google_Service_Drive($this->client);
        return $service->revisions->listRevisions($documentId);
    }

    function copyFile($originFileId, $copyTitle, $parent = null) {
        $service = new Google_Service_Drive($this->client);
        $folderId = $parent;
        try {
            $file = new \Google_Service_Drive_DriveFile();
            $file->setParents(['1-mvD94is05hE-MSY5B_aq4DUWkG68ArE']);
            $file->setName($copyTitle);
            $file->setMimeType('application/vnd.google-apps.html');
            $copyFileId =  $service->files->copy($originFileId, $file);

            $emptyFileMetadata = new Google_Service_Drive_DriveFile();
            // Retrieve the existing parents to remove

            if ($parent)
            {
                $file = $service->files->get($copyFileId->getId(), array('fields' => 'parents'));
                // Move the file to the new folder
                $file = $service->files->update($copyFileId->getId(), $emptyFileMetadata, array(
                    'addParents' => $folderId,
                    'fields' => 'id, parents'));
            }
            return $copyFileId;
        } catch (Exception $e) {
            print "An error occurred: " . $e->getMessage();
        }
        return NULL;
    }

    function deleteFile($fileId) {
        $service = new Google_Service_Drive($this->client);
        try {
            $service->files->delete($fileId);
        } catch (Exception $e) {
            print "An error occurred: " . $e->getMessage();
        }
    }

    public function create_document($name, $content_id=null)
    {
        $user = Auth::user();
        $copywriter=Copywriter::where('user_id', $user->id)->first();
        $folderId = $copywriter->gd_folder_id;
        $client=$this->getClient();
        $service = new \Google_Service_Drive($client);
        $fileMetadata = new \Google_Service_Drive_DriveFile(array(
            'name' => $name,'mimeType' => 'application/vnd.google-apps.document','parents' => array($folderId)));
        $file = $service->files->create($fileMetadata, array(
            'mimeType' => 'application/vnd.google-apps.document',
            'uploadType' => 'multipart',
            'fields' => 'id'));
        if ($content_id!=null) {
            $content=Content::findOrFail($content_id);
            $content->file_id=$file->id;
            $content->save();
        }
        return ['file_name'=>$name,'file_id'=>$file->id];
    }
}
