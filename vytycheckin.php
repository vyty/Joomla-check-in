<?php
/**
 * @package		 Vyty Plugins
 * @subpackage	 Joomla!
 * @copyright    Copyright (C) 2010 Vyty.com. All rights reserved.
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
    private $currentView = "";
    
    /**
     * Constructor
     *
     * @param object $subject The object to observe
     * @param array  $config  An optional associative array of configuration settings.
     * Recognized key values include 'name', 'group', 'params', 'language'
     * (this list is not meant to be comprehensive).
     * @since 1.5
     */
    public function __construct(&$subject, $config = array()) {
        parent::__construct($subject, $config);
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
    public function onContentPrepare($context, &$article, &$params, $limitstart) {

        $app =& JFactory::getApplication();
        /* @var $app JApplication */

        if($app->isAdmin()) {
            return;
        }
        
        $doc     = JFactory::getDocument();
        /* @var $doc JDocumentHtml */
        $docType = $doc->getType();
        
        // Check document type
        if(strcmp("html", $docType) != 0){
            return;
        }
        
        $currentOption = JRequest::getCmd("option");
        
        if( ($currentOption != "com_content") OR !isset($this->params)) {
            return;            
        }
        
        $this->currentView  = JRequest::getCmd("view");
        
        /** Check for selected views, which will display the buttons. **/   
        /** If there is a specific set and do not match, return an empty string.**/
        $showInArticles     = $this->params->get('showInArticles');
        
        if(!$showInArticles AND (strcmp("article", $this->currentView) == 0)){
            return;
        }
        
        // Check for category view
        $showInCategories   = $this->params->get('showInCategories');
        
        if(!$showInCategories AND (strcmp("category", $this->currentView) == 0)){
            return;
        }
        
        if($showInCategories AND ($this->currentView == "category")) {
            $articleData        = $this->getArticle($article);
            $article->id        = $articleData['id'];
            $article->catid     = $articleData['catid'];
            $article->title     = $articleData['title'];
            $article->slug      = $articleData['slug'];
            $article->catslug   = $articleData['catslug'];
        }
        
        if(!isset($article) OR empty($article->id) ) {
            return;            
        }
        
        $excludeArticles = $this->params->get('excludeArticles');
        if(!empty($excludeArticles)){
            $excludeArticles = explode(',', $excludeArticles);
        }
        settype($excludeArticles, 'array');
        JArrayHelper::toInteger($excludeArticles);
        
        // Exluded categories
        $excludedCats           = $this->params->get('excludeCats');
        if(!empty($excludedCats)){
            $excludedCats = explode(',', $excludedCats);
        }
        settype($excludedCats, 'array');
        JArrayHelper::toInteger($excludedCats);
        
        // Included Articles
        $includedArticles = $this->params->get('includeArticles');
        if(!empty($includedArticles)){
            $includedArticles = explode(',', $includedArticles);
        }
        settype($includedArticles, 'array');
        JArrayHelper::toInteger($includedArticles);
        
        if(!in_array($article->id, $includedArticles)) {
            // Check exluded places
            if(in_array($article->id, $excludeArticles) OR in_array($article->catid, $excludedCats)){
                return "";
            }
        }
            
        // Generate content
		$content      = $this->getContent($article, $params);
        $position     = $this->params->get('position');
        
        switch($position){
            case 1:
                $article->text = $content . $article->text;
                break;
            case 2:
                $article->text = $article->text . $content;
                break;
            default:
                $article->text = $content . $article->text . $content;
                break;
        }
        
        return;
    }
    
    /**
     * Generate the content with buttons
     * 
     * @param   object      The article object.  Note $article->text is also available
     * @param   object      The article params
     * @return  string      Returns html code or empty string.
     */
    private function getContent(&$article, &$params){
        
        $doc   = JFactory::getDocument();
        /* @var $doc JDocumentHtml */
        
        $url = JURI::getInstance();
        $root= $url->getScheme() ."://" . $url->getHost();
        
        $url = JRoute::_(ContentHelperRoute::getArticleRoute($article->slug, $article->catslug), false);
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
     * Get the information about aritcle.
     * This method will be used if you want to add the button on the view 'category'
     * @param object $article
     */
    private function getArticle(&$article) {
        
        $db = JFactory::getDbo();
        
        $query = "
            SELECT 
                `#__content`.`id`,
                `#__content`.`catid`,
                `#__content`.`alias`,
                `#__content`.`title`,
                `#__categories`.`alias` as category_alias
            FROM
                `#__content`
            INNER JOIN
                `#__categories`
            ON
                `#__content`.`catid`=`#__categories`.`id`
            WHERE
                `#__content`.`introtext` = " . $db->Quote($article->text); 
        
        $db->setQuery($query);
        $result = $db->loadAssoc();
        
        if ($db->getErrorNum() != 0) {
            JError::raiseError(500, "System error!", $db->getErrorMsg());
        }
        
        if(!empty($result)) {
            $result['slug'] = $result['alias'] ? ($result['id'].':'.$result['alias']) : $result['id'];
            $result['catslug'] = $result['category_alias'] ? ($result['catid'].':'.$result['category_alias']) : $result['catid'];
        }
        
        return $result;
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