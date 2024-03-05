<?php

declare(strict_types=1);

namespace Dev\ViewBundle\Serializer\Normalizer;

class ViewNormalizerFactory
{
    public function create(): ViewNormalizer
    {
        return new ViewNormalizer();
    }
}