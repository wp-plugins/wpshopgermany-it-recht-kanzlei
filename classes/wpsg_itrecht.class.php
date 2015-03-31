<?php

	class wpsg_itrecht
	{
		
		var	$SC = false;
		var $version = '1.9';
		
		var $api_version = '1.0';
		
		public function __construct($SC = false)
		{
				
			$this->SC = $SC;
						
		} // public function __construct($SC = false)
		
		// Shortcodes
		public function sc_wpsg_itrecht_agb($atts) { return $this->get_option('wpsgitrecht_html_agb'); }
		public function sc_wpsg_itrecht_datenschutz($atts) { return $this->get_option('wpsgitrecht_html_datenschutz'); }
		public function sc_wpsg_itrecht_widerruf($atts) { return $this->get_option('wpsgitrecht_html_widerruf'); }
		public function sc_wpsg_itrecht_impressum($atts) { return $this->get_option('wpsgitrecht_html_impressum'); } 
		
		function dispatch()
		{
			
			if (isset($_REQUEST['wpsgitrecht_submit']))
			{
					
				$this->saveForm();
					
			}
						
			echo $this->render('fullform.phtml');
			
		} // function dispatch()
		
		function admin_menu()
		{
		
			if (!is_plugin_active('wpshopgermany/wpshopgermany.php'))
			{
					
				add_submenu_page('options-general.php', 'wpShopGermany - IT-Recht Kanzlei M�nchen',  'IT-Recht Kanzlei M�nchen', 'administrator', 'wpshopgermany-itrecht-Admin', array($this, 'dispatch'));
		
			}
		
		} // function admin_menu()
		
		function wp_loaded()
		{
			
			global $wpdb;
			
			if (wpsgitrecht_isSizedString($_REQUEST['wpsgitrecht_action'], 'genKey') && is_admin())
			{
				
				die($this->getNewApiKey());
				
			}
			if (wpsgitrecht_isSizedString($_REQUEST['wpsgitrecht_action'], 'api') && wpsgitrecht_isSizedString($_REQUEST['xml']))
			{
								
				$xml = simplexml_load_string(stripslashes($_REQUEST['xml']));

				$returnCode = false;
				
				if ($xml === false) { $returnCode = '12'; }
				else 
				{
				
					$request = array(
						'api_version' => strval($xml->api_version),
						'user_auth_token' => strval($xml->user_auth_token),
						'rechtstext_type' => strval($xml->rechtstext_type),
						'rechtstext_text' => strval($xml->rechtstext_text),
						'rechtstext_html' => strval($xml->rechtstext_html),
						'rechtstext_pdf_url' => strval($xml->rechtstext_pdf_url),
						'rechtstext_pdf_md5hash' => strval($xml->rechtstext_pdf_md5hash),
						'rechtstext_language' => strval($xml->rechtstext_language),
						'action' => strval($xml->action)								
					);
					
					$arPageTypes = $this->getPageTypes();
					
					// error 1 - Schnittstellen-Version (Versionsnummer api_version) ist unterschiedlich
					if ($request['api_version'] != $this->api_version) { $returnCode = '1'; } 
					else if ($request['user_auth_token'] != $this->getAPIKey()) { $returnCode = '3'; }
					else if (!in_array($request['rechtstext_type'], array_keys($arPageTypes))) { $returnCode = '4'; }
					else if (!wpsgitrecht_isSizedString($request['rechtstext_text']) || strlen($request['rechtstext_text']) < 50) { $returnCode = '5'; }
					else if (!wpsgitrecht_isSizedString($request['rechtstext_html']) || strlen($request['rechtstext_html']) < 50) { $returnCode = '6'; }
					else if (!wpsgitrecht_isSizedString($request['rechtstext_language']) || ($request['rechtstext_language'] != 'de')) { $returnCode = '9'; }
					else if (!in_array($request['action'], array('push'))) { $returnCode = '10'; }
					else if ($arPageTypes[$request['rechtstext_type']]['needPDF'] === true && trim($request['rechtstext_pdf_url']) === '') { $returnCode = '7'; }			
					else if ($arPageTypes[$request['rechtstext_type']]['needPDF'] === true && md5(file_get_contents($request['rechtstext_pdf_url'])) != $request['rechtstext_pdf_md5hash']) { $returnCode = '8'; }		 
					else 
					{
						
						$arPage = $this->getPageTypes();
						$arPageConfig = $arPage[$request['rechtstext_type']];
						
						// Inhalt verarbeiten
						$this->UpdateQuery($wpdb->prefix."posts", array(
							"post_content" => $this->q($request['rechtstext_html'])
						), "`ID` = '".$this->q($arPageConfig['set'])."'");
						
						$this->update_option('wpsgitrecht_html_'.$request['rechtstext_type'], $request['rechtstext_html']);
						$this->update_option('wpsgitrecht_lastupdate_'.$request['rechtstext_type'], time());
						
						$returnCode = "success";
						
					}
					
				} 
				 
				// error 11
				// Wert für user_account_id wird benötigt (Multishop-System), ist aber leer oder nicht gültig oder passt nicht zur Kombination user_username/user_password bzw. zu user_auth_token
				
				$doc = new DOMDocument('1.0', 'utf-8');

				$node_response = $doc->createElement("response");
				
				if ($returnCode === "success")
				{
				
					$node_status = $doc->createElement("status");
					$node_status->appendChild($doc->createTextNode($returnCode));
 
				}
				else
				{
					
					$node_status = $doc->createElement("status");
					$node_status->appendChild($doc->createTextNode('error'));
					
					$node_error = $doc->createElement('error');
					$node_error->appendChild($doc->createTextNode($returnCode));
					
					$node_response->appendChild($node_error);
					
				}
				
				// ModulVersion
				$modul_data = get_plugin_data(dirname(__FILE__).'/../wpshopgermany-itrecht.php');
				
				$node_module_version = $doc->createElement('meta_modulversion');
				$node_module_version->appendChild($doc->createTextNode($modul_data['Version']));
				
				$node_response->appendChild($node_module_version);
				
				if (function_exists('is_plugin_active') && is_plugin_active('wpshopgermany/wpshopgermany.php'))
				{
					
					$shop_data = get_plugin_data(dirname(__FILE__).'/../../wpshopgermany/wpshopgermany.php');

					$node_shop_version = $doc->createElement('meta_shopversion');
					$node_shop_version->appendChild($doc->createTextNode($shop_data['Version']));
					
					$node_response->appendChild($node_shop_version);
					
				}				
				
				$node_response->appendChild($node_status);
				 
				$doc->appendChild($node_response);
				
				header('Content-Type: application/xml; charset=utf-8'); die($doc->saveXML());
				
			}
			
		} // function wp_loaded()
		
		public function getPageTypes()
		{
			
			$arPageTypes = array(
				'agb' => array(
					'label' => __('Allgemeine Geschäftsbedingungen', 'wpsgitrecht'),
					'shop_page_option' => 'wpsg_page_agb',
					'needPDF' => true
				),	
				'datenschutz' => array(
					'label' => __('Datenschutzerklärung', 'wpsgitrecht'),
					'shop_page_option' => 'wpsg_page_datenschutz',
					'needPDF' => true
				),
				'widerruf' => array(
					'label' => __('Widerrufsbelehrung', 'wpsgitrecht'),
					'shop_page_option' => 'wpsg_page_widerrufsbelehrung',
					'needPDF' => true
				),
				'impressum' => array(
					'label' => __('Impressum', 'wpsgitrecht'),
					'shop_page_option' => 'wpsg_page_impressum'
				)
			);
			
			// Werte auslesen
			foreach ($arPageTypes as $page_key => $page)
			{
				
				if ($this->get_option('wpsgitrecht_lastupdate_'.$page_key) !== false) $arPageTypes[$page_key]['last_update'] = $this->get_option('wpsgitrecht_lastupdate_'.$page_key);
				else $arPageTypes[$page_key]['last_update'] = 0;
				
				$set = false;
				
				if ($this->get_option('wpsgitrecht_page_'.$page_key) !== false) $set = $this->get_option('wpsgitrecht_page_'.$page_key);
				else
				{

					// Eventuell Seite aus Shop
					if (function_exists('is_plugin_active') && is_plugin_active('wpshopgermany/wpshopgermany.php') && $this->get_option($page['shop_page_option']) !== false)
					{

						$set = $this->get_option($page['shop_page_option']);
						$this->update_option('wpsgitrecht_page_'.$page_key, $set);
						
					}
					
				}
				
				$arPageTypes[$page_key]['set'] = $set;
				
			}
									
			return $arPageTypes;
			
		}
		
		public function getPages()
		{
			
			$pages = get_pages();
			
			$arPages = array();
			
			foreach ($pages as $k => $v)
			{
				$arPages[$v->ID] = $v->post_title.' (ID:'.$v->ID.')';
			}
				
			return $arPages;
			
		} // public function getPages()
		
		public function showForm()
		{
			
			return $this->render('form.phtml');
			
		} // public function showForm()
		
		public function saveForm()
		{
			 
			global $wpdb;
			
			$this->update_option('wpsgitrecht_apiToken', $_REQUEST['wpsgitrecht_apiToken']);
			
			foreach ($this->getPageTypes() as $page_key => $page)
			{
				
				if ($_REQUEST['ContentPage'][$page_key] > 0)
				{

					$this->update_option('wpsgitrecht_page_'.$page_key, $_REQUEST['ContentPage'][$page_key]);
					
				}
				else if ($_REQUEST['ContentPage'][$page_key] == '-1') 
				{
					
					// Seite anlegen
					global $wpdb;
					
					$user_id = 0; if (function_exists("get_currentuserinfo")) { get_currentuserinfo(); $user_id = $current_user->user_ID; }					
					if ($user_id == 0 && function_exists("get_current_user_id")) { $user_id = get_current_user_id(); }
					
					$data = array(
						"post_author" => $user_id,
						"post_date" => "NOW()",
						"post_title" => $page['label'],
						"post_date_gmt" => "NOW()",
						"post_name" => strtolower($page['label']),
						"post_status" => "publish",
						"comment_status" => "closed",
						"ping_status" => "neue-seite",
						"post_type" => "page",
						"post_content" => '',
						"ping_status" => "closed",
						"comment_status" => "closed",
						"post_excerpt" => "",
						"to_ping" => "",
						"pinged" => "",
						"post_content_filtered" => ""
					);
					
					$page_id = $this->ImportQuery($wpdb->prefix."posts", $data);
					
					$this->UpdateQuery($wpdb->prefix."posts", array(
						"post_name" => $this->clear($title, $page_id)
					), "`ID` = '".$this->q($page_id)."'");
					
					$this->update_option('wpsgitrecht_page_'.$page_key, $page_id);
					
				}
				
			}
			
			$this->addBackendMessage(__('Einstellungen erfolgreich gespeichert.', 'wpsgitrecht'));
			
		} // public function saveForm()
		
		public function getNewApiKey()
		{
			
			$new_code = substr(str_shuffle(base64_encode(rand(1, 500).time().$_SERVER['REQUEST_URI'].rand(1, 500))), 0, 42);
			
			return $new_code;
			
		}
		
		public function getAPIKey()
		{
		
			if (wpsgitrecht_isSizedString($this->get_option('wpsgitrecht_apiToken'))) return $this->get_option('wpsgitrecht_apiToken');
			else 
			{
				
				$new_code = $this->getNewApiKey();
				
				$this->update_option('wpsgitrecht_apiToken', $new_code);
				
				return $new_code;				
				
			}
			
		}
		
		public function getAPIUrl()
		{
			
			$home_url = home_url();
			
			if (strpos($home_url, '?') === false) $home_url .= '?wpsgitrecht_action=api';
			else $home_url .= '&wpsgitrecht_action=api';
			
			return $home_url;
			
		} // public function getAPIUrl()
		
		private function render($file)
		{
			
			ob_start();
			include dirname(__FILE__).'/../views/'.$file;
			$content = ob_get_contents();
			ob_end_clean();
			
			return $content;
			
		} // private function render($file)
		
		public function get_option($key)
		{
				
			return get_option($key);
				
		}
		
		public function update_option($key, $value)
		{
				
			update_option($key, $value);
				
		}
		 
		/**
		 * Fügt eine Hinweismeldung eines Backend Moduls hinzu
		 * Wird mittels writeBackendMessage ausgegeben
		 */
		public function addBackendMessage($message)
		{
		
			if (isset($_REQUEST['wpsg_mod_legaltexts_submitform'])) $GLOBALS['wpsg_sc']->addBackendMessage($message);
				
			if (!in_array($message, (array)$_SESSION['wpsgitrecht']['backendMessage'])) $_SESSION['wpsgitrecht']['backendMessage'][] = $message;
		
		} // public function addBackendMessage($message)
		
		/**
		 * Fügt eine neue Fehlermeldung eines Backend Moduls hinzu
		 */
		public function addBackendError($message)
		{
		
			if (isset($_REQUEST['wpsg_mod_legaltexts_submitform'])) $GLOBALS['wpsg_sc']->addBackendError($message);
				
			if (!in_array($message, (array)$_SESSION['wpsgitrecht']['backendError'])) $_SESSION['wpsgitrecht']['backendError'][] = $message;
		
		} // public function addBackendError($message)
		
		public function writeBackendMessage()
		{
		
			$strOut  = '';
		
			if (!isset($_SESSION['wpsgitrecht']['backendMessage']) && !isset($_SESSION['wpsgitrecht']['backendError'])) return;
		
			if (is_array($_SESSION['wpsgitrecht']['backendMessage']) && sizeof($_SESSION['wpsgitrecht']['backendMessage']) > 0)
			{
		
				$strOut  .= '<div id="wpsgitrecht_message" class="updated">';
		
				foreach ($_SESSION['wpsgitrecht']['backendMessage'] as $m)
				{
		
					$strOut .= '<p>'.$m.'</p>';
						
				}
		
				$strOut .= '</div>';
		
				unset($_SESSION['wpsgitrecht']['backendMessage']);
		
			}
		
			if (wpsgitrecht_isSizedArray($_SESSION['wpsgitrecht']['backendError']))
			{
		
				$strOut  .= '<div id="wpsgitrecht_message" class="error">';
		
				foreach ($_SESSION['wpsgitrecht']['backendError'] as $m)
				{
		
					$strOut .= '<p>'.$m.'</p>';
		
				}
		
				$strOut .= '</div>';
		
				unset($_SESSION['wpsgitrecht']['backendError']);
		
			}
		
			return $strOut;
		
		} // public function writeBackendMessage()
		
		/**
		 * Importiert die Daten aus $data als neue Zeile in die Tabelle $table
		 * $data muss dabei aus einem Schlüssel/Wert Array bestehen
		 * Der Rückgabewert ist die ID des eingefügten Datensatzes
		 */
		function ImportQuery($table, $data, $checkCols = false)
		{
				
			global $wpdb;
				
			/**
			 * Wenn diese Option aktiv ist, so werden Spalten nur importiert
			 * wenn sie auch in der Zieltabelle existieren.
			 */
			if ($checkCols === true)
			{
		
				$arFields = $this->fetchAssoc("SHOW COLUMNS FROM `".$this->q($table)."` ");
		
				$arCols = array();
				foreach ($arFields as $f) { $arCols[] = $f['Field']; }
				foreach ($data as $k => $v) { if (!in_array($k, $arCols)) { unset($data[$k]); } }
		
			}
				
			if (!wpsgitrecht_isSizedArray($data)) return false;
				
			// Query zusammenbauen
			$strQuery = "INSERT INTO `".$this->q($table)."` SET ";
				
			foreach ($data as $k => $v)
			{
		
				if ($v != "NOW()" && $v != "NULL" && !is_array($v))
					$v = "'".$v."'";
				else if (is_array($v))
					$v = $v[0];
					
				$strQuery .= "`".$k."` = ".$v.", ";
		
			}
				
			$strQuery = substr($strQuery, 0, -2);
				
			$res = $wpdb->query($strQuery);
		
			return $wpdb->insert_id;
			
		} // function ImportQuery($table, $data)
		
		/**
		 * Gibt eine einzelne Zelle aus der Datenbank zurück
		 */
		function fetchOne($strQuery)
		{
				
			global $wpdb;
		
			$result = $wpdb->get_var($strQuery);
		
			return $result;
				
		} // function fetchOne($strQuery)
		
		/**
		 * Aktualisiert Zeilen in der Datenbank anhand des $where Selectse
		 */
		function UpdateQuery($table, $data, $where)
		{
				
			global $wpdb;
				
			// Query aufbauen, da wir den kompletten QueryWHERE String als String übergeben
			$strQuery = "UPDATE `".$this->q($table)."` SET ";
				
			foreach ($data as $k => $v)
			{
		
				if ($v != "NOW()" && $v != "NULL" && !is_array($v))
					$v = "'".$v."'";
				else if (is_array($v))
					$v = $v[0];
					
				$strQuery .= "`".$k."` = ".$v.", ";
		
			}
				
			$strQuery = substr($strQuery, 0, -2)." WHERE ".$where;
				
			$res = $wpdb->query($strQuery);
						
			return $res;
				
		} // function UpdateQuery($table, $data, $where)
		
		function q($value)
		{
			 
			if (is_array($value))
			{
				
				foreach ($value as $k => $v)
				{
					
					$value[$k] = $this->q($v);
					
				}
				
				return $value;
				
			}
			else
			{
							
				return esc_sql($value);
				
			}
			
		} // function q($value)
		
		/**
		 * Bereinigt den URL Key bzw. das Path Segment
		 * Ist der Parameter post_id angegeben, so wird überprüft das kein Post ungleich dieser ID mit diesem Segment existiert
		 */
		public function clear($value, $post_id = false)
		{
				
			global $wpdb;
				
			$arReplace = array(
				'/Ö/' => 'Oe', '/ö/' => 'oe',
				'/Ü/' => 'Ue', '/ü/' => 'ue',
				'/Ä/' => 'Ae', '/ä/' => 'ae',
				'/ß/' => 'ss', '/\040/' => '-',
				'/\€/' => 'EURO',
				'/\//' => '_',
				'/\[/' => '',
				'/\]/' => '',
				'/\|/' => ''
			);
				
			$strReturn = preg_replace(array_keys($arReplace), array_values($arReplace), $value);
			$strReturn = sanitize_title($strReturn);
		
			if (is_numeric($post_id) && $post_id > 0)
			{
		
				$n = 0;
		
				while (true)
				{
						
					$n ++;
						
					$nPostsSame = $this->fetchOne("SELECT COUNT(*) FROM `".$wpdb->prefix."posts` WHERE `post_name` = '".$this->q($strReturn)."' AND `id` != '".$this->q($post_id)."'");
						
					if ($nPostsSame > 0)
					{
		
						$strReturn .= $n;
		
					}
					else
					{
		
						break;
		
					}
						
				}
		
			}
				
			return $strReturn;
				
		} // private function clear($value)
		
	} // class wpsg_itrecht

?>