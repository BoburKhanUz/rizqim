// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'items10.dart';
import 'payments14.dart';

part 'data147.g.dart';

@JsonSerializable()
class Data147 {
  const Data147({
    required this.sourceLabel,
    required this.branchId,
    required this.diningTableId,
    required this.tableIds,
    required this.startsAt,
    required this.endsAt,
    required this.guestsPlanned,
    required this.eventType,
    required this.eventTypeLabel,
    required this.contactName,
    required this.contactPhone,
    required this.source,
    required this.uuid,
    required this.status,
    required this.statusLabel,
    required this.minSpend,
    required this.note,
    required this.orderUuid,
    required this.depositTotal,
    required this.depositAvailable,
    required this.depositUnsettled,
    required this.itemsCount,
    required this.payments,
    required this.version,
    required this.createdBy,
    required this.confirmedAt,
    required this.arrivedAt,
    required this.completedAt,
    required this.canceledAt,
    required this.confirmationSentAt,
    required this.confirmationChannel,
    required this.createdAt,
    required this.updatedAt,
    required this.items,
    required this.cancelReason,
  });

  factory Data147.fromJson(Map<String, Object?> json) =>
      _$Data147FromJson(json);

  final String uuid;
  @JsonKey(name: 'branch_id')
  final int branchId;
  @JsonKey(name: 'dining_table_id')
  final int? diningTableId;
  @JsonKey(name: 'table_ids')
  final List<int> tableIds;
  @JsonKey(name: 'starts_at')
  final String startsAt;
  @JsonKey(name: 'ends_at')
  final String endsAt;
  @JsonKey(name: 'guests_planned')
  final int guestsPlanned;
  @JsonKey(name: 'event_type')
  final String eventType;
  @JsonKey(name: 'event_type_label')
  final String eventTypeLabel;
  @JsonKey(name: 'contact_name')
  final String contactName;
  @JsonKey(name: 'contact_phone')
  final String? contactPhone;
  final String source;
  @JsonKey(name: 'source_label')
  final String sourceLabel;
  final String status;
  @JsonKey(name: 'status_label')
  final String statusLabel;
  @JsonKey(name: 'min_spend')
  final String? minSpend;
  final String? note;
  @JsonKey(name: 'order_uuid')
  final String? orderUuid;
  @JsonKey(name: 'deposit_total')
  final String depositTotal;
  @JsonKey(name: 'deposit_available')
  final String depositAvailable;
  @JsonKey(name: 'deposit_unsettled')
  final bool depositUnsettled;
  @JsonKey(name: 'items_count')
  final int itemsCount;
  @JsonKey(name: 'cancel_reason')
  final String? cancelReason;
  final int version;
  @JsonKey(name: 'created_by')
  final int? createdBy;
  @JsonKey(name: 'confirmed_at')
  final String? confirmedAt;
  @JsonKey(name: 'arrived_at')
  final String? arrivedAt;
  @JsonKey(name: 'completed_at')
  final String? completedAt;
  @JsonKey(name: 'canceled_at')
  final String? canceledAt;
  @JsonKey(name: 'confirmation_sent_at')
  final String? confirmationSentAt;
  @JsonKey(name: 'confirmation_channel')
  final String? confirmationChannel;
  @JsonKey(name: 'created_at')
  final String? createdAt;
  @JsonKey(name: 'updated_at')
  final String? updatedAt;
  final List<Items10> items;
  final List<Payments14> payments;

  Map<String, Object?> toJson() => _$Data147ToJson(this);
}
