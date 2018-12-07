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
    public function normalize($object, $format = null, array $context = array())
    {
        if ($object instanceof \Exception) {
            return $this->normalizeException($object);
        }
        return parent::normalize($object, $format, $context);
    }


    /**
     * @param Exception $e exception to normalize
     * @return array normalized output
     */
    protected function normalizeException(\Exception $e)
    {
        $data = array(
            'class'   => get_class($e),
            'message' => $e->getMessage(),
            'code'    => $e->getCode(),
            'file'    => $e->getFile().':'.$e->getLine(),
            'trace'   => $e->getTraceAsString(),
        );

        if ($previous = $e->getPrevious()) {
            $data['previous'] = $this->normalizeException($previous);
        }

        return $data;
    }

}