<h1>Ophir.php</h1>
<h2>PHP script that converts ODT to HTML</h2>
ophir.php is a lightweight script that parses an <b>open document</b> file and outputs a <b><i>simple</i> HTML</b> file, with very few tags (contrarily to most other tools that do the same).

<h2>Features</h2>
Currently, the script parses bold (b tag), italic (i tag), underline (u tag), quotations (blockquote tag), images (using data URIs), links, headings (h1, h2, ...), lists (ul and li), tables (table tr and td) annotations and footnotes.
Ophir.php can also <b>ignore</b> or <b>remove</b> some tags on demand. This can be useful if you want to extract <i>only unformatted text</i> from a document, or if you don't want tables, footnotes or annotations in the resulting HTML, or if the application that generated the ODT file produced unnecessary formatting informations ...
<i><b>This fork also is able to parse line-breaks (< br >), more to come</b></i>

<h2>Limitations</h2>
Everything that is not mentioned in the feature section is not supported.

<h2>How to use this script</h2>
This script requires libzip and XMLReader, that are usually installed by default with php5.
If you meet these requirements, just put ophir.php on your server, and use it like that:
<code>
<pre>
&lt;?php
require("ophir.php");

$OPHIR_CONF["footnote"] = 0; //Do not import footnotes
$OPHIR_CONF["annotation"] = 0; //Do not import annotations

$OPHIR_CONF["list"] = 1; //Import lists, but prints them as simple text (no ul or li tags will be generated)
$OPHIR_CONF["link"] = 1; //Import links, but prints them as simple text (only extract text from the links)

/*Available parameters are:
"header", "quote", "list", "table", "footnote", "link", "image", "note", and "annotation"
*/

echo odt2html("/path/to/file.odt");

?&gt;
</pre>
</code>


<h2>License</h2>
Ophir.php is under LGPLv3. Full license informations available at http://www.gnu.org/licenses/lgpl.html. 

<h2>Other scripts</h2>
This script was coded in one afternoon to answer to my personal needs. More professional tools, with more features, that produce uglier HTML files are available: 

 - http://drupal.org/sandbox/lovasoa/1460720 A drupal 7 module I created that uses ophir.php to import ODT files to Drupal.

 - http://odt2xhtml.eu.org/ (in PHP)

 - http://www.artofsolving.com/opensource/jodconverter (in java)

 -  http://www.openoffice.org/udk/python/python-bridge.html (in Python)