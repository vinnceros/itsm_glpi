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

use Glpi\Asset\Asset;
use Glpi\Asset\AssetDefinition;
use Glpi\Http\Response;
use Glpi\Search\SearchEngine;

include('../../inc/includes.php');

$definition_id = (int)($_GET[AssetDefinition::getForeignKeyField()] ?? null);
$definition = new AssetDefinition();
if (
    $definition_id === 0
    || !$definition->getFromDB($definition_id)
) {
    Response::sendError(400, 'Bad request', Response::CONTENT_TYPE_TEXT_HTML);
}

if (!$definition->hasRightOnAssets(READ)) {
    Html::displayRightError(sprintf('User is missing the %d (%s) right for "%s" assets', READ, 'READ', $definition->getName()));
    exit();
}

Html::header(Asset::getTypeName(Session::getPluralNumber()), $_SERVER['PHP_SELF'], 'assets', $definition->getID());

SearchEngine::show(
    Asset::class,
    [
        'target' => Asset::getSearchURL(false) . '?' . AssetDefinition::getForeignKeyField() . '=' . $definition->getID(),
    ]
);

Html::footer();
