<?php

namespace VersoBit\ResourceThreads\XFRM\Service\ResourceItem;

use XFRM\Entity\ResourceItem;

class Approve extends XFCP_Approve
{
    protected function onApprove()
    {
        $return = parent::onApprove();

        if ($return !== false)
        {
            $this->approveDiscussionThread($this->resource);
        }

        return $return;
    }

    protected function approveDiscussionThread(ResourceItem $resource)
    {
        $thread = $this->getResourceDiscussionThread($resource);
        if (!$thread || $thread->discussion_state !== 'moderated')
        {
            return;
        }

        /** @var \XF\Service\Thread\Approver $threadApprover */
        $threadApprover = \XF::service('XF:Thread\Approver', $thread);
        $threadApprover->approve();
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
