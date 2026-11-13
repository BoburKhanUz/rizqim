// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'staff21.dart';
import 'holders9.dart';
import 'recorded_by9.dart';
import 'corrections10.dart';

part 'tips9.g.dart';

@JsonSerializable()
class Tips9 {
  const Tips9({
    required this.uuid,
    required this.method,
    required this.source,
    required this.amount,
    required this.originalAmount,
    required this.mode,
    required this.staff,
    required this.holders,
    required this.businessDate,
    required this.orderUuid,
    required this.billUuid,
    required this.paymentUuid,
    required this.shiftUuid,
    required this.recordedBy,
    required this.closed,
    required this.corrections,
    required this.createdAt,
  });

  factory Tips9.fromJson(Map<String, Object?> json) => _$Tips9FromJson(json);

  final String uuid;
  final String method;
  final String? source;
  final String amount;
  @JsonKey(name: 'original_amount')
  final String originalAmount;
  final String mode;
  final Staff21? staff;
  final List<Holders9> holders;
  @JsonKey(name: 'business_date')
  final String businessDate;
  @JsonKey(name: 'order_uuid')
  final String? orderUuid;
  @JsonKey(name: 'bill_uuid')
  final String? billUuid;
  @JsonKey(name: 'payment_uuid')
  final String? paymentUuid;
  @JsonKey(name: 'shift_uuid')
  final String? shiftUuid;
  @JsonKey(name: 'recorded_by')
  final RecordedBy9? recordedBy;
  final bool closed;
  final List<Corrections10> corrections;
  @JsonKey(name: 'created_at')
  final String? createdAt;

  Map<String, Object?> toJson() => _$Tips9ToJson(this);
}
