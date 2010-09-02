<?php

class CSSRuntimeError extends Exception {}

class CSSStylesheet
{
	/** CSSObject[] */ protected $objects = array();
	private $charset="ISO-8859-1";
	private $vars = array();

	function setCharset($c) {$this->charset = $c;}

	function addVars(CSSDeclarations $decs)
	{
		$defs = $decs->getValues();
		$this->vars = array_merge($this->vars,$defs);

	}
	function getVar($name)
	{
		if (!isset($this->vars[$name])) throw new CSSRuntimeError('unknown variable '.$name);

		assert('$this->vars[$name] instanceof CSSExpression');
		return $this->vars[$name]->toTerm();
	}

	function add(CSSObject $obj)
	{

		$this->objects[] = $obj;
	}
	function addSheet(CSSStylesheet $s)
	{
		$this->objects = array_merge($this->objects, $s->getObjects());
		$this->addVarsArray($s->getVars());
	}

	function getObjects() {return $this->objects;}
	function getVars() {return $this->vars;}
	function addVarsArray($v) {
		$this->vars = array_merge($this->vars, $v);
	}
}

interface CSSObject {}

class CSSImport implements CSSObject
{
	private $media,$uri;
	function __construct($uri,$media)
	{
		$this->media = $media;
		$this->uri = $uri;
	}
	function getImport() {return $this->import;}
	function getMedia() {return $this->media;}
}

class CSSRuleset implements CSSObject
{
	function __construct(CSSSelectors $sels,CSSDeclarations $decs)
	{
		$this->sels = $sels;
		$this->decs = $decs;
	}
	/* stupid, because getDeclarations is property of returned value as well */
	function getDeclarations() {return $this->decs;}
	/* same as above */
	function getSelectors() {return $this->sels;}
}

class CSSDeclarations
{
	private $decs = array();
	function add(CSSDeclaration $dec) {$this->decs[] = $dec;}
	function isEmpty() {return !count($this->decs);}

	function getValues()
	{
		$out = array();
		foreach($this->decs as $d)
		{
			$out[$d->getName()] = $d->getValue();
		}
		return $out;
	}

	function getDeclarations() {return $this->decs;}

	/** checks if ALL declarations have this filter equal to val */
	function queryFilterAll($type,$val)
	{
		foreach($this->decs as $d)
		{
			if ($d->queryFilter($type)!==$val) return false;
		}
		return true;
	}
}

class CSSMedia implements CSSObject
{
	protected $media,$rulesets,$cond;
	function __construct($media,$rulesets,$cond)
	{
		$this->media = $media;
		$this->rulesets = $rulesets;
		$this->cond = $cond;
	}

	function getMedia() {return $this->media;}
	function getRulesets() {return $this->rulesets;}
	function getConditions() {return $this->cond;}
}

abstract class CSSTerm
{
	protected $value;
	function __construct($value) {$this->value = $value;}
	function output() {return (string)$this->value;}
	function getValue() {return $this->value;}
	function __toString() {return $this->output();}
}


class CSSTermVar extends CSSTerm
{
	private $sheet,$name;
	function __construct(CSSStylesheet $sheet,$name) {$this->name = $name; $this->sheet = $sheet;}
	function output() {return $this->sheet->getVar($this->name)->output();}
	function getValue() {return $this->sheet->getVar($this->name)->getValue();}
	function toTerm()
	{
		$out = $this->sheet->getVar($this->name);
		if ($out instanceof CSSTermMath) return $out->toTerm(); return $out;
	}
	function __toString() {return '\$'.$this->name;}
}


class CSSTermMath extends CSSTerm
{
	private $expression;
	function __construct($expression) {assert('is_array($expression)'); $this->expression = $expression;}
	private function operate($op, CSSTerm $a,CSSTerm $b)
	{
		if ($a instanceof CSSTermMath || $a instanceof CSSTermVar) {$a = $a->toTerm();}
		if ($b instanceof CSSTermMath || $b instanceof CSSTermVar) {$b = $b->toTerm();}
		if ($a instanceof CSSTermMath || $a instanceof CSSTermVar) {$a = $a->toTerm();}
		if ($b instanceof CSSTermMath || $b instanceof CSSTermVar) {$b = $b->toTerm();}

		if ($op == '=')
		{
			return new CSSTermNumber($a->getValue() == $b->getValue());
		}
		if ($op == '>')
		{
			return new CSSTermNumber($a->getValue() > $b->getValue());
		}
		if ($op == '<')
		{
			return new CSSTermNumber($a->getValue() < $b->getValue());
		}

		if ($a instanceof CSSTermString || $b instanceof CSSTermString)
		{
			if ($op != '+') throw new CSSRuntimeError('invalid string operand');
			return new CSSTermString($a->getValue() . $b->getValue());
		}
		if (!($a instanceof CSSTermSigned && $b instanceof CSSTermSigned))
		{


			throw new CSSRuntimeError('invalid operands '.get_class($a).' '.get_class($b));
		}

		switch($op)
		{
			case '*': case '%': case '/':

				if ($op == '*') $res = $a->getValue() * $b->getValue();
				elseif ($op == '/') $res = $a->getValue() / $b->getValue();
				else $res = $a->getValue() % $b->getValue();

				if ($a->getUnit()=='' || $a->getUnit()=='%') {$unit = $b->getUnit();} else {$unit = $a->getUnit();}

				//d($a->getValue(),'a = '.$a->getUnit());
				//d($b->getValue(),'b = '.$b->getUnit());


				if ($unit=='px') $res = (int)$res;

				if ($unit=='%' || ($unit=='' && $a->getUnit()=='%')) return new CSSTermPercentage(($res*100.0).'%');
				if ($unit=='') return new CSSTermNumber($res);
				return new CSSTermDimension($res.$unit);

			default:
				if ($op == '+') $res = $a->getValue() + $b->getValue();
				else $res = $a->getValue() - $b->getValue();

				if ($a->getUnit()=='' || $a->getUnit()=='%') {$unit = $b->getUnit();} else {$unit = $a->getUnit();}


				//d($a->getValue(),'a = '.$a->getUnit());
				//d($b->getValue(),'b = '.$b->getUnit());


				if ($unit=='px') $res = (int)$res;

				if ($unit=='%') return new CSSTermPercentage(($res*100.0).'%');
				if ($unit=='') return new CSSTermNumber($res);
				return new CSSTermDimension($res.$unit);
		}
		//d($a->getUnit());
		return new CSSTermNumber($a->getValue() + $b->getValue());
	}

/* +/-
abslength	[in,pt,cm]
rellength	[em,ex]
pxlength	[px]
numbers		[]
x strings		[]
*,/
any * scalar
*/

	private function calculate()
	{
		$v = $this->expression;

		if (count($v)==1) return $v[0];
		for($i=1;$i<count($v);$i+=2)
		{
			if (strchr("*/%",$v[$i]))
			{
				$v[$i-1] = $this->operate($v[$i],$v[$i-1],$v[$i+1]); array_splice($v,$i,2);
				if (count($v)==1) return $v[0];
				$i-=2;
			}
		}
		for($i=1;$i<count($v);$i+=2)
		{
			if (strchr("+-",$v[$i]))
			{
				$v[$i-1] = $this->operate($v[$i],$v[$i-1],$v[$i+1]); array_splice($v,$i,2);
				if (count($v)==1) return $v[0];
				$i-=2;
			}
		}

		if (count($v)==1) return $v[0];
		for($i=1;$i<count($v);$i+=2)
		{
			if (strchr("<>=",$v[$i]))
			{
				$v[$i-1] = $this->operate($v[$i],$v[$i-1],$v[$i+1]); array_splice($v,$i,2);
				if (count($v)==1) return $v[0];
				$i-=2;
			}
		}
		/*for($i=1;$i<count($v);$i+=2)
		{
			if ("?"==$v[$i]) {$v[$i-1] = $this->operate($v[$i],$v[$i-1],$v[$i+1]); array_splice($v,$i,2);}
		}*/
		return $v[0];
	}

	function output()
	{
		if ($this->termbusy) throw new CSSRuntimeError('circular dependency in math expression');
		$this->termbusy = true;
		$o = $this->calculate()->output();
		$this->termbusy = false;
		return $o;
	}
	function getValue() {return $this->calculate()->getValue();}

	private $termbusy;
	function toTerm()
	{
		return $this->calculate();
	}
	function __toString() {return 'expr';}
}

class CSSTermString extends CSSTerm
{
	function output() {return  '"'.$this->value.'"';} // CHECK: buggy, escape
}
class CSSTermIdent extends CSSTerm {}
class CSSTermURI extends CSSTerm
{
	function output() {return 'url('.$this->value.')';} // CHECK: buggy, escape, care for ie5mac
}

class CSSTermColor extends CSSTerm
{
	function output()
	{
		return '#'.preg_replace('/^(.)\1(.)\2(.)\3$/','\1\2\3',$this->value);
	}
}

class CSSTermSigned extends CSSTerm
{
	protected $sign;
	function setSign($sign) {$this->sign = $sign;}
	function output() {return ($this->sign=='-'?'-':'').$this->value;}
	function getValue() {return $this->output();}
	function getUnit() {return preg_replace('/[0-9.-]*(.*)$/','\1',$this->value);}
}

class CSSTermNumber extends CSSTermSigned {}
class CSSTermPercentage extends CSSTermSigned
{
	function getValue() {return $this->output()/100.0;}
}
class CSSTermDimension extends CSSTermSigned {}

class CSSTermFunction extends CSSTerm
{
	function __construct($fname,$fargs)
	{
		$this->fname = $fname;
		$this->fargs = $fargs;
	}

	function getName() 	{return $this->fname;}
	function getValue() {	throw new CSSRuntimeError('Unable to get value of '.$this->getName().'() function.');	}

	function output()
	{
		$o = $this->fname.'(';
		foreach($this->fargs->getTerms() as $t)
		{
			$o .= $t[0].$t[1]->output();
		}
		return $o.')';
	}
}

class CSSSelectors
{
	private $sels,$filter;
	function add(CSSSelector $sel)
	{
		$this->sels[] = $sel;
	}

	function getSelectors() {return $this->sels;}
}

class CSSSelector
{
	private $sels = array();
	function add($combinator,CSSSimpleSelector $s)
	{
		$this->sels[] = array($combinator,$s);
	}

	/* should actually return array of CSSSimpleSelectors. Make combinator a CSSSimpleSelector? */
	function getSimpleSelectors() {return $this->sels;}
}

class CSSSimpleSelector
{
	function __construct($element,array $qualifiers)
	{
		$this->element = $element;
		$this->qualifiers = $qualifiers;
	}

	function getElement() {return $this->element;}
	function getQualifiers() {return $this->qualifiers;}
}



class CSSExpression
{
	private $exprs = array();

	function __construct($op=NULL, $exp=NULL)
	{
		if ($exp!==NULL) $this->add($op,$exp);
	}
	function add($op, $exp)
	{
		assert('!is_array($exp)');
		assert('$exp instanceof CSSTerm');
		$this->exprs[] = array($op,$exp);
	}

	function toTerm()
	{
		if (count($this->exprs)>1) {throw new CSSRuntimeError('multi-term expression cast');}
		return $this->exprs[0][1];
	}

	function getTerms()
	{
		return $this->exprs;
	}

	function __toString()
	{
		$o='';
		foreach($this->exprs as $e) {$o.= $e[0].$e[1]->__toString();}
		return $o;
	}
}

class CSSDeclaration
{
	function __construct($propname,CSSExpression $expr,$pri)
	{
		$this->propname = $propname;
		$this->expr = $expr;
		$this->pri = $pri;
	}

	function isImportant() {return $this->pri;}
	function getName() {return $this->propname;}
	/* stupid name? */
	function getValue() {return $this->expr;}

	function __toString()
	{
		return $this->getName().':'.$this->expr->__toString();
	}


	private $filter = array();
	function setFilter($type,$val)
	{
		$this->filter[$type] = $val;
	}

	function queryFilter($type)
	{
		return isset($this->filter[$type])?$this->filter[$type]:NULL;
	}

	function unsetFilter($type)
	{
		unset($this->filter[$type]);
	}
}
