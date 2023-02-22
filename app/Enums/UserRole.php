<?php

namespace App\Enums;

enum UserRole: string {
    case Admin = 'admin';
    case Developer = 'developer';
    case Manager = 'manager';
    case Editor = 'editor';
}
