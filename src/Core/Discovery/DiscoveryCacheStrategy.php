<?php

declare (strict_types=1);
namespace JooosiIcon\Core\Discovery;

defined('ABSPATH') || exit;
enum DiscoveryCacheStrategy : string
{
    case NONE = 'none';
    case PARTIAL = 'partial';
    case FULL = 'full';
}
