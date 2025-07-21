<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\VoiceNarratorInterface;
use App\Enums\SpeechSpeed;
use App\Enums\VoiceStyle;
use App\ValueObjects\NarrationContent;
use App\ValueObjects\SpeechConfiguration;
use Illuminate\Support\Collection;

class VoiceNarrationService
{
    public function __construct(
        private readonly Collection $narrators
    ) {}

    public function narrate(NarrationContent $content, SpeechConfiguration $config): void
    {
        $narrator = $this->resolveNarrator($config->voice);

        $speech = $narrator->generate($content, $config);

        $this->speak($speech, $config);
    }

    private function resolveNarrator(VoiceStyle $voice): VoiceNarratorInterface
    {
        return $this->narrators->get($voice->value) ?? $this->narrators->get('default');
    }

    public function speak(string $speech, SpeechConfiguration $config): void
    {
        $platform = $this->detectPlatform();

        match ($platform) {
            'darwin' => $this->speakMacOS($speech, $config),
            'windows' => $this->speakWindows($speech, $config),
            'linux' => $this->speakLinux($speech, $config),
            default => $this->fallbackToText($speech),
        };
    }

    private function detectPlatform(): string
    {
        return strtolower(PHP_OS_FAMILY);
    }

    private function speakMacOS(string $speech, SpeechConfiguration $config): void
    {
        $rate = match ($config->speed) {
            SpeechSpeed::Slow => 100,
            SpeechSpeed::Fast => 200,
            default => 140,
        };

        $command = sprintf('say -r %d %s', $rate, escapeshellarg($speech));
        shell_exec($command);
    }

    private function speakWindows(string $speech, SpeechConfiguration $config): void
    {
        // Windows trolling included 😈
        sleep(1);

        $trolledSpeech = 'Why are you using Windows for development? '.
                        'Get a real operating system first. '.
                        "Anyway, here's your briefing from the inferior platform: ".$speech;

        $rate = match ($config->speed) {
            SpeechSpeed::Slow => -4,
            SpeechSpeed::Fast => 0,
            default => -2,
        };

        $psCommand = sprintf(
            'Add-Type -AssemblyName System.Speech; '.
            '$speak = New-Object System.Speech.Synthesis.SpeechSynthesizer; '.
            '$speak.Rate = %d; '.
            '$speak.Speak(%s); '.
            '$speak.Dispose()',
            $rate,
            escapeshellarg($trolledSpeech)
        );

        shell_exec("powershell -Command \"{$psCommand}\"");
    }

    private function speakLinux(string $speech, SpeechConfiguration $config): void
    {
        if (shell_exec('which espeak 2>/dev/null')) {
            $speedFlag = match ($config->speed) {
                SpeechSpeed::Slow => '-s 120',
                SpeechSpeed::Fast => '-s 200',
                default => '-s 160',
            };
            shell_exec("espeak {$speedFlag} ".escapeshellarg($speech));
        } else {
            $this->fallbackToText($speech);
        }
    }

    private function fallbackToText(string $speech): void
    {
        echo "\n📝 Text version:\n";
        echo "═══════════════\n";
        echo $speech."\n";
        echo "═══════════════\n";
    }
}
