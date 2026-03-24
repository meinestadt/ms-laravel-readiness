<?php

declare(strict_types=1);

namespace Meinestadt\MsLaravelReadiness;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

final class ReadinessController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $connections = array_keys(config('database.connections'));
        $connections = array_filter(
            $connections,
            fn ($connection, $name) => !empty($connection['url']),
            ARRAY_FILTER_USE_BOTH
        );
        $response = [
            "healthy" => true,
            "status" => 200,
            'checks' => [
                'connections' => [],
                'redis' => [],
            ],
        ];

        foreach ($connections as $name) {
            $a = rand(1, 1000);
            $b = rand(1, 1000);
            $expectedSum = $a + $b;

            try {
                $result = DB::connection($name)->selectOne("SELECT ? + ? AS total", [$a, $b]);
                $dbSum = (int) $result->total;

                if ($dbSum === $expectedSum) {
                    $response['checks']['connections'][$name] = [
                        'status' => 'OK',
                    ];
                } else {
                    throw new \Exception("Math mismatch: Expected {$expectedSum}, got {$dbSum}");
                }
            } catch (\Exception $e) {
                $allHealthy = false;
                $response['checks']['connections'][$name] = [
                    'status' => 'FAILED',
                    'error' => $e->getMessage()
                ];
                $response['healthy'] = false;
                $response['status'] = 503;
            }
        }

        $redisConnections = array_filter(
            config('database.redis', []),
            fn (mixed $value): bool => is_array($value),
        );
        $redisConnections = array_filter(
            $redisConnections,
            fn (array $connection): bool => isset($connection['host'], $connection['port']),
        );

        foreach (array_keys($redisConnections) as $name) {
            try {
                // ignore local stuff
                if (str_ends_with($redisConnections[$name]['host'], '.local')) {
                    continue;
                }
                $info = explode(" ", trim(Redis::connection($name)->executeRaw(['CLIENT', 'INFO'])));
                $info = array_reduce($info, function ($carry, $item) {
                    [$key, $value] = explode('=', $item, 2);
                    $carry[$key] = $value;
                    return $carry;
                }, []);

                if (isset($info['id']) && !empty($info['id'])) {
                    $response['checks']['redis'][$name] = [
                        'status' => 'OK',
                        'info' => $info,
                    ];
                } else {
                    throw new \Exception("Redis did not respond correctly: " . json_encode($info));
                }
            } catch (\Exception $e) {
                $response['checks']['redis'][$name] = [
                    'status' => 'FAILED',
                    'error' => $e->getMessage()
                ];
                $response['healthy'] = false;
                $response['status'] = 503;
            }
        }

        return response()->json($response, $response['status']);
    }
}
