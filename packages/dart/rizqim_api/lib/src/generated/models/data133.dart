// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'user_role.dart';

part 'data133.g.dart';

@JsonSerializable()
class Data133 {
  const Data133({
    required this.id,
    required this.name,
    required this.role,
    required this.branchId,
  });

  factory Data133.fromJson(Map<String, Object?> json) =>
      _$Data133FromJson(json);

  final int id;
  final String name;
  final UserRole role;
  @JsonKey(name: 'branch_id')
  final int? branchId;

  Map<String, Object?> toJson() => _$Data133ToJson(this);
}
