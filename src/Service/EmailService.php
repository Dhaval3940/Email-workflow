<?php

namespace Drupal\email_workflow\Service;

use Drupal\Core\Link;
use Drupal\Core\Mail\MailFormatHelper;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

/**
 * The DoStuff service. Does a bunch of stuff.
 */
class EmailService {

  /**
   * Constructs a new MailHandler object.
   */
  public function __construct() {

  }

  /**
   * Does something.
   *
   * @return string
   *   Some value.
   */
  public function mailTriggered($entity) {

    if ($entity->getEntityTypeId() !== 'node') {
      return '';
    }
    $approverlist = [];
    $uploaderlist = [];
    $key = '';
    $result = '';
    $mailManager = \Drupal::service('plugin.manager.mail');
    $module = 'email_workflow';

    if ($entity->bundle() == 'entry' || $entity->bundle() == 'basic_page_collapsible') {
      $current_entity_lang = $entity->get('langcode')->value;
      $translated_entity = $entity->getTranslation($current_entity_lang);

      $mod = $translated_entity->get('moderation_state')->getValue()[0]['value'];

      $nodeObj = Node::load($entity->id());

      if ($nodeObj->field_approvers_list) {
        $approver_uids = $nodeObj->field_approvers_list->getValue();
        foreach ($approver_uids as $key => $value) {
          $userObj = User::load($value['target_id']);
          $mail_id = $userObj->get('mail')->value;
          $approverlist[] = $mail_id;
        }
      }
      if ($nodeObj->field_uploaders_list) {
        $uploader_uids = $nodeObj->field_uploaders_list->getValue();
        foreach ($uploader_uids as $key => $value) {
          $userObj = User::load($value['target_id']);
          $mail_id = $userObj->get('mail')->value;
          $uploaderlist[] = $mail_id;
        }
      }

      $publishto = array_merge($approverlist, $uploaderlist);
      $url = Url::fromUri('internal:/node/' . $entity->id());
      $l = Link::fromTextAndUrl(t('Link'), $url, ['absolute' => TRUE])->toString();
      $absolute_url = MailFormatHelper::htmlToText($l);

      if ($approverlist || $uploaderlist) {
        switch ($mod) {
          case 'review':
            $key = 'review';
            $to = implode(",", $approverlist);

            $params['from'] = \Drupal::currentUser()->getEmail();
            $params['subject'] = t('@label - @id is ready for Review', [
              '@label' => $entity->label(),
              '@id' => $entity->id(),
            ]);
            $params['message'] = t('Please review the content - @label - @id at this link: @url', [
              '@label' => $entity->label(),
              '@id' => $entity->id(),
              '@url' => $absolute_url,
            ]);

            $params['node_title'] = $entity->label();

            $langcode = \Drupal::currentUser()->getPreferredLangcode();
            $send = TRUE;
            if ($to) {
              $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);
            }
            break;

          case 'send_back_to_uploader':
            $key = 'send_back_to_uploader';
            $to = implode(",", $uploaderlist);
            $params['from'] = \Drupal::currentUser()->getEmail();
            $params['subject'] = t('@label - @id has been sent back for Updating', [
              '@label' => $entity->label(),
              '@id' => $entity->id(),
            ]);
            $params['message'] = t('The content of @id - @label - has been sent back as it needs to be modified. Please update the content and resubmit for review at this link: @url', [
              '@id' => $entity->id(),
              '@label' => $entity->label(),
              '@url' => $absolute_url,
            ]);
            $params['node_title'] = $entity->label();

            $langcode = \Drupal::currentUser()->getPreferredLangcode();
            $send = TRUE;
            if ($to) {
              $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);
            }

            break;

          case 'published':
            $key = 'published';
            $to = implode(",", $uploaderlist);
            $params['from'] = \Drupal::currentUser()->getEmail();
            $params['subject'] = t('@label - @id has been Published', [
              '@label' => $entity->label(),
              '@id' => $entity->id(),
            ]);
            $params['message'] = t('The content of @id - @label - has been Published by Approver and is available at this link: @url', [
              '@id' => $entity->id(),
              '@label' => $entity->label(),
              '@url' => $absolute_url,
            ]);
            $params['node_title'] = $entity->label();
            $langcode = \Drupal::currentUser()->getPreferredLangcode();
            $send = TRUE;
            if ($to) {
              $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);
            }

            break;
        }
      }
    }
    return $result;
  }

  /**
   * Mailfortranslations function.
   */
  public function mailfortranslations($entity) {
    if ($entity->getEntityTypeId() !== 'node') {
      return;
    }
    $translationlist = [];
    $key = '';
    $result = '';
    $mailManager = \Drupal::service('plugin.manager.mail');
    $module = 'email_workflow';

    if ($entity->bundle() == 'entry' || $entity->bundle() == 'basic_page_collapsible') {
      $current_entity_lang = $entity->get('langcode')->value;
      if ($current_entity_lang !== 'en') {
        return;
      }

      $translated_entity = $entity->getTranslation($current_entity_lang);

      $nodeObj = Node::load($entity->id());

      if ($nodeObj->field_translation_team) {
        $translation_uids = $nodeObj->field_translation_team->getValue();
        foreach ($translation_uids as $key => $value) {
          $userObj = User::load($value['target_id']);
          $mail_id = $userObj->get('mail')->value;
          $translationlist[] = $mail_id;
        }
      }
      $url = Url::fromUri('internal:/node/' . $entity->id());
      $l = Link::fromTextAndUrl(t('Link'), $url, ['absolute' => TRUE])->toString();
      $absolute_url = MailFormatHelper::htmlToText($l);
      $key = 'translation';
      $to = implode(",", $translationlist);

      $params['from'] = \Drupal::currentUser()->getEmail();
      $params['subject'] = t('@label - @id is ready for translation', [
        '@label' => $entity->label(),
        '@id' => $entity->id(),
      ]);
      $params['message'] = t('Please translate the content - @label - @id at this link: @url', [
        '@label' => $entity->label(),
        '@id' => $entity->id(),
        '@url' => $absolute_url,
      ]);

      $params['node_title'] = $entity->label();

      $langcode = \Drupal::currentUser()->getPreferredLangcode();
      $send = TRUE;
      if ($to) {
        $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);
      }
    }
    return $result;
  }

}
