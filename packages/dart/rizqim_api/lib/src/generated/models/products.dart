// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'category.dart';
import 'channel_price.dart';
import 'image_thumb_urls2.dart';
import 'menu_batch.dart';
import 'modifier_group.dart';
import 'product.dart';
import 'product_kind.dart';
import 'product_modifier_group.dart';
import 'product_variant.dart';
import 'products_modifier_groups.dart';
import 'products_unavailable_reason.dart';
import 'products_variants.dart';
import 'store.dart';

part 'products.g.dart';

@JsonSerializable()
class Products {
  const Products({
    required this.createdAt,
    required this.uuid,
    required this.storeId,
    required this.categoryId,
    required this.name,
    required this.slug,
    required this.description,
    required this.type,
    required this.sku,
    required this.status,
    required this.sortOrder,
    required this.hasVariants,
    required this.id,
    required this.updatedAt,
    required this.deletedAt,
    required this.image,
    required this.batchTracked,
    required this.kind,
    required this.mxikCode,
    required this.packageCode,
    required this.vatRate,
    required this.quickAdd,
    required this.quickMultipliers,
    required this.waitMinutes,
    required this.stationId,
    required this.isActive,
    required this.imageUrl,
    required this.imageThumbUrls,
    required this.unavailableReason,
    required this.isAvailable,
    required this.variants,
    required this.weightMode,
    required this.modifierGroups,
    this.channelPrices,
    this.batches,
    this.category,
    this.store,
    this.modifierGroupLinks,
  });

  factory Products.fromJson(Map<String, Object?> json) =>
      _$ProductsFromJson(json);

  final int id;
  final String uuid;
  @JsonKey(name: 'store_id')
  final int storeId;
  @JsonKey(name: 'category_id')
  final int categoryId;
  final String name;
  final String slug;
  final String? description;
  final String type;
  final String? sku;
  final String status;
  @JsonKey(name: 'sort_order')
  final int sortOrder;
  @JsonKey(name: 'has_variants')
  final bool hasVariants;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;
  @JsonKey(name: 'deleted_at')
  final DateTime? deletedAt;
  final String? image;
  @JsonKey(name: 'batch_tracked')
  final bool batchTracked;
  final ProductKind kind;
  @JsonKey(name: 'mxik_code')
  final String? mxikCode;
  @JsonKey(name: 'package_code')
  final String? packageCode;
  @JsonKey(name: 'vat_rate')
  final String? vatRate;
  @JsonKey(name: 'quick_add')
  final bool quickAdd;
  @JsonKey(name: 'quick_multipliers')
  final List<int>? quickMultipliers;
  @JsonKey(name: 'wait_minutes')
  final int? waitMinutes;
  @JsonKey(name: 'weight_mode')
  final String? weightMode;
  @JsonKey(name: 'is_active')
  final bool isActive;
  @JsonKey(name: 'image_url')
  final String? imageUrl;
  @JsonKey(name: 'image_thumb_urls')
  final ImageThumbUrls2 imageThumbUrls;
  final Store? store;
  final Category? category;
  final List<ProductsVariants> variants;
  @JsonKey(name: 'modifier_groups')
  final List<ProductsModifierGroups> modifierGroups;
  @JsonKey(name: 'modifier_group_links')
  final List<ProductModifierGroup>? modifierGroupLinks;
  @JsonKey(name: 'channel_prices')
  final List<ChannelPrice>? channelPrices;
  final List<MenuBatch>? batches;
  @JsonKey(name: 'is_available')
  final bool isAvailable;
  @JsonKey(name: 'unavailable_reason')
  final ProductsUnavailableReason? unavailableReason;
  @JsonKey(name: 'station_id')
  final int? stationId;

  Map<String, Object?> toJson() => _$ProductsToJson(this);
}
