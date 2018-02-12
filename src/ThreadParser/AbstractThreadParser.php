<?php

declare(strict_types=1);

namespace phpClub\ThreadParser;

use phpClub\Entity\File;
use phpClub\Entity\Post;
use phpClub\Entity\Thread;
use Symfony\Component\DomCrawler\Crawler;

abstract class AbstractThreadParser
{
    /**
     * @var DateConverter
     */
    private $dateConverter;

    /**
     * @param DateConverter $dateConverter
     */
    public function __construct(DateConverter $dateConverter)
    {
        $this->dateConverter = $dateConverter;
    }

    abstract protected function getPostsXPath(): string;

    abstract protected function getIdXPath(): string;

    abstract protected function getTitleXPath(): string;

    abstract protected function getAuthorXPath(): string;

    abstract protected function getDateXPath(): string;

    abstract protected function getTextXPath(): string;

    abstract protected function getFilesXPath(): string;

    abstract protected function extractFile(Crawler $fileNode, Post $post): File;

    /**
     * @param string $threadHtml
     * @param string $threadPath
     *
     * @throws \Exception
     *
     * @return Thread
     */
    public function extractThread(string $threadHtml, string $threadPath = ''): Thread
    {
        $threadCrawler = new Crawler($threadHtml);

        $postsXPath = $this->getPostsXPath();

        $firstPostXPath = $threadCrawler->filterXPath($postsXPath . '[1]');
        $thread = new Thread($this->extractId($firstPostXPath));

        $postNodes = $threadCrawler->filterXPath($postsXPath);

        if (!count($postNodes)) {
            throw new \Exception('Posts not found');
        }

        $extractPost = function (Crawler $postNode) use ($thread, $threadPath) {
            $post = new Post(
                $this->extractId($postNode),
                $this->extractTitle($postNode),
                $this->extractAuthor($postNode),
                $this->extractDate($postNode),
                $this->extractText($postNode),
                $thread
            );

            if (!$this->isThreadWithMissedFiles($thread)) {
                $post->addFiles($this->extractFiles($postNode, $post, $threadPath));
            }

            $thread->addPost($post);
        };

        $postNodes->each($extractPost);

        return $thread;
    }

    /**
     * @param Crawler $postNode
     *
     * @throws \Exception
     *
     * @return int
     */
    protected function extractId(Crawler $postNode): int
    {
        $idXPath = $this->getIdXPath();
        $idNode = $postNode->filterXPath($idXPath);

        if (!count($idNode)) {
            throw new \Exception("Unable to parse post id, HTML: {$postNode->html()}");
        }

        $postId = preg_replace('/[^\d]+/', '', $idNode->text());

        return (int) $postId;
    }

    /**
     * @param Crawler $postNode
     *
     * @return string
     */
    protected function extractTitle(Crawler $postNode): string
    {
        $titleXPath = $this->getTitleXPath();
        $titleNode = $postNode->filterXPath($titleXPath);

        if (!count($titleNode)) {
            return '';
        }

        return trim($titleNode->text());
    }

    /**
     * @param Crawler $postNode
     *
     * @throws \Exception
     *
     * @return string
     */
    protected function extractAuthor(Crawler $postNode): string
    {
        $authorXPath = $this->getAuthorXPath();
        $authorNode = $postNode->filterXPath($authorXPath);

        if (!count($authorNode)) {
            throw new \Exception("Unable to parse post author, HTML: {$postNode->html()}");
        }

        return $authorNode->text();
    }

    /**
     * @param Crawler $postNode
     *
     * @throws \Exception
     *
     * @return \DateTimeImmutable
     */
    protected function extractDate(Crawler $postNode): \DateTimeImmutable
    {
        $dateXPath = $this->getDateXPath();
        $dateNode = $postNode->filterXPath($dateXPath);

        if (!count($dateNode)) {
            throw new \Exception("Unable to parse post date, HTML: {$postNode->html()}");
        }

        return $this->dateConverter->toDateTime($dateNode->text());
    }

    /**
     * @param Crawler $postNode
     *
     * @throws \Exception
     *
     * @return string
     */
    protected function extractText(Crawler $postNode): string
    {
        $textXPath = $this->getTextXPath();
        $textNode = $postNode->filterXPath($textXPath);

        if (!count($textNode)) {
            throw new \Exception("Unable to parse post text, HTML: {$postNode->html()}");
        }

        return trim($textNode->html());
    }

    /**
     * @param Crawler $postNode
     * @param Post    $post
     * @param string  $threadPath
     *
     * @return File[]
     */
    protected function extractFiles(Crawler $postNode, Post $post, string $threadPath): array
    {
        $filesXPath = $this->getFilesXPath();
        $fileNodes = $postNode->filterXPath($filesXPath);

        $extractFile = function (Crawler $fileNode) use ($post, $threadPath) {
            $file = $this->extractFile($fileNode, $post);

            if ($threadPath && !filter_var($file->getPath(), FILTER_VALIDATE_URL)) {
                $file->updatePaths(
                    $threadPath . '/' . basename($file->getPath()),
                    $threadPath . '/' . basename($file->getThumbPath())
                );
            }

            return $file;
        };

        return $fileNodes->each($extractFile);
    }

    protected function isThreadWithMissedFiles(Thread $thread): bool
    {
        // 345388 - Thread #15 (Google cache)
        $threadsWithMissedFiles = ['345388'];

        return in_array($thread->getId(), $threadsWithMissedFiles, $strict = true);
    }
}
