<?php
declare(strict_types=1);

namespace SpotMaster\Api;

/**
 * StatusStore (Windows-safe)
 * - Escreve com fopen('c+'), flock(LOCK_EX), ftruncate(0), fwrite, fflush, flock(LOCK_UN).
 * - Lê com flock(LOCK_SH) para garantir consistência.
 * - Faz retries em caso de lock temporário (AV/backup/reader).
 */
final class StatusStore
{
    private string $statusFile;
    private int $maxRetries;
    private int $retrySleepMs;

    public function __construct(?string $statusFile = null, int $maxRetries = 20, int $retrySleepMs = 20)
    {
        $this->statusFile   = $statusFile ?: realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'status.json';
        $this->maxRetries   = $maxRetries;
        $this->retrySleepMs = $retrySleepMs;

        $dir = \dirname($this->statusFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (!file_exists($this->statusFile)) {
            @touch($this->statusFile);
            @chmod($this->statusFile, 0664);
            @file_put_contents($this->statusFile, json_encode(new \stdClass()));
        }
        if (!is_writable($this->statusFile)) {
            @chmod($this->statusFile, 0664);
        }
        if (!is_writable($this->statusFile)) {
            throw new \RuntimeException('Sem permissões de escrita para: ' . $this->statusFile);
        }
    }

    /** Escrita com lock exclusivo + retry (robusto em Windows). */
    public function write(array $data): void
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \RuntimeException('Falha a codificar JSON: ' . json_last_error_msg());
        }

        $attempt = 0;
        while (true) {
            $fh = @fopen($this->statusFile, 'c+'); // cria se não existir, leitura/escrita
            if ($fh === false) {
                $this->sleep();
                $this->tryFail(++$attempt, 'Não consegui abrir o ficheiro de status.');
                continue;
            }

            $locked = @flock($fh, LOCK_EX);
            if (!$locked) {
                @fclose($fh);
                $this->sleep();
                $this->tryFail(++$attempt, 'Não consegui obter LOCK_EX no status.json.');
                continue;
            }

            // Trunca e escreve
            @ftruncate($fh, 0);
            @rewind($fh);
            $bytes = @fwrite($fh, $json);
            @fflush($fh);
            @flock($fh, LOCK_UN);
            @fclose($fh);

            if ($bytes === false || $bytes < strlen($json)) {
                $this->sleep();
                $this->tryFail(++$attempt, 'Escrita incompleta no status.json.');
                continue;
            }

            @chmod($this->statusFile, 0664);
            return;
        }
    }

    /** Leitura consistente com LOCK_SH (compartilhado). */
    public function read(): array
    {
        $attempt = 0;
        while (true) {
            $fh = @fopen($this->statusFile, 'r');
            if ($fh === false) {
                $this->sleep();
                $this->tryFail(++$attempt, 'Não consegui abrir status.json para leitura.');
                continue;
            }

            $locked = @flock($fh, LOCK_SH);
            if (!$locked) {
                @fclose($fh);
                $this->sleep();
                $this->tryFail(++$attempt, 'Não consegui obter LOCK_SH no status.json.');
                continue;
            }

            $contents = @stream_get_contents($fh);
            @flock($fh, LOCK_UN);
            @fclose($fh);

            if ($contents === false) {
                $this->sleep();
                $this->tryFail(++$attempt, 'Falha a ler status.json.');
                continue;
            }

            $data = json_decode($contents, true);
            return is_array($data) ? $data : [];
        }
    }

    /** Limpa (objeto vazio) com lock exclusivo. */
    public function clear(): void
    {
        $this->write([]);
    }

    private function sleep(): void
    {
        if ($this->retrySleepMs > 0) {
            usleep($this->retrySleepMs * 1000);
        }
    }

    private function tryFail(int $attempt, string $msg): void
    {
        if ($attempt >= $this->maxRetries) {
            throw new \RuntimeException($msg);
        }
    }
}
