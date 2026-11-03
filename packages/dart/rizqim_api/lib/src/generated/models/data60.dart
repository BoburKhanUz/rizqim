// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'slots2.dart';

part 'data60.g.dart';

@JsonSerializable()
class Data60 {
  const Data60({
    required this.id,
    required this.uuid,
    required this.productId,
    required this.productName,
    required this.prices,
    required this.isActive,
    required this.slots,
  });

  factory Data60.fromJson(Map<String, Object?> json) => _$Data60FromJson(json);

  final int id;
  final String uuid;
  @JsonKey(name: 'product_id')
  final int productId;
  @JsonKey(name: 'product_name')
  final String? productName;
  final List<String> prices;
  @JsonKey(name: 'is_active')
  final bool isActive;
  final List<Slots2> slots;

  Map<String, Object?> toJson() => _$Data60ToJson(this);
}
