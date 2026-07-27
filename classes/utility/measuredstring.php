<?php

namespace TFAuthLS;

class Utility_MeasuredString
{

	public $string;
	/**
  * @var int
  */
 public $length;

	public function __construct($string)
	{
		$this->string = $string;
		$this->length = strlen($string);
	}

	public function __toString(): string
	{
		return $this->string;
	}
}
