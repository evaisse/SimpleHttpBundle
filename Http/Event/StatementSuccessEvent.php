<?php


namespace evaisse\SimpleHttpBundle\Http\Event;

use evaisse\SimpleHttpBundle\Http\Request;
use evaisse\SimpleHttpBundle\Http\Response;

/**
 * Class StatementSuccessEvent
 * @package evaisse\SimpleHttpBundle\Http\Event
 */
class StatementSuccessEvent extends AbstractStatementEvent
{
    use StatementResponseTrait;

    const KEY = "simple_http.statement_events.success";

    /**
     * StatementSuccessEvent constructor.
     */
    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request);
        $this->response = $response;
    }
}