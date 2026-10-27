// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'actor2.dart';
import 'device10.dart';

part 'data46.g.dart';

@JsonSerializable()
class Data46 {
  const Data46({
    required this.id,
    required this.action,
    required this.actor,
    required this.device,
    required this.subjectType,
    required this.subjectId,
    required this.branchId,
    required this.before,
    required this.after,
    required this.meta,
    required this.ip,
    required this.createdAt,
  });

  factory Data46.fromJson(Map<String, Object?> json) => _$Data46FromJson(json);

  final int id;
  final String action;
  final Actor2? actor;
  final Device10? device;
  @JsonKey(name: 'subject_type')
  final String? subjectType;
  @JsonKey(name: 'subject_id')
  final int? subjectId;
  @JsonKey(name: 'branch_id')
  final int? branchId;
  final Map<String, dynamic>? before;
  final Map<String, dynamic>? after;
  final Map<String, dynamic>? meta;
  final String? ip;
  @JsonKey(name: 'created_at')
  final String createdAt;

  Map<String, Object?> toJson() => _$Data46ToJson(this);
}
