<?php

namespace Ernadoo\MondialRelayBundle\DataCollector;

use Ernadoo\MondialRelayBundle\SoapClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

class MondialRelayDataCollector extends DataCollector
{
	/**
	 * @var SoapClient
	 */
	protected $service;

	/**
	 * Constructor
	 *
	 * @param MyService $service
	 */
	public function __construct(\SoapClient $service)
	{
		$this->service = $service;
	}

	public function collect(Request $request, Response $response, \Throwable $exception = null)
	{
		$this->data = $this->service->getProfiles();

		foreach ($this->data as &$data)
		{
			$data['method']	= $this->cloneVar($data['method']);
			$data['params'] = $this->cloneVar($data['params']);
			$data['result'] = $this->cloneVar($data['result']);
		}
	}

	public function reset()
	{
		$this->data = [];
	}

	public function getName()
	{
		return 'ernadoo.mondialrelay';
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