<?php

namespace VersoBit\ResourceThreads\Repository;

use XF\Entity\Post;
use XF\Mvc\Entity\Repository;
use XFRM\Entity\ResourceUpdate;

class UpdatePost extends Repository
{
    public const MAP_TABLE = 'xf_versobit_resource_threads_update_post';

    public function recordPostForUpdate(ResourceUpdate $update, Post $post, string $source = 'created'): void
    {
        $resourceUpdateId = (int)$update->resource_update_id;
        $postId = (int)$post->post_id;

        if (!$resourceUpdateId || !$postId || !$this->postBelongsToResourceDiscussion($update, $post))
        {
            return;
        }

        $this->db()->query("
            INSERT INTO " . self::MAP_TABLE . "
                (resource_update_id, post_id, map_source, map_date)
            VALUES
                (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                post_id = VALUES(post_id),
                map_source = VALUES(map_source),
                map_date = VALUES(map_date)
        ", [$resourceUpdateId, $postId, $source, \XF::$time]);
    }

    public function getMappedPost(ResourceUpdate $update): ?Post
    {
        $postId = $this->getMappedPostId((int)$update->resource_update_id);
        if (!$postId)
        {
            return null;
        }

        /** @var Post|null $post */
        $post = \XF::em()->find('XF:Post', $postId);
        if (!$post || !$this->postBelongsToResourceDiscussion($update, $post))
        {
            return null;
        }

        return $post;
    }

    public function getMappedPostId(int $resourceUpdateId): int
    {
        if (!$resourceUpdateId)
        {
            return 0;
        }

        return (int)$this->db()->fetchOne("
            SELECT post_id
            FROM " . self::MAP_TABLE . "
            WHERE resource_update_id = ?
        ", [$resourceUpdateId]);
    }

    public function resolvePostForUpdate(ResourceUpdate $update, bool $recordResolvedPost = true, string $source = 'legacy'): ?Post
    {
        $post = $this->getMappedPost($update);
        if (!$post)
        {
            $post = $this->findPostByRouteAwareLink($update) ?: $this->findPostByUpdateId($update);

            if ($post && $recordResolvedPost)
            {
                $this->recordPostForUpdate($update, $post, $source);
            }
        }

        return $post;
    }

    public function findPostByRouteAwareLink(ResourceUpdate $update): ?Post
    {
        $threadId = $this->getDiscussionThreadId($update);
        if (!$threadId)
        {
            return null;
        }

        foreach ($this->getUpdateLinkNeedles($update) as $needle)
        {
            /** @var Post|null $post */
            $post = $this->finder('XF:Post')
                ->where('thread_id', $threadId)
                ->where('message', 'LIKE', '%' . $needle . '%')
                ->order('post_date', 'DESC')
                ->fetchOne();

            if ($post && $this->postBelongsToResourceDiscussion($update, $post))
            {
                return $post;
            }
        }

        return null;
    }

    public function findPostByUpdateId(ResourceUpdate $update): ?Post
    {
        $threadId = $this->getDiscussionThreadId($update);
        $resourceUpdateId = (int)$update->resource_update_id;
        if (!$threadId || !$resourceUpdateId)
        {
            return null;
        }

        /** @var \XF\Mvc\Entity\ArrayCollection $posts */
        $posts = $this->finder('XF:Post')
            ->where('thread_id', $threadId)
            ->where('message', 'LIKE', '%update/' . $resourceUpdateId . '%')
            ->order('post_date', 'DESC')
            ->limit(50)
            ->fetch();

        foreach ($posts as $post)
        {
            /** @var Post $post */
            if ($this->postMessageReferencesUpdate($post, $update))
            {
                return $post;
            }
        }

        return null;
    }

    public function postMessageReferencesUpdate(Post $post, ResourceUpdate $update): bool
    {
        return $this->getResourceUpdateIdFromPost($post) === (int)$update->resource_update_id;
    }

    public function getResourceUpdateIdFromPost(Post $post): int
    {
        if (!preg_match('~(?:^|[^\w])update/(\d+)(?:\D|$)~i', $post->message, $match))
        {
            return 0;
        }

        return (int)$match[1];
    }

    public function getDiscussionThreadId(ResourceUpdate $update): int
    {
        $resource = $update->Resource;
        if (!$resource || !$resource->discussion_thread_id)
        {
            return 0;
        }

        return (int)$resource->discussion_thread_id;
    }

    public function postBelongsToResourceDiscussion(ResourceUpdate $update, Post $post): bool
    {
        $threadId = $this->getDiscussionThreadId($update);

        return $threadId && (int)$post->thread_id === $threadId;
    }

    protected function getUpdateLinkNeedles(ResourceUpdate $update): array
    {
        $needles = [];

        try
        {
            $router = \XF::app()->router('public');
            foreach (['resources/update', 'canonical:resources/update'] as $route)
            {
                $link = (string)$router->buildLink($route, $update);
                if (!$link)
                {
                    continue;
                }

                $needles[] = $link;

                $path = parse_url($link, PHP_URL_PATH);
                if ($path)
                {
                    $needles[] = $path;
                    $needles[] = ltrim($path, '/');
                }
            }
        }
        catch (\Throwable $e)
        {
            // Route building is a convenience resolver. The update-id resolver below is the legacy fallback.
        }

        return array_values(array_unique(array_filter($needles, function ($needle)
        {
            return is_string($needle) && strlen($needle) >= 6;
        })));
    }
}
