<?php
/**
 * @file
 * Contains Drupal\soccerbet_tipper\Form\TipperSettingsForm.
 */

namespace Drupal\soccerbet\Form;

use Drupal;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\File\Event\FileUploadSanitizeNameEvent;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\soccerbet\Entity\SoccerbetTeam;
use Drupal\soccerbet\Entity\SoccerbetTournament;

/**
 * Class SoccerbetSettingsForm.
 *
 * @package Drupal\soccerbet\Form
 * @ingroup soccerbet
 */
class SoccerbetSettingsForm extends ConfigFormBase {

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'soccerbet_settings';
  }

  /**
   * Form submission handler.
   *
   * @param FormStateInterface $form
   *   An associative array containing the structure of the form.
   * @param array $form_state
   *   An associative array containing the current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Empty implementation of the abstract submit class.
    parent::submitForm($form, $form_state);
  }


  /**
   * Define the form used for Soccerbet settings.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param FormStateInterface $form_state
   *   An associative array containing the current state of the form.
   *
   * @return array
   *   Form definition array.
   *
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'system/drupal.system';

    $config = $this->config('soccerbet.settings');

    $form['tournament'] = [
      '#type' => 'details',
      '#title' => $this->t('Tournament settings'),
      '#open' => TRUE,
    ];
    $tournaments = SoccerbetTournament::loadMultiple();

    $form['tournament']['import'] = [
      '#type' => 'submit',
      '#markup' => count($tournaments)>0 ? t('There are already tournaments defined') : t('You can import tournament data here.'),
      '#value' => $this->t('Import Tournament Data'),
      '#disabled' => count($tournaments)>0 ? TRUE : FALSE,
      '#submit' => ['::submitTournamentImport'],
    ];

    $form['team'] = [
      '#type' => 'details',
      '#title' => $this->t('Team settings'),
      '#open' => TRUE,
    ];

    $form['team']['import'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import Team Data'),
      '#submit' => ['::submitTeamImport'],
    ];

    $form['soccerbet_settings']['#markup'] = 'Settings form for Soccerbet. Manage field settings here.';
    return $form;
  }

  protected function getEditableConfigNames() {
    return ['soccerbet.settings'];
  }

  public function submitTournamentImport(array &$form, FormStateInterface $formState) {

    $batch_builder = (new BatchBuilder())
      ->setTitle(t('Importing Soccerbet Initial Data'))
      ->setFinishCallback([
        SoccerbetSettingsForm::class,
        'soccerbet_finished_batch_callback',
      ])
      ->setInitMessage(t('Starting the import of tournament data'))
      ->addOperation([SoccerbetSettingsForm::class, 'soccerbet_import_tournament_data']);

    batch_set($batch_builder->toArray());
  }

  public static function soccerbet_import_tournament_data(&$context) {

    //First of all we prepare the path
    $module_path = Drupal::service('extension.list.module')->getPath('soccerbet');

    //Tournaments Data are in the includes directory
    $soccerbet_tournament_data = include $module_path . '/includes/soccerbet_tournament_install.php';

    if (empty($context['sandbox'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['currend_id'] = 0;
      $context['sandbox']['max'] = count($soccerbet_tournament_data);
      $context['results'] = 0;

      $limit = 5;

      foreach ($soccerbet_tournament_data as $entity) {
        $tournament = SoccerbetTournament::create(
          [
            'name' => $entity['name'],
            'langcode' => $entity['langcode'],
            'start_and_end_date' => $entity['start_and_end_date'],
            'group_count' => $entity['group_count'],
            'status' => $entity['status'],
            'id' => $entity['id'],
            'uuid' => Drupal::service('uuid')->generate(),
            'created' => $entity['created'],
          ]
        );
        if (!empty($tournament)) {
          try {
            $tournament->save();
          } catch (EntityStorageException $e) {
            Drupal::logger('soccerbet')->error($e->getMessage());
          }
        }
      }
    }
  }

  public function submitTeamImport(array &$form, FormStateInterface $formState) {

    $batch_builder = (new BatchBuilder())
      ->setTitle(t('Importing Soccerbet Initial Data'))
      ->setFinishCallback([
        SoccerbetSettingsForm::class,
        'soccerbet_finished_batch_callback',
      ])
      ->setInitMessage(t('Starting the import of team data'))
      ->addOperation([SoccerbetSettingsForm::class, 'soccerbet_import_team_data']);

    batch_set($batch_builder->toArray());
  }

  public static function soccerbet_import_team_data(&$context) {

    //First of all we prepare the path
    $module_path = Drupal::service('extension.list.module')->getPath('soccerbet');

    //Tournaments Data are in the includes directory
    $soccerbet_team_data = include $module_path . '/includes/soccerbet_team_install.php';

    if (empty($context['sandbox'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['currend_id'] = 0;
      $context['sandbox']['max'] = count($soccerbet_team_data);
      $context['results'] = 0;

      $limit = 5;

      //Set the path for the files
      $image_target_path = 'public://soccerbetflags';
      /** @var FileSystemInterface $file_system */
      $file_system = Drupal::service('file_system');
      $file_system->prepareDirectory($image_target_path, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

      //Iterate through the array
      foreach ($soccerbet_team_data as $entity) {
        $team = SoccerbetTeam::create(
          [
            'title' => $entity['title'],
            'langcode' => $entity['langcode'],
            'status' => 1,
            'team_name_code' => $entity['team_name_code'],
            'uuid' => Drupal::service('uuid')->generate(),
            'created' => $entity['created'],
          ]
        );
        //In case we are using the FlagKit, we have to use this naming convention
        $image_file_name = $team->getTeamNameCode() . '@3x.png';
        //Building the image object for the Flag based on the shortcode which is stored in the SoccerbetTeam /flags directory
        $image_source_path = $module_path . '/flags/PNG/3x/' . $image_file_name;
        //Reading the Image file contents
        $image_data = file_get_contents($image_source_path);
        $image_alt = 'Flag of ' . $team->getTitle();
        $image_title = 'Flag of ' . $team->getTitle();

        //Sanitize the filename before we use it
        $image_copy_event = new FileUploadSanitizeNameEvent($image_file_name, '');
        $image_sanitized_filename = $image_copy_event->getFilename();

        //Writing the file into the target folder
        $image_object = Drupal::service('file.repository')
          ->writeData($image_data, $image_target_path . '/' . $image_sanitized_filename, FileSystemInterface::EXISTS_REPLACE);

        //Store the File Entity to the Soccerbet Team Entity
        $team->set('flag', [
          'target_id' => $image_object->id(),
          'alt' => $image_alt,
          'title' => $image_title,
        ]);

        //Save it to the database
        if (!empty($team)) {
          try {
            $team->save();
          } catch (EntityStorageException $e) {
            Drupal::logger('soccerbet_team')->error($e->getMessage());
          }
        }
      }
    }
  }

  public static function soccerbet_finished_batch_callback($success, $results, $operations, $elapsed) {
    $messenger = Drupal::messenger();
    if ($success) {
      if (!empty($results['errors'])) {
        $logger = Drupal::logger('soccerbet');
        foreach ($results['errors'] as $error) {
          $messenger->addError($error);
          $logger->error($error);
        }
        $messenger->addWarning(t('Data was imported with errors.'));
      }
    }
    else {
      // An error occurred.
      // $operations contains the operations that remained unprocessed.
      $error_operation = reset($operations);
      $message = t('An error occurred while processing %error_operation with arguments: @arguments', [
        '%error_operation' => $error_operation[0],
        '@arguments' => print_r($error_operation[1], TRUE),
      ]);
      $messenger->addError($message);
    }
  }

}