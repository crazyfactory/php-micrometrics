<?php

namespace CrazyFactory\MicroMetrics;


use CrazyFactory\ShopApi\Exception;

class MicroMetrics
{
	private $aggregator = array();
	private $aggregatorQueue = array();
	private $lastCheck;
	private $sensorQueue= array();
	private $treshold;

	/**
	 * MicoMetrics constructor
	 * @param int $last_checked : timestamp
	 * @param int $treshold_in_minutes : pauses between runs for this amount in minutes
	 */
	public function __construct( $last_checked=0, $treshold_in_minutes=5)
	{
		$this->lastCheck=$last_checked;
		$this->treshold=$treshold_in_minutes;
	}

	/**
	 * Adds a aggregator to its queue
	 * @param $aggregator the new Aggregator to add to the queue
	 * @return array with all queued Aggregators
	 */
	public function addToAggregatorQueue($aggregator)
	{
		$this->aggregatorQueue[]=$aggregator;
		return $this->aggregatorQueue;
	}

	/**
	 * adds a Sensor to Queue to process
	 * @param $sensor
	 * @return array $sensorQueue
	 */
	public function addToSensorQueue($sensor)
	{
		$this->aggregatorQueue[]=$sensor;
		return $this->sensorQueue;
	}

	/**
	 * exposes data collected by Aggregators
	 * @return array $aggregator contains data collected by all Aggregators
	 */
	public function getAggregatedData()
	{
		return $this->aggregator;
	}

	/**
	 * shifts an Aggregator from the start of the queue
	 * @return mixed next Aggregator to process
	 */
	private function getNextAggregator()
	{
		return array_shift($this->aggregatorQueue);
	}


	/**
	 * validates if we are ready to check again
	 * @param $last_check timestamp
	 * @param $treshold_in_minutes
	 * @return bool
	 */
	public static function ready($last_check, $treshold_in_minutes)
	{
		$next_check=$last_check + ($treshold_in_minutes*60);
		$proceed= time() > $next_check ? true : false;
		return $proceed;
	}

	/**
	 * runs the queued tasks one-by-one
	 * validated if the last check is long enough ago ($this->proceedExecution return true)
	 * @return void
	 */
	public function runAggregators()
	{
		if(self::ready($this->lastCheck, $this->treshold)){
			foreach($this->aggregatorQueue as $aggregator)
			{
				// run the aggregator
				$aggregator_name = $aggregator->getName();

				try{
					$this->aggregator[$aggregator_name]=$aggregator->aggregate();
				}
				catch (Exception $e) {
					$this->notify($e);
				}
			}

		}
		return $this->aggregator;
	}

	/**
	 * calls 'validate' methode of all sensors in sensorQueue
	 * @param array $aggregator_data is the collected data of the aggregators just run
	 * @return array with the results given back from
	 */
	public function runSensors($aggregator_data)
	{
		$response = array();
		foreach($this->sensorQueue as $sensor)
		{
			$sensor_name = $sensor->getName();
			try{
				$response[$sensor_name] = $sensor->validate($aggregator_data);
			}
			catch (Exception $e) {
				$this->notify($e);
			}
		}
		return $response;

	}

	/**
	 * sets an array as queues tasks
	 * this potentially override tasks set with MicroMetrics->addTask
	 * @param array $aggregator_queue
	 * @return array
	 * @throws Exception
	 */
	public function setAggregatorQueue($aggregator_queue)
	{
		if(is_array($aggregator_queue))
		{
			$this->aggregatorQueue=$aggregator_queue;
		}
		else{
			throw new Exception('MicroMetrics->setAggregatorQueue called with non-array parameter');
		}
		return $this->aggregatorQueue;
	}

	/**
	 * set a sensor to queue
	 * @param array $sensor_queue
	 * @return array $this->sensorQueue
	 * @throws Exception
	 */
	public function setSensorQueue($sensor_queue)
	{
		if(is_array($sensor_queue))
		{
			$this->sensorQueue = $sensor_queue;
		}
		else{
			throw new Exception('MicroMetrics->setAggregatorQueue called with non-array parameter');
		}
		return $this->sensorQueue;
	}


}
