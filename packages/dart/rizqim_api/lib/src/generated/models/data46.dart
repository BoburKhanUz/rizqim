// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data46.g.dart';

@JsonSerializable()
class Data46 {
  const Data46({required this.productId, required this.modifierGroups});

  factory Data46.fromJson(Map<String, Object?> json) => _$Data46FromJson(json);

  @JsonKey(name: 'product_id')
  final int productId;
  @JsonKey(name: 'modifier_groups')
  final String modifierGroups;

  Map<String, Object?> toJson() => _$Data46ToJson(this);
}
