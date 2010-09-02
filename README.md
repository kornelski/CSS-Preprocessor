#An old CSS preprocessor

Adds variable definitions:

    @define
    {
      variable: 100px;
    }
  
and expressions:

    div {
      width: (variable * 2 - 10px);
    }

It can output browser-compatible minified CSS2.1 or formatted and syntax-colored HTML with the stylesheet.

It's not a quick'n'dirty hack, but a proper recursive descent parser that parses (sort-of superset) of CSS2.1 grammar (based on now-outdated CR 2004-02-25 grammar) and implements CSS-specific error recovery.

##Todo

* Support for unicode escapes
* Update of CSS grammar
* [lots more…](http://pornel.net/css)

I'm not planning to work on this in forseeable future, so *fork it!*

----

## License

The MIT License

©2010 pornel@pornel.net

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.