// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'components3.dart';

part 'data204.g.dart';

@JsonSerializable()
class Data204 {
  const Data204({
    required this.uuid,
    required this.branchId,
    required this.userId,
    required this.userName,
    required this.role,
    required this.roleLabel,
    required this.effectiveFrom,
    required this.components,
    required this.note,
    required this.updatedAt,
  });

  factory Data204.fromJson(Map<String, Object?> json) =>
      _$Data204FromJson(json);

  final String uuid;
  @JsonKey(name: 'branch_id')
  final int branchId;
  @JsonKey(name: 'user_id')
  final int? userId;
  @JsonKey(name: 'user_name')
  final String? userName;
  final String? role;
  @JsonKey(name: 'role_label')
  final String? roleLabel;
  @JsonKey(name: 'effective_from')
  final String effectiveFrom;
  final List<Components3> components;
  final String? note;
  @JsonKey(name: 'updated_at')
  final String? updatedAt;

  Map<String, Object?> toJson() => _$Data204ToJson(this);
}
