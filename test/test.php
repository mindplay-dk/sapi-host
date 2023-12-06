<?php

namespace test;

use Error;
use function in_array;
use Kodus\Http\SapiFunctions;
use Kodus\Http\SapiHost;
use function mindplay\testies\configure;
use function mindplay\testies\enabled;
use function mindplay\testies\eq;
use function mindplay\testies\expect;
use function mindplay\testies\ok;
use function mindplay\testies\run;
use function mindplay\testies\test;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreatorInterface;
use function ob_get_clean;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

$ROOT = dirname(__DIR__);

require "{$ROOT}/vendor/autoload.php";

configure()->enableCodeCoverage(__DIR__ . "/build/coverage.xml", ["{$ROOT}/src"]);

class MockServerRequestCreator implements ServerRequestCreatorInterface
{
    public function fromGlobals(): ServerRequestInterface
    {
        return (new Psr17Factory())->createServerRequest("GET", "http://test/");
    }

    public function fromArrays(
        array $server,
        array $headers = [],
        array $cookie = [],
        array $get = [],
        ?array $post = [],
        array $files = [],
        $body = null
    ): ServerRequestInterface {
        throw new Error("NOT IMPLEMENTED");
    }

    public static function getHeadersFromServer(array $server): array
    {
        throw new Error("NOT IMPLEMENTED");
    }
}

class SapiCapture implements SapiFunctions
{
    /**
     * @var string[] list of "Name: value" header strings
     */
    public $headers = [];

    /**
     * @var int|null
     */
    public $status;

    /**
     * @var bool
     */
    public $finished = false;

    /**
     * @var bool
     */
    public $headers_sent = false;

    public function emitHeader(string $string, bool $replace = true, ?int $http_response_code = null): void
    {
        $this->headers[] = $string;

        if ($http_response_code) {
            $this->status = $http_response_code;
        }
    }

    public function headersSent(?string &$file, ?int &$line): bool
    {
        $file = "mock_file.php";
        $line = 123;

        return $this->headers_sent;
    }

    public function finishRequest(): void
    {
        $this->finished = true;
    }
}

const EXPECTED_BUFFER_LENGTH = 8192;

function create_host(?SapiCapture $sapi = null): SapiHost
{
    $factory =  new Psr17Factory();

    return new SapiHost(
        $factory,
        $factory,
        $factory,
        $factory,
        $factory,
        EXPECTED_BUFFER_LENGTH,
        new MockServerRequestCreator(),
        $sapi ?: new SapiCapture()
    );
}

test(
    "can dispatch Handler",
    function () {
        $host = create_host();

        $handler = new class implements RequestHandlerInterface {
            public $dispatched = false;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->dispatched = true;

                return (new Psr17Factory())->createResponse(200);
            }
        };

        $host->dispatch($handler);

        ok($handler->dispatched);
    }
);

class CapturedOutput
{
    /**
     * @var SapiCapture
     */
    public $sapi;

    /**
     * @var string
     */
    public $body;

    public function __construct(SapiCapture $sapi, $body)
    {
        $this->sapi = $sapi;
        $this->body = $body;
    }
}

class MockResponseHandler implements RequestHandlerInterface
{
    private $response;

    public function __construct(ResponseInterface $response)
    {
        $this->response = $response;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->response;
    }
}

function capture_output_from(ResponseInterface $response): CapturedOutput
{
    $sapi = new SapiCapture();

    ob_start();

    create_host($sapi)->dispatch(new MockResponseHandler($response));

    $body = ob_get_clean();

    return new CapturedOutput($sapi, $body);
}

test(
    "can emit Response",
    function () {
        $expected_body = "HELLO WORLD";

        $captured = capture_output_from(
            (new Psr17Factory())
                ->createResponse(418, "I'm a teapot")
                ->withHeader("X-Hello", "Hey")
                ->withProtocolVersion("2.0")
                ->withBody(
                    (new Psr17Factory())->createStream($expected_body)
                ));

        eq(
            $captured->body,
            $expected_body,
            "can emit Response body"
        );

        ok(
            in_array("HTTP/2.0 418 I'm a teapot", $captured->sapi->headers),
            "can emit protocol version, status code and reason"
        );

        ok(
            in_array("X-Hello: Hey", $captured->sapi->headers),
            "can emit header"
        );
    }
);

test(
    "dispatch should fail if headers already sent",
    function () {
        $sapi = new SapiCapture();

        $sapi->headers_sent = true;

        $handler = new MockResponseHandler((new Psr17Factory())->createResponse(200));

        $host = create_host($sapi);

        expect(
            RuntimeException::class,
            "should throw if headers already sent",
            function () use ($host, $handler) {
                $host->dispatch($handler);
            },
            "/" . preg_quote("Unable to emit response: Headers already sent in file \"mock_file.php\" line 123") . "/i"
        );
    }
);

exit(run());
