<?php

namespace Drupal\events_manager\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class EventSettingsForm extends ConfigFormBase {

  protected function getEditableConfigNames() {
    return ['events_manager.settings'];
  }

  public function getFormId() {
    return 'events_manager_settings';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('events_manager.settings');

    $form['enable_notifications'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Admin Notifications'),
      '#default_value' => $config->get('enable_notifications'),
    ];

    $form['admin_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Admin Notification Email'),
      '#default_value' => $config->get('admin_email'),
      '#description' => $this->t('Email address to receive registration notifications.'),
      '#states' => [
        'visible' => [
          ':input[name="enable_notifications"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('events_manager.settings')
      ->set('enable_notifications', $form_state->getValue('enable_notifications'))
      ->set('admin_email', $form_state->getValue('admin_email'))
      ->save();
    parent::submitForm($form, $form_state);
  }
}