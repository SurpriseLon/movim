<?php
namespace Movim\Daemon;

use Ratchet\ConnectionInterface;
use React\EventLoop\Timer\Timer;
use Movim\Controller\Front;

class Session
{
    protected   $clients;
    public      $timestamp;
    protected   $sid;
    protected   $baseuri;
    public      $process;
    public      $pullSocket;
    public      $pushSocket;

    public      $registered;
    public      $started;

    protected   $buffer;
    private     $state;

    private     $verbose;
    private     $debug;

    private     $language;
    private     $offset;

    public function __construct($loop, $sid, $baseuri, $language = false, $offset = 0, $verbose = false, $debug = false)
    {
        $this->sid     = $sid;
        $this->baseuri = $baseuri;
        $this->language = $language;
        $this->offset = $offset;

        $this->verbose = $verbose;
        $this->debug = $debug;

        $this->clients = new \SplObjectStorage;
        $this->register($loop, $this);

        $this->timestamp = time();
    }

    public function attach($loop, ConnectionInterface $conn)
    {
        $this->clients->attach($conn);

        if ($this->verbose) {
            echo colorize($this->sid, 'yellow'). " : ".colorize($conn->resourceId." connected\n", 'green');
        }

        if ($this->countClients() > 0) {
            $this->stateOut('up');
        }
    }

    public function detach($loop, ConnectionInterface $conn)
    {
        $this->clients->detach($conn);

        if ($this->verbose) {
            echo colorize($this->sid, 'yellow'). " : ".colorize($conn->resourceId." deconnected\n", 'red');
        }

        if ($this->countClients() == 0) {
            $loop->addPeriodicTimer(20, function($timer) {
                if ($this->countClients() == 0) {
                    $this->stateOut('down');
                }
                $timer->cancel();
            });
        }
    }

    public function countClients()
    {
        return $this->clients->count();
    }

    private function register($loop, $me)
    {
        $buffer = '';

        // Communication sockets with the linker
        $file = CACHE_PATH . 'movim_feeds_' . $this->sid . '.ipc';

        $context = new \React\ZMQ\Context($loop, new \ZMQContext(1, false));
        $this->pullSocket = $context->getSocket(\ZMQ::SOCKET_PULL);
        $this->pullSocket->getWrappedSocket()->setSockOpt(\ZMQ::SOCKOPT_LINGER, 0);
        $this->pullSocket->bind('ipc://' . $file . '_pull');

        $this->pushSocket = $context->getSocket(\ZMQ::SOCKET_PUSH);
        $this->pushSocket->getWrappedSocket()->setSockOpt(\ZMQ::SOCKOPT_LINGER, 0);
        $this->pushSocket->bind('ipc://' . $file . '_push');

        $this->pullSocket->on('message', function($msg) use ($me) {
            $me->messageOut($msg);
        });

        // Launching the linker
        $this->process = new \React\ChildProcess\Process(
                            'exec php linker.php ' . $this->sid,
                            null,
                            [
                                'sid'       => $this->sid,
                                'baseuri'   => $this->baseuri,
                                'language'  => $this->language,
                                'offset'    => $this->offset,
                                'verbose'   => $this->verbose,
                                'debug'     => $this->debug
                            ]
                        );
        $this->process->start($loop);

        // The linker died, we close properly the session
        $this->process->on('exit', function($output) use ($me, $file) {
            if ($me->verbose) {
                echo colorize($this->sid, 'yellow'). " : ".colorize("linker killed \n", 'red');
            }

            $me->process = null;
            $me->closeAll();

            $this->pullSocket->unbind('ipc://' . $file . '_pull');
            $this->pushSocket->unbind('ipc://' . $file . '_push');

            (new \Modl\PresenceDAO)->clearPresence();
            (new \Modl\SessionxDAO)->delete($this->sid);
        });

        $self = $this;

        $this->process->stderr->on('data', function($output) use ($me, $self) {
            if (strpos($output, 'registered') !== false) {
                $self->registered = true;
            } elseif (strpos($output, 'started') !== false) {
                $self->started = true;
            } else {
                echo $output;
            }
        });
    }

    public function killLinker()
    {
        if (isset($this->process)) {
            $this->process->terminate();
            $this->process = null;
        }
    }

    public function closeAll()
    {
        foreach ($this->clients as $client) {
            $client->close();
        }
    }

    public function stateOut($state)
    {
        if ($this->state == $state) return;

        if (isset($this->process)) {
            $this->state = $state;
            $msg = new \stdClass;
            $msg->func = $this->state;
            $msg = json_encode($msg);
            $this->pushSocket->send($msg);
        }
    }

    public function messageIn($msg)
    {
        $this->timestamp = time();
        $this->pushSocket->send($msg);
        unset($msg);
    }

    public function messageOut($msg)
    {
        $this->timestamp = time();
        if(!empty($msg)) {
            foreach ($this->clients as $client) {
                $client->send($msg);
            }
        }
    }
}
