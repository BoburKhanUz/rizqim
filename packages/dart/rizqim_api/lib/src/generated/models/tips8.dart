// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'staff19.dart';
import 'holders8.dart';
import 'recorded_by8.dart';
import 'corrections9.dart';

part 'tips8.g.dart';

@JsonSerializable()
class Tips8 {
  const Tips8({
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

  factory Tips8.fromJson(Map<String, Object?> json) => _$Tips8FromJson(json);

  final String uuid;
  final String method;
  final String? source;
  final String amount;
  @JsonKey(name: 'original_amount')
  final String originalAmount;
  final String mode;
  final Staff19? staff;
  final List<Holders8> holders;
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
  final RecordedBy8? recordedBy;
  final bool closed;
  final List<Corrections9> corrections;
  @JsonKey(name: 'created_at')
  final String? createdAt;

  Map<String, Object?> toJson() => _$Tips8ToJson(this);
}
