// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'unit2.dart';

part 'items6.g.dart';

@JsonSerializable()
class Items6 {
  const Items6({
    required this.quantityPerPerson,
    this.productId,
    this.productVariantId,
    this.name,
    this.unit,
  });

  factory Items6.fromJson(Map<String, Object?> json) => _$Items6FromJson(json);

  @JsonKey(name: 'product_id')
  final int? productId;
  @JsonKey(name: 'product_variant_id')
  final int? productVariantId;
  final String? name;
  @JsonKey(name: 'quantity_per_person')
  final num quantityPerPerson;
  final Unit2? unit;

  Map<String, Object?> toJson() => _$Items6ToJson(this);
}
