// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'halls4.dart';

part 'events2.g.dart';

@JsonSerializable()
class Events2 {
  const Events2({
    required this.uuid,
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
    required this.guestsGuaranteed,
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
  });

  factory Events2.fromJson(Map<String, Object?> json) =>
      _$Events2FromJson(json);

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
  final List<Halls4> halls;
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

  Map<String, Object?> toJson() => _$Events2ToJson(this);
}
