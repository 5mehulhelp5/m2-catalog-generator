<?php
/**
 * Copyright © Qoliber. All rights reserved.
 *
 * @category    Qoliber
 * @package     Qoliber_CatalogGenerator
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\CatalogGenerator\Resolver;

use Qoliber\CatalogGenerator\Api\Resolver\ResolverInterface;

class NameResolver implements ResolverInterface
{
    /** @var string[] */
    private const ADJECTIVES = [
        'Alpine', 'Arctic', 'Aspen', 'Autumn', 'Azure', 'Birch', 'Blazing', 'Bold',
        'Breeze', 'Canyon', 'Cedar', 'Classic', 'Coastal', 'Copper', 'Crimson', 'Crystal',
        'Dawn', 'Desert', 'Dusk', 'Edge', 'Elite', 'Ember', 'Everest', 'Falcon',
        'Fjord', 'Flex', 'Forest', 'Frost', 'Golden', 'Granite', 'Harbor', 'Horizon',
        'Indigo', 'Iron', 'Ivory', 'Jade', 'Lunar', 'Maple', 'Marine', 'Mesa',
        'Midnight', 'Nordic', 'Nova', 'Oasis', 'Onyx', 'Pacific', 'Peak', 'Pine',
        'Polar', 'Prairie', 'Prism', 'Pulse', 'Raven', 'Ridge', 'River', 'Rustic',
        'Sage', 'Sierra', 'Silver', 'Slate', 'Solar', 'Spirit', 'Steel', 'Stone',
        'Storm', 'Summit', 'Terra', 'Timber', 'Trail', 'Tundra', 'Urban', 'Venture',
        'Vertex', 'Vista', 'Vortex', 'Wave', 'Willow', 'Zenith',
    ];

    /** @var string[] */
    private const MATERIALS = [
        'Canvas', 'Carbon', 'Cashmere', 'Chambray', 'Chiffon', 'Corduroy', 'Cotton',
        'Denim', 'Down', 'Fleece', 'Hemp', 'Jersey', 'Knit', 'Lace', 'Leather',
        'Linen', 'Merino', 'Mesh', 'Microfiber', 'Neoprene', 'Nylon', 'Organic',
        'Oxford', 'Percale', 'Poplin', 'Quilted', 'Rayon', 'Ripstop', 'Sateen',
        'Satin', 'Silk', 'Suede', 'Tweed', 'Twill', 'Velvet', 'Viscose', 'Wool',
    ];

    /** @var string[] */
    private const PRODUCTS = [
        'Backpack', 'Belt', 'Blazer', 'Blouse', 'Boots', 'Cap', 'Cardigan', 'Cargo Pants',
        'Chinos', 'Coat', 'Crossbody Bag', 'Dress', 'Duffle Bag', 'Gloves', 'Henley',
        'Hoodie', 'Jacket', 'Jeans', 'Joggers', 'Jumpsuit', 'Leggings', 'Loafers',
        'Messenger Bag', 'Mittens', 'Moccasins', 'Overalls', 'Parka', 'Peacoat',
        'Polo Shirt', 'Pullover', 'Raincoat', 'Romper', 'Sandals', 'Scarf', 'Shorts',
        'Skirt', 'Slippers', 'Sneakers', 'Socks', 'Sunglasses', 'Sweater', 'Swimsuit',
        'T-Shirt', 'Tank Top', 'Tote Bag', 'Trench Coat', 'Trousers', 'Tunic',
        'Turtleneck', 'Vest', 'Wallet', 'Watch', 'Windbreaker', 'Wrap',
    ];

    /**
     * Generate a realistic product name
     *
     * @return string
     */
    public function generateName(): string
    {
        $pattern = rand(1, 3);

        return match ($pattern) {
            1 => sprintf(
                '%s %s',
                self::ADJECTIVES[array_rand(self::ADJECTIVES)],
                self::PRODUCTS[array_rand(self::PRODUCTS)]
            ),
            2 => sprintf(
                '%s %s %s',
                self::ADJECTIVES[array_rand(self::ADJECTIVES)],
                self::MATERIALS[array_rand(self::MATERIALS)],
                self::PRODUCTS[array_rand(self::PRODUCTS)]
            ),
            3 => sprintf(
                '%s %s',
                self::MATERIALS[array_rand(self::MATERIALS)],
                self::PRODUCTS[array_rand(self::PRODUCTS)]
            ),
        };
    }

    /**
     * Resolve Data
     *
     * @return string
     */
    public function resolveData(): string
    {
        return $this->generateName();
    }
}
