<?php

// src/DataCollector/RequestCollector.php
namespace Ernadoo\MondialRelayBundle\DataCollector;

use Ernadoo\MondialRelay\MondialRelayWebAPI;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

class MondialRelayDataCollector extends DataCollector
{
	/**
	 * @var MyService
	 */
	protected $service;

	/**
	 * Constructor
	 *
	 * @param MyService $service
	 */
	public function __construct(MondialRelayWebAPI $service)
	{
		$this->service = $service;
	}

	public function collect(Request $request, Response $response, \Exception $exception = null)
	{
		$this->data = [
			'method' => $request->getMethod(),
			'acceptable_content_types' => $request->getAcceptableContentTypes(),
		];
	}

	public function reset()
	{
		$this->data = [];
	}

	public function getName()
	{
		return 'app.request_collector';
	}

	public function getMethod()
	{
		return $this->data['method'];
	}

	public function getAcceptableContentTypes()
	{
		return $this->data['acceptable_content_types'];
	}
}