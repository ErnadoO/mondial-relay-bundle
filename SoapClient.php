<?php

namespace Ernadoo\MondialRelayBundle;

use Symfony\Component\Stopwatch\{Stopwatch, StopwatchEvent};

class SoapClient extends \SoapClient
{
	/**
	 * @var integer
	 */
	protected $counter = 1;

	/**
	 * @var array $profiles Profiled data
	 */
	protected $profiles = array();

	/**
	 * @var Stopwatch $stopwatch Symfony profiler Stopwatch service
	 */
	protected $stopwatch;

	function __construct(Stopwatch $stopwatch, $wsdl, $options = [])
	{
		$this->stopwatch = $stopwatch;

		parent::__construct($wsdl, $options);
	}

	public function __call($method, $arguments)
	{
		$event = $this->startProfiling($method, $arguments);

		$result = parent::__soapCall($method, $arguments);

		$this->stopProfiling($event, $result->{$method . 'Result'});

		return $result;
	}

	public function getProfiles()
	{
		return $this->profiles;
	}

	/**
	 * Starts profiling
	 *
	 * @param string $query Query text
	 *
	 * @return StopwatchEvent
	 */
	protected function startProfiling($method, $arguments)
	{
		if ($this->stopwatch instanceof Stopwatch) {
			$this->profiles[$this->counter] = array(
				'method'		=> $method,
				'params'		=> $arguments[0],
				'duration'		=> null,
				'memory_start'	=> memory_get_usage(true),
				'memory_end'	=> null,
				'memory_peak'	=> null,
				'result'		=> null,
			);

			return $this->stopwatch->start('mr');
		}
	}

	/**
	 * Stops the profiling
	 *
	 * @param StopwatchEvent $event A stopwatchEvent instance
	 */
	protected function stopProfiling(StopwatchEvent $event = null, $result)
	{
		if ($this->stopwatch instanceof Stopwatch) {
			$event->stop();

			$values = array(
				'duration'		=> $event->getDuration(),
				'memory_end'	=> memory_get_usage(true),
				'memory_peak'	=> memory_get_peak_usage(true),
				'result'		=> (array) $result,
			);

			$this->profiles[$this->counter] = array_merge($this->profiles[$this->counter], $values);

			$this->counter++;
		}
	}
}
