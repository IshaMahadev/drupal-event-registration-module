<?php

namespace Drupal\events_manager\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

class EventListingForm extends FormBase {

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
    return 'events_manager_listing';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#prefix'] = '<div id="listing-wrapper">';
    $form['#suffix'] = '</div>';

    // 1. Filters
    $dates = $this->getAllEventDates();
    $selected_date = $form_state->getValue('filter_date');
    $selected_event_id = $form_state->getValue('filter_event');

    $form['filters'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Filter Registrations'),
      '#attributes' => ['class' => ['container-inline']],
    ];

    $form['filters']['filter_date'] = [
      '#type' => 'select',
      '#title' => $this->t('Event Date'),
      '#options' => $dates,
      '#empty_option' => $this->t('- Select Date -'),
      '#ajax' => [
        'callback' => '::updateListing',
        'wrapper' => 'listing-wrapper',
      ],
    ];

    $events = [];
    if ($selected_date) {
      $events = $this->getEventsByDate($selected_date);
    }

    $form['filters']['filter_event'] = [
      '#type' => 'select',
      '#title' => $this->t('Event Name'),
      '#options' => $events,
      '#empty_option' => $this->t('- Select Event -'),
      '#validated' => TRUE,
      '#ajax' => [
        'callback' => '::updateListing',
        'wrapper' => 'listing-wrapper',
      ],
    ];
    
    $form['filters']['export'] = [
      '#type' => 'submit',
      '#value' => $this->t('Export as CSV'),
      '#submit' => ['::exportCsv'],
    ];

    // 2. Data Table
    $header = [
      'Name', 'Email', 'Event Date', 'College', 'Department', 'Submission Date'
    ];
    
    $rows = $this->getRegistrations($selected_date, $selected_event_id);

    $form['count'] = [
      '#markup' => '<p><strong>Total Participants: ' . count($rows) . '</strong></p>',
    ];

    $form['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No registrations found.'),
    ];

    return $form;
  }

  public function updateListing(array &$form, FormStateInterface $form_state) {
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {}

  public function exportCsv(array &$form, FormStateInterface $form_state) {
    $date = $form_state->getValue('filter_date');
    $event_id = $form_state->getValue('filter_event');
    $rows = $this->getRegistrations($date, $event_id);

    $csv_data = "Name,Email,Event Date,College,Department,Submission Date\n";
    foreach ($rows as $row) {
      $csv_data .= implode(',', $row) . "\n";
    }

    $response = new Response($csv_data);
    $response->headers->set('Content-Type', 'text/csv');
    $response->headers->set('Content-Disposition', 'attachment; filename="registrations.csv"');
    $form_state->setResponse($response);
  }

  // --- Helper Functions ---

  protected function getAllEventDates() {
    $query = $this->database->select('events_manager_event', 'e');
    $query->fields('e', ['event_date', 'event_date']);
    $query->distinct();
    return $query->execute()->fetchAllKeyed();
  }

  protected function getEventsByDate($date) {
    $query = $this->database->select('events_manager_event', 'e');
    $query->fields('e', ['id', 'event_name']);
    $query->condition('event_date', $date);
    return $query->execute()->fetchAllKeyed();
  }

  protected function getRegistrations($date = NULL, $event_id = NULL) {
    $query = $this->database->select('events_manager_registration', 'r');
    $query->join('events_manager_event', 'e', 'r.event_id = e.id');
    $query->fields('r', ['full_name', 'email', 'college', 'department', 'created']);
    $query->fields('e', ['event_date']);

    if ($date) {
      $query->condition('e.event_date', $date);
    }
    if ($event_id) {
      $query->condition('r.event_id', $event_id);
    }

    $results = $query->execute()->fetchAll();
    $rows = [];
    foreach ($results as $row) {
      $rows[] = [
        $row->full_name,
        $row->email,
        $row->event_date,
        $row->college,
        $row->department,
        date('Y-m-d H:i:s', $row->created),
      ];
    }
    return $rows;
  }
}