// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data53.g.dart';

@JsonSerializable()
class Data53 {
  const Data53({
    required this.id,
    required this.branchId,
    required this.type,
    required this.subjectId,
    required this.subjectUuid,
    required this.name,
    required this.reason,
    required this.until,
    required this.createdBy,
    required this.createdAt,
  });

  factory Data53.fromJson(Map<String, Object?> json) => _$Data53FromJson(json);

  final int id;
  @JsonKey(name: 'branch_id')
  final int branchId;
  final String type;
  @JsonKey(name: 'subject_id')
  final int subjectId;
  @JsonKey(name: 'subject_uuid')
  final dynamic subjectUuid;
  final dynamic name;
  final String? reason;
  final String? until;
  @JsonKey(name: 'created_by')
  final int? createdBy;
  @JsonKey(name: 'created_at')
  final String? createdAt;

  Map<String, Object?> toJson() => _$Data53ToJson(this);
}
