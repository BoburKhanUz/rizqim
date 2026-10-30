// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'staff23.dart';
import 'device24.dart';
import 'order13.dart';
import 'resolved_by3.dart';

part 'errors2.g.dart';

@JsonSerializable()
class Errors2 {
  const Errors2({
    required this.opId,
    required this.seq,
    required this.type,
    required this.typeLabel,
    required this.status,
    required this.reason,
    required this.message,
    required this.staff,
    required this.device,
    required this.occurredAt,
    required this.receivedAt,
    required this.appliedAt,
    required this.clockSkewSeconds,
    required this.order,
    required this.payload,
    required this.refs,
    required this.resolvedAt,
    required this.resolvedBy,
    required this.resolution,
    required this.resolutionNote,
  });

  factory Errors2.fromJson(Map<String, Object?> json) =>
      _$Errors2FromJson(json);

  @JsonKey(name: 'op_id')
  final String opId;
  final int seq;
  final String type;
  @JsonKey(name: 'type_label')
  final String typeLabel;
  final String status;
  final String? reason;
  final String? message;
  final Staff23? staff;
  final Device24? device;
  @JsonKey(name: 'occurred_at')
  final String? occurredAt;
  @JsonKey(name: 'received_at')
  final String? receivedAt;
  @JsonKey(name: 'applied_at')
  final String? appliedAt;
  @JsonKey(name: 'clock_skew_seconds')
  final int? clockSkewSeconds;
  final Order13? order;
  final Map<String, dynamic> payload;
  final Map<String, dynamic> refs;
  @JsonKey(name: 'resolved_at')
  final String? resolvedAt;
  @JsonKey(name: 'resolved_by')
  final ResolvedBy3? resolvedBy;
  final String? resolution;
  @JsonKey(name: 'resolution_note')
  final String? resolutionNote;

  Map<String, Object?> toJson() => _$Errors2ToJson(this);
}
