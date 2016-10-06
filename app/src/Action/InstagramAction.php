<?php

namespace App\Action;

use Slim\Http\Request,
    Slim\Http\Response;

use FileSystemCache;

use Thapp\XmlBuilder\XmlBuilder;
use Thapp\XmlBuilder\Normalizer;

use Stringy\Stringy;

final class InstagramAction
{
    private $username, $paths, $length = 5;

    public function __construct($paths)
    {
        $this->paths = $paths;
    }

    public function feed(Request $request, Response $response, $args)
    {
        $this->setUsername($args['username']);

        if(isset($args['amount']))
        {
            $this->setLength($args['amount']);
        }

        $forceFileCached = isset($request->getQueryParams()['forceFileCached']) ? $request->getQueryParams()['forceFileCached'] : false;

        FileSystemCache::$cacheDir = __DIR__ . '/../../../cache/tmp';
        $key = FileSystemCache::generateCacheKey($this->getUsername());
        $data = FileSystemCache::retrieve($key);

        if($data === false || $forceFileCached == true)
        {
            $content = json_decode(file_get_contents($this->getInstagramMediaUrl()));

            $data = array(
                'info' => array(
                    'id' => $content->items[0]->user->id,
                    'name' => $content->items[0]->user->full_name,
                    'username' => $content->items[0]->user->username,
                    'midia' => array(
                        'profile' => $content->items[0]->user->profile_picture,
                    )
                ),
                'feeds' => array()
            );

            foreach($content->items as $i => $item)
            {
                $data['feeds'][$i] = array(
                    'type' => $item->type,
                    'date' => array(
                        'created' => date('Y-m-d H:i:s', $item->caption->created_time)
                    ),
                    'message' => array(
                        'cut' => str_replace("\n", ' ', (string) Stringy::create($item->caption->text)->safeTruncate(250, '...')),
                        'full' => $item->caption->text
                    ),
                    'midia' => array(
                        'image' => $item->images->standard_resolution->url,
                        'video' => $item->type == 'video' ? $item->videos->standard_resolution->url : ''
                    ),
                    'engagement' => array(
                        'videoviews' => array(
                            'total' => isset($item->video_views) ? $item->video_views : ''
                        ),
                        'likes' => array(
                            'total' => $item->likes->count,
                            'users' => array()
                        ),
                        'comments' => array(
                            'total' => $item->comments->count,
                            'users' => array()
                        )
                    )
                );

                foreach($item->likes->data as $user)
                {
                    $data['feeds'][$i]['engagement']['likes']['users'][] = array(
                        'id' => $user->id,
                        'name' => $user->full_name,
                        'username' => $user->username,
                        'midia' => array(
                            'profile' => $user->profile_picture,
                        )
                    );
                }

                foreach($item->comments->data as $comment)
                {
                    $data['feeds'][$i]['engagement']['comments']['users'][] = array(
                        'date' => array(
                            'created' => date('Y-m-d H:i:s', $comment->created_time)
                        ),
                        'message' => array(
                            'cut' => str_replace("\n", ' ', (string) Stringy::create($comment->text)->safeTruncate(250, '...')),
                            'full' => $comment->text
                        ),
                        'from' =>array(
                            'id' => $comment->from->id,
                            'name' => $comment->from->full_name,
                            'username' => $comment->from->username,
                            'midia' => array(
                                'profile' => $comment->from->profile_picture,
                            )
                        )
                    );
                }

                if($i > $this->getLength())
                {
                    break;
                }
            }

            FileSystemCache::store($key, $data, 7200);
        }

        $xmlBuilder = new XmlBuilder('root');
        $xmlBuilder->setSingularizer(function ($name) {
            if ('feeds' === $name) {
                return 'feed';
            }
            if ('users' === $name) {
                return 'user';
            }
            return $name;
        });
        $xmlBuilder->load($data);
        $xml_output = $xmlBuilder->createXML(true);

        $response->write($xml_output);
        $response = $response->withHeader('content-type', 'text/xml');
        return $response;
    }

    /**
     * @return mixed
     */
    private function getUsername()
    {
        return $this->username;
    }

    /**
     * @param mixed $username
     */
    private function setUsername($username)
    {
        $this->username = $username;
    }

    /**
     * @return mixed
     */
    private function getPaths()
    {
        return $this->paths;
    }

    /**
     * @return int
     */
    private function getLength()
    {
        return $this->length;
    }

    /**
     * @param int $length
     */
    private function setLength($length)
    {
        $this->length = $length;
    }

    /**
     * @return mixed
     */
    private function getInstagramMediaUrl()
    {
        if(empty($this->getUsername()))
        {
            return false;
        }
        return 'https://www.instagram.com/' . $this->getUsername() . '/media/';
    }
}