// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'category.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Category _$CategoryFromJson(Map<String, dynamic> json) => Category(
  id: (json['id'] as num).toInt(),
  uuid: json['uuid'] as String,
  parentId: (json['parent_id'] as num?)?.toInt(),
  name: json['name'] as String,
  slug: json['slug'] as String,
  image: json['image'] as String?,
  sortOrder: (json['sort_order'] as num).toInt(),
  isActive: json['is_active'] as bool,
  createdAt: json['created_at'] == null
      ? null
      : DateTime.parse(json['created_at'] as String),
  updatedAt: json['updated_at'] == null
      ? null
      : DateTime.parse(json['updated_at'] as String),
  deletedAt: json['deleted_at'] == null
      ? null
      : DateTime.parse(json['deleted_at'] as String),
  imageUrl: json['image_url'] as String?,
  imageThumbUrls: ImageThumbUrls.fromJson(
    json['image_thumb_urls'] as Map<String, dynamic>,
  ),
  parent: json['parent'] == null
      ? null
      : Category.fromJson(json['parent'] as Map<String, dynamic>),
  children: (json['children'] as List<dynamic>?)
      ?.map((e) => Category.fromJson(e as Map<String, dynamic>))
      .toList(),
  products: (json['products'] as List<dynamic>?)
      ?.map((e) => Product.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$CategoryToJson(Category instance) => <String, dynamic>{
  'id': instance.id,
  'uuid': instance.uuid,
  'parent_id': instance.parentId,
  'name': instance.name,
  'slug': instance.slug,
  'image': instance.image,
  'sort_order': instance.sortOrder,
  'is_active': instance.isActive,
  'created_at': instance.createdAt?.toIso8601String(),
  'updated_at': instance.updatedAt?.toIso8601String(),
  'deleted_at': instance.deletedAt?.toIso8601String(),
  'image_url': instance.imageUrl,
  'image_thumb_urls': instance.imageThumbUrls,
  'parent': instance.parent,
  'children': instance.children,
  'products': instance.products,
};
