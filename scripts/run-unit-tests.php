<?php
/**
 * Test Runner leve em PHP puro para testes unitários em ambientes rápidos.
 */

namespace PHPUnit\Framework {
    if (!class_exists('PHPUnit\Framework\TestCase')) {
        class TestCase {
            protected function setUp(): void {}
            protected function tearDown(): void {}
            public function assertTrue($condition, $msg = '') {
                if (!$condition) throw new \Exception("Falha em assertTrue: " . $msg);
            }
            public function assertFalse($condition, $msg = '') {
                if ($condition) throw new \Exception("Falha em assertFalse: " . $msg);
            }
            public function assertEquals($expected, $actual, $msg = '') {
                if ($expected !== $actual) throw new \Exception("Falha em assertEquals: esperado '" . print_r($expected, true) . "', obtido '" . print_r($actual, true) . "' - " . $msg);
            }
            public function assertNotEmpty($actual, $msg = '') {
                if (empty($actual)) throw new \Exception("Falha em assertNotEmpty: " . $msg);
            }
            public function assertNotNull($actual, $msg = '') {
                if ($actual === null) throw new \Exception("Falha em assertNotNull: " . $msg);
            }
            public function assertArrayHasKey($key, $array, $msg = '') {
                if (!is_array($array) || !array_key_exists($key, $array)) throw new \Exception("Falha em assertArrayHasKey: chave '{$key}' ausente - " . $msg);
            }
            public function assertFileExists($file, $msg = '') {
                if (!file_exists($file)) throw new \Exception("Falha em assertFileExists: '{$file}' não existe - " . $msg);
            }
            public function assertContains($needle, $haystack, $msg = '') {
                if (!in_array($needle, $haystack, true)) throw new \Exception("Falha em assertContains: '" . print_r($needle, true) . "' não encontrado - " . $msg);
            }
            public function assertIsArray($actual, $msg = '') {
                if (!is_array($actual)) throw new \Exception("Falha em assertIsArray: " . $msg);
            }
        }
    }
}

namespace GVN\Checkout\Tests {
    require_once __DIR__ . '/../tests/bootstrap.php';

    // Autoloader para classes do src/
    spl_autoload_register(function ($class) {
        $prefix = 'GVN\\Checkout\\';
        $base_dir = dirname(__DIR__) . '/src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    });

    $test_files = glob(dirname(__DIR__) . '/tests/Unit/*Test.php');
    $total_tests = 0;
    $passed_tests = 0;
    $failed_tests = 0;

    echo "==> Executando testes unitários do GVN Checkout...\n";

    foreach ($test_files as $file) {
        require_once $file;
        $class_name = 'GVN\\Checkout\\Tests\\Unit\\' . basename($file, '.php');
        if (!class_exists($class_name)) {
            continue;
        }

        $ref = new \ReflectionClass($class_name);
        $instance = $ref->newInstance();

        echo "--- " . basename($file) . " ---\n";

        foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if (strpos($method->getName(), 'test_') === 0) {
                $total_tests++;
                try {
                    $ref->getMethod('setUp')->invoke($instance);
                    $method->invoke($instance);
                    $ref->getMethod('tearDown')->invoke($instance);
                    echo "  [OK] " . $method->getName() . "\n";
                    $passed_tests++;
                } catch (\Throwable $e) {
                    echo "  [FAIL] " . $method->getName() . ": " . $e->getMessage() . "\n";
                    $failed_tests++;
                }
            }
        }
    }

    echo "\n===============================\n";
    echo "Total de testes: {$total_tests} | Passaram: {$passed_tests} | Falharam: {$failed_tests}\n";
    echo "===============================\n";

    exit($failed_tests > 0 ? 1 : 0);
}
