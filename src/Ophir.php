<?php

namespace lovasoa\ophir;
use lovasoa\ophir\exceptions\DOMNodesNullExeption;
use lovasoa\ophir\Exceptions\FileNotFoundException;
use \XMLReader;

/**
 * Simple ODT to HTML converter
 *
 * Class Ophir
 * @package lovasoa\ophir
 */
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
    const STYLES            = "STYLES";
    const CSS               = Ophir::STYLES;

	const IMAGEFOLDER		= "IMAGEFOLDER";
	const IMAGE_FOLDER		= Ophir::IMAGEFOLDER;

	/**
	 * Default configuration
	 * @var array
	 */
	private $configuration = array(
		Ophir::HEADINGS   => Ophir::ALL,
		Ophir::LISTS      => Ophir::NONE,
		Ophir::TABLE      => Ophir::ALL,
		Ophir::FOOTNOTE   => Ophir::ALL,
		Ophir::LINK       => Ophir::ALL,
		Ophir::IMAGE      => Ophir::ALL,
		Ophir::ANNOTATION => Ophir::ALL,
        OPHIR::STYLES     => OPHIR::ALL,
	);

    /**
     * Prefix of every style class
     * @var string
     */
    private $classPrefix = "ophir-";


    /**
     * The root DOMDocument
     * @var \DOMDocument;
     */
    private $domdocument;

    /**
     * The file to parse
     * @var String
     */
    private $file;

    /**
     * CSS Styles
     * @var OphirStyles
     */
    private $styles;


    /**
     * Converts the file given as parameter to HTML.
     * If no HTML is given, it converts the file given as $this->file
     *
     * @param  string|null $file
     * @throws DOMNodesNullExeption
     * @throws FileNotFoundException
     * @return string
     */
    public function convert($file = NULL){
        if($file !== NULL){
            $this->setFile($file);
        }

        if(!file_exists($this->file)) throw new FileNotFoundException("File {$this->file} does not exist");

        $this->styles = new OphirStyles();
        $xmlReader =    new XMLReader();

        $xmlReader->xml(file_get_contents("zip://{$this->file}#content.xml"));

        $this->domdocument = new \DOMDocument();
        /**
         * The parents array keeps track of the structure of the
         * original XML document. The indexes refer to the __depth of the children__ in the XML Document.
         * This first node is the DOMDocument node. That way a Element on the first depth level will be added to
         * $parents[0] and a element on the depth level 9 will be added to $parents[9]*
         *
         * *(except if $parents[9] is NULL, see Ophir::appendToLastThatIsNotNull)
         *
         * @var \DOMNode[] $parents
         */
        $parents = array(
            0 => $this->domdocument
        );
        while($xmlReader->read()){
            $node = $this->DOMNodeFactory($xmlReader);

            if($node !== NULL){
                $parents = $this->appendToNextThatIsNotNull($parents,$node,$xmlReader->depth);
            }
            $parents[$xmlReader->depth + 1] = $node;
        }

        //OphirHelper::DOMNodePrinter($this->domdocument);

        $retval = $this->domdocument->saveXML();

        if($this->configuration[Ophir::STYLES]){
            $retval .= '<style>'.$this->styles->getCss().'</style>';
        }

        return $retval;
    }

    /**
     * Gets an array of DOMNodes and NULL's
     * Iterates backwards through the array and appends $childNode to the
     * first Element, that is not NULL
     *
     * @param \DOMNode[] $domNodes
     * @param \DOMNode $childNode
     * @throws DOMNodesNullExeption
     * @return \DOMNode[]
     */
    private function appendToNextThatIsNotNull($domNodes,$childNode,$position){
        $length = count($domNodes);
        $position = ($position > $length) ? $length : $position;
        for($i = $position; $i >= 0; $i--){
            if($domNodes[$i] !== NULL){
                $domNodes[$i]->appendChild($childNode);
                return $domNodes;
            }
        }

        throw new DOMNodesNullExeption();
    }


    /**
     * The DOMNodeFactory creates Elements, if needed.
     * Returns DOMNode, if the current Element maps to a rea        }
l node and NULL, if not
     * @param \XMLReader $xml
     * @return \DOMNode|null
     */
    private function DOMNodeFactory($xml){

        if($xml->nodeType !== XMLReader::ELEMENT) return NULL;

        $node = NULL;
        $forceParagraph =   false;
        $containsText =     true;
        $skip =             false;

        switch($xml->name){
            // at the moment notes and annotations are rendered as normal text
            case 'office:annotation':
            case 'text:note':
            case '#text':
            case "text:p":
                $node = $this->domdocument->createElement('p');
                break;

            case 'text:span':
                $node = $this->domdocument->createElement('span');
                break;

            case "text:h":
                if($this->configuration[Ophir::HEADINGS] === Ophir::SIMPLE) $forceParagraph = true;
                if($this->configuration[Ophir::HEADINGS] === Ophir::NONE)   $skip = true;
                if($this->configuration[Ophir::HEADINGS] !== Ophir::ALL)    break;

                $level = $xml->getAttribute('text:outline-level');
                $node = $this->domdocument->createElement('h'.$level);
                break;

            case "text:a":
                if($this->configuration[Ophir::LINK] === Ophir::SIMPLE) $forceParagraph = true;
                if($this->configuration[Ophir::LINK] === Ophir::NONE)   $skip = true;
                if($this->configuration[Ophir::LINK] !== Ophir::ALL)    break;

                $href = $xml->getAttribute("xlink:href");
                $node = $this->domdocument->createElement('a');
                $node->setAttribute('href',$href);
                break;

            case 'draw:image':
                if($this->configuration[Ophir::IMAGE] !== Ophir::ALL)    break;

                $src = OphirHelper::getImageSource($this,$xml);
                $node = $this->domdocument->createElement('img');
                $node->setAttribute('src',$src);
                $containsText = false;
                break;

            case 'text:list-style':
                OphirHelper::getListStyle($this,$xml);
                break;

            case 'text:list':
                if($this->configuration[Ophir::LISTS] === Ophir::SIMPLE)    $forceParagraph = true;
                if($this->configuration[Ophir::LISTS] === Ophir::NONE)      $skip = true;
                if($this->configuration[Ophir::LISTS] !== Ophir::ALL)       break;

                $node = $this->domdocument->createElement('ul');
                $containsText = false;
                break;

            case 'text:list-item':
                if($this->configuration[Ophir::LISTS] === Ophir::SIMPLE) $forceParagraph = true;
                if($this->configuration[Ophir::LISTS] === Ophir::NONE)   $skip = true;
                if($this->configuration[Ophir::LISTS] !== Ophir::ALL)    break;

                $node = $this->domdocument->createElement('li');
                break;

            case 'style:style':
                OphirHelper::getStyleStyle($this,$xml);
                break;


            case 'table:table':
                if($this->configuration[Ophir::TABLE] === Ophir::SIMPLE) $forceParagraph = true;
                if($this->configuration[Ophir::TABLE] === Ophir::NONE)   $skip = true;
                if($this->configuration[Ophir::TABLE] !== Ophir::ALL)    break;

                $node = $this->domdocument->createElement('table');
                $containsText = false;
                break;

            case 'table:table-row':
                if($this->configuration[Ophir::TABLE] === Ophir::SIMPLE) $forceParagraph = true;
                if($this->configuration[Ophir::TABLE] === Ophir::NONE)   $skip = true;
                if($this->configuration[Ophir::TABLE] !== Ophir::ALL)    break;

                $node = $this->domdocument->createElement('tr');
                $containsText = false;
                break;

            case 'table:table-cell':
                if($this->configuration[Ophir::TABLE] === Ophir::SIMPLE) $forceParagraph = true;
                if($this->configuration[Ophir::TABLE] === Ophir::NONE)   $skip = true;
                if($this->configuration[Ophir::TABLE] !== Ophir::ALL)    break;

                $node = $this->domdocument->createElement('td');
        }

        if($skip){
            $xml->next();
            return NULL;
        }

        if($forceParagraph){
            $node = $this->domdocument->createElement('p');
        }

        if($node !== NULL){
            // setting the content of the element
            $string = $xml->readString();
            $innerXML = $xml->readInnerXml();

            if(!empty($string) && $containsText){
                $string = OphirHelper::getDOMTextFromXMLString($innerXML);
                $text = $this->domdocument->createTextNode($string);
                $node->appendChild($text);
            }

            $node->setAttribute('class',OphirHelper::extractClasses($this,$xml));

        }

        return $node;
    }



    // ---------- GETTERS & SETTERS ----------

    /**
     * @return array
     */
    public function getConfiguration(){
        return $this->configuration;
    }


    /**
     * @param String $option
     * @param int    $value
     *
     * @return Ophir $this
     */
    public function setConfiguration($option, $value = Ophir::ALL)
    {
        $this->configuration[$option] = $value;
        return $this;
    }


    /**
     * @return String
     */
    public function getFile() {
        return $this->file;
    }

    /**
     * @param String $file
     */
    public function setFile($file) {
        $this->file = $file;
    }

    /**
     * @return OphirStyles
     */
    public function getStyles() {
        return $this->styles;
    }

    /**
     * @param OphirStyles $styles
     */
    public function setStyles($styles) {
        $this->styles = $styles;
    }

    /**
     * @return string
     */
    public function getClassPrefix() {
        return $this->classPrefix;
    }

    /**
     * @param string $classPrefix
     */
    public function setClassPrefix($classPrefix) {
        $this->classPrefix = $classPrefix;
    }


} 
