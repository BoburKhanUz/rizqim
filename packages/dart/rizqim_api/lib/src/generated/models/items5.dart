// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'items5.g.dart';

@JsonSerializable()
class Items5 {
  const Items5({
    required this.id,
    required this.productId,
    required this.productVariantId,
    required this.name,
    required this.quantityPerPerson,
    required this.unit,
  });

  factory Items5.fromJson(Map<String, Object?> json) => _$Items5FromJson(json);

  final int id;
  @JsonKey(name: 'product_id')
  final int? productId;
  @JsonKey(name: 'product_variant_id')
  final int? productVariantId;
  final String name;
  @JsonKey(name: 'quantity_per_person')
  final String quantityPerPerson;
  final String unit;

  Map<String, Object?> toJson() => _$Items5ToJson(this);
}
