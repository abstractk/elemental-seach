<?php

/**
 * Created by Nivanka Fonseka (nivanka@silverstripers.com).
 * User: nivankafonseka
 * Date: 9/7/18
 * Time: 11:36 AM
 * To change this template use File | Settings | File Templates.
 */

namespace SilverStripers\ElementalSearch\Model;

use DNADesign\Elemental\Models\ElementalArea;
use GuzzleHttp\Client;
use SilverStripe\Control\Director;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\SSViewer;

class SearchDocument extends DataObject
{

    private static $db = [
        'Type' => 'Varchar(300)',
        'OriginID' => 'Int',
        'Title' => 'Text',
        'Content' => 'Text',
    ];

    private static $searchable_fields = [
        'Title',
        'Content'
    ];

    private static $table_name = 'SearchDocument';

    private static $search_x_path;

    /**
     * @return DataObject
     */
    public function Origin()
    {
        return DataList::create($this->Type)->byID($this->OriginID);
    }

    public function makeSearchContent()
    {
        $origin = $this->Origin();
        $searchLink = $origin->getGenerateSearchLink();

        $oldThemes = SSViewer::get_themes();
        SSViewer::set_themes(SSViewer::config()->get('themes'));

        try {
            $bypassElemental = $origin->config()->get('use_only_x_path');
            if (!$bypassElemental) {
                $bypassElemental = self::config()->get('use_only_x_path');
            }

            if (!$bypassElemental) {
                $useElemental = false;
                foreach ($origin->hasOne() as $key => $class) {
                    if($class == ElementalArea::class) {
                        $useElemental = true;
                    }
                }
            } else {
                $useElemental = false;
            }

            $output = [];
            if($useElemental) {
                foreach ($origin->hasOne() as $key => $class) {
                    if ($class !== ElementalArea::class) {
                        continue;
                    }
                    
                    /** @var ElementalArea|Versioned $area */
                    $area = $origin->$key();
                    
                    if( $area->hasExtension(Versioned::class)){
                        $area = Versioned::get_by_stage($class, Versioned::LIVE)->byID($area->ID);
                    }
    
                    if ($area && $area->exists()) {
                        $output[] = $area->forTemplate();
                    }
                }
            }
            else {
            	//check if page needs to be scanned
	            $scan_page = $origin->config()->get('elemental_search_scan_page');
	            if( $scan_page !== false )
                    $output[] = Director::test($searchLink);
            }

            // any fields mark to search
            if($origin->config()->get('full_text_fields')) {
                foreach ($origin->config()->get('full_text_fields') as $fieldName) {
                    $dbObject = $origin->dbObject($fieldName);
                    if($dbObject) {
                        $output[] = $dbObject->forTemplate();
                    }
                }
            }

            $html = implode("\n", $output);
            $x_path = $origin->config()->get('search_x_path');
            if (!$x_path) {
                $x_path = self::config()->get('search_x_path');
            }
            if ($x_path) {
                $domDoc = new \DOMDocument();
                @$domDoc->loadHTML($html);

                $finder = new \DOMXPath($domDoc);
                $nodes = $finder->query("//*[contains(@class, '$x_path')]");
                $nodeValues = [];
                if ($nodes->length) {
                    foreach ($nodes as $node) {
                        $nodeValues[] = $node->nodeValue;
                    }
                } else {
                    $contents = strip_tags($html);
                }
                $contents = implode("\n\n", $nodeValues);
            } else {
                $contents = strip_tags($html);
            }


            $this->Title = $origin->getTitle();
            if ($contents) {
                $contents = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $contents);
                $this->Content = $contents;
            }
            $this->write();
        } catch (\Exception $e) {
        } finally {
            // Reset theme if an exception occurs, if you don't have a
            // try / finally around code that might throw an Exception,
            // CMS layout can break on the response. (SilverStripe 4.1.1)
            SSViewer::set_themes($oldThemes);
        }
        return implode($output);





        /*
        $searchLink = $origin->getGenerateSearchLink();

        try {
            $client = new Client();
            $res = $client->request('GET', $searchLink);
            if ($res->getStatusCode() == 200) {
                $body = $res->getBody();

                $x_path = $origin->config()->get('search_x_path');
                if (!$x_path) {
                    $x_path = self::config()->get('search_x_path');
                }

                if ($x_path) {
                    $domDoc = new \DOMDocument();
                    @$domDoc->loadHTML($body);

                    $finder = new \DOMXPath($domDoc);
                    $nodes = $finder->query("//*[contains(@class, '$x_path')]");
                    $nodeValues = [];
                    if ($nodes->length) {
                        foreach ($nodes as $node) {
                            $nodeValues[] = $node->nodeValue;
                        }
                    }
                    $contents = implode("\n\n", $nodeValues);
                } else {
                    $contents = strip_tags($body);
                }

                $this->Title = $origin->getTitle();
                if ($contents) {
                    $this->Content = $contents;
                }
                $this->write();
            }
        } catch(\Exception $e) {}
        */


    }

    function removeEmptyLines($string)
    {
        return preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $string);
    }

}
