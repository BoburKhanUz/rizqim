// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data57.g.dart';

@JsonSerializable()
class Data57 {
  const Data57({
    required this.code,
    required this.expiresAt,
    required this.type,
    required this.branchId,
  });

  factory Data57.fromJson(Map<String, Object?> json) => _$Data57FromJson(json);

  final String code;
  @JsonKey(name: 'expires_at')
  final String expiresAt;
  final String type;
  @JsonKey(name: 'branch_id')
  final int? branchId;

  Map<String, Object?> toJson() => _$Data57ToJson(this);
}
