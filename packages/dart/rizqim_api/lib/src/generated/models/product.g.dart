// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'product.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Product _$ProductFromJson(Map<String, dynamic> json) => Product(
  id: (json['id'] as num).toInt(),
  uuid: json['uuid'] as String,
  storeId: (json['store_id'] as num).toInt(),
  categoryId: (json['category_id'] as num).toInt(),
  name: json['name'] as String,
  slug: json['slug'] as String,
  description: json['description'] as String?,
  type: json['type'] as String,
  sku: json['sku'] as String?,
  status: json['status'] as String,
  sortOrder: (json['sort_order'] as num).toInt(),
  hasVariants: json['has_variants'] as bool,
  createdAt: json['created_at'] == null
      ? null
      : DateTime.parse(json['created_at'] as String),
  updatedAt: json['updated_at'] == null
      ? null
      : DateTime.parse(json['updated_at'] as String),
  deletedAt: json['deleted_at'] == null
      ? null
      : DateTime.parse(json['deleted_at'] as String),
  image: json['image'] as String?,
  batchTracked: json['batch_tracked'] as bool,
  isActive: json['is_active'] as bool,
  imageUrl: json['image_url'] as String?,
  imageThumbUrls: ImageThumbUrls2.fromJson(
    json['image_thumb_urls'] as Map<String, dynamic>,
  ),
  store: json['store'] == null
      ? null
      : Store.fromJson(json['store'] as Map<String, dynamic>),
  category: json['category'] == null
      ? null
      : Category.fromJson(json['category'] as Map<String, dynamic>),
  variants: (json['variants'] as List<dynamic>?)
      ?.map((e) => ProductVariant.fromJson(e as Map<String, dynamic>))
      .toList(),
  modifierGroups: (json['modifier_groups'] as List<dynamic>?)
      ?.map((e) => ModifierGroup.fromJson(e as Map<String, dynamic>))
      .toList(),
  modifierGroupLinks: (json['modifier_group_links'] as List<dynamic>?)
      ?.map((e) => ProductModifierGroup.fromJson(e as Map<String, dynamic>))
      .toList(),
  channelPrices: (json['channel_prices'] as List<dynamic>?)
      ?.map((e) => ChannelPrice.fromJson(e as Map<String, dynamic>))
      .toList(),
  batches: (json['batches'] as List<dynamic>?)
      ?.map((e) => MenuBatch.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$ProductToJson(Product instance) => <String, dynamic>{
  'id': instance.id,
  'uuid': instance.uuid,
  'store_id': instance.storeId,
  'category_id': instance.categoryId,
  'name': instance.name,
  'slug': instance.slug,
  'description': instance.description,
  'type': instance.type,
  'sku': instance.sku,
  'status': instance.status,
  'sort_order': instance.sortOrder,
  'has_variants': instance.hasVariants,
  'created_at': instance.createdAt?.toIso8601String(),
  'updated_at': instance.updatedAt?.toIso8601String(),
  'deleted_at': instance.deletedAt?.toIso8601String(),
  'image': instance.image,
  'batch_tracked': instance.batchTracked,
  'is_active': instance.isActive,
  'image_url': instance.imageUrl,
  'image_thumb_urls': instance.imageThumbUrls,
  'store': instance.store,
  'category': instance.category,
  'variants': instance.variants,
  'modifier_groups': instance.modifierGroups,
  'modifier_group_links': instance.modifierGroupLinks,
  'channel_prices': instance.channelPrices,
  'batches': instance.batches,
};
