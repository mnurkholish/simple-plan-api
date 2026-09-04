<?php

namespace App\OpenApi;

use OpenApi\Analysers\AnalyserInterface;
use OpenApi\Analysers\AttributeAnnotationFactory;
use OpenApi\Analysers\DocBlockAnnotationFactory;
use OpenApi\Analysers\ReflectionAnalyser;
use OpenApi\Generator;

class SwaggerGenerator extends Generator
{
    private bool $docBlockAnalyserLocked = false;

    public function useDocBlockAnalyser(): self
    {
        parent::setAnalyser(new ReflectionAnalyser([
            new AttributeAnnotationFactory,
            new DocBlockAnnotationFactory,
        ]));

        $this->docBlockAnalyserLocked = true;

        return $this;
    }

    public function setAnalyser(?AnalyserInterface $analyser): Generator
    {
        if ($this->docBlockAnalyserLocked) {
            return $this;
        }

        return parent::setAnalyser($analyser);
    }
}
