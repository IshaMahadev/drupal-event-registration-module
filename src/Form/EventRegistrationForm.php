<?php

namespace Drupal\events_manager\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class EventRegistrationForm extends FormBase {

  protected $database;
  protected $mailManager;
  protected $configFactory;

  public function __construct(Connection $database, MailManagerInterface $mail_manager, ConfigFactoryInterface $config_factory) {
    $this->database = $database;
    $this->mailManager = $mail_manager;
    $this->configFactory = $config_factory;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('plugin.manager.mail'),
      $container->get('config.factory')
    );
  }

  public function getFormId() {
    return 'events_manager_registration';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#prefix'] = '<div id="registration-form-wrapper">';
    $form['#suffix'] = '</div>';

    $form['full_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Full Name'),
      '#required' => TRUE,
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email Address'),
      '#required' => TRUE,
    ];

    $form['college'] = [
      '#type' => 'textfield',
      '#title' => $this->t('College Name'),
      '#required' => TRUE,
    ];

    $form['department'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Department'),
      '#required' => TRUE,
    ];

    // 1. Get Categories available in the system
    $categories = $this->getAvailableCategories();
    
    $selected_category = $form_state->getValue('category');
    $selected_date = $form_state->getValue('event_date');

    $form['category'] = [
      '#type' => 'select',
      '#title' => $this->t('Category'),
      '#options' => $categories,
      '#empty_option' => $this->t('- Select -'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::updateEventFields',
        'wrapper' => 'registration-form-wrapper',
      ],
    ];

    // 2. Event Date Dropdown
    $dates = [];
    if ($selected_category) {
      $dates = $this->getDatesByCategory($selected_category);
    }
    
    $form['event_date'] = [
      '#type' => 'select',
      '#title' => $this->t('Event Date'),
      '#options' => $dates,
      '#empty_option' => $this->t('- Select -'),
      '#validated' => TRUE, // Important for AJAX dynamic options
      '#required' => TRUE,
      '#disabled' => empty($dates),
      '#ajax' => [
        'callback' => '::updateEventFields',
        'wrapper' => 'registration-form-wrapper',
      ],
    ];

    // 3. Event Name Dropdown
    $events = [];
    if ($selected_category && $selected_date) {
      $events = $this->getEventsByCatAndDate($selected_category, $selected_date);
    }

    $form['event_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Event Name'),
      '#options' => $events,
      '#empty_option' => $this->t('- Select -'),
      '#validated' => TRUE,
      '#required' => TRUE,
      '#disabled' => empty($events),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Register'),
    ];

    return $form;
  }

  public function updateEventFields(array &$form, FormStateInterface $form_state) {
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    // Special Character Validation
    $text_fields = ['full_name', 'college', 'department'];
    foreach ($text_fields as $field) {
      if (preg_match('/[^a-zA-Z0-9\s]/', $values[$field])) {
        $form_state->setErrorByName($field, $this->t('Special characters are not allowed in @field.', ['@field' => str_replace('_', ' ', ucfirst($field))]));
      }
    }

    // Duplicate Registration Check (Email + Event ID)
    if (!empty($values['email']) && !empty($values['event_id'])) {
      $exists = $this->database->select('events_manager_registration', 'emr')
        ->fields('emr', ['id'])
        ->condition('email', $values['email'])
        ->condition('event_id', $values['event_id'])
        ->execute()
        ->fetchField();

      if ($exists) {
        $form_state->setErrorByName('email', $this->t('You have already registered for this event.'));
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    // Insert to DB
    try {
      $this->database->insert('events_manager_registration')
        ->fields([
          'event_id' => $values['event_id'],
          'full_name' => $values['full_name'],
          'email' => $values['email'],
          'college' => $values['college'],
          'department' => $values['department'],
          'created' => time(),
        ])
        ->execute();

      $this->messenger()->addStatus($this->t('Registration successful!'));

      // Send Emails
      $this->sendRegistrationEmails($values);
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Registration failed. Please try again.'));
    }
  }

  // --- Helper Functions ---

  protected function getAvailableCategories() {
    $current_date = date('Y-m-d');
    $query = $this->database->select('events_manager_event', 'e');
    $query->fields('e', ['category']);
    $query->distinct();
    // Only show categories that have events currently open for registration
    $query->condition('reg_start_date', $current_date, '<=');
    $query->condition('reg_end_date', $current_date, '>=');
    return $query->execute()->fetchAllKeyed(0, 0);
  }

  protected function getDatesByCategory($category) {
    $current_date = date('Y-m-d');
    $query = $this->database->select('events_manager_event', 'e');
    $query->fields('e', ['event_date', 'event_date']);
    $query->condition('category', $category);
    $query->condition('reg_start_date', $current_date, '<=');
    $query->condition('reg_end_date', $current_date, '>=');
    $query->distinct();
    return $query->execute()->fetchAllKeyed();
  }

  protected function getEventsByCatAndDate($category, $date) {
    $current_date = date('Y-m-d');
    $query = $this->database->select('events_manager_event', 'e');
    $query->fields('e', ['id', 'event_name']);
    $query->condition('category', $category);
    $query->condition('event_date', $date);
    $query->condition('reg_start_date', $current_date, '<=');
    $query->condition('reg_end_date', $current_date, '>=');
    return $query->execute()->fetchAllKeyed();
  }

  protected function sendRegistrationEmails($values) {
    // Fetch event details for email
    $event_details = $this->database->select('events_manager_event', 'e')
      ->fields('e', ['event_name', 'event_date', 'category'])
      ->condition('id', $values['event_id'])
      ->execute()
      ->fetchObject();

    $params = [
      'name' => $values['full_name'],
      'event_name' => $event_details->event_name,
      'event_date' => $event_details->event_date,
      'category' => $event_details->category,
    ];

    // 1. User Email
    $this->mailManager->mail('events_manager', 'registration_confirmation', $values['email'], 'en', $params);

    // 2. Admin Email
    $config = $this->configFactory->get('events_manager.settings');
    if ($config->get('enable_notifications')) {
      $admin_email = $config->get('admin_email');
      if ($admin_email) {
        $this->mailManager->mail('events_manager', 'admin_notification', $admin_email, 'en', $params);
      }
    }
  }
}