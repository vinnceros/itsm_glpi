<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2023 Teclib' and contributors.
 * @copyright 2003-2014 by the INDEPNET Development Team.
 * @licence   https://www.gnu.org/licenses/gpl-3.0.html
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * ---------------------------------------------------------------------
 */

namespace Glpi\Asset;

final class AssetDefinitionManager
{
    /**
     * Singleton instance
     */
    private static ?AssetDefinitionManager $instance = null;

    /**
     * Classes files directory.
     */
    private string $classes_dir;

    /**
     * Definitions cache.
     */
    private array $definitions_data;

    /**
     * Singleton constructor
     */
    private function __construct(string $classes_dir = GLPI_CACHE_DIR . '/assets')
    {
        $this->classes_dir = $classes_dir;
    }

    /**
     * Get singleton instance
     *
     * @return AssetDefinitionManager
     */
    public static function getInstance(string $classes_dir = GLPI_CACHE_DIR . '/assets'): self
    {
        if (!self::$instance) {
            self::$instance = new self($classes_dir);
        }

        return self::$instance;
    }

    public function bootstrapAssets(): void
    {
        spl_autoload_register([$this, 'autoloadAssetClass']);
    }

    /**
     * Autoload asset class, if requested class is a generic asset class.
     *
     * @param string $classname
     * @return void
     */
    public function autoloadAssetClass(string $classname): void
    {
        $asset_class_pattern = '/^Glpi\\\Asset\\\Asset(\d+)$/';
        if (preg_match($asset_class_pattern, $classname) !== 1) {
            return;
        }

        $definition_id = (int)preg_replace($asset_class_pattern, '$1', $classname);
        $definition    = $this->getDefinition($definition_id);

        if ($definition === null) {
            return;
        }

        $this->loadDefinitionClass($definition);
    }

    /**
     * Get the classes names of all assets concrete classes.
     *
     * @param bool $with_namespace
     * @return array
     */
    public function getConcreteClassesNames(bool $with_namespace = true): array
    {
        $classes = [];

        foreach ($this->getDefinitions() as $definition) {
            if (!$definition->isActive()) {
                continue;
            }
            $classes[] = $definition->getConcreteClassName($with_namespace);
        }

        return $classes;
    }

    /**
     * Get the asset definition corresponding to given id.
     *
     * @param int $definition_id
     * @return AssetDefinition|null
     */
    private function getDefinition(int $definition_id): ?AssetDefinition
    {
        return $this->getDefinitions()[$definition_id] ?? null;
    }

    /**
     * Get all the asset definitions.
     *
     * @return AssetDefinition[]
     */
    private function getDefinitions(): array
    {
        if (!isset($this->definitions_data)) {
            $this->definitions_data = getAllDataFromTable(AssetDefinition::getTable());
        }

        $definitions = [];
        foreach ($this->definitions_data as $definition_id => $definition_data) {
            $definition = new AssetDefinition();
            $definition->getFromResultSet($definition_data);
            $definitions[$definition_id] = $definition;
        }

        return $definitions;
    }

    /**
     * Load asset definition class.
     *
     * @param AssetDefinition $definition
     * @return void
     */
    private function loadDefinitionClass(AssetDefinition $definition): void
    {
        $definition_fields = var_export($definition->fields, true);
        $class_str = <<<PHP
<?php
namespace Glpi\Asset;

final class {$definition->getConcreteClassName(false)} extends Asset
{
    public static function getDefinition(): AssetDefinition
    {
        \$definition = new AssetDefinition();
        \$definition->fields = {$definition_fields};
        return \$definition;
    }
}
PHP;

        $class_file = sprintf('%s/%s.php', $this->classes_dir, $definition->getID());

        if (!file_exists($class_file) || sha1_file($class_file) !== sha1($class_str)) {
            // (re)build file if it not exists or is not valid anymore.
            if (!is_dir($this->classes_dir) && !mkdir($this->classes_dir)) {
                throw new \RuntimeException(sprintf('Unable to create assets classes directory `%s`.', $this->classes_dir));
            }

            if (
                (file_exists($class_file) && !is_writable($class_file))
                || file_put_contents($class_file, $class_str, LOCK_EX) !== strlen($class_str)
            ) {
                throw new \RuntimeException(sprintf('Unable to create asset `%s` class file.', $definition->getName()));
            }
        }

        include_once($class_file);
    }
}
