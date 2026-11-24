// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data111.g.dart';

@JsonSerializable()
class Data111 {
  const Data111({required this.branchId, required this.ingredientId});

  factory Data111.fromJson(Map<String, Object?> json) =>
      _$Data111FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  @JsonKey(name: 'ingredient_id')
  final int ingredientId;

  Map<String, Object?> toJson() => _$Data111ToJson(this);
}
