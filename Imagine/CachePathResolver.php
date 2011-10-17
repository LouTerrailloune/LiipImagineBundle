<?php

namespace Liip\ImagineBundle\Imagine;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpKernel\Util\Filesystem;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CachePathResolver
{
    /**
     * @var Symfony\Component\Routing\RouterInterface
     */
    private $router;

    /**
     * @var Symfony\Component\HttpKernel\Util\Filesystem
     */
    private $filesystem;

    /**
     * @var string
     */
    private $webRoot;

    /**
     * Constructs cache path resolver with a given web root and cache prefix
     *
     * @param Symfony\Component\HttpFoundation\Request      $request
     * @param Symfony\Component\Routing\RouterInterface     $router
     * @param Symfony\Component\HttpKernel\Util\Filesystem  $filesystem
     * @param string                                        $webRoot
     */
    public function __construct(RouterInterface $router, Filesystem $filesystem, $webRoot)
    {
        $this->router       = $router;
        $this->filesystem   = $filesystem;
        $this->webRoot      = realpath($webRoot);
    }

    /**
     * Gets filtered path for rendering in the browser
     *
     * @param string $path
     * @param string $filter
     */
    public function getBrowserPath($targetPath, $filter)
    {
        $params = array('path' => ltrim($targetPath, '/'));

        $path = str_replace(
            urlencode($params['path']),
            urldecode($params['path']),
            $this->router->generate('_imagine_'.$filter, $params)
        );

        return $path;
    }

    /**
     * Resolves filtered path for rendering in the browser
     *
     * @param Request $request
     * @param string $path
     * @param string $filter
     */
    public function resolve(Request $request, $targetPath, $filter)
    {
        //TODO: find out why I need double urldecode to get a valid path
        $browserPath = urldecode(urldecode($this->getBrowserPath($targetPath, $filter)));

         // if cache path cannot be determined, return 404
        if (null === $browserPath) {
            throw new NotFoundHttpException('Image doesn\'t exist');
        }

        $basePath = $request->getBaseUrl();
         if (!empty($basePath) && 0 === strpos($browserPath, $basePath)) {
             $browserPath = substr($browserPath, strlen($basePath));
        }

        $targetPath = $this->webRoot.$browserPath;

        // if the file has already been cached, we're probably not rewriting
        // correctly, hence make a 301 to proper location, so browser remembers
        if (file_exists($targetPath)) {
            return new Response('', 301, array(
                'location' => $request->getBasePath().$browserPath
            ));
        }

        return $targetPath;
    }

    public function store($targetPath, $image)
    {
        $dir = pathinfo($targetPath, PATHINFO_DIRNAME);

        if (!is_dir($dir) && !$this->filesystem->mkdir($dir)) {
            throw new \RuntimeException(sprintf(
                'Could not create directory %s', $dir
            ));
        }

        file_put_contents($targetPath, $image);
    }
}
