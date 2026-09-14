<?php

/*
 * Copyright (c) 2016 Mark C. Prins <mprins@users.sf.net>
 *
 * Permission to use, copy, modify, and distribute this software for any
 * purpose with or without fee is hereby granted, provided that the above
 * copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
 * WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
 * ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY
 * CONSEQUENTIAL DAMAGES OR ANY DAMAGES WHATSOEVER RESULTING FROM LOSS OF USE,
 * DATA OR PROFITS, WHETHER IN AN ACTION OF CONTRACT, NEGLIGENCE OR OTHER
 * TORTIOUS ACTION, ARISING OUT OF OR IN CONNECTION WITH THE USE OR PERFORMANCE
 * OF THIS SOFTWARE.
 */

use dokuwiki\Logger;
use dokuwiki\Search\Indexer;
use DOMWrap\Document;

/**
 * Syntax tests for multiple namespace filters.
 *
 * @group plugin_backlinks
 * @group plugins
 */
class syntax_multiple_namespaces_plugin_backlinks_test extends DokuWikiTest
{
    protected $pluginsEnabled = array('backlinks');

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        global $conf;

        $conf['allowdebug'] = 1;

        TestUtils::rcopy(TMP_DIR, dirname(__FILE__) . '/data/');
        Logger::debug("set up class syntax_multiple_namespaces_plugin_backlinks_test");
    }

    public function setUp(): void
    {
        parent::setUp();

        global $conf;

        $conf['allowdebug'] = 1;
        $conf['cachetime'] = -1;

        $verbose = false;
        $force = false;

        $data = array();
        search($data, $conf['datadir'], 'search_allpages', array('skipacl' => true));

        foreach ($data as $val) {
            (new Indexer())->addPage($val['id'], $verbose, $force);
        }

        if ($conf['allowdebug']) {
            touch(DOKU_TMP_DATA . 'cache/debug.log');
        }
    }

    public function tearDown(): void
    {
        parent::tearDown();

        global $conf;

        if ($conf['allowdebug']) {
            print "\n";
            readfile(DOKU_TMP_DATA . 'cache/debug.log');
            unlink(DOKU_TMP_DATA . 'cache/debug.log');
        }
    }

    public function testMultipleNamespaces(): void
    {
        $request = new TestRequest();

        $response = $request->get(array('id' => 'mmm:nnn:ooo:multiple_namespaces'));

        $this->assertTrue(
            str_contains($response->getContent(), 'linking to a page form aaa'),
            '"linking to a page form aaa" should be in the output'
        );

        $this->assertFalse(
            str_contains($response->getContent(), 'linking to a page b'),
            '"linking to a page b" should not be in the output'
        );

        $this->assertFalse(
            str_contains($response->getContent(), 'linking to a page c'),
            '"linking to a page c" should not be in the output'
        );

        $this->assertFalse(
            str_contains($response->getContent(), 'linking to a page d'),
            '"linking to a page d" should not be in the output'
        );

        $doc = (new Document())->html($response->getContent());

        $this->assertEquals(
            1,
            count($doc->find('#plugin__backlinks')->toArray()),
            'There should be one backlinks element'
        );

        $wikilinks = $doc->find('#plugin__backlinks ul li');

        Logger::debug('found backlinks', $wikilinks->text());

        $this->assertEquals(
            1,
            count($wikilinks->toArray()),
            'There should be 1 backlink'
        );
    }
}
