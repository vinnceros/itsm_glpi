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
use Html;
use Entity;
use Plugin;
use Session;

final class Asset extends CommonDBTM
{
    public static function canView()
    {
        return self::hasGlobalRight(READ);
    }

    public static function canCreate()
    {
        return self::hasGlobalRight(CREATE);
    }

    public static function canUpdate()
    {
        return self::hasGlobalRight(UPDATE);
    }

    public static function canDelete()
    {
        return self::hasGlobalRight(DELETE);
    }

    public static function canPurge()
    {
        return self::hasGlobalRight(PURGE);
    }

    /**
     * Check if current user has the required global right.
     *
     * @param int $right
     * @return bool
     */
    private static function hasGlobalRight(int $right): bool
    {
        // From a static call, we cannot know what AssetDefinition is supposed to be used.
        //
        // If AssetDefinition is defined in the request, we assume that rights checks is related to this definition,
        // otherwise, for security reasons, we have to consider that user has not the required right.
        //
        // FIXME Find a better way to check rights.

        $definition_id = (int)($_REQUEST[AssetDefinition::getForeignKeyField()] ?? null);
        $definition = new AssetDefinition();
        if ($definition_id > 0 && $definition->getFromDB($definition_id)) {
            return $definition->hasRightOnAssets($right);
        }

        return false;
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

    public static function getMenuContent()
    {
        global $DB;

        $menu = [];

        $definitions_iterator = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => AssetDefinition::getTable(),
            'WHERE'  => [
                'is_active' => 1,
            ],
        ]);

        /* @var \Glpi\Asset\AssetDefinition $definition */
        foreach (AssetDefinition::getFromIter($definitions_iterator) as $definition) {
            if (!$definition->hasRightOnAssets(READ)) {
                continue;
            }

            $definition_param = AssetDefinition::getForeignKeyField() . '=' . $definition->getID();

            $links = [
                'search' => self::getSearchURL(false) . '?' . $definition_param,
            ];
            if ($definition->hasRightOnAssets(CREATE)) {
                $links['add'] = self::getFormUrl(false) . '?' . $definition_param;
            }

            $menu[$definition->getID()] = [
                'title' => $definition->getTranslatedName(Session::getPluralNumber()),
                'icon'  => $definition->getAssetsIcon(),
                'page'  => $links['search'],
                'links' => $links
            ];
        }

        $menu['is_multi_entries'] = true;

        return $menu;
    }

    public function rawSearchOptions()
    {
        $so = parent::rawSearchOptions();

        // TODO Search options

        $so[] = [
            'id'                 => '80',
            'table'              => 'glpi_entities',
            'field'              => 'completename',
            'name'               => Entity::getTypeName(1),
            'datatype'           => 'dropdown'
        ];

        $so[] = [
            'id'                 => '3',
            'table'              => $this->getTable(),
            'field'              => AssetDefinition::getForeignKeyField(),
            'name'               => AssetDefinition::getTypeName(),
            'massiveaction'      => false,
            'nosearch'           => true,
            'nodisplay'          => true,
        ];

        return $so;
    }

    public static function getSystemCriteria(): array
    {
        // In search pages, definition
        return [
            [
                'field'      => 3,
                'searchtype' => 'equals',
                'value'      => (int)($_REQUEST[AssetDefinition::getForeignKeyField()] ?? null)
            ]
        ];
    }

    public function redirectToList()
    {
        Html::redirect(
            $this->getSearchURL() . '?' . AssetDefinition::getForeignKeyField() . '=' . $this->fields[AssetDefinition::getForeignKeyField()]
        );
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
