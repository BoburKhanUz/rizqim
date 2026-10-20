// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'top_products.g.dart';

@JsonSerializable()
class TopProducts {
  const TopProducts({
    required this.name,
    required this.quantity,
    required this.revenue,
  });

  factory TopProducts.fromJson(Map<String, Object?> json) =>
      _$TopProductsFromJson(json);

  final String name;
  final num quantity;
  final num revenue;

  Map<String, Object?> toJson() => _$TopProductsToJson(this);
}
