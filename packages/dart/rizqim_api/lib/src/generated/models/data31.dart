// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data31.g.dart';

@JsonSerializable()
class Data31 {
  const Data31({
    required this.code,
    required this.expiresAt,
    required this.type,
    required this.branchId,
  });

  factory Data31.fromJson(Map<String, Object?> json) => _$Data31FromJson(json);

  final String code;
  @JsonKey(name: 'expires_at')
  final String expiresAt;
  final String type;
  @JsonKey(name: 'branch_id')
  final int? branchId;

  Map<String, Object?> toJson() => _$Data31ToJson(this);
}
