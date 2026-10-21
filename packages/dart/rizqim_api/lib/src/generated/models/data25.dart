// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'actor.dart';
import 'device9.dart';

part 'data25.g.dart';

@JsonSerializable()
class Data25 {
  const Data25({
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

  factory Data25.fromJson(Map<String, Object?> json) => _$Data25FromJson(json);

  final int id;
  final String action;
  final Actor? actor;
  final Device9? device;
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

  Map<String, Object?> toJson() => _$Data25ToJson(this);
}
