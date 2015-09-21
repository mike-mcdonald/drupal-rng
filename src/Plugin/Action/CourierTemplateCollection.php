<?php

/**
 * @file
 * Contains \Drupal\rng\Plugin\Action\CourierTemplateCollection.
 */

namespace Drupal\rng\Plugin\Action;

use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\rng\EventManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\courier\Entity\TemplateCollection;
use Drupal\courier\Service\CourierManagerInterface;

/**
 * Creates a template collection and provides a user interface to its templates.
 *
 * @Action(
 *   id = "rng_courier_message",
 *   label = @Translation("Send message"),
 *   type = "registration"
 * )
 */
class CourierTemplateCollection extends ConfigurableActionBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The RNG event manager.
   *
   * @var \Drupal\rng\EventManagerInterface
   */
  protected $eventManager;

  /**
   * The courier manager.
   *
   * @var \Drupal\courier\Service\CourierManagerInterface
   */
  protected $courierManager;

  /**
   * Constructs a RegistrantBasicEmail object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\rng\EventManagerInterface $event_manager
   *   The RNG event manager.
   * @param \Drupal\courier\Service\CourierManagerInterface $courier_manager
   *   The courier manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityManagerInterface $entity_manager, EventManagerInterface $event_manager, CourierManagerInterface $courier_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityManager = $entity_manager;
    $this->eventManager = $event_manager;
    $this->courierManager = $courier_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition,
      $container->get('entity.manager'),
      $container->get('rng.event_manager'),
      $container->get('courier.manager')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @return array
   *   - template_collection: integer: ID of a courier_template_collection
   *     entity. Automatically filled after first submission.
   */
  public function defaultConfiguration() {
    return array(
      'template_collection' => NULL,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // @todo: replace w/ inline entity forms.
    $form['#title'] = $this->t('Edit templates');
    $form['description']['#markup'] = $this->t('Registrants have an option to choose which channel they will receive the message.
Each template requires content suitable to the channel.');

    $form['links'] = array(
      '#title' => $this->t('Channels'),
      '#theme' => 'item_list',
      '#items' => [],
    );

    $configuration = $this->getConfiguration();
    if ($template_collection = $this->getTemplateCollection()) {
      foreach ($template_collection->getTemplates() as $entity) {
        $item = [];
        if ($entity->hasLinkTemplate('edit-form')) {
          $item[] = [
            '#type' => 'link',
            '#title' => $entity->getEntityType()->getLabel(),
            '#url' => $entity->urlInfo('edit-form'),
          ];
        }
        else {
          $item[] = [
            '#markup' => $entity->getEntityType()->getLabel(),
          ];
        }

        $form['links']['#items'][] = $item;
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $configuration = $this->getConfiguration();

    // Create new.
    if (!isset($configuration['template_collection'])) {
      $template_collection = TemplateCollection::create();
      if ($template_collection->save()) {
        $this->courierManager->addTemplates($template_collection);
        $template_collection->save();
      }

      $this->configuration['template_collection'] = $template_collection->id();
    }
  }

  /**
   * Sends the message.
   *
   * @param array $context
   *   An associative array defining context.
   *   - \Drupal\rng\RegistrationInterface[] registrations: An array of
   *     registrations to send the message.
   */
  public function execute($context = NULL) {
    if ($collection_original = $this->getTemplateCollection()) {
      foreach ($context['registrations'] as $registration) {
        $options = [];
        /** @var \Drupal\rng\RegistrationInterface $registration */
        if (($event = $registration->getEvent()) instanceof EntityInterface) {
          $event_meta = $this->eventManager->getMeta($event);
          $options['channels']['courier_email']['reply_to'] = $event_meta->getReplyTo();
          $collection_original->setTokenValue($event->getEntityTypeId(), $event);
        }
        $collection = clone $collection_original;
        $collection->setTokenValue('registration', $registration);
        foreach ($registration->getRegistrants() as $registrant) {
          $identity = $registrant->getIdentity();
          $this->courierManager->sendMessage($collection, $identity, $options);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    return FALSE;
  }

  /**
   * Get the courier_template_collection object associated with this action.
   *
   * @return \Drupal\courier\TemplateCollectionInterface|NULL
   *   A courier_template_collection object. NULL if it has not been created.
   */
  public function getTemplateCollection() {
    if (isset($this->configuration['template_collection'])) {
      return $this->entityManager
        ->getStorage('courier_template_collection')
        ->load($this->configuration['template_collection']);
    }
    return NULL;
  }

}
