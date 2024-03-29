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
		$transformValue = function ($val, string $type, string $q = "'") use (&$transformValue) {
			if (is_array($val)) { # Массивы
				if (in_array($type, ['#', 'a'])) { # Вероятен идентификатор как массив
					foreach ($val as $k => $v) {
						# (ключ = значение) только для ?a - спецификатора, значение форматируется в зависимости от его типа (идентично
						$val[$k] = ((is_numeric($k) || $type == '#') ? '' : "`$k` = ") . $transformValue($v, '', $q); # '' - универсальному параметру без спецификатора)
					}
					return implode(', ', $val); # Массивы  ни к чему не приводятся кроме как к строке
				}
			} elseif (is_string($val) && in_array($type, ['#', ''])) { # Для строк и идентификаторов (позволяем использовать числа: $type == '#' && is_numeric($val)
				return $q.$this->mysqli->real_escape_string($val).$q; # Cтроки и идентификаторы автоматически экранируются.
			} elseif (in_array($type, ['d', 'f', ''])) { # Числа & null
				# return is_null($val) ? 'NULL' : (($type == 'd' || is_bool($val)) ? (int) $val : $val);
				if (is_numeric($val) || is_bool($val)) {
					return $type == 'd' ? (int) $val : (float) $val;
				} elseif (is_null($val)) { # ?, ?d, ?f могут принимать значения null (в этом случае в шаблон вставляется NULL)
					return 'NULL';
				}
			}

			throw new Exception("Неверный тип значения"); # При ошибках в шаблонах или значениях выбрасывать исключения.
		};

		$k = 0;
		$query = preg_replace_callback('/(\s|\()\?([dfa\#])?(\s|\)|\}|$)/', function($ms) use (&$k, &$transformValue, &$args) { # спецификаторы
			return $ms[1].$transformValue($args[$k++], $ms[2], ($ms[2] == '#' ? "`" : "'")).$ms[3]; # $ms[1], $ms[3] - \s()}
		}, $query);

		# Если внутри условного блока есть хотя бы один параметр со специальным значением, то блок не попадает в сформированный запрос.
		return preg_replace_callback('/\{([^}]+)\}/', fn($ms) => str_contains($ms[1], $this->skip()) ? '' : $ms[1], $query);
	}

	public function skip()
	{
		return 0;
	}
}
