<?php

/**
 * -------------------------------------------------------------------------
 * Fields plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of Fields.
 *
 * Fields is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Fields is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Fields. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2013-2023 by Fields plugin team.
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/pluginsGLPI/fields
 * -------------------------------------------------------------------------
 */

use Glpi\DBAL\JsonFieldInterface;

class PluginFieldsQuestionTypeExtraDataConfig implements JsonFieldInterface
{
    // Unique reference to hardcoded name used for serialization
    public const BLOCK_ID = "block_id";

    public const FIELD_ID = "field_id";

    public function __construct(
        private readonly ?int $block_id = null,
        private readonly ?int $field_id = null,
    ) {}

    #[Override]
    public static function jsonDeserialize(array $data): self
    {
        return new self(
            block_id: $data[self::BLOCK_ID] ?? null,
            field_id: $data[self::FIELD_ID] ?? null,
        );
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return [
            self::BLOCK_ID => $this->block_id,
            self::FIELD_ID => $this->field_id,
        ];
    }

    public function getBlockId(): ?int
    {
        return $this->block_id;
    }

    public function getFieldId(): ?int
    {
        return $this->field_id;
    }
}
