<?php

namespace App\Enums;

enum RoleContext: string
{
    case Tenant = 'tenant';
    case Administration = 'administration';
}
