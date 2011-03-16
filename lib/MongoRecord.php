<?php

interface MongoRecord
{
	public static function setFindTimeout($timeout);
	public static function find($query);
	public static function findOne($query);
}

