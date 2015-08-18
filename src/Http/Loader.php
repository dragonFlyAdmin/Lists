<?php

namespace DragonFly\Lists\Http;


use Illuminate\Contracts\Http\Kernel;

class Loader
{
	protected $kernel;

	public function __construct(Kernel $kernel) {
		$this->kernel = $kernel;
	}

	public function table($definition)
	{
		return $this->kernel->loadTable($definition);
	}
}