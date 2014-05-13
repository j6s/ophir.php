<?php
/**
 * @file
 * ODT to HTML conversion functions
 *
 * Two functions are defined here : the first extracts the contents as XML from
 * the ODT file. The second parses the XML to produce HTML.
 */

/* Configuration.
 0 : do not parse, do not print
 1 : print as simple text (do not apply any HTML tag or style)
 2 : print  and apply all supported HTML tags and styles
 */
$_ophir_odt_import_conf = array(
  "features" => array (
    "header" => 2,
    "list" => 2,
    "table" => 2,
    "footnote" => 2,
    "link" => 2,
    "image" => 2,
    "note" => 2,
    "annotation" => 2,
    'table of contents' => 0,
    ),
  "images_folder" => "images"
);

// Export the configuration variable that will be overridden by library users
$OPHIR_CONF = $_ophir_odt_import_conf;

function ophir_is_image ($file) {
    $image_extensions = array("jpg", "jpeg", "png", "gif", "svg");
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    if (!in_array($ext, $image_extensions)) return FALSE;
    return (strpos(@mime_content_type($file), 'image') === 0);
}

function ophir_copy_file($from, $to) {
  if (function_exists('file_unmanaged_copy')){
    $filename = file_unmanaged_save_data(file_get_contents($from), $to, FILE_EXISTS_REPLACE);
    return ($filename) ? file_create_url($filename) : false;
  }else {
    if (file_exists($to)) {
      if (crc32(file_get_contents($from)) === crc32(file_get_contents($from))) return $to;
      $i = pathinfo($to);
      $to = $i['dirname'] . '/' . $i['filename'] . time() . '.' . $i['extension'];
    }
    return (copy($from, $to)) ? $to : FALSE;
  }
}

function ophir_error($error){
  if (function_exists("drupal_set_message")){
        drupal_set_message($error, 'error');
  }else{
    echo '<div style="color:red;font-size:2em;">' . $error . '</div>';
  }
}

/*
 * Function that parses the XML and outputs HTML. If $xml is not provided,
 * extract content.xml from $odt_file
 */
function odt2html($odt_file, $xml_string=NULL) {
  global $_ophir_odt_import_conf;
  
  $xml = new XMLReader();

  if ($xml_string===NULL){
    if (@$xml->open('zip://'.$odt_file.'#content.xml') === FALSE) {
      ophir_error("Unable to read file contents.");
      return false;
    }
  }else{
    if(@$xml->xml($xml_string)===FALSE) {
      ophir_error("Invalid file contents.");
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
  if ($_ophir_odt_import_conf["features"]["list"]===0) $translation_table["text:list"] = FALSE;
  elseif ($_ophir_odt_import_conf["features"]["list"]===2) {
    $translation_table["text:list"] = "ul";
    $translation_table["text:list-item"] = "li";
  }
  if ($_ophir_odt_import_conf["features"]["table"]===0) $translation_table["table:table"] = FALSE;
  elseif ($_ophir_odt_import_conf["features"]["table"]===2) {
    $translation_table["table:table"] = "table cellspacing=0 cellpadding=0 border=1";
    $translation_table["table:table-row"] = "tr";
    $translation_table["table:table-cell"] = "td";
  }
  if ($_ophir_odt_import_conf["features"]["table of contents"]===0) $translation_table['text:table-of-content'] = FALSE;
  elseif ($_ophir_odt_import_conf["features"]["table of contents"]===2) {
    $translation_table['text:table-of-content'] = 'div class="odt-table-of-contents"';
  }
 $translation_table['text:line-break'] = 'br';

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
          if ($_ophir_odt_import_conf["features"]["header"]===0) {
            $xml->next();
            break;
          }
          elseif ($_ophir_odt_import_conf["features"]["header"]===1) break;
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
            $opened_tags[] = "p";
            $html .= "\n<p>";
          }
          break;

        case "text:a":
          if ($_ophir_odt_import_conf["features"]["link"]===0) {
            $xml->next();
            break;
          }
          elseif ($_ophir_odt_import_conf["features"]["link"]===1) break;
          $href = $xml->getAttribute("xlink:href");
          $opened_tags[] = 'a';
          $html .= '<a href="' . $href . '">';
          break;

        case "draw:image":
          if ($_ophir_odt_import_conf["features"]["image"]===0) {
            $xml->next();
            break;
          }
          elseif ($_ophir_odt_import_conf["features"]["image"]===1) break;

          $image_file = 'zip://' . $odt_file . '#' . $xml->getAttribute("xlink:href");
          if (isset($_ophir_odt_import_conf["images_folder"]) &&
              is_dir($_ophir_odt_import_conf["images_folder"]) ) {
            if (ophir_is_image($image_file)) {
              $image_to_save = $_ophir_odt_import_conf["images_folder"] . '/' . basename($image_file);
              if ( !($src = ophir_copy_file ($image_file, $image_to_save))) {
                ophir_error("Unable to move image file");
                break;
              } 
            } else {
              ophir_error("Found invalid image file.");
              break;
            } 
          }
          else {
            //ophir_error('Unable to save the image. Creating a data URL. Image saved directly in the body.F');
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
              ($xml->name != "style:style" || $xml->nodeType != XMLReader::END_ELEMENT) //Stop on </style:style>
            ) {
            if ($xml->name == "style:text-properties") {
              if ($xml->getAttribute("fo:font-style") == "italic")
                  $styles[$name]["tags"][] = "em"; //Creates the style and add <em> to its tags

              if ($xml->getAttribute("fo:font-weight") == "bold")
                  $styles[$name]["tags"][] = "strong"; //Creates the style and add <strong> to its tags

              if ($xml->getAttribute("style:text-underline-style") == "solid")
                  $styles[$name]["tags"][] = "u"; //Creates the style and add <u> to its tags

            }
          }
          break;
        case "text:note":
          if ($_ophir_odt_import_conf["features"]["note"]===0) {
            $xml->next();
            break;
          }
          elseif ($_ophir_odt_import_conf["features"]["note"]===1) break;
          $note_id = $xml->getAttribute("text:id");
          $note_name = "Note";
          while ( $xml->read() && //Read one tag
              ($xml->name != "text:note" || $xml->nodeType != XMLReader::END_ELEMENT) //Stop on </style:style>
            ) {
            if ($xml->name=="text:note-citation" &&
            $xml->nodeType == XMLReader::ELEMENT)
              $note_name = $xml->readString();
            elseif ($xml->name=="text:note-body" &&
            $xml->nodeType == XMLReader::ELEMENT) {
              $note_content = odt2html($odt_file, $xml->readOuterXML());
            }
          }

          $html .= "<sup><a href=\"#odt-footnote-$note_id\" class=\"odt-footnote-anchor\" name=\"anchor-odt-$note_id\">$note_name</a></sup>";

          $footnotes .= "\n" . '<div class="odt-footnote" id="odt-footnote-' . $note_id . '" >';
          $footnotes .= '<a class="footnote-name" href="#anchor-odt-' . $note_id . '">' . $note_name . ' .</a> ';
          $footnotes .= $note_content;
          $footnotes .= '</div>' . "\n";
          break;

        case "office:annotation":
          if ($_ophir_odt_import_conf["features"]["annotation"]===0) {
    $xml->next();
    break;
    }
          elseif ($_ophir_odt_import_conf["features"]["annotation"]===1) break;
          $annotation_id = (isset($annotation_id))?$annotation_id+1:1;
          $annotation_content = "";
          $annotation_creator = "Anonymous";
          $annotation_date = "";
          do{
            $xml->read();
            if ($xml->name=="dc:creator" &&
            $xml->nodeType == XMLReader::ELEMENT)
              $annotation_creator = $xml->readString();
            elseif ($xml->name=="dc:date" &&
            $xml->nodeType == XMLReader::ELEMENT) {
              $annotation_date = date("jS \of F Y, H\h i\m", strtotime($xml->readString()));
            }
            elseif ($xml->nodeType == XMLReader::ELEMENT) {
              $annotation_content .= $xml->readString();
              $xml->next();
            }
          }while (!($xml->name === "office:annotation" &&
            $xml->nodeType === XMLReader::END_ELEMENT));//End of the note

          $html .= '<sup><a href="#odt-annotation-' . $annotation_id . '" name="anchor-odt-annotation-' . $annotation_id . '" title="Annotation (' . $annotation_creator . ')">(' . $annotation_id . ')</a></sup>';
          $footnotes .= "\n" . '<div class="odt-annotation" id="odt-annotation-' . $annotation_id . '" >';
          $footnotes .= '<a class="annotation-name" href="#anchor-odt-annotation-' . $annotation_id . '"> (' . $annotation_id . ')&nbsp;</a>';
          $footnotes .= "\n" . '<b>' . $annotation_creator . ' (<i>' . $annotation_date . '</i>)</b> :';
          $footnotes .= "\n" . '<div class="odt-annotation-content">' . $annotation_content . '</div>';
          $footnotes .= '</div>' . "\n";
          break;

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
