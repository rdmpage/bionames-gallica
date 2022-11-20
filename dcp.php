<?php

// Fetch and cache one page from Gallica

require_once(dirname(__FILE__) . '/config.inc.php');

//--------------------------------------------------------------------------
/**
 * @brief Test whether HTTP code is valid
 *
 * HTTP codes 200 and 302 are OK.
 *
 * For JSTOR we also accept 403
 *
 * @param HTTP code
 *
 * @result True if HTTP code is valid
 */
function HttpCodeValid($http_code)
{
	if ( ($http_code == '200') || ($http_code == '302') || ($http_code == '403'))
	{
		return true;
	}
	else{
		return false;
	}
}


//--------------------------------------------------------------------------
/**
 * @brief GET a resource
 *
 * Make the HTTP GET call to retrieve the record pointed to by the URL. 
 *
 * @param url URL of resource
 *
 * @result Contents of resource
 */
function get($url, $userAgent = '')
{
	global $config;
	
	$data = '';
	
	$ch = curl_init(); 
	curl_setopt ($ch, CURLOPT_URL, $url); 
	curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1); 
	curl_setopt ($ch, CURLOPT_FOLLOWLOCATION,	1); 
	//curl_setopt ($ch, CURLOPT_HEADER,		  1);  
	curl_setopt ($ch, CURLOPT_COOKIEJAR, 'cookie.txt');
	
	if ($userAgent != '')
	{
		curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
	}	
	
	if ($config['proxy_name'] != '')
	{
		curl_setopt ($ch, CURLOPT_PROXY, $config['proxy_name'] . ':' . $config['proxy_port']);
	}
			
	$curl_result = curl_exec ($ch); 
	
	//echo $curl_result;
	
	if (curl_errno ($ch) != 0 )
	{
		echo "CURL error: ", curl_errno ($ch), " ", curl_error($ch);
	}
	else
	{
		$info = curl_getinfo($ch);
		
		 //$header = substr($curl_result, 0, $info['header_size']);
		//echo $header;
		
		//print_r($info);
		
		
		$http_code = $info['http_code'];
		
		//echo "<p><b>HTTP code=$http_code</b></p>";
		
		if (HttpCodeValid ($http_code))
		{
			$data = $curl_result;
		}
	}
	return $data;
}


//--------------------------------------------------------------------------
function get_page($id, $start, $page = 0)
{
	global $config;
	
	$page_object = new stdclass;
	
	$page_object->namespace 	= '12148';
	$page_object->id 			= $id;
	$page_object->start_page 	= $start;

	// Images are cached in folders with the ark id as the name
	$cache_namespace = $config['cache'] . "/" . $page_object->id;
	
	// Ensure cache subfolder exists for this item
	if (!file_exists($cache_namespace))
	{
		$oldumask = umask(0); 
		mkdir($cache_namespace, 0777);
		umask($oldumask);
		
		// Thumbnails are in a subdirectory
		$oldumask = umask(0); 
		mkdir($cache_namespace . '/thumbnails', 0777);
		umask($oldumask);

		// text files are in a subdirectory
		$oldumask = umask(0); 
		mkdir($cache_namespace . '/text', 0777);
		umask($oldumask);
	}
	
	// Grab image and cache
	$page_to_fetch = $page_object->start_page + $page;
	
	$base_url = 'https://gallica.bnf.fr/ark:/' . $page_object->namespace . '/' . $page_object->id . '/f' . $page_to_fetch;
	
	$image_url = $base_url . '.highres';
	$image_file = $cache_namespace . '/f' . $page_to_fetch . '.png';
	
	$image_exists = file_exists($image_file);
	
	// Force regenerating image
	$image_exists = false;
	
	if ($image_exists)
	{
		// check not zero size from past fail
		if (filesize($image_file) == 0)
		{
			$image_exists = false;
		}
	}
	
	if (!$image_exists)
	{
		//$img = get($image_url, 'Mozilla/5.0 (iPad; U; CPU OS 3_2_1 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Mobile/7B405');
		
		$img = get('https://ozymandias-demo.herokuapp.com/image_proxy.php?url=' . urlencode($image_url));
		
		/*
		echo $image_url . "\n";
		echo $image_file . "\n";
		echo "img=" . $img . "\n";
		*/		
		
		file_put_contents($image_file, $img);
		
		// thumbnail
		$image_url = $base_url . '.thumbnail';
		$image_file = $cache_namespace . '/thumbnails/f' . $page_to_fetch . '.png';
		
		//$img = get($image_url, 'Mozilla/5.0 (iPad; U; CPU OS 3_2_1 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Mobile/7B405');

		$img = get('https://ozymandias-demo.herokuapp.com/image_proxy.php?url=' . urlencode($image_url));

		file_put_contents($image_file, $img);

		
		// text
		$text_url = $base_url . '.textePage';
		$text_file = $cache_namespace . '/text/f' . $page_to_fetch . '.txt';
		
		$html = get($text_url);
		$html = get('https://ozymandias-demo.herokuapp.com/image_proxy.php?url=' . urlencode($text_url));
		
		
		$html = str_replace("\n", " ", $html);
		$html = str_replace("\r", " ", $html);
		
		if (preg_match('/<div id="contentPageModeText">(?<text>.*)<\/div>/Uu', $html, $m))
		{
			$text = $m['text'];
			$text = preg_replace('/\s*<BR>/', "\n", $text);
			
			$text = preg_replace('/<a(.*)><\/a>/Uu', '', $text);
			$text = preg_replace('/<span class=(.*)>/Uu', '', $text);
			$text = preg_replace('/<\/span>/Uu', '', $text);
			
			$text = preg_replace('/\s\s+/Uu', ' ', $text);
			$text = preg_replace('/^\s+/Uu', '', $text);
			$text = preg_replace('/\s+$/Uu', '', $text);
			
			file_put_contents($text_file, $text);		
		
		}
	}
	
	$page_object->image_url 	=  $config['web_cache'] . '/' . $page_object->id . '/f' . $page_to_fetch . '.png';	
	$page_object->thumbnail_url =  $config['web_cache'] . '/' . $page_object->id . '/thumbnails/f' . $page_to_fetch . '.png';
	$page_object->text 			= '';
	
	$text_file = $cache_namespace . '/text/f' . $page_to_fetch . '.txt';
	if (file_exists($text_file))
	{
		$page_object->text = file_get_contents($text_file);
	}
	
	//print_r($page_object);
	
	return $page_object;
}
	

$id = '';
$image = false;
$size = 'normal';

// test
if (0)
{
	$id = 'bpt6k54425183';
	$start = '245';
	$page = 1;
}

$callback = '';

if (isset($_GET['id']))
{
	$id = $_GET['id'];
}

if (isset($_GET['start']))
{
	$start = $_GET['start'];
}

if (isset($_GET['page']))
{
	$page = $_GET['page'];
	$page--;
}

if (isset($_GET['size']))
{
	$size = $_GET['size'];
	$image = true;
}

if (isset($_GET['callback']))
{
	$callback = $_GET['callback'];
}

$page = get_page($id, $start, $page);

//print_r($page);

//exit();

if ($image)
{	
	if ($size == 'small')
	{
		header("Location: " . $page->thumbnail_url . "\n\n");	
	}
	else
	{
		header("Location: " . $page->image_url . "\n\n");		
	}
}
else
{
	header('Content-type: text/plain');
	
	$page->text = str_replace("\\n", "\n", $page->text);
	
	if ($callback != '')
	{
		echo $callback . '(';
	}
	echo json_encode($page->text);
	if ($callback != '')
	{
		echo ')';
	}
	
}


?>
		
