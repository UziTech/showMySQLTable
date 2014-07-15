<?php

class showTable {

	private static $operations = array(
		"=" => "equals",
		"!=" => "does not equal",
		">" => "is greater than",
		"<" => "is less than",
		">=" => "is greater than or equal to",
		"<=" => "is less than or equal to",
		"isnull" => "is null",
		"isnotnull" => "is not null",
		"istrue" => "is true",
		"isfalse" => "is false",
		"startswith" => "starts with",
		"endswith" => "ends with",
		"contains" => "contains",
		"between" => "is between",
	);
	private static $ands = array(
		"AND" => "AND",
		"OR" => "OR",
		"XOR" => "XOR",
	);
	private static $asc = array(
		"ASC" => "ascending order",
		"DESC" => "descending order",
	);
	private static $args = array(
		"select" => array(
			'filter' => FILTER_UNSAFE_RAW,
			'flags' => FILTER_REQUIRE_ARRAY,
		),
		"orderby" => array(
			'filter' => FILTER_UNSAFE_RAW,
			'flags' => FILTER_REQUIRE_ARRAY,
		),
		"where" => array(
			'filter' => FILTER_UNSAFE_RAW,
			'flags' => FILTER_REQUIRE_ARRAY,
		),
		"start" => FILTER_VALIDATE_INT,
		"limit" => FILTER_VALIDATE_INT,
	);
	//class variables
	private $table_name = null;
	private $columns = null;
	////string, date, time, datetime, boolean so far
	private $column_types = null;
	private $inputs = null;
	private $db = null;
	private $table = null;
	private $select = "*";
	private $orderby = "";
	private $where = "";
	private $whereParams = array();
	private $start = 0;
	private $limit = 50;
	private $total = 0;

	public function __construct() {
		$this->inputs = filter_input_array(INPUT_GET, self::$args);
		if (is_null($this->inputs)) {
			$this->inputs = array(
				"select" => null,
				"orderby" => null,
				"where" => null,
				"start" => null,
				"limit" => null,
			);
		}
		array_walk($this->inputs, "trim_value");
	}

	public function setDatabase($database) {
		$this->db = $database;
	}

	public function setColumnNames($columnNames) {
		$this->columns = $columnNames;
	}

	public function setColumnTypes($columnTypes) {
		$this->column_types = $columnTypes;
	}

	public function setTableName($tableName) {
		$this->table_name = $tableName;
	}

	private function getResults() {
		//// validate $this->db, $this->columns, $this->column_types, $this->table_name
		if (is_null($this->db)) {
			throw new Exception("Database is not set");
		}
		if (is_null($this->columns)) {
			throw new Exception("Column names is not set");
		}
		if (is_null($this->column_types)) {
			throw new Exception("Column types is not set");
		}
		if (is_null($this->table_name)) {
			throw new Exception("Table name is not set");
		}
		//sanitize $this->input["select"]
		//should be an array of column names
		if (!is_null($this->inputs["select"]) && $this->inputs["select"] !== false) {
			for ($i = 0; $i < count($this->inputs["select"]); $i++) {
				if (!isset($this->columns[$this->inputs["select"][$i]])) {
					goto SELECTERROR;
				}
			}
			$this->select = implode(", ", $this->inputs["select"]);
		} else {
			SELECTERROR:
			$this->inputs["select"] = null;
		}

		//sanitize $this->input["orderby"]
		//should be an array of strings in the format: 'columnName (ASC|DESC)'
		if (!is_null($this->inputs["orderby"]) && $this->inputs["orderby"] !== false) {
			for ($i = 0; $i < count($this->inputs["orderby"]); $i++) {
				$orderSplit = split(" ", $this->inputs["orderby"][$i]);
				if (count($orderSplit) !== 2 || !isset($this->columns[$orderSplit[0]]) || preg_match("/ASC|DESC/", $orderSplit[1]) === false) {
					goto ORDERBYERROR;
				}
			}
			$this->orderby = " ORDER BY " . implode(",", $this->inputs["orderby"]);
		} else {
			ORDERBYERROR:
			$this->inputs["orderby"] = null;
		}

		//sanitize $this->input["where"]
		//should be an array of strings in the format: 
		//array(
		//	[0] => "^(?$",
		//	[1] => "columnName",
		//	[2] => "(=|!=|LIKE|>|<|<=|>=)",
		//	[3] => "%?value%?",
		//	[4] => "^)?$",
		//	[5] => "^(AND|OR)?$",
		//	...
		//);
		if (!is_null($this->inputs["where"]) && $this->inputs["where"] !== false && count($this->inputs["where"]) % 6 === 0) {
			$whereString = "";
			for ($i = 0; $i < count($this->inputs["where"]); $i += 6) {
				$bpar = $this->inputs["where"][$i];
				$column = $this->inputs["where"][$i + 1];
				$operation = $this->inputs["where"][$i + 2];
				$value = $this->inputs["where"][$i + 3];
				$qmark = "?";
				$epar = $this->inputs["where"][$i + 4];
				$and = $this->inputs["where"][$i + 5];

				//begining parenthesis
				if ($bpar !== "(" && $bpar !== "") {
					goto WHEREERROR;
				}
				//column name
				if (!isset($this->columns[$column])) {
					goto WHEREERROR;
				}
				//operation
				if (!isset(self::$operations[$operation])) {
					goto WHEREERROR;
				}
				//ending parenthesis
				if ($epar !== ")" && $epar !== "") {
					goto WHEREERROR;
				}
				//AND|OR
				if (($and !== "" || $i !== count($this->inputs["where"]) - 6) && (!isset(self::$ands[$and]) || $i === count($this->inputs["where"]) - 6)) {
					goto WHEREERROR;
				}
				//check for like, isnull, isnotnull, between
				//check for boolean value
				if ($this->column_types[$column] === "boolean") {
					switch ($operation) {
						case "istrue":
							$operation = "= 1";
							$qmark = "";
							break;
						case "isfalse":
							$operation = "= 0";
							$qmark = "";
							break;
					}
				} else {
					switch ($operation) {
						case "isnull":
							$operation = "IS NULL";
							$qmark = "";
							break;
						case "isnotnull":
							$operation = "IS NOT NULL";
							$qmark = "";
							break;
						case "istrue":
							$operation = "=";
							$this->whereParams[] = 1;
							break;
						case "isfalse":
							$operation = "=";
							$this->whereParams[] = 0;
							break;
						case "startswith":
							$operation = "LIKE";
							$this->whereParams[] = str_replace("%", "\\%", $value) . "%";
							break;
						case "endswith":
							$operation = "LIKE";
							$this->whereParams[] = "%" . str_replace("%", "\\%", $value);
							break;
						case "contains":
							$operation = "LIKE";
							$this->whereParams[] = "%" . str_replace("%", "\\%", $value) . "%";
							break;
						case "between":
							$operation = "BETWEEN";
							$valueSplit = split(" AND ", $value);
							if (count($valueSplit) !== 2) {
								goto WHEREERROR;
							}
							$this->whereParams[] = $valueSplit[0];
							$this->whereParams[] = $valueSplit[1];
							$qmark = "? AND ?";
							break;
						default:
							$this->whereParams[] = $value;
							break;
					}
				}
				$whereString .= " {$bpar}{$column} {$operation} {$qmark}{$epar} {$and}";
			}
			$this->where = " WHERE{$whereString}";
		} else {
			WHEREERROR:
			$this->inputs["where"] = null;
		}

		//sanitize $this->input["start"]
		if (!is_null($this->inputs["start"]) && $this->inputs["start"] !== false && $this->inputs["start"] >= 0) {
			$this->start = $this->inputs["start"];
		}

		//sanitize $this->input["limit"]
		if (!is_null($this->inputs["limit"]) && $this->inputs["limit"] !== false && $this->inputs["limit"] >= 1) {
			$this->limit = $this->inputs["limit"];
		}

		//make sure $this->start is at the beginning of a page
		if ($this->start % $this->limit > 0) {
			$this->start = ((int) ($this->start / $this->limit)) * $this->limit;
		}
		//show results
		$this->table = $this->db->execQuery("SELECT SQL_CALC_FOUND_ROWS {$this->select} FROM {$this->table_name}{$this->where}{$this->orderby} LIMIT {$this->start}, {$this->limit}", $this->whereParams);
		if ($this->table !== false) {
			$this->total = $this->db->lastRowCount();
		}

		if ($this->start >= $this->total) {
			//set $this->start to beginning of last page
			$this->start = ((int) (($this->total - 1) / $this->limit)) * $this->limit;
			if ($this->total > 0) {
				$this->table = $this->db->execQuery("SELECT SQL_CALC_FOUND_ROWS {$this->select} FROM {$this->table_name}{$this->where}{$this->orderby} LIMIT {$this->start}, {$this->limit}", $this->whereParams);
				$this->total = $this->db->lastRowCount();
			}
		}
	}

	public function isFiltered() {
		return !is_null($this->inputs["select"]) || !is_null($this->inputs["orderby"]) || !is_null($this->inputs["where"]);
	}

	//get html for options from array with optionally choosing which ones are selected
	private function printOptions($options, $selected = null, $attributes = null) {
		$html = "";
		foreach ($options as $value => $label) {
			$select = "";
			if ((is_array($selected) && in_array($value, $selected, true)) || $value === $selected) {
				$select = " selected";
			}
			$attrs = "";
			if (is_array($attributes)) {
				foreach ($attributes as $attribute => $attrValue) {
					if (is_array($attrValue)) {
						if(isset($attrValue[$value])){
							$attrs .= " {$attribute}='{$attrValue[$value]}'";
						} else {
							//$attrs .= " {$attribute}=''";////should the attribute be blank or just not include it?
						}
					} else {
						$attrs .= " {$attribute}='{$attrValue}'";
					}
				}
			}
			$html .= "<option value='{$value}'{$attrs}{$select}>{$label}</option>";
		}
		return $html;
	}

//get html for page controls
	private function printPageControls() {
		if ($this->total > 0) {
			$page = (int) ($this->start / $this->limit) + 1;
			$totalPages = (int) (($this->total - 1) / $this->limit) + 1;
			$lastRow = ($this->start + $this->limit < $this->total ? $this->start + $this->limit : $this->total);
			$previous = 0;
			if ($this->start <= 0 || $this->total == 0) {
				$previous = null;
			} else if ($this->start - $this->limit > 0) {
				$previous = $this->start - $this->limit;
			}
			$next = ($lastRow >= $this->total ? null : $lastRow);
			$first = 0;
			$last = ($this->total - 1) - (($this->total - 1) % $this->limit);
			$html = "";
			//showing page ? of ?
			$html .= "<span class='count'>Showing page {$page} of {$totalPages}. {$this->total} survey" . ($this->total === 1 ? "" : "s") . ".</span>";
			//first button
			$html .= "<button class='first' type='button' title='First Page' data-start='{$first}'" . (is_null($previous) ? " disabled" : "") . ">&lt;&lt;</button>";
			//previous button
			$html .= "<button class='previous' type='button' title='Previous Page' data-start='{$previous}'" . (is_null($previous) ? " disabled" : "") . ">&lt;</button>";
			//next button
			$html .= "<button class='next' type='button' title='Next Page' data-start='{$next}'" . (is_null($next) ? " disabled" : "") . ">&gt;</button>";
			//last button
			$html .= "<button class='last' type='button' title='Last Page' data-start='{$last}'" . (is_null($next) ? " disabled" : "") . ">&gt;&gt;</button>";

			return $html;
		}
	}

//get html for select options
	private function printShowColums() {
		$selectedColumns = (is_null($this->inputs["select"]) ? array_keys($this->columns) : $this->inputs["select"]);
		$html = "<select size='" . count($this->columns) . "' multiple class='column'>" .
			$this->printOptions($this->columns, $selectedColumns) .
			"</select>";
		return $html;
	}

//get html for order by options
	private function printOrderBy() {
		$html = "";
		if (!is_null($this->inputs["orderby"])) {
			foreach ($this->inputs["orderby"] as $orderby) {
				$orderSplit = split(" ", $orderby);
				$html .= "<div class='orderby'>" .
					"<select class='column'>" .
					$this->printOptions($this->columns, $orderSplit[0]) .
					"</select>" .
					"<select class='asc'>" .
					$this->printOptions(self::$asc, $orderSplit[1]) .
					"</select>" .
					"<span><a title='Remove filter' class='close no-select'>X</a></span>" .
					"</div>";
			}
		}
		$html .= "<div class='orderby new'>" .
			"<select class='column  defaultnull'>" .
			$this->printOptions($this->columns) .
			"</select>" .
			"<select class='asc'>" .
			$this->printOptions(self::$asc) .
			"</select>" .
			"<span><a title='Remove filter' class='close no-select'>X</a></span>" .
			"</div>";
		return $html;
	}

//get html for where options
	private function printConditions() {
		$html = "";
		if (!is_null($this->inputs["where"])) {
			for ($i = 0; $i < count($this->inputs["where"]); $i += 6) {

				$bpar = $this->inputs["where"][$i] === "(";
				$column = $this->inputs["where"][$i + 1];
				$operation = $this->inputs["where"][$i + 2];
				$value = $this->inputs["where"][$i + 3];
				$epar = $this->inputs["where"][$i + 4] === ")";
				$and = $this->inputs["where"][$i + 5];
				$lastWhere = ($i === count($this->inputs["where"]) - 6);
				$dataVal = ($operation === "between" ? split(" AND ", $value, 2)[0] : $value);

				$html .= "<div class='where" . ($lastWhere ? " new" : "") . "'>" .
					"<button title='Toggle parenthesis' class='bpar" . ($bpar ? " checked" : "") . "'>(</button>" .
					"<select class='column'>" .
					$this->printOptions($this->columns, $column, array("data-type" => $this->column_types)) .
					"</select>" .
					"<select class='operation' data-val='{$dataVal}'>" .
					$this->printOptions(self::$operations, $operation) .
					"</select>" .
					"<span class='value'>";
				switch ($operation) {
					case "isnull":
					case "isnotnull":
					case "istrue":
					case "isfalse":
						break;
					case "between":
						$valueSplit = split(" AND ", $value, 2);
						$html .= "<input type='text' class='val1' value='" . $valueSplit[0] . "' /> AND <input type='text' class='val2' value='" . $valueSplit[1] . "' />";
						break;
					default:
						$html .= "<input type='text' class='val' value='" . $value . "' />";
						break;
				}
				$html .= "</span>" .
					"<button title='Toggle parenthesis' class='epar" . ($epar ? " checked" : "") . "'>)</button>" .
					"<select class='and" . ($and === "" ? " defaultnull" : "") . "'>" .
					$this->printOptions(self::$ands, $and) .
					"</select>" .
					"<span><a title='Remove filter' class='close no-select'>X</a></span>" .
					"</div>";
			}
		} else {
			$html .= "<div class='where new'>" .
				"<button title='Toggle parenthesis' class='bpar'>(</button>" .
				"<select class='column defaultnull'>" .
				$this->printOptions($this->columns, null, array("data-type" => $this->column_types)) .
				"</select>" .
				"<select class='operation  defaultnull' data-val=''>" .
				$this->printOptions(self::$operations) .
				"</select>" .
				"<span class='value'>" .
				"<input type='text' class='val' />" .
				"</span>" .
				"<button title='Toggle parenthesis' class='epar'>)</button>" .
				"<select class='and  defaultnull'>" .
				$this->printOptions(self::$ands) .
				"</select>" .
				"<span><a title='Remove filter' class='close no-select'>X</a></span>" .
				"</div>";
		}
		return $html;
	}

//print html for table
	private function printTable() {
		if ($this->total > 0) {
			$rownum = $this->start + 1;
			$columnNames = null;

			if (is_null($this->inputs["select"])) {
				$columnNames = $this->columns;
			} else {
				$columnNames = array();
				//I only want the column names that are selected and in that order.
				foreach ($this->inputs["select"] as $colomnName) {
					$columnNames[$colomnName] = $this->columns[$colomnName];
				}
			}

			$html = "<table>" .
				"<thead>" .
				"<tr>";
			foreach ($columnNames as $columnName => $columnReadableName) {
				$html .= "<th class='{$columnName}'>{$columnReadableName}</th>";
			}
			$html .= "</tr>" .
				"</thead>" .
				"<tbody>";
			$odd = true;
			foreach ($this->table as $row) {
				$html .= "<tr class='" . ($odd ? "odd" : "even") . "'>";
				foreach (array_keys($columnNames) as $columnName) {
					switch ($this->column_types[$columnName]) {////add more types?
						case "boolean":
							$html .= "<td class='{$columnName}'>" . ($row[$columnName] ? "&#10004;" : "") . "</td>"; //&#10004; is a check mark
							break;
						case "date":
							$dateFormat = "";
							if (!is_null($row[$columnName])) {
								$date = new DateTime($row[$columnName]);
								$dateFormat = $date->format("m/d/y");
							}
							$html .= "<td class='{$columnName}'>{$dateFormat}</td>";
							break;
						case "time":
							$dateFormat = "";
							if (!is_null($row[$columnName])) {
								$date = new DateTime($row[$columnName]);
								$dateFormat = $date->format("g:i A");
							}
							$html .= "<td class='{$columnName}'>{$dateFormat}</td>";
							break;
						case "datetime":
							$dateFormat = "";
							if (!is_null($row[$columnName])) {
								$date = new DateTime($row[$columnName]);
								$dateFormat = $date->format("m/d/y g:i A");
							}
							$html .= "<td class='{$columnName}'>{$dateFormat}</td>";
							break;
						case "string":
						default:
							$html .= "<td class='{$columnName}'>{$row[$columnName]}</td>";
							break;
					}
				}
				$html .= "</tr>";
				$rownum++;
				$odd = !$odd;
			}
			$html .= "</tbody>" .
				"</table>";
			return $html;
		} else {
			return "<div id='nosurveys'>No surveys matched your criteria.</div>";
		}
	}

	private function printCSS() {
		////
	}

	private function printJS() {
		////
	}

	public function printHTML() {
		$this->getResults();
		$html = "<fieldset id='filtersField'>" .
			"<legend><span class='expand no-select' data-down='<?= isFiltered() ?>' data-animating='false' >&#9650;</span>Filters</legend>" .
			"<div id='filters'>" .
			"<fieldset id='selectField'>" .
			"<legend>Show Columns</legend>" .
			"<div id='select'>" .
			$this->printShowColums() .
			"</div>" .
			"</fieldset>" .
			"<fieldset id='orderbyField'>" .
			"<legend>Order By</legend>" .
			"<div id='orderby'>" .
			$this->printOrderBy() .
			"</div>" .
			"</fieldset>" .
			"<fieldset id='whereField'>" .
			"<legend>Conditions</legend>" .
			"<div id='where'>" .
			$this->printConditions() .
			"</div>" .
			"</fieldset>" .
			"<div id='buttons'>" .
			"<button type='button' id='submit'>Submit</button>" .
			"<button type='button' id='reset'>reset</button>" .
			"</div>" .
			"</div>" .
			"</fieldset>" .
			"<div id='pagecontrols'>" .
			$this->printPageControls() .
			"</div>" .
			"<div id='table'>" .
			$this->printTable() .
			"</div>";

		return $html;
	}

}