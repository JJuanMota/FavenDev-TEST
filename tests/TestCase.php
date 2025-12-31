<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpGiftflowStorage();
    }

    private function setUpGiftflowStorage(): void
    {
        $codesFile = $this->testingPath('giftcodes.json');
        $eventsFile = $this->testingPath('webhook_events.json');

        config([
            'giftflow.codes_file' => $codesFile,
            'giftflow.events_file' => $eventsFile,
        ]);

        foreach ([$codesFile, $eventsFile] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    private function testingPath(string $file): string
    {
        $dir = storage_path('framework/testing');

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir.'/'.$file;
    }
}
