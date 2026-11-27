<?php

namespace App\OpenApi;

use OpenApi\Analysers\AnalyserInterface;
use OpenApi\Analysers\AttributeAnnotationFactory;
use OpenApi\Analysers\DocBlockAnnotationFactory;
use OpenApi\Analysers\ReflectionAnalyser;
use OpenApi\Generator;
use OpenApi\Processors\AugmentProperties;

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

        // All API properties are declared in DocBlocks, without PHP property types
        // to infer. This processor repeatedly parses the large documentation block.
        $this->getProcessorPipeline()->remove(AugmentProperties::class);

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
