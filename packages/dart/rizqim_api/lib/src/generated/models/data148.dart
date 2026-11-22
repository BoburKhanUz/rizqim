// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'by_status.dart';
import 'overdue.dart';
import 'rows.dart';

part 'data148.g.dart';

@JsonSerializable()
class Data148 {
  const Data148({
    required this.from,
    required this.to,
    required this.branchId,
    required this.events,
    required this.cancelled,
    required this.byStatus,
    required this.guests,
    required this.total,
    required this.received,
    required this.paid,
    required this.outstanding,
    required this.overdue,
    required this.retained,
    required this.refunded,
    required this.transferPending,
    required this.rows,
  });

  factory Data148.fromJson(Map<String, Object?> json) =>
      _$Data148FromJson(json);

  final String from;
  final String to;
  @JsonKey(name: 'branch_id')
  final int? branchId;
  final int events;
  final int cancelled;
  @JsonKey(name: 'by_status')
  final List<ByStatus> byStatus;
  final int guests;
  final String total;
  final String received;
  final String paid;
  final String outstanding;
  final Overdue overdue;
  final String retained;
  final String refunded;
  @JsonKey(name: 'transfer_pending')
  final String transferPending;
  final List<Rows> rows;

  Map<String, Object?> toJson() => _$Data148ToJson(this);
}
