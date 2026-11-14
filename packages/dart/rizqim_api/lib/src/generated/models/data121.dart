// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'parts4.dart';

part 'data121.g.dart';

@JsonSerializable()
class Data121 {
  const Data121({
    required this.id,
    required this.uuid,
    required this.productId,
    required this.productName,
    required this.prices,
    required this.isActive,
    required this.parts,
  });

  factory Data121.fromJson(Map<String, Object?> json) =>
      _$Data121FromJson(json);

  final int id;
  final String uuid;
  @JsonKey(name: 'product_id')
  final int productId;
  @JsonKey(name: 'product_name')
  final String productName;
  final List<String> prices;
  @JsonKey(name: 'is_active')
  final bool isActive;
  final List<Parts4> parts;

  Map<String, Object?> toJson() => _$Data121ToJson(this);
}
