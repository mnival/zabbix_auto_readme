# Default Username for Zabbix Connexion
$_default_username = "username"

# Url readme generate
$_url_generation_readme = "https://host/path/generate.php"

function get_url_zabbix {
  param(
    [Parameter(Mandatory=$true)][string]$zabbix
  )
  switch -regex ($zabbix) {
    # List here url of zabbix
    "local"   { "http://127.0.0.1/" }
    "other"   { "https://xx/${zabbix}/" }
    # Do not delete this line
		default {"zabbix parameter is mandatory"; exit 1}
	}
}
