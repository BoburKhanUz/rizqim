// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'category.dart';
import 'image_thumb_urls.dart';
import 'product.dart';

part 'category.g.dart';

@JsonSerializable()
class Category {
  const Category({
    required this.id,
    required this.uuid,
    required this.parentId,
    required this.name,
    required this.slug,
    required this.image,
    required this.sortOrder,
    required this.isActive,
    required this.createdAt,
    required this.updatedAt,
    required this.deletedAt,
    required this.imageUrl,
    required this.imageThumbUrls,
    this.parent,
    this.children,
    this.products,
  });

  factory Category.fromJson(Map<String, Object?> json) =>
      _$CategoryFromJson(json);

  final int id;
  final String uuid;
  @JsonKey(name: 'parent_id')
  final int? parentId;
  final String name;
  final String slug;
  final String? image;
  @JsonKey(name: 'sort_order')
  final int sortOrder;
  @JsonKey(name: 'is_active')
  final bool isActive;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;
  @JsonKey(name: 'deleted_at')
  final DateTime? deletedAt;
  @JsonKey(name: 'image_url')
  final String? imageUrl;
  @JsonKey(name: 'image_thumb_urls')
  final ImageThumbUrls imageThumbUrls;
  final Category? parent;
  final List<Category>? children;
  final List<Product>? products;

  Map<String, Object?> toJson() => _$CategoryToJson(this);
}
