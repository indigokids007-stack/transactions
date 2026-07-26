<?php

namespace App\Enums;

enum UserRole: string
{
    case Staff = 'staff';
    case Manager = 'manager';
    case Owner = 'owner';
    case Admin = 'admin';
}
