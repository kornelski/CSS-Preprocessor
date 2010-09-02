<?php
define('nonascii','[^\0-\177]');
define('unicode','(?:\\\\[0-9a-f]{1,6}[ \n\r\t\f]?)');
define('escape','(?:'.unicode.'|\\\\[^\r\n\f0-9a-f])');//\4177777
define('nmstart','(?:[a-zA-Z_]|'.nonascii.'|'.escape.')');
define('nmchar','(?:[a-zA-Z0-9_-]|'.nonascii.'|'.escape.')');
define('ident','-?'.nmstart.nmchar.'*');
define('name',nmchar.'+');
define('num','(?:[0-9]*\.[0-9]+|[0-9]+)');
define('nl','\r\n|[\n\r\f]');
define('w','[ \t\r\n\f]*');
define('string1','\"(?:[\t !#$%&(-~]|\\\\'.nl.'|\'|'.nonascii.'|'.escape.')*(?:\"|$)');
define('string2','\'(?:[\t !#$%&(-~]|\\\\'.nl.'|\"|'.nonascii.'|'.escape.')*(?:\'|$)');
define('strings','(?:'.string1.'|'.string2.')');

define('ATKEYWORDd','\@'.ident);
define('HASHd','\#'.name);
define('PERCENTAGEd',num.'%');
define('DIMENSIONd',num.ident);
define('URId','url\('.w.strings.w.'\)|url\('.w.'(?:[!#$%&*-~]|'.nonascii.'|'.escape.')*'.w.'\)');
define('FUNCd',ident.'\(');

define('S',0);
define('URI',1);
define('FUNC',2);
define('UNICODERANGE',3);
define('IDENT',4);
define('ATKEYWORD',5);
define('STRINGS',6);
define('HASH',7);
define('PERCENTAGE',8);
define('DIMENSION',9);
define('NUMBER',10);
define('CDO',11);
define('CDC',12);
define('SCOL',13);
define('LCBR',14);
define('RCBR',15);
define('LPAR',16);
define('RPAR',17);
define('LSBR',18);
define('RSBR',19);
define('COMMENT',20);
define('INCLUDES',21);
define('DASHMATCH',22);
define('PREFIXMATCH' 	,23);
define('SUFFIXMATCH' 	,24);
define('SUBSTRINGMATCH' 	,25);
define('DELIM',27);
define('COMMENTCPP',26);


