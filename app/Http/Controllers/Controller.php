<?php

namespace App\Http\Controllers;

use App\Traits\HasDynamicPagination;
use App\Traits\HasOwnershipCheck;
use App\Traits\HasSearchable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests, HasDynamicPagination, HasOwnershipCheck, HasSearchable;
}
