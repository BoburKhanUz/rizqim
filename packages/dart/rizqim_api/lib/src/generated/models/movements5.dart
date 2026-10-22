// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'employee6.dart';
import 'user24.dart';
import 'approver6.dart';

part 'movements5.g.dart';

@JsonSerializable()
class Movements5 {
  const Movements5({
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

  factory Movements5.fromJson(Map<String, Object?> json) =>
      _$Movements5FromJson(json);

  final String uuid;
  final String type;
  final String? category;
  final String amount;
  final String? note;
  final Employee6? employee;
  final User24? user;
  @JsonKey(name: 'approved_by')
  final int? approvedBy;
  final Approver6? approver;
  @JsonKey(name: 'created_at')
  final String? createdAt;

  Map<String, Object?> toJson() => _$Movements5ToJson(this);
}
