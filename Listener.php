<?php

namespace VersoBit\ResourceThreads;

class Listener
{
    public static function appCliSetup(...$args): void
    {
        foreach ($args as $arg)
        {
            if ($arg instanceof \XF\Cli\Runner)
            {
                $arg->addCommand(
                    'vb-resource-threads:reconcile',
                    \VersoBit\ResourceThreads\Cli\Command\Reconcile::class
                );
                return;
            }
        }
    }
}
