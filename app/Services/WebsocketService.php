<?php

namespace App\Services;

use React\EventLoop\Loop;
use Ratchet\Client\Connector;
use React\Socket\Connector as ReactConnector;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

// 웹소켓에만 사용해야합니다.
class WebsocketService
{
    protected $token;
    protected $channel;
    protected $flow;
    protected $wss_url;

    public function __construct(string $token = '', string $channel = '', string $flow = '')
    {
        $this->token = $token;
        $this->channel = $channel;
        $this->flow = $flow;
        $this->wss_url = config('globalvar.env.WEBSOCKET_URL');
    }

    public function flow()
    {
        if (in_array('', [$this->token, $this->channel, $this->flow, $this->wss_url])) {
            return false;
        }
        $loop = Loop::get();
        $connector = new Connector($loop, new ReactConnector($loop));
        $connector($this->wss_url) // 웹소켓 주소
            ->then(function ($conn) use ($loop) {
                $conn->on('message', function ($msg) use ($conn) {
                    $aMsg = json_decode($msg, true);
                    if (is_array($aMsg)) {
                        if (Arr::get($aMsg, 'action') == 'token-authentication') {
                            $conn->send(json_encode([
                                'cId' => $this->channel,
                                'action' => 'channel-connect',
                                'tId' => 2
                            ]));
                            $conn->send(json_encode([
                                'action' => 'event-bracket-flow-action',
                                'data' => [
                                    'flow' => $this->flow
                                ]
                            ]));
                        }

                        if (Arr::get($aMsg, 'action') == 'event-bracket-flow-action') {
                            $conn->close();
                        }
                    }
                });

                // 연결 끊김 처리
                $conn->on('close', function ($code = null, $reason = null) use ($loop) {
                    $loop->stop(); // 루프 정지
                });

                // 오류 발생 시 처리
                $conn->on('error', function ($error) use ($loop) {
                    $loop->stop();
                });

                $conn->send(json_encode([
                    'action' => 'token-authentication',
                    'token' => $this->token,
                    'tId' => 1,
                ]));
            }, function ($e) use ($loop) {
                $loop->stop();
            });

        $loop->run();
        return true;
    }
}
