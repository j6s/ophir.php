<?php
/**
 * @file
 * ODT to HTML conversion functions
 *
 * Two functions are defined here : the first extracts the contents as XML from
 * the ODT file. The second parses the XML to produce HTML.
 */

namespace lovasoa;
use \XMLReader;

class Ophir
{
	/**
	 * configuration options
	 */
	const ALL    = 2;
	const SIMPLE = 1;
	const NONE   = 0;

	const HEADINGS         	= "HEADINGS";
	const LISTS             = "LISTS";
	const TABLE             = "TABLE";
	const FOOTNOTE          = "FOOTNOTE";
	const LINK              = "LINK";
	const IMAGE             = "IMAGE";
	const NOTE              = "NOTE";
	const ANNOTATION        = "ANNOTATION";
	const TOC               = "TOC";
	const TABLE_OF_CONTENTS = Ophir::TOC;

	const IMAGEFOLDER		= "IMAGEFOLDER";
	const IMAGE_FOLDER		= Ophir::IMAGEFOLDER;

	/**
	 * Default configuration
	 * @var array
	 */
	private $configuration = array(
		Ophir::HEADINGS   => Ophir::ALL,
		Ophir::LISTS      => Ophir::ALL,
		Ophir::TABLE      => Ophir::ALL,
		Ophir::FOOTNOTE   => Ophir::ALL,
		Ophir::LINK       => Ophir::ALL,
		Ophir::IMAGE      => Ophir::ALL,
		Ophir::ANNOTATION => Ophir::ALL,
		Ophir::TOC        => Ophir::NONE,
	);

	public function getConfiguration(){
		return $this->configuration;
	}


	/**
	 * @param String $option
	 * @param int    $value
	 *
	 * @return \lovasoa\Ophir $this
	 */
	public function setConfiguration($option, $value = Ophir::ALL)
	{
		$this->configuration[$option] = $value;
		return $this;
	}


	/*
	 * Function that parses the XML and outputs HTML. If $xml is not provided,
	 * extract content.xml from $odt_file
	 */
	public function odt2html($odt_file, $xml_string=NULL) {

		$xml = new XMLReader();

		if ($xml_string===NULL){
			if (@$xml->open('zip://'.$odt_file.'#content.xml') === FALSE) {
				$this->error("Unable to read file contents.");
				return false;
			}
		}else{
			if(@$xml->xml($xml_string)===FALSE) {
				$this->error("Invalid file contents.");
				return false;
			}
		}

		//Now, convert the xml from a string to an
		$html = "";

		$elements_tree = array();

		static $styles = array("Quotations" => array("tags" => array("blockquote")));

		$footnotes = "";

		$translation_table = array ();
		$translation_table['draw:frame'] = 'div class="odt-frame"';
		if ($this->configuration[Ophir::LISTS] === Ophir::NONE) $translation_table["text:list"] = FALSE;
		elseif ($this->configuration[Ophir::LISTS] === Ophir::ALL) {
			$translation_table["text:list"] = "ul";
			$translation_table["text:list-item"] = "li";
		}
		if ($this->configuration[Ophir::TABLE] === Ophir::NONE) $translation_table["table:table"] = FALSE;
		elseif ($this->configuration[Ophir::TABLE] === Ophir::ALL) {
			$translation_table["table:table"] = "table cellspacing=0 cellpadding=0 border=1";
			$translation_table["table:table-row"] = "tr";
			$translation_table["table:table-cell"] = "td";
		}
		if ($this->configuration[Ophir::TOC] === Ophir::NONE) $translation_table['text:table-of-content'] = FALSE;
		elseif ($this->configuration[Ophir::TOC] === Ophir::ALL) {
			$translation_table['text:table-of-content'] = 'div class="odt-table-of-contents"';
		}
		$translation_table['text:line-break'] = 'br';

		$odtStyles = array();

		while ($xml->read()) {
			$opened_tags = array();//This array will contain the HTML tags opened in every iteration

			if ($xml->nodeType === XMLReader::END_ELEMENT) {//Handle a closing tag
				if (empty($elements_tree)) continue;
				do {
					$element = array_pop($elements_tree);
					if ($element && $element["tags"]) {
						//Close opened tags
						$element["tags"] = array_reverse($element["tags"]);
						foreach ($element["tags"] as $HTML_tag) {
							//$html.= "<font style='color:red' title='Closing $HTML_tag, from $element[name]. Current element is " .($xml->name). "'>©</font>";
							$HTML_tag = current(explode(" ", $HTML_tag));
							$html .= "</" . $HTML_tag . ">";
						}
					}
				} while ($xml->name !== $element["name"] && $element); //Close every opened tags. This should also handle malformed XML files
				continue;
			}
			elseif (in_array($xml->nodeType,
				array(XMLReader::ELEMENT,
					XMLReader::TEXT,
					XMLReader::SIGNIFICANT_WHITESPACE))
			) {//Handle tags
				switch ($xml->name) {
					case "#text"://Text
						$html .= htmlspecialchars($xml->value);
						break;
					case "text:h"://Title
						if ($this->configuration[Ophir::HEADINGS] === Ophir::NONE) {
							$xml->next();
							break;
						}
						elseif ($this->configuration[Ophir::HEADINGS] === Ophir::SIMPLE) break;
						$n = $xml->getAttribute("text:outline-level");
						if ($n>6) $n=6;
						$opened_tags[] = "h$n";
						$html .= "\n\n<h$n>";
						break;

					case "text:p"://Paragraph
						//Just convert odf <text:p> to html <p>
						$tags = @$styles[$xml->getAttribute("text:style-name")]["tags"];
						if (!($tags && !in_array("blockquote", $tags))) {
							// Do not print a <p> immediatly after or before a <blockquote>
							$html .= "\n<p>";
							if ($xml->isEmptyElement) $html .= "</p>";
							else $opened_tags[] = "p";
						}
						break;

					case "text:a":
						if ($this->configuration[Ophir::LINK] === Ophir::NONE) {
							$xml->next();
							break;
						}
						elseif ($this->configuration[Ophir::LINK] === Ophir::SIMPLE) break;
						$href = $xml->getAttribute("xlink:href");
						$opened_tags[] = 'a';
						$html .= '<a href="' . $href . '">';
						break;

					case "draw:image":
						if ($this->configuration[Ophir::IMAGE] === Ophir::NONE) {
							$xml->next();
							break;
						}
						elseif ($this->configuration[Ophir::IMAGE] === Ophir::NONE) break;

						$image_file = 'zip://' . $odt_file . '#' . $xml->getAttribute("xlink:href");
						if (isset($this->configuration[Ophir::IMAGEFOLDER]) &&
							is_dir($this->configuration[Ophir::IMAGEFOLDER]) ) {
							if ($this->is_image($image_file)) {
								$image_to_save = $this->configuration[Ophir::IMAGEFOLDER] . '/' . basename($image_file);
								if ( !($src = $this->copy_file($image_file, $image_to_save))) {
									$this->error("Unable to move image file");
									break;
								}
							} else {
								$this->error("Found invalid image file.");
								break;
							}
						}
						else {
							//$this->error('Unable to save the image. Creating a data URL. Image saved directly in the body.F');
							$src = 'data:image;base64,' . base64_encode(file_get_contents($image_file));
						}
						$html .= "\n<img src=\"$src\" />";
						break;

					case "style:style":
						$name = $xml->getAttribute("style:name");
						$parent = $xml->getAttribute("style:parent-style-name");
						if (array_key_exists($parent, $styles)) $styles[$name] = $styles[$parent]; //Not optimal

						if ($xml->isEmptyElement) break; //We can't handle that at the moment
						while ( $xml->read() && //Read one tag
							($xml->name !== "style:style" || $xml->nodeType != XMLReader::END_ELEMENT) //Stop on </style:style>
						) {
							if ($xml->name === "style:text-properties") {
								if ($xml->getAttribute("fo:font-style") == "italic"  && !@in_array("em",$styles[$name]["tags"]))
									$styles[$name]["tags"][] = "em"; //Creates the style and add <em> to its tags

								if ($xml->getAttribute("fo:font-weight") == "bold" && !@in_array("strong",$styles[$name]["tags"]))
									$styles[$name]["tags"][] = "strong"; //Creates the style and add <strong> to its tags

								if ($xml->getAttribute("style:text-underline-style") == "solid" && !@in_array("u",$styles[$name]["tags"]))
									$styles[$name]["tags"][] = "u"; //Creates the style and add <u> to its tags

							}
						}
						break;
					case "text:note":
						if ($this->configuration[Ophir::FOOTNOTE] === Ophir::NONE) {
							$xml->next();
							break;
						}
						elseif ($this->configuration[Ophir::FOOTNOTE] === Ophir::SIMPLE) break;
						$note_id = $xml->getAttribute("text:id");
						$note_name = "Note";
						while ( $xml->read() && //Read one tag
							($xml->name != "text:note" || $xml->nodeType != XMLReader::END_ELEMENT) //Stop on </style:style>
						) {
							if ($xml->name === "text:note-citation" &&
								$xml->nodeType === XMLReader::ELEMENT)
								$note_name = $xml->readString();
							elseif ($xml->name === "text:note-body" &&
								$xml->nodeType == XMLReader::ELEMENT) {
								$note_content = $this->odt2html($odt_file, $xml->readOuterXML());
							}
						}

						$html .= "<sup><a href=\"#odt-footnote-$note_id\" class=\"odt-footnote-anchor\" name=\"anchor-odt-$note_id\">$note_name</a></sup>";

						$footnotes .= "\n" . '<div class="odt-footnote" id="odt-footnote-' . $note_id . '" >';
						$footnotes .= '<a class="footnote-name" href="#anchor-odt-' . $note_id . '">' . $note_name . ' .</a> ';
						$footnotes .= $note_content;
						$footnotes .= '</div>' . "\n";
						break;

					case "text:list-style":
						$stylename = $xml->getAttribute("style:name");
						$xml->read();

						if($xml->name == "text:list-level-style-bullet"){
							$odtStyles[$stylename] = "ul";
						} elseif($xml->name == "text:list-level-style-number"){
							$odtStyles[$stylename] = "ol";
						}

						break;

					case "office:annotation":
						if ($this->configuration[Ophir::ANNOTATION] === Ophir::NONE) {
							$xml->next();
							break;
						}
						elseif ($this->configuration[Ophir::ANNOTATION] === Ophir::SIMPLE) break;
						$annotation_id = (isset($annotation_id))?$annotation_id+1:1;
						$annotation_content = "";
						$annotation_creator = "Anonymous";
						$annotation_date = "";
						do {
							$xml->read();
							if ($xml->name === "dc:creator" &&
									$xml->nodeType == XMLReader::ELEMENT)
								$annotation_creator = $xml->readString();
							elseif ($xml->name === "dc:date" &&
											$xml->nodeType === XMLReader::ELEMENT) {
								$annotation_date = date("jS \of F Y, H\h i\m", strtotime($xml->readString()));
							}
							elseif ($xml->nodeType === XMLReader::ELEMENT) {
								$annotation_content .= $xml->readString();
								$xml->next();
							}
						} while (!($xml->name === "office:annotation" &&
							$xml->nodeType === XMLReader::END_ELEMENT));//End of the note

						$html .= '<sup><a href="#odt-annotation-' . $annotation_id . '" name="anchor-odt-annotation-' . $annotation_id . '" title="Annotation (' . $annotation_creator . ')">(' . $annotation_id . ')</a></sup>';
						$footnotes .= "\n" . '<div class="odt-annotation" id="odt-annotation-' . $annotation_id . '" >';
						$footnotes .= '<a class="annotation-name" href="#anchor-odt-annotation-' . $annotation_id . '"> (' . $annotation_id . ')&nbsp;</a>';
						$footnotes .= "\n" . '<b>' . $annotation_creator . ' (<i>' . $annotation_date . '</i>)</b> :';
						$footnotes .= "\n" . '<div class="odt-annotation-content">' . $annotation_content . '</div>';
						$footnotes .= '</div>' . "\n";
						break;

					case "text:list":
						if(!empty($odtStyles[$xml->getAttribute("text:style-name")])){
							$translation_table["text:list"] = $odtStyles[$xml->getAttribute("text:style-name")];
						} else {
							$translation_table["text:list"] = "ul";
						}

					default:
						if (array_key_exists($xml->name, $translation_table)) {
							if ($translation_table[$xml->name]===FALSE) {
								$xml->next();
								break;
							}
							$tag = explode(" ", $translation_table[$xml->name], 1);
							//$tag[0] is the tag name, other indexes are attributes
							$opened_tags[] = $tag[0];
							$html .= "\n<" . $translation_table[$xml->name] . ">";
						}
				}
			}

			if ($xml->nodeType === XMLReader::ELEMENT  &&
				!($xml->isEmptyElement) ) { //Opening tag
				$current_element_style = $xml->getAttribute("text:style-name");
				if ($current_element_style &&
					isset($styles[$current_element_style])) {
					//Styling tags management
					foreach ($styles[$current_element_style]["tags"] as $HTML_tag) {
						$html .= "<" . $HTML_tag . ">";
						$opened_tags[] = $HTML_tag;
					}
				}
				$elements_tree[] = array("name" => $xml->name,
										 "tags" => $opened_tags);
			}

		}
		return $html . $footnotes;
	}


	/**
	 * @param String $file
	 *
	 * @return bool
	 */
	private function is_image($file)
	{
		$image_extensions = array("jpg", "jpeg", "png", "gif", "svg");
		$ext              = pathinfo($file, PATHINFO_EXTENSION);
		if (!in_array($ext, $image_extensions)) return FALSE;
		return (strpos(@mime_content_type($file), 'image') === 0);
	}

	/**
	 * @param String $from
	 * @param String $to
	 *
	 * @return bool|string
	 */
	private function copy_file($from, $to)
	{
		if (function_exists('file_unmanaged_copy')) {
			$filename = file_unmanaged_save_data(file_get_contents($from), $to, FILE_EXISTS_REPLACE);
			return ($filename) ? file_create_url($filename) : FALSE;
		} else {
			if (file_exists($to)) {
				if (crc32(file_get_contents($from)) === crc32(file_get_contents($from))) return $to;
				$i  = pathinfo($to);
				$to = $i['dirname'] . '/' . $i['filename'] . time() . '.' . $i['extension'];
			}
			return (copy($from, $to)) ? $to : FALSE;
		}
	}

	/**
	 * @param String $error
	 */
	private function error($error){
		if (function_exists("drupal_set_message")){
			drupal_set_message($error, 'error');
		}else{
			echo '<div style="color:red;font-size:2em;">' . $error . '</div>';
		}
	}
} 
