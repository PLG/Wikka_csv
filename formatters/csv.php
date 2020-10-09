<?php
// convert inline csv data into a table.
// by OnegWR, May 2005, license GPL http://wikkawiki.org/OnegWRCsv
// by ThePLG, Apr 2020, license GPL http://wikkawiki.org/PLG-Csv

// https://blog.teamtreehouse.com/how-to-debug-in-php
// ini_set('display_errors', 'On');
// error_reporting(E_ALL | E_STRICT);

//---------------------------------------------------------------------------------------------------------------------

// https://www.phpliveregex.com
// https://www.regular-expressions.info/quickstart.html

if (!defined('PATTERN_ARGUMENT'))			define('PATTERN_ARGUMENT', '(?:;([^;\)\x01-\x1f\*\?"<>\|]*))?');
if (!defined('PATTERN_SPILL_GROUP'))		define('PATTERN_SPILL_GROUP', '([^\)]*)');
if (!defined('PATTERN_NO_ESC'))				define('PATTERN_NO_ESC', '(?<!\\\)');
if (!defined('PATTERN_NUMBER_FORMAT'))		define('PATTERN_NUMBER_FORMAT', '#(?:,##)?([.,\'\s])#{3}(?:([.,\'])(#+|#~))?');
if (!defined('PATTERN_CURRENCY_FORMAT'))	define('PATTERN_CURRENCY_FORMAT', '\'((?:USD|SEK)(?:,\s*(?:USD|SEK))*)\'');
if (!defined('PATTERN_CSS_IDENTIFIER'))		define('PATTERN_CSS_IDENTIFIER', '-?[_a-zA-Z]+[_a-zA-Z0-9-]*');
if (!defined('PATTERN_CSS_DECLARATION'))	define('PATTERN_CSS_DECLARATION', '(?:a|table|t[hrd])?(?:[:\.#]'.PATTERN_CSS_IDENTIFIER.')*');
if (!defined('PATTERN_CSS_RULE'))			define('PATTERN_CSS_RULE', '('.PATTERN_CSS_DECLARATION.'(?:,\s*'.PATTERN_CSS_DECLARATION.')*)\s*(\{.*\})');
if (!defined('PATTERN_VAR'))				define('PATTERN_VAR', '[a-zA-Z_]\w*');
if (!defined('PATTERN_JQUERY_VAR'))			define('PATTERN_JQUERY_VAR', '\$\(\'#('.PATTERN_CSS_IDENTIFIER.')\s*('.PATTERN_VAR.')\'\)');
if (!defined('CSS_ID_DELIM'))				define('CSS_ID_DELIM', '-');
if (!defined('PATTERN_XL_ID'))				define('PATTERN_XL_ID', '[A-Z]+[\d]+');

$pagevars= $this->pagevars;
// $pagevar_value= $this->GetPageVariable($pagevar_key, ''); // $GLOBALS['wakka']->GetPageVariable(...)
$replace_pagevars= function($text) use (&$pagevars)
{
	foreach ($pagevars as $key => $value)
		$text= preg_replace('/'.PATTERN_NO_ESC.'\{\$'. $key .'\}/', $value, $text);

	return $text;
};

if (preg_match('/^'.PATTERN_ARGUMENT.PATTERN_ARGUMENT.PATTERN_ARGUMENT.PATTERN_ARGUMENT.PATTERN_SPILL_GROUP.'$/su', ';'.$format_option, $args))
	list(, $arg1, $arg2, $arg3, $arg4, $invalid) = $args;

$DELIM= ($arg1 == 'semi-colon') ? ';' : ',';
$arg2= $replace_pagevars($arg2);
$arg3= $replace_pagevars($arg3);

$rndw= function ($length=4) {
	return substr(str_shuffle("qwertyuiopasdfghjklzxcvbnm"),0,$length);
};

$ID_TABLE= $rndw(23);
$arg2= preg_replace('/^(.*)\..*$/', '\1', $arg2); // should be .csv, but remove any extension
if (preg_match('/^'.PATTERN_CSS_IDENTIFIER.'$/', $arg2, $a_table_id))
	$ID_TABLE= $a_table_id[0];

// https://www.rexegg.com/regex-lookarounds.html
// asserts what precedes the ; is not a backslash \\\\, doesn't account for \\; (escaped backslash semicolon)
// OMFG! https://stackoverflow.com/questions/40479546/how-to-split-on-white-spaces-not-between-quotes
//
$PATTERN_NO_SPLIT_QUOTED_DELIM= PATTERN_NO_ESC . $DELIM .'(?=(?:[^"]*(["])[^"]*\1)*[^"]*$)';
$PATTERN_ESC_DELIM='\\\\'. $DELIM .'';

$ARRAY_CODE_LINES= preg_split("/[\n]/", $text);
foreach ($ARRAY_CODE_LINES as $row => $csv_line) 
	$ARRAY_CODE_LINES[$row]= $replace_pagevars($csv_line);

$comments= 0;

//---------------------------------------------------------------------------------------------------------------------

$number_formats['standard']= '#,###.#~';
$number_formats['european']= '#.###,#~';

// https://www.thefinancials.com/Default.aspx?SubSectionID=curformat
// [#,###.###]BHD [#,###.##] [#.###,##] [# ###.##]AUD [#,##,###.##]INR [#.###]CLP [#,###]JPY [# ###]LBP
$number_formats['USD']= '#,###.##';
$number_formats['SEK']= '#.###,##';

$parse_number_format= function($format) use (&$number_formats)
{
	preg_match('/^'.PATTERN_NUMBER_FORMAT.'$/', $number_formats[$format], $a_separators);
	return $a_separators;
};


$selected_formats= array('standard');
if (preg_match('/^'.PATTERN_CURRENCY_FORMAT.'$/', $arg3, $a_selected))
	$selected_formats= explode(',', $a_selected[1]);

//TODO: what format do we output? use the first specified format for now
$js_toFixed= function($nr) use (&$parse_number_format, &$selected_formats)
{
	list(, $grouping, $decimal, $places)= $parse_number_format( trim($selected_formats[0]) );

	if (!strcmp($places, '#~'))
		return $nr;
	return 'Number('. $nr .').toFixed('.strlen($places).')';
};

// https://www.php.net/manual/en/functions.anonymous.php
//if (!function_exists('parse_number')) { } // doesn't see global scope variables, support 'static'
$parse_number= function ($cell) use (&$parse_number_format, &$selected_formats) 
{
	foreach ($selected_formats as $format)
	{
		list(, $grouping, $decimal, $places)= $parse_number_format( trim($format) );
		
		if ($grouping == '.') $grouping= '\.';
		if ($decimal  == '.') $decimal= '\.';

		if (!strcmp($places, '#~'))
			$pattern_number= '([+-]?)\s*(\d{1,3}(?:'. $grouping .'\d{3})*|(?:\d+))(?:'. $decimal .'(\d+))?';
		else
			$pattern_number= '([+-]?)\s*(\d{1,3}(?:'. $grouping .'\d{3})*|(?:\d+))(?:'. $decimal .'(\d{'. strlen($places) .'}))?';

		if (preg_match('/^'.$pattern_number.'$/', $cell, $a_currency))
		{
			$cell= $a_currency[1] . preg_replace('/'.$grouping.'/', '', $a_currency[2]);
			if ( isset($a_currency[3]) )
				$cell.= '.'. $a_currency[3];

			return array(true, floatval($cell), $format);
		}
	}

	return array(false, $cell, 'ERR');
};

//---------------------------------------------------------------------------------------------------------------------

$replace_jquery_var= function ($name)
{
	if (preg_match('/'.PATTERN_JQUERY_VAR.'/', $name, $a_vars))
	{
		$var= '$'. preg_replace('/[-]/', '_', $a_vars[1]) . '_' . $a_vars[2];
		$selector= $a_vars[1] . CSS_ID_DELIM . $a_vars[2];

		return array(true, $selector, $var);
	}

	return array(false, '', $name);
};

// https://stackoverflow.com/questions/3302857/algorithm-to-get-the-excel-like-column-name-of-a-number
$spreadsheet_baseZ= function ($n) {
    for($r = ""; $n >= 0; $n = intval($n / 26) - 1)
        $r = chr($n%26 + 0x41) . $r;
    return $r;
};

// https://stackoverflow.com/questions/1028248/how-to-combine-class-and-id-in-css-selector
//$css['table, th, td']= '{ border: 1px solid black; border-collapse: collapse; }';
$css['th, td']= '{ padding: 1px 10px 1px 10px; }';
$css['th']= '{ background-color:#ccc; }'; 
$css['tr.even']= '{ background-color:#ffe; }';
$css['tr.odd']= '{ background-color:#eee; }';
//$css['.warning']= '{ background-color:#f00; }';
$css['.warning']= '{ background-color:#fcc; border: 2px solid red; border-collapse: collapse; }';
$css['.total']= '{ border: 1px solid black; border-collapse: collapse; }';
$css['a:link']= '{ color: blue; }';
$css['a:visited']= '{ color: blue; }';

foreach ($ARRAY_CODE_LINES as $row => $csv_line) 
{
	if ( preg_match('/^#!\s*'.PATTERN_CSS_RULE.'$/', $csv_line, $a_css) )
	{
		$css[ $a_css[1] ]= $a_css[2];

		unset($ARRAY_CODE_LINES[$row]);
		$comments++;
		continue;
	}

	if (preg_match('/^#/',$csv_line))
		print 'ERROR: invalid CSS directive: \''. $csv_line .'\'' ."\n";

	break;
}

print "<style>\n";
foreach ($css as $key => $rule) 
{
	foreach (explode(',', $key) as $tag) 
		$key= preg_replace('/'.trim($tag).'/', '#'.$ID_TABLE.' '.trim($tag), $key);
	print $key .' '. $rule ."\n";
}
print "</style>\n";

//---------------------------------------------------------------------------------------------------------------------

//$TD_ws= "\n\t"; 
$TD_ws= ''; 
$js_script= '';

print '<table id="'. $ID_TABLE .'">'. "\n";
// https://www.thoughtco.com/and-in-javascript-2037515
$js_script.= 'function $(x) { return document.getElementById(x); }; var tcol={}; var trow={}; '. "\n";
foreach ($ARRAY_CODE_LINES as $csv_row => $csv_line) 
{
	if (preg_match('/^#js!/', $csv_line, $js_line))
		break;

	unset($ARRAY_CODE_LINES[$csv_row]);

	if (preg_match('/^#/',$csv_line)) {
		$comments++;
		continue;
	}

	$row= $csv_row - $comments;

	print ($row %2) ? '<tr class="even" >' : '<tr class="odd" >';

	foreach (preg_split('/'. $PATTERN_NO_SPLIT_QUOTED_DELIM .'/', $csv_line) as $col => $csv_cell)
	{
		$xl_id= $spreadsheet_baseZ($col) . $row;
		$id= $ID_TABLE . CSS_ID_DELIM . $xl_id;

		$cell_style='';
		$quotes= '';

		// extract the cell out of it's quotes
		//
        if (preg_match('/^\s*("?)(.*?)\1\s*$/', $csv_cell, $matches))
		{
			$quotes= $matches[1];
			$cell= $matches[2];

			if ($quotes == '"')
				$cell_style.= 'white-space:pre; ';
		}

		//-------------------------------------------------------------------------------------------------------------
		// header

		if (preg_match('/^\s*==(.*)==\s*$/', $cell, $a_header)) 
		{
			$header= trim($a_header[1]);
			$header= preg_replace('/'.PATTERN_NO_ESC.'\$ID/', $xl_id, $header);

			if (preg_match('/([\/\\\\|])(.*)\1$/', $header, $a_align)) 
			{
				print '<style>';
				switch ($a_align[1]) {
					case '/' :	print '#'. $ID_TABLE .' .col'. $col .' { text-align:right; }';	break;
					case '\\' :	print '#'. $ID_TABLE .' .col'. $col .' { text-align:left; }';	break;
					case '|' :	print '#'. $ID_TABLE .' .col'. $col .' { text-align:center; }';	break;
				}
				print '</style>';

				$header= $a_align[2];
			}

			if (preg_match('/^(?:\$(\w*))?\[\.\.\.\]$/', $header, $a_header_t))
			{
				//TODO: should be possible to add totals in a accu 
				if ( isset($total['col'][$col]) xor isset($total['row'][$row]) )
				{

					foreach (array('row' => $row, 'col' => $col) as $dim => $idx)
						if ( isset($total[$dim][$idx]) ) 
						{
							print $TD_ws. '<th id="'. $id .'" class="total row'. $row .' col'. $col .'" title="['. $xl_id .']" >ERROR!</th>';
							$js_script.= 'if (t'.$dim.'['.$idx.'] !== undefined) $("'. $id .'").innerHTML= '. $js_toFixed('t'.$dim.'['.$idx.']') .'; '. "\n";

							if (isset( $a_header_t[1] ))
							{
								$var= $a_header_t[1];	

								print '<div id="'. $ID_TABLE . CSS_ID_DELIM . $var .'" hidden>ERROR!</div>';
								$js_script.= 'if (t'.$dim.'['.$idx.'] !== undefined) $("'. $ID_TABLE . CSS_ID_DELIM . $var .'").innerHTML= t'.$dim.'['.$idx.']; '. "\n";
								$js_script.= 'var $'. $var .'= t'.$dim.'['.$idx.']; '. "\n";
							}

							unset($total[$dim][$idx]);
						}

					continue;
				}

				print $TD_ws. '<th id="'. $id .'" class="warning total row'. $row .' col'. $col .'" title="['. $xl_id .']" >SYNTAX!</th>';

				foreach (array('row' => $row, 'col' => $col) as $dim => $idx)
					if ( isset($total[$dim][$idx]) )
						unset($total[$dim][$idx]);

				continue;
			}

			//TODO: undefined all tcol and trow in js at the end of the script

			if (preg_match('/^(.*)\s*'.PATTERN_NO_ESC.'([+#])\2$/', $header, $a_accum)) {
				$header= $a_accum[1];
				$total['col'][$col]= true;
				$js_script.= 'tcol['.$col.']= 0; '. "\n";
			}
			elseif (preg_match('/^'.PATTERN_NO_ESC.'([+#])\1(.*)\s*$/', $header, $a_accum)) {
				$header= $a_accum[2];
				$total['row'][$row]= true;
				$js_script.= 'trow['.$row.']= 0; '. "\n";
			}

			if ($quotes != '"')
				$header= preg_replace('/[\\\](.)/', '\1', $header);

			print $TD_ws. '<th id="'. $id .'" class="row'. $row .' col'. $col .'" style="'. $cell_style .'" >'. $this->htmlspecialchars_ent($header) .'</th>';
			continue;
		}

		//-------------------------------------------------------------------------------------------------------------
		// cell

		if ($quotes != '"')
			$cell= preg_replace('/[\\\](.)/', '\1', $cell);

		if ( isset($total['col'][$col]) || isset($total['row'][$row]) )
		{
			if (trim($cell) == '_') {
				print $TD_ws. '<td id="'. $id .'" class="accu row'.$row .' col'.$col .'" title="['. $xl_id .']" >&nbsp;</td>';
				continue;
			}

			$title= $cell;
			list($success, $nr, $format)= $parse_number($cell);

			print $TD_ws. '<td id="'. $id .'" class="accu '. (($nr <= 0) ? 'warning' : '' ) .' row'.$row .' col'.$col .'" title="['. $xl_id .'] '. $title .'('. $format .')" >ERROR!</td>';

			foreach (array('row' => $row, 'col' => $col) as $dim => $idx)
				if ( isset($total[$dim][$idx]) )
				{
					list($replaced, $selector, $var)= $replace_jquery_var($cell);

					if ($success)
						$js_script.= '$("'. $id .'").innerHTML= '. $js_toFixed($nr) .'; if (t'.$dim.'['.$idx.'] !== undefined) t'.$dim.'['.$idx.']+= Number('. $nr .'); '. "\n";

					elseif (preg_match('/^\$'.PATTERN_VAR.'$/', $cell, $a_vars))
					{
						$var= $a_vars[0];
						$js_script.= '$("'. $id .'").innerHTML= '. $js_toFixed($var) .'; if ('. $var .' > 0) $("'. $id .'").classList.remove("warning"); '. "\n";
						$js_script.= 'if ('. $var. ' === undefined) t'.$dim.'['.$idx.']= undefined; else if (t'.$dim.'['.$idx.'] !== undefined) t'.$dim.'['.$idx.']+= Number('. $var .'); '. "\n";
					}

					elseif ($replaced)
					{
						$js_script.= 'var '. $var .'= ('. $var.'_td= $("'. $selector .'")) ? '. $var.'_td.innerHTML : undefined; '. $var.'_td= undefined;' ."\n";
						$js_script.= '$("'. $id .'").innerHTML= '. $js_toFixed($var) .'; if ('. $var .' > 0) $("'. $id .'").classList.remove("warning"); '. "\n";
						$js_script.= 'if ('. $var. ' === undefined) t'.$dim.'['.$idx.']= undefined; else if (t'.$dim.'['.$idx.'] !== undefined) t'.$dim.'['.$idx.']+= Number('. $var .'); '. "\n";
					}
					else
						$js_script.= 't'.$dim.'['.$idx.']= undefined; '. "\n";
				}

			continue;
		}

		// if blank, print &nbsp;
		//
		if (preg_match('/^\s*$/',$cell)) 
		{
			print $TD_ws. '<td id="'. $id .'" class="row'. $row .' col'. $col .'" >&nbsp;</td>';
			continue;
		}

		$cell= preg_replace('/'.PATTERN_NO_ESC.'\$ID/', $xl_id, $cell);

		// test for CamelLink
		//
		if (preg_match_all('/\[\[(.*?)\]\]/', $cell, $all_links))
		{
			foreach ($all_links[1] as $i => $camel_link) 
				$cell = preg_replace('/\[\['. $camel_link .'\]\]/', $this->Link($camel_link), $cell);
		}		
		// test for [[url|label]]
		//
		elseif (preg_match_all('/\[\[(.*?\|.*?)\]\]/', $cell, $all_links))
		{
			foreach ($all_links[1] as $i => $url_link) 
				if(preg_match('/^\s*(.*?)\s*\|\s*(.*?)\s*$/su', $url_link, $matches)) {
					$url = $matches[1];
					$text = $matches[2];
					$cell = $this->Link($url, '', $text, TRUE, TRUE, '', '', FALSE);	
				}
		}		
		else
			$cell= $this->htmlspecialchars_ent($cell);

		print $TD_ws. '<td id="'. $id .'" class="row'. $row .' col'. $col .'" style="'. $cell_style .'" >'. $cell .'</td>';

	}
	print "</tr>\n";

}
print "</table>\n";

print "<script>\n". $js_script ."</script>\n";

//---------------------------------------------------------------------------------------------------------------------

// https://www.w3schools.com/js/js_htmldom_html.asp
// https://playcode.io/

$print_javascript= function () use (&$replace_jquery_var, &$ARRAY_CODE_LINES, &$ID_TABLE)
{
	$declared_names= array();
	$assigned_names= array();
	foreach ($ARRAY_CODE_LINES as $js_line) 
	{
		if (preg_match_all('/'.PATTERN_JQUERY_VAR.'|'.PATTERN_XL_ID.'/', $js_line, $a_vars)) 
			$declared_names= array_merge($declared_names, $a_vars[0]);
		if (preg_match_all('/('.PATTERN_JQUERY_VAR.'|'.PATTERN_XL_ID.')\s*=/', $js_line, $a_vars))
			$assigned_names= array_merge($assigned_names, $a_vars[1]);
	}

	$declared_names= array_unique($declared_names);
	sort($declared_names);

	$assigned_names= array_unique($assigned_names);
	sort($assigned_names);

	// print <script/>
	//

	// declare a variable for each variable declared in a js directive
	//
	print '<script>' . "\n";
	foreach ($declared_names as $name) 
	{
		list($replaced, $selector, $var)= $replace_jquery_var($name);

		if ($replaced)
			print 'var '. $var .'= ('. $var.'_td= $("'. $selector .'")) ? '. $var.'_td.innerHTML : undefined; '. $var.'_td= undefined;' ."\n";
		else 
			print 'var '. $name .'= ('. $name.'_td= $("'. $ID_TABLE .'-'. $name .'")) ? '. $name.'_td.innerHTML : undefined; '. $name.'_td= undefined;' ."\n";
	}

	// output each line of code; replace fxns/vars and check allowed characters
	//
	foreach ($ARRAY_CODE_LINES as $lnr => $js_line) 
	{
		if (!preg_match('/^#(?:js!)?\s*(.*)$/', $js_line, $a_jscode))
			break;

		$js= $a_jscode[1];

		if (preg_match('/^\s*\/\//', $js, $a_jscode))
			continue;

		if (preg_match_all('/'.PATTERN_JQUERY_VAR.'/', $js, $a_vars)) 
			foreach ($a_vars[0] as $name)
			{
				list(,, $var)= $replace_jquery_var($name);
				$js= str_replace($name, $var, $js);
			}

		// Escape the Math.fxn() calls, if the line qualifies, then print the unescaped $js_line
		//
		$js_esc_math= preg_replace('/(Math\.|Number|toFixed)([^\(]*)\(([^\)]*)\)/U', '\1\2"\3"', $js);
		if (preg_match('/^[\$\w=\s\/;+\'"*!|&^%\.-]*$/', $js_esc_math, $a_js)) {
			print $js."\n";
			continue;
		}
		
		print '</script>' ."\n";
		print 'ERROR: line '. $lnr .': \''. $js_line .'\'' ."\n";
		return;
	}

	// push results back into html foreach variable assigned in js (with an = sign)
	//
	foreach ($assigned_names as $name) 
	{
		list($replaced, $selector, $var)= $replace_jquery_var($name);

		if ($replaced)
			print 'if ('. $var.'_td= $("'. $selector .'")) '. $var.'_td.innerHTML= '. $var .'; '. $var.'_td= undefined;' . "\n";
		else
		print 'if ('. $name.'_td= $("'. $ID_TABLE .'-'. $name .'")) '. $name.'_td.innerHTML= '. $name .'; '. $name.'_td= undefined;' . "\n";
	}

	// clean-up; undeclare all declared variables 
	//
	$output= '';
	foreach ($declared_names as $name) 
	{
		list($replaced, $selector, $var)= $replace_jquery_var($name);

		if ($replaced)
			$output.= $var .'= ';
		else
			$output.= $name .'= ';
	}
	if (!empty($output)) print $output ."undefined;\n";

	print '</script>' ."\n";
};

$print_javascript();
?>
