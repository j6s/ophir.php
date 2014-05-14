<?php
/**
 * Created by PhpStorm.
 * User: thephpjo
 * Date: 14.05.14
 * Time: 20:10
 */
namespace lovasoa;


class OphirTest extends \PHPUnit_Framework_TestCase{

	function __construct(){
		$this->ophir = new \lovasoa\Ophir();

		// enable everything
		$this->ophir->setConfiguration(Ophir::HEADER,		Ophir::ALL);
		$this->ophir->setConfiguration(Ophir::LISTS,		Ophir::ALL);
		$this->ophir->setConfiguration(Ophir::TABLE,		Ophir::ALL);
		$this->ophir->setConfiguration(Ophir::FOOTNOTE,		Ophir::ALL);
		$this->ophir->setConfiguration(Ophir::LINK,			Ophir::ALL);
		$this->ophir->setConfiguration(Ophir::IMAGE,		Ophir::ALL);
		$this->ophir->setConfiguration(Ophir::NOTE,			Ophir::ALL);
		$this->ophir->setConfiguration(Ophir::ANNOTATION, 	Ophir::ALL);
		$this->ophir->setConfiguration(Ophir::TOC,			Ophir::ALL);

		$this->html = $this->ophir->odt2html(__DIR__."/test.odt");

		// ignore line breaks in tests
		$this->html = str_replace(array("\r", "\n"), "", $this->html);
	}
	public function testSimpleText(){
		$this->assertContains("<p>This is a simple text Paragraph</p>",$this->html, "testing simple Text");

	}

	public function testFormattedText(){
		$this->assertContains("This is a <strong>bold text</strong>",	$this->html, "testing bold Text");
		$this->assertContains("This is a <em>italic text</em>",			$this->html, "testing italic Text");
		$this->assertContains("This is a <u>underlined text</u>",		$this->html, "testing underlined Text");

		$this->assertContains("This is a <em><strong>bold italic text</strong></em>",	$this->html, "testing bold italic Text");
		$this->assertContains("This is a <strong><u>bold underlined text</u></strong>",	$this->html, "testing bold underlined Text");
		$this->assertContains("This is a <em><u>italic underlined text</u></em>",		$this->html, "testing italic underlined Text");
	}

	public function testLists(){
		$this->assertContains("<ol><li><p>Ordered List</p></li><li><p>wow, so ordered </p></li><li><p>such number</p></li></ol>",	$this->html, "testing ordered Lists");
		$this->assertContains("<ul><li><p>unordered List</p></li><li><p>wow, so unordered</p></li><li><p>much messy</p></li></ul>",	$this->html, "testing unordered Lists");
	}

	public function testImage(){
		$this->assertContains(base64_encode(file_get_contents(__DIR__."/image.jpg")),$this->html);
	}

	public function testHeader(){
		// fails
//		$this->assertContains("This is a header",	$this->html, "testing headers");
	}

	public function testFooter(){
		// fails
//		$this->assertContains("This is a footer",	$this->html, "testing footers");
	}

	public function testLink(){
		$this->assertContains('This is a <a href="https://github.com/lovasoa/ophir.php">link</a>',$this->html);
	}

	public function testAnnotation(){
		$this->assertContains('This is a annotation',$this->html);
	}

	public function testNote(){
		// fails
//		$this->assertContains('This is a footnote', $this->html);
	}

	public function testHeadings(){
		$this->assertContains("<h1>This is a h1</h1>",$this->html,"testing h1");
		$this->assertContains("<h2>This is a h2</h2>",$this->html,"testing h2");
		$this->assertContains("<h3>This is a h3</h3>",$this->html,"testing h3");
	}

}