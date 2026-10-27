// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'variants.dart';

part 'data35.g.dart';

@JsonSerializable()
class Data35 {
  const Data35({
    required this.productId,
    required this.name,
    required this.description,
    required this.imageUrl,
    required this.batchTracked,
    required this.variants,
  });

  factory Data35.fromJson(Map<String, Object?> json) => _$Data35FromJson(json);

  @JsonKey(name: 'product_id')
  final int productId;
  final String name;
  final String? description;
  @JsonKey(name: 'image_url')
  final String? imageUrl;

  /// P2-30: sold from a batch (kazan): without an open batch on the branch it is sold out (docs/MENU.md §4)
  @JsonKey(name: 'batch_tracked')
  final bool batchTracked;
  final List<Variants> variants;

  Map<String, Object?> toJson() => _$Data35ToJson(this);
}
