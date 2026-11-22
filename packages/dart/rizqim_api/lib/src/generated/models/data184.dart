// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data184.g.dart';

@JsonSerializable()
class Data184 {
  const Data184({required this.productId, required this.modifierGroups});

  factory Data184.fromJson(Map<String, Object?> json) =>
      _$Data184FromJson(json);

  @JsonKey(name: 'product_id')
  final int productId;
  @JsonKey(name: 'modifier_groups')
  final String modifierGroups;

  Map<String, Object?> toJson() => _$Data184ToJson(this);
}
