<?php

namespace Tests\Services;

use Devpark\PayboxGateway\Services\HmacHashGenerator;
use Illuminate\Config\Repository as Config;
use Illuminate\Contracts\Filesystem\Filesystem;
use Tests\UnitTestCase;
use Mockery as m;

class HmacHashGeneratorTest extends UnitTestCase
{
    /** @test */
    public function it_gets_valid_hmac_hash_for_multiple_params()
    {
        $config = m::mock(Config::class);
        $files = m::mock(Filesystem::class);

        $generator = new HmacHashGenerator($config, $files);

        $params = [
            'param1' => 'value',
            'param2' => 'value % 2',
        ];

        $hmacFileLocation = 'sample/path/for/hmac.txt';

        $config->shouldReceive('get')->with('paybox.hmac_key')->once()
            ->andReturn($hmacFileLocation);

        $secret = unpack('H*', 'secret');
        $secret = array_shift($secret);

        $files->shouldReceive('get')->with($hmacFileLocation)->once()->andReturn($secret);

        $result = $generator->get($params);

        $hmac = mb_strtoupper(hash_hmac('sha512',
            'param1=' . $params['param1'] . '&param2=' . $params['param2'], 'secret'));
        $this->assertSame($hmac, $result);
    }
}