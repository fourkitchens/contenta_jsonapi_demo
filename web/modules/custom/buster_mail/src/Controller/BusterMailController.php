<?php

namespace Drupal\buster_mail\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;

/**
 * Class BusterMailController.
 */
class BusterMailController extends ControllerBase {

  private $tempFile = 'bust.zip';
  private $email;
  private $zip;
  private $tempDir;
  private $deets = array(
    'title' => '',
    'description' => '',
    'zipfile' => '',
  );

  /**
   * {@inheritdoc}
   */
  public function __construct() {
    $this->zip = new \ZipArchive();

    if (isset($_ENV['PANTHEON_ENVIRONMENT'])) {
      $this->tempDir = $_SERVER['HOME'] . '/tmp/';
    }
    else {
      $this->tempDir = '/tmp/';
    }
  }

  private function list() {
    $files = [];
    for ($i = 0; $i < $this->zip->numFiles; $i++) {
      $files[] = $this->zip->getNameIndex($i);
    }
    return $files;
  }

  private function parseDeets() {
    $searchDomain = 'https://accounts.itseez3d.com/';
    $searchEnd = '" style="';

    // Find the zip file link.
    $link_pos_start = strpos($this->email, $searchDomain);
    $link_pos_end = strpos($this->email, $searchEnd, $link_pos_start + strlen($searchDomain));

    // Exit out if no url found.
    if (!$link_pos_start) {
      return;
    }

    // Get zip file url.
    $this->deets['zipfile'] = substr($this->email, $link_pos_start, $link_pos_end - $link_pos_start);

    // Get title.
    $pos_start = strpos($this->email, '">Title');
    $pos_title_start = strpos($this->email, 'valign="top">', $pos_start) + 13;
    $pos_title_end = strpos($this->email, '</td>', $pos_title_start);
    $this->deets['title'] = substr($this->email, $pos_title_start, $pos_title_end - $pos_title_start);

    // Get description.
    $pos_start = strpos($this->email, '">Description');
    $pos_desc_start = strpos($this->email, 'valign="top">', $pos_start) + 13;
    $pos_desc_end = strpos($this->email, '</td>', $pos_desc_start);
    $this->deets['description'] = substr($this->email, $pos_desc_start, $pos_desc_end - $pos_desc_start);
  }

  private function clearDeets() {
    $this->deets = array(
      'title' => '',
      'description' => '',
      'zipfile' => '',
    );
  }

  private function saveBust($info, $files) {
    // Create node object with attached file.
    $node_array = [
      'type'        => 'bust',
      'title'       => $info['title'],
      'body' => [
        'value' => $info['description'],
      ]
    ];

    // Create file object for each file
    foreach ($files as $key => $file) {
      $name = preg_replace('@[^a-z0-9-]+@','-', strtolower($info['title']));
      $filename = $name . '-' . $key . '.' . end(explode('.', $file));
      $dir = "public://busts";
      $file = file_unmanaged_move($file, "$dir/$filename", FILE_EXISTS_REPLACE);
      $file = File::Create(['uri' => $file]);
      $file->save();

      $node_array[$key] = ['target_id' => $file->id()];
    }

    $node = Node::create($node_array);
    $node->save();
    return $node;
  }

  /**
   * Getmail.
   *
   * @return string
   *   Return Hello string.
   */
  public function getMail() {
    $response = new Response();

    if (!isset($_POST['mandrill_events'])) {
      $response->setStatusCode(400);
      $response->setContent('No valid emails given.');
      return $response;
    }

    // Parse post data to JSON.
    $events = json_decode($_POST['mandrill_events']);

    // Parse emails in events.
    foreach($events as $event) {
      $this->email = $event->msg->html;
      $this->parseDeets();

      // Error out if we can't find the link in the email
      if (!$this->deets['zipfile']) {
        \Drupal::logger('buster_mail')->warning('Failed to parse link from email: ' . print_r($event->msg, TRUE));
        continue;
      }

      $zip_file = $this->tempDir . $this->tempFile;
      system_retrieve_file($this->deets['zipfile'], $zip_file, FALSE, FILE_EXISTS_REPLACE);

      if ($this->zip->open($zip_file) !== TRUE) {
        \Drupal::logger('buster_mail')->warning('Failed to get/open zip from email: ' . print_r($event->msg, TRUE));
        continue;
      }

      // Extract files.
      $model_dir = $this->tempDir . 'model_temp';
      $this->zip->extractTo($model_dir);
      $this->zip->close();

      $files = array(
        'object' => "$model_dir/model_mesh.obj",
        'material' => "$model_dir/model_mesh.obj.mtl",
        'texture' => "$model_dir/model_texture.jpg",
        'preview' => "$model_dir/model_preview.png",
      );

      $node = $this->saveBust($this->deets, $files);
      if ($node) {
        \Drupal::logger('buster_mail')->notice('Parsed Email to Node (' . $node->id() . '): ' . print_r($event->msg, TRUE));
      }
      else {
        \Drupal::logger('buster_mail')->error('Failed to create node from email: ' . print_r($event->msg, TRUE));
      }

      $this->clearDeets();
    }

    $response->setContent('Nodes Created');
    return $response;
  }

}
