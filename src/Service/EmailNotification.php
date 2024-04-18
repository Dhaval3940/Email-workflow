<?php

namespace Drupal\email_workflow\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Mail\MailFormatHelper;
use Drupal\Core\Mail\MailManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The DoStuff service. Does a bunch of stuff.
 */
class EmailNotification {


  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $request;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The Logger Factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $loggerFactory;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Config Factory service object.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new MailHandler object.
   *
   * @param Symfony\Component\HttpFoundation\RequestStack $request
   *   The Request Stack.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The Entity Type Manager.
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   *   The Mail Manager .
   * @param \Drupal\Core\Database\Connection $connection
   *   The Connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $loggerFactory
   *   The logger Factory.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The Date Formatter.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The Config Factory.
   */
  public function __construct(RequestStack $request, EntityTypeManagerInterface $entityTypeManager, MailManagerInterface $mailManager, Connection $connection, LoggerChannelFactory $loggerFactory, DateFormatterInterface $dateFormatter, ConfigFactoryInterface $configFactory) {
    $this->request = $request;
    $this->entityTypeManager = $entityTypeManager;
    $this->mailManager = $mailManager;
    $this->database = $connection;
    $this->loggerFactory = $loggerFactory;
    $this->dateFormatter = $dateFormatter;
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
    // Load the service required to construct this class.
      $container->get('request_stack'),
    $container->get('entity_type.manager'),
    $container->get('plugin.manager.mail'),
    $container->get('database'),
    $container->get('logger.factory'),
    $container->get('date.Formatter'),
    $container->get('config.factory'),
    );
  }

  /**
   * Does something.
   *
   * @return string
   *   Some value.
   */
  public function getExpiringNodes() {
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->exists('publish_on')
      ->condition('publish_on', [strtotime('now'), strtotime('7 days')], 'BETWEEN')
      ->latestRevision()
      ->sort('publish_on')
      ->sort('nid');
    $query->accessCheck(FALSE);
    $nids = $query->execute();
    return $nids;
  }

  /**
   * Send mail.
   */
  public function SendMail($nids) {
    /** @var \Drupal\node\NodeInterface[] $nodes */
    $nodes = $this->loadNodes($nids);
    foreach ($nodes as $node_multilingual) {
      $languages = $node_multilingual->getTranslationLanguages();
      foreach ($languages as $language) {
        $node = $node_multilingual->getTranslation($language->getId());
        if (!empty($node) && $node->get('moderation_state')->getString() == 'draft') {
          $langcode = $node->get('langcode')->value;
          $path = $node->toUrl();
          $options = ['absolute' => TRUE];
          $l = Link::fromTextAndUrl(t('Link'), $path, $options)->toString();
          $absolute_url = MailFormatHelper::htmlToText($l);
          if ($node->field_approvers_list) {
            $approver_uids = $node->field_approvers_list->getValue();
            foreach ($approver_uids as $key => $value) {
              $userObj = $this->entityTypeManager->getStorage('user')->load($value['target_id']);
              $to = $userObj->get('mail')->value;
              $params['from'] = $this->configFactory->get('system.site')->get('mail');
              $params['subject'] = t('Please approve for this content @title', ['@title' => $node->getTitle()]);
              $params['message'] = t('Please approve the content - @title - @id at this link: @url', [
                '@title' => $node->getTitle(),
                '@id' => $node->id(),
                '@url' => $absolute_url,
              ]);
              $send = TRUE;
              $message = $this->mailManager->mail('email_workflow', 'email_notification', $to, $langcode, $params, NULL, $send);
              if ($message['result']) {
                $this->loggerFactory->get('email_workflow')->notice('Successfully sent email to %recipient', ['%recipient' => $to]);
              }
            }
          }
        }
      }
    }
  }

  /**
   * Helper method to load latest revision of each node.
   *
   * @param array $nids
   *   Array of node ids.
   *
   * @return array
   *   Array of loaded nodes.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function loadNodes(array $nids) {
    $node_storage = $this->entityTypeManager->getStorage('node');
    $nodes = [];

    // Load the latest revision for each node.
    foreach ($nids as $nid) {
      $node = $node_storage->load($nid);
      $revision_ids = $node_storage->revisionIds($node);
      $vid = end($revision_ids);
      $nodes[] = $node_storage->loadRevision($vid);
    }

    return $nodes;
  }

}
