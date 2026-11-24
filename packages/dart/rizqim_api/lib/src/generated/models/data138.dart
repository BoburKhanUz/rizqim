// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data138.g.dart';

@JsonSerializable()
class Data138 {
  const Data138({
    required this.id,
    required this.uuid,
    required this.name,
    required this.price,
    required this.pricing,
    required this.pricingLabel,
    required this.isActive,
    required this.sortOrder,
  });

  factory Data138.fromJson(Map<String, Object?> json) =>
      _$Data138FromJson(json);

  final int id;
  final String uuid;
  final String name;
  final String price;
  final String pricing;
  @JsonKey(name: 'pricing_label')
  final String pricingLabel;
  @JsonKey(name: 'is_active')
  final bool isActive;
  @JsonKey(name: 'sort_order')
  final int sortOrder;

  Map<String, Object?> toJson() => _$Data138ToJson(this);
}
