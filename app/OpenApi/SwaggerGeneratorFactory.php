<?php

namespace App\OpenApi;

use L5Swagger\CustomGeneratorInterface;
use OpenApi\Generator;

class SwaggerGeneratorFactory implements CustomGeneratorInterface
{
    public function create(): Generator
    {
        return (new SwaggerGenerator)->useDocBlockAnalyser();
    }
}
