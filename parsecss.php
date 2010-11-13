<?php

require_once "parse.php";
require_once "csstokens.php";
require_once "cssobjects.php";

class CSSParseError extends Exception {}

class ParseCSS extends ParserToken
{
	static $tokenstring;
	protected $backslashCommentHack,$errors,$condition;

	function __construct()
	{
		$this->errors = array();

		self::$tokenstring = '/('.implode(')|(',self::$tokens).')/';
		$this->o = new CSSStylesheet();
	}

	function setCondition($cond)
	{
		$this->condition = $cond;
	}

	function isCondition($cond)
	{
		return ($cond === $this->condition);
	}

	function error($msg)
	{
		$this->d('PARSE ERROR: '.$msg);
		$li = $this->offsetToLine($this->i);
		$this->errors[] = $msg.', line '.$li[0].' code: ...'.substr($this->h,$this->i-10,20).'...';
		throw new CSSParseError($msg);
	}

	static function isIdent($st)
	{
		return preg_match('/'.ident.'/',$st);
	}

	function getErrors()
	{
		return $this->errors;
	}

	function whiteSpace() /* S* */
	{
		if (!$this->isNext(S)) return false;
		while($this->isNext(S));
		return true;
	}

	function CSSCharset() /* [ CHARSET_SYM S* STRING S* ';' ]? */
	{
		if ($this->isValNext(ATKEYWORD,"charset"))
		{
			$this->whiteSpace();
			$this->o->setCharset($this->getTokenValNext(STRINGS,'Charset must have name in string'));
			$this->whiteSpace();
			$this->expect(SCOL,'Charset declaration must end with semicolon');
			return true;
		}
		return false;
	}
	function CSSInclude()
	{
		if ($this->isValNext(ATKEYWORD,'include'))
		{
			$this->whiteSpace();
			if ($this->isIn(STRINGS,URI))
			{
				$uri = $this->getTokenValNext();
			}
			else $this->error('Include expects string or uri');

			$this->whiteSpace();
			$this->expect(SCOL,'Include rule must end with semicolon');
			$this->whiteSpace();
			$this->includeFile($uri);
			return true;
		}
		return false;
	}
	function includeFile($fname1)
	{
		/* try loading .pcss first */
		$fname = preg_replace('/\.css$/','.pcss',$fname1);
		if (!file_exists($fname)) $fname = $fname1;

		$f = file_get_contents($fname); if (!$f) $this->error('Unable to include file '.$fname);
		$p = new ParseCSS();
		$p->setState($this->o);
		$s = $p->parse($f);
		$this->errors = array_merge($this->errors,$p->getErrors());
		$this->o->addSheet($s);
	}
	function setState(CSSStyleSheet $s)
	{
		d($s->getVars(),'setting state');
		$this->o->addVarsArray($s->getVars());
	}

	function CSSImport() /* IMPORT_SYM S* [STRING|URI] S* [ medium [ COMMA S* medium]* ]? ';' S* */
	{
		if ($this->isValNext(ATKEYWORD,'import'))
		{
			$this->whiteSpace();
			if ($this->isIn(STRINGS,URI))
			{
				$uri = $this->getTokenValNext();
			}
			else $this->error('Import expects string or uri');

			$this->whiteSpace();

			$media = NULL;
			if (!$this->is(SCOL))
			{
				$media = $this->CSSMedia();
			}
			$this->expect(SCOL,'Import rule must end with semicolon');
			$this->whiteSpace();
			return new CSSImport($uri,$media);
		}
		return false;
	}

	function d($msg='HERE')
	{
		d($this->nextToken,$msg.' (1:debug token)');
		d(substr($this->h,$this->nextToken[2],50).'...',$msg.' (2:debug source)');
	}

	function CSSRuleset()
	{
		try
		{
			$selectors = new CSSSelectors();
			$sel = $this->CSSSelector();

			if (!$sel) $this->error('Selector expected');

			$selectors->add($sel);

			while($this->isValNext(DELIM,','))
			{
				$this->whiteSpace();
				$selectors->add($this->CSSSelector());
			}
			$this->expect(LCBR,'Selector should end here and rule is expected');
		}
		catch(CSSParseError $e)
		{

			$this->skipUpTo(LCBR);$this->nextToken();
			$this->skipUpTo(RCBR);$this->nextToken();$this->whiteSpace();
			return false;
		}

		try {
			$declarations = $this->CSSDeclarations(RCBR);
			if (!$this->isEof()) // autoclose blocks on eof
			{
				$this->expect(RCBR,'Block of declarations should end here'); $this->whiteSpace();
			}
			return new CSSRuleset($selectors,$declarations);
		}
		catch(CSSParseError $e)
		{
			$this->skipUpTo(RCBR);$this->nextToken();$this->whiteSpace();
			return false;
		}
	}

	function getTokenVal($token=NULL, $msg=NULL)
	{
		if ($token !== NULL && $this->getToken() != $token) $this->expect($token,$msg);
		return $this->nextToken[1];
	}

	function getTokenValNext($token=NULL, $msg=NULL)
	{
		$val = $this->nextToken[1];
		if ($token !== NULL) $this->expect($token,$msg); else $this->nextToken(); /* expect() skips token */
		return $val;
	}

	function expect($token, $msg=NULL)
	{
		if ($this->is($token)) return $this->nextToken();
		$this->error($msg.($msg?' (':'').'expected token '.$token.', found '.$this->getToken().($msg?') ':''));
	}

	function skipUpTo($token1,$token2=NULL)
	{
		assert('is_numeric($token1)');
		assert('$token2 === NULL || is_numeric($token2)');

		$safe = 3000;
		do
		{
			if ($this->isEof()) {$this->error('skipped all up to end of file');return;}
			$tok = $this->getToken();
			if ($tok == $token1) return;
			if ($token2 !== NULL && $tok == $token2) return;
			switch($tok)
			{
				case LCBR: $this->nextToken();$this->skipUpTo(RCBR); break;
				case LPAR: $this->nextToken();$this->skipUpTo(RPAR); break;
				case LSBR: $this->nextToken();$this->skipUpTo(RSBR); break;
			}
			$this->nextToken();
		}
		while($safe--);
		$this->error('skipped lots, lots of code');
	}

	function CSSDeclarations($endtoken)
	{
		$declarations = new CSSDeclarations();
		$safe = 100;
		do
		{
			try
			{
				$this->whiteSpace();
				if ($dec = $this->CSSDeclaration($endtoken))
				{
					if ($this->backslashCommentHack) {$dec->setFilter('hidemac',true);}
					$declarations->add($dec);
				}
				if ($this->isNext(SCOL))
				{
					$this->whiteSpace(); continue;
				}
				elseif (!$this->isEof() && !$this->is($endtoken)) $this->error('trash between declarations?');
				else break;
			}
			catch(CSSParseError $e)
			{
				$this->skipUpTo(SCOL,$endtoken);

				if ($this->is($endtoken))	break;
				else $this->nextToken();
			}
		}
		while($safe--);

		return $declarations;
	}

	function CSSDeclaration($endtoken)
	{
		if ($this->is($endtoken)) return false;

		$propname = $this->getTokenValNext(IDENT,'Expected identifier being property name (did you forget to close ruleset?)'); $this->whiteSpace();

		if (!$this->isValNext(DELIM,':'))	$this->error('Expected colon after property name (did you forget to close ruleset?)');

		$this->whiteSpace();

		$expr = $this->CSSExpression($endtoken);

		$pri = $this->CSSPriority();
		return new CSSDeclaration($propname,$expr,$pri);
	}

	function CSSExpression($endtoken)
	{
		$exprs = new CSSExpression();
		$term = $this->CSSTerm();
		if (!$term) $this->error('Syntax of expression doesn\'t match any allowed term');

		$exprs->add(NULL,$term);
		$safe=100;
		while($safe--)
		{
			$op = $this->CSSOperator($endtoken);
			if ($op==false) break;
			$term = $this->CSSTerm();
			if ($term==false) break;
			$exprs->add($op,$term);
		}
		return $exprs;
	}

	function CSSMathTerm()
	{
		if ($this->is(IDENT))
		{
			$name = $this->getTokenValNext();
			$this->whiteSpace();
			try {
				return new CSSTermVar($this->o,$name);
			}
			catch(Exception $e)
			{
				$this->error("unknown variable ".$name);
			}
		}
		return $this->CSSTerm();
	}

	function CSSMath($end) /* MY EXTENSION: term [ operator term ]* */
	{
		$values = array($this->CSSMathTerm());
		while(!$this->is($end))
		{
			$values[] = $this->CSSMathOp();
			$values[] = $this->CSSMathTerm();
		}
		return new CSSTermMath($values);
	}

	function CSSMathOp()
	{
		$del = $this->getTokenValNext(DELIM,'Expecting math operator');
		if (!strchr("+-*/<>=?:",$del)) {
		throw new CSSParseError('Unknown math operator');}
		$this->whiteSpace();
		return $del;
	}

	function CSSTerm()
	{
		/* term : unary_operator?
    [ NUMBER S* | PERCENTAGE S* | LENGTH S* | EMS S* | EXS S* | ANGLE S* |
      TIME S* | FREQ S* | function ]
  | STRING S* | IDENT S* | URI S* | hexcolor */

	  $unary = NULL;

		    if ($this->is(STRINGS)) {$term = new CSSTermString($this->getTokenValNext());$this->whiteSpace();}
		elseif ($this->is(IDENT))   {$term = new CSSTermIdent($this->getTokenValNext());$this->whiteSpace();}
		elseif ($this->is(URI))     {$term = new CSSTermURI($this->getTokenValNext());$this->whiteSpace();}
		elseif ($this->is(HASH))    {$term = new CSSTermColor($this->getTokenValNext());$this->whiteSpace();}
		elseif ($this->isNext(LPAR))
		{
			/* MY EXTENSION */
			$this->whiteSpace();
			$term = $this->CSSMath(RPAR);
			$this->expect(RPAR,'Expecting closing paren after math expression');
			$this->whiteSpace();
		}
		else
		{
			    if ($this->isValNext(DELIM,'+')) $unary = '+'; /* plus has {w} by definition, here - not */
			elseif ($this->isValNext(DELIM,'-')) $unary = '-';

			    if ($this->is(NUMBER))     {$term = new CSSTermNumber($this->getTokenValNext());$this->whiteSpace();}
		  elseif ($this->is(PERCENTAGE)) {$term = new CSSTermPercentage($this->getTokenValNext());$this->whiteSpace();}
		  elseif ($this->is(DIMENSION)) {$term = new CSSTermDimension($this->getTokenValNext());$this->whiteSpace();}
		  elseif ($this->is(FUNC))
		  {
			  $term = $this->CSSFunction();

			}
		  else {
			  $this->d('out of ideas for term');
		  	return false;/* no fatal fail! CHECK: really? is term supposed to do that? */
		  }
		  if ($unary !== NULL) $term->setSign($unary);
		}
		return $term;
	}

	function CSSOperator($endtoken)
	{
		if ($this->isVal(DELIM,'!') || $this->is($endtoken) || $this->is(SCOL)) {return false;}  /* dont confuse last space with end of rule */

		if ($this->isValNext(DELIM,'/')) {$this->whiteSpace();return '/';}
		if ($this->isValNext(DELIM,',')) {$this->whiteSpace();return ',';}
		return ' ';
	}

	function CSSFunction()
	{
		$fname = $this->getTokenValNext(FUNC,'Expecting function');
		$fbody = $this->CSSExpression(RPAR);

		$this->expect(RPAR,'Function needs to be closed with right paren');
		$this->whiteSpace();
		return new CSSTermFunction($fname,$fbody);
	}

	function CSSPriority()
	{
		if (!$this->isValNext(DELIM,'!')) return false;
		$this->whiteSpace();
		if (!$this->isValNext(IDENT,'important'))	$this->error('!important keyword expected');
		return true;
	}

	function CSSSelector()
	{
		$selector = new CSSSelector();
		$ss = $this->CSSSimpleSelector(); if (!$ss)
		{
			if ($this->is(SCOL)) $this->error('Don\'t put semicolon after rule set');
			elseif ($this->is(LCBR)) $this->error('Missing selector');
			else $this->error('Unrecognized selector');
		}
		$selector->add(NULL,$ss);
		while(($comb = $this->CSSCombinator()) && ($sel = $this->CSSSimpleSelector()))
		{
			$selector->add($comb,$sel);
		}
		return $selector;
	}

	function CSSCombinator()
	{
		$space = $this->whiteSpace();

		if ($this->isValNext(DELIM,'+')) {$this->whiteSpace();return '+';}
		if ($this->isValNext(DELIM,'>')) {$this->whiteSpace();return '>';}
		if ($this->isValNext(DELIM,'~')) {$this->whiteSpace();return '~';}
		if ($space && !$this->is(LCBR) && !$this->isVal(DELIM,',')) /* dont confuse with space before block */
		{
			return ' ';
		}
		return false;/* no parse err!*/
	}
	function CSSSimpleSelector()
	{
		$implict = false;
		if ($this->is(IDENT))
		{
			$element = $this->getTokenValNext();
		}
		elseif ($this->isValNext(DELIM,'*'))
		{
			$element = '*';
		}
		else
		{
			$implict = true;
			$element = '*';
		}

		$qualifiers = array();
		$safe = 100;
		do
		{
			if ($this->is(HASH))
			{
				$qualifiers[] = array('hash',$this->getTokenValNext());
			}
			elseif ($this->isValNext(DELIM,'.'))
			{
				$qualifiers[] = array('class',$this->getTokenValNext(IDENT,'Expecting class name'));
			}
			elseif ($this->isNext(LSBR)) /*attrib  : '[' S* IDENT S* [ [ '=' | INCLUDES | DASHMATCH ] S* [ IDENT | STRING ] S* ]? ']' */
			{
				$this->whiteSpace();	$atr = $this->getTokenValNext(IDENT,'Expecting attribute name of attribute selector'); $this->whiteSpace();
				if ($this->isVal(DELIM,'=') || $this->isIn(INCLUDES,DASHMATCH,PREFIXMATCH,SUFFIXMATCH,SUBSTRINGMATCH))
				{
					$compare = $this->getToken(); $this->nextToken(); $this->whiteSpace();
					if (!$this->isIn(IDENT,STRINGS)) throw new CSSParseError('expected ident/string as attr value');
					$data = $this->getTokenValNext();
				}
				else {$compare=NULL;$data=NULL;}
				$this->expect(RSBR,'Attribute selector is missing right brace');
				$qualifiers[] = array('attr',$compare,$atr,$data);
			}
			elseif ($this->isValNext(DELIM,':')) /* pseudo  : ':' [ IDENT | FUNCTION S* IDENT? S* ')' ]; */
			{
				$this->isValNext(DELIM,':'); /* CHECK: fake css3 */
				if ($this->is(FUNC))
				{
					$func = $this->getTokenValNext(); $this->whiteSpace();
					if ($this->is(IDENT)) $ident = $this->getTokenValNext(); else $ident = NULL;
					$this->whiteSpace();
					$this->expect(RPAR,'Function selector is missing right paren');
				}
				else
				{
					$func = NULL;
					$ident = $this->getTokenValNext(IDENT,'Expecting name of pseudo-class (or did you forget opening brace?)');
				}
				$qualifiers[] = array('pseudo',$func,$ident);
			}
			else
			{/**/break;}
		}
		while($safe--);

		if ($implict && !count($qualifiers)) {$this->d('implict unqualified');return false;}

		return new CSSSimpleSelector($element,$qualifiers);
	}

	private function IfConditionalBlock($cond)
	{
		$this->expect(LCBR,'Conditional block has to be in curly braces');

		if (!$cond)
		{
			$this->d('condition unmet, skipping');
			$this->skipUpTo(RCBR);
		}
		else
		{

			$this->whiteSpace();
			try {
				while(!$this->isEof() && !$this->is(RCBR))
				{
					if ($p=$this->CSSAny()) $this->o->add($p);
				}
			}
			catch(CSSParseError $e)
			{
				$this->skipUpTo(RCBR);
				return false;
			}

		}
		if (!$this->isEof()) // autoclose blocks on eof
		{
			$this->d('skipped false block');
			$this->expect(RCBR,'Conditional block must end with curly brace'); $this->whiteSpace();
		}

		return true;
	}

	function CSSIf() /* Extension @if S* condition S* {properties} S* [ @elseif S* condition S* {properties} S* ]* [ @else S* {properties} S* ] */
	{
		if (!$this->isValNext(ATKEYWORD,'if')) return false;

		$this->whiteSpace();
		$condname = $this->getTokenValNext(IDENT,'Expecting condition name');

		$cond = $this->isCondition($condname);
		$this->whiteSpace();

		$this->IfConditionalBlock($cond);
		return true;
	}

// 	private function CSSElseIf($dump) /* @elseif S* {properties} S*  */
// 	{
// 		if (!$this->isValNext(ATKEYWORD,'elseif')) return false;

// 		$this->whiteSpace();
// 		$dump = $dump && !$this->isCondition($this->getTokenValNext(IDENT));
// 		$this->whiteSpace();

// 		$res = IfConditionalBlock($dump);
// 	}

// 	private function CSSElse($dump) /* @else S* {properties} S*  */
// 	{
// 		if (!$this->isValNext(ATKEYWORD,'else')) return false;

// 		$this->whiteSpace();

// 		$res = IfConditionalBlock($dump);
// 	}

	function CSSDefine() /* @define S* { properties } S* */
	{
		$dump=false;
		if (!$this->isValNext(ATKEYWORD,'define')) return false;

		$this->whiteSpace();
		$this->expect(LCBR,'Definition block must be in curly braces');
		try {

			$declarations = $this->CSSDeclarations(RCBR);
		}
		catch(CSSParseError $e)
		{
			$this->skipUpTo(RCBR);
			return false;
		}
		if (!$this->isEof()) // autoclose blocks on eof
		{
			$this->expect(RCBR,'Definition block must end with curly brace'); $this->whiteSpace();
		}
		if ($dump) return new CSSDeclarations();
		return $declarations;
	}

	function CSSPage()
	{
		if ($this->isValNext(ATKEYWORD,'page'))
		{
			$this->d('page');
			throw new MyException('page unsupported');
		}
		return false;
	}
	function CSSMedia()
	{
		if ($this->isValNext(ATKEYWORD,'media'))
		{
			$media = array();
			do
			{
				$this->whiteSpace();
				$medianame = $this->getTokenValNext(IDENT,'Expecting media name');
				$media[] = $medianame;
				$this->whiteSpace();
			}
			while($this->isValNext(DELIM,','));

			/*
media_query: [only | not]? <media_type> [ and <expression> ]*
expression: ( <media_feature> [: <value>]? )
media_type: all | aural | braille | handheld | print |
projection | screen | tty | tv | embossed
media_feature: width | min-width | max-width
| height | min-height | max-height
| device-width | min-device-width | max-device-width
| device-height | min-device-height | max-device-height
| device-aspect-ratio | min-device-aspect-ratio | max-device-aspect-ratio
| color | min-color | max-color
| color-index | min-color-index | max-color-index
| monochrome | min-monochrome | max-monochrome
| resolution | min-resolution | max-resolution
| scan | grid*/

			if ('and'==$this->isValNext(IDENT,'and'))
			{

				$this->whiteSpace();
				$this->expect(LPAR);
				$cond = $this->CSSDeclarations(RPAR);
				$this->expect(RPAR);
				$this->whiteSpace();
			}
			else {$cond=NULL;}

			$this->expect(LCBR,'Media block must be in curly braces');
			$this->whiteSpace();
			$rulesets = array();
			while(!$this->isNext(RCBR) && !$this->isEof())
			{
				$rulesets[] = $this->CSSRuleset();
			}

			$this->whiteSpace();
			return new CSSMedia($media,$rulesets,$cond);
		}
		return false;
	}

	function CSSCDC()
	{
		while(false!==$this->isInNext(S,CDC,CDO));
	}

	function CSSAtrule()
	{
		if (!$this->is(ATKEYWORD)) return NULL;
		$atrule = $this->getTokenVal();
		try {
			    if ($p = $this->CSSPage()) {return $p;}
			elseif ($p = $this->CSSMedia()) { return $p;}
			elseif ($p = $this->CSSDefine()) {$this->o->addVars($p); return NULL;}
			elseif ($this->CSSInclude()) {return NULL;}
			elseif ($this->CSSIf()) {return NULL;}

			$this->skipUpTo(SCOL,LCBR);
			if ($this->is(LCBR)) {$this->nextToken();$this->skipUpTo(RCBR);$this->nextToken();} else {$this->nextToken();}
			$this->whiteSpace();
			$this->error('unknown atrule "'.$atrule.'"');
		}
		catch(Exception $e){}
		return false;
	}

	function CSSAny()
	{
		while($this->is(ATKEYWORD))
		{
			if (false !== ($p = $this->CSSAtrule())) {return $p;}
		}

		if ($p = $this->CSSRuleset()) {return $p;}
		return false;
	}

	function parse($s)
	{
		$this->i = 0;
		$this->eof = strlen($s);
		$this->h = $s;
	  $this->nextToken(); //read first 1

	//	do
	//	{
	//		$lasti = $this->i;
	////////////////////////////////////////////////////////

	try {
			$this->CSSCharset(); $this->CSSCDC();
			while($this->CSSImport()) {;}
			$safe=2500;
			while($safe-- && (!$this->isEof()))
			{
				if ($p = $this->CSSAny()) $this->o->add($p);
				$this->CSSCDC();
			}

		}
		catch(Exception $e) { trigger_error($e->getMessage()); }


	////////////////////////////////////////////////////////

	//		d($this->nextToken());
	//	}
//		while($lasti != $this->i && $this->i < $end);
	//	if ($lasti==$this->i) trigger_error('internal parser error');
		return $this->o;
	}


	function isEof()
	{
		return $this->i >= $this->eof;
	}

	function nextToken()
	{
		if ($this->isEof()) {$this->nextToken = NULL;return;}

		$isat = $this->i;
		$keys=NULL;
		if (!$this->match(self::$tokenstring,$this->i,$keys))
		{
			$this->d('parseerr');
			throw new MyException('parse error');
		}

		$token = count($keys)-1;
		$tokenval = $keys[count($keys)-1];

		if ($token == STRINGS) {$tokenval = preg_replace('/\\\\(["\'\\\\])/','\1',substr($tokenval,1,-1));	} /* CHECK: remove escape chars! */
		elseif ($token == ATKEYWORD || $token==HASH) {$tokenval = substr($tokenval,1);}
		elseif ($token == URI) {$tokenval = preg_replace('/^url\(([\'"])(.*)\1\)|^url\((.*)\)/','\2\3',$tokenval);}
		elseif ($token == FUNC) {$tokenval = substr($tokenval,0,-1);} // remove "("


		$this->nextToken = array($token,$tokenval,$isat);
		if ($token==COMMENT || $token==COMMENTCPP)
		{
			if ($token==COMMENT)
			{
				//d($this->nextToken[1]{strlen($this->nextToken[1])-3},'beforelast');
				$this->backslashCommentHack = $this->nextToken[1]{strlen($this->nextToken[1])-3}=='\\';

			}
			$this->nextToken();
		}
	}

	static $tokens = array(
S=>             '[ \t\r\n\f]+',
URI=>           URId,
FUNC=>          FUNCd,
UNICODERANGE=> 'U\+[0-9A-F?]{1,6}(?:-[0-9A-F]{1,6})?',
IDENT=>         ident,
ATKEYWORD=>     ATKEYWORDd,
STRINGS=>        strings,
HASH=>          HASHd,
PERCENTAGE=>    PERCENTAGEd,
DIMENSION=>     DIMENSIONd,
NUMBER=>        num,
CDO=>           '\<\!--',        /* ************************************** */
CDC=>           '\-->',          /* ORDER must match constant definitions! */
SCOL=>          ';',             /* ************************************** */
LCBR=>          '\{',
RCBR=>          '\}',
LPAR=>          '\(',
RPAR=>          '\)',
LSBR=>          '\[',
RSBR=>          '\]',
COMMENT=>       '\/\*.*?(?:\*\/|$)',
INCLUDES=>      '\~=',
DASHMATCH=>     '\|=',
PREFIXMATCH=>   '\^=',
SUFFIXMATCH=>   '\$=',
SUBSTRINGMATCH=>'\*=',
COMMENTCPP=>    '\/\/[^\n]*',
DELIM=>         '.',

                );
};
