<?php
/**
 * @package		 Vyty Plugins
 * @subpackage	 Joomla!
 * @copyright    Copyright (C) 2010 Vyty.com <todor@vyty.com>. All rights reserved.
 * @license      http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * Vyty Check-In is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 */

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

jimport('joomla.plugin.plugin');

/**
 * Display a check-in button on the web pages
 *
 * @package		Vyty Plugins
 * @subpackage	Joomla!
 * @since 		1.5
 */
class plgContentVytyCheckin extends JPlugin {
       
    static $vytyFlag = 0;
    
    public function __construct($subject, $params){
        
        parent::__construct($subject, $params);

    }
    
    /**
     * Prepare the plugin and data, which we need to generate the buttons
     * Method is called by the view
     *
     * @param   string  The context of the content being passed to the plugin.
     * @param   object  The content object.  Note $article->text is also available
     * @param   object  The content params
     * @param   int     The 'page' number
     * @since   1.6
     */
    public function onPrepareContent(&$article, &$params, $limitstart){
        
        $app =& JFactory::getApplication();
        /* @var $app JApplication */

        if($app->isAdmin()) {
            return;
        }
        
        $doc   = JFactory::getDocument();
        /* @var $doc JDocumentHtml */
        $docType = $doc->getType();
        
        // Check document type
        if(strcmp("html", $docType) != 0){
            return;
        }
        
        $currentOption = JRequest::getCmd("option");
        
        if(($currentOption != "com_content") OR !isset($article) OR empty($article->id) OR !isset($this->params)) {
            return;            
        }
        
        // Generate buttons
        $buttons    = $this->getContent($article, $params);
        $position   = $this->params->get('position');
        
        switch($position){
            case 1:
                $article->text = $buttons . $article->text;
                break;
            case 2:
                $article->text = $article->text . $buttons;
                break;
            default:
                $article->text = $buttons . $article->text . $buttons;
                break;
        }
        
        return;
    }
    
    
    /**
     * Generate content
     * @param   object      The article object.  Note $article->text is also available
     * @param   object      The article params
     * @return  string      Returns html code or empty string.
     */
    private function getContent(&$article, &$params){
        
        $doc         = JFactory::getDocument();
        $currentView = JRequest::getWord("view");
        
        // Check where we are able to show buttons?
        $showInArticles     = $this->params->get('showInArticles');
        $showInCategories   = $this->params->get('showInCategories');
        $showInSections     = $this->params->get('showInSections');
        $showInFrontPage    = $this->params->get('showInFrontPage');
        
        /** Check for selected views, which will display the buttons. **/   
        /** If there is a specific set and do not match, return an empty string.**/
        if(!$showInArticles AND (strcmp("article", $currentView) == 0)){
            return "";
        }
        
        if(!$showInCategories AND (strcmp("category", $currentView) == 0)){
            return "";
        }
        
        if(!$showInSections AND (strcmp("section", $currentView) == 0)){
            return "";
        }
        
        if(!$showInFrontPage AND (strcmp("frontpage", $currentView) == 0)){
            return "";
        }
        
        // Exclude categories
        $excludedCats = $this->params->get('excludeCats');
        if(!empty($excludedCats)){
            $excludedCats = explode(',', $excludedCats);
        }
        settype($excludedCats, 'array');
        JArrayHelper::toInteger($excludedCats);
        
        // Exclude sections
        $excludeSections = $this->params->get('excludeSections');
        if(!empty($excludeSections)){
            $excludeSections = explode(',', $excludeSections);
        }
        settype($excludeSections, 'array');
        JArrayHelper::toInteger($excludeSections);
        
        // Exclude articles
        $excludeArticles = $this->params->get('excludeArticles');
        if(!empty($excludeArticles)){
            $excludeArticles = explode(',', $excludeArticles);
        }
        settype($excludeArticles, 'array');
        JArrayHelper::toInteger($excludeArticles);
        
        // Included Articles
        $includedArticles = $this->params->get('includeArticles');
        if(!empty($includedArticles)){
            $includedArticles = explode(',', $includedArticles);
        }
        settype($includedArticles, 'array');
        JArrayHelper::toInteger($includedArticles);
        
        if(!in_array($article->id, $includedArticles)) {
            // Check exluded places
            if(in_array($article->catid, $excludedCats) OR in_array($article->sectionid, $excludeSections) OR in_array($article->id, $excludeArticles)){
                return "";
            }
        }
        
        $url = JURI::base();
        $url = new JURI($url);
        $root= $url->getScheme() ."://" . $url->getHost();
        
        $url = JRoute::_(ContentHelperRoute::getArticleRoute($article->slug, $article->catslug, $article->sectionid), false);
        $url = $root.$url;
        
        $html = '
        <div style="display: block !important;">';
        $html .= $this->getImplementationCode($this->params);
        $html .= $this->getCheckIn($this->params, $url);
        $html .= '
        </div>
        <div style="clear:both;"></div>
        ';
    
        return $html;
    }
    
    /**
     * 
     * Generate the button code
     * @param object $params
     * @param string $url
     */
    private function getCheckIn($params, $url){
        
        $html = "";
        if($params->get("checkinButton")) {
            $html ='
            <div>
            	<div class="vyty_checkin" data-game="' . $params->get("gameId").'" data-layout="' . $params->get("checkinLayout").'"  data-uri="' . $url . '" ></div>
            </div>
            ';
        }
        
        return $html;
    }
    
    /**
     * 
     * Put the implementation code into the page code
     * @param object $params
     */
    private function getImplementationCode($params){
        
        $html = "";
        
        if($params->get("checkinButton") AND $params->get("checkinImpCode") AND ( self::$vytyFlag == 0 )) {
            $html = '<div id="vyty_root"></div>
<script type="text/javascript">
  (function() {
    var vy = document.createElement("script"); 
    vy.type = "text/javascript"; vy.async = true;
    vy.src = "http://api.vyty.com/js/bonsai.js";
    var s = document.getElementsByTagName("script")[0]; 
    s.parentNode.insertBefore(vy, s);
  })();
</script>';
            self::$vytyFlag++;
        }
        
        return $html;
    }
}