<?php
/**
 * Created by PhpStorm.
 * User: evaisse
 * Date: 05/05/15
 * Time: 15:38
 */

namespace evaisse\SimpleHttpBundle\Serializer;

use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;

class CustomGetSetNormalizer extends GetSetMethodNormalizer
{
    /**
     * {@inheritdoc}
     */
    public function normalize(mixed $object, string $format = null, array $context = [])
    {
        if ($object instanceof \Throwable) {
            return $this->normalizeThrowable($object);
        }
        return parent::normalize($object, $format, $context);
    }

    /**
     * @param \Throwable $e throwable to normalize
     * @return array normalized output
     */
    protected function normalizeThrowable(\Throwable $e)
    {
        $data = array(
            'class'   => get_class($e),
            'message' => $e->getMessage(),
            'code'    => $e->getCode(),
            'file'    => $e->getFile().':'.$e->getLine(),
            'trace'   => $e->getTraceAsString(),
        );

        if ($previous = $e->getPrevious()) {
            $data['previous'] = $this->normalizeThrowable($previous);
        }

        return $data;
    }
}
