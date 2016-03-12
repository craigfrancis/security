<?php

//--------------------------------------------------
// Supporting functions

	function head($text) {
		return str_replace(array("\r", "\n", "\0"), '', $text);
	}

	function path_to_array($path) {
		$path = str_replace('\\', '/', $path); // Windows
		$output = array();
		foreach (explode('/', $path) as $name) {
			if ($name == '..') { // Move up a folder
				array_pop($output);
			} else if ($name != '' && $name != '.') { // Ignore empty and current folder
				$output[] = $name;
			}
		}
		return $output;
	}

	function host_cookie_name_get($host) {
		return 'host-' . str_replace('=', '', base64_encode($host)) . '-';
	}

	function host_url_create($src_url, $default_host = NULL) {

		$src_url = trim($src_url);

		if ($src_url == '') {
			return NULL;
		}

		$src_parts = parse_url($src_url);

		if ($src_parts) {

			//--------------------------------------------------
			// New host

				if (isset($src_parts['host'])) {

					if (isset($src_parts['scheme']) && $src_parts['scheme'] == 'https') {
						$new_host = 'https://';
					} else {
						$new_host = 'http://';
					}

					if (isset($src_parts['user'])) {
						$new_host .= $src_parts['user'];
						if (isset($src_parts['pass'])) {
							$new_host .= ':' . $src_parts['pass'];
						}
						$new_host .= '@';
					}

					$new_host .= $src_parts['host'];

					if (isset($src_parts['port'])) {
						$new_host .= ':' . $src_parts['port'];
					}

				} else if ($default_host) {

					$new_host = $default_host;

				} else {

					return NULL;

				}

			//--------------------------------------------------
			// New query

				if (isset($src_parts['query'])) {
					parse_str($src_parts['query'], $new_query);
				} else {
					$new_query = array();
				}

				$new_query = array_reverse($new_query);
				$new_query['host'] = $new_host;
				$new_query = array_reverse($new_query);

			//--------------------------------------------------
			// New url

				if (isset($_SERVER['HTTP_HOST'])) {
					$new_url = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
				} else {
					$new_url = '';
				}

				if (isset($src_parts['path']) && $src_parts['path'] != '') {
					$new_url .= $src_parts['path'];
				} else {
					$new_url .= '/';
				}

				$new_url .= '?'  . http_build_query($new_query);

			//--------------------------------------------------
			// Value set

				return $new_url;

		} else {

			//--------------------------------------------------
			// Failed

				return NULL;

		}

	}

	function host_url_css_update($css, $request_base_path, $host_full) {

		preg_match_all('/url\("?\'?(.*?)"?\'?\)/', $css, $matches, PREG_SET_ORDER);
		foreach ($matches as $cMatch) {

			if (substr($cMatch[1], 0, 1) == '/') {
				$new_url = $cMatch[1];
			} else {
				$new_url = '/' . implode('/' , path_to_array($request_base_path . $cMatch[1]));
			}

			$new_url = host_url_create($new_url, $host_full);

			if ($new_url) {
				$css = str_replace($cMatch[0], 'url("' . $new_url . '")', $css);
			}

		}

		return $css;

	}

//--------------------------------------------------
// URL request

	$url = '';

	if (count($_GET) == 1 && isset($_GET['url'])) {

		$url = trim($_GET['url']);

		if ($url != '') {

			$new_url = host_url_create($url);

			if ($new_url != '') {
				header('Location: ' . head($new_url), true, 302);
				exit();
			}

		}

	}

//--------------------------------------------------
// Host request

	if (isset($_GET['host'])) {

		$host_full = trim($_GET['host']);

		if ($host_full != '') {

			$host_parts = parse_url($host_full);

			if (!isset($host_parts['host'])) {

				//--------------------------------------------------
				// Error

					exit('Invalid host "' . $host_full . '"');

			} else {

				//--------------------------------------------------
				// Request host

					$https = (isset($host_parts['scheme']) && strtolower($host_parts['scheme']) == 'https');

					if (!isset($host_parts['scheme']) && isset($host_parts['port']) && $host_parts['port'] == 443) {
						$https = true;
					}

					if (!isset($host_parts['port']) || $host_parts['port'] == 0) {
						if ($https) {
							$port = 443;
						} else {
							$port = 80;
						}
					} else {
						$port = $host_parts['port'];
					}

					$request_host = ($https ? 'tls://' : '') . $host_parts['host'];

				//--------------------------------------------------
				// Request method

					$request_method = (isset($_SERVER['REQUEST_METHOD'])  ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET');

					if (!in_array($request_method, array('GET', 'POST'))) {
						$request_method = 'GET';
					}

				//--------------------------------------------------
				// Request path

					$path_parts = parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/');

					if (isset($path_parts['path']) && $path_parts['path'] != '') {
						$request_path = $path_parts['path'];
					} else {
						$request_path = '/';
					}

					$request_base_path = $request_path;
					if (substr($request_base_path, -1) != '/') {
						$request_base_path = dirname($request_base_path) . '/';
					}

					if (isset($path_parts['query'])) {

						parse_str($path_parts['query'], $request_query);

						unset($request_query['host']);

						if ($request_query) {
							$request_path .= '?' . http_build_query($request_query);
						}

					}

				//--------------------------------------------------
				// Request headers

					//--------------------------------------------------
					// Header main

						$request_headers = array();
						$request_headers[] = head($request_method) . ' ' . head($request_path) . ' HTTP/1.1';
						$request_headers[] = 'Host: ' . head($host_parts['host']);
						$request_headers[] = 'Connection: Close';

					//--------------------------------------------------
					// User authorisation

						$user = (isset($host_parts['user']) ? $host_parts['user'] : '');
						$pass = (isset($host_parts['pass']) ? $host_parts['pass'] : '');

						if ($user != '' && $pass != '') {
							$request_headers[] = 'Authorization: Basic ' . base64_encode($user . ':' . $pass);
						}

					//--------------------------------------------------
					// Cookies

						$cookies = array();

						$host_prefix = host_cookie_name_get($host_parts['host']);
						$host_prefix_len = strlen($host_prefix);

						if (isset($_COOKIE)) {
							foreach ($_COOKIE as $name => $value) {
								if (substr($name, 0, $host_prefix_len) == $host_prefix) {
									$cookies[] = urlencode(substr($name, $host_prefix_len)) . '=' . urlencode($value);
								}
							}
						}

						if (count($cookies) > 0) {
							$request_headers[] = 'Cookie: ' . head(implode('; ', $cookies));
						}

					//--------------------------------------------------
					// Extra headers

						$header_blacklist = array(
								'host',
								'connection',
								'authorization',
								'cookie',
								'upgrade-insecure-requests',
								'accept-encoding',
								'if-modified-since', // Being lazy, kill the cache
								'if-none-match',
							);

						$source_referrer = NULL;

						foreach (apache_request_headers() as $header => $value) {

							$header_lower = strtolower($header);

							if ($header_lower == 'referer') {

								$source_referrer = $value;

							} else if (!in_array($header_lower, $header_blacklist)) {

								$request_headers[] = $header . ': ' . $value;

							}

						}

					//--------------------------------------------------
					// Referrer

						if ($source_referrer) {

							$referrer_parts = parse_url($source_referrer);

							if (isset($referrer_parts['query'])) {

								parse_str($referrer_parts['query'], $referrer_query);

								if ($referrer_query['host']) {

									//--------------------------------------------------
									// Extract host

										$referrer_host = $referrer_query['host'];

										unset($referrer_query['host']);

									//--------------------------------------------------
									// Build new URL

										if (isset($referrer_parts['scheme']) && $referrer_parts['scheme'] == 'https') {
											$new_host = 'https://';
										} else {
											$new_host = 'http://';
										}

											// No user/pass

										$new_host .= $referrer_parts['host'];

										if (isset($referrer_parts['port'])) {
											$new_host .= ':' . $referrer_parts['port'];
										}

										if (count($referrer_query) > 0) {
											$new_host .= '?' . http_build_query($referrer_query);
										}

									//--------------------------------------------------
									// Send

										$request_headers[] = 'Referer: ' . head($new_host);

								}

							}

						}

				//--------------------------------------------------
				// Request data

					$request_data = file_get_contents('php://input');

				//--------------------------------------------------
				// Request body

					$request_body = implode("\r\n", $request_headers) . "\r\n\r\n";

					if ($request_method != 'GET' && $request_data != '') {
						$request_body .= $request_data;
					}

				//--------------------------------------------------
				// Connect

					$connection = fsockopen($request_host, $port, $errno, $errstr, 5);

					if ($connection) {

						$result = @fwrite($connection, $request_body); // Send request

						if ($result != strlen($request_body)) { // Connection lost will result in some bytes being written
							exit('Connection lost to "' . $request_host . '"');
						}

					} else {

						exit('Failed connection to "' . $request_host . '"');

					}

				//--------------------------------------------------
				// Get response

					$error_reporting = error_reporting(0); // IIS forgetting close_notify indicator - https://php.net/file

					$length = NULL;
					$response_headers = '';
					$response_data = NULL;

					while (($line = fgets($connection, 255))) {
						if ($response_data === NULL) {

							$response_headers .= $line;

							if (strncmp($line, 'Content-Length:', 15) === 0) {
								$length = intval(substr($line, 15));
							} else if (trim($line) == '') {
								$response_data = '';
							}

						} else {

							$response_data .= $line;

							if ($length !== NULL && strlen($response_data) >= $length) {
								break;
							}

						}
					}

					error_reporting($error_reporting);

				//--------------------------------------------------
				// Return headers

					$header_blacklist = array(
							'content-length',
							'strict-transport-security',
							'content-security-policy',
							'public-key-pins',
							'x-content-type-options',
							'x-xss-protection',
							'x-frame-options',
						);

					$transfer_chunked = false;
					$mime_type = NULL;

					foreach (explode("\n", $response_headers) as $header) {
						$header = array_map('trim', explode(':', trim($header), 2));
						if (count($header) == 2) {

							list($header_name, $header_value) = $header;

							$header_name_lower = strtolower($header_name);

							if (in_array($header_name_lower, $header_blacklist)) {
								continue; // Don't want these
							}

							if ($header_name_lower == 'transfer-encoding' && strtolower(trim($header_value)) == 'chunked') {
								$transfer_chunked = true;
								continue;
							}

							if ($header_name_lower == 'location') {
								$header_value = host_url_create($header_value, $host_full);
							}

							if ($header_name_lower == 'set-cookie') {
								$value = array_map('trim', explode(';', $header_value));
								if (count($value) > 0) {
									$value[0] = host_cookie_name_get($host_parts['host']) . $value[0];
								} else {
									continue;
								}
								if (($pos = array_search('secure', array_map('strtolower', $value))) !== false) {
									unset($value[$pos]);
								}
								$header_value = implode('; ', $value);
							}

							if ($header_name_lower == 'content-type') {
								$value = explode(';', $header_value, 2);
								if (count($value) > 0) {
									$mime_type = strtolower(trim(array_shift($value)));
								}
							}

							header(head($header_name) . ': ' . head($header_value));

						}
					}

				//--------------------------------------------------
				// Remove chunks

					if ($transfer_chunked) {

						$output = '';
						$chunked_str = $response_data;
						$chunked_length = strlen($chunked_str);
						$chunked_pos = 0;

						while ($chunked_pos < $chunked_length) { // See comment at https://php.net/manual/en/function.http-chunked-decode.php

							$pos_nl = strpos($chunked_str, "\n", ($chunked_pos + 1));

							if ($pos_nl === false) { // Bad response from remote server
								break;
							}

							$hex_length = substr($chunked_str, $chunked_pos, ($pos_nl - $chunked_pos));
							$chunked_pos = ($pos_nl + 1);

							$chunk_length = hexdec(rtrim($hex_length, "\r\n"));
							$output .= substr($chunked_str, $chunked_pos, $chunk_length);
							$chunked_pos = ($chunked_pos + $chunk_length);
								// $chunked_pos = (strpos($chunked_str, "\n", $chunked_pos + $chunk_length) + 1);

						}

						$response_data = $output;

					}

				//--------------------------------------------------
				// Update URLs

					if (in_array($mime_type, array('text/html', 'application/xhtml+xml'))) {

						//--------------------------------------------------
						// Parse

							libxml_use_internal_errors(true);

							$response_dom = new DomDocument();
							$response_dom->loadHTML($response_data);

						//--------------------------------------------------
						// Update URLs

							$tags = array( // http://stackoverflow.com/questions/2725156/complete-list-of-html-tag-attributes-which-have-a-url-value
									'a'          => array('href'),
									'applet'     => array('codebase', 'archive'),
									'area'       => array('href'),
									'audio'      => array('src'),
									'base'       => array('href'),
									'blockquote' => array('cite'),
									'body'       => array('background'),
									'button'     => array('formaction'),
									'command'    => array('icon'),
									'del'        => array('cite'),
									'embed'      => array('src'),
									'form'       => array('action'),
									'frame'      => array('longdesc', 'src'),
									'head'       => array('profile'),
									'html'       => array('manifest'),
									'iframe'     => array('longdesc', 'src'),
									'img'        => array('longdesc', 'src', 'usemap'),
									'input'      => array('src', 'formaction', 'usemap'),
									'ins'        => array('cite'),
									'link'       => array('href'),
									'object'     => array('data', 'classid', 'codebase', 'usemap', 'archive'),
									'q'          => array('cite'),
									'script'     => array('src'),
									'source'     => array('src'),
									'video'      => array('poster', 'src'),
								);

							foreach ($tags as $tag => $attributes) {

								$nodes = $response_dom->getElementsByTagName($tag);

								for ($k = ($nodes->length - 1); $k >= 0; $k--) {

									$node = $nodes->item($k);

									foreach ($attributes as $attribute) {

										$value_old = $node->getAttribute($attribute);

										$value_new = host_url_create($value_old, $host_full);

										// echo $tag . '[' . $attribute . '] = "' . $value_old . '" -> "' . $value_new . '"' . "\n";

										if ($value_new) {
											$node->setAttribute($attribute, $value_new);
										}

									}

								}

							}

						//--------------------------------------------------
						// Convert <tag /> to <tag></tag>

							foreach (array('a', 'video', 'iframe', 'script') as $tag) {

								$nodes = $response_dom->getElementsByTagName($tag);

								for ($k = ($nodes->length - 1); $k >= 0; $k--) {

									$node = $nodes->item($k);

									$node->appendChild($response_dom->createTextNode(''));

								}

							}

						//--------------------------------------------------
						// Inline script tags

							$nodes = $response_dom->getElementsByTagName('script');

							for ($k = ($nodes->length - 1); $k >= 0; $k--) {

								//--------------------------------------------------
								// Value

									$node = $nodes->item($k);

									$new_script = $node->nodeValue;

									$script_changed = false;

								//--------------------------------------------------
								// NewRelic

									// if (strpos($new_script, 'NREUM') !== false) {
									// 	$new_script = '';
									// }

								//--------------------------------------------------
								// Fix images in the base64 encoded form

									if (($pos_start = strpos($new_script, 'var p = \'')) !== false) {
										$pos_start += 9;
										if (($pos_end = strpos($new_script, '\'', ($pos_start + 1))) !== false) {

											$base64_form = substr($new_script, $pos_start, ($pos_end - $pos_start));
											if ($base64_form !== false) {

												$base64_form = base64_decode($base64_form);

												preg_match_all('/"(\/assets\/templates\/.*?)"/', $base64_form, $matches, PREG_SET_ORDER);
												foreach ($matches as $cMatch) {
													$new_url = host_url_create($cMatch[1], $host_full);
													if ($new_url) {
														$base64_form = str_replace($cMatch[0], '"' . $new_url . '"', $base64_form);
													}
												}

												$base64_form = base64_encode($base64_form);

												$new_script = substr($new_script, 0, $pos_start) . $base64_form . substr($new_script, $pos_end);

												$script_changed = true;

											}

										}
									}

								//--------------------------------------------------
								// Apply

									if ($script_changed) {
										$node->nodeValue = $new_script;
									}

							}

						//--------------------------------------------------
						// Inline style tags

							// ... Not tested
							//
							// $nodes = $response_dom->getElementsByTagName('style');
							//
							// for ($k = ($nodes->length - 1); $k >= 0; $k--) {
							//
							// 	$node = $nodes->item($k);
							//
							// 	$new_css = host_url_css_update($node->nodeValue, $request_base_path, $host_full);
							//
							// 	$node->nodeValue = $new_css;
							//
							// }

						//--------------------------------------------------
						// Back to a string

							$response_data = $response_dom->saveHTML();

					} else if (in_array($mime_type, array('text/javascript'))) {

						//--------------------------------------------------
						// JavaScript

							preg_match_all('/"(https?:\/\/.*?)"/', $response_data, $matches, PREG_SET_ORDER);
							foreach ($matches as $cMatch) {

								$new_url = host_url_create($cMatch[1], $host_full);

								if ($new_url) {
									$response_data = str_replace($cMatch[0], '"' . $new_url . '"', $response_data);
								}

							}

							preg_match_all('/"(\/(js|app|assets)\/.*?)"/', $response_data, $matches, PREG_SET_ORDER);
							foreach ($matches as $cMatch) {

								if (substr($cMatch[1], 0, 1) == '/') {
									$new_url = $cMatch[1];
								} else {
									$new_url = '/' . implode('/' , path_to_array($request_base_path . $cMatch[1]));
								}

								$new_url = host_url_create($new_url, $host_full);

								if ($new_url) {
									$response_data = str_replace($cMatch[0], '"' . $new_url . '"', $response_data);
								}

							}

							$response_data = preg_replace('/Worldpay\.api_path\+"tokens\/?"/', '"' . host_url_create('https://api.worldpay.com/v1/tokens/') . '&"', $response_data);

					} else if (in_array($mime_type, array('text/css'))) {

						//--------------------------------------------------
						// CSS

							$response_data = host_url_css_update($response_data, $request_base_path, $host_full);

					}

				//--------------------------------------------------
				// Return

					exit($response_data);

			}

		}

	}

?>
<!DOCTYPE html>
<html lang="en-GB" xml:lang="en-GB" xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta charset="UTF-8" />
	<title>Proxy</title>
	<style>
		form {
			text-align: center;
		}
		form fieldset {
			padding: 1em 0;
		}
		form input#url {
			width: 30em;
		}
	</style>
</head>
<body>

	<form action="/" method="get" accept-charset="UTF-8">
		<fieldset>
			<label for="url">URL</label>
			<input name="url" id="url" required="required" type="text" maxlength="300" value="<?= htmlentities($url ? $url : 'https://www.example.com') ?>" />
			<input type="submit" value="Go" />
		</fieldset>
	</form>

</body>
</html>