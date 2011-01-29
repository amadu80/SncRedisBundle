<?php

namespace Bundle\RedisBundle\SessionStorage;

use Symfony\Component\HttpFoundation\SessionStorage\NativeSessionStorage;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Bundle\RedisBundle\Client\Predis\LoggingConnection;
use Predis\Client;

use Predis\Commands\Set,
    Predis\Commands\Get,
    Predis\Commands\Expire;

/**
 * Redis based session storage
 *
 * @link    http://github.com/justinrainbow/
 * @author  Justin Rainbow <justin.rainbow@gmail.com>
 */
class RedisSessionStorage extends NativeSessionStorage
{
    /**
     * Instance of Client
     *
     * @var Client
     */
    protected $db;

    /**
     * Redis session storage constructor
     *
     * @param  Client $db      Redis database connection
     * @param  array             $options Session options
     * @param  string            $prefix  Prefix to use when writing session data
     */
    public function __construct(Client $db, $options = null, $prefix = 'session')
    {
        $this->db = $db;

        $cookieDefaults = session_get_cookie_params();

        $this->options = array_merge(array(
            'name'          => '_SESS',
            'lifetime'      => $cookieDefaults['lifetime'],
            'path'          => $cookieDefaults['path'],
            'domain'        => $cookieDefaults['domain'],
            'secure'        => $cookieDefaults['secure'],
            'httponly'      => isset($cookieDefaults['httponly']) ? $cookieDefaults['httponly'] : false,
            'prefix'        => $prefix,
        ), $options);

        session_name($this->options['name']);
    }

    /**
     * Starts the session.
     */
    public function start()
    {
        if (self::$sessionStarted) {
            return;
        }

        parent::start();

        $this->options['id'] = session_id();
    }

    /**
     * Returns the session ID
     *
     * @return mixed  The session ID
     *
     * @throws \RuntimeException If the session was not started yet
     */
    public function getId()
    {
        if (!self::$sessionStarted) {
             throw new \RuntimeException('The session has not been started yet');
        }
        return $this->options['id'];
    }

    /**
     * Reads a session.
     *
     * @param  string $id  A session ID
     *
     * @return string      The session data if the session was read or created, otherwise an exception is thrown
     *
     * @throws \RuntimeException If the session cannot be read
     */
    public function read($key, $default = null)
    {
        $cmd = new Get();
        $cmd->setArgumentsArray(array($this->createId($key)));
        $this->db->writeCommand($cmd);

        if (null !== $data = $this->db->readResponse($cmd))
        {
            return unserialize($data);
        }
        return $default;
    }

    /**
     * Writes session data.
     *
     * @param  string $id    A session ID
     * @param  string $data  A serialized chunk of session data
     *
     * @return bool true, if the session was written, otherwise an exception is thrown
     *
     * @throws \RuntimeException If the session data cannot be written
     */
    public function write($key, $data)
    {
        try {
            $cmd = new Set();
            $cmd->setArgumentsArray(array($this->createId($key), serialize($data)));
            $this->db->writeCommand($cmd);
            return $this->db->readResponse($cmd);
        }
        catch (\Exception $e) {

        }
    }

    /**
     * Deletes the provided session key.
     *
     * @param  string $id   A session ID
     *
     * @return bool   true, if the session data was deleted
     */
    public function remove($key)
    {
        $cmd = new Del();
        $cmd->setArgumentsArray(array($this->createId($key)));
        $this->db->writeCommand($cmd);

        return $this->db->readResponse($cmd);
    }

    /**
     * Prepends the Session ID with a user-defined prefix (if any).
     *
     * @param  string $id   A session ID
     *
     * @return string prefixed session ID
     */
    protected function createId($id)
    {
        if (!isset($this->options['prefix']))
        {
            return $this->options['id'] . ':' . $id;
        }

        return $this->options['prefix'] . ':' . $this->options['id'] . ':' . $id;
    }
}