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
            $content_json = json_decode(file_get_contents($this->getInstagramMediaUrl()));

            $path_uploads = __DIR__ . '/../../../data/uploads/' . $this->getUsername() . '/';
            if(!file_exists($path_uploads))
            {
                mkdir($path_uploads);
            }

            //CLEAR IMAGE
            $files = scandir($path_uploads);
            foreach ($files as $file)
            {
                $file_path = $path_uploads . $file;
                if(is_file($file_path))
                {
                    $current_date = (new \DateTime('-60 days'));
                    $file_date = (new \DateTime())->setTimestamp(filemtime($file_path));

                    if($file_date < $current_date)
                    {
                        unlink($file_path);
                    }
                }
            }

            //IMAGE PROFILE
            $img_profile = 'profile_picture_' . sha1($content_json->items[0]->user->profile_picture) . '.jpg';
            $img_profile_path = $path_uploads . $img_profile;

            if(!file_exists($img_profile_path))
            {
                $content = file_get_contents(str_replace('https://', 'http://', $content_json->items[0]->user->profile_picture));
                file_put_contents($img_profile_path, $content);
            }

            $data = array(
                'info' => array(
                    'date' => array(
                        'created' => (new \DateTime())->format('Y-m-d H:i:s'),
                    ),
                    'name' => $content_json->items[0]->user->full_name,
                    'username' => $content_json->items[0]->user->username,
                    'media' => array(
                        'profile' => sprintf('%s%s/%s', $this->getPaths()['upload_path_virtual'], $this->getUsername(), $img_profile),
                    )
                ),
                'feeds' => array()
            );

            foreach($content_json->items as $i => $item)
            {
                //IMAGE FEED
                $img_feed = 'feed_picture_' . sha1($item->images->standard_resolution->url) . '.jpg';
                $img_feed_path = $path_uploads . $img_feed;

                if(!file_exists($img_feed_path))
                {
                    $content = file_get_contents(str_replace('https://', 'http://', $item->images->standard_resolution->url));
                    file_put_contents($img_feed_path, $content);
                }

                //VIDEO FEED
                if($item->type == 'video')
                {
                    $video_feed = 'feed_video_' . sha1($item->videos->standard_resolution->url) . '.mp4';
                    $video_feed_path = $path_uploads . $video_feed;

                    if(!file_exists($video_feed_path))
                    {
                        $content = file_get_contents(str_replace('https://', 'http://', $item->videos->standard_resolution->url));
                        file_put_contents($video_feed_path, $content);
                    }

                    $video_path_virtual = sprintf('%s%s/%s', $this->getPaths()['upload_path_virtual'], $this->getUsername(), $video_feed);
                }
                else
                {
                    $video_path_virtual = '';
                }

                $data['feeds'][$i] = array(
                    'type' => $item->type,
                    'date' => array(
                        'created' => date('Y-m-d H:i:s', $item->caption->created_time)
                    ),
                    'message' => array(
                        'cut' => str_replace("\n", ' ', (string) Stringy::create($item->caption->text)->safeTruncate(140, '...')),
                        'full' => $item->caption->text
                    ),
                    'media' => array(
                        'image' => sprintf('%s%s/%s', $this->getPaths()['upload_path_virtual'], $this->getUsername(), $img_feed),
                        'video' => $video_path_virtual
                    ),
                    'engagement' => array(
                        'videoviews' => array(
                            'total' => isset($item->video_views) ? $item->video_views : '0'
                        ),
                        'likes' => array(
                            'total' => $item->likes->count,
                            'users' => array()
                        )
                    )
                );

                foreach($item->likes->data as $j => $user)
                {
                    //IMAGE LIKE
                    $img_like = 'like_picture_' . sha1($user->profile_picture) . '.jpg';
                    $img_like_path = $path_uploads . $img_like;

                    if(!file_exists($img_like_path))
                    {
                        $content = file_get_contents(str_replace('https://', 'http://', $user->profile_picture));
                        file_put_contents($img_like_path, $content);
                    }

                    $data['feeds'][$i]['engagement']['likes']['users'][] = array(
                        'name' => $user->full_name,
                        'username' => $user->username,
                        'media' => array(
                            'profile' => sprintf('%s%s/%s', $this->getPaths()['upload_path_virtual'], $this->getUsername(), $img_like)
                        )
                    );

                    if($j >= 2)
                    {
                        break;
                    }
                }

                if($i >= ($this->getLength() - 1))
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