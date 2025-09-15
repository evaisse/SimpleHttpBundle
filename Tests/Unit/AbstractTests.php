<?php
/**
 * Created by PhpStorm.
 * User: evaisse
 * Date: 02/06/15
 * Time: 11:15
 */

namespace evaisse\SimpleHttpBundle\Tests\Unit;

use evaisse\SimpleHttpBundle\Http\Kernel;
use evaisse\SimpleHttpBundle\Service\Helper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

class AbstractTests extends TestCase
{
    public static $baseUrl = null;

    public function __construct(?string $name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        self::$baseUrl = self::$baseUrl ?? getenv('HTTP_BIN_URL');
    }


    /**
     * @return array{0: Helper, 1: Kernel}
     */
    protected function createContext()
    {
        $eventDispatcher = new EventDispatcher();
        $httpKernel = new Kernel($eventDispatcher);
        $helper = new Helper($httpKernel, $eventDispatcher);

        return [$helper, $httpKernel];
    }


}