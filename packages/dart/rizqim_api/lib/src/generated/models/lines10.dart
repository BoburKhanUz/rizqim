// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'metrics3.dart';
import 'components6.dart';

part 'lines10.g.dart';

@JsonSerializable()
class Lines10 {
  const Lines10({
    required this.uuid,
    required this.userId,
    required this.userName,
    required this.role,
    required this.roleLabel,
    required this.metrics,
    required this.components,
    required this.gross,
    required this.advances,
    required this.adjustment,
    required this.adjustmentReason,
    required this.payable,
    required this.paid,
    required this.paidAt,
    required this.paidMethod,
    required this.paidNote,
    required this.cashMovementId,
  });

  factory Lines10.fromJson(Map<String, Object?> json) =>
      _$Lines10FromJson(json);

  final String uuid;
  @JsonKey(name: 'user_id')
  final int? userId;
  @JsonKey(name: 'user_name')
  final String? userName;
  final String? role;
  @JsonKey(name: 'role_label')
  final String? roleLabel;
  final Metrics3 metrics;
  final List<Components6> components;
  final String gross;
  final String advances;
  final String adjustment;
  @JsonKey(name: 'adjustment_reason')
  final String? adjustmentReason;
  final String payable;
  final bool paid;
  @JsonKey(name: 'paid_at')
  final String? paidAt;
  @JsonKey(name: 'paid_method')
  final String? paidMethod;
  @JsonKey(name: 'paid_note')
  final String? paidNote;
  @JsonKey(name: 'cash_movement_id')
  final int? cashMovementId;

  Map<String, Object?> toJson() => _$Lines10ToJson(this);
}
