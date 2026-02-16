<?php

declare(strict_types=1);

namespace App\Error;

use Nette\Application\Attributes\Requires;
use Nette\Application\BadRequestException;
use Nette\Application\IPresenter;
use Nette\Application\Request;
use Nette\Application\Response;
use Nette\Application\Responses;
use Nette\Http\IRequest;
use Nette\Http\IResponse;
use Tracy\ILogger;


/**
 * Handles uncaught exceptions and errors, and logs them.
 */
#[Requires(forward: true)]
final class ErrorPresenter implements IPresenter
{
	public function __construct(
		private ILogger $logger,
        private \Nette\Http\Request $http
	) {
	}


	public function run(Request $request): Response
	{
		// Log the exception
		$exception = $request->getParameter('exception');

        if ($exception instanceof BadRequestException) {
            $module = "Front:";

            if (str_contains($this->http->getUrl()->getPath(), "/_admin/")) {
                $module = "Admin:";
            }

            return new Responses\ForwardResponse($request->setPresenterName($module . 'Error4xx'));
        }

		$this->logger->log($exception, ILogger::EXCEPTION);
		// Display a generic error message to the user
		return new Responses\CallbackResponse(function (IRequest $httpRequest, IResponse $httpResponse): void {
			if (preg_match('#^text/html(?:;|$)#', (string) $httpResponse->getHeader('Content-Type'))) {
				require __DIR__ . '/500.phtml';
			}
		});
	}
}
