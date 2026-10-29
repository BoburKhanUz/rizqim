// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'pickup_status.dart';

part 'data176.g.dart';

@JsonSerializable()
class Data176 {
  const Data176({
    required this.orderUuid,
    required this.number,
    required this.businessDate,
    required this.status,
    required this.channel,
    required this.orderStatus,
    required this.readyAt,
    required this.calledAt,
    required this.callCount,
    required this.handedAt,
    required this.openedAt,
  });

  factory Data176.fromJson(Map<String, Object?> json) =>
      _$Data176FromJson(json);

  @JsonKey(name: 'order_uuid')
  final String orderUuid;
  final int number;
  @JsonKey(name: 'business_date')
  final String? businessDate;
  final PickupStatus? status;
  final String channel;
  @JsonKey(name: 'order_status')
  final String orderStatus;
  @JsonKey(name: 'ready_at')
  final String? readyAt;
  @JsonKey(name: 'called_at')
  final String? calledAt;
  @JsonKey(name: 'call_count')
  final int callCount;
  @JsonKey(name: 'handed_at')
  final String? handedAt;
  @JsonKey(name: 'opened_at')
  final String? openedAt;

  Map<String, Object?> toJson() => _$Data176ToJson(this);
}
