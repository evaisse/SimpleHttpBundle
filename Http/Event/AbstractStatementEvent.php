<?php
/**
 * Created by PhpStorm.
 * User: evaisse
 * Date: 19/06/15
 * Time: 10:53
 */

namespace evaisse\SimpleHttpBundle\Http\Event;

use evaisse\SimpleHttpBundle\Http\Request;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractStatementEvent extends Event
{
    /** @var Request */
    protected $request;

    /**
     * Event constructor.
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * @return Request
     */
    public function getRequest(): Request
    {
        return $this->request;
    }
}


