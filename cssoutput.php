<?php

class OutputCSS
{
	function outputStylesheet(CSSStylesheet $t)
	{
		$out='';
		foreach($t->getObjects() as $o)
		{
			assert('$o instanceof CSSObject');
			switch(get_class($o))
			{
				case 'CSSImport': $out .= $this->outputImport($o); break;
				case 'CSSRuleset': $out .= $this->outputRuleset($o); break;
				case 'CSSMedia': $out .= $this->outputMedia($o); break;
			}
		}
		return $out."\n"; // stupid firefox ignores first rule if CSS doesnt have newline!
	}

	function outputMedia(CSSMedia $m)
	{
		$out = '@media '.implode(',',$m->getMedia());
		if ($m->getConditions())
		{
			$out.=' and('.$this->outputDeclarations($m->getConditions()).')'; // CHECK: these arent strictly declarations
		}
		$out .= '{';
		foreach($this->getRulesets() as $rule)
		{
			$out .= $this->outputRuleset($rule);
		}
		return $out . "}\n";
	}

	function outputImport(CSSImport $o)
	{
		return '@import"'.$o->getURI().'"'.implode(',',$o->getMedia()).';';
	}

	function outputRuleset(CSSRuleset $o)
	{
		$decs = $o->getDeclarations();
		$sels = $o->getSelectors();
		if (!$decs->isEmpty())
		{
			if ($decs->queryFilterAll('hidemac',true))
			{
				return '/*\*/'.$this->outputSelectors($sels).'{'.$this->outputDeclarations($decs,array('hidemac'=>true))."}/**/\n";
			}
			return $this->outputSelectors($sels).'{'.$this->outputDeclarations($decs)."}\n";
		}
		return '';
	}


	function outputDeclarations(CSSDeclarations $decs, $ignorefilters=array())
	{
		$prev=false;
		$all = $decs->getDeclarations();
		assert('count($all)');
		$hidemac = !isset($ignorefilters['hidemac']) && $all[0]->queryFilter('hidemac');
		if ($hidemac) $out = '/*\*/'; else $out='';
		foreach($all as $d)
		{
			try {
				if ($prev) $out.= ";"; else $prev=true;

				if (!isset($ignorefilters['hidemac']) && $d->queryFilter('hidemac')===true)
				{
					if (!$hidemac) $out .= '/*\*/'; $hidemac=true;
				}
				if (!isset($ignorefilters['hidemac']) && !$d->queryFilter('hidemac'))
				{
					if ($hidemac) $out .= '/**/'; $hidemac=false;
				}
				$out .= $this->outputDeclaration($d);
			}
			catch(CSSRuntimeError $e) {$out .= '/* runtime error: '.$e->getMessage().' */';} /* CHECK: report error better! */
		}
		if ($hidemac) $out .= '/**/';
		return $out;
	}

	function outputDeclaration(CSSDeclaration $d)
	{
		return $d->getName().':'.$this->outputExpression($d->getValue()).($d->isImportant()?'!important':'');
	}

	function outputExpression(CSSExpression $e)
	{
		$out='';
		foreach($e->getTerms() as $e)
		{
			$out .= $e[0].$this->outputTerm($e[1]);
		}
		return $out;
	}

	function outputTerm($t) {return $t->output();}

	function outputSelectors(CSSSelectors $sels)
	{
		$out='';$prev=false;
		foreach($sels->getSelectors() as $s)
		{
			if ($prev) $out .= ',';else $prev=true;
			$out .= $this->outputSelector($s);
		}
		return $out;
	}

	function outputCombinator($s) {return $s;}

	function outputSelector(CSSSelector $sel)
	{
		$out = '';
		foreach($sel->getSimpleSelectors() as $s)
		{
			if ($s[0] !== NULL) $out .= $this->outputCombinator($s[0]);
			$out .= $this->outputSimpleSelector($s[1]);
		}
		return $out;
	}

	function outputSimpleSelectorElement($e) {return $e;}

	static $tokentochar = array(DELIM=>'=',SUBSTRINGMATCH=>'*=',SUFFIXMATCH=>'$=',PREFIXMATCH=>'^=',DASHMATCH=>'|=',INCLUDES=>'~=');

	function outputSimpleSelector(CSSSimpleSelector $sel)
	{
		$element = $sel->getElement();
		$qualifier = $sel->getQualifiers();
		if ($element!='*' || !count($qualifier))	$out= $this->outputSimpleSelectorElement($element); else $out='';

		foreach($qualifier as $c)
		{
			if ($c[0]=='hash') $out.= '#'.$c[1];
			elseif ($c[0]=='class') $out.= '.'.$c[1];
			elseif ($c[0]=='pseudo') if ($c[1]) $out.= ':'.$c[1].$c[2].')'; else $out.= ':'.$c[2];
			elseif ($c[0]=='attr')
			{

				$out .= '['.$c[2];
				if ($c[1])
				{
					$out .= self::$tokentochar[$c[1]].$this->quoteString($c[3],true);
				}
				$out .= ']';
			}
			else throw new MyException('unknown qualifier type '.$c[0]);
		}
		return $out;
	}

	protected function quoteString($s,$auto=false)
	{
		if (!$auto || preg_match('/^[^a-z_]|[^a-z0-9_-]/i',$s)) /** @todo use actual token regexp! */
		{
			return '"'.addslashes($s).'"'; /** @todo make some real escaping */
		}
		return $s;
	}
}

class OutputHTMLCSS extends OutputCSS
{

	function outputStylesheet(CSSStylesheet $t)
	{
		return '<link rel=stylesheet href=/css/metacss.css>'.parent::outputStylesheet($t);
	}

	function outputMedia(CSSMedia $m)
	{
		return '<div class="media">'.parent::outputMedia($m).'</div>';
	}

	function outputRuleset(CSSRuleset $o)
	{
		return '<div class="rule">'.parent::outputRuleset($o).'</div>';
	}

	function outputImport(CSSImport $o)
	{
		return '<div class="import">'.parent::outputImport($o).'</div>';
	}

	function outputDeclarations(CSSDeclarations $decs, $ignorefilters=array())
	{
		$prev=false;
		$out = '<div>';
		foreach($decs->getDeclarations() as $d)
		{
			if ($prev) $out.= ';</div><div>'; else $prev=true;
			$out .= $this->outputDeclaration($d);
		}
		return $out . '</div>';
	}

	function outputDeclaration(CSSDeclaration $d)
	{
		return '<var>'.$d->getName().'</var>: '.$this->outputExpression($d->getValue()).($d->isImportant()?'<span class="imp">!important</span>':'');
	}

	function outputTerm($t)
	{
		if ($t instanceof CSSTermSigned)
		{
			return '<var class="s">'.$t->output().'</var>';
		}
		elseif ($t instanceof CSSTermString)
		{
			return '<var class="str">'.$t->output().'</var>';
		}
		elseif ($t instanceof CSSTermURI)
		{
			return '<var class="url">'.$t->output().'</var>';
		}
		return '<var>'.$t->output().'</var>';
	}

	function outputCombinator($s)
	{
		return '<span class="comb'.($s===' '?' desc':'').'">'.$s.'</span>';
	}

	function outputSelector(CSSSelector $o)
	{
		return '<span class="sel">'.parent::outputSelector($o).'</span> ';
	}

	function outputSimpleSelector(CSSSimpleSelector $o)
	{
		return '<span>'.parent::outputSimpleSelector($o).'</span>';
	}

	function outputSimpleSelectorElement($e) {return '<span class="el">'.$e.'</span>';}

}

