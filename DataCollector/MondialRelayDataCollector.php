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
	 * @var MondialRelayWebAPI
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

	public function collect(Request $request, Response $response, \Throwable $exception = null)
	{
		$this->data = $this->service->getProfiles();

		foreach ($this->data as &$data)
		{
			$data['query'] = $this->cloneVar($data['query']);
			$data['result'] = $this->cloneVar($data['result']);
		}
	}

	public function reset()
	{
		$this->data = [];
	}

	public function getName()
	{
		return 'app.request_collector';
	}

	/**
	 * Returns an array of collected requests.
	 */
	public function getQueries(): array
	{
		return $this->data;
	}

	/**
	 * Returns the execution time of all collected requests in seconds.
	 */
	public function getTotalDuration(): float
	{
		$time = 0;
		foreach ($this->data as $command) {
			$time += $command['duration'];
		}

		return $time;
	}
}