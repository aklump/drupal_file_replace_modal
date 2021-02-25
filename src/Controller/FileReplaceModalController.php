<?php

namespace Drupal\file_replace_modal\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilder;
use Drupal\file\Entity\File;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;

class FileReplaceModalController extends ControllerBase {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The ModalFormExampleController constructor.
   *
   * @param \Drupal\Core\Form\FormBuilder $formBuilder
   *   The form builder.
   */
  public function __construct(FormBuilder $formBuilder, EntityTypeManagerInterface $entity_type_manager) {
    $this->formBuilder = $formBuilder;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Callback to display the modal for file replacement.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request instance.
   * @param \Drupal\file\Entity\File $file
   *   The file entity to replace.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response to open the modal with our replace form.
   */
  public function getModal(Request $request, File $file) {

    // The ajax context tells us the controlling entity.  It is passed to the
    // form and is why we modify the standard form to be submitted via AJAX.
    $ajax_context = $request->get('_ajax_context');
    if (!$ajax_context) {
      throw new NotAcceptableHttpException('Missing ?_ajax_context={entity_type_id}.{id}');
    }

    // Load the standard form, which will be modified for AJAX.
    list($context_entity_type_id, $context_id) = explode('.', $ajax_context);
    $form_object = $this->entityTypeManager
      ->getFormObject('file', 'replace')
      ->setEntity($file);
    $build = $this->formBuilder->getForm($form_object, 'use_ajax', $context_entity_type_id, $context_id);

    // Deliver the AJAX-ified form back into the modal.
    $response = new AjaxResponse();
    $response->addCommand(new OpenModalDialogCommand($this->t('Replace file'), $build));

    return $response;
  }

}
