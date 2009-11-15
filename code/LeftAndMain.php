<?php
/**
 * LeftAndMain is the parent class of all the two-pane views in the CMS.
 * If you are wanting to add more areas to the CMS, you can do it by subclassing LeftAndMain.
 * 
 * This is essentially an abstract class which should be subclassed.
 * See {@link CMSMain} for a good example.
 * 
 * @package cms
 * @subpackage core
 */
class LeftAndMain extends Controller {
	
	/**
	 * The 'base' url for CMS administration areas.
	 * Note that if this is changed, many javascript
	 * behaviours need to be updated with the correct url
	 *
	 * @var string $url_base
	 */
	static $url_base = "admin";
	
	static $url_segment;
	
	static $url_rule = '/$Action/$ID/$OtherID';
	
	static $menu_title;
	
	static $menu_priority = 0;
	
	static $url_priority = 50;

	static $tree_class = null;
	
	static $ForceReload;

	static $allowed_actions = array(
		'index',
		'ajaxupdateparent',
		'ajaxupdatesort',
		'callPageMethod',
		'deleteitems',
		'getitem',
		'getsubtree',
		'myprofile',
		'printable',
		'save',
		'show',
		'Member_ProfileForm',
		'EditorToolbar',
		'EditForm',
	);
	
	/**
	 * Register additional requirements through the {@link Requirements class}.
	 * Used mainly to work around the missing "lazy loading" functionality
	 * for getting css/javascript required after an ajax-call (e.g. loading the editform).
	 *
	 * @var array $extra_requirements
	 */
	protected static $extra_requirements = array(
		'javascript' => array(),
		'css' => array(),
		'themedcss' => array(),
	);
	
	/**
	 * @param Member $member
	 * @return boolean
	 */
	function canView($member = null) {
		if(!$member && $member !== FALSE) {
			$member = Member::currentUser();
		}
		
		// cms menus only for logged-in members
		if(!$member) return false;
		
		// alternative decorated checks
		if($this->hasMethod('alternateAccessCheck')) {
			$alternateAllowed = $this->alternateAccessCheck();
			if($alternateAllowed === FALSE) return false;
		}
			
		// Default security check for LeftAndMain sub-class permissions
		if(!Permission::checkMember($member, "CMS_ACCESS_$this->class")) {
			return false;
		}
		
		return true;
	}
	
	/**
	 * @uses LeftAndMainDecorator->init()
	 * @uses LeftAndMainDecorator->accessedCMS()
	 * @uses CMSMenu
	 */
	function init() {
		parent::init();
		
		// set language
		$member = Member::currentUser();
		if(!empty($member->Locale)) {
			i18n::set_locale($member->Locale);
		}
		
		// can't be done in cms/_config.php as locale is not set yet
		CMSMenu::add_link(
			'Help', 
			_t('LeftAndMain.HELP', 'Help', PR_HIGH, 'Menu title'), 
			'http://userhelp.silverstripe.com'
		);
		
		// set reading lang
		if(Translatable::is_enabled() && !Director::is_ajax()) {
			Translatable::choose_site_locale(array_keys(Translatable::get_existing_content_languages('SiteTree')));
		}

		// Allow customisation of the access check by a decorator
		if(!$this->canView()) {
			// When access /admin/, we should try a redirect to another part of the admin rather than be locked out
			$menu = $this->MainMenu();
			foreach($menu as $candidate) {
				if(
					$candidate->Link && 
					$candidate->Link != $this->Link() 
					&& $candidate->MenuItem->controller 
					&& singleton($candidate->MenuItem->controller)->canView()
				) {
					return Director::redirect($candidate->Link);
				}
			}
			
			if(Member::currentUser()) {
				Session::set("BackURL", null);
			}
			
			// if no alternate menu items have matched, return a permission error
			$messageSet = array(
				'default' => _t('LeftAndMain.PERMDEFAULT',"Please choose an authentication method and enter your credentials to access the CMS."),
				'alreadyLoggedIn' => _t('LeftAndMain.PERMALREADY',"I'm sorry, but you can't access that part of the CMS.  If you want to log in as someone else, do so below"),
				'logInAgain' => _t('LeftAndMain.PERMAGAIN',"You have been logged out of the CMS.  If you would like to log in again, enter a username and password below."),
			);

			return Security::permissionFailure($this, $messageSet);
		}

		// Don't continue if there's already been a redirection request.
		if(Director::redirected_to()) return;

		// Audit logging hook
		if(empty($_REQUEST['executeForm']) && !Director::is_ajax()) $this->extend('accessedCMS');

		// Set the members html editor config
		HtmlEditorConfig::set_active(Member::currentUser()->getHtmlEditorConfigForCMS());

		Requirements::css(CMS_DIR . '/css/typography.css');
		Requirements::css(CMS_DIR . '/css/layout.css');
		Requirements::css(CMS_DIR . '/css/cms_left.css');
		Requirements::css(CMS_DIR . '/css/cms_right.css');
		Requirements::css(SAPPHIRE_DIR . '/css/Form.css');
		
		if(isset($_REQUEST['debug_firebug'])) {
			// Firebug is a useful console for debugging javascript
			// Its available as a Firefox extension or a javascript library
			// for easy inclusion in other browsers (just append ?debug_firebug=1 to the URL)
			Requirements::javascript(THIRDPARTY_DIR . '/firebug/firebug-lite-compressed.js');
		} else {
			// By default, we include fake-objects for all firebug calls
			// to avoid javascript errors when referencing console.log() etc in javascript code
			Requirements::javascript(THIRDPARTY_DIR . '/firebug/firebugx.js');
		}
		
		Requirements::javascript(THIRDPARTY_DIR . '/prototype.js');
		Requirements::javascript(THIRDPARTY_DIR . '/jquery/jquery.js');
		Requirements::javascript(THIRDPARTY_DIR . '/jquery/jquery_improvements.js');
		Requirements::javascript(THIRDPARTY_DIR . '/behaviour.js');
		Requirements::javascript(THIRDPARTY_DIR . '/jquery/plugins/livequery/jquery.livequery.js');
		Requirements::javascript(SAPPHIRE_DIR . '/javascript/core/jquery.ondemand.js');
		Requirements::javascript(THIRDPARTY_DIR . '/prototype_improvements.js');
		Requirements::javascript(THIRDPARTY_DIR . '/loader.js');
		Requirements::javascript(THIRDPARTY_DIR . '/hover.js');
		Requirements::javascript(THIRDPARTY_DIR . '/layout_helpers.js');
		Requirements::add_i18n_javascript(SAPPHIRE_DIR . '/javascript/lang');
		Requirements::add_i18n_javascript(CMS_DIR . '/javascript/lang');
		
		Requirements::javascript(THIRDPARTY_DIR . '/scriptaculous/effects.js');
		Requirements::javascript(THIRDPARTY_DIR . '/scriptaculous/dragdrop.js');
		Requirements::javascript(THIRDPARTY_DIR . '/scriptaculous/controls.js');

		Requirements::css(THIRDPARTY_DIR . '/greybox/greybox.css');
		Requirements::javascript(THIRDPARTY_DIR . '/greybox/AmiJS.js');
		Requirements::javascript(THIRDPARTY_DIR . '/greybox/greybox.js');
		
		Requirements::javascript(THIRDPARTY_DIR . '/tree/tree.js');
		Requirements::css(THIRDPARTY_DIR . '/tree/tree.css');
		
		Requirements::javascript(CMS_DIR . '/javascript/LeftAndMain.js');
		Requirements::javascript(CMS_DIR . '/javascript/LeftAndMain_left.js');
		Requirements::javascript(CMS_DIR . '/javascript/LeftAndMain_right.js');
	
		Requirements::javascript(CMS_DIR . '/javascript/SideTabs.js');
		Requirements::javascript(CMS_DIR . '/javascript/SideReports.js');
		Requirements::javascript(CMS_DIR . '/javascript/LangSelector.js');
		Requirements::javascript(CMS_DIR . '/javascript/TranslationTab.js');
		
		Requirements::themedCSS('typography');

		foreach (self::$extra_requirements['javascript'] as $file) {
			Requirements::javascript($file[0]);
		}
		
		foreach (self::$extra_requirements['css'] as $file) {
			Requirements::css($file[0], $file[1]);
		}
		
		foreach (self::$extra_requirements['themedcss'] as $file) {
			Requirements::css($file[0], $file[1]);
		}
		
		Requirements::customScript('Behaviour.addLoader(hideLoading);');

		// Javascript combined files
		Requirements::combine_files(
			'assets/base.js',
			array(
				'jsparty/prototype.js',
				'jsparty/behaviour.js',
				'jsparty/prototype_improvements.js',
				'jsparty/jquery/jquery.js',
				'jsparty/jquery/plugins/livequery/jquery.livequery.js',
				'jsparty/jquery/plugins/effen/jquery.fn.js',
				'sapphire/javascript/core/jquery.ondemand.js',
				'jsparty/jquery/jquery_improvements.js',
				'jsparty/firebug/firebugx.js',
				'sapphire/javascript/i18n.js',
			)
		);

		Requirements::combine_files(
			'assets/leftandmain.js',
			array(
				'jsparty/loader.js',
				'jsparty/hover.js',
				'jsparty/layout_helpers.js',
				'jsparty/scriptaculous/effects.js',
				'jsparty/scriptaculous/dragdrop.js',
				'jsparty/scriptaculous/controls.js',
				'jsparty/greybox/AmiJS.js',
				'jsparty/greybox/greybox.js',
				'cms/javascript/LeftAndMain.js',
				'cms/javascript/LeftAndMain_left.js',
				'cms/javascript/LeftAndMain_right.js',
				'jsparty/tree/tree.js',
				'jsparty/tabstrip/tabstrip.js',
				'jsparty/SWFUpload/swfupload.js',
				'cms/javascript/Upload.js',
				'cms/javascript/TinyMCEImageEnhancement.js',
				'sapphire/javascript/TreeSelectorField.js',
		 		'cms/javascript/ThumbnailStripField.js',
			)
		);

		Requirements::combine_files(
			'assets/cmsmain.js',
			array(
				'cms/javascript/CMSMain.js',
				'cms/javascript/CMSMain_left.js',
				'cms/javascript/CMSMain_right.js',
				'cms/javascript/SideTabs.js',
				'cms/javascript/SideReports.js',
				'cms/javascript/LangSelector.js',
				'cms/javascript/TranslationTab.js',
				'jsparty/calendar/calendar.js',
				'jsparty/calendar/lang/calendar-en.js',
				'jsparty/calendar/calendar-setup.js',
			)
		);

		// DEPRECATED 2.3: Use init()
		$dummy = null;
		$this->extend('augmentInit', $dummy);
		
		$dummy = null;
		$this->extend('init', $dummy);
	}

	//------------------------------------------------------------------------------------------//
	// Main controllers

	/**
	 * You should implement a Link() function in your subclass of LeftAndMain,
	 * to point to the URL of that particular controller.
	 * 
	 * @return string
	 */
	public function Link($action = null) {
		// Handle missing url_segments
		if(!$this->stat('url_segment', true))
			self::$url_segment = $this->class;
		return Controller::join_links(
			$this->stat('url_base', true),
			$this->stat('url_segment', true),
			'/', // trailing slash needed if $action is null!
			"$action"
		);
	}
	
	
	/**
 	* Returns the menu title for the given LeftAndMain subclass.
 	* Implemented static so that we can get this value without instantiating an object.
 	* Menu title is *not* internationalised.
 	*/
	static function menu_title_for_class($class) {
		$title = eval("return $class::\$menu_title;");
		if(!$title) $title = preg_replace('/Admin$/', '', $class);
		return $title;
	}

	public function show($params) {
		if($params['ID']) $this->setCurrentPageID($params['ID']);
		if(isset($params['OtherID']))
			Session::set('currentMember', $params['OtherID']);

		if(Director::is_ajax()) {
			SSViewer::setOption('rewriteHashlinks', false);
			return $this->EditForm()->formHtmlContent();

		} else {
			return array();
		}
	}


	public function getitem() {
		$this->setCurrentPageID($_REQUEST['ID']);
		SSViewer::setOption('rewriteHashlinks', false);

		if(isset($_REQUEST['ID']) && is_numeric($_REQUEST['ID'])) {
			$record = DataObject::get_by_id($this->stat('tree_class'), $_REQUEST['ID']);
			if($record && !$record->canView()) return Security::permissionFailure($this);
		}

		$form = $this->EditForm();
		if ($form) return $form->formHtmlContent();
		else return "";
	}
	public function getLastFormIn($html) {
		$parts = split('</?form[^>]*>', $html);
		return $parts[sizeof($parts)-2];
	}

	//------------------------------------------------------------------------------------------//
	// Main UI components

	/**
	 * Returns the main menu of the CMS.  This is also used by init() to work out which sections the user
	 * has access to.
	 * 
	 * @return DataObjectSet
	 */
	public function MainMenu() {
		// Don't accidentally return a menu if you're not logged in - it's used to determine access.
		if(!Member::currentUser()) return new DataObjectSet();

		// Encode into DO set
		$menu = new DataObjectSet();
		$menuItems = CMSMenu::get_viewable_menu_items();
		if($menuItems) foreach($menuItems as $code => $menuItem) {
			// alternate permission checks (in addition to LeftAndMain->canView())
			if(
				isset($menuItem->controller) 
				&& $this->hasMethod('alternateMenuDisplayCheck')
				&& !$this->alternateMenuDisplayCheck($menuItem->controller)
			) {
				continue;
			}

			$linkingmode = "";
			
			if(strpos($this->Link(), $menuItem->url) !== false) {
				if($this->Link() == $menuItem->url) {
					$linkingmode = "current";
				
				// default menu is the one with a blank {@link url_segment}
				} else if(singleton($menuItem->controller)->stat('url_segment') == '') {
					if($this->Link() == $this->stat('url_base').'/') $linkingmode = "current";

				} else {
					$linkingmode = "current";
				}
			}
		
			// already set in CMSMenu::populate_menu(), but from a static pre-controller
			// context, so doesn't respect the current user locale in _t() calls - as a workaround,
			// we simply call LeftAndMain::menu_title_for_class() again if we're dealing with a controller
			if($menuItem->controller) {
				$defaultTitle = LeftAndMain::menu_title_for_class($menuItem->controller);
				$title = _t("{$menuItem->controller}.MENUTITLE", $defaultTitle);
			} else {
				$title = $menuItem->title;
			}
			
			$menu->push(new ArrayData(array(
				"MenuItem" => $menuItem,
				"Title" => Convert::raw2xml($title),
				"Code" => $code,
				"Link" => $menuItem->url,
				"LinkingMode" => $linkingmode
			)));
		}
		
		// if no current item is found, assume that first item is shown
		//if(!isset($foundCurrent)) 
		return $menu;
	}


	public function CMSTopMenu() {
		return $this->renderWith(array('CMSTopMenu_alternative','CMSTopMenu'));
	}

  /**
   * Return a list of appropriate templates for this class, with the given suffix
   */
  protected function getTemplatesWithSuffix($suffix) {
    $classes = array_reverse(ClassInfo::ancestry($this->class));
    foreach($classes as $class) {
      $templates[] = $class . $suffix;
      if($class == 'LeftAndMain') break;
    }
    return $templates;
  }

	public function Left() {
		return $this->renderWith($this->getTemplatesWithSuffix('_left'));
	}

	public function Right() {
		return $this->renderWith($this->getTemplatesWithSuffix('_right'));
	}

	public function getRecord($id, $className = null) {
		if($id && is_numeric($id)) {
			if(!$className) $className = $this->stat('tree_class');
			return DataObject::get_by_id($className, $id);
		}
	}

	/**
	 * Get a site tree displaying the nodes under the given objects
	 * @param $className The class of the root object
	 * @param $rootID The ID of the root object.  If this is null then a complete tree will be
	 *                shown
	 * @param $childrenMethod The method to call to get the children of the tree.  For example,
	 *                        Children, AllChildrenIncludingDeleted, or AllHistoricalChildren
	 */
	function getSiteTreeFor($className, $rootID = null, $childrenMethod = null, $filterFunction = null, $minNodeCount = 30) {
		// Default childrenMethod
		if (!$childrenMethod) $childrenMethod = 'AllChildrenIncludingDeleted';
		
		// Get the tree root
		$obj = $rootID ? $this->getRecord($rootID) : singleton($className);
		
		// Mark the nodes of the tree to return
		if ($filterFunction) $obj->setMarkingFilterFunction($filterFunction);

		$obj->markPartialTree($minNodeCount, $this, $childrenMethod);
		
		// Ensure current page is exposed
		if($p = $this->currentPage()) $obj->markToExpose($p);
		
		// NOTE: SiteTree/CMSMain coupling :-(
		SiteTree::prepopuplate_permission_cache('edit', $obj->markedNodeIDs());
		SiteTree::prepopuplate_permission_cache('delete', $obj->markedNodeIDs());

		// getChildrenAsUL is a flexible and complex way of traversing the tree
		$titleEval = '
					"<li id=\"record-$child->ID\" class=\"" . $child->CMSTreeClasses($extraArg) . "\">" .
					"<a href=\"" . Director::link(substr($extraArg->Link(),0,-1), "show", $child->ID) . "\" class=\"" . $child->CMSTreeClasses($extraArg) . "\" title=\"' . _t('LeftAndMain.PAGETYPE','Page type: ') . '".$child->class."\" >" . 
					($child->TreeTitle()) . 
					"</a>"
';
		$siteTree = $obj->getChildrenAsUL(
			"", 
			$titleEval,
			$this, 
			true, 
			$childrenMethod,
			$minNodeCount
		);

		// Wrap the root if needs be.

		if(!$rootID) {
			$rootLink = $this->Link() . '0';
			
			// This lets us override the tree title with an extension
			if($this->hasMethod('getCMSTreeTitle')) $treeTitle = $this->getCMSTreeTitle();
			else $treeTitle =  _t('LeftAndMain.SITECONTENTLEFT',"Site Content",PR_HIGH,'Root node on left');
			
			$siteTree = "<ul id=\"sitetree\" class=\"tree unformatted\"><li id=\"record-0\" class=\"Root nodelete\"><a href=\"$rootLink\"><strong>$treeTitle</strong></a>"
				. $siteTree . "</li></ul>";
		}

		return $siteTree;
	}

	/**
	 * Get a subtree underneath the request param 'ID'.
	 * If ID = 0, then get the whole tree.
	 */
	public function getsubtree($request) {
		// Get the tree
		$minNodeCount = (is_numeric($request->getVar('minNodeCount'))) ? $request->getVar('minNodeCount') : NULL;
		$tree = $this->getSiteTreeFor(
			$this->stat('tree_class'), 
			$request->getVar('ID'), 
			null, 
			null, 
			$minNodeCount
		);

		// Trim off the outer tag
		$tree = ereg_replace('^[ \t\r\n]*<ul[^>]*>','', $tree);
		$tree = ereg_replace('</ul[^>]*>[ \t\r\n]*$','', $tree);
		
		return $tree;
	}

	/**
	 * Allows you to returns a new data object to the tree (subclass of sitetree)
	 * and updates the tree via javascript.
	 */
	public function returnItemToUser($p) {
		if(Director::is_ajax()) {
			// Prepare the object for insertion.
			$parentID = (int) $p->ParentID;
			$id = $p->ID ? $p->ID : "new-$p->class-$p->ParentID";
			$treeTitle = Convert::raw2js($p->TreeTitle());
			$hasChildren = (is_numeric($id) && $p->AllChildren() && $p->AllChildren()->Count()) ? ' unexpanded' : '';
			$singleInstanceCSSClass = $p->stat('single_instance_only') ?  $p->stat('single_instance_only_css_class') : "";

			// Ensure there is definitly a node avaliable. if not, append to the home tree.
			$response = <<<JS
				var tree = $('sitetree');
				var newNode = tree.createTreeNode("$id", "$treeTitle", "{$p->class}{$hasChildren} {$singleInstanceCSSClass}");
				node = tree.getTreeNodeByIdx($parentID);
				if(!node) {
					node = tree.getTreeNodeByIdx(0);
				}
				node.open();
				node.appendTreeNode(newNode);
				newNode.selectTreeNode();	
JS;
			FormResponse::add($response);
			FormResponse::add($this->hideSingleInstanceOnlyFromCreateFieldJS($p));
			
			return FormResponse::respond();
		} else {
			Director::redirect('admin/' . self::$url_segment . '/show/' . $p->ID);
		}
	}

	/**
	 * Save and Publish page handler
	 */
	public function save($urlParams, $form) {
		$className = $this->stat('tree_class');
		$result = '';

		$SQL_id = Convert::raw2sql($_REQUEST['ID']);
		if(substr($SQL_id,0,3) != 'new') {
			$record = DataObject::get_one($className, "\"$className\".\"ID\" = {$SQL_id}");
			if($record && !$record->canEdit()) return Security::permissionFailure($this);
		} else {
			if(!singleton($this->stat('tree_class'))->canCreate()) return Security::permissionFailure($this);
			$record = $this->getNewItem($SQL_id, false);
		}

		// We don't want to save a new version if there are no changes
		$dataFields_new = $form->Fields()->dataFields();
		$dataFields_old = $record->getAllFields();
		$changed = false;
		$hasNonRecordFields = false;
		foreach($dataFields_new as $datafield) {
			// if the form has fields not belonging to the record
			if(!isset($dataFields_old[$datafield->Name()])) {
				$hasNonRecordFields = true;
			}
			// if field-values have changed
			if(!isset($dataFields_old[$datafield->Name()]) || $dataFields_old[$datafield->Name()] != $datafield->dataValue()) {
				$changed = true;
			}
		}

		if(!$changed && !$hasNonRecordFields) {
			// Tell the user we have saved even though we haven't, as not to confuse them
			if(is_a($record, "Page")) {
				$record->Status = "Saved (update)";
			}
			FormResponse::status_message(_t('LeftAndMain.SAVEDUP',"Saved"), "good");
			FormResponse::update_status($record->Status);
			return FormResponse::respond();
		}

		$form->dataFieldByName('ID')->Value = 0;

		if(isset($urlParams['Sort']) && is_numeric($urlParams['Sort'])) {
			$record->Sort = $urlParams['Sort'];
		}

		// HACK: This should be turned into something more general
		$originalClass = $record->ClassName;
		$originalStatus = $record->Status;
		$originalParentID = $record->ParentID;

		$record->HasBrokenLink = 0;
		$record->HasBrokenFile = 0;

		$record->writeWithoutVersion();

		// HACK: This should be turned into something more general
		$originalURLSegment = $record->URLSegment;

		$form->saveInto($record, true);

		if(is_a($record, "Page")) {
			$record->Status = ($record->Status == "New page" || $record->Status == "Saved (new)") ? "Saved (new)" : "Saved (update)";
		}

		if(Director::is_ajax()) {
			if($SQL_id != $record->ID) {
				FormResponse::add("$('sitetree').setNodeIdx(\"{$SQL_id}\", \"$record->ID\");");
				FormResponse::add("$('Form_EditForm').elements.ID.value = \"$record->ID\";");
			}

			if($added = DataObjectLog::getAdded('SiteTree')) {
				foreach($added as $page) {
					if($page->ID != $record->ID) $result .= $this->addTreeNodeJS($page);
				}
			}
			if($deleted = DataObjectLog::getDeleted('SiteTree')) {
				foreach($deleted as $page) {
					if($page->ID != $record->ID) $result .= $this->deleteTreeNodeJS($page);
				}
			}
			if($changed = DataObjectLog::getChanged('SiteTree')) {
				foreach($changed as $page) {
					if($page->ID != $record->ID) {
						$title = Convert::raw2js($page->TreeTitle());
						FormResponse::add("$('sitetree').setNodeTitle($page->ID, \"$title\");");
					}
				}
			}

			$message = _t('LeftAndMain.SAVEDUP');

			// Update the class instance if necessary
			if($originalClass != $record->ClassName) {
				$newClassName = $record->ClassName;
				// The records originally saved attribute was overwritten by $form->saveInto($record) before.
				// This is necessary for newClassInstance() to work as expected, and trigger change detection
				// on the ClassName attribute
				$record->setClassName($originalClass);
				// Replace $record with a new instance
				$record = $record->newClassInstance($newClassName);
				
				// update the tree icon
				FormResponse::add("if(\$('sitetree').setNodeIcon) \$('sitetree').setNodeIcon($record->ID, '$originalClass', '$record->ClassName');");
			}

			// HACK: This should be turned into somethign more general
			if( ($record->class == 'VirtualPage' && $originalURLSegment != $record->URLSegment) ||
				($originalClass != $record->ClassName) || self::$ForceReload == true) {
				FormResponse::add("$('Form_EditForm').getPageFromServer($record->ID);");
			}

			// After reloading action
			if($originalStatus != $record->Status) {
				$message .= sprintf(_t('LeftAndMain.STATUSTO',"  Status changed to '%s'"),$record->Status);
			}
			
			if($originalParentID != $record->ParentID) {
				FormResponse::add("if(\$('sitetree').setNodeParentID) \$('sitetree').setNodeParentID($record->ID, $record->ParentID);");
			}

			$record->write();
			
			// if changed to a single_instance_only page type
			if ($record->stat('single_instance_only')) {
				FormResponse::add("jQuery('#sitetree li.{$record->ClassName}').addClass('{$record->stat('single_instance_only_css_class')}');");
				FormResponse::add($this->hideSingleInstanceOnlyFromCreateFieldJS($record));
			}
			else {
				FormResponse::add("jQuery('#sitetree li.{$record->ClassName}').removeClass('{$record->stat('single_instance_only_css_class')}');");
			}
			// if chnaged from a single_instance_only page type
			$sampleOriginalClassObject = new $originalClass();
			if($sampleOriginalClassObject->stat('single_instance_only')) {
				FormResponse::add($this->showSingleInstanceOnlyInCreateFieldJS($sampleOriginalClassObject));
			}
			
			if( ($record->class != 'VirtualPage') && $originalURLSegment != $record->URLSegment) {
				$message .= sprintf(_t('LeftAndMain.CHANGEDURL',"  Changed URL to '%s'"),$record->URLSegment);
				FormResponse::add("\$('Form_EditForm').elements.URLSegment.value = \"$record->URLSegment\";");
				FormResponse::add("\$('Form_EditForm_StageURLSegment').value = \"{$record->URLSegment}\";");
			}

			// If the 'Save & Publish' button was clicked, also publish the page
			if (isset($urlParams['publish']) && $urlParams['publish'] == 1) {
				$this->extend('onAfterSave', $record);
			
				$record->doPublish();
				
				// Update classname with original and get new instance (see above for explanation)
				$record->setClassName($originalClass);
				$publishedRecord = $record->newClassInstance($record->ClassName);

				return $this->tellBrowserAboutPublicationChange(
					$publishedRecord, 
					sprintf(
						_t(
							'LeftAndMain.STATUSPUBLISHEDSUCCESS', 
							"Published '%s' successfully",
							PR_MEDIUM,
							'Status message after publishing a page, showing the page title'
						),
						$record->Title
					)
				);
			} else {
				// BUGFIX: Changed icon only shows after Save button is clicked twice http://support.silverstripe.com/gsoc/ticket/76
				$title = Convert::raw2js($record->TreeTitle());
				FormResponse::add("$('sitetree').setNodeTitle(\"$record->ID\", \"$title\");");
				$result .= $this->getActionUpdateJS($record);
				FormResponse::status_message($message, "good");
				FormResponse::update_status($record->Status);

				$this->extend('onAfterSave', $record);
				
				return FormResponse::respond();
			}
		}
	}
	
	/** 
	 * Return a javascript snippet that hides a page type from Create dropdownfield 
	 * if it's a single_instance_only page type and has been created in the site tree
	 */
	protected function hideSingleInstanceOnlyFromCreateFieldJS($createdPage) {
		// Prepare variable to single_instance_only checking in javascript
		$pageClassName = $createdPage->class;
		$singleInstanceCSSClass = "";
		$singleInstanceClassSelector = "." . $createdPage->stat('single_instance_only_css_class');
		if ($createdPage->stat('single_instance_only')) {
			$singleInstanceCSSClass = $createdPage->stat('single_instance_only_css_class');
		}
		
		return <<<JS
			// if the current page type that was created is single_instance_only, 
			// hide it from the create dropdownlist afterward
			singleSingleOnlyOfThisPageType = jQuery("#sitetree li.{$pageClassName}{$singleInstanceClassSelector}");
			
			if (singleSingleOnlyOfThisPageType.length > 0) {
				jQuery("#" + _HANDLER_FORMS.addpage + " option[@value={$pageClassName}]").remove();
			}
JS;
	}
	
	/** 
	 * Return a javascript snippet that that shows a single_instance_only page type in Create dropdownfield 
	 * if there isn't any of its instance in the site tree
	 */
	protected function showSingleInstanceOnlyInCreateFieldJS($deletedPage) {
		$className = $deletedPage->class;
		$singularName = $deletedPage->singular_name();
		$singleInstanceClassSelector = "." . $deletedPage->stat('single_instance_only_css_class');
		return <<<JS
// show the hidden single_instance_only page type in the create dropdown field
singleSingleOnlyOfThisPageType = jQuery("#sitetree li.{$className}{$singleInstanceClassSelector}");

if (singleSingleOnlyOfThisPageType.length == 0) {	
	if(jQuery("#" + _HANDLER_FORMS.addpage + " option[@value={$className}]").length == 0) {
		jQuery("#" + _HANDLER_FORMS.addpage + " select option").each(function(){
			if ("{$singularName}".toLowerCase() >= jQuery(this).val().toLowerCase()) {
				jQuery("<option value=\"{$className}\">{$singularName}</option>").insertAfter(this);
			}
		});
	}
}
JS;
	}

	/**
	 * Return a piece of javascript that will update the actions of the main form
	 */
	public function getActionUpdateJS($record) {
		// Get the new action buttons

		$tempForm = $this->getEditForm($record->ID);
		$actionList = '';
		foreach($tempForm->Actions() as $action) {
			$actionList .= $action->Field() . ' ';
		}

		FormResponse::add("$('Form_EditForm').loadActionsFromString('" . Convert::raw2js($actionList) . "');");

		return FormResponse::respond();
	}

	/**
	 * Return JavaScript code to generate a tree node for the given page, if visible
	 */
	public function addTreeNodeJS($page, $select = false) {
		$parentID = (int)$page->ParentID;
		$title = Convert::raw2js($page->TreeTitle());
		$response = <<<JS
var newNode = $('sitetree').createTreeNode($page->ID, "$title", "$page->class");
var parentNode = $('sitetree').getTreeNodeByIdx($parentID); 
if(parentNode) parentNode.appendTreeNode(newNode);
JS;
		$response .= ($select ? "newNode.selectTreeNode();\n" : "") ;
		FormResponse::add($response);
		return FormResponse::respond();
	}
	/**
	 * Return JavaScript code to remove a tree node for the given page, if it exists.
	 */
	public function deleteTreeNodeJS($page) {
		$id = $page->ID ? $page->ID : $page->OldID;
		$response = <<<JS
var node = $('sitetree').getTreeNodeByIdx($id);
if(node && node.parentTreeNode) node.parentTreeNode.removeTreeNode(node);
$('Form_EditForm').closeIfSetTo($id);
JS;
		FormResponse::add($response);
		
		if ($this instanceof LeftAndMain) FormResponse::add($this->showSingleInstanceOnlyInCreateFieldJS($page));
		return FormResponse::respond();
	}

	/**
	 * Sets a static variable on this class which means the panel will be reloaded.
	 */
	static function ForceReload(){
		self::$ForceReload = true;
	}

	/**
	 * Ajax handler for updating the parent of a tree node
	 */
	public function ajaxupdateparent() {
		$id = $_REQUEST['ID'];
		$parentID = $_REQUEST['ParentID'];
		if($parentID == 'root'){
			$parentID = 0;
		}
		$_REQUEST['ajax'] = 1;
		$cleanupJS = '';
		
		if (!Permission::check('SITETREE_REORGANISE') && !Permission::check('ADMIN')) {
			FormResponse::status_message(_t('LeftAndMain.CANT_REORGANISE',"You do not have permission to rearange the site tree. Your change was not saved."),"bad");
			return FormResponse::respond();
		}

		if(is_numeric($id) && is_numeric($parentID) && $id != $parentID) {
			$node = DataObject::get_by_id($this->stat('tree_class'), $id);
			if($node){
				if($node && !$node->canEdit()) return Security::permissionFailure($this);
				
				$node->ParentID = $parentID;
				$node->Status = "Saved (update)";
				$node->write();

				if(is_numeric($_REQUEST['CurrentlyOpenPageID'])) {
					$currentPage = DataObject::get_by_id($this->stat('tree_class'), $_REQUEST['CurrentlyOpenPageID']);
					if($currentPage) {
						$cleanupJS = $currentPage->cmsCleanup_parentChanged();
					}
				}

				FormResponse::status_message(_t('LeftAndMain.SAVED','saved'), 'good');
				if($cleanupJS) FormResponse::add($cleanupJS);

			}else{
				FormResponse::status_message(_t('LeftAndMain.PLEASESAVE',"Please Save Page: This page could not be upated because it hasn't been saved yet."),"good");
			}


			return FormResponse::respond();
		} else {
			user_error("Error in ajaxupdateparent request; id=$id, parentID=$parentID", E_USER_ERROR);
		}
	}

	/**
	 * Ajax handler for updating the order of a number of tree nodes
	 * $_GET[ID]: An array of node ids in the correct order
	 * $_GET[MovedNodeID]: The node that actually got moved
	 */
	public function ajaxupdatesort() {
		$className = $this->stat('tree_class');
		$counter = 0;
		$js = '';
		$_REQUEST['ajax'] = 1;
		
		if (!Permission::check('SITETREE_REORGANISE') && !Permission::check('ADMIN')) {
			FormResponse::status_message(_t('LeftAndMain.CANT_REORGANISE',"You do not have permission to rearange the site tree. Your change was not saved."),"bad");
			return FormResponse::respond();
		}

		if(is_array($_REQUEST['ID'])) {
			if($_REQUEST['MovedNodeID']==0){ //Sorting root
				$movedNode = DataObject::get($className, "\"ParentID\"=0");				
			}else{
				$movedNode = DataObject::get_by_id($className, $_REQUEST['MovedNodeID']);
			}
			foreach($_REQUEST['ID'] as $id) {
				if($id == $movedNode->ID) {
					$movedNode->Sort = ++$counter;
					$movedNode->Status = "Saved (update)";
					$movedNode->write();

					$title = Convert::raw2js($movedNode->TreeTitle());
					$js .="$('sitetree').setNodeTitle($movedNode->ID, \"$title\");\n";

				// Nodes that weren't "actually moved" shouldn't be registered as having been edited; do a direct SQL update instead
				} else if(is_numeric($id)) {
					++$counter;
					DB::query("UPDATE \"$className\" SET \"Sort\" = $counter WHERE \"ID\" = '$id'");
				}
			}
			// Virtual pages require selected to be null if the page is the same.
			FormResponse::add(
				"if( $('sitetree').selected && $('sitetree').selected[0]){
					var idx =  $('sitetree').selected[0].getIdx();
					if(idx){
						$('Form_EditForm').getPageFromServer(idx);
					}
				}\n" . $js
			);
			FormResponse::status_message(_t('LeftAndMain.SAVED'), 'good');
		} else {
			FormResponse::error(_t('LeftAndMain.REQUESTERROR',"Error in request"));
		}

		return FormResponse::respond();
	}
	
	public function CanOrganiseSitetree() {
		return !Permission::check('SITETREE_REORGANISE') && !Permission::check('ADMIN') ? false : true;
	}

	/**
	 * Delete a number of items
	 */
	public function deleteitems() {
		$ids = split(' *, *', $_REQUEST['csvIDs']);

		$script = "st = \$('sitetree'); \n";
		foreach($ids as $id) {
			if(is_numeric($id)) {
				$record = DataObject::get_by_id($this->stat('tree_class'), $id);
				if($record && !$record->canDelete()) return Security::permissionFailure($this);
				
				DataObject::delete_by_id($this->stat('tree_class'), $id);
				$script .= "node = st.getTreeNodeByIdx($id); if(node) node.parentTreeNode.removeTreeNode(node); $('Form_EditForm').closeIfSetTo($id); \n";
				
				if ($id == $this->currentPageID()) FormResponse::add('CurrentPage.isDeleted = 1;');
			}
		}
		FormResponse::add($script);

		return FormResponse::respond();
	}

	public function EditForm() {
		// Include JavaScript to ensure HtmlEditorField works.
		HtmlEditorField::include_js();
		
		if ($this->currentPageID() != 0) {
			$record = $this->currentPage();
			if(!$record) return false;
			if($record && !$record->canView()) return Security::permissionFailure($this);
		}
		if ($this->hasMethod('getEditForm')) {
			return $this->getEditForm($this->currentPageID());
		}
		
		return false;
	}
	
	public function myprofile() {
		$form = $this->Member_ProfileForm();
		return $this->customise(array(
			'Form' => $form
		))->renderWith('BlankPage');
	}
	
	public function Member_ProfileForm() {
		return new Member_ProfileForm($this, 'Member_ProfileForm', Member::currentUser());
	}

	public function printable() {
		$id = $_REQUEST['ID'] ? $_REQUEST['ID'] : $this->currentPageID();

		if($id) $form = $this->getEditForm($id);
		$form->transform(new PrintableTransformation());
		$form->actions = null;

		Requirements::clear();
		Requirements::css(CMS_DIR . '/css/LeftAndMain_printable.css');
		return array(
			"PrintForm" => $form
		);
	}

	public function currentPageID() {
		if(isset($_REQUEST['ID']) && is_numeric($_REQUEST['ID']))	{
			return $_REQUEST['ID'];
		} elseif (isset($this->urlParams['ID']) && is_numeric($this->urlParams['ID'])) {
			return $this->urlParams['ID'];
		} elseif(Session::get("{$this->class}.currentPage")) {
			return Session::get("{$this->class}.currentPage");
		} else {
			return null;
		}
	}

	public function setCurrentPageID($id) {
		Session::set("{$this->class}.currentPage", $id);
	}

	public function currentPage() {
		return $this->getRecord($this->currentPageID());
	}

	public function isCurrentPage(DataObject $page) {
		return $page->ID == Session::get("{$this->class}.currentPage");
	}
	
	/**
	 * Get the staus of a certain page and version.
	 *
	 * This function is used for concurrent editing, and providing alerts
	 * when multiple users are editing a single page. It echoes a json
	 * encoded string to the UA.
	 */

	/**
	 * Return the CMS's HTML-editor toolbar
	 */
	public function EditorToolbar() {
		return Object::create('HtmlEditorField_Toolbar', $this, "EditorToolbar");
	}

	/**
	 * Return the version number of this application.
	 * Uses the subversion path information in <mymodule>/silverstripe_version
	 * (automacially replaced $URL$ placeholder).
	 * 
	 * @return string
	 */
	public function CMSVersion() {
		$sapphireVersionFile = file_get_contents('../sapphire/silverstripe_version');
		$jspartyVersionFile = file_get_contents('../jsparty/silverstripe_version');
		$cmsVersionFile = file_get_contents('../cms/silverstripe_version');

		if(strstr($sapphireVersionFile, "/sapphire/trunk")) {
			$sapphireVersion = "trunk";
		} else {
			preg_match("/sapphire\/(?:(?:branches)|(?:tags))(?:\/rc)?\/([A-Za-z0-9._-]+)\/silverstripe_version/", $sapphireVersionFile, $matches);
			$sapphireVersion = ($matches) ? $matches[1] : null;
		}

		if(strstr($jspartyVersionFile, "/jsparty/trunk")) {
			$jspartyVersion = "trunk";
		} else {
			preg_match("/jsparty\/(?:(?:branches)|(?:tags))(?:\/rc)?\/([A-Za-z0-9._-]+)\/silverstripe_version/", $jspartyVersionFile, $matches);
			$jspartyVersion = ($matches) ? $matches[1] : null;
		}

		if(strstr($cmsVersionFile, "/cms/trunk")) {
			$cmsVersion = "trunk";
		} else {
			preg_match("/cms\/(?:(?:branches)|(?:tags))(?:\/rc)?\/([A-Za-z0-9._-]+)\/silverstripe_version/", $cmsVersionFile, $matches);
			$cmsVersion = ($matches) ? $matches[1] : null;
		}

		if($sapphireVersion == $jspartyVersion && $jspartyVersion == $cmsVersion) {
			return $sapphireVersion;
		}	else {
			return "cms: $cmsVersion, sapphire: $sapphireVersion, jsparty: $jspartyVersion";
		}
	}

	/**
	 * The application name. Customisable by calling
	 * LeftAndMain::setApplicationName() - the first parameter.
	 * 
	 * @var String
	 */
	static $application_name = 'SilverStripe CMS';
	
	/**
	 * The application logo text. Customisable by calling
	 * LeftAndMain::setApplicationName() - the second parameter.
	 *
	 * @var String
	 */
	static $application_logo_text = 'SilverStripe';

	/**
	 * Set the application name, and the logo text.
	 *
	 * @param String $name The application name
	 * @param String $logoText The logo text
	 */
	static $application_link = "http://www.silverstripe.org/";
	static function setApplicationName($name, $logoText = null, $link = null) {
		self::$application_name = $name;
		self::$application_logo_text = $logoText ? $logoText : $name;
		if($link) self::$application_link = $link;
	}

	/**
	 * Get the application name.
	 * @return String
	 */
	function getApplicationName() {
		return self::$application_name;
	}
	
	/**
	 * Get the application logo text.
	 * @return String
	 */
	function getApplicationLogoText() {
		return self::$application_logo_text;
	}
	function ApplicationLink() {
		return self::$application_link;
	}

	/**
	 * Return the title of the current section, as shown on the main menu
	 */
	function SectionTitle() {
		// Get menu - use obj() to cache it in the same place as the template engine
		$menu = $this->obj('MainMenu');
		
		foreach($menu as $menuItem) {
			if($menuItem->LinkingMode == 'current') return $menuItem->Title;
		}
	}

	/**
	 * The application logo path. Customisable by calling
	 * LeftAndMain::setLogo() - the first parameter.
	 *
	 * @var unknown_type
	 */
	static $application_logo = 'cms/images/mainmenu/logo.gif';

	/**
	 * The application logo style. Customisable by calling
	 * LeftAndMain::setLogo() - the second parameter.
	 *
	 * @var String
	 */
	static $application_logo_style = '';
	
	/**
	 * Set the CMS application logo.
	 *
	 * @param String $logo Relative path to the logo
	 * @param String $logoStyle Custom CSS styles for the logo
	 * 							e.g. "border: 1px solid red; padding: 5px;"
	 */
	static function setLogo($logo, $logoStyle) {
		self::$application_logo = $logo;
		self::$application_logo_style = $logoStyle;
		self::$application_logo_text = '';
	}
	
	protected static $loading_image = 'cms/images/loading.gif';
	
	/**
	 * Set the image shown when the CMS is loading.
	 */
	static function set_loading_image($loadingImage) {
		self::$loading_image = $loadingImage;
	}
	
	function LoadingImage() {
		return self::$loading_image;
	}
	
	function LogoStyle() {
		return "background: url(" . self::$application_logo . ") no-repeat; " . self::$application_logo_style;
	}

	/**
	 * Return the base directory of the tiny_mce codebase
	 */
	function MceRoot() {
		return MCE_ROOT;
	}

	/**
	 * Use this as an action handler for custom CMS buttons.
	 */
	function callPageMethod($data, $form) {
		$methodName = $form->buttonClicked()->extraData();
		$record = $this->currentPage();
		if(!$record) return false;
		
		return $record->$methodName($data, $form);
	}
	
	/**
	 * Register the given javascript file as required in the CMS.
	 * Filenames should be relative to the base, eg, SAPPHIRE_DIR . '/javascript/loader.js'
	 */
	public static function require_javascript($file) {
		self::$extra_requirements['javascript'][] = array($file);
	}
	
	/**
	 * Register the given stylesheet file as required.
	 * 
	 * @param $file String Filenames should be relative to the base, eg, THIRDPARTY_DIR . '/tree/tree.css'
	 * @param $media String Comma-separated list of media-types (e.g. "screen,projector") 
	 * @see http://www.w3.org/TR/REC-CSS2/media.html
	 */
	public static function require_css($file, $media = null) {
		self::$extra_requirements['css'][] = array($file, $media);
	}
	
	/**
	 * Register the given "themeable stylesheet" as required.
	 * Themeable stylesheets have globally unique names, just like templates and PHP files.
	 * Because of this, they can be replaced by similarly named CSS files in the theme directory.
	 * 
	 * @param $name String The identifier of the file.  For example, css/MyFile.css would have the identifier "MyFile"
	 * @param $media String Comma-separated list of media-types (e.g. "screen,projector") 
	 */
	static function require_themed_css($name, $media = null) {
		self::$extra_requirements['themedcss'][] = array($name, $media);
	}
	
}

?>
