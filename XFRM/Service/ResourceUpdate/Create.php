<?php

namespace VersoBit\ResourceThreads\XFRM\Service\ResourceUpdate;

use VersoBit\ResourceThreads\Repository\UpdatePost;

class Create extends XFCP_Create
{
    protected function _save()
    {
        $update = parent::_save();

        /** @var UpdatePost $updatePostRepo */
        $updatePostRepo = \XF::repository('VersoBit\ResourceThreads:UpdatePost');
        $post = $updatePostRepo->resolvePostForUpdate($update, false);

        if ($post)
        {
            $updatePostRepo->recordPostForUpdate($update, $post, 'created');
        }

        return $update;
    }
}
