<?php

namespace evaisse\SimpleHttpBundle\Serializer;

use evaisse\SimpleHttpBundle\Http\Request;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Flex\SymfonyBundle;

/**
 * Beurk, but we are stuck
 * The package in version <= 3.1 supports SF 4 & 5, whereas SF 4 does not typehint parameters & SF 5 does
 * Since version >= 3.2, we only support SF 6
 * So we musts provide in versions 3.1.* some code that is compatible with both SF 4 & 5 (if a patch version should drop SF 4 support, it would prevent any further patching compatible with SF 4
 */
if (Kernel::MAJOR_VERSION <= 4) {
    class RequestNormalizer extends GetSetMethodNormalizer
    {
        protected function getAttributeValue($object, $attribute, $format = null, array $context = [])
        {
            $ucfirsted = ucfirst($attribute);

            $haser = 'has' . $ucfirsted;
            if ($object instanceof Request && $attribute === 'session' && !$object->$haser()) {
                return null;
            }

            return parent::getAttributeValue($object, $attribute, $format, $context);
        }
    }
} else {
    class RequestNormalizer extends GetSetMethodNormalizer
    {
        protected function getAttributeValue(object $object, string $attribute, string $format = null, array $context = [])
        {
            $ucfirsted = ucfirst($attribute);

            $haser = 'has' . $ucfirsted;
            if ($object instanceof Request && $attribute === 'session' && !$object->$haser()) {
                return null;
            }

            return parent::getAttributeValue($object, $attribute, $format, $context);
        }
    }
}
