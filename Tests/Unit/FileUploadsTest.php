<?php
/**
 * Created by PhpStorm.
 * User: evaisse
 * Date: 02/06/15
 * Time: 11:15
 */

namespace evaisse\SimpleHttpBundle\Tests\Unit;



use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileUploadsTest extends AbstractTests
{


    public function testPutFile()
    {
        list($helper, $httpKernel, $container) = $this->createContext();
//
//        *
//        * @param string $path         The full temporary path to the file
//    * @param string $originalName The original file name
//    * @param string $mimeType     The type of the file as provided by PHP
//    * @param int    $size         The file size
//    * @param int    $error        The error constant of the upload (one of PHP's UPLOAD_ERR_XXX constants)
//     * @param bool   $test         Whether the test mode is active
//     *
//     * @throws FileException         If file_uploads is disabled
//     * @throws FileNotFoundException If the file does not exist
//     *
//     * @api
//     */
//    public function __construct($path, $originalName, $mimeType = null, $size = null, $error = null, $test = false)

        $file = new UploadedFile(
            __FILE__,
            basename(__FILE__),
            'text/plain',
            strlen(file_get_contents(__FILE__)),
            0
        );

        $stmt = $helper->prepare("PUT", 'http://httpbin.org/put');
        $stmt->getRequest()->files->set('test', $file);
        $stmt->execute($httpKernel);


        var_dump($stmt->getResult());
    }


    public function testDownloadFile()
    {

    }




}