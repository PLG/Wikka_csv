<?php
if (!defined('PATTERN_VAR')) define('PATTERN_VAR', '[a-zA-Z_]\w*');

global $wakka;

if (is_array($vars)) {
	//foreach ($vars as $param => $value) {}

	$pagevar_key= $vars['key'];
	if (preg_match('/^'.PATTERN_VAR.'$/', $pagevar_key))
	{
		$pagevar_value= $vars['value'];
		if (!isset($pagevar_value))
			print $this->GetPageVariable($pagevar_key, $vars['default']);

		// from Wakka.class::Action(...)
		// The parameter value is sanitized using htmlspecialchars_ent();
		// It is still the responsibility of each action to validate its own parameters!
		// 
		//TODO: additional checks to make sure certain patterns are escaped?
		elseif (preg_match('/^[\.\w\s~¥£&%,*\/#\\\'"()\[\]+=-]*$/', $pagevar_value))
			$this->SetPageVariable($pagevar_key, $pagevar_value);

		return;
	}

	$pagevar_key= $vars['delete'];
	if (preg_match('/^'.PATTERN_VAR.'$/', $pagevar_key))
	{
		$this->SetPageVariable($pagevar_key, null);
		return;
	}

	print 'ERR[ \''. $pagevar_key .'\' ]';
}
?>

