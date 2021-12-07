<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Heartbeat;
use App\Models\User;
use Illuminate\Support\Carbon;

class WakatimeController extends Controller
{
    public function heartbeat($user = null)
    {
        $user = $this->authenticate($user);
        abort_if($user == null, 403);

        // Record heartbeats
        $payloads = request()->all();
        if (isset($payloads['branch'])) {
            $payloads = [$payloads];
        }

        $createdHeartbeats = 0;

        collect($payloads)
            ->each(function ($payload) use ($user, &$createdHeartbeats) {
                $hearbeat = Heartbeat::firstOrCreate(
                    array_merge(
                        collect($payload)->only([
                            // branch": "string",
                            // "category": "string",
                            // "created_at": 0,
                            // "editor": "string",
                            // "entity": "string",
                            // "id": 0,
                            // "is_write": true,
                            // "language": "string",
                            // "machine": "string",
                            // "operating_system": "string",
                            // "project": "string",
                            // "time": 0,
                            // "type": "string",
                            // "user_agent": "string"
                            'entity',
                            'type',
                            'category',
                            'is_write',
                            'project',
                            'branch',
                            'language',
                            'user_agent',
                        ])->toArray(),
                        [
                            'created_at' => Carbon::createFromTimestamp($payload['time']),
                            'user_id' => $user->id,
                        ]
                    )
                );

                $hearbeat->save();
                $createdHeartbeats++;
            });

        // construct weird response format to make the cli consider all heartbeats to having been successfully saved
        // @see: https://github.com/muety/wakapi/blob/master/routes/api/heartbeat.go#L155-L174
        $finalResponse = ['responses' => []];
        for ($i = 0; $i < $createdHeartbeats; $i++) {
            $finalResponse['responses'][] = [null, 201];
        }

        return response()->json($finalResponse, 201);
    }

    protected function authenticate($user = null)
    {
        // Autenticate request
        if ($user == null || $user == 'current') {
            $token = ltrim(request()->header('Authorization'), 'Basic ');
            $api_key = base64_decode($token);
        } else {
            $api_key = $user;
        }

        return User::where('api_key', $api_key)->first();
    }
}