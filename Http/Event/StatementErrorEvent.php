<?php


namespace evaisse\SimpleHttpBundle\Http\Event;


use evaisse\SimpleHttpBundle\Http\Request;
use evaisse\SimpleHttpBundle\Http\Response;

class StatementErrorEvent extends AbstractStatementEvent
{
    use StatementResponseTrait;

    const KEY = "simple_http.statement_events.error";

    /** @var \Throwable */
    protected $throwable = null;

    /**
     * StatementErrorEvent constructor.
     * @param \Throwable $throwable
     */
    public function __construct(Request $request, \Throwable $throwable, Response $response = null)
    {
        parent::__construct($request);
        $this->response = $response;
        $this->throwable = $throwable;
    }

    /**
     * @return \Throwable
     */
    public function getThrowable(): \Throwable
    {
        return $this->throwable;
    }
}