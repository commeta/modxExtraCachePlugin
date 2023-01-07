<?php
/*
 * Modx revo plugin:
 *  Plugin for MODX Revo, increase server response time.
 *  Cache warming with Wget.
 *  Handle the incoming If-Modified-since header - to send a Not Modified 304 response.
 * 
 * 
 * Use events: 
 *   OnMODXInit
 *   OnWebPagePrerender
 *   OnSiteRefresh
 *   OnDocFormSave
 * 
 * https://github.com/commeta/modxExtraCachePlugin
 * https://webdevops.ru/blog/extra-cache-plugin-modx.html
 * 
 * Copyright 2022 commeta <dcs-spb@ya.ru>
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 * 
 * 
 */

$enable_cache_for_logged_user= true; // set false for Enable caching for logged manager user !!!
$ignore_url_get_parameters= true; // set false for Disable keep cached page on any get parameters
$expires= 3600; // Expires and max-age HTTP header, time after which the response is considered expired

$erase_session_keys= true; // set false for Disable erase session keys between requests
$session_keys= [ // Include session keys
	'AjaxForm',
	//'mSearch2',
	//'minishop2',
	//'mspc',
];



if(!function_exists('notModified')) { 
    function notModified($LastModified_unix, $expires){ // If modified since check and print 304 header
    	$LastModified = gmdate("D, d M Y H:i:s \G\M\T", $LastModified_unix);
    	$IfModifiedSince = false;
    	
    	if (isset($_ENV['HTTP_IF_MODIFIED_SINCE']))
    		$IfModifiedSince = strtotime(substr($_ENV['HTTP_IF_MODIFIED_SINCE'], 5));  
    	if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']))
    		$IfModifiedSince = strtotime(substr($_SERVER['HTTP_IF_MODIFIED_SINCE'], 5));
    	if ($IfModifiedSince && $IfModifiedSince >= $LastModified_unix) {
    		header($_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified');
    		exit;
    	}
    	
    	header('Cache-Control: public, max-age='.$expires.', must-revalidate');
    	header('Expires: '.gmdate('D, d M Y H:i:s', time() + $expires).' GMT');
    	header('Last-Modified: '.$LastModified);
    }
}

if(!function_exists('ifMofifiedSince')) { 
    function ifMofifiedSince($cache_key, $expires){
    	$cached_file= MODX_CORE_PATH."cache/extra_cache/".$cache_key.".cache.php";
    		
    	if(file_exists($cached_file) && $LastModified_unix= @filemtime($cached_file)){
    		notModified($LastModified_unix, $expires);
    	} else {
        	header('Cache-Control: public, max-age='.$expires.', must-revalidate');
        	header('Expires: '.gmdate('D, d M Y H:i:s', time() + $expires).' GMT');
        	header('Last-Modified: '.gmdate('D, d M Y H:i:s', time()).' GMT');
    	}
    }
}


switch ($modx->event->name) {
    case 'OnMODXInit':
        if(
            empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['REQUEST_METHOD']) == 'get' &&
            $modx->context->get('key') != 'mgr' &&
            (!$modx->user->hasSessionContext('mgr') && $enable_cache_for_logged_user)
        ){
            $options= [xPDO::OPT_CACHE_KEY=>'extra_cache'];

            if($ignore_url_get_parameters) $cache_key= md5(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH));
            else $cache_key= md5($_SERVER['REQUEST_URI']);

            ifMofifiedSince($cache_key, $expires);
            
            $cached= $modx->cacheManager->get($cache_key, $options);
            
            if(!empty($cached)){
                $output= unserialize($cached);
                foreach($session_keys as $sk) $_SESSION[$sk]= $output['session'][$sk];
                die($output['output']);
            }

            if($erase_session_keys && mb_stripos($_SERVER['HTTP_USER_AGENT'], 'wget') !== false) {
                foreach($session_keys as $sk) $_SESSION[$sk]= [];
            }
        }
    break;

    
    case 'OnWebPagePrerender':
        if(
            mb_stripos($_SERVER['HTTP_USER_AGENT'], 'wget') !== false &&
            empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['REQUEST_METHOD']) == 'get'
        ){
            $options= [xPDO::OPT_CACHE_KEY=>'extra_cache'];
            
            if($ignore_url_get_parameters) $cache_key= md5(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH));
            else $cache_key= md5($_SERVER['REQUEST_URI']);
            
            $session= [];
            foreach($session_keys as $sk) $session[$sk]= $_SESSION[$sk];

            $modx->cacheManager->set(
                $cache_key, 
                serialize(['output'=>$modx->resource->_output, 'session'=>$session]), 
                0, 
                $options
            );
        }
    break;


    case 'OnSiteRefresh':
        shell_exec('pkill -9 -f wget');
    
    	$options= [xPDO::OPT_CACHE_KEY=>'extra_cache'];
    	$modx->cacheManager->clean($options);

        shell_exec('wget--no-check-certificate  -r -nc -nd -l 7 --spider -q -b https://'.MODX_HTTP_HOST.'/');
    break;
    

    case 'OnDocFormSave':
        $url= str_ireplace(['http://', 'https://', MODX_HTTP_HOST], '', $modx->makeUrl($id));

        $cache_key= md5($url);
    	$cached_file= MODX_CORE_PATH."cache/extra_cache/".$cache_key.".cache.php";

    	if(file_exists($cached_file)){
    	    unlink($cached_file);
            shell_exec('wget --no-check-certificate -nc -nd --delete-after -q -b https://'.MODX_HTTP_HOST.$url);
    	}
    break;
}
