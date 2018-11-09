<?php

use Swoole\Coroutine as co;

class Block
{
	public $index;
	public $nonce;
	public $timestamp;
	public $payload;
	public $prevHash;
	public $hash;
	public $difficulty = 1;

	public function calculateHash()
	{
		return sha1($this->index.$this->nonce.$this->timestamp.$this->payload.$this->prevHash.$this->difficulty);
	}

	public function toArray()
	{
		return [
			'index' => $this->index,
			'nonce' => $this->nonce,
			'timestamp' => $this->timestamp,
			'payload' => $this->payload,
			'prevHash' => $this->prevHash,
			'hash' => $this->hash,
		];
	}
}

// 区块数据
$chains = [];

// 创建创世区块
function generateGenesisBlock() : Block
{
	$block = new Block;
	$block->index = 1;
	$block->nonce = mt_rand(0, 100);
	$block->timestamp = time();
	$block->payload = 'xiaoteng';
	$block->prevHash = '';
	$block->difficulty = 1;
	$block->hash = $block->calculateHash();
	return $block;
}

function info($msg)
{
	echo "{$msg}\n";
}

info("RPC服务：127.0.0.1:9503");

$server = new swoole_server("127.0.0.1", 9503);
$server->set([
	'worker_num' => 1,
]);

$server->on('WorkerStart', function ($serv, $worker_id) {
	global $chains;
	info("生成创世区块：");
	$block = generateGenesisBlock();
	array_push($chains, $block);
	info(json_encode($chains));
	info("运行中...");

	go(function () use (&$chains) {
		while (true) {
			$index = count($chains);

			$block = new Block;
			$block->index = $index + 1;
			$block->timestamp = time();
			$block->payload = '';
			$block->prevHash = $chains[$index - 1]->hash;
			$block->hash = $block->calculateHash();
			$block->difficulty = $chains[$index - 1]->difficulty;

			for ($i = 1;; $i++) {
				$block->nonce = $i;
				$hash = $block->calculateHash();
				if (substr($hash, 0, $block->difficulty) == str_pad('', $block->difficulty, '0')) {
					$block->hash = $hash;
					break;
				}
			}

			info(json_encode($block->toArray()));
			array_push($chains, $block);

			co::sleep(3);
		}
	});
});

$server->on('connect', function ($server, $fd){
    echo "connection open: {$fd}\n";
});

$server->on('receive', function ($server, $fd, $reactor_id, $data) {
	global $chains;
	$data = trim($data);
	if ($data == 'chains') {
		$server->send($fd, json_encode($chains));
	}
});

$server->on('close', function ($server, $fd) {
    echo "connection close: {$fd}\n";
});

$server->start();