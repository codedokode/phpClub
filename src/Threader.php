<?php
namespace App;

use \Doctrine\ORM\EntityManager;

use App\Controller;
use App\Validator;
use App\Authorizer;
use App\Helper;
use App\Entities\Thread;
use App\Entities\Post;
use App\Entities\File;

class Threader extends Controller
{
    protected $em;
    protected $authorizer;

    public function __construct(EntityManager $em, Authorizer $authorizer)
    {
        $this->em = $em;
        $this->authorizer = $authorizer;
    }

    public function update()
    {
        $threadsHeaders = get_headers(Helper::getCatalogUrl(), true);

        if ($threadsHeaders['Content-Type'][0] != 'application/json') {
            throw new \Exception("Invalid catalog file");
        }

        $threads = file_get_contents(Helper::getCatalogUrl());
        $threads = json_decode($threads);

        if (!$threads) {
            throw new \Exception("Failed decoding threads json file");
            
        }

        foreach ($threads->threads as $someThread) {
            if (Validator::validateThreadSubject($someThread->subject)) {

                $threadHeaders = get_headers(Helper::getThreadUrl($someThread->num), true);

                if ($threadHeaders['Content-Type'][0] != 'application/json') {
                    throw new \Exception("Invalid thread file");
                }

                $json = file_get_contents(Helper::getThreadUrl($someThread->num));
                $jsonthread = json_decode($json);

                if (!$jsonthread) {
                    throw new \Exception("Failed decoding thread json file");
    
                }

                $thread = $this->em->getRepository('App\Entities\Thread')->find($jsonthread->current_thread);

                if (!$thread) {
                    $thread = new Thread();
                    $thread->setNumber($jsonthread->current_thread);

                    mkdir(Helper::getSrcDirectoryPath());
                    mkdir(Helper::getThumbDirectoryPath());

                    $this->em->persist($thread);
                    $this->em->flush();
                }

                foreach ($jsonthread->threads['0']->posts as $jsonpost) {
                    if ($this->em->getRepository('App\Entities\Post')->find($jsonpost->num)) {
                        continue;
                    }
                    
                    $post = new Post();
                    $post->setThread($thread);
                    $post->fillData($jsonpost);

                    $this->em->persist($post);
                    $this->em->flush();

                    $reflinks = Validator::validateRefLinks($post->getComment());

                    foreach ($reflinks as $link) {
                        $reflink = new RefLink();
                        $reflink->setPost($post->getPost());
                        $reflink->setReference($link);

                        $this->em->persist($reflink);
                        $this->em->flush();
                    }


                    foreach($jsonpost->files as $jsonfile) {
                        if ($jsonfile->displayname == 'Стикер') {
                            continue;
                        }

                        $file = new File();
                        $file->setPost($post);
                        $file->fillData($jsonfile);

                        $this->em->persist($file);
                        $this->em->flush();
                        
                        $content = file_get_contents(Helper::getSrcUrl($file->getPath()));
                        $thumbnail = file_get_contents(Helper::getThumbUrl($file->getThumbnail()));

                        if (!$content or !$thumbnail) {
                            throw new \Exception("Invalid files");
                        }

                        file_put_contents(Helper::getSrcPath($file->getPath()), $content);
                        file_put_contents(Helper::getThumbPath($file->getThumbnail()), $thumbnail);
                    }
                }

                //just in case
                file_put_contents(Helper::getJsonPath($jsonthread->current_thread), $json);
            }
        }
    }

    public function runThreads()
    {
        $logged = $this->authorizer->isLoggedIn();

        $threadsQuery = $this->em->createQuery('SELECT t FROM App\Entities\Thread t');
        $threads = $threadsQuery->getArrayResult();

        foreach ($threads as $key => $value) {
            $thread = new Thread();
            $thread->setNumber($value['number']);

            $countQuery = $this->em->createQuery("SELECT COUNT(p) FROM App\Entities\Post p WHERE p.thread = :number");
            $countQuery->setParameter('number', $thread->getNumber());
            $count = $countQuery->getSingleScalarResult();

            $opPost = $this->em->getRepository('App\Entities\Post')->findOneBy(array('post' => $thread->getNumber()));
            $posts = $this->em->getRepository('App\Entities\Post')->findBy(array('thread' => $thread->getNumber()), array(), 3, $count - 3);

            $thread->addPost($opPost);

            foreach ($posts as $post) {
                $thread->addPost($post);
            }

            $threads[$key] = $thread;
        }

        $this->render('public/board.php', compact('logged','threads'));
    }

    public function runThread()
    {
        $logged = $this->authorizer->isLoggedIn();

        $number = $this->getNumberQuery();

        if (!$number) {
            $this->redirect();
        }

        $thread = $this->em->getRepository('App\Entities\Thread')->find($number);

        $this->render('public/thread.php', compact('logged', 'thread'));
    }

    public function runChain()
    {
        $logged = $this->authorizer->isLoggedIn();

        $number = $this->getChainQuery();

        if (!$number) {
            $this->redirect();
        }

        $chain = Helper::getChain($number, $this->em);

        $posts = new \Doctrine\Common\Collections\ArrayCollection();
        
        foreach ($chain as $link) {
            $post = $this->em->getRepository('App\Entities\Post')->findOneBy(array('post' => $link));

            if (!$post) {
                continue;
            } 

            $posts->add($post);
        }

        $this->render('public/chain.php', compact('logged', 'posts'));
    }
}