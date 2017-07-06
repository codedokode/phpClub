<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;
use phpClub\ThreadParser\{DvachThread, DateConverter, ThreadHtmlParser};

class DvachHtmlParserTest extends TestCase
{
    /**
     * @var ThreadHtmlParser
     */
    private $threadParser;

    public function setUp()
    {
        $this->threadParser = new ThreadHtmlParser(new DateConverter(), new DvachThread());
    }

    public function testGetPost()
    {
        $post = $this->threadParser->getPost(new Crawler(file_get_contents(__DIR__ . '/fixtures/posts/post-thread-17.html')));
        $this->assertEquals('Аноним', $post->author);
        $this->assertEquals('20/01/14 17:23:22', $post->date->format('d/m/y H:i:s'));
        $this->assertEquals('319724', $post->id);
        $this->assertContains('делать, если расширение', $post->text);
        $this->assertEquals('', $post->title);
        $this->assertCount(0, $post->files);

        $post = $this->threadParser->getPost(new Crawler(file_get_contents(__DIR__ . '/fixtures/posts/post-thread-71.html')));
        $this->assertEquals('пхп', $post->author);
        $this->assertEquals('24/02/16 17:11:57', $post->date->format('d/m/y H:i:s'));
        $this->assertEquals('665216', $post->id);
        $this->assertContains('что в пхп ини то же самое</span><br>А ты тот файл который нужно редактируешь? Настройки', $post->text);
        $this->assertEquals('', $post->title);
        $this->assertCount(0, $post->files);
    }

    /**
     * @dataProvider provideThreadsHtml
     */
    public function testGetPosts($pathToThreadHtml)
    {
        $threadArray = $this->threadParser->getPosts(file_get_contents($pathToThreadHtml));
        $this->assertGreaterThan(500, count($threadArray));
        $this->assertNotEmpty($threadArray[0]->author);
        $this->assertNotEmpty($threadArray[0]->id);
        $this->assertNotEmpty($threadArray[0]->text);
    }

    public function provideThreadsHtml()
    {
        return [
            [__DIR__ . '/fixtures/pr-thread-1/236463.html'],
            [__DIR__ . '/fixtures/pr-thread-2/247752.html'],
            [__DIR__ . '/fixtures/pr-thread-3/268546.html'],
            [__DIR__ . '/fixtures/pr-thread-6/293537.html'],
            [__DIR__ . '/fixtures/pr-thread-10/313971.html'],
            [__DIR__ . '/fixtures/pr-thread-17/319643.html'],
            [__DIR__ . '/fixtures/18/pr-thread-18.html'],
            [__DIR__ . '/fixtures/19/pr-thread-19.html'],
            [__DIR__ . '/fixtures/20/pr-thread-20.html'],
            [__DIR__ . '/fixtures/21/pr-thread-21.html'],
            [__DIR__ . '/fixtures/22/pr-thread-22.html'],
            [__DIR__ . '/fixtures/23/pr-thread-23.html'],
            [__DIR__ . '/fixtures/24/pr-thread-24.html'],
            [__DIR__ . '/fixtures/26/pr-thread-26.html'],
            [__DIR__ . '/fixtures/27/pr-thread-27.html'],
            [__DIR__ . '/fixtures/28/pr-thread-28.html'],
            [__DIR__ . '/fixtures/29/pr-thread-29.html'],
            [__DIR__ . '/fixtures/30/pr-thread-30.html'],
            [__DIR__ . '/fixtures/31/pr-thread-31.html'],
            [__DIR__ . '/fixtures/40/pr-thread-40.html'],
            [__DIR__ . '/fixtures/32/pr-thread-32.html'],
            [__DIR__ . '/fixtures/50/pr-thread-50.html'],
            [__DIR__ . '/fixtures/pr-thread-60/551625.html'],
            [__DIR__ . '/fixtures/pr-thread-77/753595.html'],
            [__DIR__ . '/fixtures/pr-thread-80/825576.html'],
        ];
    }

    /**
     * @dataProvider providePostsWithOpPostTitles
     */
    public function testOpPostTitleIsCorrect($pathToThreadHtml, $opPostTitle)
    {
        $postsArray = $this->threadParser->getPosts(file_get_contents($pathToThreadHtml));
        $this->assertEquals($opPostTitle, $postsArray[0]->title);
    }

    public function providePostsWithOpPostTitles()
    {
        return [
            [
                __DIR__ . '/fixtures/pr-thread-80/825576.html',
                'Клуб изучающих PHP 80: Последний летний.'
            ],
            [
                __DIR__ . '/fixtures/pr-thread-6/293537.html',
                'Клуб PHP для начинающих (6)'
            ],
            [
                __DIR__ . '/fixtures/pr-thread-2/247752.html',
                ''
            ],
            [
                __DIR__ . '/fixtures/31/pr-thread-31.html',
                'Клуб изучения PHP 31',
            ]
        ];
    }

    public function testFiles()
    {
        $pathToHtml  = __DIR__ . '/fixtures/pr-thread-80/825576.html';
        $threadArray = $this->threadParser->getPosts(file_get_contents($pathToHtml));
        $files       = $threadArray[0]->files;

        $this->assertCount(4, $files);

        // Image 1
        $this->assertEquals('14719368905530.png', $files[0]->fullName);
        $this->assertEquals(500, $files[0]->height);
        $this->assertEquals(500, $files[0]->width);
        $this->assertEquals('14719368905530s.jpg', $files[0]->thumbName);

        // Image 2
        $this->assertEquals('14719368905541.jpg', $files[1]->fullName);
        $this->assertEquals(166, $files[1]->height);
        $this->assertEquals(250, $files[1]->width);
        $this->assertEquals('14719368905541s.jpg', $files[1]->thumbName);

        // Image 3
        $this->assertEquals('14719368905542.jpg', $files[2]->fullName);
        $this->assertEquals(250, $files[2]->height);
        $this->assertEquals(175, $files[2]->width);
        $this->assertEquals('14719368905542s.jpg', $files[2]->thumbName);

        $this->assertCount(2, $threadArray[1]->files);
        $this->assertCount(0, $threadArray[2]->files);
    }
}
