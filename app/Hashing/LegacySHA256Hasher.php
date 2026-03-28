<?php

namespace App\Hashing;

use Illuminate\Contracts\Hashing\Hasher;

class LegacySHA256Hasher implements Hasher
{
    public function info($hashedValue)
    {
        return [
            'algo' => 'sha256',
            'algoName' => 'sha256',
            'options' => [],
        ];
    }

    public function make($value, array $options = [])
    {
        return hash('sha256', $value);
    }

    public function check($value, $hashedValue, array $options = [])
    {
        return $this->make($value) === $hashedValue;
    }

    public function needsRehash($hashedValue, array $options = [])
    {
        return false;
    }
}
