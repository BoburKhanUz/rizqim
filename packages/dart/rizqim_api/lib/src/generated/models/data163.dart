// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data163.g.dart';

@JsonSerializable()
class Data163 {
  const Data163({
    required this.id,
    required this.branchId,
    required this.type,
    required this.subjectId,
    required this.subjectUuid,
    required this.name,
    required this.reason,
    required this.until,
    required this.createdBy,
    required this.createdByName,
    required this.createdAt,
  });

  factory Data163.fromJson(Map<String, Object?> json) =>
      _$Data163FromJson(json);

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

  /// The store's own staff only (a super admin's entry shows no name)
  @JsonKey(name: 'created_by_name')
  final String? createdByName;
  @JsonKey(name: 'created_at')
  final String? createdAt;

  Map<String, Object?> toJson() => _$Data163ToJson(this);
}
