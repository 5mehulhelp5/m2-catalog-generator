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

class CategoryNameResolver implements ResolverInterface
{
    /** @var string[] */
    private const CATEGORIES = [
        "Women's Fashion", "Men's Fashion", "Kids' Clothing", 'Activewear', 'Outerwear',
        'Accessories', 'Footwear', 'Bags & Luggage', 'Jewelry', 'Watches',
        'Home & Garden', 'Kitchen', 'Bedding', 'Bath & Towels', 'Furniture',
        'Lighting', 'Rugs & Carpets', 'Wall Art', 'Candles & Fragrance', 'Storage',
        'Electronics', 'Laptops', 'Smartphones', 'Audio', 'Cameras',
        'Tablets', 'Gaming', 'Smart Home', 'Wearables', 'Cables & Chargers',
        'Sports', 'Running', 'Yoga & Pilates', 'Cycling', 'Swimming',
        'Hiking', 'Camping', 'Fishing', 'Golf', 'Tennis',
        'Beauty', 'Skincare', 'Makeup', 'Hair Care', 'Fragrance',
        'Nail Care', 'Bath & Body', 'Sun Care', 'Men\'s Grooming', 'Tools & Brushes',
        'Toys & Games', 'Board Games', 'Puzzles', 'Action Figures', 'Dolls',
        'Building Sets', 'Outdoor Play', 'Arts & Crafts', 'Educational Toys', 'Plush Toys',
        'Books', 'Fiction', 'Non-Fiction', 'Cookbooks', 'Travel Guides',
        'Pet Supplies', 'Dog', 'Cat', 'Bird', 'Fish & Aquarium',
        'Office', 'Stationery', 'Desk Accessories', 'Planners', 'Notebooks',
        'Food & Drink', 'Coffee & Tea', 'Snacks', 'Gourmet', 'Organic',
        'Baby & Toddler', 'Nursery', 'Car Seats', 'Strollers', 'Baby Feeding',
        'Health & Wellness', 'Vitamins', 'Fitness', 'First Aid', 'Personal Care',
        'Automotive', 'Car Care', 'Tools', 'Parts', 'Interior Accessories',
        'Garden & Patio', 'Outdoor Furniture', 'Planters', 'Grills', 'Pool & Spa',
        'Seasonal', 'Summer Collection', 'Winter Essentials', 'Holiday Shop', 'New Arrivals',
        'Clearance', 'Best Sellers', 'Gift Ideas', 'Premium Collection', 'Eco-Friendly',
    ];

    /** @var int[] */
    private array $usedIndices = [];

    /**
     * Resolve Data
     *
     * @return string
     */
    public function resolveData(): string
    {
        $available = array_diff(array_keys(self::CATEGORIES), $this->usedIndices);

        if (empty($available)) {
            $this->usedIndices = [];
            $available = array_keys(self::CATEGORIES);
        }

        $index = $available[array_rand($available)];
        $this->usedIndices[] = $index;

        return self::CATEGORIES[$index];
    }
}
