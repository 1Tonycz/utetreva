<?php

declare(strict_types=1);

namespace App\Admin\UI\Error4xx;

use App\Admin\UI\BasePresenter;
use Nette\Application\Attributes\Requires;
use Nette\Application\BadRequestException;


/**
 * Handles 4xx HTTP error responses.
 */
#[Requires(methods: '*', forward: true)]
final class Error4xxPresenter extends BasePresenter
{
	public function renderDefault(BadRequestException $exception): void
	{
		// renders the appropriate error template based on the HTTP status code
		$code = $exception->getCode();
		$file = is_file($file = __DIR__ . "/$code.latte")
			? $file
			: __DIR__ . '/4xx.latte';
		$this->template->httpCode = $code;
		$this->template->setFile($file);

	}

}
