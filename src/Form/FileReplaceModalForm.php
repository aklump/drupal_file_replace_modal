<?php

namespace Drupal\file_replace_modal\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\file_replace_modal\Controller\FileReplaceWidgetController;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A service to manage the file_replace form for AJAX.
 */
class FileReplaceModalForm implements ContainerInjectionInterface {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * FileReplaceModalForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MessengerInterface $messenger) {
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('messenger')
    );
  }

  /**
   * Alter the file_replace form to submit via AJAX.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function ajaxifyForm(array &$form, FormStateInterface $form_state) {

    // @link https://www.drupal.org/node/2897377
    $form['#id'] = Html::getId($form_state->getBuildInfo()['form_id']);

    $form['actions']['submit']['#ajax'] = [
      'callback' => [$this, 'ajaxResponse'],
      'event' => 'click',
    ];
  }

  /**
   * Returns the AJAX response from a modal/ajax submission.
   */
  public function ajaxResponse($form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    // Get the context of the parent so we can make a helpful status report.
    $args = $form_state->getBuildInfo()['args'] ?? [];
    array_shift($args);
    list($parent_type_id, $parent_id) = $args;

    // Cannot use $form_state because the parent form does not report errors that
    // way, only reports it in the messenger object.
    $has_any_errors = count($this->messenger
        ->messagesByType(MessengerInterface::TYPE_ERROR)) > 0;

    // Nothing more to do, it was successful, so tell them, and hide the stuff
    // that would be confusing to show again.
    if (!$has_any_errors) {
      $parent = $this->entityTypeManager
        ->getStorage($parent_type_id)
        ->load($parent_id);
      $this->messenger
        ->addStatus(t('%title has also been saved with this change.', [
          '%title' => $parent->label(),
        ]));
      $form['original']['#access'] = FALSE;
      $form['replacement']['#access'] = FALSE;
      $form['actions']['#access'] = FALSE;
    }

    $form['status_messages'] = [
      '#type' => 'status_messages',
      '#weight' => -1000,
    ];
    $form['#sorted'] = FALSE;

    $response->addCommand(new ReplaceCommand('[data-drupal-selector="' . $form['#attributes']['data-drupal-selector'] . '"]', $form));

    return $response;
  }

  /**
   * Adds the "replace" UI to a file widget.
   *
   * @param $element
   * @param $context
   */
  public function widgetAlter(&$element, $context) {

    // There is nothing to replace, if there is no file.
    $fid = $element['#default_value']['target_id'] ?? NULL;
    if (!$fid) {
      return;
    }
    $file = $this->entityTypeManager->getStorage('file')->load($fid);
    if (!$file) {
      return;
    }
    $wrapper_entity = $context['items']->getParent()->getEntity();
    $url = $this->getModalFormUrl($file, $wrapper_entity);

    $parents_prefix = implode('_', $element['#parents'] ?? [
        $context['items']->getName(),
        $context['delta'],
      ]);
    $is_multiple = $element['#multiple'] ?? FALSE;

    // Create an "Operations" element to open the replace modal.
    $element['replace_button'] = [
      '#name' => $parents_prefix . '_replace_button',

      // We have to make this a "submit" type because that is how
      // template_preprocess_file_widget_multiple() decides that this is going
      // to be an "Operations" element.  It's not actually going to submit the
      // form though, I presume this is because bindAjaxLinks takes control of
      // the rendered HTML input element.
      '#type' => 'submit',
      '#value' => $is_multiple ? t('Replace selected') : t('Replace'),

      // These are necessary to leverage the AJAX modal API.  For more
      // information take a look at Drupal.ajax.bindAjaxLinks().
      '#attributes' => [
        'href' => $url->toString(),
        'data-accepts' => 'application/vnd.drupal-modal',
        'class' => ['use-ajax'],
      ],
      '#attached' => [
        'library' => [
          'core/drupal.dialog.ajax',
        ],
      ],
    ];
  }

  /**
   * Return the URL to load the modal form via AJAX.
   *
   * @param \Drupal\file\Entity\File $file
   *   The file to be replaced.
   * @param \Drupal\Core\Entity\EntityInterface $wrapper_entity
   *   The entity that has a lease on $file.
   *
   * @return \Drupal\Core\Url
   *   The URL instance.
   */
  public function getModalFormUrl(File $file, EntityInterface $wrapper_entity) {
    return Url::fromRoute('file_replace_modal.form', ['file' => $file->id()], [
      'query' => [
        '_ajax_context' => implode('.', [
          $wrapper_entity->getEntityTypeId(),
          $wrapper_entity->id(),
        ]),
      ],
    ]);
  }

}
