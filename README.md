[![Build Status](https://travis-ci.org/thephpjo/ophir.php.svg?branch=dev)](https://travis-ci.org/thephpjo/ophir.php)
# Ophir.php
## PHP script that converts ODT to HTML
ophir.php is a lightweight script that parses an <b>open document</b> file and outputs a <b><i>simple</i> HTML</b> file, with very few tags (contrarily to most other tools that do the same thing).

## Features
Currently, the script can convert the following:
 - bold (```<strong>``` tag)
 - italic (```<i>``` tag)
 - underline (```<u>``` tag)
 - quotations (```<blockquote>``` tag)
 - images (using data URIs)
 - links
 - headings (h1, h2, ...)
 - lists (ul and li)
 - tables (table tr and td)
 - annotations
 - footnotes.

Ophir.php can also **ignore** or **remove** some tags on demand. This can be useful if you want to extract *only unformatted text* from a document, or if you don't want tables, footnotes or annotations in the resulting HTML, or if the application that generated the ODT file produced unnecessary formatting informations ...

## Limitations
Everything that is not mentioned in the feature section is not supported.

## How to use this script
This script requires libzip and XMLReader, that are usually installed by default with php5.
If you meet these requirements, just put ophir.php on your server, and use it like that:

```php
require("src/ophir.php");

$ophir = new \lovasoa\Ophir();

$ophir->setConfiguration(Ophir::FOOTNOTE,		Ophir::NONE); //Do not import footnotes
$ophir->setConfiguration(Ophir::ANNOTATION,		Ophir::NONE); //Do not import annotations

$ophir->setConfiguration(Ophir::LISTS,			Ophir::SIMPLE); //Import lists, but prints them as simple text (no ul or li tags will be generated)
$ophir->setConfiguration(Ophir::LINK,			Ophir::SIMPLE); //Import links, but prints them as simple text (only extract text from the links)

$ophir->setConfiguration(Ophir::IMAGE,			Ophir::ALL);	// Display all the images

/*
Available features are:
Ophir::HEADINGS
Ophir::LISTS
Ophir::TABLE
Ophir::FOOTNOTE
Ophir::LINK
Ophir::IMAGE
Ophir::NOTE
Ophir::ANNOTATION
Ophir::TABLE_OF_CONTENTS

Available modes are:
Ophir::ALL 		// print feature completely
Ophir::SIMPLE 	// only print text-content
Ophir::NONE 	// ignore: Do not echo content

$ophir->setConfiguration({FEATURE},{MODE});
*/

echo $ophir->odt2html("/path/to/file.odt");
```


## License
Ophir.php is under LGPLv3.
You can use it in both your free and proprietary software projects, but if you change some of the code, you have to share your improvements.
Full license informations available at http://www.gnu.org/licenses/lgpl.html. 

## Other scripts
This script was coded in one afternoon to answer to my personal needs. More professional tools, with more features, that produce uglier HTML files are available: 

 - https://github.com/lovasoa/ophir_odt_import A drupal 7 module I created that uses ophir.php to import ODT files to Drupal.

 - http://odt2xhtml.eu.org/ (in PHP)

 - http://www.artofsolving.com/opensource/jodconverter (in java)

 -  http://www.openoffice.org/udk/python/python-bridge.html (in Python)
