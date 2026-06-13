<?php

namespace VersoBit\ResourceThreads\Cli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use VersoBit\ResourceThreads\Repository\UpdatePost;
use XF\Entity\Post;
use XFRM\Entity\ResourceUpdate;

class Reconcile extends Command
{
    protected function configure()
    {
        $this
            ->setName('vb-resource-threads:reconcile')
            ->setDescription('Reports Resource Threads discussion synchronization issues.')
            ->addOption('repair', null, InputOption::VALUE_NONE, 'Repair safe update-post mapping and visible deleted-update posts.')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum rows to inspect per check.', 500);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $repair = (bool)$input->getOption('repair');
        $limit = max(1, (int)$input->getOption('limit'));

        $output->writeln('Resource Threads reconciliation');
        $output->writeln('Mode: ' . ($repair ? 'repair' : 'dry-run'));

        $stats = [
            'reported' => 0,
            'repaired' => 0
        ];

        $this->reportResourceThreadIssues($output, $limit, $stats);
        $this->reportModeratedUpdatePosts($output, $limit, $repair, $stats);
        $this->reportDeletedUpdatePosts($output, $limit, $repair, $stats);
        $this->reportUnmappedUpdatePosts($output, $limit, $repair, $stats);

        $output->writeln(sprintf(
            'Done. Reported %d record(s); repaired %d record(s).',
            $stats['reported'],
            $stats['repaired']
        ));

        return 0;
    }

    protected function reportResourceThreadIssues(OutputInterface $output, int $limit, array &$stats): void
    {
        $db = \XF::db();

        $resourcesWithoutThreads = $db->fetchAll("
            SELECT resource_id, title
            FROM xf_rm_resource
            WHERE discussion_thread_id = 0
            ORDER BY resource_id
            LIMIT {$limit}
        ");

        foreach ($resourcesWithoutThreads as $resource)
        {
            $this->writeReport($output, $stats, 'resource_no_discussion_thread', [
                'resource_id' => $resource['resource_id'],
                'title' => $resource['title']
            ]);
        }

        $resourcesMissingThreads = $db->fetchAll("
            SELECT r.resource_id, r.title, r.discussion_thread_id
            FROM xf_rm_resource AS r
            LEFT JOIN xf_thread AS t ON (t.thread_id = r.discussion_thread_id)
            WHERE r.discussion_thread_id <> 0
                AND t.thread_id IS NULL
            ORDER BY r.resource_id
            LIMIT {$limit}
        ");

        foreach ($resourcesMissingThreads as $resource)
        {
            $this->writeReport($output, $stats, 'resource_missing_discussion_thread', [
                'resource_id' => $resource['resource_id'],
                'thread_id' => $resource['discussion_thread_id'],
                'title' => $resource['title']
            ]);
        }
    }

    protected function reportModeratedUpdatePosts(OutputInterface $output, int $limit, bool $repair, array &$stats): void
    {
        /** @var UpdatePost $updatePostRepo */
        $updatePostRepo = \XF::repository('VersoBit\ResourceThreads:UpdatePost');

        $updates = \XF::finder('XFRM:ResourceUpdate')
            ->with('Resource')
            ->where('message_state', 'moderated')
            ->order('resource_update_id')
            ->limit($limit)
            ->fetch();

        foreach ($updates as $update)
        {
            /** @var ResourceUpdate $update */
            $post = $updatePostRepo->resolvePostForUpdate($update, $repair);

            if (!$post)
            {
                $this->writeReport($output, $stats, 'moderated_update_missing_post', [
                    'resource_update_id' => $update->resource_update_id,
                    'resource_id' => $update->resource_id
                ]);
                continue;
            }

            $this->writeReport($output, $stats, 'moderated_update_' . $post->message_state . '_post', [
                'resource_update_id' => $update->resource_update_id,
                'post_id' => $post->post_id,
                'post_state' => $post->message_state
            ]);
        }
    }

    protected function reportDeletedUpdatePosts(OutputInterface $output, int $limit, bool $repair, array &$stats): void
    {
        /** @var UpdatePost $updatePostRepo */
        $updatePostRepo = \XF::repository('VersoBit\ResourceThreads:UpdatePost');

        $updates = \XF::finder('XFRM:ResourceUpdate')
            ->with('Resource')
            ->where('message_state', 'deleted')
            ->order('resource_update_id')
            ->limit($limit)
            ->fetch();

        foreach ($updates as $update)
        {
            /** @var ResourceUpdate $update */
            $post = $updatePostRepo->resolvePostForUpdate($update, $repair);
            if (!$post || $post->message_state !== 'visible')
            {
                continue;
            }

            $this->writeReport($output, $stats, 'deleted_update_visible_post', [
                'resource_update_id' => $update->resource_update_id,
                'post_id' => $post->post_id
            ]);

            if ($repair)
            {
                $this->softDeletePost($post);
                $stats['repaired']++;
            }
        }
    }

    protected function reportUnmappedUpdatePosts(OutputInterface $output, int $limit, bool $repair, array &$stats): void
    {
        /** @var UpdatePost $updatePostRepo */
        $updatePostRepo = \XF::repository('VersoBit\ResourceThreads:UpdatePost');

        $posts = \XF::db()->fetchAll("
            SELECT p.post_id
            FROM xf_post AS p
            INNER JOIN xf_rm_resource AS r ON (r.discussion_thread_id = p.thread_id)
            LEFT JOIN " . UpdatePost::MAP_TABLE . " AS m ON (m.post_id = p.post_id)
            WHERE p.message LIKE '%/update/%'
                AND m.resource_update_id IS NULL
            ORDER BY p.post_id
            LIMIT {$limit}
        ");

        foreach ($posts as $postRow)
        {
            /** @var Post|null $post */
            $post = \XF::em()->find('XF:Post', $postRow['post_id']);
            if (!$post)
            {
                continue;
            }

            $resourceUpdateId = $updatePostRepo->getResourceUpdateIdFromPost($post);
            if (!$resourceUpdateId)
            {
                $this->writeReport($output, $stats, 'update_post_no_update_id', [
                    'post_id' => $post->post_id,
                    'thread_id' => $post->thread_id
                ]);
                continue;
            }

            /** @var ResourceUpdate|null $update */
            $update = \XF::em()->find('XFRM:ResourceUpdate', $resourceUpdateId);
            if (!$update)
            {
                $this->writeReport($output, $stats, 'update_post_missing_update', [
                    'post_id' => $post->post_id,
                    'resource_update_id' => $resourceUpdateId
                ]);
                continue;
            }

            if (!$updatePostRepo->postBelongsToResourceDiscussion($update, $post))
            {
                $this->writeReport($output, $stats, 'update_post_thread_mismatch', [
                    'post_id' => $post->post_id,
                    'resource_update_id' => $resourceUpdateId,
                    'thread_id' => $post->thread_id
                ]);
                continue;
            }

            $this->writeReport($output, $stats, 'update_post_unmapped', [
                'post_id' => $post->post_id,
                'resource_update_id' => $resourceUpdateId
            ]);

            if ($repair)
            {
                $updatePostRepo->recordPostForUpdate($update, $post, 'reconcile');
                $stats['repaired']++;
            }
        }
    }

    protected function softDeletePost(Post $post): void
    {
        /** @var \XF\Service\Post\Deleter $postDeleter */
        $postDeleter = \XF::service('XF:Post\Deleter', $post);
        $postDeleter->delete('soft', \XF::phrase('vb_resourcethreads_resource_update_deleted'));
    }

    protected function writeReport(OutputInterface $output, array &$stats, string $type, array $context): void
    {
        $details = [];
        foreach ($context as $key => $value)
        {
            $details[] = $key . '=' . json_encode($value);
        }

        $output->writeln('[' . $type . '] ' . implode(' ', $details));
        $stats['reported']++;
    }
}
