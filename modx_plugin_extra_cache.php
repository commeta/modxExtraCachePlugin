<?php
/*
 * Modx revo plugin:
 *  Plugin for MODX Revo, increase server response time
 *  Cache warming with Wget
 *  
 * 
 * 
 * Use events: 
 *   OnMODXInit
 *   OnWebPagePrerender
 *   OnSiteRefresh
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

if(!function_exists('notModified')) { 
    function notModified($LastModified_unix){ // If modified since check and print 304 header
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
    	
    	header('Cache-Control: public, max-age=3600, must-revalidate');
    	header('Expires: '.gmdate('D, d M Y H:i:s', time() + 3600).' GMT');
    	header('Last-Modified: '.$LastModified);
    }
}

if(!function_exists('ifMofifiedSince')) { 
    function ifMofifiedSince($cache_key){
    	$cached_file= MODX_CORE_PATH."cache/front_cache/".$cache_key.".cache.php";
    		
    	if(file_exists($cached_file)){
    	  clearstatcache();
    		$LastModified_unix= filemtime($cached_file);
    		notModified($LastModified_unix);
    	} else {
        	header('Cache-Control: public, max-age=3600, must-revalidate');
        	header('Expires: '.gmdate('D, d M Y H:i:s', time() + 3600).' GMT');
        	header('Last-Modified: '.gmdate('D, d M Y H:i:s', time()).' GMT');
    	}
    }
}


switch ($modx->event->name) {
    case 'OnMODXInit':
        if(
            mb_stripos($_SERVER['REQUEST_URI'], '?') === false &&
            empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['REQUEST_METHOD']) == 'get' &&
            mb_stripos($_SERVER['REQUEST_URI'], '404') === false &&
            !$_SESSION['minishop2']['cart'] &&
            $modx->context->get('key') != 'mgr' &&
            !$modx->user->hasSessionContext('mgr')
        ){
            $options= [xPDO::OPT_CACHE_KEY=>'front_cache'];
            $cache_key= md5($_SERVER['REQUEST_URI']);
            ifMofifiedSince($cache_key);
            $output= $modx->cacheManager->get($cache_key, $options);
            $options= [xPDO::OPT_CACHE_KEY=>'session_cache'];
            $session= $modx->cacheManager->get($cache_key, $options);
            
            if(!empty($session) ){
                $session= unserialize($session);
                
                $_SESSION['AjaxForm']= $session['AjaxForm'];
            }

            if(!empty($output) ){
                die($output);
            }
            
            if(mb_stripos($_SERVER['HTTP_USER_AGENT'], 'wget') !== false){
                $_SESSION['AjaxForm']= [];
            }
        }
        
    break;

    
    case 'OnWebPagePrerender':
        if(
            mb_stripos($_SERVER['REQUEST_URI'], '?') === false &&
            mb_stripos($_SERVER['REQUEST_URI'], 'manager') === false &&
            empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['REQUEST_METHOD']) == 'get' &&
            mb_stripos($_SERVER['REQUEST_URI'], '404') === false &&
            !$_SESSION['minishop2']['cart'] &&
            mb_stripos($_SERVER['HTTP_USER_AGENT'], 'wget') !== false &&
            $modx->context->get('key') != 'mgr' &&
            !$modx->user->hasSessionContext('mgr')
        ){
            if($uri= substr(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH), 1)) $resource= $modx->getObject('modResource', ['uri' => $uri], false); // ID!~!!
            else $resource= true;
            
            if($resource) {
                $options= [xPDO::OPT_CACHE_KEY=>'front_cache'];
				        $cache_key= md5($_SERVER['REQUEST_URI']);

                $modx->cacheManager->set($cache_key, preg_replace([ "|(<!--.*?-->)|s", '|\s+|'], ' ', $modx->resource->_output), 0, $options);

                $options= [xPDO::OPT_CACHE_KEY=>'session_cache'];
                $session= [
                    'AjaxForm'=> $_SESSION['AjaxForm'],
                ];
                
                $modx->cacheManager->set($cache_key, serialize($session), 0, $options);
            }
        }
        
    break;

    case 'OnSiteRefresh':
      shell_exec('pkill -9 -f wget');
    
    	$options= [xPDO::OPT_CACHE_KEY=>'front_cache'];
    	$modx->cacheManager->clean($options);
    	
    	$options= [xPDO::OPT_CACHE_KEY=>'session_cache'];
    	$modx->cacheManager->clean($options);

      shell_exec('wget -r -l 7 -p -nc -nd --spider -q --reject=png,jpg,jpeg,ico,xml,txt,ttf,woff,woff2,pdf,eot,gif,svg,mp3,ogg,mpeg,avi,zip,gz,bz2,rar,swf,otf,webp,js,css https://'.MODX_HTTP_HOST.'/ >/dev/null 2>/dev/null &');
    break;

}