<?php

/**
 * @package     Bw.Plugin.Task.ArticleHousekeeping
 * @copyright   (C) 2024 Barclay.Works
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace Bw\Plugin\Task\ArticleHousekeeping\Extension;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\SubscriberInterface;

/**
 * Article Housekeeping Task Plugin
 *
 * Provides scheduled tasks for automated article management operations:
 * - Move articles to category
 * - Archive articles
 * - Unpublish articles
 * - Change article access level
 *
 * @since  1.0.0
 */
final class ArticleHousekeeping extends CMSPlugin implements SubscriberInterface
{
    use TaskPluginTrait;
    use DatabaseAwareTrait;

    /**
     * Autoload plugin language files
     *
     * @var    boolean
     * @since  1.0.0
     */
    protected $autoloadLanguage = true;

    /**
     * Task routines map
     *
     * @var    array
     * @since  1.0.0
     */
    private const TASKS_MAP = [
        'bw.article.move' => [
            'langConstPrefix' => 'PLG_TASK_BW_ARTICLEHOUSEKEEPING_MOVE',
            'method'          => 'moveArticles',
            'form'            => 'move',
        ],
        'bw.article.archive' => [
            'langConstPrefix' => 'PLG_TASK_BW_ARTICLEHOUSEKEEPING_ARCHIVE',
            'method'          => 'archiveArticles',
            'form'            => 'archive',
        ],
        'bw.article.unpublish' => [
            'langConstPrefix' => 'PLG_TASK_BW_ARTICLEHOUSEKEEPING_UNPUBLISH',
            'method'          => 'unpublishArticles',
            'form'            => 'unpublish',
        ],
        'bw.article.access' => [
            'langConstPrefix' => 'PLG_TASK_BW_ARTICLEHOUSEKEEPING_ACCESS',
            'method'          => 'changeAccess',
            'form'            => 'access',
        ],
    ];

    /**
     * Constructor
     *
     * @param   DispatcherInterface  $dispatcher  The event dispatcher
     * @param   array                $config      Plugin configuration
     *
     * @since   1.0.0
     */
    public function __construct(DispatcherInterface $dispatcher, array $config)
    {
        parent::__construct($dispatcher, $config);
    }

    /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return  array
     *
     * @since   1.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList'    => 'advertiseRoutines',
            'onExecuteTask'        => 'standardRoutineHandler',
            'onContentPrepareForm' => 'enhanceTaskItemForm',
        ];
    }

    /**
     * Move articles to a target category
     *
     * @param   ExecuteTaskEvent  $event  The task event
     *
     * @return  int  Task status code
     *
     * @since   1.0.0
     */
    private function moveArticles(ExecuteTaskEvent $event): int
    {
        $params = $event->getArgument('params');

        // Validate target category
        $targetCategory = (int) ($params->target_category ?? 0);

        if ($targetCategory <= 0) {
            $this->logTask('Error: No target category specified');

            return Status::KNOCKOUT;
        }

        // Get affected articles
        $articles = $this->getAffectedArticles($params);

        if (empty($articles)) {
            $this->logTask('No articles match the specified criteria');

            return Status::OK;
        }

        // Dry run mode
        if ($params->dry_run ?? false) {
            $this->logTask(sprintf('DRY RUN: Would move %d articles to category %d', count($articles), $targetCategory));

            foreach ($articles as $article) {
                $this->logTask(sprintf('  - [%d] %s (current category: %d)', $article->id, $article->title, $article->catid));
            }

            return Status::OK;
        }

        // Execute move
        $articleIds = array_column($articles, 'id');
        $db         = $this->getDatabase();
        $query      = $db->getQuery(true)
            ->update($db->quoteName('#__content'))
            ->set($db->quoteName('catid') . ' = :catid')
            ->whereIn($db->quoteName('id'), $articleIds)
            ->bind(':catid', $targetCategory, ParameterType::INTEGER);

        $db->setQuery($query)->execute();

        $this->logTask(sprintf('Moved %d articles to category %d', count($articles), $targetCategory));

        return Status::OK;
    }

    /**
     * Archive articles
     *
     * @param   ExecuteTaskEvent  $event  The task event
     *
     * @return  int  Task status code
     *
     * @since   1.0.0
     */
    private function archiveArticles(ExecuteTaskEvent $event): int
    {
        $params = $event->getArgument('params');

        // Get affected articles
        $articles = $this->getAffectedArticles($params);

        if (empty($articles)) {
            $this->logTask('No articles match the specified criteria');

            return Status::OK;
        }

        // Dry run mode
        if ($params->dry_run ?? false) {
            $this->logTask(sprintf('DRY RUN: Would archive %d articles', count($articles)));

            foreach ($articles as $article) {
                $this->logTask(sprintf('  - [%d] %s', $article->id, $article->title));
            }

            return Status::OK;
        }

        // Execute archive (state = 2)
        $articleIds = array_column($articles, 'id');
        $state      = 2;
        $db         = $this->getDatabase();
        $query      = $db->getQuery(true)
            ->update($db->quoteName('#__content'))
            ->set($db->quoteName('state') . ' = :state')
            ->whereIn($db->quoteName('id'), $articleIds)
            ->bind(':state', $state, ParameterType::INTEGER);

        $db->setQuery($query)->execute();

        $this->logTask(sprintf('Archived %d articles', count($articles)));

        return Status::OK;
    }

    /**
     * Unpublish articles
     *
     * @param   ExecuteTaskEvent  $event  The task event
     *
     * @return  int  Task status code
     *
     * @since   1.0.0
     */
    private function unpublishArticles(ExecuteTaskEvent $event): int
    {
        $params = $event->getArgument('params');

        // Get affected articles
        $articles = $this->getAffectedArticles($params);

        if (empty($articles)) {
            $this->logTask('No articles match the specified criteria');

            return Status::OK;
        }

        // Dry run mode
        if ($params->dry_run ?? false) {
            $this->logTask(sprintf('DRY RUN: Would unpublish %d articles', count($articles)));

            foreach ($articles as $article) {
                $this->logTask(sprintf('  - [%d] %s', $article->id, $article->title));
            }

            return Status::OK;
        }

        // Execute unpublish (state = 0)
        $articleIds = array_column($articles, 'id');
        $state      = 0;
        $db         = $this->getDatabase();
        $query      = $db->getQuery(true)
            ->update($db->quoteName('#__content'))
            ->set($db->quoteName('state') . ' = :state')
            ->whereIn($db->quoteName('id'), $articleIds)
            ->bind(':state', $state, ParameterType::INTEGER);

        $db->setQuery($query)->execute();

        $this->logTask(sprintf('Unpublished %d articles', count($articles)));

        return Status::OK;
    }

    /**
     * Change article access level
     *
     * @param   ExecuteTaskEvent  $event  The task event
     *
     * @return  int  Task status code
     *
     * @since   1.0.0
     */
    private function changeAccess(ExecuteTaskEvent $event): int
    {
        $params = $event->getArgument('params');

        // Validate target access level
        $targetAccess = (int) ($params->target_access ?? 0);

        if ($targetAccess <= 0) {
            $this->logTask('Error: No target access level specified');

            return Status::KNOCKOUT;
        }

        // Get affected articles
        $articles = $this->getAffectedArticles($params);

        if (empty($articles)) {
            $this->logTask('No articles match the specified criteria');

            return Status::OK;
        }

        // Dry run mode
        if ($params->dry_run ?? false) {
            $this->logTask(sprintf('DRY RUN: Would change access to %d for %d articles', $targetAccess, count($articles)));

            foreach ($articles as $article) {
                $this->logTask(sprintf('  - [%d] %s (current access: %d)', $article->id, $article->title, $article->access));
            }

            return Status::OK;
        }

        // Execute access change
        $articleIds = array_column($articles, 'id');
        $db         = $this->getDatabase();
        $query      = $db->getQuery(true)
            ->update($db->quoteName('#__content'))
            ->set($db->quoteName('access') . ' = :access')
            ->whereIn($db->quoteName('id'), $articleIds)
            ->bind(':access', $targetAccess, ParameterType::INTEGER);

        $db->setQuery($query)->execute();

        $this->logTask(sprintf('Changed access level to %d for %d articles', $targetAccess, count($articles)));

        return Status::OK;
    }

    /**
     * Get articles matching the filter criteria
     *
     * @param   object  $params  Task parameters
     *
     * @return  array  Array of article objects
     *
     * @since   1.0.0
     */
    private function getAffectedArticles(object $params): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        // Calculate cutoff date
        $ageDays    = (int) ($params->age_days ?? 30);
        $cutoffDate = (new \DateTime())
            ->modify("-{$ageDays} days")
            ->format('Y-m-d H:i:s');

        // Determine which date field to use
        $dateField = $params->date_field ?? 'publish_up';

        if (!in_array($dateField, ['created', 'modified', 'publish_up'], true)) {
            $dateField = 'publish_up';
        }

        $query->select(['a.id', 'a.title', 'a.catid', 'a.state', 'a.access'])
            ->from($db->quoteName('#__content', 'a'))
            ->where($db->quoteName("a.{$dateField}") . ' < :cutoff')
            ->where($db->quoteName("a.{$dateField}") . ' IS NOT NULL')
            ->where($db->quoteName("a.{$dateField}") . ' != ' . $db->quote('0000-00-00 00:00:00'))
            ->bind(':cutoff', $cutoffDate);

        // Category filter
        $sourceCategory = (int) ($params->source_category ?? 0);

        if ($sourceCategory > 0) {
            $includeSubcats = (bool) ($params->include_subcategories ?? false);
            $categoryIds    = $this->getCategoryIds($sourceCategory, $includeSubcats);
            $query->whereIn($db->quoteName('a.catid'), $categoryIds);
        }

        // State filter
        $stateFilter = $params->state_filter ?? '1';

        if ($stateFilter !== '*') {
            $state = (int) $stateFilter;
            $query->where($db->quoteName('a.state') . ' = :state')
                ->bind(':state', $state, ParameterType::INTEGER);
        }

        return $db->setQuery($query)->loadObjectList();
    }

    /**
     * Get category IDs including subcategories if requested
     *
     * @param   int   $categoryId      Parent category ID
     * @param   bool  $includeSubcats  Whether to include subcategories
     *
     * @return  array  Array of category IDs
     *
     * @since   1.0.0
     */
    private function getCategoryIds(int $categoryId, bool $includeSubcats): array
    {
        $categoryIds = [$categoryId];

        if (!$includeSubcats) {
            return $categoryIds;
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        // Get parent category's lft and rgt values
        $query->select(['lft', 'rgt'])
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('id') . ' = :catid')
            ->bind(':catid', $categoryId, ParameterType::INTEGER);

        $parent = $db->setQuery($query)->loadObject();

        if (!$parent) {
            return $categoryIds;
        }

        // Get all descendant categories using nested set
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
            ->where($db->quoteName('lft') . ' > :lft')
            ->where($db->quoteName('rgt') . ' < :rgt')
            ->bind(':lft', $parent->lft, ParameterType::INTEGER)
            ->bind(':rgt', $parent->rgt, ParameterType::INTEGER);

        $subcats = $db->setQuery($query)->loadColumn();

        if (!empty($subcats)) {
            $categoryIds = array_merge($categoryIds, $subcats);
        }

        return $categoryIds;
    }
}
