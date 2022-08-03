<?php

declare(strict_types=1);

namespace Shaarli\Front\Controller\Admin;

use Psr\Http\Message\UploadedFileInterface;
use Shaarli\Netscape\NetscapeBookmarkUtils;
use Shaarli\Security\SessionManager;
use Shaarli\TestCase;
use Slim\Http\ServerRequest;
use Slim\Http\Response;
use Laminas\Diactoros\UploadedFile;

class ImportControllerTest extends TestCase
{
    use FrontAdminControllerMockHelper;

    /** @var ImportController */
    protected $controller;

    public function setUp(): void
    {
        $this->createContainer();

        $this->controller = new ImportController($this->container);
    }

    /**
     * Test displaying import page
     */
    public function testIndex(): void
    {
        $assignedVariables = [];
        $this->assignTemplateVars($assignedVariables);

        $request = $this->createMock(ServerRequest::class);
        $response = new Response();

        $result = $this->controller->index($request, $response);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame('import', (string) $result->getBody());

        static::assertSame('Import - Shaarli', $assignedVariables['pagetitle']);
        static::assertIsInt($assignedVariables['maxfilesize']);
        static::assertRegExp('/\d+[KM]iB/', $assignedVariables['maxfilesizeHuman']);
    }

    /**
     * Test importing a file with default and valid parameters
     */
    public function testImportDefault(): void
    {
        $parameters = [
            'abc' => 'def',
            'other' => 'param',
        ];

        $requestFile = new UploadedFile('file', 123, UPLOAD_ERR_OK, 'name', 'type');

        $request = $this->createMock(ServerRequest::class);
        $request->method('getParams')->willReturnCallback(function () use ($parameters) {
            return $parameters;
        });
        $request->method('getUploadedFiles')->willReturn(['filetoupload' => $requestFile]);
        $response = new Response();

        $this->container->netscapeBookmarkUtils = $this->createMock(NetscapeBookmarkUtils::class);
        $this->container->netscapeBookmarkUtils
            ->expects(static::once())
            ->method('import')
            ->willReturnCallback(
                function (
                    array $post,
                    UploadedFileInterface $file
                ) use (
                    $parameters,
                    $requestFile
                ): string {
                    static::assertSame($parameters, $post);
                    static::assertSame($requestFile, $file);

                    return 'status';
                }
            )
        ;

        $this->container->sessionManager
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_SUCCESS_MESSAGES, ['status'])
        ;

        $result = $this->controller->import($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/admin/import'], $result->getHeader('location'));
    }

    /**
     * Test posting an import request - without import file
     */
    public function testImportFileMissing(): void
    {
        $request = $this->createMock(ServerRequest::class);
        $response = new Response();

        $this->container->sessionManager
            ->expects(static::once())
            ->method('setSessionParameter')
            ->with(SessionManager::KEY_ERROR_MESSAGES, ['No import file provided.'])
        ;

        $result = $this->controller->import($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/admin/import'], $result->getHeader('location'));
    }

    /**
     * Test posting an import request - with an empty file
     */
    public function testImportEmptyFile(): void
    {
        $requestFile = new UploadedFile('file', 0, UPLOAD_ERR_OK, 'name', 'type');;

        $request = $this->createMock(ServerRequest::class);
        $request->method('getUploadedFiles')->willReturn(['filetoupload' => $requestFile]);
        $response = new Response();

        $this->container->netscapeBookmarkUtils = $this->createMock(NetscapeBookmarkUtils::class);
        $this->container->netscapeBookmarkUtils->expects(static::never())->method('filterAndFormat');

        $this->container->sessionManager
            ->expects(static::once())
            ->method('setSessionParameter')
            ->willReturnCallback(function (string $key, array $value): SessionManager {
                static::assertSame(SessionManager::KEY_ERROR_MESSAGES, $key);
                static::assertStringStartsWith('The file you are trying to upload is probably bigger', $value[0]);

                return $this->container->sessionManager;
            })
        ;

        $result = $this->controller->import($request, $response);

        static::assertSame(302, $result->getStatusCode());
        static::assertSame(['/subfolder/admin/import'], $result->getHeader('location'));
    }
}
