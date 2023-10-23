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

final class AssetDefinition extends CommonDBTM
{
    public static $rightname = 'config';

    public static function getTypeName($nb = 0)
    {
        return _n('Asset definition', 'Asset definitions', $nb);
    }

    public static function getIcon()
    {
        return 'ti ti-database-cog';
    }

    public static function canCreate()
    {
        // required due to usage of `config` rightname
        return static::canUpdate();
    }

    public static function canPurge()
    {
        // required due to usage of `config` rightname
        return static::canUpdate();
    }

    public function showForm($ID, array $options = [])
    {
        $this->initForm($ID, $options);
        TemplateRenderer::getInstance()->display('pages/admin/assetdefinition.html.twig', [
            'item'   => $this,
            'params' => $options,
        ]);
        return true;
    }

    /**
     * Check if connected user has given right on assets from current definition.
     *
     * @param int $right
     * @return bool
     */
    public function hasRightOnAssets(int $right): bool
    {
        // TODO Fine-grain rights management.
        return true;
    }

    /**
     * Return translated name.
     *
     * @param int $count
     * @return string
     */
    public function getTranslatedName(int $count = 1): string
    {
        // TODO Return translated plural form.
        return $this->fields['name'];
    }

    /**
     * Return icon to use for assets.
     *
     * @return string
     */
    public function getAssetsIcon(): string
    {
        return $this->fields['icon'] ?: 'ti ti-box';
    }

    public function rawSearchOptions()
    {
        $so = parent::rawSearchOptions();

        // TODO Search options

        return $so;
    }

    /**
     * Get the definition's concerte asset class name.
     *
     * @param bool $with_namespace
     * @return string|null
     */
    public function getConcreteClassName(bool $with_namespace = true): string
    {
        return sprintf(
            ($with_namespace ? 'Glpi\\Asset\\' : '') . 'Asset%s',
            $this->getID()
        );
    }
}
