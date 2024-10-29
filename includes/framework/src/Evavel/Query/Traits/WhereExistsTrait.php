<?php

namespace Evavel\Query\Traits;

use Closure;
use Evavel\Database\DB;

trait WhereExistsTrait
{
	public function whereExists($subqueryClosure, Closure $callback = null, $boolean = 'and')
	{
		// Crear una subquery
		$subQuery = new static();
		call_user_func($subqueryClosure, $subQuery);

		// Construir la cláusula WHERE EXISTS
		$subSql = $subQuery->buildSql();
		$existsClause = "EXISTS ({$subSql})";

		// Agregar la cláusula a las condiciones WHERE
		$this->whereRawExists($existsClause, $boolean);

		return $this;
	}

	public function whereRawExists($sql, $boolean = 'and')
	{
		$this->bindings['where'][] = [
			'type' => 'raw',
			'sql' => $sql,
			'boolean' => $boolean
		];

		return $this;
	}

	// Estos métodos deben estar implementados en la clase que use este trait
	abstract protected function validateTableName($tableName);
	abstract public function buildSql();
	abstract public static function table($tableName, $fromClass = null, $keepTableName = false);
	abstract public function select($columns);
}
