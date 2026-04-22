<?php

declare(strict_types=1);

namespace App\Modules\User\Domain;

enum Role: string
{
    case Buyer = 'buyer';
    case Seller = 'seller';
    case Admin = 'admin';
}
