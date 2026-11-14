// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'halls3.dart';
import 'quote.dart';
import 'lines16.dart';
import 'payments4.dart';
import 'warnings9.dart';

part 'data219.g.dart';

@JsonSerializable()
class Data219 {
  const Data219({
    required this.guestsGuaranteed,
    required this.branchId,
    required this.eventDate,
    required this.slot,
    required this.slotLabel,
    required this.startTime,
    required this.endTime,
    required this.startsAt,
    required this.endsAt,
    required this.hallIds,
    required this.halls,
    required this.eventType,
    required this.eventTypeLabel,
    required this.status,
    required this.statusLabel,
    required this.title,
    required this.customerName,
    required this.customerPhone,
    required this.uuid,
    required this.guestsMinimum,
    required this.guestsActual,
    required this.guestsBilled,
    required this.packageId,
    required this.packageName,
    required this.total,
    required this.paidTotal,
    required this.outstanding,
    required this.holdExpiresAt,
    required this.frozen,
    required this.contractNo,
    required this.version,
    required this.overdue,
    required this.packageSnapshot,
    required this.packageTotal,
    required this.warnings,
    required this.extrasTotal,
    required this.ordersTotal,
    required this.quote,
    required this.frozenAt,
    required this.note,
    required this.orderUuid,
    required this.cancelReason,
    required this.createdBy,
    required this.confirmedAt,
    required this.startedAt,
    required this.settledAt,
    required this.cancelledAt,
    required this.releasedAt,
    required this.createdAt,
    required this.updatedAt,
    required this.lines,
    required this.payments,
    required this.hallTotal,
  });

  factory Data219.fromJson(Map<String, Object?> json) =>
      _$Data219FromJson(json);

  final String uuid;
  @JsonKey(name: 'branch_id')
  final int branchId;
  @JsonKey(name: 'event_date')
  final String eventDate;
  final String slot;
  @JsonKey(name: 'slot_label')
  final String slotLabel;
  @JsonKey(name: 'start_time')
  final String startTime;
  @JsonKey(name: 'end_time')
  final String endTime;
  @JsonKey(name: 'starts_at')
  final String startsAt;
  @JsonKey(name: 'ends_at')
  final String endsAt;
  @JsonKey(name: 'hall_ids')
  final List<int> hallIds;
  final List<Halls3> halls;
  @JsonKey(name: 'event_type')
  final String eventType;
  @JsonKey(name: 'event_type_label')
  final String eventTypeLabel;
  final String status;
  @JsonKey(name: 'status_label')
  final String statusLabel;
  final String? title;
  @JsonKey(name: 'customer_name')
  final String customerName;
  @JsonKey(name: 'customer_phone')
  final String? customerPhone;
  @JsonKey(name: 'guests_guaranteed')
  final int guestsGuaranteed;
  @JsonKey(name: 'guests_minimum')
  final int guestsMinimum;
  @JsonKey(name: 'guests_actual')
  final int? guestsActual;
  @JsonKey(name: 'guests_billed')
  final int guestsBilled;
  @JsonKey(name: 'package_id')
  final int? packageId;
  @JsonKey(name: 'package_name')
  final String? packageName;
  final String total;
  @JsonKey(name: 'paid_total')
  final String paidTotal;
  final String outstanding;
  @JsonKey(name: 'hold_expires_at')
  final String? holdExpiresAt;
  final bool frozen;
  @JsonKey(name: 'contract_no')
  final String? contractNo;
  final int version;
  final bool overdue;
  @JsonKey(name: 'package_snapshot')
  final Map<String, dynamic>? packageSnapshot;
  @JsonKey(name: 'package_total')
  final String packageTotal;
  @JsonKey(name: 'hall_total')
  final String hallTotal;
  @JsonKey(name: 'extras_total')
  final String extrasTotal;
  @JsonKey(name: 'orders_total')
  final String ordersTotal;
  final Quote? quote;
  @JsonKey(name: 'frozen_at')
  final String? frozenAt;
  final String? note;
  @JsonKey(name: 'order_uuid')
  final String? orderUuid;
  @JsonKey(name: 'cancel_reason')
  final String? cancelReason;
  @JsonKey(name: 'created_by')
  final int? createdBy;
  @JsonKey(name: 'confirmed_at')
  final String? confirmedAt;
  @JsonKey(name: 'started_at')
  final String? startedAt;
  @JsonKey(name: 'settled_at')
  final String? settledAt;
  @JsonKey(name: 'cancelled_at')
  final String? cancelledAt;
  @JsonKey(name: 'released_at')
  final String? releasedAt;
  @JsonKey(name: 'created_at')
  final String? createdAt;
  @JsonKey(name: 'updated_at')
  final String? updatedAt;
  final List<Lines16> lines;
  final List<Payments4> payments;
  final List<Warnings9> warnings;

  Map<String, Object?> toJson() => _$Data219ToJson(this);
}
