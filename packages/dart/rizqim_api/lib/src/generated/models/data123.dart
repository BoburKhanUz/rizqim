// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'parts4.dart';

part 'data123.g.dart';

@JsonSerializable()
class Data123 {
  const Data123({
    required this.id,
    required this.uuid,
    required this.productId,
    required this.productName,
    required this.prices,
    required this.isActive,
    required this.parts,
  });

  factory Data123.fromJson(Map<String, Object?> json) =>
      _$Data123FromJson(json);

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

  Map<String, Object?> toJson() => _$Data123ToJson(this);
}
