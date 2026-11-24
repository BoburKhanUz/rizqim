// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data109.g.dart';

@JsonSerializable()
class Data109 {
  const Data109({required this.branchId, required this.ingredients});

  factory Data109.fromJson(Map<String, Object?> json) =>
      _$Data109FromJson(json);

  @JsonKey(name: 'branch_id')
  final int? branchId;
  final List<String> ingredients;

  Map<String, Object?> toJson() => _$Data109ToJson(this);
}
