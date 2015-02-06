<?php

/**
 * @file
 * Contains \Drupal\rng\Form\EventTypeConfigDeleteForm.
 */

namespace Drupal\rng\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldConfig;

/**
 * Form controller to delete event configs.
 */
class EventTypeConfigDeleteForm extends EntityConfirmFormBase {
  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete settings for event %label and all associated registrations?', array(
      '%label' => $this->entity->label(),
    ));
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('rng.event_type_config.overview');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->entity->delete();
    drupal_set_message(t('Event type %label was deleted.', array(
      '%label' => $this->entity->label(),
    )));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }
}