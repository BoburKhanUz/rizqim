// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data144.g.dart';

@JsonSerializable()
class Data144 {
  const Data144({required this.productId, required this.modifierGroups});

  factory Data144.fromJson(Map<String, Object?> json) =>
      _$Data144FromJson(json);

  @JsonKey(name: 'product_id')
  final int productId;
  @JsonKey(name: 'modifier_groups')
  final String modifierGroups;

  Map<String, Object?> toJson() => _$Data144ToJson(this);
}
