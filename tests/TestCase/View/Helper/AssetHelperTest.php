<?php

namespace Interweberde\WebpackAssetLoader\Test\TestCase\View\Helper;

use Cake\TestSuite\TestCase;
use Cake\View\View;
use DOMDocument;
use DOMNamedNodeMap;
use DOMNode;
use Exception;
use Interweberde\WebpackAssetLoader\View\Helper\AssetHelper;

/**
 * Class AssetHelperTest
 * @testdox AssetHelper
 * @package Interweberde\WebpackAssetLoader\Test\TestCase\View\Helper
 */
class AssetHelperTest extends TestCase {

    private $helper = null;

    private $root = null;

    private function checkHtmlForScripts(string $html) {
        $dom = new DOMDocument();
        $dom->loadHTML($html);

        $scripts = $dom->getElementsByTagName("script");
        $this->assertEquals($scripts->count(), 2);

        $loaded = [];
        for ($i = 0; $i < $scripts->length; $i++) {
            /** @var DOMNode $script */
            $script = $scripts->item($i);
            $this->assertNotNull($script->attributes);

            /** @var DOMNamedNodeMap $attributes */
            $attributes = $script->attributes;

            /** @var DOMNode $src */
            $src = $attributes->getNamedItem('src');
            $this->assertNotNull($src);

            $text = $src->textContent;
            $loaded[] = $text;

            if ($text === '/dist/main.js') {
                /** @var DOMNode $integrity */
                $integrity = $attributes->getNamedItem('integrity');
                $this->assertNotNull($integrity);

                $this->assertTrue($integrity->textContent === 'hash-main', 'Asset #1 has correct Integrity');
            }

            if ($text === '/dist/vendors.js') {
                $this->assertNull($attributes->getNamedItem('integrity'), 'Asset #2 has no Integrity');
            }
        }

        $this->assertContains('/dist/main.js', $loaded, 'Asset #1 Loaded');
        $this->assertContains('/dist/vendors.js', $loaded, 'Asset #2 Loaded');
    }

    private function checkHtmlForStyles(string $html) {
        $dom = new DOMDocument();
        $dom->loadHTML($html);

        $styles = $dom->getElementsByTagName("link");
        $this->assertEquals($styles->count(), 1);

        $loaded = [];
        for ($i = 0; $i < $styles->length; $i++) {
            /** @var DOMNode $style */
            $style = $styles->item($i);
            $this->assertNotNull($style->attributes);

            /** @var DOMNamedNodeMap $attributes */
            $attributes = $style->attributes;

            /** @var DOMNode $href */
            $href = $attributes->getNamedItem('href');
            $this->assertNotNull($href);

            $text = $href->textContent;
            $loaded[] = $text;
        }

        $this->assertContains('/dist/main.css', $loaded, 'Style Loaded');
    }

    // Here we instantiate our helper
    public function setUp() {
        parent::setUp();
        $View = new View();

        $findRoot = function ($root) {
            do {
                $lastRoot = $root;
                $root = dirname($root);
                if (is_dir($root . '/vendor/cakephp/cakephp')) {
                    return $root;
                }
            } while ($root !== $lastRoot);

            throw new Exception("Cannot find the root of the application, unable to run tests");
        };

        $this->root = $findRoot(__FILE__);

        $this->helper = new AssetHelper($View, [
            'manifest' => $this->root . DS . 'tests' . DS . 'manifest.json',
        ]);
    }

    /**
     * @testdox loads js entries correctly
     */
    public function testLoadJsEntry() {
        $html = $this->helper->loadEntry('main');

        $this->checkHtmlForScripts($html);
        $this->checkHtmlForStyles($html);
    }

    /**
     * @testdox loads css entries correctly
     */
    public function testLoadCssEntry() {
        $html = $this->helper->loadEntry('main');

        $this->checkHtmlForStyles($html);
    }

    /**
     * @testdox loads deferred js entries correctly
     */
    public function testGetDeferredJsEntries() {
        $this->helper->loadEntryDeferred('main');

        $html = $this->helper->getDeferredEntries('js');
        $this->checkHtmlForScripts($html);
    }

    /**
     * @testdox loads deferred css entries correctly
     */
    public function testGetDeferredCssEntries() {
        $this->helper->loadEntryDeferred('main');

        $html = $this->helper->getDeferredEntries('css');
        $this->checkHtmlForStyles($html);
    }

    /**
     * @testdox throws an exception if the manifest does not exist
     */
    public function testThrowsExceptionWhenManifestDoesNotExist() {
        $this->expectException(\Exception::class);

        new AssetHelper(new View(), [
            'manifest' => 'SOMERANDOMPATHTHATDOESNOTEXIST' . DS . 'manifest.json',
        ]);
    }

    /**
     * @testdox loadEntry throws an exception if the specified entry does not exist
     */
    public function testThrowsExceptionWhenEntryDoesNotExist() {
        $this->expectException(\Exception::class);

        $this->helper->loadEntry('notexistent');
    }

    /**
     * @testdox loadEntryDeferred throws an exception if the specified entry does not exist
     */
    public function testGetDeferredThrowsExceptionWhenEntryDoesNotExist() {
        $this->expectException(\Exception::class);

        $this->helper->loadEntryDeferred('notexistent');
    }

    /**
     * @testdox getDeferredEntries throws an exception if the specified entry does not exist
     */
    public function testGetDeferredEntriesThrowsExceptionWhenCalledWithInvalidType() {
        $this->expectException(\Exception::class);

        $this->helper->loadEntryDeferred('main');
        $this->helper->getDeferredEntries('docx');
    }

    /**
     * @testdox getDeferredEntries returns empty string when there are no assets with specified type
     */
    public function testGetDeferredEntriesReturnsEmptyStringWhenThereIsNoAssetWithType() {
        $this->helper->loadEntryDeferred('nocss');
        $this->assertEmpty($this->helper->getDeferredEntries('css'));
    }

    /**
     * @testdox throws exception when manifest is not parsable
     */
    public function testThrowsExceptionWhenManifestIsNotParsable() {
        $this->expectException(\Exception::class);

        new AssetHelper(new View(), [
            'manifest' => $this->root . DS . 'tests' . DS . 'invalid-manifest.json',
        ]);
    }
}
