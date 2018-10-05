<?php
namespace Interweberde\WebpackAssetLoader\View\Helper;

use Cake\Core\Configure;
use Cake\View\Helper;
use Cake\View\View;

/**
 * Asset helper
 */
class AssetHelper extends Helper
{

    /**
     * Default configuration.
     *
     * @var array
     */
    protected $_defaultConfig = [
        'manifest' => WWW_ROOT . DS . 'dist' . DS . 'manifest.json',
        'defaultOptions' => [
            'js' => [
                'block' => 'script'
            ],
            'css' => [
                'block' => 'css'
            ],
        ],
        'configurationKey' => 'interweber.WebpackAssetLoader.entries'
    ];

    public $helpers = ['Html'];
    private $manifest = [];

    public function initialize(array $config) {
        parent::initialize($config);

        if (!Configure::read($this->getConfig('configurationKey'))) {
            Configure::write($this->getConfig('configurationKey'), [
                'js' => [],
                'css' => [],
            ]);
        }

        try {
            $json = file_get_contents($this->getConfig('manifest'));
        } catch (\Exception $e) {
            throw new \Exception('could not load manifest file.');
        }

        $this->manifest = json_decode($json, true);

        if (!$this->manifest) {
            throw new \Exception('could not parse manifest file.');
        }
    }

    public function loadEntry($name, array $options = []): string {
        if (!isset($this->manifest['entrypoints'][$name])) {
            throw new \Exception('Unknown Entry \'' . $name . '\'');
        }

        $assets = $this->manifest['entrypoints'][$name];

        $this->_writeEntries($assets, 'js', $options);
        $this->_writeEntries($assets, 'css', $options);
    }

    public function loadEntryDeferred($name, array $options = []): void {
        if (!isset($this->manifest['entrypoints'][$name])) {
            throw new \Exception('Unknown Entry \'' . $name . '\'');
        }

        $assets = $this->manifest['entrypoints'][$name];

        $assets['js'] = $assets['js'] ?? [];
        $assets['css'] = $assets['css'] ?? [];

        $publicPath = $this->manifest['publicPath'];

        $deferredAssets = Configure::read($this->getConfig('configurationKey'));
        foreach ($assets['js'] as $asset) {
            // use asset as key to avoid duplicates
            $deferredAssets['js'][$asset] = $asset;
        }

        foreach ($assets['css'] as $asset) {
            $deferredAssets['css'][$asset] = $asset;
        }

        Configure::write($this->getConfig('configurationKey'), $deferredAssets);
    }

    public function getDeferredEntries(string $type, array $options = []): string {
        if ('js' !== $type && 'css' !== $type) {
            throw new \Exception("Unknown asset type '$type'.");
        }

        $deferredAssets = Configure::read($this->getConfig('configurationKey'));

        return $this->_writeEntries($deferredAssets, $type, [
            $type => $options
        ]);
    }

    private function _writeEntries(array $assets, string $type, array $options): string {
        if ('js' !== $type && 'css' !== $type) {
            throw new \Exception("Unknown asset type '$type'.");
        }

        $assets[$type] = $assets[$type] ?? [];

        $publicPath = $this->manifest['publicPath'];

        $func = 'js' === $type ? 'script' : 'css';

        $output = "";
        foreach ($assets[$type] as $asset) {
            $output .= $this->Html->$func(
                $publicPath . $asset,
                (
                    $options[$type] ?? $this->getConfig('defaultOptions.js') ?: []
                ) + ['integrity' => $this->manifest[$asset]['integrity'] ?? null]
            ) . "\n";
        }

        return $output;
    }
}
