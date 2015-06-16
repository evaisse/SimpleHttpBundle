<?php
/**
 * Created by PhpStorm.
 * User: evaisse
 * Date: 29/05/15
 * Time: 14:22
 */

namespace evaisse\SimpleHttpBundle\Http;

use Symfony\Component\HttpKernel\Exception\HttpException;

class Error extends Exception
{

    /**
     * @var array Transport infos
     */
    protected $infos;


    /**
     * @param null $message
     * @param int $code
     * @param \Exception $previous
     * @param array $infos
     */
    public function __construct($message = null, $code = 0, \Exception $previous = null, array $infos = array())
    {
        parent::__construct(500, $message, $previous, array(), $code);
        $this->infos = $infos;
    }


    /**
     * @return array
     */
    public function getTransportInfos()
    {
        return $this->infos;
    }
}