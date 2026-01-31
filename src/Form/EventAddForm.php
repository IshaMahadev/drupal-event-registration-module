<?php

namespace Drupal\events_manager\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

class EventAddForm extends FormBase {

  protected $database;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database')
    );
  }

  public function getFormId() {
    return 'events_manager_add_event';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['event_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Event Name'),
      '#required' => TRUE,
    ];

    $form['category'] = [
      '#type' => 'select',
      '#title' => $this->t('Category'),
      '#options' => [
        'Online Workshop' => 'Online Workshop',
        'Hackathon' => 'Hackathon',
        'Conference' => 'Conference',
        'One-day Workshop' => 'One-day Workshop',
      ],
      '#required' => TRUE,
    ];

    $form['event_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Event Date'),
      '#required' => TRUE,
    ];

    $form['reg_start_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Registration Start Date'),
      '#required' => TRUE,
    ];

    $form['reg_end_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Registration End Date'),
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Event'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      $this->database->insert('events_manager_event')
        ->fields([
          'event_name' => $form_state->getValue('event_name'),
          'category' => $form_state->getValue('category'),
          'event_date' => $form_state->getValue('event_date'),
          'reg_start_date' => $form_state->getValue('reg_start_date'),
          'reg_end_date' => $form_state->getValue('reg_end_date'),
        ])
        ->execute();
      $this->messenger()->addStatus($this->t('Event created successfully.'));
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Error creating event: @error', ['@error' => $e->getMessage()]));
    }
  }
}