// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'products5.g.dart';

@JsonSerializable()
class Products5 {
  const Products5({
    required this.productId,
    required this.name,
    required this.categoryId,
    required this.targetSeconds,
  });

  factory Products5.fromJson(Map<String, Object?> json) =>
      _$Products5FromJson(json);

  @JsonKey(name: 'product_id')
  final int productId;
  final String name;
  @JsonKey(name: 'category_id')
  final int categoryId;
  @JsonKey(name: 'target_seconds')
  final int targetSeconds;

  Map<String, Object?> toJson() => _$Products5ToJson(this);
}
