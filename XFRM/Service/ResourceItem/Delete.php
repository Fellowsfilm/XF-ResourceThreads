<?php

namespace VersoBit\ResourceThreads\XFRM\Service\ResourceItem;

use XFRM\Entity\ResourceItem;

class Delete extends XFCP_Delete
{
    public function delete($type, $reason = '')
    {
        $result = parent::delete($type, $reason);

        if ($result)
        {
            $this->deleteDiscussionThread($this->resource);
        }

        return $result;
    }

    protected function deleteDiscussionThread(ResourceItem $resource)
    {
        $thread = $this->getResourceDiscussionThread($resource);
        if (!$thread || $thread->discussion_state !== 'moderated')
        {
            return;
        }

        /** @var \XF\Service\Thread\Deleter $threadDeleter */
        $threadDeleter = $this->service('XF:Thread\Deleter', $thread);
        $threadDeleter->delete('soft', \XF::phrase('vb_resourcethreads_resource_deleted'));
    }

    protected function getResourceDiscussionThread(ResourceItem $resource): ?\XF\Entity\Thread
    {
        if (!$resource->discussion_thread_id)
        {
            return null;
        }

        return $resource->Discussion ?: null;
    }
}
