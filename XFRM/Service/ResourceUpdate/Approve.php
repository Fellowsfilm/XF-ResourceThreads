<?php

namespace VersoBit\ResourceThreads\XFRM\Service\ResourceUpdate;

use VersoBit\ResourceThreads\Repository\UpdatePost;
use XFRM\Entity\ResourceUpdate;

class Approve extends XFCP_Approve
{
    protected function onApprove()
    {
        $return = parent::onApprove();

        if ($return !== false)
        {
            $this->approveDiscussionThreadPost($this->update);
        }

        return $return;
    }

    protected function approveDiscussionThreadPost(ResourceUpdate $update)
    {
        /** @var UpdatePost $updatePostRepo */
        $updatePostRepo = \XF::repository('VersoBit\ResourceThreads:UpdatePost');
        $post = $updatePostRepo->resolvePostForUpdate($update);

        if (!$post || $post->message_state !== 'moderated')
        {
            return;
        }

        /** @var \XF\Service\Post\Approver $postApprover */
        $postApprover = \XF::service('XF:Post\Approver', $post);
        $postApprover->approve();
    }
}
