<?php

namespace VersoBit\ResourceThreads\XFRM\Service\ResourceUpdate;

use VersoBit\ResourceThreads\Repository\UpdatePost;
use XFRM\Entity\ResourceUpdate;

class Delete extends XFCP_Delete
{
    public function delete($type, $reason = '')
    {
        $result = parent::delete($type, $reason);

        if ($result)
        {
            $this->deleteDiscussionThreadPost($this->update);
        }

        return $result;
    }

    protected function deleteDiscussionThreadPost(ResourceUpdate $update)
    {
        /** @var UpdatePost $updatePostRepo */
        $updatePostRepo = \XF::repository('VersoBit\ResourceThreads:UpdatePost');
        $post = $updatePostRepo->resolvePostForUpdate($update);

        if (!$post || $post->message_state !== 'moderated')
        {
            return;
        }

        /** @var \XF\Service\Post\Deleter $postDeleter */
        $postDeleter = $this->service('XF:Post\Deleter', $post);
        $postDeleter->delete('soft', \XF::phrase('vb_resourcethreads_resource_update_deleted'));
    }
}
