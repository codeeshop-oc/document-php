<?php

namespace DocumentPHP;

include_once getcwd() . '/system/engine/controller.php';

class FetchFiles {
	private $results = [];
	private $destination_path = '';

	public function getResults() {
		return $this->results;
	}

	public function setDestinationPath($path = '') {
		if ($path) {
			$this->destination_path = $path;
		} else {
			$this->destination_path = getcwd() . '/catalog/controller/api/';
		}
	}

	private function recursive_copy($src) {
		if (is_dir($src)) {
			$dir = opendir($src);

			while (($file = readdir($dir))) {
				if ((substr($file, -1) != '_') && ($file != '.') && ($file != '..')) {
					if (is_dir($src . '/' . $file)) {
						$this->recursive_copy($src . '/' . $file);
					} else {
						if (is_file($this->destination_path . $file)) {
							$this->start_fetching($this->destination_path . $file);
							// print_r($this->destination_path . $file);
						}
					}
				}
			}
			closedir($dir);

			return;
		} elseif (is_file($src)) {
		} else {
			die('<strong>Not found : </strong> ' . $src);
			// print_r($src);die;
		}
	}

	private function get_function($method, $class = null) {

		if (!empty($class)) {
			$func = new \ReflectionMethod($class, $method);
		} else {
			$func = new ReflectionFunction($method);
		}

		$f = $func->getFileName();
		$start_line = $func->getStartLine() - 1;
		$end_line = $func->getEndLine();
		$length = $end_line - $start_line;

		$source = file($f);
		$source = implode('', array_slice($source, 0, count($source)));
		$source = preg_split("/" . PHP_EOL . "/", $source);

		$body = '';
		for ($i = $start_line; $i < $end_line; $i++) {
			$body .= "{$source[$i]}\n";
		}

		return $body;
	}

	public function start_processing($path = '') {
		$this->setDestinationPath($path);
		$this->recursive_copy($this->destination_path);
	}

	private function start_fetching($file) {
		include_once $file;

		$start = strpos($file, 'controller') + strlen('controller');
		$end = strpos($file, '.php') - $start;
		$route = substr($file, $start, $end);
		$class_name = 'Controller' . preg_replace('/[^a-zA-Z0-9]/', '', $route);
		$myclass = new $class_name([]);
		$class_methods = get_class_methods($myclass);
		$class_vars = get_class_vars(get_class($myclass));

		$current_data = [];

		// echo "<pre>";
		foreach ($class_methods as $method_name) {
			$function_string = $this->get_function($method_name, $myclass);

			if ($this->isReturningJSONResponse($function_string)) {
				$method_types_vars = $this->findMethodTypesVars($function_string);
				$current_method_type = empty($method_types_vars['POST']) ? 'GET' : 'POST';

				$current_data[] = [
					'full_route' => $route . ($method_name == 'index' ? '' : ('/' . $method_name)),
					'current_method_type' => $current_method_type,
					'route' => $route,
					'method_name' => $method_name,
					'method_types_vars' => $method_types_vars,
					'file' => $file,
				];
			}
		}

		$this->results[$class_name] = $current_data;
		// print_r($this->results);
		// echo "</pre>";
	}

	private function findMethodTypesVars($function_string = '') {
		$method_types = [];
		$method_types['POST'] = $this->getParams($function_string, "/->post\[/i", ['->post[', '\''], '');
		$method_types['GET'] = $this->getParams($function_string, "/->get\[/i", ['->get[', '\''], '');
		return $method_types;
	}

	private function isReturningJSONResponse($function_string = '') {
		preg_match_all("/setOutput\(/i", $function_string, $matches);

		return !empty($matches[0]) ? true : false;
	}

	private function getParams($function_string, $pattern, $find, $replace = '') {
		preg_match_all($pattern, $function_string, $matches, PREG_OFFSET_CAPTURE);

		$var_names = [];
		foreach ($matches[0] as $key => $value) {
			$strpos = strpos(substr($function_string, $value[1], strlen($function_string)), ']');
			$var_name = str_replace($find, $replace, substr($function_string, $value[1], $strpos));

			$var_names[] = $var_name;
		}

		return array_unique($var_names);
	}
}

?>