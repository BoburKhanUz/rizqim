// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'rows.g.dart';

@JsonSerializable()
class Rows {
  const Rows({
    required this.uuid,
    required this.eventDate,
    required this.slot,
    required this.eventType,
    required this.title,
    required this.branchId,
    required this.customerName,
    required this.customerPhone,
    required this.status,
    required this.statusLabel,
    required this.guestsGuaranteed,
    required this.guestsMinimum,
    required this.guestsActual,
    required this.guests,
    required this.total,
    required this.paid,
    required this.refunded,
    required this.retained,
    required this.outstanding,
    required this.overdue,
    required this.transferPending,
  });

  factory Rows.fromJson(Map<String, Object?> json) => _$RowsFromJson(json);

  final String uuid;
  @JsonKey(name: 'event_date')
  final String eventDate;
  final String slot;
  @JsonKey(name: 'event_type')
  final String eventType;
  final String? title;
  @JsonKey(name: 'branch_id')
  final int branchId;
  @JsonKey(name: 'customer_name')
  final String customerName;
  @JsonKey(name: 'customer_phone')
  final String? customerPhone;
  final String status;
  @JsonKey(name: 'status_label')
  final String statusLabel;
  @JsonKey(name: 'guests_guaranteed')
  final int guestsGuaranteed;
  @JsonKey(name: 'guests_minimum')
  final int guestsMinimum;
  @JsonKey(name: 'guests_actual')
  final int? guestsActual;
  final int guests;
  final String total;
  final String paid;
  final String refunded;
  final String retained;
  final String outstanding;
  final String overdue;
  @JsonKey(name: 'transfer_pending')
  final String transferPending;

  Map<String, Object?> toJson() => _$RowsToJson(this);
}
