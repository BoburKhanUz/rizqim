// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'slots3.dart';

part 'data61.g.dart';

@JsonSerializable()
class Data61 {
  const Data61({
    required this.id,
    required this.uuid,
    required this.productId,
    required this.productName,
    required this.prices,
    required this.isActive,
    required this.slots,
  });

  factory Data61.fromJson(Map<String, Object?> json) => _$Data61FromJson(json);

  final int id;
  final String uuid;
  @JsonKey(name: 'product_id')
  final int productId;
  @JsonKey(name: 'product_name')
  final String? productName;
  final List<String> prices;
  @JsonKey(name: 'is_active')
  final bool isActive;
  final List<Slots3> slots;

  Map<String, Object?> toJson() => _$Data61ToJson(this);
}
