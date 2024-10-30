<?php
/**
 * Created by PhpStorm.
 * User: sroussel
 * Date: 03/02/2016
 * Time: 16:30
 */

namespace evaisse\SimpleHttpBundle\Controller;

use evaisse\SimpleHttpBundle\Service\Helper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class ReplayController
 * @package evaisse\SimpleHttpBundle\Controller
 */
class ReplayController extends AbstractController
{
    /** @var Helper $serviceHelper */
    protected $serviceHelper;

    /**
     * @param Helper $serviceHelper
     */
    public function __construct(Helper $serviceHelper)
    {
        $this->serviceHelper = $serviceHelper;
    }

    /**
     * @Route("/http-replay", name="simple_http.replay_request")
     * @param Request $request
     * @return Response
     */
    public function replayRequestAction(Request $request): Response
    {
        $request = json_decode($request->request->get('request'));

        $service = $this->serviceHelper->prepare($request->method, $request->uri);
        $service->getRequest()->setContent($request->content);

        foreach ($request->headers as $header) {
            if (fnmatch('*:*', $header)) {
                list($headerName, $headerValue) = explode(':', $header, 2);
                $headerName = trim($headerName);
                $headerValue = trim($headerValue);
                $service->getRequest()->headers->set($headerName, $headerValue);
            }
        }

        foreach ($request->cookies as $cookieName => $cookieValue) {
            $service->getRequest()->cookies->set($cookieName, $cookieValue);
        }


        $this->serviceHelper->execute([
            $service,
        ]);

        return new Response('ok');
    }
}
