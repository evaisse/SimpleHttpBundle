<?php

namespace evaisse\SimpleHttpBundle\Serializer;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;

class RequestNormalizer extends GetSetMethodNormalizer
{
    protected function getAttributeValue(object $object, string $attribute, string $format = null, array $context = [])
    {
        $ucfirsted = ucfirst($attribute);

        $haser = 'has'.$ucfirsted;
        if ($object instanceof Request && $attribute === 'session' && !$object->$haser()) {
            return null;
        }

        return parent::getAttributeValue($object, $attribute, $format, $context);
    }
}
