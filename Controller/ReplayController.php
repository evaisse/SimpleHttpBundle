<?php
/**
 * Created by PhpStorm.
 * User: sroussel
 * Date: 03/02/2016
 * Time: 16:30
 */

namespace evaisse\SimpleHttpBundle\Controller;

use evaisse\SimpleHttpBundle\DataCollector\ProfilerDataCollector;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class ReplayController
 * @package evaisse\SimpleHttpBundle\Controller
 */
class ReplayController extends Controller
{

    /**
     * @Route("/_profiler/http-replay", name="simple_http.replay_request")
     * @Template()
     * @param Request $request
     * @return array
     */
    public function replayRequestAction(Request $request)
    {
        $request = json_decode($request->request->get('request'));

        $service = $this->get('service.helper')->prepare($request->method, $request->uri);
        $service->getRequest()->setContent($request->content);

        foreach ($request->headers as $header) {
            if (fnmatch('*:*', $header)) {
                list($headerName, $headerValue) = explode(':', $header, 2);
                trim($headerName);
                trim($headerValue);
                $service->getRequest()->headers->set($headerName, $headerValue);
            }
        }

        foreach ($request->cookies as $cookieName => $cookieValue) {
            $service->getRequest()->cookies->set($cookieName, $cookieValue);
        }


        $this->get('service.helper')->execute([
            $service,
        ]);

        return new Response('ok');
    }
}
