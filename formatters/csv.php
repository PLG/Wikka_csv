<?php
// convert inline csv data into a table.
// by OnegWR, May 2005, license GPL http://wikkawiki.org/OnegWRCsv
// by ThePLG, Apr 2020, license GPL http://wikkawiki.org/PLG-Csv

// https://blog.teamtreehouse.com/how-to-debug-in-php
// ini_set('display_errors', 'On');
// error_reporting(E_ALL | E_STRICT);

//---------------------------------------------------------------------------------------------------------------------

// https://www.phpliveregex.com
// https://www.php.net/manual/en/function.preg-quote.php
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
if (!defined('PATTERN_SIMPLE_VAR'))			define('PATTERN_SIMPLE_VAR', '\$(\b'.PATTERN_VAR.'\b)(?!\'\])(?!\s*\.)'); // word boundaries, not ending with '] or .
if (!defined('PATTERN_TABLE_VAR'))			define('PATTERN_TABLE_VAR', '\$\[\'(?:(?:#('.PATTERN_CSS_IDENTIFIER.')\s*)?('.PATTERN_VAR.')|(?R))*\'\]'); // recursive
if (!defined('PATTERN_XL_ID'))				define('PATTERN_XL_ID', '[A-Z]+[\d]+');

if (!defined('CSS_ID_DELIM'))				define('CSS_ID_DELIM', '-');
if (!defined('ID'))							define('ID', 'id');
if (!defined('TITLE'))						define('TITLE', 'title');
if (!defined('CLASSES'))					define('CLASSES', 'class');
if (!defined('STYLE'))						define('STYLE', 'style');

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
	return 'Number('. $nr .').toFixed('.strlen($places).')'; // NOTE: .toFixed(...) returns a string!
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
			$pattern_number= '([+-]?)\s*(\d{1,3}(?:'. $grouping .'\d{3})*|(?:\d+))(?:'. $decimal .'(\d{'. strlen($places) .'}))?(?:\s*([A-Z]{3}))?';

		if (preg_match('/^'.$pattern_number.'$/', $cell, $a_currency))
		{
			list(, $posneg, $whole, $frac, $currency)= $a_currency;

			if (isset($currency) && strcmp($currency, $format))
				continue;

			$cell= $posneg . preg_replace('/'.$grouping.'/', '', $whole);
			if ( isset($frac) )
				$cell.= '.'. $frac;

			return array(true, floatval($cell), $format, $currency);
		}
	}

	return array(false, $cell, 'ERR', '');
};

//---------------------------------------------------------------------------------------------------------------------

$_= function($var) {
	return '$'. $var;
};

$replace_camel_url_links= function ($cell)
{
	// test of [[CamelLink]] and [[URL|name]]
	//
	if (preg_match_all('/\[\[([^|\]]*)(?:\|([^\]]*))?\]\]/', $cell, $a_links))
	{
		list($found, $links, $names)= $a_links;
		foreach ($found as $idx => $found1)
		{
			if ( empty($names[$idx]) )
				$cell= preg_replace('/'.preg_quote($found[$idx],'/').'/', $this->Link($links[$idx]), $cell);

			else
				$cell= preg_replace('/'.preg_quote($found[$idx],'/').'/', $this->Link($links[$idx], '', $names[$idx], TRUE, TRUE, '', '', FALSE), $cell);
		}

		return array(true, $cell);
	}

	return array(false, $cell);
};

$escaped_css_id_var= function ($css_id, $var) {
	return preg_replace('/['. CSS_ID_DELIM .']/', '_', $css_id) . '_' . $var;
};


$qualified_var= function($ID_TABLE, $simple_var, $table_name, $table_var) use (&$escaped_css_id_var)
{
	if (!empty($simple_var))
		return array($escaped_css_id_var($ID_TABLE, $simple_var), $simple_var);

	elseif (!empty($table_var))
	{
		if (!empty($table_name))
			return array($css_id_var= $escaped_css_id_var($table_name, $table_var), $escaped_css_id_var($table_name, $table_var));
		else
			return array($escaped_css_id_var($ID_TABLE, $table_var), $table_var);
	}

	return array($escaped_css_id_var($ID_TABLE, 'ERR'), 'ERR');
};

/*
$replace_jquery_var= function ($name) use (&$escaped_css_id_var)
{
	if (preg_match('/'.PATTERN_JQUERY_VAR.'/', $name, $a_name))
	{
		list($css_id, $var)= $a_name;

		$css_id_var= $escaped_css_id_var($css_id, $var);
		$selector= $css_id . CSS_ID_DELIM . $var;

		return array(true, $selector, $css_id_var);
	}

	return array(false, '', $name);
};
*/

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
//$css['.negative']= '{ background-color:#f00; }';
$css['.negative']= '{ background-color:#fcc; border: 2px solid red; border-collapse: collapse; }';
$css['.total']= '{ border: 1px solid black; border-collapse: collapse; }';
$css['a:link']= '{ color: blue; }';
$css['a:visited']= '{ color: blue; }';

foreach ($ARRAY_CODE_LINES as $row => $csv_line) 
{
	if ( preg_match('/^#css!\s*'.PATTERN_CSS_RULE.'$/', $csv_line, $a_css) )
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

print '<script>function $(x) { return document.getElementById(x); }; var tcol={}; var trow={};</script>'. "\n";
print '<table id="'. $ID_TABLE .'">'. "\n";
// https://www.thoughtco.com/and-in-javascript-2037515
foreach ($ARRAY_CODE_LINES as $csv_row => $csv_line) 
{
	if (preg_match('/^#js!/', $csv_line, $js_line))
		break;

	unset($ARRAY_CODE_LINES[$csv_row]);

	if (preg_match('/^#/', $csv_line)) {
		$comments++;
		continue;
	}

	$row= $csv_row - $comments;

	// if blank line, print empty row ... else ...
	//
	if (preg_match('/^\s*$/', $csv_line)) 
		print '<tr class="empty" >';
	else 
		print ($row %2) ? '<tr class="even" >' : '<tr class="odd" >';


	foreach (preg_split('/'. $PATTERN_NO_SPLIT_QUOTED_DELIM .'/', $csv_line) as $col => $csv_cell)
	{
		$a_dimensions= array(
				'col' => array('|++', $col),
				'row' => array('++_', $row),
			);

		$attr[CLASSES]= ' row'. $row .' col'. $col;

		$xl_id= $spreadsheet_baseZ($col) . $row;

		$attr[ID]= $ID_TABLE . CSS_ID_DELIM . $xl_id;
		$attr[TITLE]= '['. $xl_id .']';

		$attr[STYLE]='';

		$tag= 'td';
		$tag_style= '';
		$tag_script= '';

		$quotes= '';

		// extract the cell out of it's quotes
		//
        if (preg_match('/^\s*("?)(.*?)\1\s*$/', $csv_cell, $a_matches))
		{
			list(, $quotes, $cell)= $a_matches;

			if (empty($cell))
				$cell= '&nbsp;';

			if ($quotes == '"')
				$attr[STYLE].= ' white-space:pre;';
		}

		//-------------------------------------------------------------------------------------------------------------
		// header

		if (preg_match('/^\s*==\s*(.*?)\s*==\s*$/', $cell, $a_header)) 
		{
			list(,$cell)= $a_header;

			$tag= 'th';

			if (preg_match('/([\/\\\\|])(.*)\1$/', $cell, $a_nonvar)) 
			{
				list(, $align, $cell)= $a_nonvar;

				switch ($align) {
					case '/' :	$tag_style.= '#'. $ID_TABLE .' .col'. $col .' { text-align:right; }';	break;
					case '\\' :	$tag_style.= '#'. $ID_TABLE .' .col'. $col .' { text-align:left; }';	break;
					case '|' :	$tag_style.= '#'. $ID_TABLE .' .col'. $col .' { text-align:center; }';	break;
				}
			}

			//TODO: undefined all tcol and trow in js at the end of the script

			if (preg_match('/^(.*)\s*'.PATTERN_NO_ESC.'([+#])\2$/', $cell, $a_accum)) {
				$cell= $a_accum[1];
				$total['col'][$col]= true;
				$tag_script.= 'tcol['.$col.']= Number(0); '. "\n";
			}
			elseif (preg_match('/^'.PATTERN_NO_ESC.'([+#])\1(.*)\s*$/', $cell, $a_accum)) {
				$cell= $a_accum[2];
				$total['row'][$row]= true;
				$tag_script.= 'trow['.$row.']= Number(0); '. "\n";
			}

		}

		//-------------------------------------------------------------------------------------------------------------
		// not header

		else
		{
		}

		//-------------------------------------------------------------------------------------------------------------
		//

		$replaced= false;

		$cell= preg_replace('/'.PATTERN_NO_ESC.'\$ID/', $xl_id, $cell);

		// READ into variable
		//
		if (preg_match('/^\[(?:\$('.PATTERN_VAR.'))?=(.*)?\]$/', $cell, $a_read_var))
		{
			list(, $decl_var, $decl_value)= $a_read_var;

			// if cell contains [...=|++] or [...=++_] (vertical or horizontal accummulation marker)
			//			
			if ( ($decl_value == $a_dimensions['col'][0]) || ($decl_value == $a_dimensions['row'][0]) )
			{ 
				$cell= 'SYNTAX!';

				foreach ($a_dimensions as $rowcol => list($marker, $idx) )
					if ( $decl_value == $marker && isset($total[$rowcol][$idx]) )
					{
						$attr[CLASSES].= ' total';
						$cell= 'ERROR!';

						$tag_script.= 'if (t'.$rowcol.'['.$idx.'] !== undefined) $("'. $attr[ID] .'").innerHTML= '. $js_toFixed('t'.$rowcol.'['.$idx.']') .'; '. "\n";
						$replaced= true;

						unset( $total[$rowcol][$idx] );
					}
			}

			// assign a value [...=decl_value]
			//
			else 
			{
				if (empty($decl_value))
					$decl_value= '&nbsp;';

				$cell= $decl_value;

				list($success, $nr, $format, $currency)= $parse_number($cell);
				if ($success)
					$attr[CLASSES].= (($nr < 0) ? ' negative' : '' );
			}

			// variable assignment [$decl_var=...]
			//
			if (!empty( $decl_var ))
				$tag_script.= 'var '. $_($escaped_css_id_var($ID_TABLE, $decl_var)) .'= $("'. $attr[ID] .'"); '. "\n";
		}

		// Write out variable
		//
		// https://www.regular-expressions.info/recurse.html
		elseif (preg_match('/^(-)?\s*(?:'.PATTERN_SIMPLE_VAR.'|'.PATTERN_TABLE_VAR.')$/', $cell, $a_write_var))
		{
			list(, $neg, $simple_var, $table_name, $table_var)= $a_write_var;
			list($css_id_var, $var)= $qualified_var($ID_TABLE, $simple_var, $table_name, $table_var);

			$cell= 'ERROR!';

			$tag_script.= 'if (typeof('. $_($css_id_var) .') !== \'undefined\') $("'. $attr[ID] .'").innerHTML= '. $neg . $_($css_id_var) .'.innerHTML; '. "\n";
			$tag_script.= 'if ( !isNaN(Number($("'. $attr[ID] .'").innerHTML)) ) '
				.'if (Number($("'. $attr[ID] .'").innerHTML) < 0) $("'. $attr[ID] .'").classList.add("negative"); else $("'. $attr[ID] .'").classList.remove("negative");'. "\n";
			
			$replaced= true;
		}

		// calculate totals
		//
		if ( isset($total['col'][$col]) || isset($total['row'][$row]) )
		{
			//TODO if USD is appended to the end of a cell, that means variable replacement should move here, so that the replacement is also parsed for currency
			// Also, in js, you will get '234.34 USD' when reading out a variable. Users will need string parsing tools then. argh!

			foreach ($a_dimensions as $rowcol => list(, $idx) )
				if ( isset($total[$rowcol][$idx]) )
				{
					$attr[CLASSES].= ' accu';

					if ($replaced)
						$tag_script.= 't'.$rowcol.'['.$idx.']+= Number( $("'. $attr[ID] .'").innerHTML ); if (isNaN(t'.$rowcol.'['.$idx.'])) t'.$rowcol.'['.$idx.']= undefined; '. "\n";

					elseif ($cell != '_' && $tag != 'th')
					{
						list($success, $nr, $format, $currency)= $parse_number($cell);
						
						$attr[TITLE].= ' '. $cell .'('. $format .' '. $currency .')';

						if ($success)
						{
							$attr[CLASSES].= (($nr < 0) ? ' negative' : '' );

							$tag_script.= '$("'. $attr[ID] .'").innerHTML= '. $js_toFixed($nr) .'; if (t'.$rowcol.'['.$idx.'] !== undefined) t'.$rowcol.'['.$idx.']+= Number('. $nr .'); '. "\n";
							//TODO $js_script.= '$("'. $attr[ID] .'").innerHTML= '. $js_toFixed($nr) .''. $currency .'; if (t'.$dim.'['.$idx.'] !== undefined) t'.$dim.'['.$idx.']+= Number('. $nr .'); '. "\n";
						}
						else
							$tag_script.= 't'.$rowcol.'['.$idx.']= undefined; '. "\n";
					}
				}

		}

		//-------------------------------------------------------------------------------------------------------------
		// Output

		if ($quotes != '"')
			$cell= preg_replace('/[\\\](.)/', '\1', $cell);

		$cell= $this->htmlspecialchars_ent($cell);

		list(, $cell)= $replace_camel_url_links($cell);

		print $TD_ws; 
		print ((empty($tag_style)) ? '' : '<style>'. $tag_style .'</style>');
		print '<'. $tag .' id="'. $attr[ID] .'" class="'. $attr[CLASSES] .'" title="'. $attr[TITLE] .'" style="'. $attr[STYLE] .'" >'. $cell .'</'. $tag .'>';
		print ((empty($tag_script)) ? '' : "\n". '<script>'. $tag_script .'</script>');

	}
	print "</tr>\n";

}
print "</table>\n";

//---------------------------------------------------------------------------------------------------------------------

// https://www.w3schools.com/js/js_htmldom_html.asp
// https://playcode.io/ promo code: b3M3O5bR
// https://www.w3schools.com/html/tryit.asp?filename=tryhtml_default

$print_javascript= function () use (&$_, &$escaped_css_id_var, &$qualified_var, &$ARRAY_CODE_LINES, &$ID_TABLE)
{
	$declared_names= array();
	$assigned_names= array();
	foreach ($ARRAY_CODE_LINES as $lnr => $js_line) 
	{
		if (!preg_match('/^#(?:js!)?/', $js_line))
		{
			print 'ERROR: line '. $lnr .': \''. $js_line .'\'' ."\n";
			return;
		}

		if (!preg_match('/^#js!(?!\s*\/\/|\s*$)/', $js_line))
		{
			unset($ARRAY_CODE_LINES[$lnr]);
			continue;
		}

		// preg_replace here, removes duplicates of the same declaration: $var2 and $['var2'] and $['#var-text var2']
		//
		if (preg_match_all('/'.PATTERN_SIMPLE_VAR.'|'.PATTERN_TABLE_VAR.'|'.PATTERN_XL_ID.'/', $js_line, $a_vars)) 
			$declared_names= array_merge($declared_names, preg_replace('/\[\'(#'.$ID_TABLE.')?\s*([a-zA-Z_]\w*)\'\]/', '$2', $a_vars[0]));
		if (preg_match_all('/('.PATTERN_SIMPLE_VAR.'|'.PATTERN_TABLE_VAR.'|'.PATTERN_XL_ID.')\s*=/', $js_line, $a_vars))
			$assigned_names= array_merge($assigned_names, preg_replace('/\[\'(#'.$ID_TABLE.')?\s*([a-zA-Z_]\w*)\'\]/', '$2', $a_vars[1]));
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
		if (preg_match('/^'.PATTERN_SIMPLE_VAR.'|'.PATTERN_TABLE_VAR.'|('.PATTERN_XL_ID.')$/', $name, $a_name)) 
		{
			list(, $simple_var, $table_name, $table_var, $xl_id)= $a_name;
			list($css_id_var, $var)= $qualified_var($ID_TABLE, $simple_var, $table_name, $table_var);

			if (!empty($xl_id))
				print 'var '. $xl_id .'= ('. $_($xl_id).'= $("'. $ID_TABLE .'-'. $xl_id .'")) ? '. $_($xl_id) .'.innerHTML : undefined; '. $_($xl_id) .'= undefined;' ."\n";
			else
				print 'var '. $var .'= (typeof('. $_($css_id_var) .') !== \'undefined\') ? '. $_($css_id_var) .'.innerHTML : undefined;' ."\n";
		}
	}

	// output each line of code; replace fxns/vars and check allowed characters
	//
	foreach ($ARRAY_CODE_LINES as $lnr => $js_line) 
	{
		//$js= preg_replace('/^#js!\s*/', '', $js_line);
		if (preg_match('/^#js!\s*([^\/]*)/', $js_line, $a_code))
			list(, $js)= $a_code;

		if (preg_match_all('/'.PATTERN_SIMPLE_VAR.'|'.PATTERN_TABLE_VAR.'/', $js, $a_name)) 
		{
			foreach ($a_name[0] as $idx => $name)
			{
				list($simple_var, $table_name, $table_var)= array($a_name[1][$idx], $a_name[2][$idx], $a_name[3][$idx]);
				list($css_id_var, $var)= $qualified_var($ID_TABLE, $simple_var, $table_name, $table_var);

				$js= str_replace( $name, $var, $js);
			}
		}

		// Escape the Math.fxn() calls, if the line qualifies, then print the unescaped $js_line
		//
		$js_esc_math= preg_replace('/(Math\.|Number|toFixed)([^\(]*)\(([^\)]*)\)/U', '\1\2"\3"', $js);
		if (preg_match('/^[\$\w=\s\/;+\'"*!|&^%\.-]*$/', $js_esc_math, $a_js)) {
			print $js ."\n";
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
		if (preg_match('/^'.PATTERN_SIMPLE_VAR.'|'.PATTERN_TABLE_VAR.'|('.PATTERN_XL_ID.')$/', $name, $a_name)) 
		{
			list(, $simple_var, $table_name, $table_var, $xl_id)= $a_name;
			list($css_id_var, $var)= $qualified_var($ID_TABLE, $simple_var, $table_name, $table_var);

			if (!empty($xl_id))
			{
				$var= $xl_id;
				print 'if ('. $_($var) .'= $("'. $ID_TABLE .'-'. $var .'")) { '. 
					'if (typeof('. $var.')==\'number\') '.
						'if ('. $var.'<0) '. $_($var) .'.classList.add("negative"); '.
						'else '. $_($var) .'.classList.remove("negative"); '.
					$_($var) .'.innerHTML= '. $var .'; '.
				'} '. $_($var) .'= undefined;' ."\n";
			}
			else
			{
				print 'if (typeof('. $_($css_id_var) .') !== \'undefined\') { '. 
					'if (typeof('. $var.')==\'number\') '.
						'if ('. $var.'<0) '. $_($css_id_var) .'.classList.add("negative"); '.
						'else '. $_($css_id_var) .'.classList.remove("negative"); '.
					$_($css_id_var) .'.innerHTML= '. $var .'; '.
				'}' ."\n";
			}
		}
	}

	// clean-up; undeclare all declared variables 
	//
	$output= '';
	foreach ($declared_names as $name) 
	{
		if (preg_match('/^'.PATTERN_SIMPLE_VAR.'|'.PATTERN_TABLE_VAR.'|('.PATTERN_XL_ID.')$/', $name, $a_name)) 
		{
			list(, $simple_var, $table_name, $table_var, $xl_id)= $a_name;
			list($css_id_var, $var)= $qualified_var($ID_TABLE, $simple_var, $table_name, $table_var);

			if (!empty($xl_id))
				$output.= $xl_id .'= ';
			else
				$output.= $var .'= ';
		}
	}
	if (!empty($output)) print $output ."undefined;\n";

	print '</script>' ."\n";
};

$print_javascript();
?>
