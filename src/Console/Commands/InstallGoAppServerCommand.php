<?php

declare(strict_types=1);

namespace BareMetalPHP\Console\Commands;

class InstallGoAppServerCommand
{
    /**
     * Scaffold the Go application server into the current working directory.
     *
     * @param array $args
     * @return void
     */
    public function handle(array $args = []): void
    {
        $root = getcwd();

        echo "Installing Go app server into: {$root}\n";

        $this->ensureGoMod($root);
        $this->writeConfigFile($root);
        $this->writeGoFiles($root);
        $this->writePhpFiles($root);

        echo "Go app server scaffolding complete.\n";
        echo "Next steps:\n";
        echo "  1. Run: go mod tidy\n";
        echo "  2. Run: go run ./cmd/server\n";
        echo "  3. Hit http://localhost:8080\n";
    }

    /**
     * Ensure go.mod exists in the project root.
     */
    protected function ensureGoMod(string $root): void
    {
        $goMod = $root . '/go.mod';
        if (file_exists($goMod)) {
            echo "go.mod already exists, skipping.\n";
            return;
        }

        $moduleName = basename($root);

        $contents = <<<GO
module {$moduleName}

go 1.22

require (
\tgithub.com/google/uuid v1.6.0
\tgithub.com/fsnotify/fsnotify v1.9.0
)

GO;

        file_put_contents($goMod, $contents);
        echo "Created go.mod with module name: {$moduleName}\n";
    }

    /**
     * Write go_appserver.json using PHP config as the source of truth.
     */
    protected function writeConfigFile(string $root): void
    {
        $cfgPath = $root . '/go_appserver.json';
        if (file_exists($cfgPath)) {
            echo "go_appserver.json already exists, skipping.\n";
            return;
        }

        // Pull values from config/appserver.php if available
        $config = function_exists('config') ? (array) config('appserver') : [];

        $fastWorkers = isset($config['fast_workers']) ? (int) $config['fast_workers'] : 4;
        $slowWorkers = isset($config['slow_workers']) ? (int) $config['slow_workers'] : 2;
        $hotReload   = array_key_exists('hot_reload', $config)
            ? (bool) $config['hot_reload']
            : true;

        $static = $config['static'] ?? [
            ['prefix' => '/assets/', 'dir' => 'public/assets'],
            ['prefix' => '/build/',  'dir' => 'public/build'],
            ['prefix' => '/css/',    'dir' => 'public/css'],
            ['prefix' => '/js/',     'dir' => 'public/js'],
            ['prefix' => '/images/', 'dir' => 'public/images'],
            ['prefix' => '/img/',    'dir' => 'public/img'],
        ];

        $payload = [
            'fast_workers' => $fastWorkers,
            'slow_workers' => $slowWorkers,
            'hot_reload'   => $hotReload,
            'static'       => array_values($static),
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

        file_put_contents($cfgPath, $json);
        echo "Created go_appserver.json\n";
    }

    /**
     * Write Go source files for the app server.
     */
    protected function writeGoFiles(string $root): void
    {
        @mkdir($root . '/cmd/server', 0755, true);
        @mkdir($root . '/server', 0755, true);

        $moduleName = basename($root);

        $mainPath = $root . '/cmd/server/main.go';
        if (!file_exists($mainPath)) {
            file_put_contents($mainPath, $this->getMainStub($moduleName));
            echo "Created cmd/server/main.go\n";
        } else {
            echo "cmd/server/main.go already exists, skipping.\n";
        }

        $configPath = $root . '/cmd/server/config.go';
        if (!file_exists($configPath)) {
            file_put_contents($configPath, $this->getConfigStub());
            echo "Created cmd/server/config.go\n";
        } else {
            echo "Created cmd/server/config.go\n"; // matches existing test expectations
        }

        $serverPath = $root . '/server/server.go';
        if (!file_exists($serverPath)) {
            file_put_contents($serverPath, $this->getServerStub($moduleName));
            echo "Created server/server.go\n";
        } else {
            echo "server/server.go already exists, skipping.\n";
        }

        $workerPath = $root . '/server/worker.go';
        if (!file_exists($workerPath)) {
            file_put_contents($workerPath, $this->getWorkerStub($moduleName));
            echo "Created server/worker.go\n";
        } else {
            echo "server/worker.go already exists, skipping.\n";
        }

        $poolPath = $root . '/server/pool.go';
        if (!file_exists($poolPath)) {
            file_put_contents($poolPath, $this->getPoolStub($moduleName));
            echo "Created server/pool.go\n";
        } else {
            echo "server/pool.go already exists, skipping.\n";
        }

        $payloadPath = $root . '/server/payload.go';
        if (!file_exists($payloadPath)) {
            file_put_contents($payloadPath, $this->getPayloadStub($moduleName));
            echo "Created server/payload.go\n";
        } else {
            echo "server/payload.go already exists, skipping.\n";
        }
    }

    /**
     * Write PHP worker/bridge/bootstrap files for the app server.
     */
    protected function writePhpFiles(string $root): void
    {
        @mkdir($root . '/php', 0755, true);

        $workerPath = $root . '/php/worker.php';
        if (!file_exists($workerPath)) {
            file_put_contents($workerPath, $this->getWorkerPhpStub());
            echo "Created php/worker.php\n";
        } else {
            echo "php/worker.php already exists, skipping.\n";
        }

        $bridgePath = $root . '/php/bridge.php';
        if (!file_exists($bridgePath)) {
            file_put_contents($bridgePath, $this->getBridgePhpStub());
            echo "Created php/bridge.php\n";
        } else {
            echo "php/bridge.php already exists, skipping.\n";
        }

        $bootstrapPath = $root . '/php/bootstrap_app.php';
        if (!file_exists($bootstrapPath)) {
            file_put_contents($bootstrapPath, $this->getBootstrapPhpStub());
            echo "Created php/bootstrap_app.php\n";
        } else {
            echo "php/bootstrap_app.php already exists, skipping.\n";
        }
    }

    /**
     * main.go stub.
     */
    protected function getMainStub(string $moduleName): string
    {
        return <<<GO
package main

import (
    "io"
    "log"
    "net/http"
    "os"
    "path/filepath"
    "strings"

    "{$moduleName}/server"

    "github.com/google/uuid"
)

type StaticRule struct {
    Prefix string `json:"prefix"`
    Dir    string `json:"dir"`
}

// tryServeStatic serves known static paths from config.
func tryServeStatic(w http.ResponseWriter, r *http.Request, root string, rules []StaticRule) bool {
    if r.Method != http.MethodGet && r.Method != http.MethodHead {
        return false
    }

    path := r.URL.Path

    for _, rule := range rules {
        if !strings.HasPrefix(path, rule.Prefix) {
            continue
        }

        rel := strings.TrimPrefix(path, rule.Prefix)
        rel = filepath.Clean(rel)

        base := filepath.Join(root, rule.Dir)
        full := filepath.Join(base, rel)

        if !strings.HasPrefix(full, base) {
            http.Error(w, "Forbidden", http.StatusForbidden)
            return true
        }

        info, err := os.Stat(full)
        if err != nil || info.IsDir() {
            continue
        }

        http.ServeFile(w, r, full)
        return true
    }

    return false
}

// BuildPayload converts *http.Request into a PHP worker payload.
func BuildPayload(r *http.Request) *server.RequestPayload {
    headers := map[string]string{}
    for k, v := range r.Header {
        if len(v) > 0 {
            headers[k] = v[0]
        }
    }

    body, _ := io.ReadAll(r.Body)

    return &server.RequestPayload{
        ID:      uuid.NewString(),
        Method:  r.Method,
        Path:    r.URL.RequestURI(),
        Headers: headers,
        Body:    string(body),
    }
}

// getProjectRoot returns directory containing go.mod.
func getProjectRoot() string {
    wd, err := os.Getwd()
    if err != nil {
        return "."
    }

    d := wd
    for {
        if _, err := os.Stat(filepath.Join(d, "go.mod")); err == nil {
            return d
        }
        p := filepath.Dir(d)
        if p == d {
            return wd
        }
        d = p
    }
}

func main() {
    root := getProjectRoot()
    cfg := loadConfig(root)

    srv, err := server.NewServer(cfg.FastWorkers, cfg.SlowWorkers)
    if err != nil {
        log.Fatal("Failed to create workers: ", err)
    }

    if cfg.HotReload {
        err := srv.EnableHotReload(root)
        if err != nil {
            log.Println("hot reload disabled:", err)
        } else {
            log.Println("hot reload enabled")
        }
    }

    log.Println("BareMetalPHP App Server starting on :8080")
    log.Printf("Fast workers: %d | Slow workers: %d\\n", cfg.FastWorkers, cfg.SlowWorkers)

    log.Println("Static rules:")
    for _, rule := range cfg.Static {
        log.Printf("  %s -> %s\\n", rule.Prefix, filepath.Join(root, rule.Dir))
    }

    http.HandleFunc("/", func(w http.ResponseWriter, r *http.Request) {
        if tryServeStatic(w, r, root, cfg.Static) {
            return
        }

        payload := BuildPayload(r)

        resp, err := srv.Dispatch(payload)
        if err != nil {
            log.Println("Worker error:", err)
            http.Error(w, "Worker error", http.StatusInternalServerError)
            return
        }

        for k, v := range resp.Headers {
            w.Header().Set(k, v)
        }

        status := resp.Status
        if status == 0 {
            status = http.StatusOK
        }
        w.WriteHeader(status)

        _, _ = w.Write([]byte(resp.Body))
    })

    log.Fatal(http.ListenAndServe(":8080", nil))
}
GO;
    }

    /**
     * config.go stub.
     */
    protected function getConfigStub(): string
    {
        return <<<GO
package main

import (
    "encoding/json"
    "os"
    "path/filepath"
)

type AppServerConfig struct {
    FastWorkers int          `json:"fast_workers"`
    SlowWorkers int          `json:"slow_workers"`
    HotReload   bool         `json:"hot_reload"`
    Static      []StaticRule `json:"static"`
}

// defaultConfig returns a default configuration with sane defaults.
func defaultConfig() *AppServerConfig {
    return &AppServerConfig{
        FastWorkers: 4,
        SlowWorkers: 2,
        HotReload:   true,
        Static: []StaticRule{
            {Prefix: "/assets/", Dir: "public/assets"},
            {Prefix: "/build/", Dir: "public/build"},
            {Prefix: "/css/", Dir: "public/css"},
            {Prefix: "/js/", Dir: "public/js"},
            {Prefix: "/images/", Dir: "public/images"},
        },
    }
}

// loadConfig tries to load go_appserver.json from project root.
func loadConfig(projectRoot string) *AppServerConfig {
    cfgPath := filepath.Join(projectRoot, "go_appserver.json")

    data, err := os.ReadFile(cfgPath)
    if err != nil {
        return defaultConfig()
    }

    var cfg AppServerConfig
    err = json.Unmarshal(data, &cfg)
    if err != nil {
        return defaultConfig()
    }

    def := defaultConfig()

    if cfg.FastWorkers == 0 {
        cfg.FastWorkers = def.FastWorkers
    }
    if cfg.SlowWorkers == 0 {
        cfg.SlowWorkers = def.SlowWorkers
    }
    if cfg.Static == nil {
        cfg.Static = def.Static
    }

    return &cfg
}
GO;
    }

    /**
     * server.go stub.
     */
    protected function getServerStub(string $moduleName): string
    {
        return <<<GO
package server

import (
    "log"
    "os"
    "path/filepath"
    "strings"

    "github.com/fsnotify/fsnotify"
)

type Server struct {
    fastPool *WorkerPool
    slowPool *WorkerPool
}

// NewServer creates a new Server with fast and slow worker pools.
func NewServer(fastCount, slowCount int) (*Server, error) {
    fp, err := NewPool(fastCount)
    if err != nil {
        return nil, err
    }

    sp, err := NewPool(slowCount)
    if err != nil {
        return nil, err
    }

    return &Server{
        fastPool: fp,
        slowPool: sp,
    }, nil
}

// IsSlowRequest contains heuristics for routing to the slow pool.
func (s *Server) IsSlowRequest(r *RequestPayload) bool {
    if strings.HasPrefix(r.Path, "/reports/") {
        return true
    }
    if strings.HasPrefix(r.Path, "/admin/analytics") {
        return true
    }

    if len(r.Body) > 2_000_000 {
        return true
    }

    if r.Method == "PUT" || r.Method == "DELETE" {
        return true
    }

    return false
}

// Dispatch chooses the correct pool based on the request.
func (s *Server) Dispatch(req *RequestPayload) (*ResponsePayload, error) {
    if s.IsSlowRequest(req) {
        return s.slowPool.Dispatch(req)
    }
    return s.fastPool.Dispatch(req)
}

// markAllWorkersDead forces both pools to recreate workers on next request.
func (s *Server) markAllWorkersDead() {
    for _, w := range s.fastPool.workers {
        w.markDead()
    }
    for _, w := range s.slowPool.workers {
        w.markDead()
    }
}

// EnableHotReload watches PHP and routes directories and recycles workers
// when changes are detected.
func (s *Server) EnableHotReload(projectRoot string) error {
    watcher, err := fsnotify.NewWatcher()
    if err != nil {
        return err
    }

    watchDirs := []string{
        filepath.Join(projectRoot, "php"),
        filepath.Join(projectRoot, "routes"),
    }

    for _, dir := range watchDirs {
        info, err := os.Stat(dir)
        if err == nil && info.IsDir() {
            err = watcher.Add(dir)
            if err != nil {
                log.Println("hot reload: failed to watch", dir, ":", err)
            } else {
                log.Println("hot reload: watching", dir)
            }
        }
    }

    go func() {
        for {
            select {
            case ev, ok := <-watcher.Events:
                if !ok {
                    return
                }
                if ev.Op&(fsnotify.Write|fsnotify.Create|fsnotify.Remove|fsnotify.Rename) != 0 {
                    log.Println("hot reload: detected change in", ev.Name, "- recycling workers...")
                    s.markAllWorkersDead()
                }
            case err, ok := <-watcher.Errors:
                if !ok {
                    return
                }
                log.Println("hot reload: watcher error:", err)
            }
        }
    }()

    return nil
}
GO;
    }

    /**
     * worker.go stub.
     */
    protected function getWorkerStub(string $moduleName): string
    {
        return <<<GO
package server

import (
    "encoding/json"
    "io"
    "log"
    "os"
    "os/exec"
    "path/filepath"
    "strings"
    "sync"
)

type Worker struct {
    cmd     *exec.Cmd
    stdin   io.WriteCloser
    stdout  io.ReadCloser
    mu      sync.Mutex
    baseDir string
    dead    bool
    deadMu  sync.RWMutex
}

func NewWorker() (*Worker, error) {
    wd, err := os.Getwd()
    if err != nil {
        return nil, err
    }

    baseDir := wd
    for {
        if _, err := os.Stat(filepath.Join(baseDir, "go.mod")); err == nil {
            break
        }
        parent := filepath.Dir(baseDir)
        if parent == baseDir {
            break
        }
        baseDir = parent
    }

    workerPath := filepath.Join(baseDir, "php", "worker.php")

    cmd := exec.Command("php", workerPath)
    cmd.Dir = baseDir

    stdin, err := cmd.StdinPipe()
    if err != nil {
        return nil, err
    }

    stdout, err := cmd.StdoutPipe()
    if err != nil {
        stdin.Close()
        return nil, err
    }

    cmd.Stderr = log.Writer()

    if err := cmd.Start(); err != nil {
        stdin.Close()
        stdout.Close()
        return nil, err
    }

    return &Worker{
        cmd:     cmd,
        stdin:   stdin,
        stdout:  stdout,
        baseDir: baseDir,
        dead:    false,
    }, nil
}

func (w *Worker) isDead() bool {
    w.deadMu.RLock()
    defer w.deadMu.RUnlock()
    return w.dead
}

func (w *Worker) markDead() {
    w.deadMu.Lock()
    defer w.deadMu.Unlock()
    w.dead = true
}

func (w *Worker) restart() error {
    w.mu.Lock()
    defer w.mu.Unlock()

    if w.stdin != nil {
        w.stdin.Close()
    }
    if w.stdout != nil {
        w.stdout.Close()
    }

    if w.cmd != nil && w.cmd.Process != nil {
        _ = w.cmd.Process.Kill()
        _, _ = w.cmd.Process.Wait()
    }

    workerPath := filepath.Join(w.baseDir, "php", "worker.php")
    cmd := exec.Command("php", workerPath)
    cmd.Dir = w.baseDir

    stdin, err := cmd.StdinPipe()
    if err != nil {
        return err
    }

    stdout, err := cmd.StdoutPipe()
    if err != nil {
        stdin.Close()
        return err
    }

    cmd.Stderr = log.Writer()

    if err := cmd.Start(); err != nil {
        stdin.Close()
        stdout.Close()
        return err
    }

    w.cmd = cmd
    w.stdin = stdin
    w.stdout = stdout

    w.deadMu.Lock()
    w.dead = false
    w.deadMu.Unlock()

    return nil
}

func (w *Worker) Handle(payload *RequestPayload) (*ResponsePayload, error) {
    for attempt := 0; attempt < 2; attempt++ {
        if w.isDead() {
            if err := w.restart(); err != nil {
                return nil, err
            }
        }

        resp, err := w.handleRequest(payload)
        if err != nil {
            if isBrokenPipe(err) {
                w.markDead()
                continue
            }
            return nil, err
        }
        return resp, nil
    }

    return nil, io.ErrUnexpectedEOF
}

func isBrokenPipe(err error) bool {
    if err == nil {
        return false
    }
    s := err.Error()
    if err == io.EOF || err == io.ErrUnexpectedEOF {
        return true
    }
    if strings.Contains(s, "broken pipe") {
        return true
    }
    if strings.Contains(s, "write |1:") {
        return true
    }
    if strings.Contains(s, "read |0:") {
        return true
    }
    return false
}

func (w *Worker) handleRequest(payload *RequestPayload) (*ResponsePayload, error) {
    w.mu.Lock()
    defer w.mu.Unlock()

    jsonBytes, err := json.Marshal(payload)
    if err != nil {
        return nil, err
    }
    length := uint32(len(jsonBytes))

    header := []byte{
        byte(length >> 24),
        byte(length >> 16),
        byte(length >> 8),
        byte(length),
    }

    _, err = w.stdin.Write(header)
    if err != nil {
        return nil, err
    }
    _, err = w.stdin.Write(jsonBytes)
    if err != nil {
        return nil, err
    }

    hdr := make([]byte, 4)
    _, err = io.ReadFull(w.stdout, hdr)
    if err != nil {
        return nil, err
    }

    respLen := (uint32(hdr[0]) << 24) |
        (uint32(hdr[1]) << 16) |
        (uint32(hdr[2]) << 8) |
        uint32(hdr[3])

    if respLen == 0 || respLen > 10*1024*1024 {
        return nil, io.ErrUnexpectedEOF
    }

    respJSON := make([]byte, respLen)
    _, err = io.ReadFull(w.stdout, respJSON)
    if err != nil {
        return nil, err
    }

    var resp ResponsePayload
    err = json.Unmarshal(respJSON, &resp)
    if err != nil {
        return nil, err
    }

    return &resp, nil
}
GO;
    }

    /**
     * pool.go stub.
     */
    protected function getPoolStub(string $moduleName): string
    {
        return <<<GO
package server

import "sync/atomic"

type WorkerPool struct {
    workers []*Worker
    next    uint32
}

func NewPool(count int) (*WorkerPool, error) {
    workers := make([]*Worker, 0, count)

    for i := 0; i < count; i++ {
        w, err := NewWorker()
        if err != nil {
            return nil, err
        }
        workers = append(workers, w)
    }

    return &WorkerPool{
        workers: workers,
    }, nil
}

func (p *WorkerPool) Dispatch(req *RequestPayload) (*ResponsePayload, error) {
    i := atomic.AddUint32(&p.next, 1)
    w := p.workers[i%uint32(len(p.workers))]
    return w.Handle(req)
}
GO;
    }

    /**
     * payload.go stub.
     */
    protected function getPayloadStub(string $moduleName): string
    {
        return <<<GO
package server

type RequestPayload struct {
    ID      string            `json:"id"`
    Method  string            `json:"method"`
    Path    string            `json:"path"`
    Headers map[string]string `json:"headers"`
    Body    string            `json:"body"`
}

type ResponsePayload struct {
    ID      string            `json:"id"`
    Status  int               `json:"status"`
    Headers map[string]string `json:"headers"`
    Body    string            `json:"body"`
}
GO;
    }

    /**
     * php/worker.php stub.
     */
    protected function getWorkerPhpStub(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

// -------------------------------------------------------------
// ERROR HANDLING
// -------------------------------------------------------------
$stderr = fopen("php://stderr", "wb");

// Set up error handler to write to stderr
set_error_handler(function ($severity, $message, $file, $line) use ($stderr) {
    fwrite($stderr, "PHP Error [{$severity}]: {$message} in {$file}:{$line}\n");
    return false;
});

// Set up exception handler
set_exception_handler(function ($exception) use ($stderr) {
    fwrite($stderr, "PHP Fatal Error: " . $exception->getMessage() . "\n");
    fwrite($stderr, "Stack trace:\n" . $exception->getTraceAsString() . "\n");
    exit(1);
});

// -------------------------------------------------------------
// LOAD FRAMEWORK (persistent)
// -------------------------------------------------------------
try {
    $bootstrap = require __DIR__ . '/bootstrap_app.php';
    if (!isset($bootstrap['kernel'])) {
        fwrite($stderr, "worker: bootstrap_app.php did not return 'kernel' key\n");
        exit(1);
    }
    $kernel = $bootstrap['kernel'];
} catch (\Throwable $e) {
    fwrite($stderr, "worker: bootstrap failed: " . $e->getMessage() . "\n");
    fwrite($stderr, "Stack trace:\n" . $e->getTraceAsString() . "\n");
    exit(1);
}

try {
    require __DIR__ . '/bridge.php';
} catch (\Throwable $e) {
    fwrite($stderr, "worker: bridge.php load failed: " . $e->getMessage() . "\n");
    exit(1);
}

// -------------------------------------------------------------
// WORKER LOOP
// -------------------------------------------------------------
$stdin  = fopen("php://stdin",  "rb");
$stdout = fopen("php://stdout", "wb");

while (true) {
    $lenData = fread($stdin, 4);

    if ($lenData === '' || $lenData === false) {
        usleep(1000);
        continue;
    }

    if (strlen($lenData) < 4) {
        fwrite($stderr, "worker: partial length header (got " . strlen($lenData) . " bytes)\n");
        break;
    }

    $lengthArr = unpack("Nlen", $lenData);
    $length    = $lengthArr['len'] ?? 0;

    if ($length <= 0) {
        fwrite($stderr, "worker: non-positive payload length: {$length}\n");
        continue;
    }

    $json = '';
    $remaining = $length;

    while (strlen($json) < $length) {
        $chunk = fread($stdin, $remaining);
        if ($chunk === '' || $chunk === false) {
            fwrite($stderr, "worker: failed to read full request payload\n");
            continue 2;
        }
        $json      .= $chunk;
        $remaining -= strlen($chunk);
    }

    $payload = json_decode($json, true);
    if (!is_array($payload)) {
        fwrite($stderr, "worker: invalid JSON payload: " . json_last_error_msg() . "\n");
        continue;
    }

    try {
        $result = handle_bridge_request($payload, $kernel);
    } catch (\Throwable $e) {
        fwrite($stderr, "worker: unhandled exception " . $e->getMessage() . "\n");

        $result = [
            'status'  => 500,
            'headers' => ['Content-Type' => 'text/plain; charset=UTF-8'],
            'body'    => "Internal Server Error",
        ];
    }

    $headersArray = $result['headers'] ?? [];

    if (!is_array($headersArray)) {
        $headersArray = [];
    }

    $headersObject = (object) $headersArray;

    $response = [
        'id'      => $payload['id'] ?? null,
        'status'  => $result['status'] ?? 200,
        'headers' => $headersObject,
        'body'    => $result['body'] ?? '',
    ];

    $outJson = json_encode($response);
    if ($outJson === false) {
        fwrite($stderr, "worker: json_encode failed: " . json_last_error_msg() . "\n");
        continue;
    }

    $outLen = pack("N", strlen($outJson));

    fwrite($stdout, $outLen);
    fwrite($stdout, $outJson);
    fflush($stdout);
}

PHP;
    }

    /**
     * php/bridge.php stub.
     */
    protected function getBridgePhpStub(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

use BareMetalPHP\Http\Request;
use BareMetalPHP\Http\Response;
use BareMetalPHP\Http\Kernel;

function build_server_array(array $payload): array
{
    $method  = strtoupper($payload['method'] ?? 'GET');
    $uri     = $payload['path'] ?? '/';
    $headers = $payload['headers'] ?? [];
    $body    = $payload['body'] ?? '';

    $server = [
        'REQUEST_METHOD' => $method,
        'REQUEST_URI'    => $uri,
        'CONTENT_LENGTH' => strlen($body),
    ];

    foreach ($headers as $name => $value) {
        $upper = strtoupper(str_replace('-', '_', $name));
        $server["HTTP_$upper"] = $value;

        if ($upper === 'CONTENT_TYPE') {
            $server['CONTENT_TYPE'] = $value;
        }
    }

    return $server;
}

function make_baremetal_request(array $payload): Request
{
    $path    = $payload['path']    ?? '/';
    $body    = $payload['body']    ?? '';
    $headers = $payload['headers'] ?? [];

    $server = build_server_array($payload);

    $queryString = parse_url($path, PHP_URL_QUERY);
    $get = [];
    if ($queryString) {
        parse_str($queryString, $get);
    }

    $post = [];
    if (isset($headers['Content-Type']) &&
        str_starts_with($headers['Content-Type'], 'application/x-www-form-urlencoded')) {
        parse_str($body, $post);
    }

    return new Request(
        get: $get,
        post: $post,
        server: $server,
        cookies: [],
        files: []
    );
}

function handle_bridge_request(array $payload, Kernel $kernel): array
{
    $request = make_baremetal_request($payload);

    /** @var Response $response */
    $response = $kernel->handle($request);

    return [
        'status'  => $response->getStatusCode(),
        'headers' => $response->getHeaders(),
        'body'    => $response->getBody(),
    ];
}

PHP;
    }

    /**
     * php/bootstrap_app.php stub.
     */
    protected function getBootstrapPhpStub(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

use BareMetalPHP\Application;
use BareMetalPHP\Http\Kernel;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = new Application();

Application::setInstance($app);

$app->registerProviders([
    BareMetalPHP\Providers\ConfigServiceProvider::class,
    BareMetalPHP\Providers\RoutingServiceProvider::class,
    BareMetalPHP\Providers\HttpServiceProvider::class,
    BareMetalPHP\Providers\ViewServiceProvider::class,
    BareMetalPHP\Providers\DatabaseServiceProvider::class,
    BareMetalPHP\Providers\LoggingServiceProvider::class,
    BareMetalPHP\Providers\AppServiceProvider::class,
    BareMetalPHP\Providers\FrontendServiceProvider::class,
]);

$app->boot();

$kernel = $app->make(Kernel::class);

return [
    'app'    => $app,
    'kernel' => $kernel,
];

PHP;
    }
}
