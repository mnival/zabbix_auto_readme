<#
------------------------------------------------------------------------------------
Name of script : get_template_readme.ps1
Object : Generation of readme and template for Zabbix
Usage : .\get_template_readme.sh -zabbix {xx|yy} -templateid {templateid}
------------------------------------------------------------------------------------
#>

# Parameter initialization
param (
  [string]$zabbix = $(throw "-zabbix is required."),
  [string]$git_directory = "NULL",
  [int]$templateid = $(throw "-templateid is required and numerical value.")
)

. "$PSScriptRoot\conf_get_template_readme.ps1"

# Url Zabbix
$_url_zabbix = get_url_zabbix -zabbix ${zabbix}

# Get credential
$_credential = Get-Credential -message "Get User/Password for Zabbix" -username ${_default_username}

# Verification that we have the credential
if ($_credential -eq $null) {
  echo "Please provide user and password for Zabbix"
  exit 2
}

# http://powershell-tips.blogspot.com/2015/06/83-save-file-dialog-with-powershell.html
function save-file {
  param(
    [Parameter(Mandatory=$false)][string]$initialdirectory,
    [Parameter(Mandatory=$false)][string]$default_filename
  )
  [System.Reflection.Assembly]::LoadWithPartialName("System.windows.forms") | Out-Null
  $OpenFileDialog = New-Object System.Windows.Forms.SaveFileDialog
  $OpenFileDialog.initialdirectory = $initialdirectory
  $OpenFileDialog.filename = $default_filename
  $OpenFileDialog.filter = "YAML File (*.yaml)| *.yaml"
  if ($OpenFileDialog.ShowDialog() -eq "OK") {
    return $OpenFileDialog.filename
  }
}

# conversion to JSON
function convert-to-json ($_body) {
  $_body_json = ConvertTo-Json $_body -Depth 5
    return $_body_json
}

# Generation of JSON for user.login
function _get_user.login ($_credential) {
  $_user_login = @{
    jsonrpc = "2.0"
    method = "user.login"
    params = @{
      user = $_credential.Username
      password = $_credential.GetNetworkCredential().Password
    }
    id = 1
    auth = $null
  }
  return $_user_login
}

# Generation of JSON for template.get
function _get_template.get {
  param(
    [string]$auth = $(throw "-auth is required."),
    [int]$templateid = $(throw "-templateid is required.")
  )
  $_template_get = @{
    jsonrpc = "2.0"
    method = "template.get"
    params = @{
      output = @("description")
      templateids = $templateid
    }
    id = 1
    auth = $auth
  }
  return $_template_get
}

# Generation of JSON for template.update
function _get_template.update {
  param(
    [string]$auth = $(throw "-auth is required."),
    [int]$templateid = $(throw "-templateid is required."),
    [string]$value = $(throw "-value is required.")
  )
  $_template_update = @{
    jsonrpc = "2.0"
    method = "template.update"
    params = @{
      templateid = $templateid
      description = $value
    }
    id = 1
    auth = $auth
  }
  return $_template_update
}

# Generation of JSON for configuration.export
function _get_configuration.export {
  param(
    [string]$auth = $(throw "-auth is required."),
    [ValidateSet("json","yaml")][string]$format = $(throw "-format is required."),
    [int]$templateid = $(throw "-templateid is required.")
  )
  
  $_configuration_export = @{
    jsonrpc = "2.0"
    method = "configuration.export"
    params = @{
      options = @{
        templates = @(
          $templateid
        )
    }
      format = $format
    }
    id = 1
    auth = $auth
  }
  return $_configuration_export
}

# Generation of JSON for user.logout
function _get_user.logout {
  param(
    [string]$auth = $(throw "-auth is required.")
  )
  $_user_logout = @{
    jsonrpc = "2.0"
    method = "user.logout"
    params = @()
    id = 1
    auth = $auth
  }
  return $_user_logout
}

# Running the API on the server
function _running_zabbix_api {
  param(
    [string]$body = $(throw "-body is required.")
  )
  $_content = Invoke-RestMethod ("$_url_zabbix/api_jsonrpc.php") -ContentType "application/json" -Body $body -Method Post -ErrorAction Stop
  return $_content
}

# README generation
function _readme_generation {
  param(
    [string]$body = $(throw "-body is required.")
  )
  $_content = Invoke-RestMethod ($_url_generation_readme) -ContentType "application/x-www-form-urlencoded" -Body json=$body -Method Post -ErrorAction Stop
  return $_content
}

# Connection to Zabbix
$_user_login = _get_user.login($_credential)
$_user_login = convert-to-json ($_user_login)
try {
  $zabbix_session=_running_zabbix_api -body $_user_login |
    Select-Object jsonrpc,@{Name="session";Expression={$_.Result}},id
  if ($zabbix_session.session -eq $null) {
    throw
  }
}
catch {
  if ($zabbix_session.session -eq $null) {
    echo "ERROR: maybe bad username or password $_url_zabbix"
  } else {
    echo "ERROR: Exception during login"
  }
  echo $_
  exit 3
}

# Get description
$_template_get = _get_template.get -auth $zabbix_session.session -templateid $templateid
$_template_get = convert-to-json ($_template_get)
try {
  $_template_description = _running_zabbix_api -body $_template_get
  if (!$_template_description.result.templateid) {
    echo "ERROR: The template doesn't exist"
    exit 4
  }
}
catch {
  echo "ERROR: Exception during get description"
  echo $_
  exit 5
}

# Get the template in JSON format
$_configuration_export_json = _get_configuration.export -auth $zabbix_session.session -templateid ${templateid} -format json
$_configuration_export_json = convert-to-json ($_configuration_export_json)
try {
  $_export_json = _running_zabbix_api -body $_configuration_export_json
  if ($_export_json.error) {
    throw
  }
}
catch {
  if ($_export_json.error) {
    echo "Error in JSON :"
    $_export_json.error
  } else {
    echo "ERROR: Exception during export JSON"
  }
  echo $_
  exit 7
}

# Get template name in the JSON
$_template_name = $_export_json.result | ConvertFrom-Json
$_template_name = $_template_name.zabbix_export.templates.name

# Base64 conversion to avoid "transport" problems
$_export_json.result = [System.Text.Encoding]::UTF8.GetBytes($_export_json.result)
$_export_json.result = [Convert]::ToBase64String($_export_json.result)

# README generation
try {
  $_readme = _readme_generation -body $_export_json.result
}
catch {
  echo "ERROR: Exception during generate readme"
  echo $_
  exit 8
}

$_configuration_export_yaml = _get_configuration.export -auth $zabbix_session.session -templateid ${templateid} -format yaml
$_configuration_export_yaml = convert-to-json ($_configuration_export_yaml)
try {
  $_export_yaml = _running_zabbix_api -body $_configuration_export_yaml
  if ($_export_json.error) {
    throw
  }
}
catch {
  if ($_export_json.error) {
    echo "Error in JSON :"
    $_export_json.error
  } else {
    echo "ERROR: Exception during export YAML"
  }
  echo $_
  exit 9
}

# Logout
$_user_logout = _get_user.logout -auth $zabbix_session.session
$_user_logout = convert-to-json ($_user_logout)
try {
  $_logout = _running_zabbix_api -body $_user_logout
}
catch {
  echo "ERROR: Exception during logout"
  echo $_
}

if ($git_directory -ne "NULL") {
  if (-not (Test-Path -Path $git_directory)) {
    $_dir = mkdir $git_directory
  }
  $_file_yaml = save-file -default_filename "$_template_name" -initialdirectory $git_directory
} else {
  $_file_yaml = save-file -default_filename "$_template_name"
}

if ($_file_yaml -eq $null) {
  echo "ERROR: No filename provide"
  exit 10
}
$_file_name = [io.path]::GetFileNameWithoutExtension($_file_yaml)
$_file_path = [io.path]::GetDirectoryName($_file_yaml)
$_file_md = $_file_path + "\" + $_file_name + ".md"

try {
  # Remove BOM in file
  $utf8NoBomEncoding = New-Object System.Text.UTF8Encoding($False)
  $_export_yaml.result -replace "  date: '\d{4}-\d{2}-\d{2}[A-Z]\d{2}:\d{2}:\d{2}[A-Z]'", "" | Out-File -encoding utf8 -FilePath $_file_yaml
  [System.IO.File]::WriteAllLines($_file_yaml, (Get-Content $_file_yaml), $utf8NoBomEncoding)

  # Save README
  $_readme | Out-File -encoding utf8 -FilePath $_file_md
  [System.IO.File]::WriteAllLines($_file_md, (Get-Content $_file_md), $utf8NoBomEncoding) 
  
}
catch {
  echo "ERROR : Exception during save files"
  echo $_
  exit 11
}

echo "Please find path files bellow: "
echo "$_file_yaml"
echo "$_file_md"

exit
