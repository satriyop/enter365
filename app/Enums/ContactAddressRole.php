<?php

namespace App\Enums;

enum ContactAddressRole: string
{
    case Invoice = 'invoice';
    case Delivery = 'delivery';
    case Contact = 'contact';
}
