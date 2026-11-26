<?php

namespace App\Enums;

enum TeamPermission: string
{
    case AddMember = 'member:add';
    case UpdateMember = 'member:update';
    case RemoveMember = 'member:remove';
}
