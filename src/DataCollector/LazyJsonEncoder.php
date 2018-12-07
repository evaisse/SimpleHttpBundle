<?php
/**
 * User: evaisse
 * Date: 29/11/2016
 * Time: 15:05
 */
namespace evaisse\SimpleHttpBundle\DataCollector;

use Symfony\Component\Serializer\Encoder\JsonEncoder;

/**
 * Class LazyJsonEncoder
 * @package evaisse\SimpleHttpBundle\DataCollector
 */
class LazyJsonEncoder extends JsonEncoder
{

    /**
     * @var string
     */
    protected $encoding;

    /**
     * LazyJsonEncoder constructor.
     * @param string $encoding specify an origin encoding
     */
    public function __construct($encoding = 'utf-8')
    {
        parent::__construct();
        $this->encoding = strtolower($encoding);
    }


    /**
     * Encodes an ISO-8859-1 mixed data, string, array into UTF-8
     *
     * @param mixed $data data to encode
     * @return mixed data encoded
     */
    public function utf8Encode($data)
    {
        if (is_string($data)) {
            return utf8_encode($data);
        } else if ($data instanceof \ArrayObject) {
            $data = $data->getArrayCopy();
        } else if($data instanceof \Exception) {
            return $data->__toString();
        } else if (is_object($data)) {
            $ovs = get_object_vars($data);
            $new = clone $data;
            foreach ($ovs as $k => $v)    {
                if ($new instanceof \ArrayObject) {
                    $new[$k] = $this->utf8Encode($new[$k]);
                } else {
                    $new->$k = $this->utf8Encode($new->$k);
                }
            }
            return $new;
        } else if (!is_array($data)) {
            return $data;
        }

        $ret = array();

        foreach ($data as $i => $d) {
            $ret[$this->utf8Encode($i)] = $this->utf8Encode($d);
        }

        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    public function encode($data, $format, array $context = array())
    {
        try {
            if ($this->encoding !== 'utf-8') {
                $data = $this->utf8Encode($data);
            }
            return $this->encodingImpl->encode($data, self::FORMAT, $context);
        } catch (\Exception $e) {
            $data = $this->utf8Encode($data); // safely try to force encoding
            try {
                return json_encode($data);
            } catch (\Exception $e) {
                return "{}";
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function decode($data, $format, array $context = array())
    {
        try {
            return $this->decodingImpl->decode($data, self::FORMAT, $context);
        } catch (\Exception $e) {
            return json_decode($data);
        }
    }


}