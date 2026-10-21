// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'waiter.dart';

part 'orders.g.dart';

@JsonSerializable()
class Orders {
  const Orders({
    required this.uuid,
    required this.shortCode,
    required this.status,
    required this.channel,
    required this.version,
    required this.openedAt,
    required this.openMinutes,
    required this.overdue,
    required this.billPrinted,
    required this.guestsCount,
    required this.waiter,
    required this.totalPrice,
    required this.itemsCount,
    required this.unsentCount,
  });

  factory Orders.fromJson(Map<String, Object?> json) => _$OrdersFromJson(json);

  final String uuid;
  @JsonKey(name: 'short_code')
  final String? shortCode;
  final String status;
  final String channel;
  final int version;
  @JsonKey(name: 'opened_at')
  final String? openedAt;
  @JsonKey(name: 'open_minutes')
  final int openMinutes;
  final bool overdue;
  @JsonKey(name: 'bill_printed')
  final bool billPrinted;
  @JsonKey(name: 'guests_count')
  final int? guestsCount;
  final Waiter? waiter;
  @JsonKey(name: 'total_price')
  final String totalPrice;
  @JsonKey(name: 'items_count')
  final int itemsCount;
  @JsonKey(name: 'unsent_count')
  final int unsentCount;

  Map<String, Object?> toJson() => _$OrdersToJson(this);
}
