// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'staff7.g.dart';

@JsonSerializable()
class Staff7 {
  const Staff7({
    required this.id,
    required this.name,
    required this.role,
    required this.roleLabel,
    required this.branchId,
  });

  factory Staff7.fromJson(Map<String, Object?> json) => _$Staff7FromJson(json);

  final int id;
  final String name;
  final String role;
  @JsonKey(name: 'role_label')
  final String roleLabel;
  @JsonKey(name: 'branch_id')
  final int? branchId;

  Map<String, Object?> toJson() => _$Staff7ToJson(this);
}
