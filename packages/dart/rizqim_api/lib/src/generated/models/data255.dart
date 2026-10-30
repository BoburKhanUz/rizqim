// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'data255.g.dart';

@JsonSerializable()
class Data255 {
  const Data255({
    required this.uuid,
    required this.branchId,
    required this.businessDate,
    required this.guestName,
    required this.guestPhone,
    required this.guestPhoneMasked,
    required this.partySize,
    required this.quotedWaitMinutes,
    required this.note,
    required this.status,
    required this.statusLabel,
    required this.position,
    required this.waitedMinutes,
    required this.diningTableId,
    required this.tableName,
    required this.orderUuid,
    required this.notifiedAt,
    required this.seatedAt,
    required this.closedAt,
    required this.version,
    required this.createdAt,
  });

  factory Data255.fromJson(Map<String, Object?> json) =>
      _$Data255FromJson(json);

  final String uuid;
  @JsonKey(name: 'branch_id')
  final int branchId;
  @JsonKey(name: 'business_date')
  final String businessDate;
  @JsonKey(name: 'guest_name')
  final String guestName;
  @JsonKey(name: 'guest_phone')
  final String? guestPhone;
  @JsonKey(name: 'guest_phone_masked')
  final String? guestPhoneMasked;
  @JsonKey(name: 'party_size')
  final int partySize;
  @JsonKey(name: 'quoted_wait_minutes')
  final int? quotedWaitMinutes;
  final String? note;
  final String status;
  @JsonKey(name: 'status_label')
  final String statusLabel;
  final int? position;
  @JsonKey(name: 'waited_minutes')
  final int waitedMinutes;
  @JsonKey(name: 'dining_table_id')
  final int? diningTableId;
  @JsonKey(name: 'table_name')
  final String? tableName;
  @JsonKey(name: 'order_uuid')
  final String? orderUuid;
  @JsonKey(name: 'notified_at')
  final String? notifiedAt;
  @JsonKey(name: 'seated_at')
  final String? seatedAt;
  @JsonKey(name: 'closed_at')
  final String? closedAt;
  final int version;
  @JsonKey(name: 'created_at')
  final String? createdAt;

  Map<String, Object?> toJson() => _$Data255ToJson(this);
}
