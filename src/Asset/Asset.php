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

use CommonDBTM;
use Glpi\Application\View\TemplateRenderer;
use Glpi\Plugin\Hooks;
use Entity;
use Plugin;
use Session;
use Toolbox;

abstract class Asset extends CommonDBTM
{
    /**
     * Get the asset definition related to concrete class.
     *
     * @return AssetDefinition
     */
    abstract protected static function getDefinition(): AssetDefinition;

    public static function getTypeName($nb = 0)
    {
        return static::getDefinition()->getTranslatedName($nb);
    }

    public static function getIcon()
    {
        return static::getDefinition()->getAssetsIcon();
    }

    public static function getTable($classname = null)
    {
        if (is_a($classname ?? static::class, self::class, true)) {
            return getTableForItemType(self::class);
        }
        return parent::getTable($classname);
    }

    public static function getSearchURL($full = true)
    {
        return Toolbox::getItemTypeSearchURL(self::class, $full)
            . '?'
            . AssetDefinition::getForeignKeyField()
            . '='
            . static::getDefinition()->getID();
    }

    public static function getFormURL($full = true)
    {
        return Toolbox::getItemTypeFormURL(self::class, $full)
            . '?'
            . AssetDefinition::getForeignKeyField()
            . '='
            . static::getDefinition()->getID();
    }

    public static function canView()
    {
        return static::hasGlobalRight(READ);
    }

    public static function canCreate()
    {
        return static::hasGlobalRight(CREATE);
    }

    public static function canUpdate()
    {
        return static::hasGlobalRight(UPDATE);
    }

    public static function canDelete()
    {
        return static::hasGlobalRight(DELETE);
    }

    public static function canPurge()
    {
        return static::hasGlobalRight(PURGE);
    }

    /**
     * Check if current user has the required global right.
     *
     * @param int $right
     * @return bool
     */
    private static function hasGlobalRight(int $right): bool
    {
        return static::getDefinition()->hasRightOnAssets($right);
    }

    public function canViewItem()
    {
        return $this->hasItemRight(READ) && parent::canViewItem();
    }

    public function canCreateItem()
    {
        return $this->hasItemRight(CREATE) && parent::canCreateItem();
    }

    public function canUpdateItem()
    {
        return $this->hasItemRight(UPDATE) && parent::canUpdateItem();
    }

    public function canDeleteItem()
    {
        return $this->hasItemRight(DELETE) && parent::canDeleteItem();
    }

    public function canPurgeItem()
    {
        return $this->hasItemRight(PURGE) && parent::canPurgeItem();
    }

    public function can($ID, $right, array &$input = null)
    {
        $ID = (int)$ID;

        if ($this->isNewID($ID)) {
            if (!isset($this->fields['id'])) {
                $this->getEmpty();
            }

            if (is_array($input)) {
                $this->input = $input;
            }

            // Rely only on `canCreateItem()` that will check rights based on asset definition.
            return $this->canCreateItem();
        }

        if ((!isset($this->fields['id']) || $this->fields['id'] != $ID) && !$this->getFromDB($ID)) {
            // Ensure the right item is loaded.
            return false;
        }
        $this->right = $right;

        Plugin::doHook(Hooks::ITEM_CAN, $this);
        if ($this->right !== $right) {
            return false;
        }
        $this->right = null;

        switch ($right) {
            case READ:
                // Rely only on `canViewItem()` that will check rights based on asset definition.
                return $this->canViewItem();

            case UPDATE:
                // Rely only on `canUpdateItem()` that will check rights based on asset definition.
                return $this->canUpdateItem();

            case DELETE:
                // Rely only on `canDeleteItem()` that will check rights based on asset definition.
                return $this->canDeleteItem();

            case PURGE:
                // Rely only on `canPurgeItem()` that will check rights based on asset definition.
                return $this->canPurgeItem();

            case CREATE:
                // Rely only on `canPurgeItem()` that will check rights based on asset definition.
                return $this->canCreateItem();

            case 'recursive':
                // Can make recursive if recursive access to entity
                return Session::haveAccessToEntity($this->getEntityID())
                    && Session::haveRecursiveAccessToEntity($this->getEntityID());
        }

        return false;
    }

    /**
     * Check if current user has the required right on current item.
     *
     * @param int $right
     * @return bool
     */
    private function hasItemRight(int $right): bool
    {
        $definition_id = $this->isNewItem()
            ? ($this->input[AssetDefinition::getForeignKeyField()] ?? 0)
            : ($this->fields[AssetDefinition::getForeignKeyField()] ?? 0);
        $definition = new AssetDefinition();
        if ($definition_id === 0 || !$definition->getFromDB($definition_id)) {
            return false;
        }

        return $definition->hasRightOnAssets($right);
    }

    public function rawSearchOptions()
    {
        $search_options = parent::rawSearchOptions();

        // TODO Search options

        $search_options[] = [
            'id'                 => '80',
            'table'              => 'glpi_entities',
            'field'              => 'completename',
            'name'               => Entity::getTypeName(1),
            'datatype'           => 'dropdown'
        ];

        $search_options[] = [
            'id'                 => '3',
            'table'              => $this->getTable(),
            'field'              => AssetDefinition::getForeignKeyField(),
            'name'               => AssetDefinition::getTypeName(),
            'massiveaction'      => false,
            'nosearch'           => true,
            'nodisplay'          => true,
        ];

        foreach ($search_options as &$search_option) {
            if (
                is_array($search_option)
                && array_key_exists('table', $search_option)
                && $search_option['table'] === $this->getTable()
            ) {
                // Search class could not be able to retrieve the concrete class when using `getItemTypeForTable()`,
                // so we have to define an `itemtype` here.
                $search_option['itemtype'] = static::class;
            }
        }

        return $search_options;
    }

    public static function getSystemCriteria(): array
    {
        // In search pages, only items from current definition must be shown.
        return [
            [
                'field'      => 3,
                'searchtype' => 'equals',
                'value'      => static::getDefinition()->getID()
            ]
        ];
    }

    public function showForm($ID, array $options = [])
    {
        $this->initForm($ID, $options);
        TemplateRenderer::getInstance()->display('pages/assets/asset.html.twig', [
            'item'   => $this,
            'params' => $options,
        ]);
        return true;
    }
}
