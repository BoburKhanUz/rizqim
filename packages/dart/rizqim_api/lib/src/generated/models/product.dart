// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'category.dart';
import 'channel_price.dart';
import 'image_thumb_urls2.dart';
import 'menu_batch.dart';
import 'modifier_group.dart';
import 'product_kind.dart';
import 'product_modifier_group.dart';
import 'product_variant.dart';
import 'store.dart';

part 'product.g.dart';

@JsonSerializable()
class Product {
  const Product({
    required this.hasVariants,
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
    required this.id,
    required this.createdAt,
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
    required this.imageThumbUrls,
    required this.weightMode,
    required this.kdsTargetSeconds,
    required this.isActive,
    required this.imageUrl,
    required this.waitMinutes,
    this.store,
    this.category,
    this.variants,
    this.modifierGroups,
    this.modifierGroupLinks,
    this.channelPrices,
    this.batches,
  });

  factory Product.fromJson(Map<String, Object?> json) =>
      _$ProductFromJson(json);

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
  @JsonKey(name: 'kds_target_seconds')
  final int? kdsTargetSeconds;
  @JsonKey(name: 'is_active')
  final bool isActive;
  @JsonKey(name: 'image_url')
  final String? imageUrl;
  @JsonKey(name: 'image_thumb_urls')
  final ImageThumbUrls2 imageThumbUrls;
  final Store? store;
  final Category? category;
  final List<ProductVariant>? variants;
  @JsonKey(name: 'modifier_groups')
  final List<ModifierGroup>? modifierGroups;
  @JsonKey(name: 'modifier_group_links')
  final List<ProductModifierGroup>? modifierGroupLinks;
  @JsonKey(name: 'channel_prices')
  final List<ChannelPrice>? channelPrices;
  final List<MenuBatch>? batches;

  Map<String, Object?> toJson() => _$ProductToJson(this);
}
