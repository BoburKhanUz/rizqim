// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'employee3.dart';
import 'user21.dart';
import 'approver3.dart';

part 'movements3.g.dart';

@JsonSerializable()
class Movements3 {
  const Movements3({
    required this.uuid,
    required this.type,
    required this.category,
    required this.amount,
    required this.note,
    required this.employee,
    required this.user,
    required this.approvedBy,
    required this.createdAt,
    this.approver,
  });

  factory Movements3.fromJson(Map<String, Object?> json) =>
      _$Movements3FromJson(json);

  final String uuid;
  final String type;
  final String? category;
  final String amount;
  final String? note;
  final Employee3? employee;
  final User21? user;
  @JsonKey(name: 'approved_by')
  final int? approvedBy;
  final Approver3? approver;
  @JsonKey(name: 'created_at')
  final String? createdAt;

  Map<String, Object?> toJson() => _$Movements3ToJson(this);
}
