<?php
/**
 * Created by PhpStorm.
 * User: evaisse
 * Date: 19/06/15
 * Time: 10:53
 */

namespace evaisse\SimpleHttpBundle\Http;

/**
 * Class StatementEventMap
 * @package evaisse\SimpleHttpBundle\Http
 */
abstract class StatementEventMap
{
    public const KEY_PREPARE = 'simple_http.statement_events.prepare';
    public const KEY_SUCCESS = 'simple_http.statement_events.success';
    public const KEY_ERROR = 'simple_http.statement_events.error';
}


