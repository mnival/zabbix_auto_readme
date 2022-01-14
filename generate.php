<?php

//error_reporting(-1);
error_reporting(0);

// check $_POST['json']
if (!isset($_POST['json'])) {
	printf ("ERROR: Need json value\n");
	http_response_code(500);
	die;
}

// base64 decode
$_zabbix_base64 = preg_replace('/ /', '+', $_POST['json']);
$_current_dir = dirname(__FILE__);
file_put_contents("${_current_dir}/last.json", $_zabbix_base64);
if ($_zabbix_base64 = base64_decode($_zabbix_base64, true)) {
	$_zabbix_json = json_decode($_zabbix_base64, true);
} else {
	printf ("ERROR: Base64 decoding error\n");
	http_response_code(500);
	die;
}

// JSON Decode
$_zabbix_json = json_decode($_zabbix_base64, true);

// Pretty JSON
//printf("%s\n", json_encode($_zabbix_json, JSON_PRETTY_PRINT));

// Element Conversion Table
// https://git.zabbix.com/projects/ZT/repos/templator/browse/src/main/java/org/zabbix/template/generator/objects/Type.java
$_type_num_monitor = array(0 => "ZABBIX_PASSIVE", 1 => "SNMPV1", 2 => "TRAP", 3 => "SIMPLE", 4 => "SNMPV2", 5 => "INTERNAL", 6 => "SNMPV3", 7 => "ZABBIX_ACTIVE", 8 => "AGGREGATE", 10 => "EXTERNAL", 11 => "ODBC", 12 => "IPMI", 13 => "SSH", 14 => "TELNET", 15 => "CALCULATED", 16 => "JMX", 17 => "SNMP_TRAP", 18 => "DEPENDENT", 19 => "HTTP_AGENT");
// https://git.zabbix.com/projects/ZT/repos/templator/browse/src/main/java/org/zabbix/template/generator/objects/PreprocessingStepType.java
$_type_num_preprocessing = array(1 => "MULTIPLIER", 2 => "RTRIM", 3 => "LTRIM", 4 => "TRIM", 5 => "REGEX", 6 => "BOOL_TO_DECIMAL", 7 => "OCTAL_TO_DECIMAL", 8 => "HEX_TO_DECIMAL", 9 => "SIMPLE_CHANGE", 10 => "CHANGE_PER_SECOND", 11 => "XMLPATH", 12 => "JSONPATH", 13 => "IN_RANGE", 14 => "MATCHES_REGEX", 15 => "NOT_MATCHES_REGEX", 16 => "CHECK_JSON_ERROR", 17 => "CHECK_XML_ERROR", 18 => "CHECK_REGEX_ERROR", 19 => "DISCARD_UNCHANGED", 20 => "DISCARD_UNCHANGED_HEARTBEAT", 21 => "JAVASCRIPT", 22 => "PROMETHEUS_PATTERN", 23 => "PROMETHEUS_TO_JSON", 24 => "CSV_TO_JSON");
// https://git.zabbix.com/projects/ZT/repos/templator/browse/src/main/java/org/zabbix/template/generator/objects/Trigger.java
$_priority_num_trigger = array(0 => "NOT_CLASSIFIED", 1 => "INFO", 2 => "WARNING", 3 => "AVERAGE", 4 => "HIGH", 5 => "DISASTER");

$_monitor_type = array();

// Functions
// Generate FCAT Resource
function _gen_fcat_ressource ($_value) {
	if (preg_match("/^([\w|\d| ]*):.*/", $_value, $_ressource)) {
		return $_ressource[1];
	} else {
		return "NO_RESSOURCE";
	}
}

// Generate FCAT Instance
function _gen_fcat_instance ($_value) {
	if (preg_match("/.*\[Inst\](.*)\[Inst\].*/i", $_value, $_instance)) {
		return $_instance[1];
	} else {
		return "NO_INSTANCE";
	}
}

// Generate monitor type
function _generate_monitor_type ($_item_type) {
	global $_type_num_monitor;
	if (!isset($_item_type)) {
		$_item_type = 'ZABBIX_PASSIVE';
	} else {
		if (is_numeric($_item_type)) {
			$_item_type = strtr($_item_type, $_type_num_monitor);
		}
	}
	return $_item_type;
}

// Generate item line
function _generate_item_line ($_item_applications, $_item_name, $_item_description, $_item_type, $_item_key, $_item_preprocessing) {
	$_item_line = sprintf ("|%s|%s|%s|%s|%s%s|\n", _generate_application_name($_item_applications), $_item_name, _generate_description($_item_description), _generate_monitor_type($_item_type), _double_backslash($_item_key), _generate_preprocessing_item($_item_preprocessing));
	return $_item_line;
}

// Generate trigger line
function _generate_trigger_line ($_trigger_name, $_trigger_description, $_trigger_expression, $_trigger_priority, $_trigger_manual_close) {
	global $_priority_num_trigger;
	$_trigger_line = sprintf ("|%s|%s|%s|%s|%s|\n", $_trigger_name, _generate_description($_trigger_description), $_trigger_expression, $_trigger_priority, strtr($_trigger_manual_close, array(0 => "", "YES" => "<p>Manual close: YES</p>")));
	return $_trigger_line;
}

// stringify item description
function _generate_description($_description) {
	if (empty($_description)) {
		$_description_stringify = "<p>-</p>";
	} else {
		$_description_stringify = sprintf("<p>%s</p>", preg_replace('/\r\n?/', "<br/>", $_description));
	}
	return $_description_stringify;
}

// stringify preprocessing step
function _generate_preprocessing_item($_preprocessing) {
	global $_type_num_preprocessing;
	if (count($_preprocessing)>0) {
		$_preprocessing_stringify = sprintf ("<p>**Preprocessing**:</p>");
    foreach ($_preprocessing as $_key => $_value) {
      $_parameters_count = 0;
      $_parameters_stringify = "";
      foreach ($_value['parameters'] as $_kp => $_vp) {
        $_parameters_count++;
        $_vp = preg_replace('/\\\\n/', '@@CRLF@@', $_vp);
        $_vp = preg_replace('/\n/', '', $_vp);
        $_vp = preg_replace('/@@CRLF@@/', '\\n', $_vp);
        $_vp = preg_replace('/\|/', '\\\|', $_vp);
        $_parameters_stringify .= sprintf ("param%s: %s", $_parameters_count, $_vp);
      }
			$_preprocessing_stringify .= sprintf ("<p>- %s: `%s`</p>", strtr($_value['type'], $_type_num_preprocessing), $_parameters_stringify);
		}
		return $_preprocessing_stringify;
	}
}

// stringify application name
function _generate_application_name ($_applications) {
	$_applications_stringify = "";
	foreach ($_applications as $k => $v) {
		$_applications_stringify .= sprintf("%s, ", $v['name']);
	}
	$_applications_stringify = preg_replace('/, $/', '', $_applications_stringify);
	return $_applications_stringify;
}

// Protect \
function _double_backslash ($_value_update) {
	return preg_replace('/\\\/', "\\\\\\", $_value_update);
}

// Protect special charaters in MarkDown
function _protect_pipe ($_value) {
	return preg_replace("/\|/", "\|", $_value);
}


// Init varible with data of JSON
$_zabbix_export = $_zabbix_json['zabbix_export'];
// Only one template you can use [0]
$_zabbix_template = $_zabbix_export['templates'][0];
$_template_name = $_zabbix_template['name'];
// Get the description to feed the setup and the configuration and the description of the templates
$_description = preg_replace("/@@CRLF@@@@CRLF@@/", "@@CRLF@@", preg_replace("/[\r\n]/", "@@CRLF@@", $_zabbix_template['description']));
$_template_version = preg_replace("/.*(?:@@CRLF@@)?@@VERSION=([\d+:\-\. ]+).*/", "\\1", $_description);
// Setup
if (preg_match("/@@SETUP.*SETUP@@/", $_description)) {
	$_setup = preg_replace("/(?:@@CRLF@@)?SETUP@@/", "SETUP@@", $_description);
	$_setup = preg_replace("/@@SETUP(?:@@CRLF@@)?/", "@@SETUP", $_setup);
	$_setup = preg_replace("/.*@@SETUP(.*)SETUP@@.*/", "\\1", $_setup);
	$_setup = preg_replace("/@@CRLF@@/", "  \n", $_setup);
	$_setup = sprintf ("%s\n\n", $_setup);
} else {
	$_setup = sprintf ("Please complete information in description of template with this syntax :  \n@@SETUP  \nInformation for setup  \nSETUP@@\n\n");
}
// Configuration
if (preg_match("/@@CONFIGURATION.*CONFIGURATION@@/", $_description)) {
	$_configuration = preg_replace("/(?:@@CRLF@@)?CONFIGURATION@@/", "CONFIGURATION@@", $_description);
	$_configuration = preg_replace("/@@CONFIGURATION(?:@@CRLF@@)?/", "@@CONFIGURATION", $_configuration);
	$_configuration = preg_replace("/.*@@CONFIGURATION(.*)CONFIGURATION@@.*/", "\\1", $_configuration);
	$_configuration = preg_replace("/@@CRLF@@/", "  \n", $_configuration);
	$_configuration = sprintf ("%s\n\n", $_configuration);
} else {
	$_configuration = sprintf ("Please complete information in description of template with this syntax :  \n@@CONFIGURATION  \nInformation for configuration  \nCONFIGURATION@@\n\n");
}
// Macros
if (preg_match("/@@MACROS?.*MACROS?@@/", $_description)) {
	$_macro_description = preg_replace("/(?:@@CRLF@@)?MACROS?@@/", "MACROS@@", $_description);
	$_macro_description = preg_replace("/@@MACROS?(?:@@CRLF@@)?/", "@@MACROS", $_macro_description);
	$_macro_description = preg_replace("/.*@@MACROS(.*)MACROS@@.*/", "\\1", $_macro_description);
	$_macro_descriptions = array();
	foreach (preg_split("/@@CRLF@@/", $_macro_description) as $_value) {
		if (preg_match("/=/", $_value)) {
			$_vk = preg_split("/=/", $_value);
			$_macro_descriptions[$_vk[0]] = _protect_pipe($_vk[1]);
		}
	}
}
// Overview
if (preg_match("/@@OVERVIEW.*OVERVIEW@@/", $_description)) {
	$_overview = preg_replace("/(?:@@CRLF@@)?OVERVIEW@@/", "OVERVIEW@@", $_description);
	$_overview = preg_replace("/@@OVERVIEW(?:@@CRLF@@)?/", "OVERVIEW", $_overview);
	$_overview = preg_replace("/.*@@OVERVIEW(.*)OVERVIEW@@.*/", "\\1", $_overview);
	$_overview = preg_replace("/@@CRLF@@/", "  \n", $_overview);
	$_overview = sprintf ("%s\n\n", $_overview);
} else {
	$_overview = sprintf ("\n");
}

// $_macro_used
if (isset($_zabbix_template['macros']) && count($_zabbix_template['macros'])>0) {
  $_templates_macros = $_zabbix_template['macros'];
	$_macro_used = sprintf ("These macros can be replaced at host or linked model level\n\n");
	$_macro_used .= sprintf ("|Name|Description|Default|\n|----|-----------|-------|\n");
	foreach ($_templates_macros as $_key => $_value) {
		if (!empty($_value['value'])) {
			$_macro_value = sprintf("`%s`", $_value['value']);
		} else {
			$_macro_value = "";
		}
		if (!isset($_value['description'])) {
			if (isset($_macro_descriptions[$_value['macro']])) {
				$_macro_desc = $_macro_descriptions[$_value['macro']];
      } else {
        $_macro_desc = sprintf("Please complete description of the macro (if Zabbix < 4.4 update description with %s=Comment)", $_value['macro']);
      }
		} else {
			$_macro_desc = $_value['description'];
		}
		$_macro_used .= sprintf ("%s|<p>%s</p>|%s|\n", $_value['macro'], $_macro_desc, $_macro_value);
	}
	$_macro_used .= sprintf ("\n");
} else {
	$_macro_used = sprintf ("There are no macro in this template.\n\n");
}

// $_template_links
if (isset($_zabbix_template['templates']) && count($_zabbix_template['templates'])>0) {
	$_template_links = sprintf ("|Name|\n|----|\n");
	foreach ($_zabbix_template['templates'] as $_key => $_value) {
		$_template_links .= sprintf ("|[%s](%s.xml) ([documentation](%s.md))|\n", $_value['name'], $_value['name'], $_value['name']);
	}
	$_template_links .= sprintf ("\n");
} else {
	$_template_links = sprintf ("There are no template links in this template.\n\n");
}

// $_discovery_rules
if (isset($_zabbix_template['discovery_rules']) && count($_zabbix_template['discovery_rules'])>0) {
	$_templates_discovery_rules = $_zabbix_template['discovery_rules'];
	$_templates_discovery_rules_item_prototypes = "";
	$_fcat_discovery_rules = "";
	$_templates_discovery_rules_trigger_prototypes = "";
	$_discovery_rules = sprintf ("|Name|Description|Type|Key and additional info|\n|----|-----------|----|----|\n");
	foreach ($_templates_discovery_rules as $_key => $_value) {
		if (!isset($_value['description'])) {
			$_value['description']="";
		}
    if (!isset($_value['preprocessing'])) {
      $_value['preprocessing']=[];
    }
		$_discovery_rules .= sprintf ("|%s|%s|%s|%s%s|\n", $_value['name'], _generate_description($_value['description']), _generate_monitor_type($_value['type']), $_value['key'], _generate_preprocessing_item($_value['preprocessing']));
		foreach ($_value['item_prototypes'] as $_ik => $_iv) {
			$_monitor_type[$_iv['type']] = _generate_monitor_type($_iv['type']);
			if (!isset($_iv['description'])) {
				$_iv['description']="";
			}
			if (!isset($_iv['preprocessing'])) {
				$_iv['preprocessing']=[];
			}
			$_templates_discovery_rules_item_prototypes .= sprintf ("%s", _generate_item_line($_iv['application_prototypes'], $_iv['name'], $_iv['description'], $_iv['type'], $_iv['key'], $_iv['preprocessing']));
			if (isset($_iv['trigger_prototypes'])) {
				foreach ($_iv['trigger_prototypes'] as $_ike => $_iva) {
					$_fcat_discovery_rules .= sprintf ("|%s|%s|%s|\n", $_iva['name'], _gen_fcat_ressource($_iva['name']), _gen_fcat_instance($_iva['name']));
					if (!isset($_iva['description'])) {
						$_iva['description']="";
					}
					if (!isset($_iva['priority'])) {
						$_iva['priority']="NOT CLASSIFIED";
					}
					$_templates_discovery_rules_trigger_prototypes .= sprintf ("%s", _generate_trigger_line($_iva['name'], $_iva['description'], $_iva['expression'], $_iva['priority'], $_iva['manual_close']));;
				}
			}
		}
		if (isset($_value['trigger_prototypes'])) {
			foreach ($_value['trigger_prototypes'] as $_ike => $_iva) {
				$_fcat_discovery_rules .= sprintf ("|%s|%s|%s|\n", $_iva['name'], _gen_fcat_ressource($_iva['name']), _gen_fcat_instance($_iva['name']));
				if (!isset($_iva['description'])) {
					$_iva['description']="";
				}
				if (!isset($_iva['priority'])) {
					$_iva['priority']="NOT CLASSIFIED";
				}
				$_templates_discovery_rules_trigger_prototypes .= sprintf ("%s", _generate_trigger_line($_iva['name'], $_iva['description'], $_iva['expression'], $_iva['priority'], $_iva['manual_close']));;
			}
		}
	}
	$_discovery_rules .= sprintf ("\n");
} else {
	$_discovery_rules = sprintf ("There are no discovery rules in this template.\n\n");
}

// $_items_collected
if (isset($_zabbix_template['items']) && count($_zabbix_template['items'])>0) {
	$_templates_items = $_zabbix_template['items'];
	$_triggers_in_items = false;
	$_triggers_items = "";
	$_triggers_items_fcat = "";
	$_items_collected = sprintf ("|Group|Name|Description|Type|Key and additional info|\n|-----|----|-----------|----|---------------------|\n");
	foreach ($_templates_items as $_key => $_value) {
		if (!isset($_value['type'])) {
			$_value['type'] = "ZABBIX_PASSIVE";
		}
		$_monitor_type[$_value['type']] = _generate_monitor_type($_value['type']);
		if (!isset($_value['description'])) {
			$_value['description']="";
		}
		if (!isset($_value['preprocessing'])) {
			$_value['preprocessing']=array();
		}
		$_items_collected .= sprintf ("%s", _generate_item_line($_value['applications'], $_value['name'], $_value['description'], $_value['type'], $_value['key'], $_value['preprocessing']));
		if (isset($_value['triggers']) && count($_value['triggers'])>0) {
			$_triggers_in_items = true;
			foreach ($_value['triggers'] as $_ik => $_iv) {
				$_triggers_items_fcat .= sprintf ("|%s|%s|%s|\n", $_iv['name'], _gen_fcat_ressource($_iv['name']), _gen_fcat_instance($_iv['name']));
				if (!isset($_iv['description'])) {
					$_iv['description']="";
				}
				if (!isset($_iv['priority'])) {
					$_iv['priority']="NOT CLASSIFIED";
				}
				$_triggers_items .= sprintf ("%s", _generate_trigger_line($_iv['name'], $_iv['description'], $_iv['expression'], $_iv['priority'], $_iv['manual_close']));;
			}
		}

	}
	if (!empty($_templates_discovery_rules_item_prototypes)) {
		$_items_collected .= sprintf ("%s", $_templates_discovery_rules_item_prototypes);
	}
	$_items_collected .= sprintf ("\n");
} else {
	$_items_collected = sprintf ("There are no items in this template.\n\n");
}

// $_triggers
if (isset($_zabbix_export['triggers']) || !empty($_templates_discovery_rules_trigger_prototypes) || isset($_triggers_in_items)) {
	$_triggers = sprintf ("|Name|Description|Expression|Severity|Dependencies and additional info|\n|----|-----------|----|----|----|\n");
	$_fcats = sprintf ("|Trigger Name|Ressource|Instance|\n|--------|----|----|\n");
	if ($_triggers_in_items) {
		$_triggers .= sprintf ("%s", $_triggers_items);
		$_fcats .= sprintf ("%s", $_triggers_items_fcat);
	}
	if (isset($_zabbix_export['triggers'])) {
		foreach ($_zabbix_export['triggers'] as $_key => $_value) {
			$_fcats .= sprintf ("|%s|%s|%s|\n", $_value['name'], _gen_fcat_ressource($_value['name']), _gen_fcat_instance($_value['name']));
			if (!isset($_value['description'])) {
				$_value['description'] = "";
			}
			$_triggers .= sprintf ("%s", _generate_trigger_line($_value['name'], $_value['description'], $_value['expression'], $_value['priority'], $_value['manual_close']));
		}
	}
	if (!empty($_templates_discovery_rules_trigger_prototypes)) {
		$_triggers .= sprintf ("%s", $_templates_discovery_rules_trigger_prototypes);
		$_fcats .= sprintf ("%s", $_fcat_discovery_rules);
	}
	$_triggers .= sprintf ("\n");
	$_fcats .= sprintf ("\n");
} else {
	$_fcats = sprintf ("There are no FCAT in this template because there are no trigger.\n\n");
	$_triggers = sprintf ("There are no trigger in this template.\n\n");
}

// Generation of the readme
printf ("# Template : %s  \n", $_template_name);

printf ("## Overview\n\n");
printf ("Template version: %s  \n", $_template_version);
printf ("For Zabbix version: %s  \n", $_zabbix_export['version']);
printf ("%s", $_overview);
printf ("### Items type\n\n");
foreach($_monitor_type as $_value) {
	printf ("* %s\n", $_value);
}

printf ("\n\n");

printf ("## Setup\n\n");
printf ("%s", $_setup);

printf ("## Zabbix configuration\n\n");
printf ("%s", $_configuration);

printf ("### Macros used\n\n");
printf ("%s", $_macro_used);

printf ("## Template links\n\n");
printf ("%s", $_template_links);

printf ("## Discovery rules\n\n");
printf ("%s", $_discovery_rules);

printf ("## Items collected\n\n");
printf ("%s", $_items_collected);

printf ("## Triggers\n\n");
printf ("%s", $_triggers);

printf ("## FCAT\n\n");
printf ("%s", $_fcats);
?>
