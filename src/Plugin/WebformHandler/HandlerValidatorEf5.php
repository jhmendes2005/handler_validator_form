<?php

namespace Drupal\ef5_validator_form\Plugin\WebformHandler;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionConditionsValidatorInterface;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form submission handler.
 *
 * @WebformHandler(
 *   id = "ef5_validator_form",
 *   label = @Translation("Custom Webform Validator Nestle Colaboradores."),
 *   category = @Translation("Validation"),
 *   description = @Translation("Validates submissions to check if the user has already submitted."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 * )
 */
final class HandlerValidatorEf5 extends WebformHandlerBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The webform submission conditions validator.
   *
   * @var \Drupal\webform\WebformSubmissionConditionsValidatorInterface
   */
  protected $conditionsValidator;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new HandlerValidatorEf5.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\webform\WebformSubmissionConditionsValidatorInterface $conditions_validator
   *   The webform submission conditions validator.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    LoggerChannelFactoryInterface $logger_factory,
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
    WebformSubmissionConditionsValidatorInterface $conditions_validator
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->loggerFactory = $logger_factory->get('custom_webform_handler');
    $this->configFactory = $config_factory;
    $this->conditionsValidator = $conditions_validator;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory'),
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('webform_submission.conditions_validator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    $webform = $webform_submission->getWebform();
    $wfid = $webform->id();

    $data = $webform_submission->getData();
    $email = $data['email'];
    $email_amigo_1 = $data['email_amigo_1'];
    $email_amigo_2 = $data['email_amigo_2'];
    $email_amigo_3 = $data['email_amigo_3'];

    $emails = [$email_amigo_1, $email_amigo_2, $email_amigo_3];
    $unique_emails = array_unique($emails);

    if (count($emails) != count($unique_emails)) {
      foreach (array_count_values($emails) as $email_friend => $count) {
        if ($count > 1) {
          $form_state->setErrorByName('email_amigo_2', t('O email: %email está repetido, utilize outro email de amigo!', ['%email' => $email_friend]));
          return;
        }
      }
    }

    $sql = "select sid
      from webform_submission_data wsd
      where wsd.webform_id = :webform_id
        and wsd.name = 'email'
        and wsd.value = :email
        and exists(
          select sid
          from webform_submission_data t
          where t.webform_id = :webform_id
            and t.name in('email_amigo_1', 'email_amigo_2', 'email_amigo_3')
            and t.value in(:email_amigo_1, :email_amigo_2, :email_amigo_3)
            and t.sid = wsd.sid
      );";

    $database = \Drupal::database();
    $query = $database->query($sql, [
      ':webform_id' => $wfid,
      ':email' => $email,
      ':email_amigo_1' => $email_amigo_1,
      ':email_amigo_2' => $email_amigo_2,
      ':email_amigo_3' => $email_amigo_3,
    ]);
    $submission_ids = $query->fetchAll(\PDO::FETCH_COLUMN);

    if (!empty($submission_ids)) {
      $submissions = \Drupal::entityTypeManager()->getStorage('webform_submission')->loadMultiple($submission_ids);

      foreach ($submissions as $submission) {
        $submission_data = $submission->getData();

        if (isset($submission_data['email']) && $submission_data['email'] == $email) {
          if ((isset($submission_data['email_amigo_1']) && $submission_data['email_amigo_1'] == $email_amigo_1) ||
            (isset($submission_data['email_amigo_2']) && $submission_data['email_amigo_2'] == $email_amigo_1) ||
            (isset($submission_data['email_amigo_3']) && $submission_data['email_amigo_3'] == $email_amigo_1)) {
            $form_state->setErrorByName('email_amigo_1', t('Email de amigo 1 já utilizado, utilize outro email de amigo!'));
          }
          if ((isset($submission_data['email_amigo_1']) && $submission_data['email_amigo_1'] == $email_amigo_2) ||
            (isset($submission_data['email_amigo_2']) && $submission_data['email_amigo_2'] == $email_amigo_2) ||
            (isset($submission_data['email_amigo_3']) && $submission_data['email_amigo_3'] == $email_amigo_2)) {
            $form_state->setErrorByName('email_amigo_2', t('Email de amigo 2 já utilizado, utilize outro email de amigo!'));
          }
          if ((isset($submission_data['email_amigo_1']) && $submission_data['email_amigo_1'] == $email_amigo_3) ||
            (isset($submission_data['email_amigo_2']) && $submission_data['email_amigo_2'] == $email_amigo_3) ||
            (isset($submission_data['email_amigo_3']) && $submission_data['email_amigo_3'] == $email_amigo_3)) {
            $form_state->setErrorByName('email_amigo_3', t('Email de amigo 3 já utilizado, utilize outro email de amigo!'));
          }
        }
      }
    }
  }
}
