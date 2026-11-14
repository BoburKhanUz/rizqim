// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'dishes4.g.dart';

@JsonSerializable()
class Dishes4 {
  const Dishes4({
    required this.productId,
    required this.name,
    required this.unit,
    required this.quantityPerPerson,
    required this.quantity,
    required this.kg,
  });

  factory Dishes4.fromJson(Map<String, Object?> json) =>
      _$Dishes4FromJson(json);

  @JsonKey(name: 'product_id')
  final int? productId;
  final String name;
  final String unit;
  @JsonKey(name: 'quantity_per_person')
  final String? quantityPerPerson;
  final String quantity;
  final String? kg;

  Map<String, Object?> toJson() => _$Dishes4ToJson(this);
}
