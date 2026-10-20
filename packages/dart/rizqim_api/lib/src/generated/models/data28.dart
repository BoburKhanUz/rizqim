// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'variants.dart';

part 'data28.g.dart';

@JsonSerializable()
class Data28 {
  const Data28({
    required this.productId,
    required this.name,
    required this.description,
    required this.imageUrl,
    required this.variants,
  });

  factory Data28.fromJson(Map<String, Object?> json) => _$Data28FromJson(json);

  @JsonKey(name: 'product_id')
  final int productId;
  final String name;
  final String? description;
  @JsonKey(name: 'image_url')
  final String? imageUrl;
  final List<Variants> variants;

  Map<String, Object?> toJson() => _$Data28ToJson(this);
}
