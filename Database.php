<?php

namespace FpDbTest;
use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

	public function buildQuery(string $query, array $args = []): string
	{
		$k = 0;
		$transformValue = function ($val, string $type, string $q = "'") use (&$transformValue) {
			if ($type == "#" ? !is_array($val) : $type == '' && is_string($val)) { # Для строк и идентификаторов
				return $q.$this->mysqli->real_escape_string($val).$q;
			} elseif (is_array($val)) { # Массивы  ни к чему не приводятся
				if (in_array($type, ['#', 'a'])) {
					foreach ($val as $k => $v) {
						$val[$k] = (is_numeric($k) ? '' : "`$k` = ") . $transformValue($v, '', $q);
					}
					return implode(', ', $val);
				}
			} elseif (in_array($type, ['d', 'f', ''])) { # Числа
				return is_null($val) ? 'NULL' : (($type == 'd' || is_bool($val)) ? (int) $val : $val);
			}

			throw new Exception("Неверный тип значения");
		};

		$query = preg_replace_callback('/(\s|\()\?([dfa\#])?(\s|\)|\}|$)/', function($ms) use (&$k, &$transformValue, &$args) {
			return "{$ms[1]}{$transformValue($args[$k++], $ms[2], $ms[2] == '#' ? "`" : "'")}{$ms[3]}";
		}, $query);

		return preg_replace_callback('/\{([^}]+)\}/', fn($ms) => str_contains($ms[1], $this->skip()) ? '' : $ms[1], $query);
	}

	public function skip()
	{
		return 0;
	}
}