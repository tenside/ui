<?php

/**
 * This file is part of tenside/ui.
 *
 * (c) Christian Schiffler <https://github.com/discordier>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    tenside/ui
 * @author     Christian Schiffler <https://github.com/discordier>
 * @copyright  Christian Schiffler <https://github.com/discordier>
 * @link       https://github.com/tenside/ui
 * @license    https://github.com/tenside/ui/blob/master/LICENSE MIT
 * @filesource
 */

namespace Tenside\Ui\Web\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouteCollection;
use Tenside\Web\Controller\AbstractController;

/**
 * Provide access to all embedded asset files.
 */
class UiController extends AbstractController
{
    /**
     * {@inheritdoc}
     */
    public static function createRoutes(RouteCollection $routes)
    {
        static::createRoute($routes, 'rootRedirect', '/');
        static::createRoute($routes, 'index', '/index.html');
        static::createRoute($routes, 'install', '/install.html');

        static::createRoute(
            $routes,
            'asset',
            '/{path}/{file}',
            ['GET'],
            [
                'path' => 'css|fonts|img|js|pages|l10n',
                'file' => '[\-\_a-zA-Z0-9]*(\.[a-zA-Z0-9]*)+'
            ]
        );
    }

    /**
     * Redirect any request to the "/" path to the index.html file.
     *
     * @param Request $request The request to process.
     *
     * @return RedirectResponse
     */
    public function rootRedirectAction(Request $request)
    {
        $uri = $request->getUri();
        // Special case, not correctly setup yet. Do so now.
        if (!$this->getTenside()->isInstalled()) {
            return new RedirectResponse($uri . 'install.html');
        }

        return new RedirectResponse($uri . 'index.html');
    }

    /**
     * Provide the index.html file.
     *
     * @param Request $request The request to process.
     *
     * @return Response
     */
    public function indexAction(Request $request)
    {
        // Special case, not correctly setup yet. Do so now.
        if (!$this->getTenside()->isInstalled()) {
            return new RedirectResponse($request->getUri() . 'install.html');
        }

        return new Response(
            str_replace(
                'var TENSIDEApi=window.location.href.split(\'#\')[0];',
                'var TENSIDEApi=\'' . $request->getSchemeAndHttpHost() . $request->getBaseUrl() . '/\';',
                file_get_contents($this->getAssetsDir() . '/index.html')
            ),
            200,
            array(
                'Content-Type' => 'text/html; charset=UTF-8'
            )
        );
    }

    /**
     * Provide the install.html file.
     *
     * @param Request $request The request to process.
     *
     * @return Response
     */
    public function installAction(Request $request)
    {
        // Special case, already setup. Redirect to index then.
        if ($this->getTenside()->isInstalled()) {
            return new RedirectResponse($request->getUri() . 'index.html');
        }

        return new Response(
            str_replace(
                'var TENSIDEApi=window.location.href.split(\'#\')[0];',
                'var TENSIDEApi=\'' . $request->getSchemeAndHttpHost() . $request->getBaseUrl() . '/\';',
                file_get_contents($this->getAssetsDir() . '/install.html')
            ),
            200,
            array(
                'Content-Type' => 'text/html; charset=UTF-8'
            )
        );
    }

    /**
     * Provide an asset.
     *
     * @param string  $path    The assets sub dir.
     *
     * @param string  $file    The file name within the sub dir..
     *
     * @param Request $request The request to process.
     *
     * @return Response
     */
    public function assetAction($path, $file, Request $request)
    {
        $filePath = $this->getAssetsDir() . '/' . $path . '/' . $file;

        if (!file_exists($filePath)) {
            return new Response($filePath . ' not found', 404);
        }

        $response = new Response();
        $response->setPublic();
        $response->headers->addCacheControlDirective('must-revalidate', true);

        $lastModified = filemtime($filePath);

        $response->setETag(md5($filePath . $lastModified));
        $response->setLastModified(new \DateTime('@' . $lastModified));

        if ($response->isNotModified($request)) {
            // return the 304 Response immediately
            return $response;
        }

        $mime = $this->getMime($filePath);
        if ($mime !== null) {
            $response->headers->set('Content-Type', $mime);
        }

        return $response->setContent(file_get_contents($filePath));
    }

    /**
     * Retrieve the mime type of a file.
     *
     * @param string $filePath The filename for which the mime shall be guessed.
     *
     * @return string|null
     */
    private function getMime($filePath)
    {
        $chunks = explode('.', $filePath);
        if (count($chunks) > 1) {
            $fileExtension = array_pop($chunks);
            foreach ([
                    'js' => 'text/javascript; charset=UTF-8',
                    'html' => 'text/html; charset=UTF-8',
                    'css' => 'text/css; charset=UTF-8',
                    'png' => 'image/png',
                    'svg' => 'image/svg+xml',
                    'woff' => 'application/font-woff',
                    'woff2' => 'application/font-woff',
                    'map' => 'application/json',
                    'json' => 'application/json',
                ] as $extension => $mimeType) {
                if ($fileExtension === $extension) {
                    return $mimeType;
                }
            }
        }

        return null;
    }

    /**
     * Retrieve the assets dir.
     *
     * @return string
     *
     * @throws \RuntimeException When the assets dir can not be located.
     */
    private function getAssetsDir()
    {
        if ($phar = \Phar::running()) {
            return $phar . '/assets';
        }

        // FIXME: hardcoded assets path for non phar mode - change this!
        $dir = dirname(__DIR__);
        while (($dir = dirname($dir)) !== '.') {
            if (is_dir($dir . '/.build')) {
                return $dir . '/.build';
            }
        }

        throw new \RuntimeException('Could not find assets directory.');
    }
}
