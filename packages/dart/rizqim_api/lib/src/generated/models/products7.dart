// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'products7.g.dart';

@JsonSerializable()
class Products7 {
  const Products7({
    required this.name,
    required this.quantity,
    required this.revenue,
    required this.orders,
  });

  factory Products7.fromJson(Map<String, Object?> json) =>
      _$Products7FromJson(json);

  final String name;
  final num quantity;
  final String revenue;
  final int orders;

  Map<String, Object?> toJson() => _$Products7ToJson(this);
}
